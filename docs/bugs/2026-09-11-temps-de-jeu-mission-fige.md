# Temps de jeu en mission figé (dernière remontée ancienne)

**Date :** 2026-09-11  
**Statut :** corrigé

## Contexte

Sur la fiche opérateur, le cartouche « Temps de jeu en mission » affichait encore une dernière remontée au **06/09/2026** alors que des sessions avaient lieu ensuite avec le mod lié au portail.

## Symptôme

- Cumul figé (ex. « 5 h 40 min ») avec date de dernière remontée ancienne.
- Positions / chat pouvaient repartir après correctifs de liaison, sans nouvelle progression du temps de mission.

## Cause

1. Pendant la panne d’auth (401), les envois de temps de mission échouaient sans relancer la session : la route playtime n’était pas traitée comme sensible à l’auth côté liaison.
2. Le tracker Overwatch envoyait dès qu’une URL de portail était connue, y compris sans liaison prête → refus silencieux.
3. En solo hors éditeur, le contexte était vide et l’UID `_SP_PLAYER_` était abandonné avant envoi.
4. Côté portail, l’enregistrement ne regardait que le Steam du corps de requête, pas le compte déjà identifié par la session jeu.

## Correctif

- Portail : rattacher le cumul au compte de la session jeu ; Steam de session en repli ; Steam non obligatoire si le compte est déjà connu.
- Liaison : playtime dans les routes qui relancent l’auth après 401 ; pas d’envoi sans jeton / clé.
- Pack : cumul seulement si liaison Athena prête ; solo hors éditeur compté en « serveur » ; `_SP_PLAYER_` laissé à la liaison (Steam de session).

## Fichiers touchés

- `app/Controllers/Api/AtakApiController.php` (`playtime`)
- `app/Support/AtakArmaWriteGuard.php`
- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_playtimeTracker.sqf`
- `mod/Overwatch 2026/ProdVersion/GPT/@COMSPEC_ATAK/addons/comspec_atak_core/functions/fn_playtimeTracker.sqf`

## Vérification

- Tests unitaires assets playtime / catalogue UPDATE #486.
- Manuelle : pack 1.5.24 + liaison 2.0.20, session liée ≥ 5 min → date de dernière remontée avance sur la fiche.

## Versions

- Overwatch Connect **1.5.24**
- Liaison **2.0.20**
