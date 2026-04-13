<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UnitsOrbatMaskMode extends AbstractMigration
{
    public function up(): void
    {
        $units = $this->table('units');
        if (!$units->hasColumn('orbat_mask_mode')) {
            if ($units->hasColumn('show_on_public_page')) {
                $this->execute(<<<'SQL'
ALTER TABLE units
    ADD COLUMN orbat_mask_mode VARCHAR(32) NOT NULL DEFAULT 'none' AFTER show_on_public_page
SQL);
            } else {
                $this->execute(<<<'SQL'
ALTER TABLE units
    ADD COLUMN orbat_mask_mode VARCHAR(32) NOT NULL DEFAULT 'none'
SQL);
            }
        }
    }

    public function down(): void
    {
        $units = $this->table('units');
        if ($units->hasColumn('orbat_mask_mode')) {
            $this->execute('ALTER TABLE units DROP COLUMN orbat_mask_mode');
        }
    }
}
