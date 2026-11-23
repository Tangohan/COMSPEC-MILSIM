# Bannissement : accès jeu, sessions et parcours de contestation

## Contexte
Audit complet du bannissement du site, de la contestation et de la fenêtre d'information (demande de TTRD).

## Symptôme
- Une personne bannie (fiche « inactive ») passait les contrôles de statut de l'API ATAK (liaison par Steam, initialisation jeu, garde d'écriture) quand son Steam n'était pas bloqué.
- Son jeton de session jeu (deux heures) et sa session navigateur sur les API tactiques restaient valables après le bannissement ; son jeton de reprise aussi jusqu'à l'expiration.
- Un administrateur de communauté pouvait sanctionner l'identifiant d'une personne d'une autre communauté.
- Une session ouverte avant le bannissement affichait « Ce compte n'est plus actif » au lieu de l'avis et du moyen de contester.
- La fenêtre ne donnait ni portée, ni date de début, ni durée explicite ; aucun suivi de la contestation ; aucun accusé ni notification de décision ; dépôt possible pour une sanction terminée.

## Cause
Les listes de statuts refusés ignoraient « inactive ». Le bannissement ne révoquait pas les sessions du jeu. Les API tactiques ne passent pas par AuthMiddleware. L'identifiant cible n'était pas rattaché à la communauté.

## Correctif
AccountAccessStatus (statuts refusés par le jeu) ; révocation des sessions jeu au bannissement ; contrôle du statut dans ComspecApiKeyAuth (jeton jeu et session navigateur) ; contrôle d'appartenance à la communauté ; avis de suspension aussi pour une session ouverte ; fenêtre et page de contestation enrichies (portée, début, durée, effets, bouton, suivi reçu/décision) ; e-mails d'accusé et de décision (sans le texte de la réponse) ; limitation de débit du dépôt ; refus pour sanction terminée.

## Fichiers touchés
app/Support/AccountAccessStatus.php, ComspecApiKeyAuth.php, AtakArmaWriteGuard.php ; AtakApiController.php ; PlatformAccountBanService.php ; PlatformBanNotice.php ; PlatformBanAppealMailer.php ; PlatformBanAppealController.php ; SystemPlatformBansController.php ; AuthMiddleware.php ; AuthController.php ; ModerationOrganizationController.php ; vues, CSS, lang, tests/Unit/PlatformBanAuditFixesTest.php.

## Vérification
Mini exécuteur : PlatformBanAuditFixesTest (tests SQLite à lancer en CI, pilote absent en local). Capture de la fenêtre à 1280 px, clair et sombre.

## Statut
corrigé (non éprouvé sur la production)
