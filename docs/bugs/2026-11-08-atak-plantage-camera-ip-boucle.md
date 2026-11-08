# ATAK : le jeu plante à chaque prise de téléphone après un plantage sur une caméra IP

## Contexte

Retour de The Exile (Steam, 3 nov.). Un joueur pose une caméra IP, appuie sur « link », le client plante. Ensuite, il plante à chaque téléphone pris à l'arsenal, ce qui l'empêche de revenir. Le créateur a répondu de vider le cache dans Documents. Le changelog Steam annonce pour la 3.0.4 : « plus de plantage du jeu après "Lier" une caméra IP ».

## Symptôme

Plantage dur du client dès qu'un téléphone est dans l'inventaire. Le personnage reste sur le serveur, immortel, à courir sur place. Log serveur : `Slow network ... DisconnectTimeout too high (19/60000 ms)` : le client ne répond plus, le serveur l'attend encore.

## Cause

**Prouvé par le code**

1. **Le correctif annoncé n'est pas livré depuis `main`.** `fn_deviceGuard` et ses appels n'existent que non validés dans `.worktrees/atak-hotfix-camlink` (fichier du 3 nov.). Aucun commit, pas de note `docs/bugs/2026-11-03-crash-lien-camera-ip.md` (citée par ce fichier), aucune trace dans le `main.pbo` de `main` (`grep -c deviceGuard` : 0). `fab4f1c25` dit seulement que « la 3.0.4 est prise par le correctif caméra IP ».
2. **Ce correctif ne couvrait pas la boucle.** Son marqueur partait par `profileSave "dirty"` (écriture jusqu'à 2 min plus tard) : il ne survit pas au plantage qu'il doit détecter. Pas de vérification des données reprises, pas de bouton dans Réglages, rien pour l'admin.
3. **Une page caméra est rejouée à chaque téléphone.** `fn_displayUnload` garde la dernière page dans le profil, `fn_displayLoad` la rouvre. Si c'est `LIVECAM`, `fn_pageLivecam` choisit seul une source et crée une caméra avec sa texture r2t, sans action du joueur, y compris en HUD et arsenal ouvert. C'est un état persistant, à la fois sur le disque et indépendant de la mission : il explique la forme de la boucle.
4. **Aucun bouton « Lier » sur une caméra IP.** Les actions d'une caméra sont PARTAGER, RENOMMER, orienter, récupérer. Les seuls boutons traduits « LINK » sont RELIER (app Liaison ATAK, équipier à terre) et LIEN (Musique). La séquence exacte du joueur reste à faire préciser.
5. **Reprise non vérifiée** : `fn_offline` rechargeait `COMSPEC_ATAK_OfflineSave` sans contrôle de forme, les noms de caméra (variable publique) allaient bruts dans des textes structurés, `FeedPfh` s'empilait à chaque redessin.

**Écarté par lecture** : la DLL (`Extension*.cs`, `LocalDocuments`) ne laisse pas passer d'exception gérée vers le moteur : tampon `Output` borné, rappel borné à 20 000 octets, lectures de cache sous `try`. Rien n'y explique un plantage natif. Une exception mémoire sur un fichier énorme restait possible : fichier hors taille désormais mis de côté.

**Supposé, non prouvé**

- Que la page caméra rejouée soit bien ce qui plante le client de ce joueur. Aucun `.mdmp` ni RPT client, aucune reproduction possible ici. Le moteur sait planter sur caméra et r2t pendant la fermeture de l'arsenal (même famille que `2026-09-18-atak-crash-prise-equipement.md`), mais c'est une analogie.
- L'hypothèse du joueur (« la liaison à Athena relance le problème ») : aucune donnée renvoyée par Athena ne crée de caméra. Elle n'est pas retenue, sans pouvoir être exclue.
- Que le joueur ait une version contenant le correctif du 3 nov. : les versions publiées sur Steam ne sont pas vérifiables depuis le dépôt.

## Correctif

- **`core/fn_deviceGuard`** : la page caméra n'est plus jamais rouverte seule. Avant une étape à risque (première sortie du téléphone, démarrage d'un flux), marqueur écrit sur le disque tout de suite, effacé après 20 s ou en fin de mission. Retrouvé au lancement suivant : mode sans échec des caméras, notification qui propose de nettoyer. Vérifie le profil au démarrage, sources vidéo vérifiées avant toute caméra. Entrée illisible ignorée, ligne `[WARN]`.
- **Couvre l'état déjà corrompu** sans action : la page `LIVECAM` gardée et une sauvegarde illisible sont écartées dès la première mission avec cette version.
- **Réparer mes données** : Réglages › À propos, ACE, Vidéo live, réglage CBA. Admin : app Admin et fiche ATAK du site ; l'avis est relevé même sans téléphone sur le joueur. DLL : `RepairLocalData` met de côté les caches dans `Sync\_repair`. Le mode compromis et l'identifiant ne sont jamais effacés.
- **Personnage immortel** : le mod ne peut pas s'en occuper proprement. Le moteur garde l'unité tant que le délai du serveur court ; rien en SQF ne distingue un client planté d'un client ralenti, et supprimer l'unité d'un joueur encore connecté ferait pire. Réglage côté serveur (`server.cfg`, d'après la documentation Bohemia, non testé ici) : `disconnectTimeout` plus bas et `kickClientsOnSlowNetwork[] = {0, 0, 0, 1};`.

## Fichiers touchés

`core/fn_deviceGuard.sqf` (nouveau), `XEH_postInitClient.sqf`, `XEH_preInit.sqf`, `config.cpp`, `ui/fn_displayLoad.sqf`, `camera/fn_camView.sqf`, `camera/fn_camAction.sqf`, `camera/fn_camShare.sqf`, `pages/fn_livecamStart.sqf`, `pages/fn_livecamUav.sqf`, `pages/fn_pageLivecam.sqf`, `pages/fn_pageSettings.sqf`, `shell/fn_aceMenu.sqf`, `shell/fn_adminNotices.sqf`, `network/fn_offline.sqf`, `i18n/en.txt`, connect `fn_uplinkHold.sqf`, `Extension.cs`, `AdminAtakFleetController.php`, `AtakAdminControlRepository.php`, `AtakGameAdminService.php`, `routes/web.php`, `views/admin/atak-fleet/show.php`, `lang/en/site_auto.php`, autotest `telephone.sqf`.

## Vérification

`hemtt check` (2 erreurs acceptées), `dotnet build` des deux DLL, PHPUnit `AtakCompromisePolicyTest`, PHPStan, CI de la PR. Rien n'a pu être testé en jeu. À tester : ouvrir une caméra, couper le jeu dans les 20 s (fin de tâche), relancer : mode sans échec et notification ; Réparer mes données ; demande depuis le site, joueur sans téléphone. Coût à surveiller : une écriture du profil à la première sortie du téléphone et au démarrage d'un flux.

## Statut

corrigé à vérifier en jeu ; cause du plantage natif non prouvée.
