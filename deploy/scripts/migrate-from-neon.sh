#!/usr/bin/env bash
set -euo pipefail

# ──────────────────────────────────────────────────
#  PêchCompta — migrate-from-neon.sh
#  À lancer sur le VPS (dans le dossier deploy).
#  Migre les données Neon → Postgres Docker local.
#
#  Usage:  bash scripts/migrate-from-neon.sh
#  Prérequis : services up (docker compose ps) + .env avec POSTGRES_PASSWORD.
# ──────────────────────────────────────────────────

DEPLOY_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$DEPLOY_DIR"

[ -f .env ] || { echo "Erreur : .env absent."; exit 1; }

NEON_HOST="ep-cool-sky-b2y1ztjc.c-6.eu-central-1.aws.neon.tech"
NEON_DB="neondb"
NEON_USER="neondb_owner"
NEON_PASS="npg_SyQwHtP6oX4u"
NEON_PORT="5432"

echo "============================================"
echo "  Migration Neon → VPS"
echo "  Source : $NEON_HOST/$NEON_DB"
echo "  Cible  : db (Docker) / peche"
echo "============================================"
read -rp "Continuer ? (y/N) " CONFIRM
[ "${CONFIRM:-n}" = "y" ] || { echo "Annulé."; exit 0; }

# ── 1. Dump de Neon via un conteneur postgres jetable ──
echo ""
echo "[1/4] Dump de Neon..."
# Neon tourne en Postgres 18 : on dê une 18 pour dumper (compat asc/desc)
docker run --rm \
  -e PGPASSWORD="$NEON_PASS" \
  postgres:18 pg_dump \
  -h "$NEON_HOST" -p "$NEON_PORT" -U "$NEON_USER" -d "$NEON_DB" \
  --no-owner --no-privileges --clean --if-exists \
  -f /tmp/neon_dump.sql 2>&1
# Échec réel du dump ?
if [ ! -s /tmp/neon_dump.sql ]; then
  echo "ERREUR : dump Neon vide/absent — le pg_dump a échoué."
  exit 1
fi
echo "   Dump OK ($(wc -c < /tmp/neon_dump.sql) octets)."

# ── 2. Séquences MAX (avant écrasement) ──
echo "[2/4] Sauvegarde des valeurs max des séquences..."
docker compose exec -T db psql -U peche -d peche -At -c "
SELECT tablename, pg_get_serial_sequence(tablename, columnname) AS seq
FROM (
  SELECT t.table_name AS tablename, c.column_name AS columnname
  FROM information_schema.columns c
  JOIN information_schema.tables t
    ON c.table_name = t.table_name AND c.table_schema = t.table_schema
  WHERE c.table_schema = 'public' AND c.column_default LIKE 'nextval%'
) sub
WHERE pg_get_serial_sequence(sub.tablename, sub.columnname) IS NOT NULL" > /tmp/seqs.txt 2>&1 || echo ""
echo "   $(wc -l < /tmp/seqs.txt) séquences détectées."

# ── 3. Restauration ──
echo "[3/4] Restauration dans le VPS..."
docker compose exec -T db psql -U peche -d peche -v ON_ERROR_STOP=0 --single-transaction -f /tmp/neon_dump.sql 2>&1 | tail -5 || true
echo "   Restauration OK."

# ── 4. Reset des séquences ──
echo "[4/4] Mise à jour des séquences..."
while IFS='|' read -r tbl seq; do
  [ -n "${seq:-}" ] || continue
  docker compose exec -T db psql -U peche -d peche -c \
    "SELECT setval('$seq', COALESCE((SELECT MAX(id) FROM \"$tbl\"), 1))" >/dev/null 2>&1 || true
done < /tmp/seqs.txt
echo "   Séquences corrigées."

echo ""
echo "============================================"
echo "  MIGRATION TERMINÉE !"
docker compose exec -T db psql -U peche -d peche -At -c \
  "SELECT count(*) || ' tables restaurées' FROM information_schema.tables WHERE table_schema='public'" 2>/dev/null || true
echo "============================================"