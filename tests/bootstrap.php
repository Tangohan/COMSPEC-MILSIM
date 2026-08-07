<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

// PHPUnit 11 ne peut pas mocker les classes `final` : on lève la restriction en tests.
if (class_exists(\DG\BypassFinals::class)) {
    \DG\BypassFinals::enable();
}
