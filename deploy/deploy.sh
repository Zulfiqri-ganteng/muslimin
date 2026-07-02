#!/bin/bash
# ============================================================================
#  Deploy script — Sistem Kesediaan Guru Mengajar (domain: kangmuslim.com)
#  Mengikuti pola deploy project "galajuara".
#
#  Cara pakai di hosting cPanel:
#    1. Pastikan repo sudah di-clone ke $PROJECT (lihat PANDUAN.md bagian Deploy).
#    2. Jalankan manual lewat Terminal cPanel/SSH:
#         bash "$HOME/kangmuslim/deploy/deploy.sh"
#       ATAU pasang sebagai Cron Job di cPanel.
#
#  Catatan: path memakai $HOME agar otomatis mengikuti akun cPanel
#  (tidak perlu hardcode /home/<username>/).
# ============================================================================

LOGFILE="$HOME/deploy/deploy-kangmuslim.log"
PROJECT="$HOME/kangmuslim"
GIT="/usr/local/cpanel/3rdparty/lib/path-bin/git"
PHP="/usr/local/bin/php"

mkdir -p "$HOME/deploy"

echo "======================================" >> "$LOGFILE"
echo "Deploy run: $(date)" >> "$LOGFILE"

cd "$PROJECT" || { echo "Folder project tidak ditemukan: $PROJECT" >> "$LOGFILE"; exit 1; }

echo "Pull repository..." >> "$LOGFILE"
$GIT fetch origin               >> "$LOGFILE" 2>&1
$GIT reset --hard origin/main   >> "$LOGFILE" 2>&1
# git clean menghapus file untracked, TAPI .env & public/uploads di-ignore
# sehingga tetap aman (tidak terhapus).
$GIT clean -fd                  >> "$LOGFILE" 2>&1

echo "Run CI4 migration..." >> "$LOGFILE"
$PHP spark migrate --all        >> "$LOGFILE" 2>&1

echo "Clear cache..." >> "$LOGFILE"
$PHP spark cache:clear          >> "$LOGFILE" 2>&1

echo "Fix writable permission..." >> "$LOGFILE"
chmod -R 775 writable           >> "$LOGFILE" 2>&1

echo "Deploy finished." >> "$LOGFILE"
echo "" >> "$LOGFILE"
