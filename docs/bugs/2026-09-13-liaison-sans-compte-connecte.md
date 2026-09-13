# En liaison portail / compte non connecté en jeu

## Contexte

13 septembre 2026. Portail : TA1 « EN LIAISON ». Téléphone jeu : « Compte trouvé » + « Compte non connecté » + pseudo NewPI sur la barre OK. L’opérateur est bien vu du poste mais sans session compte en jeu.

## Symptôme

- Poste : contact en liaison.
- Jeu : messages contradictoires (trouvé / non connecté) + pseudo Arma au lieu du prénom/nom Athena.
- Steam non associé bloquait l’écran « connecté » même avec canal prêt.

## Cause

- `_allOk = AthenaReady && SteamLinked` : Steam traité comme obligatoire.
- AuthHint disait « Compte non connecté » dès que `auth_state ≠ READY`, même si `AthenaReady` (liaison appareil) était vrai.
- Pas de libellé dédié « en liaison sans fiche compte ».

## Correctif

- Compte connecté = Athena prêt + identité fiche (prénom/nom), Steam facultatif.
- Bandeau : « En liaison — compte à ouvrir » / « Connecté — canal ouvert ».
- AuthHint : explique que le poste peut voir l’indicatif sans fiche compte chargée.
- Tutoriel / WIKI aligné.

## Fichiers touchés

- `fn_athena_updatePanel.sqf`, `fn_athena_applyHomeLayout.sqf`
- `fn_athena_homeAction.sqf`, `fn_athena_authFocus.sqf`, `fn_athena_updateWiki.sqf`
- `config.cpp` (1.0.105), DevDispatchCatalog UPDATE #527

## Vérification

1. Pack 1.0.105, quitter Arma.
2. Cas liaison sans fiche : bandeau orange « En liaison — compte à ouvrir », aide lisible, Entrer visible.
3. Après Entrer / Appairer avec nom Athena : « Connecté — canal ouvert », plus de pseudo seul.
4. Sans Steam mais avec e-mail : fiche connectée quand même.

## Statut

corrigé — Athena 1.0.105
