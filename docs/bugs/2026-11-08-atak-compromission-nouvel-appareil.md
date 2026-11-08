# ATAK : joueur exclu du réseau après une compromission, et app Admin qui dérègle le téléphone

## Contexte

Retour de The Exile (Steam, 7 nov.). Un joueur meurt, son chef de groupe valide la compromission pendant le respawn. Avec un nouveau téléphone, il reste hors du réseau. L'app Admin « fait bugger » le téléphone de l'admin ; il finit par nettoyer les appareils sur le site.

## Symptôme

Téléphone neuf en mode « LIAISON DE DONNÉES COUPÉE », sans issue depuis le jeu. App Admin instable pour celui qui s'en sert.

## Cause

**Prouvé par le code (identifiant)**

1. `fn_getTerminalUid` calcule l'identifiant depuis le Steam et le profil, sans notion d'appareil : le téléphone neuf garde le terminal compromis. Le mode compromis est gardé dans le profil par terminal (`fn_compromiseView`) et seule la levée le retire. Promettre un nouvel IMEI ne changeait rien.
2. Le nouvel IMEI se perdait aussi : `COMSPEC_ATAK_PhoneGen` est une variable de l'unité, absente après le respawn.
3. Un joueur déconnecté au moment de la validation ne recevait jamais l'ordre.

**Prouvé par le code (sécurité, plus grave que le symptôme)**

4. Chaque position du mod remonte `compromise_state = none`. `AtakApiController` effaçait donc la compromission validée à la remontée suivante. D'où aussi le bouton LEVER grisé dans l'app Admin : le site ne voyait déjà plus de compromission.
5. `fn_syncAtakRealism` redemande un certificat quand le sien est révoqué, et le site le délivrait. L'appareil compromis retrouvait donc un certificat valide. `ClearCompromise` était ouvert à tout client de jeu.
6. `listOperatorPhysicalTwins` fusionnait les fiches d'un même opérateur : un nouvel identifiant aurait repris la fiche compromise.

**App Admin : mécanisme prouvé, lien avec ce retour supposé**

7. Tempête de redessin : l'app demande un relevé toutes les 10 s et chaque joueur qui répond redessine toute la page, y compris l'onglet ATHENA qui n'en dépend pas (40 fiches recréées, recherche en cours perdue). Même famille d'arrêt moteur que `2026-11-08-atak-pages-redessinees-file-cba.md`.
8. Mode compromis sur le mauvais téléphone : un avis sans terminal était appliqué au téléphone qui le reçoit. L'ancienne formule additionnait les caractères du Steam et du profil : deux joueurs d'une même communauté peuvent avoir le même identifiant (fréquence réelle inconnue), donc partager une fiche. L'admin pouvait aussi déclarer compromis son propre ATAK depuis son téléphone.

Le retour ne dit pas quel bouton a été pressé : lequel de ces mécanismes a joué chez lui reste **supposé**. « Outstanding devices » n'est pas défini non plus : sans doute les fiches du parc.

## Correctif

- **Nouvel appareil** : à la validation par le chef, `fn_compromise` « ownerReset » prend une nouvelle génération d'identifiant et garde le nouvel IMEI dans le profil. Le téléphone suivant s'enregistre seul. Rattrapage 30 s après l'arrivée si la validation a eu lieu pendant une absence. Le mode compromis reste attaché à l'ancien identifiant.
- **Message au joueur** : notification à chaque étape, ligne « Mon appareil » dans l'app Alertes.
- **Stricte séparation** (`AtakCompromisePolicy`) : depuis le jeu, plus de levée, de réenregistrement ni de certificat pour une fiche compromise (409 `terminal_compromised`). Reste levable depuis le jeu : un effet Zeus ou une remontée du terrain. La fiche compromise n'est ni fusionnée, ni retirée, ni reprise.
- **Collision** : 409 `terminal_uid_taken`, le mod prend un autre identifiant. Formule à deux empreintes pour tout nouvel identifiant.
- **App Admin** : ni relevé ni redessin sur ATHENA et APPS, un redessin par seconde au plus ailleurs, une action à la fois, données vérifiées, `&` échappé. REMPLACER L'APPAREIL (aussi sur la fiche du site) règle le cas d'un appareil en attente. Déclarer son propre ATAK depuis le jeu est refusé.

## Fichiers touchés

`shell/fn_compromise.sqf`, `shell/fn_compromiseView.sqf`, `shell/fn_adminNotices.sqf`, `shell/fn_captureServer.sqf`, `ui/fn_statusUpdate.sqf`, `pages/fn_pageAlerts.sqf`, `admin/fn_pageAdmin.sqf`, `admin/fn_adminClient.sqf`, connect `fn_getTerminalUid.sqf`, `fn_registerAtakTerminal.sqf`, `fn_realismErrorMessage.sqf`, `Extension.cs`, `AtakCompromisePolicy.php` (nouveau), `AtakRealismApiController.php`, `AtakApiController.php`, `AtakRealismRepository.php`, `AtakAdminControlRepository.php`, `AtakGameAdminService.php`, `AtakCompromiseNoticeMeta.php`, `AdminAtakFleetController.php`, `routes/web.php`, `views/admin/atak-fleet/show.php`, autotest `telephone.sqf`.

## Vérification

PHPUnit `AtakCompromisePolicyTest`, `hemtt check`, `dotnet build`, PHPStan, CI de la PR. Rien n'a pu être testé en jeu. À tester : capture par l'IA, validation par le chef, respawn, nouveau téléphone (retour sur le réseau, nouvelle fiche, ancienne toujours COMPROMIS, certificat refusé à l'ancien identifiant) ; REMPLACER L'APPAREIL ; app Admin sur l'onglet ATHENA à plusieurs joueurs.

Limite : un joueur déjà bloqué sur une version plus ancienne garde son identifiant jusqu'à la mise à jour ; il passe par REMPLACER après la mise à jour, ou par la fiche du site d'ici là. Comme le mod tourne chez le joueur, le changement d'identifiant reste une règle de jeu, pas une preuve cryptographique.

## Statut

corrigé à vérifier en jeu.
