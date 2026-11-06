<?php
// Standalone integration harness: no production credentials, no external DB or Composer required.
$repoRoot=dirname(__DIR__,2);
spl_autoload_register(static function(string $class) use ($repoRoot): void {
    if (str_starts_with($class,'App\\')) { $f=$repoRoot.'/app/'.str_replace('\\','/',substr($class,4)).'.php'; if(is_file($f)) require_once $f; }
});
if (!function_exists('base_path')) { function base_path(string $p=''): string { return (str_starts_with($p,'storage')?($GLOBALS['patch_test_root']??dirname(__DIR__,2)):dirname(__DIR__,2)).($p!==''?'/'.$p:''); } }
if (!function_exists('env')) { function env(string $k,mixed $v=null): mixed { return getenv($k)?:$v; } }
if (!function_exists('url')) { function url(string $p=''): string { return '/'.ltrim($p,'/'); } }
if (!function_exists('asset_url')) { function asset_url(string $p): string { return url($p); } }
function patchTestDb(string $dsn='sqlite::memory:'): PDO {
    $pdo=new PDO($dsn);$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
    $sql=file_get_contents(dirname(__DIR__,2).'/migrations/2026_11_06_patch_studio.sql');
    $sql=preg_replace('/,?\s*(?:INDEX|UNIQUE KEY) [^\n]+/','',$sql);
    $sql=preg_replace('/\) ENGINE=InnoDB[^;]+;/i',');',$sql);
    $pdo->exec($sql);
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INT PRIMARY KEY,tenant_id INT,status TEXT)");
    $pdo->exec("INSERT OR IGNORE INTO users VALUES (1,7,'active'),(2,7,'active'),(3,8,'active')");
    return $pdo;
}
