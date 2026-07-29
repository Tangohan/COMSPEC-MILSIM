# Détections automatiques visibles après déconnexion

## Contexte
Panneau **Assistances médicales** → onglet **Au sol / suivi** (« Détections automatiques »).

## Symptôme
Une carte du type « N-10 — Au sol — inconscient » restait affichée alors que l’opérateur n’était plus en liaison (déconnexion jeu, TTL, ou coupure manuelle).

## Cause
- Le panneau conservait des alertes reconstituées depuis le **cache tchat / Liaison**, même après passage du contact en **hors liaison**.
- Le rafraîchissement des effectifs ne déclenchait pas toujours un re-filtrage immédiat des assistances.
- Côté serveur, la clôture automatique des alertes ouvertes n’était déclenchée que sur l’endpoint **unités**, pas sur **medical-alerts**.

## Correctif
- `atak-medical-alerts.js` : masquer alertes et unités à secourir dont l’indicatif est **offline** ; réagir à `atak:units-updated` ; ne plus conserver d’anciennes `criticalUnits` si l’API renvoie une liste vide ; empêcher le cache local de réouvrir une alerte déjà clôturée.
- `AtakApiController::medicalAlertsIndex` : appeler `logStaleUnitDisconnects()` avant agrégation pour clôturer les alertes ouvertes quand le TTL bascule un contact hors liaison.

## Fichiers touchés
- `public/assets/js/atak-medical-alerts.js`
- `app/Controllers/Api/AtakApiController.php`
- `views/atak.php`

## Vérification
1. Faire tomber un joueur en « au sol / inconscient » → la carte apparaît dans **Détections automatiques**.
2. Couper sa liaison (menu contact) ou le laisser expirer (TTL) → la carte disparaît sans rechargement manuel de la page.
3. Vérifier que le journal Liaison / tchat conserve l’historique.

## Statut
Corrigé
