# Bug — caméra casque ATAK vide + 503 en rafale

## Contexte

Vue ATAK web (`https://athena.ttrd.fr/public/atak`), onglet Caméras : tuile « Caméra casque — N-10 » sans image, statut « Hors ligne · il y a 2 min ». Console : 503 sur ping, ordres, stats, alertes médicales, logistique, photos terrain, codes laser. 404 tuiles carte `altis/3/-1/*.png` (hors carte, sans lien).

## Symptôme

La tuile caméra affiche le motif « pas de signal » : ce n’est pas un flux vidéo, et aucun aperçu photo n’était rattaché. En parallèle, presque toutes les requêtes du poste de commandement répondaient « service indisponible », donc photos et mises à jour ne remontaient pas.

## Cause

1. **Routes cassées** — dans `routes/web.php`, la ligne `/atak/sse/collecte` n’avait plus la parenthèse fermante. Au chargement, PHP refuse tout le fichier de routes : la page ATAK (parfois encore en cache) s’affiche, mais ping, ordres, photos, etc. répondent 503.
2. **Ping trop lourd** — même hors de ce bug, le ping ATAK passait par le gros contrôleur (ouverture base dès la construction). Une coupure MySQL, ou le garde-fou « communauté en session », renvoyait 503 y compris sur le ping, censé rester vivant.
2. **Aperçu vide** — la tuile vient du roster casque (fichier), pas d’une photo. Sans cliché récent jumelé (indicatif ou identifiant de caméra), `snapshot_url` reste vide → rayures. Les 503 photos empêchaient aussi de rattraper une image déjà reçue.
3. **404 tuiles** — bords de carte Altis, sans impact sur les caméras.

## Correctif

- Ping isolé, sans base ni profil de communauté.
- Liaison coupée : bandeau explicite, conservation du dernier roster / des dernières photos, texte « Aucun aperçu photo » sur la tuile.
- Rattachement d’un cliché récent du même indicatif même si le type d’appareil ne correspond pas exactement.

## Fichiers touchés

- `app/Controllers/Api/AtakPingController.php`
- `routes/web.php` (ping isolé + parenthèse manquante sur `/atak/sse/collecte`)
- `app/Core/Application.php`
- `app/Middleware/TenantTypeModuleAccessMiddleware.php`
- `app/Controllers/Api/AtakApiController.php`
- `app/Repositories/ReconImageRepository.php`
- `public/assets/js/atak-cams.js`
- `public/assets/css/atak.css`
- `views/atak.php`

## Vérification

1. Recharger ATAK (Ctrl+F5) : tuile hors ligne avec le texte « Aucun aperçu photo » si aucun cliché.
2. Couper volontairement une API : bandeau rouge, les tuiles déjà vues ne disparaissent pas.
3. `GET /api/atak/ping` reste `ok` même si le reste de la carte est en incident base.
4. Après « Demander une nouvelle vue » et un cliché casque réussi : la photo doit remplir la tuile.

## Statut

`corrigé à déployer`
