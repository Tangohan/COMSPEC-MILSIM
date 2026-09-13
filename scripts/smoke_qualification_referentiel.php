<?php

declare(strict_types=1);

$root = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($root): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $path = $root . '/' . str_replace('\\', '/', $class) . '.php';
    // App\Foo -> app/Foo.php
    $path = $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

use App\Services\Personnel\QualificationPermissionGrantService;
use App\Services\Personnel\QualificationTemporalStatusService;
use App\Support\QualificationAdminStatus;

$failures = 0;
$assert = static function (bool $cond, string $msg) use (&$failures): void {
    if (!$cond) {
        echo "FAIL: {$msg}\n";
        $failures++;
    } else {
        echo "OK: {$msg}\n";
    }
};

$assert(QualificationAdminStatus::label('obtained') === 'Obtenue', 'label obtained');
$assert(QualificationAdminStatus::canTransition('obtained', 'revoked'), 'transition revoke');
$assert(!QualificationAdminStatus::canTransition('revoked', 'obtained'), 'no revive');

$svc = new QualificationTemporalStatusService();
$now = new DateTimeImmutable('2026-09-13');
$assert(
    $svc->resolve(['admin_status' => 'obtained', 'expires_at' => '2026-09-20', 'alert_before_expiry_days' => 30], $now)['code']
        === QualificationTemporalStatusService::EXPIRING_SOON,
    'expiring soon'
);
$assert(
    $svc->resolve(['admin_status' => 'obtained', 'expires_at' => '2026-09-10', 'grace_period_days' => 7], $now)['code']
        === QualificationTemporalStatusService::EXPIRED_GRACE,
    'grace'
);

$ref = new ReflectionClass(QualificationPermissionGrantService::class);
$perm = $ref->newInstanceWithoutConstructor();
$assert(!$perm->isPermissionAllowed('admin.roles.manage'), 'block admin');
$assert($perm->isPermissionAllowed('trainings.create'), 'allow training');

$assert(is_file($root . '/views/admin/organization/qualifications/certificates/layout_classique.php'), 'layout classique');
$assert(is_file($root . '/views/admin/organization/qualifications/certificates/layout_moderne.php'), 'layout moderne');
$assert(is_file($root . '/public/assets/img/qualification-badge-default.svg'), 'badge default');
$assert(is_file($root . '/docs/technique/qualification-certificate-templates/template_classique_vierge.pdf'), 'pdf classique');
$assert(is_file($root . '/bootstrap/qualification_referentiel_migration.php'), 'migration');

exit($failures > 0 ? 1 : 0);
