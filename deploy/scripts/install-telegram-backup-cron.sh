#!/usr/bin/env bash
set -euo pipefail

# ──────────────────────────────────────────────────
#  PêchCompta — install-telegram-backup-cron.sh
#  Ajoute la sauvegarde Telegram quotidienne (03:10)
#  dans le crontab de l'utilisateur courant.
#
#  Usage:  bash scripts/install-telegram-backup-cron.sh
# ──────────────────────────────────────────────────

if [ ! -f docker-compose.yml ]; then
  echo "Erreur : lance depuis le dossier deploy (cd ~/PechCompta/deploy)."
  exit 1
fi

SCRIPT_DIR="$(pwd)/scripts/backup-telegram.sh"
chmod +x "$SCRIPT_DIR"

CRON_LINE="10 3 * * * /bin/bash $SCRIPT_DIR >> $HOME/peche-backups/backup-telegram.log 2>&1"

# Éviter les doublons (on retire les anciennes lignes backup-telegram.sh)
( crontab -l 2>/dev/null | grep -v 'backup-telegram.sh' || true; echo "$CRON_LINE" ) | crontab -

echo "Cron installé : $CRON_LINE"
echo "Vérification :"
crontab -l | grep backup-telegram