# Checklist commande Athena (Lighthouse / perf)

Checklist pour valider la performance et l’expérience des surfaces « commandement » (hub, boîte de réception, manœuvres, PWA).

## Lighthouse (mobile + desktop)

- [ ] **Performance** ≥ 80 (mobile) / ≥ 90 (desktop) sur hub et pages portail légères
- [ ] **Accessibilité** ≥ 90 — contrastes, labels, focus visible, titres hiérarchisés
- [ ] **Bonnes pratiques** ≥ 90 — HTTPS, pas d’erreurs console critiques
- [ ] **SEO** — titres uniques, `lang="fr"`, meta description si page publique

## Chargement & budget

- [ ] Premier contenu utile (hero / titre) visible sans attendre des scripts secondaires
- [ ] CSS design-system chargé une seule fois ; pas de doublon Tailwind CDN + build en prod
- [ ] Images : formats adaptés, dimensions raisonnables ; logo PWA référencé correctement
- [ ] Pas de cascade bloquante de polices : `preconnect` fonts OK, fallback système acceptable

## PWA / hors ligne

- [ ] `manifest.webmanifest` valide (nom, icônes, `theme_color`, `start_url`)
- [ ] Service worker `sw.js` enregistré sans erreur ; cache shell mis à jour après déploiement (bump version cache)
- [ ] Message hors ligne compréhensible (français, sans jargon technique)
- [ ] Icône apple-touch / 192 px présente sous `assets/icons/`

## Interaction

- [ ] Palette de commande (Ctrl+K) : raccourcis + documents / forum / personnel / événements / formations
- [ ] Navigation 4 pôles (Commandement / Doctrine / Effectifs / Formation) lisible sur mobile
- [ ] Pages nouvelles (inbox, onboarding, C2, assistant, etc.) en thème clair slate/emerald

## Régression ciblée

- [ ] `/hub` titre « Centre de commandement » + section Actions
- [ ] `/manoeuvres` et `/pointage` partagent le même écran de présence
- [ ] `/evenements` thème clair, RSVP fonctionnel
- [ ] Assistant : réponse locale cadré communauté (pas d’appel externe requis en phase 1)
