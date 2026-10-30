#!/usr/bin/env bash
# Puts the application's storage (uploads, recordings, backups, logs) on a
# dedicated data disk instead of the small OS disk. One-time; safe to re-run
# (it stops if the disk is already mounted there).
#
# Usage: sudo bash deploy/attach-data-disk.sh /dev/sdb
#
# Refuses to touch a disk that has ANY partition, filesystem or signature on
# it -- formatting is irreversible, so only a genuinely blank disk is used.
# The old storage directory is kept (renamed storage.pre-disk-<time>) until
# you delete it yourself.
set -euo pipefail
trap 'echo "FAILED at line $LINENO: $BASH_COMMAND" >&2' ERR

DISK="${1:-}"
APP_DIR=/var/www/ecenter
STORAGE="$APP_DIR/shared/storage"
LABEL=ecenter-data
TMP_MNT=/mnt/ecenter-data-new

[ "$(id -u)" -eq 0 ] || { echo "Run as root: sudo bash $0 /dev/sdX" >&2; exit 1; }
[ -b "$DISK" ] || { echo "Usage: sudo bash $0 /dev/sdX   (not a block device: '$DISK')" >&2; exit 1; }
step() { printf '\n==> %s\n' "$*"; }

if findmnt -rn "$STORAGE" >/dev/null; then
    echo "$STORAGE is already a mount point:"; findmnt "$STORAGE"; df -h "$STORAGE"; exit 0
fi

step "Checking that $DISK is blank"
lsblk -o NAME,SIZE,TYPE,FSTYPE,MOUNTPOINTS "$DISK"
[ "$(lsblk -nro NAME "$DISK" | wc -l)" -eq 1 ] || { echo "ABORT: $DISK has partitions." >&2; exit 1; }
[ -z "$(blkid -p "$DISK" 2>/dev/null)" ] || { echo "ABORT: $DISK carries a filesystem/RAID/LVM signature." >&2; exit 1; }
[ -z "$(wipefs -n "$DISK" 2>/dev/null | tail -n +2)" ] || { echo "ABORT: wipefs found signatures on $DISK." >&2; exit 1; }
! grep -q "^$DISK" /proc/mounts || { echo "ABORT: $DISK is mounted." >&2; exit 1; }
echo "OK: no partitions, no signatures, not mounted."

step "Partitioning and formatting (GPT, one ext4 partition labelled $LABEL)"
parted -s "$DISK" mklabel gpt mkpart "$LABEL" ext4 1MiB 100%
partprobe "$DISK" 2>/dev/null || true
udevadm settle 2>/dev/null || true
PART=""
for _ in $(seq 1 20); do
    PART="$(lsblk -nrpo NAME "$DISK" | sed -n 2p)"
    [ -n "$PART" ] && [ -b "$PART" ] && break
    sleep 0.5
done
[ -n "$PART" ] && [ -b "$PART" ] || { echo "ABORT: partition did not appear on $DISK." >&2; exit 1; }
mkfs.ext4 -q -L "$LABEL" -m 1 "$PART"
UUID="$(blkid -s UUID -o value "$PART")"
echo "$PART  UUID=$UUID"

step "Maintenance mode on, queue workers stopped"
runuser -u www-data -- php "$APP_DIR/current/artisan" down --retry=30 || true
trap 'runuser -u www-data -- php "$APP_DIR/current/artisan" up >/dev/null 2>&1 || true; supervisorctl start "ecenter-worker:*" >/dev/null 2>&1 || true' EXIT
supervisorctl stop 'ecenter-worker:*' || true

step "Copying current storage onto the new disk"
mkdir -p "$TMP_MNT"
mount "$PART" "$TMP_MNT"
rsync -aHAX --numeric-ids "$STORAGE/" "$TMP_MNT/"
src=$(find "$STORAGE" | wc -l); dst=$(find "$TMP_MNT" -path "$TMP_MNT/lost+found" -prune -o -print | wc -l)
echo "entries: source=$src copy=$dst"
[ "$src" -eq "$dst" ] || { umount "$TMP_MNT"; echo "ABORT: copy is incomplete, nothing switched." >&2; exit 1; }
umount "$TMP_MNT" && rmdir "$TMP_MNT"

step "Mounting the disk at $STORAGE (permanent, via /etc/fstab)"
OLD="$STORAGE.pre-disk-$(date +%Y%m%d-%H%M%S)"
mv "$STORAGE" "$OLD"
mkdir "$STORAGE"
cp /etc/fstab "/etc/fstab.bak-$(date +%Y%m%d-%H%M%S)"
# nofail: if the disk is ever missing the server still boots (and the app's
# System Health page shows the wrong disk size) instead of hanging at boot.
echo "UUID=$UUID  $STORAGE  ext4  defaults,noatime,nofail  0  2" >> /etc/fstab
systemctl daemon-reload
mount "$STORAGE"
chown www-data:www-data "$STORAGE"
chmod 755 "$STORAGE"
findmnt "$STORAGE"

step "Back online"
supervisorctl start 'ecenter-worker:*'
runuser -u www-data -- php "$APP_DIR/current/artisan" up
trap - EXIT
runuser -u www-data -- bash -c "echo ok > '$STORAGE/logs/.write-test' && rm '$STORAGE/logs/.write-test'" && echo "www-data can write to the new disk"
df -h / "$STORAGE"
echo
echo "Done. The previous storage directory is kept at $OLD -- delete it once you are happy:"
echo "  sudo rm -rf $OLD"
