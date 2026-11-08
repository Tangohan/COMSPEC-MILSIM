<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/Services/OrganizationCatalog/RealUnitKitDefinitions.php';
require dirname(__DIR__, 2) . '/app/Services/Effectifs/MissingCommandDetector.php';

use App\Services\Effectifs\MissingCommandDetector;

$unit = static fn (int $id, string $slug, ?int $parent = null): array => [
    'id' => $id, 'slug' => $slug, 'name' => $slug, 'parent_id' => $parent,
];
$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$check(MissingCommandDetector::detect([]) === [], 'Empty ORBAT');
$check(MissingCommandDetector::detect([$unit(1, 'custom')]) === [], 'Custom independent unit');
$check(MissingCommandDetector::detect([$unit(1, 'us-ussocom')]) === [], 'Top command needs no parent');
$missing = MissingCommandDetector::detect([$unit(1, 'us-usasoc'), $unit(2, 'custom', 1)]);
$check(count($missing) === 1 && str_contains($missing[0]['expected'], 'United States Special Operations Command'), 'Sub-units do not replace USSOCOM');
$check(MissingCommandDetector::detect([$unit(1, 'us-usasoc', 2), $unit(2, 'us-ussocom')]) === [], 'Complete command chain');
$check(count(MissingCommandDetector::detect([$unit(1, 'us-usasoc', 99)])) === 1, 'Missing or foreign parent');
$check(count(MissingCommandDetector::detect([$unit(1, 'us-usasoc', 2), $unit(2, 'custom', 1)])) === 1, 'Cycles terminate');
echo "7 command hierarchy checks passed.\n";
