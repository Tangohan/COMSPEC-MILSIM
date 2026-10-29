<?php

declare(strict_types=1);

/** Historical cleanup: preserve organisation-created roles, their grants and member assignments. */
return static function (PDO $pdo): void {
    echo "  community_access_roles_purge_v1 : purge automatique désactivée ; rôles et emplois conservés.\n";
};
