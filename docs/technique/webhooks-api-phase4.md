# Webhooks & API (Phase 4 — socle)

## Objectif

Exposer des webhooks sortants Premium (`advanced_integrations`) pour automatiser Discord, outils RH et pipelines externes — toujours **scopés au tenant**.

## Événements prévus

| Événement | Déclencheur |
|-----------|-------------|
| `member.joined` | Acceptation invitation / candidature |
| `event.rsvp` | Changement RSVP manœuvre |
| `training.completed` | Parcours marqué complété |
| `forum.mission_priority` | Sujet priorité mission |
| `recruitment.status` | Changement statut candidature |

## Sécurité

- Clé HMAC par communauté (déjà amorcé via intégrations back-office)
- Pas de payload cross-tenant
- Rotation de clé côté back-office

## État

Socle documentaire + page templates/fédération/war-room livrés. Implémentation delivery queue à brancher sur `OrganizationIntegrationsController`.
