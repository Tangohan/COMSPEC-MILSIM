# Ordres peu visibles quand les alertes écran sont coupées

## Contexte
12 septembre 2026. Pack Overwatch / Athena. Réception d’un ordre C2 sur le téléphone ATAK.

## Symptôme
Avec « Alertes à l’écran : non », l’opérateur entend parfois un son mais ne voit presque jamais le toast « Nouvel ordre ». Le double chemin receiveOrder + onOrderReceived pouvait aussi annoncer deux fois.

## Cause
`fn_addScreenToast` sortait dès que `shouldShowScreenNotification` était faux. Le toast d’ordre dépendait donc du même interrupteur que les bandeaux BIS. En parallèle, `onOrderReceived` rappelait showNotification / toast / son déjà couverts par `receiveOrder`.

## Correctif
- Toast forçable (`_force`) pour les ordres quand les alertes écran sont OFF.
- Plus de second bandeau / son depuis `onOrderReceived` ; pastille Athena + miroir FRAGO conservés.
- Si le téléphone est déjà ouvert : bascule douce vers Ordres reçus (TASK).

## Fichiers touchés
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_addScreenToast.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_onOrderReceived.sqf`

## Vérification
1. Alertes à l’écran : non → émettre un ordre depuis le poste → toast « Nouvel ordre » visible une fois.
2. Alertes à l’écran : oui → bandeau BIS, pas de second toast redondant.
3. Téléphone déjà ouvert → écran Ordres reçus se met à jour / s’ouvre sans ouvrir le téléphone à froid.

## Statut
corrigé (pack 1.5.47)
