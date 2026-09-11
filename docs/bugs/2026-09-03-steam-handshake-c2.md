# Handshake Steam bloqué par le ping C2

## Contexte

Lancement mission Overwatch. Connexion automatique par identifiant Steam.

## Symptôme

Journal : « Connexion Steam » puis « Pas de session — les transmissions restent coupées ». `athenaReady=false`.

## Cause

Après un échange Steam réussi, l’extension exigeait encore le ping C2 de la clé API. Un échec renvoyait `C2_UNAVAILABLE` et la session n’atteignait jamais READY. Le SQF silencieux ne journalisait pas le code d’erreur.

## Correctif

Si les jetons Athena sont émis, READY est posé même si le ping C2 échoue (`C2_DEGRADED`). Le retour Steam est journalisé.

**Complément (2026-09-11)** : READY avec `C2_DEGRADED` ne doit plus démarrer les boucles de transmission. Voir `docs/bugs/2026-09-11-athena-ready-c2-401.md` — le SQF exige désormais `isC2Ok`, et l’auth HTTP accepte le Bearer jeu même si une ancienne clé header est présente.

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/GameAuth.cs`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_loginSteam.sqf`
- `tests/Unit/GameAuthAssetTest.php`

## Vérification

Steam déjà lié au compte : journal « Session Athena prête » **uniquement si le canal poste répond**. Sinon : compte lié, transmissions coupées (sans spam 401).

## Statut

partiellement corrigé — complété le 2026-09-11 (gate C2 + priorité Bearer)
