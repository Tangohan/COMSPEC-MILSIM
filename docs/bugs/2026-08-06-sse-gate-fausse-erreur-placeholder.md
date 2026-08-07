# Sas SSE — fausse erreur et placeholder trompeur

## Contexte

Écran d’entrée `/atak/sse` (Accès SSE).

## Symptôme

- Bandeau rouge « Saisissez un code d’accès valide… » dès l’arrivée, alors qu’aucun code n’a encore été tenté.
- Le champ code semble déjà rempli (points) alors qu’il est vide.

## Cause

1. `SsePortalAccessMiddleware` flashait un message d’erreur à chaque redirection vers le sas (accès à une page protégée sans habilitation) — lu comme un échec de validation.
2. Le placeholder du champ était `········`, indistinguable d’une saisie masquée.

## Correctif

- Plus de flash côté middleware : le sas explique déjà qu’un code est requis ; les erreurs réelles restent celles du redeem / CSRF.
- Placeholder explicite « Saisir le code reçu ».
- Alerte sas avec contraste lisible ; formulaire à gauche, panneau visuel à droite ; secours si le logo aigle est absent.

## Fichiers touchés

- `app/Middleware/SsePortalAccessMiddleware.php`
- `views/atak/sse/gate.php`
- `public/assets/css/sse_portal.css`

## Vérification

- Ouvrir `/atak/sse` sans session : pas de bandeau rouge.
- Ouvrir `/atak/sse/operations` sans code : redirection propre vers le sas, sans faux message d’échec.
- Soumettre un code invalide : message d’erreur clair et lisible.
- Champ code vide : placeholder lisible, pas de faux « points remplis ».

## Statut

corrigé
