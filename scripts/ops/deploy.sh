#!/usr/bin/env bash

set -euo pipefail

usage() {
  cat <<'USAGE'
deploy.sh — đưa mã tự viết lên máy chủ, kiểm khói, tự hoàn tác khi kiểm khói hỏng (đã chạy thật trên staging thử 29/10/2026 và trên VPS production 09/11/2026)

  scripts/ops/deploy.sh --env=staging|production --host=deploy@<máy> --domain=<tên miền> [--smoke-env=staging|production] [--skip-tests] [--no-smoke] [--yes-production] [--dry-run]
  scripts/ops/deploy.sh --env=... --host=... --domain=... --rollback-to=<tên-gói>

  --env              quyết định thư mục đích; production cần thêm --yes-production
  --host             ssh tới máy chủ, người dùng phải là deploy
  --domain           địa chỉ kiểm khói (https://<tên miền>)
  --smoke-env        chế độ kiểm khói, mặc định bằng --env. Máy production còn ở chế độ staging (noindex, trước ngày lên chính thức) thì đặt --smoke-env=staging; để mặc định, kiểm khói production đòi cho lập chỉ mục nên hỏng và tự hoàn tác cả bản tốt
  --skip-tests       bỏ bước chạy toàn bộ kiểm thử
  --no-smoke         bỏ kiểm khói (không khuyến khích)
  --rollback-to      hoàn tác tay về một gói trong /var/www/<site>/rollback
  --dry-run          chỉ in các bước
USAGE
}

ENV_NAME=""; HOST=""; DOMAIN=""; SMOKE_ENV=""; SMOKE_ENV_SET=0; SKIP_TESTS=0; NO_SMOKE=0; YES_PROD=0; DRY=0; ROLLBACK_TO=""
for arg in "$@"; do
  case "$arg" in
    --env=*) ENV_NAME="${arg#*=}" ;;
    --host=*) HOST="${arg#*=}" ;;
    --domain=*) DOMAIN="${arg#*=}" ;;
    --smoke-env=*) SMOKE_ENV="${arg#*=}"; SMOKE_ENV_SET=1 ;;
    --skip-tests) SKIP_TESTS=1 ;;
    --no-smoke) NO_SMOKE=1 ;;
    --yes-production) YES_PROD=1 ;;
    --dry-run) DRY=1 ;;
    --rollback-to=*) ROLLBACK_TO="${arg#*=}" ;;
    -h | --help) usage; exit 0 ;;
    *) echo "Tham số lạ: $arg (xem --help)" >&2; exit 2 ;;
  esac
done

[[ "$ENV_NAME" == "staging" || "$ENV_NAME" == "production" ]] || { echo "Cần --env=staging|production" >&2; exit 2; }
(( SMOKE_ENV_SET )) || SMOKE_ENV="$ENV_NAME"
[[ "$SMOKE_ENV" == "staging" || "$SMOKE_ENV" == "production" ]] || { echo "--smoke-env phải là staging hoặc production" >&2; exit 2; }
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
read -r -a SSH_EXTRA <<< "${DEPLOY_SSH_OPTS:-}"
SSH=(ssh ${SSH_EXTRA[@]+"${SSH_EXTRA[@]}"} -o BatchMode=yes -o ConnectTimeout=15 "$HOST")

step() { printf '\n== %s\n' "$*"; }
run() { if (( DRY )); then printf '   $ %s\n' "$*"; else "$@"; fi; }
remote() {
  if (( DRY )); then printf '   [máy chủ] %s\n' "$1"; else "${SSH[@]}" "$1"; fi
}

restore_bundle() {
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
  (( NO_SMOKE )) || run node "${REPO}/scripts/ops/smoke.mjs" "https://${DOMAIN}" "--env=${SMOKE_ENV}"
  exit 0
fi

step "1/8 Kiểm kho Git"
cd "$REPO"
if [[ -n "$(git status --porcelain)" ]]; then
  echo "Kho Git còn thay đổi chưa commit. Triển khai chỉ từ commit đã có để hoàn tác được." >&2
  git status --short >&2
  exit 1
fi
COMMIT="$(git rev-parse --short=12 HEAD)"
echo "   commit: ${COMMIT} ($(git log -1 --format=%s))"

step "2/8 Kiểm thử toàn bộ"
if (( SKIP_TESTS )); then
  echo "   (bỏ qua theo --skip-tests)"
else
  run node tests/run.mjs
fi

step "3/8 Build theme (dist/ không nằm trong Git)"
run bash -c "cd '${THEME}' && npm ci && npm run build"
(( DRY )) || [[ -f "${THEME}/dist/.vite/manifest.json" || -f "${THEME}/dist/manifest.json" ]] || { echo "Không thấy manifest của bản build: dừng." >&2; exit 1; }

step "4/8 Nén bản ĐANG CHẠY thành gói hoàn tác"
STAMP="$(date +%Y%m%d-%H%M%S)"
if (( DRY )); then
  RUNNING="(không đọc khi --dry-run)"
else
  RUNNING="$("${SSH[@]}" "cat '${SITE_DIR}/.deployed-ref' 2>/dev/null || echo khong-ro")"
fi
BUNDLE="${STAMP}-${RUNNING//[^0-9a-f]/}.tar.zst"
[[ "$BUNDLE" =~ ^[0-9]{8}-[0-9]{6}-[0-9a-f]{7,40}\.tar\.zst$ ]] || BUNDLE="${STAMP}-0000000.tar.zst"
echo "   bản đang chạy: ${RUNNING}  →  gói: ${ROLLBACK_DIR}/${BUNDLE}"
remote "set -euo pipefail; install -d -m 0750 '${ROLLBACK_DIR}'; cd '${ROOT}/wp-content'; \
  old=\$(ls -d themes/vgarco plugins/vgarco-core mu-plugins/vgarco-loader.php 2>/dev/null || true); \
  if [ -n \"\$old\" ]; then tar --zstd -cf '${ROLLBACK_DIR}/${BUNDLE}' \$old; else echo '   (chưa có mã cũ: không có gì để nén)'; fi"

THEME_EXCLUDES=(node_modules src scripts package.json package-lock.json vite.config.js)

push_dir() {
  local src=$1 dst=$2
  shift 2
  local ex=() p
  for p in "$@"; do ex+=("--exclude=$p"); done
  tar -C "$src" ${ex[@]+"${ex[@]}"} --mode='u=rwX,go=rX' -cf - . |
    "${SSH[@]}" "set -euo pipefail; rm -rf '${dst}.new' '${dst}.old'; install -d -m 0755 '${dst}.new'; tar -C '${dst}.new' --no-same-owner --no-same-permissions -xf -; [ ! -e '${dst}' ] || mv '${dst}' '${dst}.old'; mv '${dst}.new' '${dst}'; rm -rf '${dst}.old'"
}

push_loader() {
  local dst="${ROOT}/wp-content/mu-plugins"
  "${SSH[@]}" "set -euo pipefail; cat > '${dst}/.vgarco-loader.php.new'; chmod 0644 '${dst}/.vgarco-loader.php.new'; mv -f '${dst}/.vgarco-loader.php.new' '${dst}/vgarco-loader.php'" < "${REPO}/wp-content/mu-plugins/vgarco-loader.php"
}

if command -v rsync >/dev/null 2>&1; then
  step "5/8 rsync (chỉ ba thứ này; thuộc deploy, PHP chỉ đọc)"
  RSYNC=(rsync -a --delay-updates --itemize-changes -e "ssh ${DEPLOY_SSH_OPTS:-}")
  run "${RSYNC[@]}" "${REPO}/wp-content/mu-plugins/vgarco-loader.php" "${HOST}:${ROOT}/wp-content/mu-plugins/"
  run "${RSYNC[@]}" --delete-delay "${REPO}/wp-content/plugins/vgarco-core/" "${HOST}:${ROOT}/wp-content/plugins/vgarco-core/"
  run "${RSYNC[@]}" --delete-delay $(printf -- '--exclude %s ' "${THEME_EXCLUDES[@]}") "${THEME}/" "${HOST}:${ROOT}/wp-content/themes/vgarco/"
else
  step "5/8 tar qua ssh (máy này không có rsync; chỉ ba thứ này; mỗi thư mục dựng ở .new rồi đổi tên)"
  run push_loader
  run push_dir "${REPO}/wp-content/plugins/vgarco-core" "${ROOT}/wp-content/plugins/vgarco-core"
  run push_dir "${THEME}" "${ROOT}/wp-content/themes/vgarco" "${THEME_EXCLUDES[@]}"
fi

step "6/8 Reload PHP-FPM, xóa cache đối tượng và Cloudflare"
remote "set -euo pipefail; sudo systemctl reload php8.3-fpm; cd '${ROOT}'; wp cache flush --quiet || true; \
  wp eval 'if ( function_exists( \"vgarco_cf_purge\" ) ) { var_dump( vgarco_cf_purge() ); }' || true"
note_rewrite="   Nếu commit này đổi kiểu nội dung, phân loại hoặc quy tắc đường dẫn: chạy thêm  ssh ${HOST} 'cd ${ROOT} && wp rewrite flush'  (không tự chạy mỗi lần để khỏi xóa cache vô ích)."
echo "$note_rewrite"

step "7/8 Kiểm khói"
if (( NO_SMOKE )); then
  echo "   (bỏ qua theo --no-smoke) — bản mới CHƯA được xác nhận."
elif (( DRY )); then
  echo "   $ node scripts/ops/smoke.mjs https://${DOMAIN} --env=${SMOKE_ENV}   (nếu hỏng: giải gói ${BUNDLE}, reload, xóa cache, thoát 1)"
else
  [[ "$SMOKE_ENV" == "$ENV_NAME" ]] || echo "   kiểm khói theo chế độ ${SMOKE_ENV} (--env=${ENV_NAME} chỉ chọn thư mục đích)"
  if ! node "${REPO}/scripts/ops/smoke.mjs" "https://${DOMAIN}" "--env=${SMOKE_ENV}"; then
    if ! "${SSH[@]}" "test -f '${ROLLBACK_DIR}/${BUNDLE}'"; then
      echo "KIỂM KHÓI HỎNG — đây là lần triển khai đầu, không có bản cũ để hoàn tác. Mã mới vẫn nằm trên máy chủ; sửa lỗi rồi chạy lại. Xem docs/SU-CO.md." >&2
      exit 1
    fi
    echo "KIỂM KHÓI HỎNG — tự hoàn tác về ${BUNDLE}" >&2
    restore_bundle "$BUNDLE"
    node "${REPO}/scripts/ops/smoke.mjs" "https://${DOMAIN}" "--env=${SMOKE_ENV}" || echo "Sau hoàn tác kiểm khói VẪN hỏng: lỗi không nằm ở mã vừa đưa lên. Xem docs/SU-CO.md." >&2
    exit 1
  fi
fi

step "8/8 Ghi mã commit đang chạy, dọn gói hoàn tác cũ (giữ 5)"
remote "set -euo pipefail; echo '${COMMIT}' > '${SITE_DIR}/.deployed-ref'; cd '${ROLLBACK_DIR}'; (ls -1t *.tar.zst 2>/dev/null || true) | tail -n +6 | xargs -r rm -f --"
echo
echo "Xong: ${ENV_NAME} đang chạy commit ${COMMIT}. Gói hoàn tác của bản trước: ${ROLLBACK_DIR}/${BUNDLE}"
(( DRY )) && echo "(--dry-run: chưa có gì thay đổi trên máy chủ)"
exit 0
