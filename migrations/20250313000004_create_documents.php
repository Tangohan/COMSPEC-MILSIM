<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateDocuments extends AbstractMigration
{
    public function change(): void
    {
        $categories = $this->table('document_categories', ['id' => true, 'primary_key' => ['id']]);
        $categories->addColumn('tenant_id', 'integer', ['signed' => false])
            ->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('slug', 'string', ['limit' => 100])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex(['tenant_id', 'slug'], ['unique' => true])
            ->create();

        $documents = $this->table('documents', ['id' => true, 'primary_key' => ['id']]);
        $documents->addColumn('tenant_id', 'integer', ['signed' => false])
            ->addColumn('title', 'string', ['limit' => 255])
            ->addColumn('slug', 'string', ['limit' => 100])
            ->addColumn('document_category_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('status', 'string', ['limit' => 50, 'default' => 'draft'])
            ->addColumn('created_by', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('document_category_id', 'document_categories', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->addIndex(['tenant_id', 'slug'], ['unique' => true])
            ->create();

        $versions = $this->table('document_versions', ['id' => true, 'primary_key' => ['id']]);
        $versions->addColumn('document_id', 'integer', ['signed' => false])
            ->addColumn('file_path', 'string', ['limit' => 500])
            ->addColumn('checksum', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('mime_type', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('size', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('created_by', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('change_notes', 'text', ['null' => true])
            ->addColumn('published_at', 'datetime', ['null' => true])
            ->addColumn('is_current', 'boolean', ['default' => true])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('document_id', 'documents', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex(['document_id', 'is_current'])
            ->create();
    }
}
