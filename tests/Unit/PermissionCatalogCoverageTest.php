<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Authorization\TenantPermissionCatalog;
use App\Services\Rbac\AccessRightsCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Garde-fou anti-oubli : un droit testé dans le code (allows / deny / can / allowsAny, clés « permission » de la
 * navigation) doit figurer dans TenantPermissionCatalog, donc être cochable dans Effectifs > Accès. Sinon il doit être
 * déclaré, avec sa raison, dans AccessRightsCatalog::NOT_EDITABLE.
 */
final class PermissionCatalogCoverageTest extends TestCase
{
    /** @return array<string, list<string>> */
    private function usedSlugs(): array
    {
        $root = str_replace(chr(92), '/', dirname(__DIR__, 2));
        $used = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $path = str_replace(chr(92), '/', $file->getPathname());
            $rel = substr($path, strlen($root));
            if (!preg_match('#^/(app|views|routes|config|bootstrap)/.*\.php$#', $rel)) {
                continue;
            }
            $src = (string) file_get_contents($path);
            $patterns = [
                "/(?:allows|deny|can|allowsAny|allowsAll|hasPermission|has|requirePermission|denyUnless)\(\s*\[?\s*((?:'[a-z0-9_]+(?:\.[a-z0-9_*]+)+'\s*,?\s*)+)/",
                '/[\'"](?:permission|any_permissions|required_permission|permission_slug)[\'"]\s*=>\s*(\[[^\]]*\]|[\'"][a-z0-9_.*]+[\'"])/',
            ];
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $src, $m)) {
                    foreach ($m[1] as $group) {
                        preg_match_all('/[\'"]([a-z0-9_]+(?:\.[a-z0-9_*]+)+)[\'"]/', $group, $mm);
                        foreach ($mm[1] as $slug) {
                            $used[$slug][] = $rel;
                        }
                    }
                }
            }
        }

        return $used;
    }

    public function testEveryPermissionUsedInCodeIsDeclared(): void
    {
        $catalog = array_flip(TenantPermissionCatalog::allSlugs());
        $undeclared = [];
        foreach ($this->usedSlugs() as $slug => $files) {
            if (!isset($catalog[$slug]) && !isset(AccessRightsCatalog::NOT_EDITABLE[$slug])) {
                $undeclared[] = $slug . ' (' . $files[0] . ')';
            }
        }
        self::assertSame([], $undeclared, 'Droit utilisé dans le code mais absent de TenantPermissionCatalog : ajoutez-le (libellé, module, action) ou déclarez-le dans AccessRightsCatalog::NOT_EDITABLE avec sa raison.');
    }

    public function testNotEditableListNeverOverlapsCatalogAndIsExplained(): void
    {
        $catalog = array_flip(TenantPermissionCatalog::allSlugs());
        foreach (AccessRightsCatalog::NOT_EDITABLE as $slug => $why) {
            self::assertFalse(isset($catalog[$slug]), $slug . ' est dans le catalogue : retirez-le de NOT_EDITABLE');
            self::assertTrue(trim($why) !== '', $slug . ' sans raison');
        }
    }

    public function testCatalogSlugsAreUniqueAndNamed(): void
    {
        $seen = [];
        foreach (TenantPermissionCatalog::definitions() as $d) {
            self::assertFalse(isset($seen[$d['slug']]), 'doublon ' . $d['slug']);
            $seen[$d['slug']] = true;
            self::assertTrue(trim($d['name']) !== '', $d['slug'] . ' sans libellé');
        }
    }
}
