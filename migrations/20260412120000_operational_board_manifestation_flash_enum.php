<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class OperationalBoardManifestationFlashEnum extends AbstractMigration
{
    public function up(): void
    {
        $t = $this->table('planning_entries');
        if (!$t->exists()) {
            return;
        }
        $this->execute(<<<'SQL'
ALTER TABLE planning_entries
    MODIFY COLUMN entry_type ENUM(
        'permanence','info','mission','task','formation',
        'manifestation','flash_info'
    ) NOT NULL
SQL);
    }

    public function down(): void
    {
        $t = $this->table('planning_entries');
        if (!$t->exists()) {
            return;
        }
        $this->execute(<<<'SQL'
UPDATE planning_entries SET entry_type = 'mission' WHERE entry_type IN ('manifestation','flash_info')
SQL);
        $this->execute(<<<'SQL'
ALTER TABLE planning_entries
    MODIFY COLUMN entry_type ENUM('permanence','info','mission','task','formation') NOT NULL
SQL);
    }
}
