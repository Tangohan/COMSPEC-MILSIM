# Validation « Nouvel objet » → 403 / 404 sur `/atak/sse/objets`

## Contexte

Soumission du formulaire `/atak/sse/objets/nouveau`.

## Symptôme

Après validation : page Hostinger **403 Forbidden** ou page Athena **404** sur l’URL `/public/atak/sse/objets` — pas d’ouverture de l’investigation.

## Cause

1. Le formulaire **POST**ait vers `/atak/sse/objets`.
2. Il n’existait **aucun GET** sur cette URL exacte (seulement `/objets/{type}` et `/objets/nouveau`) → **404** Athena si on atterrit en GET.
3. Le **403** brut Hostinger (« Access to this resource… ») survient souvent quand le POST multipart est coupé avant PHP (ModSecurity / conflit chemin), l’URL restant celle de l’action du formulaire.
4. En succès, le code devait rediriger vers `/atak/sse/toiles/{id}` — donc rester sur `/objets` signifie que le POST n’a pas abouti proprement.

## Correctif

- POST dédié : `/atak/sse/objets/creer` (+ alias `/nouveau` et legacy `/objets`)
- GET `/atak/sse/objets` → redirection vers Opérations
- Formulaire pointe vers `…/creer`
- `objectStore` : retours d’erreur plus clairs + try/catch création

## Fichiers touchés

- `routes/web.php`
- `app/Controllers/Web/SsePortalController.php`
- `views/atak/sse/object_create.php`

## Vérification

Créer une identité sans image → redirection vers une **investigation** (toile) avec message de succès.

## Statut

corrigé (à déployer en prod avec `routes/web.php`)
