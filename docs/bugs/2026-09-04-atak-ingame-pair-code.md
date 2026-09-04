# Appairage en jeu : aucun code, codes de secours refusés

## Contexte

Nouvelle version du pack COMSPEC ATAK (ProdVersion). Sur l’écran
ATHENA // SECURE ACCESS, l’opérateur ouvre Associer ce terminal.

## Symptôme

- Statut : « Appairage indisponible », encadré « AUCUN CODE ».
- Générer un code ne produit rien.
- Un code de secours déjà enregistré est aussi refusé.

Le téléphone reste en mode local / hors ligne : sans code, la session
Athena ne s’ouvre pas.

## Cause

Le terminal demande au poste de créer le code, puis d’en valider un de
secours. Ces deux demandes passent par le même contrôleur que la page
Compte → Terminaux ATAK.

Ce contrôleur n’était pas dans le registre des liaisons. Le routeur
l’instanciait sans ses dépendances : la page web plantait, et les
demandes du jeu aussi.

Une validation trop stricte pouvait aussi refuser un identifiant Steam
ou un numéro de version atypique (éditeur, mission locale).

## Correctif

- Le contrôleur d’API se construit tout seul si le registre l’oublie.
- Steam / version atypiques sont ignorés au lieu de bloquer la demande.
- Si les tables ne sont pas encore prêtes, le poste répond clairement
  plutôt que de tomber en erreur interne.
- En jeu, les messages d’échec décrivent la situation sans coller le
  détail technique.

## Fichiers touchés

- `app/Controllers/Api/AtakDeviceAuthApiController.php`
- `app/Services/Atak/AtakDeviceAuthService.php`
- `tests/Unit/AtakDeviceAuthContractTest.php`
- `mod/Overwatch 2026/ProdVersion/@COMSPEC_ATAK/addons/comspec_atak_core/functions/fn_networkStartPairing.sqf`
- `mod/Overwatch 2026/ProdVersion/@COMSPEC_ATAK/addons/comspec_atak_core/functions/fn_networkRecoveryCode.sqf`

## Vérification

Après rechargement du portail : dans Arma, Associer ce terminal →
Générer un code affiche un code à recopier dans Compte, Terminaux ATAK.
Un code de secours déjà enregistré ouvre la session.

## Statut

corrigé — en attente de déploiement du portail
