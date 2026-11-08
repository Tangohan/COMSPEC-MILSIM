<?php

declare(strict_types=1);
namespace App\Services\PatchStudio;

use App\Support\Migrations\SchemaInspector;
use PDO;
use PDOException;
use Throwable;

/** Explicit active migration only; never creates or alters tables on a normal HTTP request. */
final class Schema
{
    public static function migrate(PDO $pdo, string $root): void
    {
        $file=$root.'/migrations/2026_11_06_patch_studio.sql';
        $inspector=new SchemaInspector($pdo);
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite') {
            $sql=(string)file_get_contents($file);
            $sql=preg_replace('/,?\s*(?:INDEX|UNIQUE KEY) [^\n]+/','',$sql);
            $sql=preg_replace('/\) ENGINE=InnoDB[^;]+;/i',');',$sql);
            $pdo->exec($sql);
        } else $inspector->runSqlFile($file);
        $inspector->addColumnIfMissing('patch_studio_projects','kind',"VARCHAR(16) NOT NULL DEFAULT 'patch'");
        $inspector->addColumnIfMissing('patch_studio_projects','preview_hash','CHAR(64) DEFAULT NULL');
        $inspector->addColumnIfMissing('patch_studio_projects','preview_revision','INT DEFAULT NULL');
        $inspector->addIndexIfMissing('patch_studio_projects','ps_project_home',['tenant_id','owner_id','kind','updated_at']);
    }
    public static function missing(Throwable $e): bool
    {
        if (!$e instanceof PDOException) return false;
        $message=strtolower($e->getMessage());
        $missing=in_array((string)$e->getCode(),['42S02','42S22'],true)||str_contains($message,'no such table')||str_contains($message,'no such column');
        return $missing && (str_contains($message,'patch_studio')||str_contains($message,'p.kind')||str_contains($message,'preview_hash')||str_contains($message,'preview_revision'));
    }
}
