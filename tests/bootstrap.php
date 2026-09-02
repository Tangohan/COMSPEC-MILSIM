<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

// PHPUnit 11 ne peut pas mocker les classes `final` : on lève la restriction en tests.
// Ne pas muter vendor/phpunit : retirer `readonly` sur TestStatus\Known casse Warning (fatal).
if (class_exists(\DG\BypassFinals::class)) {
    \DG\BypassFinals::denyPaths([
        '*/vendor/phpunit/*',
    ]);
    \DG\BypassFinals::enable(bypassReadOnly: false);
}
