# ATAK — P2P écran vide (Retour / Send Data)

## Contexte

L’application P2P — Réseau local du téléphone ATAK n’affichait plus le chat entre opérateurs.

## Symptôme

Écran gris, boutons Retour et Send Data uniquement. Pas de liste de correspondants, pas de messages.

## Cause

P2P ouvrait une page Message BCE vidée, et un masquage d’écrans la forçait au premier plan à la place du chat IceMan.

## Correctif

P2P reprend l’écran Message IceMan. La coquille vide n’est plus affichée.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp` (1.0.148)
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_hideForeignPages.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_messageHubOpenP2P.sqf`

## Vérification

Quitter Arma complètement. Recharger Athena 1.0.148. Ouvrir P2P — Réseau local : le chat téléphone à téléphone doit réapparaître, comme Message.

## Statut

Corrigé
