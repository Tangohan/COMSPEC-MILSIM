<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateTenantsAndUsers extends AbstractMigration
{
    public function change(): void
    {
        $tenants = $this->table('tenants', ['id' => true, 'primary_key' => ['id']]);
        $tenants->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('slug', 'string', ['limit' => 100])
            ->addColumn('logo_url', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('settings', 'text', ['null' => true])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addIndex(['slug'], ['unique' => true])
            ->create();

        $roles = $this->table('roles', ['id' => true, 'primary_key' => ['id']]);
        $roles->addColumn('tenant_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('name', 'string', ['limit' => 100])
            ->addColumn('slug', 'string', ['limit' => 100])
            ->addColumn('description', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('is_system', 'boolean', ['default' => false])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex(['tenant_id', 'slug'], ['unique' => true])
            ->create();

        $permissions = $this->table('permissions', ['id' => true, 'primary_key' => ['id']]);
        $permissions->addColumn('tenant_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('name', 'string', ['limit' => 100])
            ->addColumn('slug', 'string', ['limit' => 100])
            ->addColumn('module', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex(['tenant_id', 'slug'], ['unique' => true])
            ->create();

        $rolePermissions = $this->table('role_permissions', ['id' => false, 'primary_key' => ['role_id', 'permission_id']]);
        $rolePermissions->addColumn('role_id', 'integer', ['signed' => false])
            ->addColumn('permission_id', 'integer', ['signed' => false])
            ->addForeignKey('role_id', 'roles', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('permission_id', 'permissions', 'id', ['delete' => 'CASCADE'])
            ->addIndex(['role_id', 'permission_id'], ['unique' => true])
            ->create();

        $grades = $this->table('grades', ['id' => true, 'primary_key' => ['id']]);
        $grades->addColumn('tenant_id', 'integer', ['signed' => false])
            ->addColumn('name', 'string', ['limit' => 100])
            ->addColumn('short_name', 'string', ['limit' => 20])
            ->addColumn('rank_order', 'integer', ['default' => 0])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex(['tenant_id'])
            ->create();

        $users = $this->table('users', ['id' => true, 'primary_key' => ['id']]);
        $users->addColumn('tenant_id', 'integer', ['signed' => false])
            ->addColumn('email', 'string', ['limit' => 255])
            ->addColumn('password_hash', 'string', ['limit' => 255])
            ->addColumn('display_name', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('callsign', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('avatar_url', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('role_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('grade_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('status', 'string', ['limit' => 50, 'default' => 'pending'])
            ->addColumn('last_login_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('role_id', 'roles', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->addForeignKey('grade_id', 'grades', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->addIndex(['tenant_id', 'email'], ['unique' => true])
            ->addIndex(['tenant_id'])
            ->create();

        $sessions = $this->table('sessions', ['id' => false, 'primary_key' => ['id']]);
        $sessions->addColumn('id', 'string', ['limit' => 128])
            ->addColumn('user_id', 'integer', ['signed' => false])
            ->addColumn('tenant_id', 'integer', ['signed' => false])
            ->addColumn('payload', 'text')
            ->addColumn('expires_at', 'datetime')
            ->addIndex(['id'], ['unique' => true])
            ->addIndex(['user_id', 'tenant_id'])
            ->create();

        $passwordResets = $this->table('password_resets', ['id' => true, 'primary_key' => ['id']]);
        $passwordResets->addColumn('user_id', 'integer', ['signed' => false])
            ->addColumn('token_hash', 'string', ['limit' => 64])
            ->addColumn('expires_at', 'datetime')
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->addIndex(['token_hash'])
            ->create();

        $loginAttempts = $this->table('login_attempts', ['id' => true, 'primary_key' => ['id']]);
        $loginAttempts->addColumn('email', 'string', ['limit' => 255])
            ->addColumn('ip', 'string', ['limit' => 45])
            ->addColumn('success', 'boolean', ['default' => false])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['email', 'created_at'])
            ->create();

        $auditLogs = $this->table('audit_logs', ['id' => true, 'primary_key' => ['id']]);
        $auditLogs->addColumn('tenant_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('action', 'string', ['limit' => 100])
            ->addColumn('entity_type', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('entity_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('old_value', 'text', ['null' => true])
            ->addColumn('new_value', 'text', ['null' => true])
            ->addColumn('ip', 'string', ['limit' => 45, 'null' => true])
            ->addColumn('user_agent', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['tenant_id', 'created_at'])
            ->addIndex(['user_id'])
            ->create();
    }
}
