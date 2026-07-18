<?php
declare(strict_types=1);

/**
 * Entrée navbar dashboard — délègue à la navbar Athena (style Caverne).
 * L’aside tuiles (`dashboard_aside.php`) et les modals formations restent inchangés.
 */
$athena_header_section = $athena_header_section ?? 'Tableau de bord';
$athena_header_current = $athena_header_current ?? 'dashboard';
require base_path('views/partials/athena_caverne_header.php');
require base_path('views/partials/navbar_info_banners.php');
