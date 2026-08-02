# SSE — Référence API cible

Endpoints prévus : `POST /api/sse/acquisitions`, CRUD `/api/sse/interest-cases`, ajout d’observations/biométries/relations, `GET /api/sse/matches`, revue d’un match, consolidation et clôture.

Tout endpoint exige authentification, permission granulaire, tenant issu du contexte serveur (jamais accepté depuis le JSON), clé d’idempotence sur acquisitions, validation de schéma et audit. Les réponses distinguent score machine, décision opérateur, confiance finale et preuve. Cette liste décrit le contrat cible; elle ne signifie pas que ces routes sont déjà exposées.
