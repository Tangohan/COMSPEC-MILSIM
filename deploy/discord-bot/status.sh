#!/usr/bin/env bash
# Contrôle de santé du bot Discord Athena : service systemd, battement de cœur, point de santé local, dernières lignes du journal.
# Code de sortie 0 si tout va bien, 1 sinon (utilisable par une supervision). N'affiche jamais le jeton ni la clé.
#
#   sudo /opt/athena-discord-bot/status.sh
set -uo pipefail

SERVICE=athena-discord-bot
HEARTBEAT=/run/athena-discord-bot/heartbeat
LOG=/var/log/athena-discord-bot/bot.log
PORT=8765
MAX_BEAT_AGE=120
bad=0

if systemctl is-active --quiet "$SERVICE"; then
  echo "service     : actif depuis $(systemctl show "$SERVICE" -p ActiveEnterTimestamp --value)"
else
  echo "service     : ARRÊTÉ ($(systemctl is-active "$SERVICE" 2>/dev/null || true))"
  bad=1
fi
echo "au démarrage: $(systemctl is-enabled "$SERVICE" 2>/dev/null || echo inconnu)"
echo "redémarrages: $(systemctl show "$SERVICE" -p NRestarts --value 2>/dev/null || echo ?)"

if [ -f "$HEARTBEAT" ]; then
  now="$(date +%s)"
  at="$(sed -n 's/.*"at": *\([0-9]*\).*/\1/p' "$HEARTBEAT" | head -n1)"
  guilds="$(sed -n 's/.*"guilds": *\([0-9]*\).*/\1/p' "$HEARTBEAT" | head -n1)"
  if [ -n "$at" ] && [ $((now - at)) -le "$MAX_BEAT_AGE" ]; then
    echo "battement   : il y a $((now - at)) s, ${guilds:-?} serveur(s) Discord"
  else
    echo "battement   : ABSENT OU ANCIEN (le bot ne répond plus ?)"
    bad=1
  fi
else
  echo "battement   : fichier absent ($HEARTBEAT)"
  bad=1
fi

if command -v curl >/dev/null 2>&1; then
  if body="$(curl -fsS --max-time 3 "http://127.0.0.1:$PORT/health" 2>/dev/null)"; then
    echo "santé locale: $body"
  else
    echo "santé locale: PAS DE RÉPONSE sur 127.0.0.1:$PORT (connexion Discord ou file d'événements en défaut)"
    bad=1
  fi
fi

if [ -f "$LOG" ]; then
  echo "--- dernières lignes de $LOG"
  tail -n 5 "$LOG"
fi

exit "$bad"
