#!/usr/bin/env bash
set -euo pipefail

# ──────────────────────────────────────────────────
#  PêchCompta — backup.sh
#  Sauvegarde la base Postgres (conteneur docker db).
#  Garde les 30 derniers jours.
#
#  Usage:  bash scripts/backup.sh
#  Planifié : cron chaque jour à 03:00 (voir install-backup-cron.sh).
# ──────────────────────────────────────────────────

DEPLOY_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$DEPLOY_DIR"

BACKUP_DIR="$HOME/peche-backups"
KEEP_DAYS=30

mkdir -p "$BACKUP_DIR"

STAMP="$(date +%Y-%m-%d_%H%M)"
OUT="$BACKUP_DIR/peche-$STAMP.sql.gz"

echo "[backup] Dump $OUT ..."
docker compose exec -T db pg_dump -U peche peche | gzip > "$OUT"

SIZE=$(du -h "$OUT" | cut -f1)
echo "[backup] OK ($SIZE)."

# Purge des vieux backups (avant $KEEP_DAYS jours)
echo "[backup] Purge des fichiers de plus de $KEEP_DAYS jours..."
find "$BACKUP_DIR" -name 'peche-*.sql.gz' -mtime +"$KEEP_DAYS" -delete

echo "[backup] Contenu du dossier :"
ls -lh "$BACKUP_DIR"