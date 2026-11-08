# Nommage MIL-STD sans effet visible

## Contexte
Demande joueur : « le traçage de nom MIL STD ne marche pas ».

## Symptôme
Thème MIL-STD choisi, mais traits nommés « Trait 1 », routes « Route 1 », noms de traits absents de la carte à zoom normal.

## Cause
Le thème ne s'appliquait qu'au bouton TOUT NOMMER et aux axes. Les noms de traits n'étaient dessinés que très près (échelle inférieure à 0,02). Le thème par défaut n'était pas MIL-STD. Attendu : abréviations inspirées d'APP-6 / MIL-STD-2525, sans prétendre à une conformité complète (MSR, ASR, ROUTE, PL, LD, LOA, AA, OBJ, BP, CP, RP, SP, WP).

## Correctif
Thème MIL-STD par défaut, un ancien choix reste gardé. Trait ouvert nommé PL + code, contour AA + code, zone unique selon son type. Route à pied ou en véhicule : ROUTE + code, désignation MSR / ASR / ROUTE dans sa fiche. Étape BP ajoutée. Lignes de contrôle nommées à chaque bout dès le zoom moyen.

## Fichiers touchés
`fn_zoneAction.sqf`, `fn_zoneGen.sqf`, `fn_zoneDraw.sqf`, `fn_routeAction.sqf`, `fn_pageRoute.sqf`, `fn_mapCtxMenu.sqf`.

## Vérification
`tests/Unit/AtakZonesRoutesTraceTest.php`, `hemtt check`. Rien ne peut être testé en jeu ici.

## Statut
corrigé à vérifier en jeu
