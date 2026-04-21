# TrainingPublicationEngine

Architecture stratégique du module de liaison **LMS / Documents / Courrier**.

## Sous-domaines

- **LMS_SOURCE** : normalisation pédagogique (`chapters`, `objectives`, `metadata`).
- **DOC_ENGINE** : pipeline de compilation document (`cover`, `toc`, `pages`, `annexes`, `qr`, `watermark`).
- **COURRIER_COMPLIANCE** : circuit multi-acteurs (rédacteur, relecteur métier, validateur hiérarchique, conformité/courrier, approbation finale).
- **SECURITY_LAYER** : isolation tenant stricte (`id + tenant_id`), classification diffusion, journal de preuves.
- **RELEASE_SYSTEM** : workflow versionné `draft -> review -> validated -> published -> archived`, obsolescence et remplacement.

## API

- `POST /api/training/publications`
- `POST /api/training/publications/{id}/compile`
- `POST /api/training/publications/{id}/validate`
- `POST /api/training/publications/{id}/release`
- `POST /api/training/publications/{id}/read-progress`
- `POST /api/training/publications/{id}/attest`
- `POST /api/training/publications/{id}/annexes`
- `POST /api/training/publications/{id}/obsolete`

## Règles critiques implémentées

1. Aucun accès lecture publication hors `tenant_id`.
2. Aucun `validate`/`release` sans droits `documents.publish`, `training.publish`, `courrier.validate`.
3. Compilation multi-format unique source (`pdf_official`, `web`, `mobile`, `print`, `lms_package`).
4. Diff intelligent par révision (`chapters_added`, `paragraphs_modified`, `annexes_removed`, `impact`).
5. Preuve de lecture complète (ouverture, temps cumulé, dernière page, attestation, quiz_score).
6. Journal des preuves horodaté : publication, validation, consultation, export, obsolescence.
7. Score de conformité avant publication + cachet institutionnel (référence, autorité, empreinte).


## Back-office / RBAC

- **UI back-office** : `GET /formation/publications`
- **Change log** : `GET /formation/publications/{id}/changelog`
- **Alias legacy** : `/back-office/ressources/training/publications` (redirection canonique LMS)
- **Permission dédiée** : `training.publications.manage`
- **Attribution auto** : rôles `tenant_admin` et `community_owner` (migration + seed nouveaux tenants)
