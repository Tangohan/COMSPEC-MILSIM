<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateEnlistments extends AbstractMigration
{
    public function change(): void
    {
        $enlistments = $this->table('enlistments', ['id' => true, 'primary_key' => ['id']]);
        $enlistments->addColumn('tenant_id', 'integer', ['signed' => false])
            ->addColumn('first_name', 'string', ['limit' => 100])
            ->addColumn('last_name', 'string', ['limit' => 100])
            ->addColumn('email', 'string', ['limit' => 255])
            ->addColumn('callsign', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('country', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('experience', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('specialty', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('platform', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('availability', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('status', 'string', ['limit' => 50, 'default' => 'submitted'])
            ->addColumn('reviewed_by', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('reviewed_at', 'datetime', ['null' => true])
            ->addColumn('reviewer_comment', 'text', ['null' => true])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex(['tenant_id', 'status'])
            ->create();
    }
}
