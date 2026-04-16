<?php
declare(strict_types=1);

$orbatRecruitmentHub = $orbatRecruitmentHub ?? false;
$orbatEmptyStateBackUrl = $orbatEmptyStateBackUrl ?? url('dashboard');
$orbatPageEyebrow = $orbatPageEyebrow ?? null;
$orbatPageTitle = $orbatPageTitle ?? null;
$orbatPageLead = $orbatPageLead ?? null;

require base_path('views/partials/orbat/orbat_canvas.php');
