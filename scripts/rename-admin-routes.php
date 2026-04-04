<?php
declare(strict_types=1);

$roots = [__DIR__ . '/../app', __DIR__ . '/../views'];
$skip = ['routes/web.php'];

foreach ($roots as $root) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($it as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        foreach ($skip as $s) {
            if (str_ends_with(str_replace('\\', '/', $path), $s)) {
                continue 2;
            }
        }
        $c = file_get_contents($path);
        $orig = $c;
        $c = str_replace('admin/organization', 'back-office', $c);
        $c = str_replace('admin/system/', 'admin/', $c);
        $c = str_replace("url('admin/system')", "url('admin')", $c);
        $c = str_replace('url("admin/system")', 'url("admin")', $c);
        if ($c !== $orig) {
            file_put_contents($path, $c);
            echo $path, "\n";
        }
    }
}
echo "OK\n";
