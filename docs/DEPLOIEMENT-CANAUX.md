# Déploiement plateforme : canaux, versions et campagnes

Ce document sert de référence technique pour le code et les agents (Cursor), pas pour les libellés affichés aux utilisateurs métier.

## Chaîne d’environnements (`deployment_channels`)

Les canaux seedés par la migration `20260415000001_release_channels_and_tester_communities.sql` suivent une **priorité numérique croissante** (`priority`) :

| `code`     | Rôle habituel |
|-----------|----------------|
| `DEV`     | Intégration continue / développement |
| `INTERNAL` | Qualification interne |
| `TEST`    | Préqualification / bêta |
| `PREPROD` | Mise en miroir quasi production |
| `PROD`    | Production |

**Important** : un canal n’est pas équivalent à `APP_ENV` d’une instance PHP. Une même base peut piloter plusieurs canaux logiques ; l’instance détermine souvent quel canal est « ciblé » pour la résolution des habilitations (voir `ModuleReleaseAccessResolver`).

## Versions livrables (`platform_module_versions`)

- `version` : identifiant semver lisible (ex. `1.2.0`).
- `status` : cycle de vie (`draft`, `validated`, `published`, `rollback_ready`, `deprecated`).
- Traçabilité build (optionnel) : `build_ref`, `commit_hash`, `artifact_path`, `changelog`.

## Publication courante par canal (`platform_module_channel_releases`)

Pour un couple `(module_id, channel_id)`, une seule ligne a `is_current = 1`. La mise à jour passe par `PlatformModuleReleaseRepository::setCurrentReleaseForModuleChannel` (transaction : désactive l’ancienne, insère la nouvelle).

## Campagnes asynchrones (`deployment_campaigns` + `deployment_jobs`)

Une **campagne** regroupe plusieurs **jobs** ordonnés (`step_order` croissant). Chaque job pointe vers :

- `module_version_id` : la version à publier ;
- `target_channel_id` : l’environnement cible de l’étape.

Statuts campagne : `queued`, `in_progress`, `completed`, `failed`, `cancelled`.  
Statuts job : `queued`, `running`, `success`, `failed`, `rolled_back`.

L’exécution est prévue **asynchrone au sens file d’attente** : création immédiate des lignes, puis traitement par actions admin (POST « exécuter ») ou par automatisation future (cron / CLI) via `DeploymentCampaignProcessor::processCampaignSteps`.

## Fichiers clés

- Migration campagne : `migrations/20260415120000_deployment_campaigns.sql` + bloc idempotent dans `run-migrations.php`.
- Accès données : `app/Repositories/DeploymentCampaignRepository.php`.
- Moteur d’étapes : `app/Services/Platform/DeploymentCampaignProcessor.php`.
- Publication + audit : `app/Services/Platform/DeploymentChannelReleaseService.php`.
- UI admin : `app/Controllers/Admin/System/PlatformDeploymentAdminController.php`, vues `views/admin/system/deployment_campaign_*.php`, routes sous `/admin/system/deployment/campaigns`.
