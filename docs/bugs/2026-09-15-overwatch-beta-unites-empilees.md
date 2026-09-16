# Overwatch Beta — indicatifs empilés au même point

## Contexte
Poste Overwatch Beta. Plusieurs contacts (ou un contact et un repère du théâtre au même nom) se trouvaient au même lieu.

## Symptôme
Les indicatifs se superposaient (ex. TA1 illisible). Impossible de cliquer le bon contact.

## Cause
Chaque pastille était collée aux coordonnées réelles, sans écart visuel. Un repère du théâtre portant le même indicatif s’affichait en plus.

## Correctif
Écarter les pastilles autour du point réel. Indicatif en une ligne. Masquer le repère du théâtre s’il reprend un contact déjà affiché tout près. La fiche liste les autres contacts au même point.

## Fichiers touchés
- `public/assets/js/atak-overwatch-beta.js`
- `public/assets/css/atak-overwatch-beta.css`
- `views/atak-overwatch-beta.php`

## Vérification
Deux contacts au même lieu : chaque indicatif reste lisible. Clic : la fiche montre « Au même point ». Zoom : l’écart reste lisible.

## Statut
corrigé
