<?php

declare(strict_types=1);

use App\Services\Community\TenantDefaultRoleDefinitions;
use Phinx\Migration\AbstractMigration;

final class BackfillRolesLabelEn extends AbstractMigration
{
    public function up(): void
    {
        $conn = $this->getAdapter()->getConnection();
        if (!$conn instanceof \PDO) {
            return;
        }
        TenantDefaultRoleDefinitions::applyCanonicalEnglishLabels($conn, null);
    }
}
