# Repères et comptes rendus qui reviennent après suppression

## Contexte

Sur Overwatch Beta, des libellés de théâtre (Headquarters, Bikini Blast, comptes rendus, etc.) restent collés à la carte. Clic droit → Supprimer : ils disparaissent, puis réapparaissent.

## Symptôme

Après suppression, le point s’en va un instant, puis revient (souvent au prochain rafraîchissement, quelques secondes).

## Cause

Deux cas se recoupaient :

1. **Compte rendu** : le poste enlevait seulement la pastille à l’écran, sans retirer le signalement. Au rechargement, il revenait.
2. **Repère du jeu** : le poste masquait le marqueur, mais un envoi suivant depuis Arma l’effaçait puis le recréait, ce qui annulait le masquage.

## Correctif

- Le retrait d’un compte rendu enlève le signalement et la pastille.
- Un repère retiré du poste reste masqué, même si le jeu le renvoie.
- En cas d’échec du retrait, le point reste visible (plus de disparition trompeuse).

## Fichiers touchés

- `app/Repositories/AtakDataRepository.php`
- `public/assets/js/atak-overwatch-beta.js`
- `public/assets/js/atak-overwatch-ops.js`

## Vérification

Rechargez Overwatch Beta (Ctrl+F5). Clic droit sur un repère ou un compte rendu, Supprimer : il ne doit plus revenir après quelques secondes.

## Statut

corrigé
