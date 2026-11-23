<?php

declare(strict_types=1);

namespace App\Services\Modpack;

/**
 * Archive ZIP (sans compression : les PBO sont déjà denses) des mods cochés, construite dans un fichier temporaire
 * que l'appelant supprime après l'envoi. Seuls les identifiants du manifeste sont acceptés.
 */
final class ModpackBundle
{
    public const MAX_BYTES = 536870912;

    public function __construct(private readonly ModpackItems $items, private readonly int $maxBytes = self::MAX_BYTES)
    {
    }

    /**
     * @param list<mixed> $ids identifiants demandés
     * @return array{path: string, name: string, bytes: int} archive temporaire et nom de téléchargement
     * @throws \InvalidArgumentException identifiant inconnu, mod non téléchargeable ou sélection vide
     * @throws \RuntimeException plafond dépassé ou écriture impossible
     */
    public function build(array $ids, ?string $directory = null): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $item = is_string($id) ? $this->items->find($id) : null;
            if ($item === null) {
                throw new \InvalidArgumentException('unknown_item');
            }
            if (!$this->items->isDownloadable($item)) {
                throw new \InvalidArgumentException('unavailable_item');
            }
            $wanted[$item['id']] = $item;
        }
        if ($wanted === []) {
            throw new \InvalidArgumentException('empty_selection');
        }
        $total = 0;
        foreach ($wanted as $item) {
            $total += ModpackItems::bytes($item);
        }
        if ($total > $this->maxBytes) {
            throw new \RuntimeException('too_large');
        }
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('zip_unavailable');
        }

        $order = array_flip(array_keys($this->items->all()));
        uasort($wanted, static fn (array $a, array $b): int => $order[$a['id']] <=> $order[$b['id']]);

        $tmp = @tempnam($directory ?? sys_get_temp_dir(), 'mpk');
        $zip = new \ZipArchive();
        if ($tmp === false || $zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('zip_write');
        }
        $ok = true;
        foreach ($wanted as $item) {
            foreach ($item['files'] as $file) {
                $entry = $item['folder'] . '/' . $file['path'];
                $ok = $ok && $zip->addFile((string) $this->items->filePath($item, $file), $entry)
                    && $zip->setCompressionName($entry, \ZipArchive::CM_STORE);
            }
        }
        if (!$ok || !$zip->close()) {
            @unlink($tmp);
            throw new \RuntimeException('zip_write');
        }

        $single = count($wanted) === 1 ? reset($wanted) : null;
        $name = $single === null
            ? 'COMSPEC-Selection.zip'
            : 'COMSPEC-' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $single['id'] . ($single['version'] !== null ? '-' . $single['version'] : '')) . '.zip';
        return ['path' => $tmp, 'name' => $name, 'bytes' => (int) filesize($tmp)];
    }
}
