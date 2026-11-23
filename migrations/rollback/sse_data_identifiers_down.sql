-- Retour arrière de l'étape sse_data_identifiers (5020). À lancer à la main : efface les numéros d'identification, noms de code,
-- DSE, liens vers les IA et le journal de leurs modifications. Les données SSE elles-mêmes ne sont pas touchées ; une nouvelle
-- passe de l'étape attribuerait de NOUVEAUX identifiants (les anciens DSE ne seraient pas retrouvés).
DROP TABLE IF EXISTS sse_identifier_history;
DROP TABLE IF EXISTS sse_identifier_counters;
DROP TABLE IF EXISTS sse_data_identifiers;
