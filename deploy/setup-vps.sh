#!/usr/bin/env bash
set -euo pipefail

# ──────────────────────────────────────────────────
#  PêchCompta — setup-vps.sh
#  À lancer en SSH sur ton VPS OVHcloud (Debian 12).
#  Usage:  bash setup-vps.sh <IP_DU_VPS>
#
#  Prérequis : le dépôt est cloné sur le VPS, .env créé
#  (cp .env.example .env + mot de passe Postgres rentré).
# ──────────────────────────────────────────────────

IP="${1:?Usage: bash setup-vps.sh <IP_DU_VPS>}"
SSLIP_HOST="${IP//./-}.sslip.io"
DEPLOY_DIR="$(cd "$(dirname "$0")" && pwd)"

cd "$DEPLOY_DIR"

[ -f .env ] || { echo "Erreur : .env absent. Fais : cp .env.example .env puis remplis POSTGRES_PASSWORD."; exit 1; }
grep -q "CHANGEMOI_12_CARACTERES_MIN" .env && { echo "Erreur : POSTGRES_PASSWORD pas encore changé dans .env."; exit 1; }

echo "============================================"
echo "  PêchCompta — Setup VPS"
echo "  IP: $IP   sslip: $SSLIP_HOST"
echo "============================================"

# ── 1. Docker + Compose ──
if ! command -v docker &>/dev/null; then
  echo "[1/7] Installation de Docker..."
  apt-get update -qq
  apt-get install -y -qq ca-certificates curl gnupg openssl
  install -m 0755 -d /etc/apt/keyrings
  curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
  chmod a+r /etc/apt/keyrings/docker.gpg
  . /etc/os-release
  echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/debian $VERSION_CODENAME stable" > /etc/apt/sources.list.d/docker.list
  apt-get update -qq
  apt-get install -y -qq docker-ce docker-ce-cli containerd.io docker-compose-plugin
else
  echo "[1/7] Docker déjà installé."
fi

# ── 2. Générer APP_KEY ──
echo "[2/7] Génération de APP_KEY..."
NEW_KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
sed -i "s|^APP_KEY=.*|APP_KEY=$NEW_KEY|" .env

# ── 3. Renseigner SSLIP_HOST ──
echo "[3/7] Renseignement de SSLIP_HOST → $SSLIP_HOST"
sed -i "s|^SSLIP_HOST=.*|SSLIP_HOST=$SSLIP_HOST|" .env

# ── 4. Lancer les services ──
echo "[4/7] Lancement des services (build + up)..."
docker compose up -d --build

# ── 6. Attendre que Postgres soit sain ──
echo "[5/7] Attente de Postgres..."
for _ in $(seq 1 30); do
  if docker compose exec -T db pg_isready -U peche -d peche >/dev/null 2>&1; then echo "   Postgres prêt."; break; fi
  sleep 2
done

# ── 7. Attendre que le conteneur backend soit up (migrate + seed) ──
echo "[6/7] Attente du backend (migrate + seed)..."
for _ in $(seq 1 30); do
  STATUS=$(docker inspect -f '{{.State.Running}}' "$(docker compose ps -q backend)" 2>/dev/null || true)
  [ "$STATUS" = "true" ] && { echo "   Backend démarré."; break; }
  sleep 2
done

# ── 8. Vérification finale ──
echo "[7/7] Vérification finale…"
sleep 5
HTTP_CODE=$(curl -s -o /dev/null -w '%{http_code}' -X POST "https://$SSLIP_HOST/api/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@peche.com","password":"password"}' || true)
echo "   POST /api/login (expect 200) → $HTTP_CODE"

echo ""
echo "============================================"
if [ "$HTTP_CODE" = "200" ]; then
  echo "  DÉPLOYÉ AVEC SUCCÈS !"
else
  echo "  DÉPLOIEMENT ACTIF, MAIS LOGIN ≠ 200 (voir logs)."
fi
echo "  API:  https://$SSLIP_HOST"
echo "  IP:   $IP"
echo "============================================"
echo ""
echo "Étape suivante (sur Vercel) :"
echo "  1. Dashboard Vercel → project → Settings → Environment Variables"
echo "  2. Ajouter VITE_API_URL = https://$SSLIP_HOST"
echo "  3. Redéployer le frontend"
echo ""
echo "Pour migrer les données Neon → VPS après coup :"
echo "  bash scripts/migrate-from-neon.sh"
echo ""
echo "SECURITÉ : change le mot de passe admin dès la première connexion."