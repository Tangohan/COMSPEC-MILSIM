<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUnitsAndProfiles extends AbstractMigration
{
    public function change(): void
    {
        $units = $this->table('units', ['id' => true, 'primary_key' => ['id']]);
        $units->addColumn('tenant_id', 'integer', ['signed' => false])
            ->addColumn('parent_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('slug', 'string', ['limit' => 100])
            ->addColumn('type', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('code', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('commander_user_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('display_order', 'integer', ['default' => 0])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex(['tenant_id', 'slug'], ['unique' => true])
            ->addIndex(['tenant_id'])
            ->create();

        $userUnits = $this->table('user_units', ['id' => true, 'primary_key' => ['id']]);
        $userUnits->addColumn('user_id', 'integer', ['signed' => false])
            ->addColumn('unit_id', 'integer', ['signed' => false])
            ->addColumn('is_primary', 'boolean', ['default' => false])
            ->addColumn('assigned_by', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('assigned_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('ended_at', 'datetime', ['null' => true])
            ->addColumn('assignment_type', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('unit_id', 'units', 'id', ['delete' => 'CASCADE'])
            ->addIndex(['user_id', 'unit_id'])
            ->create();

        $userProfiles = $this->table('user_profiles', ['id' => false, 'primary_key' => ['user_id']]);
        $userProfiles->addColumn('user_id', 'integer', ['signed' => false])
            ->addColumn('first_name', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('last_name', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('birth_date', 'date', ['null' => true])
            ->addColumn('nationality', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('timezone', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('language', 'string', ['limit' => 10, 'null' => true])
            ->addColumn('bio', 'text', ['null' => true])
            ->addColumn('phone', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('emergency_contact', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->create();

        $personnelExtras = $this->table('personnel_extras', ['id' => false, 'primary_key' => ['user_id']]);
        $personnelExtras->addColumn('user_id', 'integer', ['signed' => false])
            ->addColumn('service_number', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('squadron', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('date_of_enlistment', 'date', ['null' => true])
            ->addColumn('clearance_level', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('flight_hours', 'decimal', ['precision' => 10, 'scale' => 1, 'null' => true])
            ->addColumn('specializations', 'text', ['null' => true])
            ->addColumn('readiness_percent', 'integer', ['null' => true])
            ->addColumn('admin_notes', 'text', ['null' => true])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
