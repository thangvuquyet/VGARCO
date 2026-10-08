#!/usr/bin/env bash
# deploy.sh — đưa mã tự viết (mu-plugin, plugin vgarco-core, theme vgarco) lên máy chủ, kiểm khói, TỰ HOÀN TÁC nếu kiểm khói hỏng.
#
# ╔════════════════════════════════════════════════════════════════════════════════════════════════════════════╗
# ║  BẢN THIẾT KẾ — CHƯA CHẠY TRÊN MÁY CHỦ THẬT. Mới kiểm được: cú pháp (bash -n) và --dry-run (in kế hoạch).    ║
# ║  Lần chạy thật đầu tiên PHẢI là trên staging. Production cần thêm --yes-production.                        ║
# ╚════════════════════════════════════════════════════════════════════════════════════════════════════════════╝
#
# Chạy trên MÁY BUILD (có Git, Node 20+, PHP 8.3, ssh, rsync), không chạy trên máy chủ:
#   scripts/ops/deploy.sh --env=staging --host=deploy@staging.example.vn --domain=staging.example.vn --dry-run
#   scripts/ops/deploy.sh --env=staging --host=deploy@staging.example.vn --domain=staging.example.vn
#   scripts/ops/deploy.sh --env=production --host=deploy@<máy> --domain=<tên miền> --yes-production
#
# Tham số:
#   --env=staging|production    quyết định thư mục đích (/var/www/vgarco-staging/public hoặc /var/www/vgarco/public) — khớp provision.sh
#   --host=<người-dùng@máy>     ssh tới máy chủ; người dùng PHẢI là `deploy` (sở hữu mã; PHP chỉ đọc)
#   --domain=<tên miền>         địa chỉ để kiểm khói (https://<tên miền>); bỏ qua kiểm khói bằng --no-smoke (không khuyến khích)
#   --skip-tests                bỏ bước `node tests/run.mjs` (chỉ khi đã chạy ngay trước đó trên đúng commit này)
#   --yes-production            bắt buộc khi --env=production
#   --dry-run                   chỉ in các bước, không đụng vào máy chủ; vẫn chạy phần kiểm trên máy build nếu không kèm --skip-tests
#
# Trình tự: (1) kiểm kho Git sạch → (2) kiểm thử toàn bộ → (3) build theme → (4) trên máy chủ: nén BẢN ĐANG CHẠY thành gói hoàn tác →
#   (5) rsync ba thư mục mã (--delay-updates: tệp mới chỉ vào chỗ ở cuối lượt chép) → (6) reload PHP-FPM (OPcache validate_timestamps=0) → xóa cache đối tượng + Cloudflare →
#   (7) smoke.mjs; nếu hỏng: giải gói hoàn tác, reload, xóa cache, thoát mã 1 → (8) ghi mã commit đang chạy, giữ 5 gói hoàn tác gần nhất.
#
# KHÔNG làm (có chủ ý): không đụng vào uploads, /srv/*/private, wp-config.php, plugin bên thứ ba (cài/cập nhật bằng WP-CLI theo plugins.lock.json, xem docs/TRIEN-KHAI.md mục 3);
#   không chạy nhập liệu hay lệnh đổi cơ sở dữ liệu. Thay đổi DỮ LIỆU không hoàn tác được bằng gói mã: khôi phục từ sao lưu (docs/SAO-LUU-PHUC-HOI.md).
#
# Hoàn tác tay một bản đã triển khai thành công: ssh deploy@<máy> 'ls -1 /var/www/<site>/rollback' rồi chạy lại với --rollback-to=<tên-gói> (xem bên dưới).

set -euo pipefail

ENV_NAME=""; HOST=""; DOMAIN=""; SKIP_TESTS=0; NO_SMOKE=0; YES_PROD=0; DRY=0; ROLLBACK_TO=""
for arg in "$@"; do
  case "$arg" in
    --env=*) ENV_NAME="${arg#*=}" ;;
    --host=*) HOST="${arg#*=}" ;;
    --domain=*) DOMAIN="${arg#*=}" ;;
    --skip-tests) SKIP_TESTS=1 ;;
    --no-smoke) NO_SMOKE=1 ;;
    --yes-production) YES_PROD=1 ;;
    --dry-run) DRY=1 ;;
    --rollback-to=*) ROLLBACK_TO="${arg#*=}" ;;
    -h | --help) sed -n '2,32p' "$0"; exit 0 ;;
    *) echo "Tham số lạ: $arg (xem --help)" >&2; exit 2 ;;
  esac
done

[[ "$ENV_NAME" == "staging" || "$ENV_NAME" == "production" ]] || { echo "Cần --env=staging|production" >&2; exit 2; }
[[ "$HOST" =~ ^deploy@[A-Za-z0-9._-]+$ ]] || { echo "--host phải dạng deploy@<máy> (người dùng deploy, không phải root)" >&2; exit 2; }
[[ -n "$DOMAIN" || $NO_SMOKE -eq 1 ]] || { echo "Cần --domain=<tên miền> để kiểm khói (hoặc --no-smoke)" >&2; exit 2; }
[[ -z "$DOMAIN" || "$DOMAIN" =~ ^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$ ]] || { echo "--domain không hợp lệ" >&2; exit 2; }
[[ "$ENV_NAME" == "staging" || $YES_PROD -eq 1 ]] || { echo "Production cần --yes-production (và nên đã triển khai staging cùng commit này)." >&2; exit 2; }

if [[ "$ENV_NAME" == "production" ]]; then SITE="vgarco"; else SITE="vgarco-staging"; fi
SITE_DIR="/var/www/${SITE}"
ROOT="${SITE_DIR}/public"
ROLLBACK_DIR="${SITE_DIR}/rollback"
REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
THEME="${REPO}/wp-content/themes/vgarco"
SSH=(ssh -o BatchMode=yes -o ConnectTimeout=15 "$HOST")

step() { printf '\n== %s\n' "$*"; }
run() { if (( DRY )); then printf '   $ %s\n' "$*"; else "$@"; fi; }
remote() { # remote '<lệnh>' — chạy trên máy chủ (một chuỗi lệnh bash); in thay vì chạy khi --dry-run
  if (( DRY )); then printf '   [máy chủ] %s\n' "$1"; else "${SSH[@]}" "$1"; fi
}

# ---- Hoàn tác tay theo tên gói ----------------------------------------------------------------------------------------------------
restore_bundle() { # restore_bundle <tên-gói>
  local bundle=$1
  [[ "$bundle" =~ ^[0-9]{8}-[0-9]{6}-[0-9a-f]{7,40}\.tar\.zst$ ]] || { echo "Tên gói hoàn tác không hợp lệ: $bundle" >&2; return 2; }
  step "Hoàn tác về gói ${bundle}"
  remote "set -euo pipefail; cd '${ROOT}/wp-content'; test -f '${ROLLBACK_DIR}/${bundle}'; \
    rm -rf themes/vgarco plugins/vgarco-core mu-plugins/vgarco-loader.php; \
    tar --zstd -xf '${ROLLBACK_DIR}/${bundle}'; \
    sudo systemctl reload php8.3-fpm; \
    (cd '${ROOT}' && wp cache flush --quiet || true; wp eval 'if ( function_exists( \"vgarco_cf_purge\" ) ) { vgarco_cf_purge(); }' --quiet || true)"
}

if [[ -n "$ROLLBACK_TO" ]]; then
  restore_bundle "$ROLLBACK_TO"
  (( NO_SMOKE )) || run node "${REPO}/scripts/ops/smoke.mjs" "https://${DOMAIN}" "--env=${ENV_NAME}"
  exit 0
fi

# ---- 1. Kho Git sạch, biết đang triển khai commit nào -----------------------------------------------------------------------------
step "1/8 Kiểm kho Git"
cd "$REPO"
if [[ -n "$(git status --porcelain)" ]]; then
  echo "Kho Git còn thay đổi chưa commit. Triển khai chỉ từ commit đã có để hoàn tác được." >&2
  git status --short >&2
  exit 1
fi
COMMIT="$(git rev-parse --short=12 HEAD)"
echo "   commit: ${COMMIT} ($(git log -1 --format=%s))"

# ---- 2. Kiểm thử --------------------------------------------------------------------------------------------------------------------
step "2/8 Kiểm thử toàn bộ"
if (( SKIP_TESTS )); then
  echo "   (bỏ qua theo --skip-tests)"
else
  node tests/run.mjs # đặt PHP=… PHP_EXT=… nếu php không nằm trong PATH; kết thúc "Tất cả đạt", lỗi thì dừng (set -e)
fi

# ---- 3. Build theme -----------------------------------------------------------------------------------------------------------------
step "3/8 Build theme (dist/ không nằm trong Git)"
run bash -c "cd '${THEME}' && npm ci && npm run build"
(( DRY )) || [[ -f "${THEME}/dist/.vite/manifest.json" || -f "${THEME}/dist/manifest.json" ]] || { echo "Không thấy manifest của bản build: dừng." >&2; exit 1; }

# ---- 4. Gói hoàn tác của bản đang chạy ----------------------------------------------------------------------------------------------
step "4/8 Nén bản ĐANG CHẠY thành gói hoàn tác"
STAMP="$(date +%Y%m%d-%H%M%S)"
if (( DRY )); then
  RUNNING="(không đọc khi --dry-run)"
else
  RUNNING="$("${SSH[@]}" "cat '${SITE_DIR}/.deployed-ref' 2>/dev/null || echo khong-ro")"
fi
BUNDLE="${STAMP}-${RUNNING//[^0-9a-f]/}.tar.zst"
[[ "$BUNDLE" =~ ^[0-9]{8}-[0-9]{6}-[0-9a-f]{7,40}\.tar\.zst$ ]] || BUNDLE="${STAMP}-0000000.tar.zst" # chưa từng triển khai / không rõ
echo "   bản đang chạy: ${RUNNING}  →  gói: ${ROLLBACK_DIR}/${BUNDLE}"
# Máy chủ mới (chưa có mã cũ): không có gì để nén — chỉ khi chạy lần đầu; có mã cũ mà nén lỗi thì dừng, không triển khai khi chưa có đường lui.
remote "set -euo pipefail; install -d -m 0750 '${ROLLBACK_DIR}'; cd '${ROOT}/wp-content'; \
  old=\$(ls -d themes/vgarco plugins/vgarco-core mu-plugins/vgarco-loader.php 2>/dev/null || true); \
  if [ -n \"\$old\" ]; then tar --zstd -cf '${ROLLBACK_DIR}/${BUNDLE}' \$old; else echo '   (chưa có mã cũ: không có gì để nén)'; fi"

# ---- 5. Đưa mã lên ------------------------------------------------------------------------------------------------------------------
step "5/8 rsync (chỉ ba thứ này; thuộc deploy, PHP chỉ đọc)"
RSYNC=(rsync -a --delay-updates --itemize-changes)
run "${RSYNC[@]}" "${REPO}/wp-content/mu-plugins/vgarco-loader.php" "${HOST}:${ROOT}/wp-content/mu-plugins/"
run "${RSYNC[@]}" --delete-delay "${REPO}/wp-content/plugins/vgarco-core/" "${HOST}:${ROOT}/wp-content/plugins/vgarco-core/"
run "${RSYNC[@]}" --delete-delay --exclude node_modules --exclude src --exclude scripts --exclude package.json --exclude package-lock.json --exclude vite.config.js \
  "${THEME}/" "${HOST}:${ROOT}/wp-content/themes/vgarco/"

# ---- 6. Nạp lại mã và xóa cache ------------------------------------------------------------------------------------------------------
step "6/8 Reload PHP-FPM, xóa cache đối tượng và Cloudflare"
# Chỉ reload khi cần: không reload thì OPcache (validate_timestamps=0) vẫn chạy mã CŨ.
remote "set -euo pipefail; sudo systemctl reload php8.3-fpm; cd '${ROOT}'; wp cache flush --quiet || true; \
  wp eval 'if ( function_exists( \"vgarco_cf_purge\" ) ) { var_dump( vgarco_cf_purge() ); }' || true"
note_rewrite="   Nếu commit này đổi kiểu nội dung, phân loại hoặc quy tắc đường dẫn: chạy thêm  ssh ${HOST} 'cd ${ROOT} && wp rewrite flush'  (không tự chạy mỗi lần để khỏi xóa cache vô ích)."
echo "$note_rewrite"

# ---- 7. Kiểm khói + tự hoàn tác ----------------------------------------------------------------------------------------------------------
step "7/8 Kiểm khói"
if (( NO_SMOKE )); then
  echo "   (bỏ qua theo --no-smoke) — bản mới CHƯA được xác nhận."
elif (( DRY )); then
  echo "   $ node scripts/ops/smoke.mjs https://${DOMAIN} --env=${ENV_NAME}   (nếu hỏng: giải gói ${BUNDLE}, reload, xóa cache, thoát 1)"
else
  if ! node "${REPO}/scripts/ops/smoke.mjs" "https://${DOMAIN}" "--env=${ENV_NAME}"; then
    echo "KIỂM KHÓI HỎNG — tự hoàn tác về ${BUNDLE}" >&2
    restore_bundle "$BUNDLE"
    node "${REPO}/scripts/ops/smoke.mjs" "https://${DOMAIN}" "--env=${ENV_NAME}" || echo "Sau hoàn tác kiểm khói VẪN hỏng: lỗi không nằm ở mã vừa đưa lên. Xem docs/SU-CO.md." >&2
    exit 1
  fi
fi

# ---- 8. Ghi nhận ---------------------------------------------------------------------------------------------------------------------
step "8/8 Ghi mã commit đang chạy, dọn gói hoàn tác cũ (giữ 5)"
remote "set -euo pipefail; echo '${COMMIT}' > '${SITE_DIR}/.deployed-ref'; cd '${ROLLBACK_DIR}'; ls -1t *.tar.zst 2>/dev/null | tail -n +6 | xargs -r rm -f --"
echo
echo "Xong: ${ENV_NAME} đang chạy commit ${COMMIT}. Gói hoàn tác của bản trước: ${ROLLBACK_DIR}/${BUNDLE}"
(( DRY )) && echo "(--dry-run: chưa có gì thay đổi trên máy chủ)"
exit 0
