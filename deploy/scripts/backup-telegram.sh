#!/usr/bin/env bash
set -euo pipefail

# ──────────────────────────────────────────────────
#  PêchCompta — backup-telegram.sh
#  Sauvegarde la base Postgres puis envoie le dump
#  dans un chat Telegram (copie hors-site).
#
#  Usage:  bash scripts/backup-telegram.sh
#  Planifié : cron chaque jour à 03:10 (voir install-telegram-backup-cron.sh).
#  Config : TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID dans deploy/.env
# ──────────────────────────────────────────────────

DEPLOY_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$DEPLOY_DIR"

BACKUP_DIR="$HOME/peche-backups"
mkdir -p "$BACKUP_DIR"

STAMP="$(date +%Y-%m-%d_%H%M)"
OUT="$BACKUP_DIR/peche-telegram-$STAMP.sql.gz"

echo "[backup-telegram] Dump $OUT ..."
docker compose exec -T db pg_dump -U peche peche | gzip > "$OUT"

TOKEN="$(grep -E '^TELEGRAM_BOT_TOKEN=' .env | cut -d= -f2-)"
CHAT_ID="$(grep -E '^TELEGRAM_CHAT_ID=' .env | cut -d= -f2-)"

if [ -z "$TOKEN" ] || [ -z "$CHAT_ID" ]; then
  echo "[backup-telegram] ERREUR : TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID absents du .env"
  rm -f "$OUT"
  exit 1
fi

SIZE="$(du -h "$OUT" | cut -f1)"
echo "[backup-telegram] Envoi vers Telegram ($SIZE) ..."

RESPONSE="$(curl -s -A "PechComptaBackup/1.0" \
  -F "chat_id=$CHAT_ID" \
  -F "document=@$OUT" \
  "https://api.telegram.org/bot$TOKEN/sendDocument")"

if echo "$RESPONSE" | grep -q '"ok":true'; then
  echo "[backup-telegram] OK : $SIZE envoyé à Telegram."
else
  echo "[backup-telegram] ERREUR API Telegram : $RESPONSE"
  rm -f "$OUT"
  exit 1
fi