# Arrêt du jeu à l’affichage des ordres (dépannage)

**Date :** 2026-09-19  
**Statut :** corrigé (Overwatch 1.5.89 / Athena 1.0.140)

## Contexte

Journal `COMSPEC_2026-09-19_111758_173.log`. Pack encore en 1.5.87 / Athena 1.0.138 (Arma n’avait pas été quitté après 1.5.88). Dépannage liaison lancé à 11:20 avec le téléphone.

## Symptôme

Le bandeau était encore sur « Formes du poste » sur la capture, mais le journal va plus loin. Le jeu se ferme à 11:27:35, **7 secondes après** « Ordres (affichage) ». Même arrêt que d’habitude : taille de tableau négative, puis ACCESS_VIOLATION `7C2D2B58`.

Pendant « Ordres (réception) », le bandeau montrait déjà :

- 3 ordres, dont 2 « nouveaux » à chaque cycle ;
- mémoire qui grimpe : 11 → 14 → 17 → 20 → 23 → 26 → 29 ;
- affichage reporté.

## Cause

La réception rangeait les ordres en mémoire **sans les marquer comme déjà vus**. À l’affichage, le téléphone les livrait tous d’un coup (alerte, fil, ouverture TASK). IceMan recevait une rafale et le moteur fermait le jeu.

Les 3 ordres d’avant la partie avaient bien été ignorés au chargement. Ils revenaient quand même comme « nouveaux » à chaque lecture, donc la mémoire s’allongeait.

## Correctif

- Un identifiant déjà reçu est marqué vu, même si l’affichage est encore reporté.
- Un seul nouvel ordre est présenté à la fois.
- Pendant le dépannage, la file déjà en mémoire n’est plus rejouée à l’affichage.
- Le miroir téléphone (alerte / fil) ne part plus en rafale.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollOrders.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_startSyncLoops.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_onOrderReceived.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_syncOrdersToGroupChat.sqf`

## Vérification

Quitter Arma complètement. Recharger Overwatch 1.5.89 et Athena 1.0.140. Le bandeau doit afficher ces versions (plus 1.5.87 / 1.0.138). Relancer le dépannage avec le téléphone. L’étape « Ordres (affichage) » ne doit plus fermer le jeu. La mémoire qui grimpe de 3 en 3 est traitée à part (Overwatch 1.5.90).

## Statut

Corrigé.
