<?php

declare(strict_types=1);

/** Historical cleanup retained as a no-op so replaying migrations cannot erase tenant work. */
return static function (PDO $pdo): void {
    echo "  unused_military_catalog_jobs_purge_v2 : purge automatique désactivée ; référentiels et attributions conservés.\n";
};
