# Arrêt du jeu à la prise d’équipement ATAK

**Date :** 2026-09-18  
**Statut :** corrigé (Overwatch 1.5.87 / Athena 1.0.138) — à rejouer en mission

## Contexte

Journal `COMSPEC_2026-09-18_191918_348.log`. Pack 1.5.84 / Athena 1.0.137. Pas de téléphone au départ. L’opérateur prend son équipement (arsenal Avalon) vers 19:20:36. Le jeu se ferme à 19:20:53. Même exception que l’arrêt du 13:03 avec téléphone ouvert.

## Symptôme

Dès que l’opérateur récupère le téléphone ATAK dans l’arsenal, le jeu se ferme. Dernières lignes utiles :

- 19:20:42 acquittement d’un ordre (plusieurs secondes de gel)
- 19:20:50 deux nouveaux ordres livrés
- 19:20:52 93 repères du poste d’un coup
- 19:20:53 taille de tableau négative, puis ACCESS_VIOLATION `7C2D2B58`

## Cause

La première prise du téléphone déclenchait tout en même temps : lecture des ordres déjà en attente (traités comme « nouveaux »), alerte et fil téléphone, acquittement bloquant, et pose de tous les repères du poste — pendant la fermeture de l’arsenal.

La première interrogation des ordres revient souvent vide, puis les ordres d’une partie précédente arrivent au tour suivant. Ils étaient alors traités comme des ordres nouveaux de la partie en cours. Le poste renvoyait aussi tous les ordres encore en attente, y compris ceux émis avant cette partie.

## Correctif

- Quelques secondes de calme après la prise du téléphone, et tant que l’arsenal est ouvert.
- Les ordres déjà présents au poste au chargement sont ignorés pour toute la partie. Seuls les ordres émis ensuite sont livrés.
- Le poste ne transmet au téléphone que les ordres créés après le début de la partie.
- Fil téléphone seulement si le grand écran ATAK est vraiment ouvert.
- Acquittement reporté de quelques secondes.
- Première lecture des repères et des formes : comptage seulement, puis pose par petits paquets.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_uplinkQuiet.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_startSyncLoops.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollOrders.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollAiOrders.sqf`
- `app/Repositories/AtakOrderRepository.php`
- `app/Controllers/Api/AtakApiController.php`
- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_receiveOrder.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollAthenaMarkers.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollMapShapes.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/XEH_postInitClient.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_syncOrdersToGroupChat.sqf`

## Vérification

Quitter Arma complètement. Recharger Overwatch 1.5.87. Entrer en mission **sans** téléphone, ouvrir l’arsenal, prendre l’ATAK, fermer l’arsenal. Attendre une vingtaine de secondes, puis ouvrir le téléphone. Le jeu doit rester ouvert. Dans le journal : `Téléphone pris`, puis `hors partie` / `ordre(s) d’avant ignoré(s)`, **pas** deux ordres « nouveaux », **pas** de mémoire qui grimpe (3 → 6 → 9). Un ordre émis pendant cette partie doit ensuite apparaître.

Session Eden `COMSPEC_2026-09-18_193709_028.log` : encore **1.5.84** (pack FN verrouillé). Téléphone ouvert 19:38:27, ordres « nouveaux 2 » répétés (mémoire 3 → 6 → 9), 93 repères, menu d’applications IceMan ouvert (capture). Arrêt 19:39:38, même `7C2D2B58`. Eden ne change rien : même chemin client.
