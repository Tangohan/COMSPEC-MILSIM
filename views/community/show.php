<?php
/** @var array $tenant */
/** @var array $memberships */
/** @var array $communityConfig */
/** @var array<string, mixed>|null $communityProfile */
/** @var string $publicLayout */
$publicLayout = $publicLayout ?? 'legacy';
if ($publicLayout === 'showcase') {
    require base_path('views/community/show_showcase.php');
} else {
    require base_path('views/community/show_legacy.php');
}
