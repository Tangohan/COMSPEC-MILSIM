#!/usr/bin/env bash
# Installation et mise à jour du bot Discord Athena sur le VPS. Idempotent : on peut le relancer à volonté.
#
#   sudo deploy/discord-bot/install.sh              installe ou met à jour, active le service ; le démarre seulement
#                                                   s'il est arrêté et que le fichier d'environnement est rempli
#   sudo deploy/discord-bot/install.sh --restart    redémarre aussi le service s'il tourne (à utiliser après une mise à jour du code)
#   sudo deploy/discord-bot/install.sh --no-start   installe sans rien démarrer
#
# Ce script n'affiche jamais le contenu du fichier d'environnement. Procédure complète : docs/discord-bot-vps.md.
set -euo pipefail

RESTART=0
NO_START=0
for arg in "$@"; do
  case "$arg" in
    --restart) RESTART=1 ;;
    --no-start) NO_START=1 ;;
    -h|--help) sed -n '2,10p' "$0"; exit 0 ;;
    *) echo "Option inconnue : $arg" >&2; exit 2 ;;
  esac
done

if [ "$(id -u)" -ne 0 ]; then
  echo "À lancer en root (sudo)." >&2
  exit 1
fi
command -v systemctl >/dev/null || { echo "systemd est requis." >&2; exit 1; }

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SRC="$REPO/bots/athena-companion"
APP=/opt/athena-discord-bot
ENV_DIR=/etc/athena-discord-bot
ENV_FILE="$ENV_DIR/bot.env"
UNIT=/etc/systemd/system/athena-discord-bot.service
SERVICE=athena-discord-bot
USER_NAME=athena-bot

[ -f "$SRC/main.py" ] || { echo "Code du bot introuvable dans $SRC." >&2; exit 1; }

# Python 3.12 ou plus (discord.py 2.7).
PY="$(command -v python3.12 || command -v python3.13 || command -v python3 || true)"
[ -n "$PY" ] || { echo "Python 3.12 ou plus est requis." >&2; exit 1; }
"$PY" -c 'import sys; sys.exit(0 if sys.version_info >= (3, 12) else 1)' || { echo "Python 3.12 ou plus est requis (trouvé : $("$PY" -V))." >&2; exit 1; }
"$PY" -c 'import venv, ensurepip' 2>/dev/null || { echo "Le module venv manque : apt install python3-venv" >&2; exit 1; }

echo "== Compte système"
if ! id "$USER_NAME" >/dev/null 2>&1; then
  useradd --system --no-create-home --home-dir /nonexistent --shell /usr/sbin/nologin "$USER_NAME"
  echo "   compte $USER_NAME créé"
else
  echo "   compte $USER_NAME déjà présent"
fi

echo "== Code du bot"
install -d -m 0755 "$APP" "$APP/app" "$APP/docs"
CHANGED=0
for f in api.py bureau.py config.py health.py i18n.py main.py state.py requirements.txt; do
  if ! cmp -s "$SRC/$f" "$APP/app/$f" 2>/dev/null; then
    install -m 0644 "$SRC/$f" "$APP/app/$f"
    CHANGED=1
  fi
done
install -m 0644 "$REPO/docs/discord-bot-vps.md" "$APP/docs/discord-bot-vps.md" 2>/dev/null || true
install -m 0755 "$REPO/deploy/discord-bot/status.sh" "$APP/status.sh"
echo "   fichiers du bot à jour (modifiés : $([ "$CHANGED" = 1 ] && echo oui || echo non))"

echo "== Environnement Python"
if [ ! -x "$APP/venv/bin/python" ]; then
  "$PY" -m venv "$APP/venv"
fi
"$APP/venv/bin/python" -m pip install --quiet --disable-pip-version-check --upgrade pip
"$APP/venv/bin/python" -m pip install --quiet --disable-pip-version-check -r "$APP/app/requirements.txt"
echo "   dépendances installées"

echo "== Fichier d'environnement"
install -d -m 0750 -o root -g "$USER_NAME" "$ENV_DIR"
if [ ! -f "$ENV_FILE" ]; then
  install -m 0640 -o root -g "$USER_NAME" "$REPO/deploy/discord-bot/bot.env.example" "$ENV_FILE"
  echo "   $ENV_FILE créé depuis le modèle : à remplir (jeton, clé) avant de démarrer"
else
  chown root:"$USER_NAME" "$ENV_FILE"
  chmod 0640 "$ENV_FILE"
  echo "   $ENV_FILE présent (non modifié)"
fi

echo "== Service et rotation des journaux"
if ! cmp -s "$REPO/deploy/systemd/athena-discord-bot.service" "$UNIT" 2>/dev/null; then
  install -m 0644 "$REPO/deploy/systemd/athena-discord-bot.service" "$UNIT"
  CHANGED=1
fi
install -m 0644 "$REPO/deploy/discord-bot/athena-discord-bot.logrotate" /etc/logrotate.d/athena-discord-bot
systemctl daemon-reload
systemctl enable "$SERVICE" >/dev/null 2>&1
echo "   service activé au démarrage du serveur"

# Le fichier est-il rempli ? On teste sans jamais afficher les valeurs.
env_ready() {
  local token key
  token="$(grep -E '^DISCORD_TOKEN=' "$ENV_FILE" | head -n1 | cut -d= -f2- || true)"
  key="$(grep -E '^ATHENA_COMPANION_API_KEY=' "$ENV_FILE" | head -n1 | cut -d= -f2- || true)"
  [ -n "$token" ] && [ "$token" != "à remplir" ] && [ "${#key}" -ge 32 ] && [ "$key" != "à remplir" ]
}

echo "== Démarrage"
if [ "$NO_START" = 1 ]; then
  echo "   --no-start : rien n'est démarré"
elif ! env_ready; then
  echo "   $ENV_FILE n'est pas rempli : le service reste arrêté. Remplissez-le puis relancez ce script."
elif systemctl is-active --quiet "$SERVICE"; then
  if [ "$RESTART" = 1 ]; then
    systemctl restart "$SERVICE"
    echo "   service redémarré"
  elif [ "$CHANGED" = 1 ]; then
    echo "   le service tourne avec l'ancienne version : relancez avec --restart pour appliquer la mise à jour"
  else
    echo "   le service tourne déjà, rien à faire"
  fi
else
  systemctl start "$SERVICE"
  echo "   service démarré"
fi

echo
"$APP/status.sh" || true
