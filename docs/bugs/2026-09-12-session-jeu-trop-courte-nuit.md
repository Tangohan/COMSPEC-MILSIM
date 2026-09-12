# Session jeu trop courte — PC allumé toute la nuit

## Contexte

Pack Overwatch ~1.5.45. Machine laissée avec Arma ouvert toute la nuit (Appairage / clé communauté). Au matin, le journal Liaison du portail affiche des rafales d’événements ACCÈS.

## Symptôme

Messages répétés (souvent plusieurs par seconde) :

« Session jeu ignorée — jeton invalide ou expiré (repli clé API) »

La liaison peut encore fonctionner via la clé communauté, mais le journal est inutilisable. Sensation de « session trop courte ».

## Cause

Deux TTL coexistent :

| Jeton | TTL | Rôle |
| --- | --- | --- |
| Access Athena (`GameAuthService`) | 2 h | Bearer jeu ; renouvelable 30 j via `refresh_token` |
| Session opaque ATAK (`AtakGameSession`) | 4 h | `X-COMSPEC-SESSION` après client-init |

Après Appairer, il n’y a souvent **pas** de `refresh_token` DPAPI : la clé communauté porte le trafic.

Le jeton opaque expire au bout de 4 h. L’extension continue de l’envoyer. Le portail l’ignore et bascule sur la clé API (**sans 401**), donc aucun re-client-init côté client. Chaque poll journalise l’échec → spam.

Second trou : si un Bearer accès expire sans refresh, `EnsureFreshGameAccessToken` le renvoyait quand même (token mort), au lieu de basculer sur la clé communauté.

## Correctif

1. Extension : mémoriser `expires_in`, renouveler silencieusement via client-init avant expiration ; ne plus coller un `session_token` mort dans les corps JSON.
2. Extension : Bearer périmé sans refresh → clear + repli clé communauté ; refresh révoqué avec clé encore valide → pas de `SESSION_EXPIRED` forcé.
3. Portail : journal « Session jeu ignorée » via `logThrottled` (1× / 10 min).

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/COMSPECExtension/GameAuth.cs`
- `app/Support/AtakArmaWriteGuard.php`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp` (1.5.46)
- `app/Support/DevDispatchCatalog.php` (UPDATE #510)
- `docs/bugs/2026-09-12-session-jeu-trop-courte-nuit.md`

## Vérification

1. Rebuild DLL + `connect.pbo` (pack 1.5.46), déployer PHP portail.
2. Appairer, laisser tourner > 4 h (ou TTL local `AtakGameSession::TTL_SECONDS` court) : pas de rafale ACCÈS ; position toujours au poste.
3. Connexion Athena avec refresh : après 2 h, refresh silencieux, canal OK.
4. Déconnecter volontairement : nouvel Appairage toujours requis.

## Statut

corrigé — Overwatch 1.5.46 (UPDATE #510)
