# SSE — Sécurité, intégrité et traçabilité

Exigences : filtrage tenant sur lectures, écritures, API et jobs; RBAC et compartiments; identifiants non énumérables; chiffrement des biométries; empreinte des médias; téléchargements et exports audités; journal append-only; réauthentification pour fusion/consolidation; conservation, anonymisation et purge contrôlées.

La consolidation et la fusion sont séparées de la création. Les données contradictoires et propositions rejetées ne sont jamais effacées. Les requêtes directes par ID exigent simultanément `tenant_id`; un job sans tenant est refusé.
