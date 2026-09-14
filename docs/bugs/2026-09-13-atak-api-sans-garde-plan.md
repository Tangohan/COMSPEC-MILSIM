# Garde de plan manquante sur l’API carte

## Contexte

Le poste web refuse l’accès à la carte si la communauté n’a pas la fonction « carte et liaisons ». L’API tactique, elle, acceptait encore les liaisons en jeu dès qu’une clé ou une session était présentée.

## Symptôme

Une communauté sans cette fonction sur son offre pouvait quand même envoyer et recevoir des positions, messages et ordres depuis Arma, alors que le poste web affichait déjà l’écran d’offre.

## Cause

La vérification du plan n’était appliquée que sur les pages web. L’API tactique contrôlait la communauté, la maintenance et la clé, mais pas l’offre.

## Correctif

Après identification de la communauté, l’API refuse (message clair, accès interdit) si l’offre n’inclut pas la carte et les liaisons. La sonde de présence du poste reste ouverte, pour distinguer un portail injoignable d’un accès non compris dans l’offre. L’identification ami / ennemi suit la même règle.

## Fichiers touchés

- `app/Support/AtakPlanAccess.php`
- `app/Controllers/Api/AtakApiController.php`
- `app/Controllers/Api/IffController.php`
- `docs/technical/SEC-ATHENA-C2.md`
- `docs/registry/REG-ATHENA-CAPABILITIES.md`

## Vérification

Tests unitaires de présence du contrôle et du libellé. La sonde `/api/atak/ping` ne porte pas ce contrôle.

## Statut

corrigé
