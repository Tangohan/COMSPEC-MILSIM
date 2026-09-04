-- Retrait définitif des comptes de démonstration et de leur communauté.
-- Le compte technique de modération de production n'est pas concerné.

DELETE FROM site_role_assignments
WHERE LOWER(email_normalized) LIKE '%@demo.local';

DELETE FROM users
WHERE LOWER(email) LIKE '%@demo.local';

DELETE FROM tenants
WHERE slug = 'demo-comspec';
