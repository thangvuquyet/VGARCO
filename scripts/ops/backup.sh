#!/usr/bin/env bash

set -euo pipefail
umask 077

CONF="${VGARCO_BACKUP_CONF:-/etc/vgarco/backup.env}"
LOG="${VGARCO_BACKUP_LOG:-/var/log/vgarco-backup.log}"
DUMP_DIR="${VGARCO_BACKUP_DUMPS:-/var/backups/vgarco}"
LOCK="${VGARCO_BACKUP_LOCK:-/var/lock/vgarco-backup.lock}"
WEB_BASE="${VGARCO_WEB_BASE:-/var/www}"
PRIV_BASE="${VGARCO_PRIV_BASE:-/srv}"
ETC_VGARCO="${VGARCO_ETC:-/etc/vgarco}"
ETC_NGINX="${VGARCO_NGINX_ETC:-/etc/nginx}"

usage() {
  cat <<'USAGE'
vgarco-backup — sao lưu hằng ngày bằng restic (CSDL, ảnh tải lên, CV, khóa, cấu hình Nginx)

  vgarco-backup            chạy một lần sao lưu (cron gọi hằng đêm)
  vgarco-backup --init     tạo kho restic lần đầu (chạy tay đúng một lần)
  vgarco-backup --status   liệt kê bản chụp gần nhất

Cấu hình: /etc/vgarco/backup.env (quyền 0600 root): RESTIC_REPOSITORY, RESTIC_PASSWORD, AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY,
BACKUP_SITES="vgarco", BACKUP_DBS="vgarco", HEARTBEAT_URL (tùy chọn).
USAGE
}

MODE=run
case "${1:-}" in
  "") ;;
  --init) MODE=init ;;
  --status) MODE=status ;;
  -h | --help) usage; exit 0 ;;
  *) echo "Tham số lạ: $1 (xem --help)" >&2; exit 2 ;;
esac

[[ -r "$CONF" ]] || { echo "Thiếu hoặc không đọc được ${CONF}" >&2; exit 2; }
set -a
. "$CONF"
set +a
: "${RESTIC_REPOSITORY:?Thiếu RESTIC_REPOSITORY trong ${CONF}}"
: "${RESTIC_PASSWORD:?Thiếu RESTIC_PASSWORD trong ${CONF}}"
BACKUP_SITES="${BACKUP_SITES:-vgarco}"
BACKUP_DBS="${BACKUP_DBS:-vgarco}"
HEARTBEAT_URL="${HEARTBEAT_URL:-}"

if [[ "$MODE" == "init" ]]; then
  exec restic init
fi
if [[ "$MODE" == "status" ]]; then
  exec restic snapshots --compact --latest 5
fi

mkdir -p "$(dirname "$LOG")"
exec 9>"$LOCK"
flock -n 9 || { echo "$(date -Is) đang có một lần sao lưu khác chạy, bỏ qua" >>"$LOG"; exit 0; }
exec >>"$LOG" 2>&1

ping_heartbeat() {
  local suffix="${1:-}"
  [[ -n "$HEARTBEAT_URL" ]] || return 0
  curl -fsS -m 20 --retry 3 -o /dev/null "${HEARTBEAT_URL}${suffix}" || echo "$(date -Is) không gọi được địa chỉ heartbeat"
}

on_error() {
  local code=$? line=$1
  echo "$(date -Is) LỖI (mã ${code}) ở dòng ${line}"
  logger -t vgarco-backup "sao lưu lỗi (mã ${code}) ở dòng ${line}" 2>/dev/null || true
  ping_heartbeat /fail
  exit "$code"
}
trap 'on_error $LINENO' ERR

echo "$(date -Is) bắt đầu"
mkdir -p "$DUMP_DIR"
stamp=$(date +%F)

for db in $BACKUP_DBS; do
  tmp="${DUMP_DIR}/.${db}-${stamp}.sql.gz.part"
  mysqldump --single-transaction --quick --routines --triggers --databases "$db" | gzip -9 >"$tmp"
  if ! gzip -dc "$tmp" | tail -n 5 | grep -q 'Dump completed'; then
    echo "$(date -Is) bản sao CSDL ${db} không đầy đủ (thiếu dòng Dump completed)"
    rm -f "$tmp"
    false
  fi
  mv "$tmp" "${DUMP_DIR}/${db}-${stamp}.sql.gz"
  echo "$(date -Is) đã sao CSDL ${db}"
done
find "$DUMP_DIR" -maxdepth 1 -name '*.sql.gz' -mtime +3 -delete

paths=("$DUMP_DIR" "$ETC_VGARCO" "$ETC_NGINX")
for site in $BACKUP_SITES; do
  for p in "${WEB_BASE}/${site}/public/wp-content/uploads" "${PRIV_BASE}/${site}/private" "${WEB_BASE}/${site}/wp-config.php"; do
    if [[ -e "$p" ]]; then paths+=("$p"); else echo "$(date -Is) bỏ qua, không thấy: ${p}"; fi
  done
done

restic backup --tag vgarco --exclude-caches --one-file-system "${paths[@]}"
restic forget --tag vgarco --keep-daily 7 --keep-weekly 4 --keep-monthly 3 --prune

if [[ "${VGARCO_BACKUP_DOW:-$(date +%u)}" == "7" ]]; then
  restic check --read-data-subset=5%
  echo "$(date -Is) đã kiểm 5% dữ liệu kho"
fi

echo "$(date -Is) xong"
ping_heartbeat
