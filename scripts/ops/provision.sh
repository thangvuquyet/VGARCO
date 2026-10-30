#!/usr/bin/env bash

set -euo pipefail

usage() {
  cat <<'USAGE'
provision.sh — dựng máy chủ gốc VGARCO từ Ubuntu 24.04 sạch (9/9 bước đã chạy thật trên VM thử 29/10/2026)

  sudo ./provision.sh --env=staging|production --domain=<tên miền> [--ssh-port=<số>] [--finalize-ssh] [--phase=a,b,...] [--dry-run]

  --env            môi trường (người dùng PHP, pool, CSDL, thư mục, khối Nginx riêng cho từng môi trường)
  --domain         tên miền của khối Nginx
  --ssh-port       cổng SSH mới (mặc định 22); cổng 22 chỉ đóng khi chạy lại với --finalize-ssh
  --finalize-ssh   đóng cổng 22 sau khi đã đăng nhập thử thành công bằng cổng mới ở cửa sổ khác (chỉ dùng cùng --ssh-port khác 22)
  --phase          chỉ chạy các bước: packages users ssh firewall php mariadb redis nginx cron
  --dry-run        chỉ in kế hoạch, không đổi gì
USAGE
}

DRY=0
ENV_NAME=""
DOMAIN=""
SSH_PORT=22
FINALIZE_SSH=0
PHASE_LIST="packages,users,ssh,firewall,php,mariadb,redis,nginx,cron"
SELECTED="$PHASE_LIST"

for arg in "$@"; do
  case "$arg" in
    --env=*) ENV_NAME="${arg#*=}" ;;
    --domain=*) DOMAIN="${arg#*=}" ;;
    --ssh-port=*) SSH_PORT="${arg#*=}" ;;
    --finalize-ssh) FINALIZE_SSH=1 ;;
    --phase=*) SELECTED="${arg#*=}" ;;
    --dry-run) DRY=1 ;;
    -h | --help) usage; exit 0 ;;
    *) echo "Tham số lạ: $arg (xem --help)" >&2; exit 2 ;;
  esac
done

[[ "$ENV_NAME" == "staging" || "$ENV_NAME" == "production" ]] || { echo "Cần --env=staging|production" >&2; exit 2; }
[[ "$DOMAIN" =~ ^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$ ]] || { echo "Cần --domain=<tên miền hợp lệ, chữ thường>" >&2; exit 2; }
[[ "$SSH_PORT" =~ ^[0-9]+$ ]] && (( SSH_PORT >= 22 && SSH_PORT <= 65535 )) || { echo "--ssh-port phải là số 22–65535" >&2; exit 2; }
(( FINALIZE_SSH && SSH_PORT == 22 )) && { echo "--finalize-ssh chỉ dùng cùng --ssh-port khác 22: với cổng 22 nó sẽ xóa luôn quy tắc SSH và khóa chính mình." >&2; exit 2; }

if [[ "$ENV_NAME" == "production" ]]; then
  PHP_USER="vgarco"; SITE="vgarco"
else
  PHP_USER="vgarco-staging"; SITE="vgarco-staging"
fi
WEB_ROOT="/var/www/${SITE}/public"
PRIVATE_DIR="/srv/${SITE}/private"
DB_NAME="${SITE//-/_}"
DB_USER="${SITE//-/_}"
FPM_SOCK="/run/php/php8.3-fpm-${SITE}.sock"
SECRETS_DIR="/etc/vgarco"
WPCLI_VERSION="2.12.0"
WPCLI_SHA512="be928f6b8ca1e8dfb9d2f4b75a13aa4aee0896f8a9a0a1c45cd5d2c98605e6172e6d014dda2e27f88c98befc16c040cbb2bd1bfa121510ea5cdf5f6a30fe8832"

log() { printf '\n== %s\n' "$*"; }
note() { printf '   %s\n' "$*"; }
have() { command -v "$1" >/dev/null 2>&1; }
run() { if (( DRY )); then printf '   $ %s\n' "$*"; else "$@"; fi; }

ghi_tep() {
  local path=$1 mode=$2 owner=$3 tmp
  tmp=$(mktemp)
  cat >"$tmp"
  if [[ -f "$path" ]] && cmp -s "$tmp" "$path"; then rm -f "$tmp"; return 1; fi
  if (( DRY )); then
    note "~ ghi $path ($mode $owner)"; rm -f "$tmp"; return 0
  fi
  mkdir -p "$(dirname "$path")"
  [[ -f "$path" ]] && cp -p "$path" "$path.bak.$(date +%Y%m%d%H%M%S)"
  install -m "$mode" -o "${owner%%:*}" -g "${owner##*:}" "$tmp" "$path"
  rm -f "$tmp"
  return 0
}

phase_on() { [[ ",${SELECTED}," == *",$1,"* ]]; }

cf_helper() {
  ghi_tep /usr/local/sbin/vgarco-cloudflare-ips 0750 root:root <<'EOS' || true
#!/usr/bin/env bash
set -euo pipefail
v4=$(curl -fsS --max-time 20 https://www.cloudflare.com/ips-v4)
v6=$(curl -fsS --max-time 20 https://www.cloudflare.com/ips-v6)
ranges=$(printf '%s\n%s\n' "$v4" "$v6" | grep -E '^([0-9]{1,3}\.){3}[0-9]{1,3}/[0-9]{1,2}$|^[0-9a-fA-F:]+/[0-9]{1,3}$')
[[ $(wc -l <<<"$ranges") -ge 10 ]] || { echo "Danh sách IP Cloudflare bất thường, bỏ qua." >&2; exit 1; }
{
  echo "# Sinh bởi vgarco-cloudflare-ips — đừng sửa tay"
  while read -r r; do echo "set_real_ip_from $r;"; done <<<"$ranges"
  echo "real_ip_header CF-Connecting-IP;"
} >/etc/nginx/conf.d/cloudflare-realip.inc.new
mv /etc/nginx/conf.d/cloudflare-realip.inc.new /etc/nginx/conf.d/cloudflare-realip.inc
while num=$(ufw status numbered | awk -F'[][]' '/cloudflare-443/ {gsub(/ /,"",$2); print $2}' | tail -n1) && [[ -n "$num" ]]; do ufw --force delete "$num" >/dev/null; done
while read -r r; do ufw allow from "$r" to any port 443 proto tcp comment cloudflare-443 >/dev/null; done <<<"$ranges"
nginx -t && systemctl reload nginx
EOS
}

if (( ! DRY )); then
  [[ $EUID -eq 0 ]] || { echo "Cần chạy bằng root (sudo)." >&2; exit 1; }
  . /etc/os-release
  [[ "${ID:-}" == "ubuntu" && "${VERSION_ID:-}" == "24.04" ]] || { echo "Kịch bản viết cho Ubuntu 24.04 (đang là ${PRETTY_NAME:-không rõ})." >&2; exit 1; }
else
  echo "(--dry-run: chỉ in kế hoạch; không kiểm quyền root, không kiểm hệ điều hành, không đổi gì)"
fi
echo "Môi trường: ${ENV_NAME} | tên miền: ${DOMAIN} | người dùng PHP: ${PHP_USER} | thư mục web: ${WEB_ROOT}"

phase_on packages && {
  log "packages: gói hệ thống"
  PKGS=(nginx php8.3-fpm php8.3-cli php8.3-mysql php8.3-gd php8.3-mbstring php8.3-xml php8.3-zip php8.3-curl php8.3-intl php8.3-redis php8.3-imagick
    mariadb-server redis-server certbot fail2ban ufw unattended-upgrades rsync zstd curl ca-certificates)
  run apt-get update -y
  run env DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends "${PKGS[@]}"
  note "Kiểm khi chạy thật: php -m | grep -i -E 'gd|zip|fileinfo|mbstring|intl' và php -r 'var_dump(count(Imagick::queryFormats(\"AVIF\")) > 0);' (GD của Ubuntu không có AVIF, mã hóa AVIF đi qua Imagick; docs/TRIEN-KHAI.md mục 2)."
  if ! have wp; then
    WPCLI_TMP="/usr/local/bin/.wp.new"
    run curl -fsSL --max-time 180 -o "$WPCLI_TMP" "https://github.com/wp-cli/wp-cli/releases/download/v${WPCLI_VERSION}/wp-cli-${WPCLI_VERSION}.phar"
    if (( ! DRY )) && ! echo "${WPCLI_SHA512}  ${WPCLI_TMP}" | sha512sum -c - >/dev/null 2>&1; then
      rm -f "$WPCLI_TMP"
      echo "DỪNG: mã băm SHA-512 của WP-CLI ${WPCLI_VERSION} không khớp mã ghim trong kịch bản; không cài." >&2
      exit 1
    fi
    run chmod 0755 "$WPCLI_TMP"
    run mv "$WPCLI_TMP" /usr/local/bin/wp
  fi
  run systemctl enable --now unattended-upgrades
}

phase_on users && {
  log "users: người dùng và thư mục"
  have id && id deploy >/dev/null 2>&1 || run useradd --create-home --shell /bin/bash deploy
  have id && id "$PHP_USER" >/dev/null 2>&1 || run useradd --system --no-create-home --shell /usr/sbin/nologin "$PHP_USER"
  id -nG deploy 2>/dev/null | tr ' ' '\n' | grep -qx "$PHP_USER" || run usermod -aG "$PHP_USER" deploy
  have id && id backup >/dev/null 2>&1 || run useradd --create-home --shell /bin/bash backup
  run install -d -m 0700 -o deploy -g deploy /home/deploy/.ssh
  run install -d -m 0755 -o deploy -g deploy "$(dirname "$WEB_ROOT")" "$WEB_ROOT" "$WEB_ROOT/wp-content" "$WEB_ROOT/wp-content/mu-plugins" "$WEB_ROOT/wp-content/plugins" "$WEB_ROOT/wp-content/themes"
  run install -d -m 2775 -o "$PHP_USER" -g "$PHP_USER" "$WEB_ROOT/wp-content/uploads"
  run install -d -m 0750 -o "$PHP_USER" -g "$PHP_USER" "$(dirname "$PRIVATE_DIR")" "$PRIVATE_DIR"
  run install -d -m 0750 -o root -g "$PHP_USER" "$SECRETS_DIR"
  run install -d -m 0750 -o deploy -g deploy "$(dirname "$WEB_ROOT")/rollback"
  ghi_tep /etc/sudoers.d/vgarco-deploy 0440 root:root <<'EOF' && { run visudo -cf /etc/sudoers.d/vgarco-deploy; } || true
deploy ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.3-fpm
EOF
  if [[ ! -f "$SECRETS_DIR/secrets.php" ]]; then
    ghi_tep "$SECRETS_DIR/secrets.php" 0640 "root:${PHP_USER}" <<'EOF' || true
<?php
// Khóa bí mật của VGARCO. Quyền 0640 root:<người dùng PHP>. KHÔNG đưa vào Git, KHÔNG gửi qua chat/email.
// Bỏ // ở đầu dòng và điền giá trị khi đã có. KHÔNG khai báo hằng số với giá trị rỗng: mã Cloudflare chỉ kiểm defined(), khai báo rỗng là bật tính năng với khóa rỗng.
// define( 'VGARCO_CF_ZONE', '' );
// define( 'VGARCO_CF_TOKEN', '' );   // token Cloudflare chỉ có quyền Cache Purge của đúng zone
// define( 'VGARCO_TELEGRAM_TOKEN', '' );
// define( 'VGARCO_TELEGRAM_CHAT', '' );
EOF
  fi
}

phase_on ssh && {
  ADMIN_USER="${SUDO_USER:-}"
  [[ "$ADMIN_USER" == "root" || "$ADMIN_USER" == "deploy" ]] && ADMIN_USER=""
  ALLOWED="deploy${ADMIN_USER:+ $ADMIN_USER}"
  log "ssh: chỉ khóa công khai, AllowUsers ${ALLOWED} (cổng ${SSH_PORT})"
  if (( ! DRY )) && [[ -n "$ADMIN_USER" ]]; then
    ADMIN_KEYS="$(getent passwd "$ADMIN_USER" | cut -d: -f6)/.ssh/authorized_keys"
    [[ -s "$ADMIN_KEYS" ]] || { echo "DỪNG bước ssh: $ADMIN_KEYS chưa có khóa công khai nào; tắt mật khẩu lúc này sẽ khóa tài khoản quản trị $ADMIN_USER ngoài máy chủ." >&2; exit 1; }
  fi
  [[ -n "$ADMIN_USER" ]] || note "Không có tài khoản quản trị nào được phép SSH (chạy bằng root, không qua sudo): sau bước này chỉ còn deploy, không có quyền root, chạy lại kịch bản phải qua màn hình console."
  KEYS=/home/deploy/.ssh/authorized_keys
  if (( ! DRY )) && [[ ! -s "$KEYS" ]]; then
    echo "DỪNG bước ssh: $KEYS chưa có khóa công khai nào. Thêm khóa của người vận hành rồi chạy lại, nếu không sẽ tự khóa mình ngoài máy chủ." >&2
    exit 1
  fi
  {
    echo "# Sinh bởi provision.sh — đừng sửa tay (chạy lại kịch bản sẽ ghi đè; bản cũ giữ thành .bak)"
    echo "PermitRootLogin no"
    echo "PasswordAuthentication no"
    echo "KbdInteractiveAuthentication no"
    echo "PubkeyAuthentication yes"
    echo "X11Forwarding no"
    echo "LoginGraceTime 30"
    echo "AllowUsers ${ALLOWED}"
    echo "MaxAuthTries 3"
    echo "Port ${SSH_PORT}"
    if (( SSH_PORT != 22 && ! FINALIZE_SSH )); then echo "Port 22"; fi
  } | ghi_tep /etc/ssh/sshd_config.d/10-vgarco.conf 0644 root:root && SSH_CHANGED=1 || SSH_CHANGED=0
  if (( SSH_CHANGED )); then
    run sshd -t
    (( SSH_PORT != 22 )) && run systemctl disable --now ssh.socket || true
    run systemctl enable ssh
    run systemctl reload ssh || run systemctl restart ssh
    if (( SSH_PORT != 22 )); then
      note "Mở cửa sổ SSH MỚI và đăng nhập thử bằng cổng ${SSH_PORT} TRƯỚC khi đóng cửa sổ này. Xong thì chạy lại với --finalize-ssh."
    else
      note "Mở cửa sổ SSH MỚI và đăng nhập thử bằng khóa TRƯỚC khi đóng cửa sổ này."
    fi
  fi
  ghi_tep /etc/fail2ban/jail.d/vgarco.local 0644 root:root <<EOF && run systemctl restart fail2ban || true
[sshd]
enabled = true
backend = systemd
journalmatch = _SYSTEMD_UNIT=ssh.service + _COMM=sshd
port = ${SSH_PORT}
maxretry = 4
bantime = 1h
EOF
}

phase_on firewall && {
  log "firewall: ufw chặn vào mặc định; SSH; 443 chỉ cho Cloudflare; KHÔNG mở cổng 80"
  run ufw allow "${SSH_PORT}/tcp" comment ssh
  (( SSH_PORT != 22 && ! FINALIZE_SSH )) && run ufw allow 22/tcp comment ssh-cu || true
  (( FINALIZE_SSH )) && run ufw delete allow 22/tcp || true
  run ufw default deny incoming
  run ufw default allow outgoing
  cf_helper
  run /usr/local/sbin/vgarco-cloudflare-ips
  [[ -f /var/log/ufw.log ]] || { run install -m 0640 -o syslog -g adm /dev/null /var/log/ufw.log; run systemctl restart rsyslog; }
  run ufw --force enable
}

phase_on php && {
  log "php: pool ${SITE}, php.ini an toàn, OPcache không kiểm thời gian tệp"
  ghi_tep /etc/php/8.3/fpm/conf.d/99-vgarco.ini 0644 root:root <<'EOF' && PHP_CHANGED=1 || PHP_CHANGED=0
; Sinh bởi provision.sh
expose_php = Off
opcache.enable = 1
opcache.memory_consumption = 192
opcache.validate_timestamps = 0   ; mã chỉ đổi khi deploy.sh chạy xong và reload php-fpm
upload_max_filesize = 16M
post_max_size = 18M
max_input_vars = 3000
EOF
  ghi_tep "/etc/php/8.3/fpm/pool.d/${SITE}.conf" 0644 root:root <<EOF && POOL_CHANGED=1 || POOL_CHANGED=0
; Sinh bởi provision.sh — pool riêng cho ${ENV_NAME}, mỗi pool một người dùng không shell
[${SITE}]
user = ${PHP_USER}
group = ${PHP_USER}
listen = ${FPM_SOCK}
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = dynamic
pm.max_children = 8
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500
; Ảnh toàn cảnh 12 MB cần ~190 MB khi tạo bản thu nhỏ (docs/TRIEN-KHAI.md mục 2)
php_admin_value[memory_limit] = 256M
php_admin_value[disable_functions] = exec,passthru,shell_exec,system,proc_open,popen
php_admin_value[error_log] = /var/log/php8.3-fpm-${SITE}.log
php_admin_flag[log_errors] = on
php_admin_flag[display_errors] = off
EOF
  PHP_LOG="/var/log/php8.3-fpm-${SITE}.log"
  [[ -f "$PHP_LOG" ]] || run install -m 0640 -o "$PHP_USER" -g adm /dev/null "$PHP_LOG"
  ghi_tep "/etc/logrotate.d/php8.3-fpm-${SITE}" 0644 root:root <<EOF || true
${PHP_LOG} {
    weekly
    rotate 8
    missingok
    notifempty
    compress
    delaycompress
    create 0640 ${PHP_USER} adm
}
EOF
  [[ -f /etc/php/8.3/fpm/pool.d/www.conf ]] && run mv /etc/php/8.3/fpm/pool.d/www.conf /etc/php/8.3/fpm/pool.d/www.conf.disabled || true
  if (( PHP_CHANGED || POOL_CHANGED )); then run php-fpm8.3 -t; run systemctl reload php8.3-fpm; fi
}

phase_on mariadb && {
  log "mariadb: chỉ nghe 127.0.0.1; CSDL và người dùng riêng cho ${ENV_NAME}"
  ghi_tep /etc/mysql/mariadb.conf.d/99-vgarco.cnf 0644 root:root <<'EOF' && run systemctl restart mariadb || true
[mysqld]
bind-address = 127.0.0.1
local_infile = 0
innodb_buffer_pool_size = 1G
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_520_ci
EOF
  DBFILE="${SECRETS_DIR}/${ENV_NAME}-db.php"
  if [[ ! -f "$DBFILE" ]]; then
    if (( DRY )); then
      note "~ sinh mật khẩu CSDL ngẫu nhiên, tạo CSDL ${DB_NAME} + người dùng ${DB_USER}@localhost, ghi ${DBFILE} (0640 root:${PHP_USER})"
    else
      pass=$(head -c 24 /dev/urandom | base64 | tr -d '/+=' | head -c 32)
      mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${pass}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
      printf "<?php\ndefine( 'DB_NAME', '%s' );\ndefine( 'DB_USER', '%s' );\ndefine( 'DB_PASSWORD', '%s' );\ndefine( 'DB_HOST', '127.0.0.1' );\n" "$DB_NAME" "$DB_USER" "$pass" \
        | install -m 0640 -o root -g "$PHP_USER" /dev/stdin "$DBFILE"
      unset pass
    fi
  else
    note "= ${DBFILE} đã có: giữ nguyên (không đặt lại mật khẩu)"
  fi
}

phase_on redis && {
  log "redis: chỉ localhost, có mật khẩu, 256 MB, allkeys-lru"
  ghi_tep /etc/sysctl.d/99-vgarco-redis.conf 0644 root:root <<'EOF' && run sysctl -w vm.overcommit_memory=1 || true
vm.overcommit_memory = 1
EOF
  RFILE="${SECRETS_DIR}/redis.php"
  if [[ ! -f "$RFILE" ]]; then
    if (( DRY )); then
      note "~ sinh mật khẩu Redis ngẫu nhiên, ghi ${RFILE} (0640 root:${PHP_USER}) và /etc/redis/vgarco.conf"
    else
      rpass=$(head -c 24 /dev/urandom | base64 | tr -d '/+=' | head -c 32)
      printf "<?php\ndefine( 'WP_REDIS_PASSWORD', '%s' );\ndefine( 'WP_REDIS_HOST', '127.0.0.1' );\n" "$rpass" | install -m 0640 -o root -g "$PHP_USER" /dev/stdin "$RFILE"
      printf "bind 127.0.0.1 -::1\nprotected-mode yes\nrequirepass %s\nmaxmemory 256mb\nmaxmemory-policy allkeys-lru\n" "$rpass" >/etc/redis/vgarco.conf
      chmod 0640 /etc/redis/vgarco.conf && chown root:redis /etc/redis/vgarco.conf
      grep -q 'include /etc/redis/vgarco.conf' /etc/redis/redis.conf || echo 'include /etc/redis/vgarco.conf' >>/etc/redis/redis.conf
      systemctl restart redis-server
      unset rpass
    fi
  else
    note "= ${RFILE} đã có: giữ nguyên"
  fi
  note "Staging và production dùng chung một Redis: đặt WP_REDIS_PREFIX (và/hoặc WP_REDIS_DATABASE) KHÁC NHAU trong wp-config.php của từng môi trường, nếu không hai bên dùng chung khóa cache."
}

phase_on nginx && {
  log "nginx: default_server từ chối, khối ${DOMAIN}, chặn tệp nhạy cảm, Authenticated Origin Pulls khi đã có CA"
  run install -d -m 0755 /etc/nginx/aop /etc/nginx/vgarco "/var/www/${SITE}/acme"
  CERT_DIR="/etc/letsencrypt/live/${DOMAIN}"
  if [[ ! -f "${CERT_DIR}/fullchain.pem" ]]; then
    CERT_PEM="/etc/nginx/vgarco/${SITE}-selfsigned.pem"; CERT_KEY="/etc/nginx/vgarco/${SITE}-selfsigned.key"
    if [[ ! -f "$CERT_PEM" ]]; then run openssl req -x509 -nodes -newkey rsa:2048 -days 90 -subj "/CN=${DOMAIN}" -addext "subjectAltName=DNS:${DOMAIN}" -keyout "$CERT_KEY" -out "$CERT_PEM"; fi
    note "Chứng chỉ tự ký đang được dùng. Cấp Let's Encrypt (certbot webroot /var/www/${SITE}/acme), rồi chạy lại --phase=nginx; sau đó Cloudflare đổi sang Full (strict)."
  else
    CERT_PEM="${CERT_DIR}/fullchain.pem"; CERT_KEY="${CERT_DIR}/privkey.pem"
  fi
  if [[ -s /etc/nginx/aop/${SITE}-aop-ca.pem ]]; then AOP="ssl_client_certificate /etc/nginx/aop/${SITE}-aop-ca.pem;
    ssl_verify_client on;"; else AOP="# AOP CHƯA BẬT: đặt CA vào /etc/nginx/aop/${SITE}-aop-ca.pem rồi chạy lại bước nginx
    ssl_verify_client off;"; fi

  ghi_tep /etc/nginx/conf.d/00-default-reject.conf 0644 root:root <<'EOF' && NGX_CHANGED=1 || NGX_CHANGED=0
# Truy cập bằng IP hoặc tên miền lạ không nhận được chứng chỉ (phương án 10.2).
server_tokens off;
server {
    listen 443 ssl http2 default_server;
    ssl_reject_handshake on;
    return 444;
}
EOF
  ghi_tep "/etc/nginx/conf.d/${SITE}.conf" 0644 root:root <<EOF && NGX_CHANGED=1 || true
# Sinh bởi provision.sh — ${ENV_NAME}. Khối này khớp phương án triển khai mục 10.2.
server {
    listen 443 ssl http2;
    server_name ${DOMAIN};
    root ${WEB_ROOT};
    index index.php;

    ssl_certificate     ${CERT_PEM};
    ssl_certificate_key ${CERT_KEY};
    ssl_protocols TLSv1.2 TLSv1.3;
    ${AOP}

    include /etc/nginx/conf.d/cloudflare-realip.inc;
    client_max_body_size 18m;
    server_tokens off;

    location ^~ /.well-known/acme-challenge/ { root /var/www/${SITE}/acme; }

    location ~* ^/wp-content/uploads/.*\.(php\d?|phtml|phar)\$ { deny all; }
    location = /xmlrpc.php                                     { deny all; }
    location = /wp-config.php                                  { deny all; }
    location ~* ^/(readme\.html|license\.txt)\$               { deny all; }
    location ~ /\.(?!well-known)                               { deny all; }
    location ~* \.(sql|bak|log|ini|sh|lock)\$                  { deny all; }

    # Tệp có mã băm trong tên (CSS, JS, phông): cache một năm, không đổi nội dung (docs/KIEN-TRUC.md)
    location ~* ^/wp-content/themes/vgarco/dist/.+\.(css|js|woff2|avif|webp|jpe?g|png|svg)\$ {
        expires 1y;
        add_header Cache-Control "public, max-age=31536000, immutable";
        access_log off;
    }

    location / { try_files \$uri \$uri/ /index.php?\$args; }
    location ~ \.php\$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_pass unix:${FPM_SOCK};
    }
}
EOF
  cf_helper
  [[ -f /etc/nginx/conf.d/cloudflare-realip.inc ]] || run /usr/local/sbin/vgarco-cloudflare-ips
  if [[ -e /etc/nginx/sites-enabled/default ]]; then run rm -f /etc/nginx/sites-enabled/default; NGX_CHANGED=1; fi
  if (( NGX_CHANGED )); then run nginx -t; run systemctl reload nginx; fi
  note "Tiêu đề bảo mật (HSTS, X-Content-Type-Options, Referrer-Policy, CSP) do Cloudflare/Nginx đặt theo yêu cầu 5.4.2 của phương án — CHƯA có trong khối này; smoke.mjs --env=production sẽ báo thiếu."
}

phase_on cron && {
  log "cron: WP-Cron thật mỗi 5 phút, làm mới IP Cloudflare hằng tuần"
  ghi_tep "/etc/cron.d/${SITE}" 0644 root:root <<EOF || true
# Sinh bởi provision.sh
PATH=/usr/local/bin:/usr/bin:/bin
*/5 * * * * deploy cd ${WEB_ROOT} && wp cron event run --due-now --quiet
EOF
  ghi_tep /etc/cron.d/vgarco-cloudflare-ips 0644 root:root <<'EOF' || true
# Sinh bởi provision.sh
17 3 * * 1 root /usr/local/sbin/vgarco-cloudflare-ips >/dev/null 2>&1
EOF
  note "Cần đặt define('DISABLE_WP_CRON', true) trong wp-config.php (docs/CAU-HINH.md). Sao lưu 02:30 và kiểm hạn chứng chỉ: chưa có mã (xem SAO-LUU-PHUC-HOI.md)."
}

log "Xong (${ENV_NAME})."
(( DRY )) && echo "Đây là --dry-run: chưa có gì thay đổi."
exit 0
