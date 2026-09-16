# Overwatch Beta — fiche contact illisible

## Contexte
Poste Overwatch Beta. Clic sur un contact en liaison (ex. TA1) : le tiroir BFT / Contact s’ouvre à droite.

## Symptôme
Un membre du même groupe apparaissait comme un rectangle clair Windows, indicatif coupé. L’adresse réseau s’affichait en entier. Intégrité montrait `none`, Radio `N/A`, Batterie « non transmis ». Centrer n’occupait pas la même largeur que les autres boutons.

## Cause
La ligne de groupe était un `<button class="ow-event">` sans reset visuel : Windows dessine un bouton clair. Les champs vides étaient toujours imprimés. Le masque d’adresse ne reconnaissait que quatre nombres isolés, pas une adresse suivie d’un port.

## Correctif
Lignes de groupe en boutons sombres pleine largeur. En-tête du tiroir en flex (fermeture à droite). Champs vides, `none`, `N/A` ou « non transmis » masqués ; intégrité traduite. Adresse : dernier groupe remplacé par un point, y compris avec un port. Actions empilées à la même largeur.

## Fichiers touchés
- `public/assets/css/atak-overwatch-beta.css`
- `public/assets/js/atak-overwatch-beta.js`
- `views/atak-overwatch-beta.php`

## Vérification
Clic sur un contact : fiche sombre, membre de groupe lisible, adresse masquée, pas de `none` / `N/A` / « non transmis ». Centrer, suivre et cadrer ont la même largeur. Recharger la page (Ctrl+F5).

## Statut
corrigé
