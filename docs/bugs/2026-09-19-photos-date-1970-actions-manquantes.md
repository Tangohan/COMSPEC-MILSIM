# Photos Overwatch — année 1970 et actions manquantes

## Contexte

Les photos transmises depuis le dépannage liaison (et plus généralement depuis la tablette) arrivaient bien dans Overwatch Beta, panneau Renseignement.

## Symptôme

- L’heure affichée sous la photo commençait par 1970 (ex. 1970-01-04 10:51:46).
- Seul le bouton Envoyer était proposé. Pas d’agrandissement, de flou, de classement SSE ni de suppression.

## Cause

Le jeu envoyait le temps écoulé depuis le briefing (quelques secondes ou minutes) à la place d’une horloge réelle. Le poste l’enregistrait comme une date, d’où 1970.

Le panneau Renseignement d’Overwatch Beta n’exposait pas les actions déjà présentes dans l’onglet Photos du poste classique.

## Correctif

- Le jeu envoie l’heure réelle. Le poste ignore une valeur trop ancienne et reprend l’heure de réception.
- Les photos déjà reçues avec 1970 affichent l’heure de réception.
- Overwatch Beta : Agrandir (clic sur la miniature), Flouter, Passer en SSE, Supprimer.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_captureReconImage.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp` (1.5.91)
- `app/Support/ReconCapturedAt.php`
- `app/Repositories/ReconImageRepository.php`
- `app/Controllers/Api/AtakApiController.php`
- `app/Services/Media/ReconPhotoHudService.php`
- `bootstrap/atak_recon_images_actions_migration.php`
- `public/assets/js/atak-overwatch-beta.js`
- `public/assets/css/atak-overwatch-beta.css`
- `public/assets/js/atak-cams.js`

## Vérification

Recharger Overwatch Beta (Ctrl+F5). Ouvrir Renseignement : dates actuelles, boutons Agrandir / Flouter / Passer en SSE / Supprimer. Clic sur la miniature → photo en grand. Relancer Arma avec Overwatch 1.5.91 pour une nouvelle photo en jeu.

## Statut

Corrigé
