<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateTraining extends AbstractMigration
{
    public function change(): void
    {
        $modules = $this->table('training_modules', ['id' => true, 'primary_key' => ['id']]);
        $modules->addColumn('tenant_id', 'integer', ['signed' => false])
            ->addColumn('title', 'string', ['limit' => 255])
            ->addColumn('slug', 'string', ['limit' => 100])
            ->addColumn('code', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('type', 'string', ['limit' => 50, 'default' => 'html'])
            ->addColumn('status', 'string', ['limit' => 50, 'default' => 'published'])
            ->addColumn('estimated_duration_min', 'integer', ['null' => true])
            ->addColumn('created_by', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex(['tenant_id', 'slug'], ['unique' => true])
            ->create();

        $progress = $this->table('training_progress', ['id' => true, 'primary_key' => ['id']]);
        $progress->addColumn('tenant_id', 'integer', ['signed' => false])
            ->addColumn('user_id', 'integer', ['signed' => false])
            ->addColumn('training_module_id', 'integer', ['signed' => false])
            ->addColumn('progress_percent', 'integer', ['default' => 0])
            ->addColumn('status', 'string', ['limit' => 50, 'default' => 'in_progress'])
            ->addColumn('started_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('last_activity_at', 'datetime', ['null' => true])
            ->addColumn('completed_at', 'datetime', ['null' => true])
            ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('training_module_id', 'training_modules', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex(['user_id', 'training_module_id'], ['unique' => true])
            ->create();

        $certs = $this->table('training_certificates', ['id' => true, 'primary_key' => ['id']]);
        $certs->addColumn('tenant_id', 'integer', ['signed' => false])
            ->addColumn('training_module_id', 'integer', ['signed' => false])
            ->addColumn('user_id', 'integer', ['signed' => false])
            ->addColumn('certificate_code', 'string', ['limit' => 50])
            ->addColumn('issued_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('expires_at', 'datetime', ['null' => true])
            ->addColumn('issued_by', 'integer', ['signed' => false, 'null' => true])
            ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('training_module_id', 'training_modules', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex(['user_id', 'training_module_id'])
            ->create();
    }
}
