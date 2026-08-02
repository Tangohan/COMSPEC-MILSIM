# SSE — Administration, rôles et séparation des fonctions

Rôles : collecteur terrain, opérateur SSE, analyste, validateur, responsable SSE, administrateur et auditeur. Permissions cibles : `sse.view`, `sse.case.create/edit/close`, `sse.person.view/consolidate`, `sse.biometric.view/create`, `sse.match.view/review/merge`, `sse.relationship.create`, `sse.collection.assign`, `sse.report.export`, `sse.audit.view`, `sse.admin.configure`.

Le rôle créateur ne reçoit pas implicitement le droit de consolider. L’administration configure statuts, confiance, seuils versionnés, sources, clôtures, relations, biométries, conservation, CRON et supervision des erreurs.
