# Module de liaison DOCUMENTS / COURRIER / LMS FORMATION

## Objectif
Mettre en place un module unifié pour produire et diffuser des **documentations de formation complètes** (type livret officiel), en connectant :

- **Documents** : stockage, versioning, métadonnées, visibilité.
- **Courrier** : gabarits institutionnels, entêtes, références, validation.
- **LMS Formation** : parcours, modules, leçons, suivi d’achèvement et preuves.

Ce module permet de générer des supports visuels homogènes avec couverture personnalisée, sommaire, pages, historique des versions, QR code et protections documentaires.

## Périmètre fonctionnel

### 1) Couverture « Thumb » personnalisable
- Sélecteur d’image de couverture (bibliothèque Documents ou upload contrôlé).
- Application d’un thème (couleurs, police, zone logo, bandeau latéral).
- Modèle réutilisable par type de formation.

### 2) Superposition de texte
- Zones configurables : titre, sous-titre, code série, millésime, unité.
- Positionnement par presets (haut-gauche, centre, bas-gauche).
- Contraste automatique (ombre + fond dégradé) pour lisibilité.

### 3) Génération de QR Code
- QR code unique pointant vers la fiche LMS ou une preuve de publication.
- QR injecté en pied de page et/ou 4e de couverture.
- Option de rotation (nouveau QR par version publiée).

### 4) Historique de MàJ
- Journal de versions lisible par les apprenants : date, auteur, motif, impact.
- Liaison avec l’audit log LMS et le cycle de validation Courrier.
- Export PDF du changelog.

### 5) Sommaire dynamique
- Généré depuis les modules et leçons LMS marqués « publiables ».
- Numérotation automatique des titres et sous-titres.
- Liens internes PDF cliquables.

### 6) Pages et descriptions
- Construction d’un document multi-pages (chapitres + annexes).
- Description pédagogique synchronisée avec les objectifs LMS.
- Inclusion de contenus mixtes (texte, image, encadrés opérationnels).

### 7) Sécurité de document
- Classification (interne / diffusion restreinte / sensible).
- Contrôle d’accès aligné sur les permissions Documents et LMS.
- Journalisation des consultations et téléchargements.

### 8) Filigrane
- Filigrane configurable : texte, opacité, diagonale, répétition.
- Filigrane contextuel : « BROUILLON », « OFFICIEL », « DIFFUSION LIMITÉE ».
- Option filigrane nominatif pour exports sensibles.

## Flux de travail cible

1. **Auteur LMS** prépare la structure (modules, leçons, objectifs).
2. **Référent Courrier** applique un gabarit institutionnel.
3. **Module de liaison** assemble couverture, sommaire, contenus et QR.
4. **Validateur** vérifie conformité + sécurité + filigrane.
5. **Publication** dans Documents et rattachement à la formation LMS.
6. **Suivi** via historique des MàJ et statistiques de consultation.

## Modèle de données minimal (proposition)

### Table `training_document_publications`
- `id`
- `tenant_id`
- `course_id` (LMS)
- `document_id` (Documents)
- `courrier_template_id` (optionnel)
- `status` (`draft`, `review`, `published`, `archived`)
- `cover_asset_id`
- `overlay_payload_json`
- `watermark_payload_json`
- `qr_payload_json`
- `security_level`
- `version_label`
- `published_at`
- `created_by`
- `updated_by`

### Table `training_document_publication_revisions`
- `id`
- `publication_id`
- `revision_number`
- `change_summary`
- `diff_payload_json`
- `pdf_snapshot_path`
- `created_at`
- `created_by`

## API interne (proposition)

- `POST /api/training/publications` : créer un brouillon lié à une formation.
- `PATCH /api/training/publications/{id}/cover` : modifier thumb + overlays.
- `PATCH /api/training/publications/{id}/security` : sécurité + filigrane.
- `POST /api/training/publications/{id}/build` : générer PDF + sommaire + QR.
- `POST /api/training/publications/{id}/publish` : publier vers Documents.
- `GET /api/training/publications/{id}/history` : historique de MàJ.

## Règles de sécurité

- Vérification des permissions conjointes :
  - `documents.publish`
  - `courrier.validate`
  - `training.publish`
- Téléchargement conditionné au niveau de classification.
- Filigrane nominatif activable pour profils non administrateurs.
- Traçabilité complète (lecture, export, partage, révocation).

## UX attendue (écran unique)

- **Colonne gauche** : configuration (thumb, overlay, QR, filigrane).
- **Centre** : preview du document (couverture + pages).
- **Colonne droite** : historique MàJ, sécurité, actions de publication.
- **Actions rapides** : Enregistrer brouillon, Générer PDF, Publier, Archiver.

## Critères d’acceptation MVP

- Création d’une publication depuis une formation LMS existante.
- Génération d’une couverture personnalisée avec superposition texte.
- Génération d’un QR code valide vers la fiche publique/interne.
- Création automatique du sommaire à partir des modules LMS.
- Journal d’historique visible et exportable.
- Filigrane activable et persistant dans le PDF final.
- Respect des permissions et refus explicite en cas d’accès insuffisant.

## Démo associée
Un prototype visuel est disponible dans `demo/demo-documents-courrier-lms-formation.html` pour illustrer l’assemblage des blocs (couverture, sommaire, pages, historique, sécurité, filigrane, QR).
