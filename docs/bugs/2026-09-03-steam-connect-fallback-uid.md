# Liaison — Steam perdu sur le Connect de secours

## Contexte

Lancement mission Overwatch. Liaison Athena par identifiant Steam. Positions vers le poste.

## Symptôme

Après une liaison, chaque position est refusée comme anonyme. Le journal Liaison se remplit d’accès refusés « identifiant Steam manquant ».

## Cause

Le Connect de secours (hors chemin synchrone) mémorisait l’adresse et la clé, mais pas l’identifiant Steam. Les positions joueur partaient sans UID. Le journal enregistrait un refus à chaque envoi.

## Correctif

Le Connect de secours reprend Steam, version du pack et groupe sanguin comme le chemin synchrone. Une position joueur sans identifiant n’est plus envoyée (les relais téléphone / IA restent autorisés). Le journal Liaison n’écrit qu’une entrée « identifiant manquant » par fenêtre de cinq minutes.

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `app/Support/AtakArmaWriteGuard.php`
- `tests/Unit/OverwatchOperatorGameProfileAssetTest.php`
- `tests/Unit/GameAuthAssetTest.php`

## Vérification

Relancer Arma complètement. Liaison Steam : positions au poste. Journal Liaison sans rafale de refus.

## Statut

corrigé (liaison 1.18.9)
