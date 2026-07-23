# Changelog — COMSPEC Overwatch

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
