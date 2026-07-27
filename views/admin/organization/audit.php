<?php
declare(strict_types=1);

if (!empty($isBackOfficeShell)) {
    require base_path('views/partials/ath_audit_log.php');
    return;
}
require base_path('views/admin/partials/audit_log_page.php');
