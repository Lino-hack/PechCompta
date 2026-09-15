#!/usr/bin/env bash
set -euo pipefail

# ──────────────────────────────────────────────────
#  PêchCompta — install-backup-cron.sh
#  Ajoute la sauvegarde hebdomadaire (dimanche 03:00)
#  dans le crontab de l'utilisateur courant.
#
#  Usage:  bash scripts/install-backup-cron.sh
# ──────────────────────────────────────────────────

if [ ! -f docker-compose.yml ]; then
  echo "Erreur : lance depuis le dossier deploy (cd ~/PechCompta/deploy)."
  exit 1
fi

SCRIPT_DIR="$(pwd)/scripts/backup.sh"
chmod +x "$SCRIPT_DIR"

CRON_LINE="0 3 * * 0 /bin/bash $SCRIPT_DIR >> $HOME/peche-backups/backup.log 2>&1"

# Éviter les doublons (on retire les anciennes lignes backup.sh)
( crontab -l 2>/dev/null | grep -v 'peche-backups/backup.sh' || true; echo "$CRON_LINE" ) | crontab -

echo "Cron installé : $CRON_LINE"
echo "Vérification :"
crontab -l | grep backup