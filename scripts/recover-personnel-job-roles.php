<?php

declare(strict_types=1);

// Explicit command only. Never executed by the migrations pipeline or web bootstrap.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap/autoload.php';
$options=getopt('', ['tenant::','labels::','apply','actor::','help']);
if (isset($options['help'])) {
    echo "Récupération des emplois depuis les traces conservées, sans droits d’accès.\n"
        . "php scripts/recover-personnel-job-roles.php [--tenant=14] [--labels=CNOS,RFL] [--apply] [--actor=ID]\n"
        . "Sans --apply : lecture seule, aucune table créée. Contrôler les preuves avant application.\n";
    exit(0);
}
$tenant=(int)($options['tenant']??14);
$labels=isset($options['labels'])?array_values(array_filter(array_map('trim',explode(',',(string)$options['labels'])))):[];
try {
    $service=new \App\Services\Personnel\PersonnelJobRoleRecoveryService(\App\Core\Database::getPdo());
    $report=isset($options['apply'])?$service->apply($tenant,$labels,isset($options['actor'])?(int)$options['actor']:null):$service->preview($tenant,$labels);
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $e) {
    fwrite(STDERR,'Récupération impossible : '.$e->getMessage()."\n");exit(1);
}
