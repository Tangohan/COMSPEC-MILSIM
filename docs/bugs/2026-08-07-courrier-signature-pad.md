# Courrier — pad de signature inutilisable

## Contexte

Modal « Signer le document » sur `/courrier/editor/{id}` : zone canvas pour dessiner la signature.

## Symptôme

Impossible de dessiner correctement (trait absent / décalé) ou pad mort après bascule « Ma signature enregistrée » → « Dessiner ».

## Cause

1. `x-if` détruisait le `<canvas>` : les écouteurs d’événements étaient perdus.
2. Canvas en `w-full` (CSS) avec buffer fixe 400×160 : coordonnées souris / doigt faussées.
3. Initialisation possible avant que le modal soit visible (dimensions à 0).

## Correctif

- `x-show` à la place de `x-if` (canvas conservé).
- Recalage buffer canvas sur la taille affichée + `devicePixelRatio`.
- Init après ouverture (`$nextTick` + `requestAnimationFrame`).
- Refus d’envoyer une zone vide (`hasInk`).
- `baseUrl` normalisé pour les appels `/courrier/.../sign`.

## Fichiers touchés

- `views/courrier/partials/signature-modal.php`

## Vérification

1. Document au statut « Validé » → « Signer le document ».
2. Dessiner dans le cadre (souris / tactile) : trait visible sous le curseur.
3. Effacer puis redessiner ; valider → document signé.
4. Basculer vers signature enregistrée puis revenir à Dessiner : pad toujours actif.

## Statut

corrigé
