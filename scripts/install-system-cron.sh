#!/usr/bin/env bash
# Installe le passage automatique des tâches Athena (toutes les 5 minutes).
# Usage, à la racine du site, en SSH : bash scripts/install-system-cron.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PHP_BIN="${PHP_CLI:-${PHP_BIN:-$(command -v php || true)}}"
if [[ -z "${PHP_BIN}" ]]; then
  echo "php introuvable. Installez le binaire en ligne de commande, puis relancez." >&2
  exit 1
fi

mkdir -p "${ROOT}/storage/logs"
SCRIPT="${ROOT}/scripts/cron-run.php"
LOG="${ROOT}/storage/logs/cron.log"
LOCK="/tmp/athena-cron.lock"
MARKER="# athena-cron-run"

if command -v flock >/dev/null 2>&1; then
  LINE="*/5 * * * * flock -n ${LOCK} ${PHP_BIN} ${SCRIPT} >> ${LOG} 2>&1"
else
  LINE="*/5 * * * * ${PHP_BIN} ${SCRIPT} >> ${LOG} 2>&1"
fi

EXISTING="$(crontab -l 2>/dev/null || true)"
FILTERED="$(printf '%s\n' "${EXISTING}" | grep -vF "${MARKER}" | grep -vF "scripts/cron-run.php" || true)"
{
  if [[ -n "${FILTERED}" ]]; then
    printf '%s\n' "${FILTERED}"
  fi
  echo "${MARKER}"
  echo "${LINE}"
} | crontab -

echo "Passage automatique installé (toutes les 5 minutes) :"
echo "  ${LINE}"
