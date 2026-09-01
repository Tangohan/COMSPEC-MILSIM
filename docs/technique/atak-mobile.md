# ATAK Mobile — architecture téléphone (QR détaché)

## Objectif

Surface **COMSPEC ATAK** dédiée au téléphone physique, ouverte via QR / `/connect/{token}/…`.  
Ce n’est **pas** le dashboard desktop compressé : pas d’iframe, pas de chrome C2 desktop.

## Routes

| Route | Rôle |
|-------|------|
| `/atak/mobile` | Shell mobile, module C2 |
| `/atak/mobile/{module}` | Module direct (`c2`, `sitac`, `chat`, `bft`, `status`, `pings`, `intel`, `jtac`, `air`, `sigint`, `orders`, `explosives`) |
| `/connect/{token}/carte` → redirect | → `/atak/mobile/sitac` |
| `/connect/{token}/tchat` → redirect | → `/atak/mobile/chat` |
| `/connect/{token}/sitac` → redirect | → `/atak/mobile/sitac` |
| `/connect/{token}/c2` → redirect | → `/atak/mobile/c2` |

Accès : `AtakWebAccessMiddleware` (session membre **ou** session téléphone après pairing).  
`tenant_id` vient **uniquement** de la session serveur authentifiée / pairing validé.

## Fichiers

- `app/Controllers/Web/AtakMobileController.php` — shell PHP
- `views/atak/mobile.php` — HTML (topbar, screens, bottom nav, drawer, sheet)
- `public/assets/css/atak-mobile.css` — UI sombre tactique + safe-area
- `public/assets/js/atak-mobile/atak-mobile.js` — navigation, polling, modules
- `public/assets/js/atak-map-crs.js` — CRS Arma (réutilisé)

## Navigation

Bottom nav fixe : **C2 · SITAC · TCHAT · BFT · PLUS**  
PLUS / ☰ ouvre un drawer modules. Header : COMSPEC, module, LIVE/STALE/OFFLINE, heure Zulu.

## APIs réutilisées (pas de 2ᵉ API)

- `GET /api/atak/units`, `/api/atak/stats`, `/api/atak/markers`, `/api/atak/activity`
- `GET|POST /api/chat`
- `GET|POST /api/pings`
- `GET /api/nine-line`, `/api/atak/air-assets`, `/api/atak/sigint/zones`
- `GET /api/atak/laser-codes`, `/api/atak/reports`, `/api/atak/intel-view`, `/api/atak/medical-alerts`
- `GET /api/atak/orders`, `/api/atak/explosive-timers`

Polling décalé par module ; suspendu si `document.hidden`.
Le tchat ne reconstruit pas la zone de saisie : seule la liste des messages est mise à jour.

## SITAC

Leaflet + CRS Simple Arma (`MGRS_CRS`), tuiles `tilePattern` du théâtre.  
Positions `pos_x` / `pos_y` (monde), pas de conversion GPS.  
Bottom-sheet unité : message / centrer / suivre / ping.

## Desktop

Inchangé. Le QR hub desktop continue de générer les PNG ; l’ouverture téléphone atterrit sur le shell mobile.
