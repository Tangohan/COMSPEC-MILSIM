# Arrêt brutal sans ouvrir Athena — ressort IceMan

## Contexte

Session 17/09 21:29, Athena 1.0.147 chargé. Le téléphone IceMan du pack FN
est présent (mini-écran / objet détecté). Athena n’a pas ouvert le grand
écran (`Map display` jamais vu). Fermeture à 21:33:42.

## Symptôme

Le jeu se ferme tout seul, même sans ouvrir le menu Athena, parfois sans
sortir le grand écran. Journal :

`Error: can't resize AutoArray to negative size!`
puis ACCESS_VIOLATION à `7C2D2B58`.

Journal COMSPEC : `IceMan Anim_CustomOffset locked`.

## Cause

Le téléphone IceMan (cTab / BCE) anime le cadre avec un ressort. Quand le
menu se referme, la largeur visée est zéro. Le ressort dépasse sous zéro.
Le moteur refuse une taille négative et s’arrête.

Athena ne peut plus remplacer ce calage : IceMan l’a verrouillé. D’où le
crash « même sans notre overlay ».

Le pack du 14/09 n’avait pas ce verrouillage, Athena pouvait encore
empêcher la largeur nulle.

## Correctif

Dans le pack FN, le ressort IceMan refuse désormais toute largeur ou
hauteur trop petite. Le mini-écran et le grand écran restent calés
sans passer sous zéro.

## Fichiers touchés

- Pack FN : `BCE_UI_Anim.pbo` (calage IceMan)
- Copies de travail : `mod/UptoDate/Sources/iceman-bce-clamp/`

## Vérification

1. Quitter Arma complètement, relancer le pack FN.
2. Rester en jeu une à deux minutes sans ouvrir Athena.
3. Ouvrir et refermer le téléphone IceMan : le jeu reste ouvert.

## Statut

Corrigé côté pack FN (à valider in-game après relance).
