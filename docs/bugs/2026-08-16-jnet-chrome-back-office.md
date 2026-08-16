# JNET — coque back-office ATHENA

## Contexte

L’extranet JNET avait sa propre coque plein écran sombre (style Bureau SSE), séparée du back-office ATHENA.

## Demande

Réutiliser l’UI du back-office et y intégrer JNET directement.

## Correctif

- `JnetPortalController::render` → `layout.main` + chrome ATHENA (`isBackOfficeShell`)
- Contenu via `views/jnet/_bo_content.php` (onglets + stage)
- Préfixe `jnet` dans `BackOfficePageContext::chromePathPrefixes`
- Entrée sidebar **OPÉRATIONS → Extranet d’unité**
- Métadonnées `config/back_office_pages.php`
- CSS `jnet_bo_embed.css` : thème clair aligné BO (plus de plein écran sombre)

## Fichiers touchés

- `app/Controllers/Web/JnetPortalController.php`
- `app/Support/BackOfficePageContext.php`
- `config/back_office_pages.php`
- `views/jnet/_bo_content.php`
- `views/partials/ath_sidebar_nav.php`
- `views/partials/back_office_sidebar.php`
- `public/assets/css/jnet_bo_embed.css`

## Vérification

1. Ouvrir `/public/jnet` connecté → sidebar ATHENA + en-tête BO + contenu JNET.
2. Menu **Opérations → Extranet d’unité** navigue entre les sections.
3. `/public/back-office` inchangé pour le reste.

## Statut

corrigé
