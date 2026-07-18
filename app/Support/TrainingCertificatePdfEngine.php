<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Détection et chargement du moteur PDF des attestations (TCPDF embarqué ou Dompdf Composer).
 */
final class TrainingCertificatePdfEngine
{
    private static bool $tcpdfLoadAttempted = false;

    /**
     * Familles Unicode utilisables pour les attestations (régulier + gras).
     * freesans est préférée : elle est intégralement versionnée dans le dépôt (fichiers .php + .z
     * de taille normale), donc fiable sur tous les environnements de déploiement.
     * dejavusans reste un second choix si un jour elle est correctement déployée, mais elle a
     * historiquement manqué (dejavusans.z absent ou tronqué sur FTP / clones Git incomplets) —
     * on ne doit donc jamais en dépendre pour que la génération fonctionne.
     *
     * @var list<string>
     */
    private const CERTIFICATE_FONT_FAMILIES = ['freesans', 'dejavusans'];

    public static function isAvailable(): bool
    {
        if (self::ensureTcpdfLoaded() && self::tcpdfCertificateFontsReady()) {
            return true;
        }

        return class_exists(\Dompdf\Dompdf::class);
    }

    public static function prefersTcpdf(): bool
    {
        return self::ensureTcpdfLoaded() && self::tcpdfCertificateFontsReady();
    }

    /**
     * Polices Unicode utilisées par TrainingCertificatePdfService (régulier + gras).
     */
    public static function tcpdfCertificateFontsReady(): bool
    {
        return self::resolveCertificateFontFamily() !== null;
    }

    /**
     * Première famille Unicode complète (php + .z régulier et gras).
     */
    public static function resolveCertificateFontFamily(): ?string
    {
        $dir = self::resolveTcpdfFontsDir();
        if ($dir === null) {
            return null;
        }

        foreach (self::CERTIFICATE_FONT_FAMILIES as $family) {
            if (self::fontFamilyFilesReady($dir, $family)) {
                return $family;
            }
        }

        return null;
    }

    /**
     * Message métier actionnable pour le staff (null si tout est prêt pour générer).
     */
    public static function staffUnavailabilityHint(): ?string
    {
        if (self::isAvailable()) {
            return null;
        }

        $tcpdfOk = self::ensureTcpdfLoaded();
        $fontsOk = self::tcpdfCertificateFontsReady();
        $dompdfOk = class_exists(\Dompdf\Dompdf::class);

        if ($tcpdfOk && !$fontsOk) {
            $missing = self::listMissingCertificateFontFiles();
            $detail = $missing !== []
                ? ' Élément manquant côté serveur : ' . implode(', ', $missing) . '.'
                : '';

            return 'La génération des attestations PDF n’est pas prête : la bibliothèque est présente,'
                . ' mais les polices nécessaires au document (accents inclus) sont incomplètes.'
                . $detail
                . ' Demandez à l’équipe technique de redéployer le dossier des polices TCPDF'
                . ' (au minimum la famille freesans, suffisante pour générer les documents),'
                . ' ou d’installer Dompdf via Composer.';
        }

        if (!$tcpdfOk && !$dompdfOk) {
            return 'La génération des attestations PDF n’est pas prête sur ce serveur.'
                . ' Contactez l’équipe technique pour déployer TCPDF (avec ses polices Unicode)'
                . ' ou Dompdf.';
        }

        return 'La génération des attestations PDF n’est pas prête sur ce serveur.'
            . ' Contactez l’équipe technique pour vérifier la bibliothèque PDF et les polices associées.';
    }

    /**
     * Message métier lorsque isAvailable() est vrai mais qu’une génération vient d’échouer.
     */
    public static function staffGenerationFailureHint(?string $detail = null): string
    {
        $detail = $detail !== null ? trim($detail) : '';
        if ($detail !== '') {
            return 'La génération du document a échoué. ' . $detail;
        }

        $parts = [
            'Le moteur PDF est détecté, mais la génération du document a échoué.',
        ];

        $family = self::resolveCertificateFontFamily();
        if (self::ensureTcpdfLoaded() && $family === null) {
            $missing = self::listMissingCertificateFontFiles();
            if ($missing !== []) {
                $parts[] = 'Polices TCPDF incomplètes (' . implode(', ', $missing) . ').'
                    . ' Un repli Dompdf a peut‑être aussi échoué.';
            }
        } elseif ($family !== null) {
            if (!self::isCacheWritable()) {
                $parts[] = 'Le dossier temporaire PDF n’est pas accessible en écriture sur le serveur.';
            } else {
                $parts[] = 'Vérifiez l’espace disque, les droits d’écriture du stockage des attestations,'
                    . ' et les images du gabarit (logo / fond : JPEG ou PNG recommandés).';
            }
        } else {
            $parts[] = 'Réessayez plus tard ; si le problème continue, contactez l’équipe technique'
                . ' (espace disque, polices ou gabarit).';
        }

        return implode(' ', $parts);
    }

    /**
     * @return list<string> noms de fichiers manquants ou illisibles (pour logs / hint staff)
     */
    public static function listMissingCertificateFontFiles(): array
    {
        $dir = self::resolveTcpdfFontsDir();
        if ($dir === null) {
            return ['dossier fonts introuvable'];
        }

        $missing = [];
        foreach (self::CERTIFICATE_FONT_FAMILIES as $family) {
            foreach (self::fontFamilyRequiredFiles($family) as $file) {
                $path = $dir . DIRECTORY_SEPARATOR . $file;
                if (!is_file($path) || !is_readable($path) || filesize($path) < 1024) {
                    $missing[] = $file;
                }
            }
        }

        return array_values(array_unique($missing));
    }

    public static function ensureTcpdfLoaded(): bool
    {
        if (class_exists(\TCPDF::class, false)) {
            return true;
        }
        if (self::$tcpdfLoadAttempted) {
            return false;
        }
        self::$tcpdfLoadAttempted = true;

        $path = self::resolveTcpdfBootstrapPath();
        if ($path === null) {
            return false;
        }

        // Cache dédié et contrôlé : sur certains hébergeurs, upload_tmp_dir / sys_get_temp_dir
        // n’est pas inscriptible → TCPDF Error() sur PNG alpha détruit le document.
        self::ensureWritableCacheDefined();

        try {
            self::suppressTcpdfPhpDeprecationsWhile(static function () use ($path): void {
                require_once $path;
            });
        } catch (\Throwable) {
            return false;
        }

        return class_exists(\TCPDF::class, false);
    }

    /**
     * Définit K_PATH_CACHE vers storage/app/tcpdf-cache avant le chargement de TCPDF.
     */
    public static function ensureWritableCacheDefined(): void
    {
        if (defined('K_PATH_CACHE')) {
            return;
        }
        $dir = base_path('storage/app/tcpdf-cache');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }
        if (!is_writable($dir)) {
            @chmod($dir, 0775);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            return;
        }
        $normalized = str_replace('\\', '/', $dir);
        define('K_PATH_CACHE', rtrim($normalized, '/') . '/');
    }

    /**
     * Indique si le cache TCPDF est utilisable (écriture temporaire images / masques).
     */
    public static function isCacheWritable(): bool
    {
        self::ensureWritableCacheDefined();
        if (!defined('K_PATH_CACHE')) {
            return false;
        }
        $dir = rtrim((string) K_PATH_CACHE, "/\\");

        return $dir !== '' && is_dir($dir) && is_writable($dir);
    }

    /**
     * Chemins possibles pour tcpdf.php (copie locale ou package Composer).
     */
    public static function resolveTcpdfBootstrapPath(): ?string
    {
        $candidates = [
            base_path('tcpdf/tcpdf.php'),
            base_path('vendor/tecnickcom/tcpdf/tcpdf.php'),
            base_path('lib/tcpdf/tcpdf.php'),
        ];

        foreach ($candidates as $candidate) {
            if (!is_file($candidate) || !is_readable($candidate)) {
                continue;
            }
            $real = realpath($candidate);

            return is_string($real) && $real !== '' ? $real : $candidate;
        }

        return null;
    }

    public static function resolveTcpdfFontsDir(): ?string
    {
        $bootstrap = self::resolveTcpdfBootstrapPath();
        if ($bootstrap !== null) {
            $dir = dirname($bootstrap) . DIRECTORY_SEPARATOR . 'fonts';
            if (is_dir($dir)) {
                $real = realpath($dir);

                return is_string($real) && $real !== '' ? $real : $dir;
            }
        }

        $fallback = base_path('tcpdf/fonts');
        if (is_dir($fallback)) {
            $real = realpath($fallback);

            return is_string($real) && $real !== '' ? $real : $fallback;
        }

        return null;
    }

    /**
     * Exécute du code qui utilise TCPDF sans laisser les avertissements E_DEPRECATED
     * polluer la sortie (PHP 8+ : anciennes signatures TCPDF 6.3 si le déploiement n’a pas les fichiers corrigés).
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function suppressTcpdfPhpDeprecationsWhile(callable $callback): mixed
    {
        $level = error_reporting();
        error_reporting($level & ~E_DEPRECATED);
        try {
            return $callback();
        } finally {
            error_reporting($level);
        }
    }

    /**
     * @return list<string>
     */
    private static function fontFamilyRequiredFiles(string $family): array
    {
        return [
            $family . '.php',
            $family . '.z',
            $family . 'b.php',
            $family . 'b.z',
        ];
    }

    private static function fontFamilyFilesReady(string $dir, string $family): bool
    {
        foreach (self::fontFamilyRequiredFiles($family) as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (!is_file($path) || !is_readable($path) || filesize($path) < 1024) {
                return false;
            }
        }

        return true;
    }
}
