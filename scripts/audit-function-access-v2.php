<?php

declare(strict_types=1);

/** Phase 1 exporter. Usage: php scripts/audit-function-access-v2.php [output-directory]. */
require dirname(__DIR__) . '/bootstrap/autoload.php';

$root = dirname(__DIR__);
$out = $argv[1] ?? ($root . '/var/access-control');
if (!is_dir($out) && !mkdir($out, 0775, true) && !is_dir($out)) {
    throw new RuntimeException('Cannot create output directory: ' . $out);
}
$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    $path = $file->getPathname();
    $relative = substr($path, strlen($root) + 1);
    if (!$file->isFile() || !str_ends_with($path, '.php') || preg_match('~^(vendor|node_modules|server/node_modules|storage)/~', $relative)) { continue; }
    $files[$relative] = (string) file_get_contents($path);
}

$functions = [];
$dangerous = [];
$permissionPattern = "~(?:allows|deny|can|hasPermission|requirePermission)\\s*\\(\\s*['\"]([^'\"]+)['\"]~";
$dangerPattern = '~\\b(isAdmin|isSuperUser|site_super_admin|super_admin|admin\\.system|site\\.support|platform\\.|(?:permission|slug)\\s*[!=]==?\\s*[\'\"]\\*[\'\"])~i';
foreach ($files as $path => $source) {
    $lines = preg_split('/\R/', $source) ?: [];
    foreach ($lines as $index => $line) {
        if (preg_match_all($permissionPattern, $line, $matches)) {
            foreach ($matches[1] as $slug) {
                $functions[$slug]['name'] = $slug;
                $functions[$slug]['origins'][] = ['file' => $path, 'line' => $index + 1, 'expression' => trim($line)];
            }
        }
        if (preg_match($dangerPattern, $line, $match)) {
            $dangerous[] = ['pattern' => $match[0], 'file' => $path, 'line' => $index + 1, 'expression' => trim($line)];
        }
    }
}
ksort($functions);
$known = array_fill_keys(App\Authorization\TenantPermissionCatalog::allSlugs(), true);
foreach ($functions as $slug => &$item) {
    $item['catalogued'] = isset($known[$slug]);
    $item['status'] = isset($known[$slug]) ? 'active' : 'orphan_or_inconsistent';
}
unset($item);
$report = [
    'generated_at' => gmdate(DATE_ATOM),
    'scope' => 'static PHP permission checks',
    'functions' => array_values($functions),
    'dangerous_patterns' => $dangerous,
    'duplicate_slugs' => array_values(array_map(static fn(array $x): string => $x['name'], array_filter($functions, static fn(array $x): bool => count($x['origins']) > 1))),
];
file_put_contents($out . '/functions-existing.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

// DB export is optional for CI, mandatory before production migration.
$dsn = getenv('DB_DSN') ?: '';
if ($dsn !== '') {
    $pdo = new PDO($dsn, getenv('DB_USER') ?: '', getenv('DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $roles = $pdo->query("SELECT r.id,r.tenant_id,r.name,r.slug,r.created_at,
      CASE WHEN r.slug IN ('member','community_owner','tenant_admin','deputy_commander','trainer','instructor','senior_instructor','recruiter','hr','super_admin','site_super_admin') THEN 'code' ELSE 'dynamic' END origin,
      COALESCE((SELECT JSON_ARRAYAGG(p.slug) FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=r.id),JSON_ARRAY()) permissions,
      (SELECT COUNT(DISTINCT ur.user_id) FROM user_roles ur WHERE ur.role_id=r.id) assigned_users
      FROM roles r ORDER BY r.tenant_id,r.id")->fetchAll(PDO::FETCH_ASSOC);
    file_put_contents($out . '/roles-existing.json', json_encode(['generated_at' => gmdate(DATE_ATOM), 'roles' => $roles], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    $csv = fopen($out . '/roles-existing.csv', 'wb');
    fputcsv($csv, ['id','tenant_id','name','slug','created_at','origin','permissions','assigned_users']);
    foreach ($roles as $role) { fputcsv($csv, $role); }
    fclose($csv);
}
echo "Audit written to {$out}\n";
