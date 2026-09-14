#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

pass() { printf 'PASS  %s\n' "$1"; }
fail() { printf 'FAIL  %s\n' "$1" >&2; exit 1; }
contains() { rg -q --fixed-strings "$2" "$1" || fail "$3"; pass "$3"; }

# Recette hors ligne : contrats indispensables P0/P1/P2.
php -l app/Controllers/Api/AtakApiController.php >/dev/null && pass 'syntaxe contrôleur SSE'
php -l app/Controllers/Web/AtakController.php >/dev/null && pass 'syntaxe contrôleur Overwatch'
php -l app/Support/AtakChatChannel.php >/dev/null && pass 'syntaxe canaux'
php -l views/atak.php >/dev/null && pass 'syntaxe vue ATAK'
node --check public/assets/js/atak-realtime.js && pass 'syntaxe client SSE'
node --check public/assets/js/atak-overwatch-beta.js && pass 'syntaxe adaptateur Overwatch'
node --check public/assets/js/atak-overwatch-p2.js && pass 'syntaxe outils P2'
contains views/atak-overwatch-beta.php "require base_path('views/atak.php')" 'P0 utilise le client ATAK réel'
contains app/Controllers/Api/AtakApiController.php "text/event-stream; charset=utf-8" 'P1 expose le flux SSE'
contains public/assets/js/atak-realtime.js "setRealtime(false)" 'P1 conserve le fallback polling'
contains public/assets/js/atak-overwatch-p2.js "MAX_IMPORT_SHAPES = 50" 'P2 borne les imports'
contains public/assets/js/atak-overwatch-p2.js "ATAKMapShapes.createShape" 'P2 réutilise les écritures serveur'
contains app/Support/AtakChatChannel.php "'support' => 'Support technique'" 'P1 fournit le canal Support'
git diff --check && pass 'aucune erreur whitespace dans le diff'

BASE_URL="${ATAK_RECIPE_BASE_URL:-}"
COOKIE_JAR="${ATAK_RECIPE_COOKIE_JAR:-}"
if [[ -z "$BASE_URL" ]]; then
  printf 'SKIP  recette live (définir ATAK_RECIPE_BASE_URL et ATAK_RECIPE_COOKIE_JAR)\n'
  exit 0
fi
BASE_URL="${BASE_URL%/}"
command -v curl >/dev/null || fail 'curl est requis pour la recette live'

# Sans cookie, le stream ne doit jamais répondre comme un flux opérationnel.
unauth_headers="$(mktemp)"; trap 'rm -f "$unauth_headers" "${beta_body:-}" "${stream_body:-}" "${stream_headers:-}"' EXIT
unauth_code="$(curl -sS -o /dev/null -D "$unauth_headers" -w '%{http_code}' --max-time 8 "$BASE_URL/api/atak/stream?mapId=1" || true)"
[[ "$unauth_code" == 401 || "$unauth_code" == 403 ]] || fail "stream anonyme refusé (HTTP $unauth_code)"
pass "stream anonyme refusé (HTTP $unauth_code)"

[[ -n "$COOKIE_JAR" && -r "$COOKIE_JAR" ]] || fail 'ATAK_RECIPE_COOKIE_JAR doit pointer vers un cookie jar curl authentifié'
beta_body="$(mktemp)"
beta_code="$(curl -sS -b "$COOKIE_JAR" -o "$beta_body" -w '%{http_code}' --max-time 15 "$BASE_URL/-ATAK-OVERWATCH-Beta")"
[[ "$beta_code" == 200 ]] || fail "page beta authentifiée (HTTP $beta_code)"
rg -q 'id="overwatch-commandbar"' "$beta_body" || fail 'chrome Overwatch absent'
! rg -q 'CONTACT C-018|ALPHA 1-1.*8 PAX' "$beta_body" || fail 'données de maquette encore présentes'
pass 'page Overwatch réelle sans contacts de démonstration'

stream_body="$(mktemp)"; stream_headers="$(mktemp)"
# curl termine volontairement après 9 s : le code 28 est attendu pour un flux ouvert.
set +e
curl -sS -N -b "$COOKIE_JAR" -D "$stream_headers" -o "$stream_body" --max-time 9 "$BASE_URL/api/atak/stream?mapId=1"
curl_code=$?
set -e
[[ $curl_code -eq 0 || $curl_code -eq 28 ]] || fail "connexion SSE (curl=$curl_code)"
rg -qi '^content-type: text/event-stream' "$stream_headers" || fail 'MIME SSE absent'
rg -q '^: heartbeat ' "$stream_body" || fail 'heartbeat SSE absent'
rg -q '^event: units$' "$stream_body" || fail 'snapshot unités SSE absent'
rg -q '^event: chat$' "$stream_body" || fail 'snapshot chat SSE absent'
rg -q '^event: alerts$' "$stream_body" || fail 'snapshot alertes SSE absent'
pass 'flux SSE authentifié unités/chat/alertes avec heartbeat'
printf 'OK    recette Overwatch Beta P0/P1/P2 terminée\n'
