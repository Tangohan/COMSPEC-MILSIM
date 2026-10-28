# Page d’accueil publique — 500 si `public_highlight_style` absent

## Symptôme

- GET `/back-office/community/presentation` → incident technique (mail ERROR_ALERT).
- Communautés dont `settings.community` n’a jamais stocké `public_highlight_style`.

## Cause

Dans `views/admin/organization/community_presentation.php`, le style d’alerte était calculé ainsi :

```php
in_array(($c['public_highlight_style'] ?? 'info'), [...], true)
    ? (string) $c['public_highlight_style']
    : 'info';
```

Le `??` fournit `'info'` pour le test, donc la branche vraie s’exécute et **relit** la clé manquante → `ErrorException` (Undefined array key) sous le handler d’erreurs.

Même piège que le correctif vitrine publique (`abcd47898` sur `TenantCommunityProfileService`).

## Correctif

Assigner une fois la valeur normalisée (`$hlStyleRaw`), puis valider / fallback sans second accès à la clé.
