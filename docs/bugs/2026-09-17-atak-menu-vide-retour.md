# Menu d’applications vide, puis arrêt brutal

## Contexte

Athena 1.0.131 dans FN, 17/09 22:55. Téléphone ouvert sur la carte.
Journal `Arma3_x64_2026-09-17_22-55-26.rpt`.

## Symptôme

Le chevron ouvre un panneau gris à droite, sans icônes d’applications.
Le jeu se ferme ensuite (~48 s après l’ouverture), même ligne :

`Error: can't resize AutoArray to negative size!`
puis ACCESS_VIOLATION à `7C2D2B58`.

## Cause

Le calage Athena ne reprenait pas le fondu IceMan. À l’ouverture, les
icônes passaient en transparent (fondu 1) alors que le fond du tiroir
restait visible. Le calage était aussi réinjecté plusieurs fois après
le démarrage, ce qui déplaçait le fond sans les applications.

Les anciens raccourcis du bureau recréaient encore une vingtaine de
boutons à chaque ouverture du téléphone.

## Correctif

Athena 1.0.132 :

- même calage qu’IceMan (largeur, fondu, hauteur laissée au téléphone) ;
- la carte reste dans le cadre ;
- plus de réinjection du calage à chaque synchro ;
- plus de raccourcis recréés sur le bureau.

## Fichiers touchés

- `fn_ATAK_Check_Layout.sqf`
- `XEH_postInitClient.sqf`
- `fn_athena_installDesktopShortcut.sqf`

## Vérification

Quitter Arma complètement, recharger Athena 1.0.132. Chevron : les
applications apparaissent. Retour referme le menu. Laisser ouvert une
à deux minutes : le jeu reste ouvert.

## Statut

Corrigé (Athena 1.0.132), à valider in-game après relance.
