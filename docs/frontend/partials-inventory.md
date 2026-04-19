# Inventaire des partials réutilisables (P1.2)

## Partials déjà mutualisés

- `views/partials/flash_message.php` — feedback utilisateur.
- `views/partials/legal_site_links.php` — liens légaux pied de page.
- `views/partials/cookie_banner.php` — consentement cookies.
- `views/partials/tailwind_cdn_or_build.php` — chargement Tailwind.
- `views/partials/public_portal_auth_frame.php` / `public_portal_auth_footer.php` — cadre pages auth publiques.

## Rationalisation appliquée

- Nouveau socle UI partagé: `public/assets/css/design-system.css`.
- Nouveau script partagé: `public/assets/js/auth_forms.js` (normalisation des inputs email).
- Écrans auth harmonisés (`login`, `forgot-password`, `register`) sur des classes de design system.

## Prochain lot conseillé

- Extraire un partial `views/partials/auth/access_control_header.php`.
- Extraire un partial `views/partials/auth/security_footer.php`.
- Factoriser le bloc de formulaire login/forgot en composant serveur unique.
