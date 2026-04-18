-- Recrutement: contexte des modèles de réponse + index dédié pour filtres.
-- La configuration SLA est stockée dans tenants.settings.recruitment.enlistment_sla_hours (pas de colonne SQL dédiée).

ALTER TABLE `enlistment_canned_messages`
  ADD COLUMN IF NOT EXISTS `context` varchar(32) NOT NULL DEFAULT 'generic' AFTER `body`,
  ADD KEY IF NOT EXISTS `tenant_context_sort` (`tenant_id`, `context`, `sort_order`);
