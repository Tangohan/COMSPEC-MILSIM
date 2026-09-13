# Textes Athena illisibles + tuile Tutoriel / WIKI

## Contexte

13 septembre 2026. Écran Connexion Athena : aides sous « Compte non connecté » et « Ou compte Athena » quasi invisibles. Les opérateurs confondent liaison OK et compte connecté (pseudo vs prénom/nom).

## Symptôme

- Ligne d’aide après « Compte non connecté » trop sombre.
- Texte sous « Ou compte Athena » coupé / illisible (hauteur trop faible + contraste faible).
- Pas d’explication claire liaison / connecté / sync dans le téléphone.

## Cause

- Couleurs d’aide `#8aa0b4` / `#A8B8C4` sur fond sombre.
- `AccountHint` (9804) redimensionné à ~7,5 % de la hauteur du formulaire → 2ᵉ ligne masquée.
- Pas d’app dédiée au dépannage identité.

## Correctif

- Textes d’aide en `#E8F2FA`, fonds contrastés, hauteurs AuthHint / AccountHint augmentées.
- App **Tutoriel / WIKI** (tuile bureau + tiroir) : liaison, connecté, sync, dépannage pseudo.

## Fichiers touchés

- `ui/athena_page.hpp`, `fn_athena_updatePanel.sqf`, `fn_athena_applyHomeLayout.sqf`
- `ui/wiki_page.hpp`, `fn_athena_updateWiki.sqf`, `fn_athena_wikiOnOpened.sqf`, `fn_athena_openWiki.sqf`
- `config.cpp`, `fn_athena_hideForeignPages.sqf`, `fn_athena_installDesktopShortcut.sqf`, `fn_athena_openFeature.sqf`
- `DevDispatchCatalog.php` (UPDATE #526), CHANGELOG

## Vérification

1. Pack Athena 1.0.104, quitter Arma.
2. Connexion Athena : aides lisibles sous les deux blocs.
3. Bureau → Tutoriel / WIKI : textes complets + bouton Connexion Athena.

## Statut

corrigé — Athena 1.0.104
