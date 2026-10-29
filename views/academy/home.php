<?php
require __DIR__ . '/init.php';
$title = $t('brand');
$meta_description = $t('hero_text');
$content = 'academy.home_body';
$marketingActive = 'academy';
$marketingStyles = [asset_url('assets/css/academy.css')];
$marketingScripts = [asset_url('assets/js/academy.js')];
require base_path('views/layout/marketing.php');
