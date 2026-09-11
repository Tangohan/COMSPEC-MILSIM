# Code accepté sur le portail, refus unauthorized en jeu

## Contexte

11 septembre 2026. Code Appairer `Q7BHTP` généré sur Athena. Journal portail : « Liaison en jeu réussie — code accepté » (×2). En jeu : « Liaison refusée » / `unauthorized`, barre « Session Athena refusée — reconnectez-vous ».

## Symptôme

- Le portail consomme le code et journalise le succès.
- Le dialog « Lier le jeu » affiche un refus.
- Un nouvel essai avec le même code échoue (déjà utilisé).

## Cause

Après `RedeemGameLink` / `LinkBySteam`, la DLL validait la clé communauté via `client-init` **sans** vider le jeton jeu encore en mémoire. `AttachApiKeyHeader` préférait alors le Bearer jeu (souvent invalide après « Session refusée ») → HTTP 401 → `ERR|unauthorized`, alors que le redeem avait déjà réussi.

## Correctif

- `ClearGameBearerForCommunityLink()` avant `client-init` après redeem / Steam.
- Message SQF plus clair si refus après saisie d’un code.
- Liaison **2.0.19**, pack **1.5.22**.

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/COMSPECExtension/COMSPECExtension.csproj`
- `connect/functions/fn_accountLinkSubmit.sqf`
- `connect/functions/auth/fn_pollAuth.sqf`
- `connect/config.cpp`

## Vérification

1. Quitter Arma complètement.
2. Nouveau code Appairer.
3. Lier le jeu → succès (pas de rouge unauthorized).
4. Pied : liaison **2.0.19**, pack **1.5.22**.
5. Journal : une seule « code accepté » pour ce code.

## Statut

corrigé — rebuild + déploiement SOAR FN
