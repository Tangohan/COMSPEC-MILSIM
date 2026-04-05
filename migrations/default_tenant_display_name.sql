-- Libellé du tenant système (slug default) : aligné sur l’UI « Pas d’organisation »
UPDATE tenants SET name = 'Pas d''organisation', updated_at = NOW() WHERE slug = 'default' AND name = 'Default Organisation';
