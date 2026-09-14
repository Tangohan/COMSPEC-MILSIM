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
php -l views/atak-overwatch-beta.php >/dev/null && pass 'syntaxe vue Overwatch Beta'
node --check public/assets/js/atak-realtime.js && pass 'syntaxe client SSE'
node --check public/assets/js/atak-overwatch-beta.js && pass 'syntaxe adaptateur Overwatch'
node --check public/assets/js/atak-overwatch-gotak.js && pass 'syntaxe outils Overwatch'
node --check public/assets/js/atak-overwatch-p2.js && pass 'syntaxe outils P2'
contains views/atak-overwatch-beta.php 'id="ow-map"' 'P0 carte Overwatch autonome'
contains views/atak-overwatch-beta.php 'atak-map-crs.js' 'P0 CRS Arma'
contains views/atak-overwatch-beta.php 'data-tool="po"' 'outil points à atteindre'
contains views/atak-overwatch-beta.php 'data-tool="rally"' 'outil points de ralliement'
contains public/assets/js/atak-overwatch-beta.js 'placeRallyPoint' 'pose des ralliements depuis le poste'
contains public/assets/js/atak-overwatch-beta.js "zone_type: 'RALLY_POINT'" 'type zone ralliement'
contains views/atak-overwatch-beta.php 'id="ow-group-task-host"' 'formulaire tâches de groupe'
contains views/atak-overwatch-beta.php 'id="ow-fs-alert-host"' 'formulaire alerte plein écran'
contains public/assets/js/atak-overwatch-beta.js 'placeReachPoint' 'pose des PO depuis le poste'
contains public/assets/js/atak-overwatch-beta.js 'submitGroupTask' 'transmission tâche de groupe'
contains public/assets/js/atak-overwatch-beta.js 'submitFullscreenAlert' 'alerte plein écran depuis le poste'
contains public/assets/js/atak-overwatch-beta.js "order_type: 'NOTIFY_FULL'" 'type alerte plein écran'
contains public/assets/js/atak-overwatch-beta.js 'data.detection' 'marqueurs suivis par règle'
contains views/admin/organization/atak_marker_detection.php 'Nom de la règle' 'page détection des marqueurs'
contains routes/web.php 'back-office/atak/detection-marqueurs' 'route détection des marqueurs'
contains mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollPoMarkers.sqf 'Point suivi atteint' 'confirmation en jeu des points suivis'
php -l app/Support/AtakMarkerDetection.php >/dev/null && pass 'syntaxe détection marqueurs'
php -l app/Services/Tactical/AtakMarkerDetectionService.php >/dev/null && pass 'syntaxe service détection'
php -l app/Controllers/Admin/Organization/AtakMarkerDetectionAdminController.php >/dev/null && pass 'syntaxe admin détection'
contains public/assets/js/atak-overwatch-beta.js '/api/atak/orders' 'tâches de groupe vers le poste'
contains public/assets/js/atak-overwatch-beta.js '/api/atak/markers/' 'confirmation PO depuis le poste'
contains app/Support/AtakPoMarker.php 'RADIUS_M = 20' 'rayon de confirmation 20 m'
contains mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollPoMarkers.sqf '_radius = 20' 'anneau PO en jeu'
contains views/atak-overwatch-beta.php 'id="ow-settings"' 'aside réglages'
contains views/atak-overwatch-beta.php 'id="ow-chat"' 'aside tchat'
contains public/assets/js/atak-overwatch-beta.js 'MGRS_CRS' 'CRS tuiles Atlas'
contains public/assets/js/atak-overwatch-gotak.js '/api/sse/notes/web' 'fiches renseignement poste'
contains public/assets/js/atak-overwatch-gotak.js '/api/atak/terrain/profile' 'profil de relief'
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
rg -q 'id="ow-map"' "$beta_body" || fail 'carte Overwatch absente'
rg -q 'id="ow-disclaimer"' "$beta_body" || fail 'disclaimer absent'
rg -q 'id="ow-settings"' "$beta_body" || fail 'aside réglages absent'
rg -q 'id="ow-chat"' "$beta_body" || fail 'aside tchat absent'
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
