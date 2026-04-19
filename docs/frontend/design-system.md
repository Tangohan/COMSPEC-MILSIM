# Mini design system pragmatique

## Tokens

Définis dans `public/assets/css/design-system.css`:

- Couleurs: `--ds-color-*`
- Radius: `--ds-radius-*`
- Espacements: `--ds-space-*`
- Focus ring: `--ds-focus-ring`

## Composants utilitaires

- Boutons: `.ds-btn`, variantes `.ds-btn--primary`, `.ds-btn--secondary`, `.ds-btn--ghost`
- Champs: `.ds-input`, `.ds-label`, `.ds-help`
- Carte: `.ds-card`
- Badges/états: `.ds-state`, variantes `--success|--warning|--danger|--info`

## Règles d'usage

- Préférer les classes DS avant classes inline répétées.
- Limiter les styles inline aux cas exceptionnellement contextuels.
- Centraliser les comportements scripts récurrents (ex: email lower-case) dans des fichiers JS dédiés.
