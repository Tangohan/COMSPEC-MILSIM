<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class OrbatDisplayTypesAndMedia extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->hasTable('tenant_orbat_chart_types')) {
            $t = $this->table('tenant_orbat_chart_types', ['id' => true, 'primary_key' => ['id']]);
            $t->addColumn('tenant_id', 'integer', ['signed' => false])
                ->addColumn('slug', 'string', ['limit' => 64])
                ->addColumn('label', 'string', ['limit' => 120])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['tenant_id', 'slug'], ['unique' => true])
                ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->create();
        }

        $units = $this->table('units');
        if (!$units->hasColumn('orbat_display_type')) {
            $this->execute('ALTER TABLE units ADD COLUMN orbat_display_type VARCHAR(64) NOT NULL DEFAULT \'command\' AFTER type');
        }
        if (!$units->hasColumn('orbat_icon_path')) {
            $after = $units->hasColumn('orbat_mask_mode') ? 'orbat_mask_mode' : 'orbat_display_type';
            $this->execute('ALTER TABLE units ADD COLUMN orbat_icon_path VARCHAR(512) NULL AFTER ' . $after);
        }
        if (!$units->hasColumn('orbat_image_path')) {
            $this->execute('ALTER TABLE units ADD COLUMN orbat_image_path VARCHAR(512) NULL AFTER orbat_icon_path');
        }
        if (!$units->hasColumn('orbat_details')) {
            $this->execute('ALTER TABLE units ADD COLUMN orbat_details TEXT NULL AFTER orbat_image_path');
        }

        // Données existantes : le champ type mélangeait structure et style d’organigramme.
        $this->execute(<<<'SQL'
UPDATE units
SET orbat_display_type = IF(
    type IN ('command','alpha','bravo','support','special'),
    type,
    'command'
)
SQL);
        $this->execute(<<<'SQL'
UPDATE units
SET type = IF(
    type IN ('command','alpha','bravo','support','special'),
    'unit',
    type
)
SQL);
    }

    public function down(): void
    {
        $units = $this->table('units');
        if ($units->hasColumn('orbat_details')) {
            $this->execute('ALTER TABLE units DROP COLUMN orbat_details');
        }
        if ($units->hasColumn('orbat_image_path')) {
            $this->execute('ALTER TABLE units DROP COLUMN orbat_image_path');
        }
        if ($units->hasColumn('orbat_icon_path')) {
            $this->execute('ALTER TABLE units DROP COLUMN orbat_icon_path');
        }
        if ($units->hasColumn('orbat_display_type')) {
            $this->execute('ALTER TABLE units DROP COLUMN orbat_display_type');
        }
        if ($this->hasTable('tenant_orbat_chart_types')) {
            $this->table('tenant_orbat_chart_types')->drop()->save();
        }
    }
}
