#!/usr/bin/env bash
set -euo pipefail

# ──────────────────────────────────────────────────
#  PêchCompta — setup-vps.sh
#  À lancer en SSH sur ton VPS OVHcloud (Ubuntu/Debian).
#  Usage:  bash setup-vps.sh <IP_DU_VPS>
#
#  Prérequis : le dépôt est cloné sur le VPS, .env créé
#  (cp .env.example .env + mot de passe Postgres rentré).
#  Le script passe par sudo si tu n'es pas root.
# ──────────────────────────────────────────────────

IP="${1:?Usage: bash setup-vps.sh <IP_DU_VPS>}"
SSLIP_HOST="${IP//./-}.sslip.io"
DEPLOY_DIR="$(cd "$(dirname "$0")" && pwd)"

cd "$DEPLOY_DIR"

[ -f .env ] || { echo "Erreur : .env absent. Fais : cp .env.example .env puis remplis POSTGRES_PASSWORD."; exit 1; }
grep -q "CHANGEMOI_12_CARACTERES_MIN" .env && { echo "Erreur : POSTGRES_PASSWORD pas encore changé dans .env."; exit 1; }

# sudo automatique si nécessaire
if [ "$(id -u)" -ne 0 ]; then
  SUDO="sudo"
else
  SUDO=""
fi

echo "============================================"
echo "  PêchCompta — Setup VPS"
echo "  OS: $(. /etc/os-release && echo "$NAME $VERSION")"
echo "  IP: $IP   sslip: $SSLIP_HOST"
echo "============================================"

# ── 1. Docker + Compose ──
if ! command -v docker &>/dev/null; then
  echo "[1/7] Installation de Docker..."
  $SUDO apt-get update -qq
  $SUDO apt-get install -y -qq ca-certificates curl gnupg openssl git
  $SUDO install -m 0755 -d /etc/apt/keyrings
  curl -fsSL https://download.docker.com/linux/$(. /etc/os-release && echo "$ID")/gpg | $SUDO gpg --dearmor -o /etc/apt/keyrings/docker.gpg
  $SUDO chmod a+r /etc/apt/keyrings/docker.gpg
  . /etc/os-release
  echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/$ID $VERSION_CODENAME stable" | $SUDO tee /etc/apt/sources.list.d/docker.list >/dev/null
  $SUDO apt-get update -qq
  $SUDO apt-get install -y -qq docker-ce docker-ce-cli containerd.io docker-compose-plugin
  # Permettre à l'utilisateur courant d'utiliser docker sans sudo
  $SUDO usermod -aG docker "$USER" 2>/dev/null || true
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

# ── 4. Lancer les services (docker compose peut demander sudo au premier lancement) ──
echo "[4/7] Lancement des services (build + up)..."
DC="docker compose"
if ! docker info >/dev/null 2>&1; then
  DC="$SUDO docker compose"
fi
$DC up -d --build

# ── 5. Attendre que Postgres soit sain ──
echo "[5/7] Attente de Postgres..."
for _ in $(seq 1 30); do
  if $DC exec -T db pg_isready -U peche -d peche >/dev/null 2>&1; then echo "   Postgres prêt."; break; fi
  sleep 2
done

# ── 6. Attendre que le conteneur backend soit up (migrate + seed) ──
echo "[6/7] Attente du backend (migrate + seed)..."
for _ in $(seq 1 45); do
  STATUS=$($DC ps -q backend 2>/dev/null | xargs -I{} docker inspect -f '{{.State.Running}}' {} 2>/dev/null || true)
  [ "$STATUS" = "true" ] && { echo "   Backend démarré."; break; }
  sleep 2
done

# ── 7. Vérification finale (test du login via HTTPS public) ──
echo "[7/7] Vérification finale…"
sleep 12
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