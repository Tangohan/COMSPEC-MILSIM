<?php
$stats = $stats ?? ['courses' => 0, 'enrollments' => 0, 'completed' => 0, 'expiringCount' => 0];
$expiring = $expiring ?? [];
$trainingCanExportFull = !empty($trainingCanExportFull);
require base_path('views/admin/training/partials/command_shell_open.php');
require base_path('views/admin/training/dashboard_body.php');
require base_path('views/admin/training/partials/command_shell_close.php');
