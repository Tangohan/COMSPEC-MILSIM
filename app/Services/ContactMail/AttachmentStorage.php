<?php

declare(strict_types=1);

namespace App\Services\ContactMail;

/**
 * Fichiers des pièces jointes de la boîte de contact : rangés sous storage/private/contact-mail (jamais dans public/),
 * sous un nom aléatoire sans rapport avec le nom d'origine. Le nom d'origine reste en base et ne sert qu'au téléchargement.
 */
final class AttachmentStorage
{
    public function __construct(private ?string $root = null) {}

    public function root(): string
    {
        return rtrim($this->root ?? \base_path('storage/private/contact-mail'), '/\\');
    }

    /**
     * @return array{path: string, sha256: string}|null null : écriture impossible
     */
    public function save(string $content): ?array
    {
        $rel = gmdate('Y/m') . '/' . bin2hex(random_bytes(16)) . '.bin';
        $abs = $this->root() . '/' . $rel;
        $dir = dirname($abs);
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            return null;
        }
        if (@file_put_contents($abs, $content, LOCK_EX) === false) {
            return null;
        }

        return ['path' => $rel, 'sha256' => hash('sha256', $content)];
    }

    /** Chemin absolu d'un fichier rangé, ou null s'il sort du dossier ou n'existe pas. */
    public function resolve(string $relative): ?string
    {
        if ($relative === '' || str_contains($relative, '..') || preg_match('~^\d{4}/\d{2}/[a-f0-9]{32}\.bin$~', $relative) !== 1) {
            return null;
        }
        $root = realpath($this->root());
        $abs = realpath($this->root() . '/' . $relative);
        if ($root === false || $abs === false || !str_starts_with($abs, $root . DIRECTORY_SEPARATOR) || !is_file($abs)) {
            return null;
        }

        return $abs;
    }

    public function delete(string $relative): void
    {
        $abs = $this->resolve($relative);
        if ($abs !== null) {
            @unlink($abs);
        }
    }
}
