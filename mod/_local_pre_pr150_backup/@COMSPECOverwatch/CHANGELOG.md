# Changelog — COMSPEC Overwatch

## 1.2.0 — 2026-07-24

### Accès anticipé (BÊTA)

- Badge **BÊTA** visible (Hub, Connexion Athena, tablette, launcher, carte ATAK web).
- Note d’accès au **premier lancement** (menu principal Arma) — confirmation unique, persistée localement.
- Enregistrement vers Athena : Steam / UID, nom, build Arma, version du pack (adresse réseau capturée côté serveur).
- Journal admin « Accès anticipé Overwatch » sur le portail.
- Extension `1.17` (`ShowBetaAccessNote`, `RegisterBeta`).

## 1.1.8 — 2026-07-24

### Réglages équipement & interface

- **Exiger un équipement** : option pour n’autoriser sync + interface que si le joueur porte un objet précis (désactivé par défaut → sync/UI sans objet).
- **Équipement requis** : liste (téléphone Android, tablette cTab, MicroDAGR, GPS, montre) + champ personnalisé optionnel.
- **Interface uniquement via ATAK Enhanced** : coupe la tablette Overwatch (K, ACE, ouverture auto) hors d’ATAK Enhanced ; liaison Athena et sync restent actives ; ouverture depuis ATAK Enhanced conservée.
- Version connect `1.1.8`, atak_athena `1.0.3`.

## 1.1.7 — 2026-07-24

### Connexion Athena (ATAK Enhanced)

- Icône **Connexion Athena** sur l’écran **Desktop** du téléphone ATAK Enhanced (à côté du raccourci Elevation).
- Écran de liaison : code (Connexion en jeu) et/ou identification Steam, bouton **Valider la liaison**.
- Barre de **transmission** + latence **ms** (mesure Ping du module Athena), rafraîchie en direct.
- Même entrée depuis l’app Athena (cTab), le Hub (touche K) et la tablette (vue Compte).
- Versions : connect `1.1.7`, atak_athena `1.0.2`.

## 1.1.5 — 2026-07-23

### Terrain & liaison (jeu)

- Positions plus fiables vers Athena (format numérique FR, altitude ASL, schéma unités).
- Statut **En liaison** : délai d’expiration (TTL) cohérent côté Athena.
- Couplage **téléphone** (QR) stabilisé.
- **Briefing** : diapositives enrichies (détail, commentaires) côté Athena et consultation tablette.
- **Alertes médicales** : moins de doublons, nettoyage à la déconnexion, fusion par sévérité, clôture des alertes obsolètes au réveil.
- **Ordres** : modèles personnalisés (templates) depuis Athena ; notifications et boîte de réception affinées.
- Tablette : fond (`colorBackground`), carte native embarquée, outils de marquage.
- Réglages CBA / interface milsim.

### Poste de commandement (Athena)

- Mentions **@** dans la messagerie.
- Outils **Tacmap** (itinéraire, terrain, alertes tactiques).
- Marqueurs **OTAN** (SIDC) en priorité configurable.
- Opérateurs ATAK : dédoublonnage d’affichage (indicatif vs nom de compte).
- Migrations « ensure columns » pour aligner le schéma en production.
- Correctif DI : enregistrement des modèles d’ordres (évite une erreur serveur sur les templates).

### Technique (notes internes)

- `CfgPatches` connect : `1.1.5` (`version` 1.15).
- Rebuild `connect.pbo` + extension si besoin pour Workshop.
- Voir aussi `PACKAGING.md` pour la checklist de diffusion.

## 1.1.4

Correctifs liaison / positions et tablette (versions antérieures 1.1.x).
