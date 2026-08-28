# Bug — Commandes Relief / vue de la carte sans effet

## Contexte

Carte ATAK, Affichage. L’opérateur coche ombrage, courbes, altitudes, pentes, passe en vue Inclinée, met l’amplification Z et l’inclinaison au maximum, et coche « Bâtiments et forêts du jeu ». Inventaire : ombrage et relevé divers « Pas encore sur le poste », mais 299 bâtiments et 399 forêts déjà reçus.

## Symptôme

La carte reste un fond plat incliné (Altis). Cocher, décocher ou glisser les curseurs ne change rien. On peut croire que le panneau est débranché.

L’ombrage et les courbes absents du poste sont un vrai manque de donnée, pas un bug. En revanche l’inclinaison, l’amplification du sol déjà relevé, et les volumes déjà comptés doivent se voir.

## Cause

Les canevas du sol et des volumes étaient posés comme **frères** du plan Leaflet (z-index 400). Les tuiles opaques les recouvrent entièrement.

Un correctif précédent ne rendait les tuiles transparentes que lorsque le maillage d’altitudes était prêt. Sans relevé d’altitudes, ce drapeau ne se lève jamais : les 299 bâtiments restent dessinés **sous** la carte. Les curseurs mettent à jour l’état, l’œil ne voit rien.

## Correctif

Monter les canevas dans des calques Leaflet dédiés, au-dessus des tuiles et sous les pastilles. Recaler le canevas sur la vue à chaque dessin. Appliquer l’inclinaison aussi sur la carte elle-même. Agrandir un peu les volumes en vue d’ensemble.

Sans altitudes, l’amplification Z ne peut pas soulever le sol — c’est honnête. Les volumes déjà reçus et l’inclinaison s’affichent quand même.

## Fichiers touchés

- `public/assets/js/atak-terrain-3d.js`
- `public/assets/js/atak-scene-3d.js`
- `public/assets/css/atak.css`
- `tests/Unit/AtakTerrain3dAssetTest.php`
- `tests/Unit/AtakScene3dAssetTest.php`

## Vérification

- Tests unitaires des assets (calques, inclinaison, volumes).
- Sur la carte : Affichage → Vue inclinée. Glisser l’inclinaison (25° ↔ 65°) doit basculer la perspective. Cocher « Bâtiments et forêts du jeu » doit faire apparaître les volumes dès qu’ils sont comptés, même si l’ombrage n’est pas encore sur le poste. Amplifier Z ne soulève le sol que si un relevé d’altitudes est présent.

## Statut

corrigé
