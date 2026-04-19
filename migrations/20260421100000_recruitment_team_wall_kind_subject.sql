-- Fil équipe recrutement : classification (post_kind) + sujet court.
-- Si MySQL renvoie l’erreur 1060 (« Nom du champ 'post_kind' déjà utilisé »), les colonnes
-- sont déjà en place (souvent via run-migrations.php). Dans ce cas : ne pas réexécuter
-- ce fichier ; vérifier avec les requêtes en bas que `subject` et l’index existent.
SET NAMES utf8mb4;

-- Colonnes (une seule fois)
ALTER TABLE recruitment_team_wall_entries
  ADD COLUMN post_kind VARCHAR(32) NOT NULL DEFAULT 'general' AFTER actor_user_id,
  ADD COLUMN subject VARCHAR(200) NULL DEFAULT NULL AFTER post_kind;

-- Index : à exécuter aussi si l’étape précédente a échoué avec 1060 (colonnes déjà là) mais que
-- SHOW INDEX … ne montre pas encore idx_rtw_tenant_kind_created.
-- Erreur 1061 = cet index existe déjà, rien à faire.
ALTER TABLE recruitment_team_wall_entries
  ADD KEY idx_rtw_tenant_kind_created (tenant_id, post_kind, created_at);

-- Vérifications (optionnel) :
-- SHOW COLUMNS FROM recruitment_team_wall_entries WHERE Field IN ('post_kind', 'subject');
-- SHOW INDEX FROM recruitment_team_wall_entries WHERE Key_name = 'idx_rtw_tenant_kind_created';
