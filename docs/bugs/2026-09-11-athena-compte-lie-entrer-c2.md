# Compte Athena trouvé mais Entrer ne connecte pas (C2_DEGRADED)

## Contexte

11 septembre 2026. Suite au correctif auth 401 / READY trompeur. Pack 1.5.16 + liaison 1.19.1. L’écran Athena affiche le profil (Jake Gylenhall, TA1, Steam lié, « Environnement prêt », « C2 disponible ») ; le téléphone ATAK reste sur « Compte non connecté ».

## Symptôme

- Journal : `Compte lié — transmissions coupées (C2_DEGRADED)` puis `Handshake terminé ok=false`
- Bouton **Entrer** ne change rien (fermeture seule de la fenêtre)
- L’écran annonce à tort « C2 DISPONIBLE » alors que l’erreur C2 est posée

## Cause

1. Restauration de session **sans** envoyer l’identité Steam du joueur : `client-init` peut échouer → `C2_DEGRADED`.
2. Correctifs récents ont ajouté un verrou `isC2Ok` : READY + C2_DEGRADED = pas de sync, alors que le pack Workshop **06-09-2026** (fonctionnel) traitait READY comme liaison OK.
3. **Entrer** ne retentait pas l’ouverture du canal.

## Correctif (suite 14h)

Cause réelle des 401 après « Session prête » : dès que `client-init` réussissait, la DLL renvoyait aussi une ancienne clé communauté (`X-COMSPEC-KEY`) avec le jeton jeu. Le portail refusait.

- Avec jeton jeu : **uniquement** Authorization Bearer (plus de clé header).
- `Connect` async ne réinjecte plus de clé CBA si session jeu active.
- Version liaison **2.0.18**, pack **1.5.19** — déployer aussi dans `@# S.O.A.R - FN`.

## Fichiers touchés

- `app/Support/ComspecApiKeyAuth.php`
- `app/Controllers/Api/AtakApiController.php`
- `app/Services/Game/GameAuthService.php`
- `mod/UptoDate/COMSPECExtension/GameAuth.cs`
- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_restoreSession.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_enterAthena.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_pollAuth.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/display_athena_auth.hpp`

## Vérification

- Pied de fenêtre : liaison **1.19.2**, pack **1.5.18**
- Handshake : `Session Athena prête` / `ok=true` dès que l’état est READY (comme Workshop 06-09)
- **Entrer** rouvre le canal puis ferme ; sync démarre
- Déployer aussi le PHP portail (Steam depuis session jeu)

## Statut

corrigé — gates SQF rétablies sur le modèle Workshop 06-09-2026 ; Steam + Entrer conservés
