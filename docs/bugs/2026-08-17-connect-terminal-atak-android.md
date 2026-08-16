# Connect — terminal ATAK Android (bezel + carte)

## Contexte

Après `/connect` → choix Carte, l’utilisateur tombait sur `/atak` plein écran
sans le chrome téléphone IceMan / cTab Android.

## Livraison

- Conversion `comspec_phone_bg_ca.paa` → PNG web (`public/assets/img/connect-device/`)
- Vue `views/atak/connect_device.php` : bezel + OSD + iframe `/atak?embed=device`
- `openCarte` rend cette vue (plus de simple redirect)
- Mode `body.atak-device-embed` : UI carte compacte dans le trou d’écran

## Géométrie

Canvas 2048², écran `(452,713)` / `1134×624` — aligné sur
`display_device_macros.hpp`.

## Fichiers

- `views/atak/connect_device.php`
- `public/assets/css/connect-device.css`
- `public/assets/js/connect-device.js`
- `public/assets/img/connect-device/*`
- `app/Controllers/Web/AtakPhoneConnectController.php`
- `views/atak.php`, `public/assets/css/atak.css`

## Vérification

`/connect` → code → **Terminal ATAK** : coque Android visible, carte Arma
dans l’écran, BFT / ordres accessibles.

## Statut

Livré
