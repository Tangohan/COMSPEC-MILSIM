# Changelog — COMSPEC Overwatch

## 1.2.1 — 2026-07-26

### Stabilisation
- Correctifs crash / gel au respawn (REAPP) : grâce respawn, sync différée, tracking véhicules sans empilement.
- Note de bienvenue au lancement : bêta publique (signalement bugs, changelog Workshop) — plus d’accord de confidentialité.
- Packs de notification sonore : libellés clairs (silencieux avec/sans vibration, ambiance tension, signal médical).
- Signalement d’erreurs / diagnostics → Athena (`ReportDiag` / `POST /api/atak/mod-report`), journal admin.

### Briefing / appui / manifeste
- Briefing / diaporama : accessible depuis ACE, bureau ATAK et panneau Athena.
- Demande d’appui aérien (CAS) : formulaire dédié + viewer 9-lignes à la réception.
- Manifeste de vol : formulaire dédié, libellés français.

### ATAK Enhanced / cTab (priorité couche Athena)
- Fonctions joueur ouvertes dans ATAK Enhanced / cTab (plus dans l’ancienne tablette par défaut).
- Terminal ATAK / cTab **requis par défaut**.
- Menu ACE « ATAK Tactique » rebranché (chemins parents, POI, rapports, évacuation, renfort, service véhicule).
- Contact permanent **HQ** dans la messagerie → poste de commandement Athena.
- Messages de groupe : restent en jeu (pas de spam journal web).
- Sélecteur de photos + remontée Quick Pictures.
- Écran liaison téléphone : adresse mobile + code d’appariement ; page web `/atak/connect` (code PC).

### Marqueurs → carte Athena
- Miroir Marker Dropper / marqueurs carte Arma + marqueurs utilisateur cTab (`_USER_DEFINED`).
- Pont cTab : écoute immédiate + file d’attente si liaison Athena pas encore prête.
- POI / évacuation / renfort / service ACE aussi affichés sur la carte web.
- Sync immédiate / plus fréquente ; rendu web (diamants hostiles, préfixes, formes, POI).
- Journal : libellés lisibles pour les marqueurs placés.

### Poste de commandement (Athena web)
- En-tête Tacmap redesignée (clusters, **Lier le jeu**, badge **BÊTA**, plus de bouton fluo).
- Identifiant de suivi Blue Force lié à l’indicatif.
- Action **Faire vibrer le terminal** depuis le menu contact.
- Filtre des messages techniques « réglages d’affichage » hors journal radio.
- Demande de renfort (QRF) sans position : refus métier explicite (400).

### Technique (notes pack)
- Rebuild `connect.pbo`, `atak_athena.pbo` (+ main / mavik_compat selon build).
- Workshop `3684656708` / `@COMSPECOverwatch` / `!Workshop`.
- Exemption clé API pour `/api/atak/mod-report` ; correctif `Database::getPdo` (dépôt rapports).

---

## 1.2.0 — 2026-07-24

### Accès anticipé (BÊTA)

- Badge **BÊTA** visible (Hub, Connexion Athena, tablette, launcher, carte ATAK web).
- Note d’accès au **premier lancement** (menu principal Arma) — confirmation unique, persistée localement.
- Enregistrement vers Athena : Steam / UID, nom, build Arma, version du pack.
- Journal admin « Accès anticipé Overwatch » sur le portail.
