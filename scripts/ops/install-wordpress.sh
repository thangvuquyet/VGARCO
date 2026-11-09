#!/usr/bin/env bash

set -euo pipefail

usage() {
  cat <<'USAGE'
install-wordpress.sh — cài lõi WordPress, wp-config.php và tài khoản quản trị cho môi trường đã dựng bằng provision.sh (bản rời chưa tham số hóa đã chạy thật trên VM thử 29/10/2026; bản này chưa chạy trên máy chủ)

  sudo ./install-wordpress.sh --env=staging|production --domain=<tên miền> --admin-email=<hộp thư vận hành> [--admin-user=<tên>] [--wp-env=staging|production] [--title=<tên site>] [--dry-run]

  --env          môi trường đã dựng bằng provision.sh (chọn người dùng PHP, CSDL, thư mục)
  --domain       tên miền của site (https)
  --admin-email  hộp thư người vận hành đọc hằng ngày (admin_email của WordPress: nhận cảnh báo khẩn)
  --admin-user   tài khoản quản trị đầu tiên (mặc định vg_quantri; không được là admin)
  --wp-env       WP_ENVIRONMENT_TYPE (mặc định bằng --env); đặt staging khi dựng production để chưa lập chỉ mục và chặn thư ra ngoài cho tới ngày mở
  --title        tên site (mặc định VGARCO)
  --dry-run      chỉ in kế hoạch, không đổi gì
USAGE
}

DRY=0
ENV_NAME=""
DOMAIN=""
ADMIN_EMAIL=""
ADMIN_USER="vg_quantri"
WP_ENV=""
TITLE="VGARCO"
WP_VERSION="7.1.4"
WP_LOCALE="vi"
TABLE_PREFIX="vgx_"
SECRETS_DIR="/etc/vgarco"
PW_FILE="/home/deploy/.wp-admin-initial-password"

for arg in "$@"; do
  case "$arg" in
    --env=*) ENV_NAME="${arg#*=}" ;;
    --domain=*) DOMAIN="${arg#*=}" ;;
    --admin-email=*) ADMIN_EMAIL="${arg#*=}" ;;
    --admin-user=*) ADMIN_USER="${arg#*=}" ;;
    --wp-env=*) WP_ENV="${arg#*=}" ;;
    --title=*) TITLE="${arg#*=}" ;;
    --dry-run) DRY=1 ;;
    -h | --help) usage; exit 0 ;;
    *) echo "Tham số lạ: $arg (xem --help)" >&2; exit 2 ;;
  esac
done

[[ "$ENV_NAME" == "staging" || "$ENV_NAME" == "production" ]] || { echo "Cần --env=staging|production" >&2; exit 2; }
WP_ENV="${WP_ENV:-$ENV_NAME}"
[[ "$WP_ENV" == "staging" || "$WP_ENV" == "production" ]] || { echo "--wp-env phải là staging hoặc production" >&2; exit 2; }
[[ "$DOMAIN" =~ ^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$ ]] || { echo "Cần --domain=<tên miền hợp lệ, chữ thường>" >&2; exit 2; }
[[ "$ADMIN_EMAIL" =~ ^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$ ]] || { echo "Cần --admin-email=<địa chỉ thư hợp lệ>" >&2; exit 2; }
[[ "$ADMIN_USER" =~ ^[a-z][a-z0-9_]{3,30}$ && "$ADMIN_USER" != "admin" ]] || { echo "--admin-user: chữ thường, số hoặc gạch dưới, từ 4 ký tự, không phải admin" >&2; exit 2; }
[[ -n "$TITLE" ]] || { echo "--title không được để trống" >&2; exit 2; }

if [[ "$ENV_NAME" == "production" ]]; then
  SITE="vgarco"
else
  SITE="vgarco-staging"
fi
PHP_USER="$SITE"
WEB_ROOT="/var/www/${SITE}/public"
PRIVATE_DIR="/srv/${SITE}/private"
DB_FILE="${SECRETS_DIR}/${ENV_NAME}-db.php"
CONFIG="${WEB_ROOT}/wp-config.php"

log() { printf '\n== %s\n' "$*"; }
note() { printf '   %s\n' "$*"; }
wpd() { sudo -u deploy -H wp --path="$WEB_ROOT" "$@"; }

if (( DRY )); then
  echo "(--dry-run: chỉ in kế hoạch; không kiểm quyền root, không đổi gì)"
  echo "Môi trường: ${ENV_NAME} | WP_ENVIRONMENT_TYPE: ${WP_ENV} | tên miền: ${DOMAIN} | thư mục web: ${WEB_ROOT}"
  note "1. wp core download --version=${WP_VERSION} --locale=${WP_LOCALE} --skip-content (WP-CLI tự kiểm mã băm)"
  note "2. ghi ${CONFIG} (0640 deploy:${PHP_USER}); nạp ${DB_FILE}, ${SECRETS_DIR}/redis.php, ${SECRETS_DIR}/secrets.php; tiền tố bảng ${TABLE_PREFIX}"
  note "3. wp config shuffle-salts"
  note "4. wp core install --url=https://${DOMAIN} --admin_user=${ADMIN_USER} --admin_email=${ADMIN_EMAIL}; mật khẩu ban đầu cất ở ${PW_FILE} (0600), không in ra"
  note "5. wp language core install ${WP_LOCALE} --activate; đường dẫn /%postname%/; múi giờ Asia/Ho_Chi_Minh; xóa nội dung mẫu của WordPress (bài 1, trang 2 và 3, bình luận kèm theo); wp rewrite flush"
  exit 0
fi

[[ $EUID -eq 0 ]] || { echo "Cần chạy bằng root (sudo)." >&2; exit 1; }
command -v wp >/dev/null 2>&1 || { echo "Chưa có WP-CLI (bước packages của provision.sh cài)." >&2; exit 1; }
[[ -d "$WEB_ROOT" ]] || { echo "Chưa có ${WEB_ROOT}: chạy provision.sh --env=${ENV_NAME} trước." >&2; exit 1; }
[[ -f "$DB_FILE" ]] || { echo "Chưa có ${DB_FILE}: chạy provision.sh --env=${ENV_NAME} (bước mariadb) trước." >&2; exit 1; }
if [[ -e "$CONFIG" || -e "$WEB_ROOT/wp-includes/version.php" ]]; then
  echo "đã có WordPress ở ${WEB_ROOT}, dừng, chưa đổi gì" >&2
  exit 1
fi
echo "Môi trường: ${ENV_NAME} | WP_ENVIRONMENT_TYPE: ${WP_ENV} | tên miền: ${DOMAIN} | thư mục web: ${WEB_ROOT}"

log "1. tải lõi WordPress ${WP_VERSION} (WP-CLI tự kiểm băm)"
wpd core download --version="$WP_VERSION" --locale="$WP_LOCALE" --skip-content

log "2. wp-config.php"
{
  cat <<PHP
<?php
define( 'WP_ENVIRONMENT_TYPE', '${WP_ENV}' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
require '${DB_FILE}';
PHP
  for k in AUTH_KEY SECURE_AUTH_KEY LOGGED_IN_KEY NONCE_KEY AUTH_SALT SECURE_AUTH_SALT LOGGED_IN_SALT NONCE_SALT; do
    printf "define( '%s', 'doi-bang-shuffle-salts' );\n" "$k"
  done
  cat <<PHP
\$table_prefix = '${TABLE_PREFIX}';
define( 'DISALLOW_FILE_EDIT', true );
define( 'DISALLOW_FILE_MODS', true );
define( 'AUTOMATIC_UPDATER_DISABLED', true );
define( 'FORCE_SSL_ADMIN', true );
define( 'DISABLE_WP_CRON', true );
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_POST_REVISIONS', 10 );
define( 'EMPTY_TRASH_DAYS', 30 );
define( 'VGARCO_PRIVATE_DIR', '${PRIVATE_DIR}' );
require '${SECRETS_DIR}/redis.php';
require '${SECRETS_DIR}/secrets.php';
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
PHP
} > "$CONFIG"
chown "deploy:${PHP_USER}" "$CONFIG"
chmod 0640 "$CONFIG"
php -l "$CONFIG"
wpd config shuffle-salts
note "salt còn là giá trị tạm: $(grep -c 'doi-bang-shuffle-salts' "$CONFIG" || true) (mong đợi 0)"
stat -c '%a %U:%G %n' "$CONFIG"

log "3. cài đặt"
OUT="$(mktemp)"
if ! wpd core install --url="https://${DOMAIN}" --title="$TITLE" --admin_user="$ADMIN_USER" --admin_email="$ADMIN_EMAIL" --skip-email > "$OUT" 2>&1; then
  grep -v 'Admin password' "$OUT" || true
  rm -f "$OUT"
  exit 1
fi
install -m 0600 -o deploy -g deploy /dev/null "$PW_FILE"
sed -n 's/^Admin password: //p' "$OUT" > "$PW_FILE"
grep -v 'Admin password' "$OUT"
rm -f "$OUT"
note "mật khẩu quản trị ban đầu đã cất vào ${PW_FILE} ($(stat -c '%a %U' "$PW_FILE"), $(tr -d '\n' < "$PW_FILE" | wc -c) ký tự); đổi ngay lần đăng nhập đầu và cất vào kho mật khẩu của chủ đầu tư"

log "4. ngôn ngữ, đường dẫn, múi giờ (theo CAU-HINH.md mục 4)"
wpd language core install "$WP_LOCALE" --activate
wpd option update permalink_structure '/%postname%/'
wpd option update timezone_string 'Asia/Ho_Chi_Minh'
wpd post delete 1 2 3 --force
wpd rewrite flush

log "5. kiểm"
note "phiên bản: $(wpd core version)"
note "môi trường: $(wpd eval 'echo wp_get_environment_type();')"
note "siteurl: $(wpd option get siteurl)"
note "ngôn ngữ site: $(wpd eval 'echo get_locale();')"
wpd core verify-checksums --locale="$WP_LOCALE"
note "bảng: $(wpd db tables --all-tables-with-prefix 2>/dev/null | wc -l) (tiền tố ${TABLE_PREFIX})"

log "Xong. Bước kế (TRIEN-KHAI.md mục 3): cài plugin theo plugins.lock.json; deploy.sh --no-smoke; kích hoạt vgarco-core rồi theme; chạy scripts/ops/setup-pages.php bằng wp eval-file; nhập nội dung; deploy.sh có kiểm khói."
