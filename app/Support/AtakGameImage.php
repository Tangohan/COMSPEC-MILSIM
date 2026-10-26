<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Copie .paa (texture Arma) des images du site que le téléphone en jeu (ATAK natif) télécharge :
 *   - portrait opérateur        (FIT_PORTRAIT : carré 256 px, recadré vers le haut, comme la vignette JPEG)
 *   - diapositives de briefing  (FIT_SLIDE : 2048 x 1024, image entière centrée sur fond noir)
 *   - photos des avis de recherche SSE et des fiches FRS / FRM (FIT_PHOTO : 1024 px sur le grand côté)
 *
 * Arma n'affiche que le JPEG et le PAA, et lit mal ce qui n'a pas des côtés en puissances de 2 : un PNG ou un WebP
 * envoyé sur le site laissait une zone noire dans le téléphone. La copie est faite à l'envoi (prepare(), appelé par
 * les dépôts qui enregistrent ces images) et, pour les images envoyées avant, à la première demande du jeu.
 *
 * Rangement : à côté de l'envoi, uploads/<dossier>/atak/<nom>.paa, dans public/ sinon storage/ (servi à la même
 * adresse par le repli de public/index.php), une seule fois : chaque nouvel envoi porte un nouveau nom. Une copie
 * JPEG légère <nom>.jpg l'accompagne (webRel) : le téléphone l'affiche dans son navigateur intégré quand Arma
 * refuse la texture téléchargée.
 *
 * Le .paa n'est servi qu'à COMSPEC Link 2.0.95+, qui l'annonce par l'en-tête « X-COMSPEC-Image: paa » : les DLL plus
 * anciennes refusent tout ce qui n'est pas JPEG ou PNG et gardent les adresses JPEG d'avant.
 */
final class AtakGameImage
{
    public const FIT_PORTRAIT = 'portrait';

    public const FIT_SLIDE = 'slide';

    public const FIT_PHOTO = 'photo';

    /** Temps de conversion maximal par réponse d'API (les images suivantes seront converties à la demande suivante). */
    public const REQUEST_BUDGET_SECONDS = 4.0;

    /** Raison du dernier échec : source_missing, no_gd, unreadable, write_failed (vide : copie servie). */
    public static string $lastError = '';

    private static float $spent = 0.0;

    /** COMSPEC Link annonce qu'elle sait ranger un .paa (en-tête X-COMSPEC-Image). */
    public static function clientAcceptsPaa(): bool
    {
        $v = strtolower((string) ($_SERVER['HTTP_X_COMSPEC_IMAGE'] ?? ''));

        return $v !== '' && in_array('paa', array_map('trim', explode(',', $v)), true);
    }

    /**
     * Chemin relatif « uploads/… » d'une image enregistrée (relatif, /uploads/…, chemin serveur ou adresse complète),
     * null si ce n'est pas un envoi du site.
     */
    public static function uploadsRel(string $pathOrUrl): ?string
    {
        $p = str_replace('\\', '/', trim($pathOrUrl));
        if ($p === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $p) === 1) {
            $p = (string) parse_url($p, PHP_URL_PATH);
        }
        if (preg_match('#(?:^|/)(uploads/[^?\#]+)$#', $p, $m) !== 1 || str_contains($m[1], '..')) {
            return null;
        }

        return $m[1];
    }

    /** Copie .paa attendue pour un envoi (uploads/x/nom.jpg → uploads/x/atak/nom.paa). */
    public static function paaRel(string $uploadsRel): string
    {
        $dir = dirname($uploadsRel);
        // Une vignette déjà rangée dans atak/ (portrait JPEG du jeu) garde son dossier.
        $dir = basename($dir) === 'atak' ? $dir : $dir . '/atak';

        return $dir . '/' . pathinfo($uploadsRel, PATHINFO_FILENAME) . '.paa';
    }

    /**
     * Fait la copie .paa d'un envoi si elle manque (appel à l'enregistrement : sans effet si le fichier n'est pas
     * là ou si la conversion échoue, l'envoi lui-même n'en dépend jamais).
     */
    public static function prepare(?string $pathOrUrl, string $fit): ?string
    {
        try {
            $rel = self::uploadsRel((string) $pathOrUrl);

            return $rel === null ? null : self::ensure($rel, $fit, false);
        } catch (\Throwable $e) {
            error_log('[atak/paa] conversion impossible : ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Adresse publique de la copie .paa pour l'adresse d'origine $url (même hôte, même préfixe), à servir au jeu.
     * Null si la copie n'existe pas et ne peut pas être faite maintenant (budget de la requête dépassé compris) :
     * l'appelant garde alors son adresse JPEG.
     */
    public static function urlFor(string $url, string $fit): ?string
    {
        try {
            $rel = self::uploadsRel($url);
            if ($rel === null) {
                return null;
            }
            $paa = self::ensure($rel, $fit, true);
            if ($paa === null) {
                return null;
            }
            $pos = strpos($url, $rel);

            return $pos === false ? null : substr($url, 0, $pos) . $paa;
        } catch (\Throwable $e) {
            error_log('[atak/paa] conversion impossible : ' . $e->getMessage());

            return null;
        }
    }

    /** Dossiers où chercher l'envoi et ranger la copie. @return list<string> */
    private static function bases(): array
    {
        $root = function_exists('base_path') ? (string) base_path() : '';

        return $root === '' ? [] : [$root . '/public', $root . '/storage'];
    }

    /**
     * Copie JPEG légère rangée à côté du .paa (même nom, .jpg) : le téléphone en jeu l'affiche dans un navigateur
     * intégré quand Arma refuse la texture téléchargée (hors d'un PBO, sans -filePatching), d'où les diapositives noires.
     */
    public static function webRel(string $paaRel): string
    {
        return substr($paaRel, 0, -4) . '.jpg';
    }

    private static function ensure(string $rel, string $fit, bool $budgeted): ?string
    {
        self::$lastError = '';
        $bases = self::bases();
        $paaRel = self::paaRel($rel);
        $webRel = self::webRel($paaRel);
        $has = static function (string $r) use ($bases): bool {
            foreach ($bases as $base) {
                // Une copie vide (écriture interrompue) est refaite.
                if (is_file($base . '/' . $r) && (int) @filesize($base . '/' . $r) > 0) {
                    return true;
                }
            }

            return false;
        };
        $paaOk = $has($paaRel);
        if ($paaOk && $has($webRel)) {
            return $paaRel;
        }
        $src = null;
        foreach ($bases as $base) {
            if (is_file($base . '/' . $rel)) {
                $src = $base . '/' . $rel;
                break;
            }
        }
        if ($src === null) {
            self::$lastError = 'source_missing';

            return $paaOk ? $paaRel : null;
        }
        if ($budgeted && self::$spent >= self::REQUEST_BUDGET_SECONDS) {
            return $paaOk ? $paaRel : null;
        }
        $t = microtime(true);
        $fitted = self::render($src, $fit);
        $paa = null;
        $jpg = null;
        if ($fitted !== null) {
            try {
                $paa = $paaOk ? null : PaaEncoder::encode($fitted);
                $jpg = self::webJpeg($fitted);
            } finally {
                imagedestroy($fitted);
            }
        }
        self::$spent += microtime(true) - $t;
        if ($jpg !== null) {
            self::write($bases, $webRel, $jpg);
        }
        if ($paaOk) {
            return $paaRel;
        }
        if ($paa === null) {
            error_log('[atak/paa] copie .paa impossible (' . self::$lastError . ') : ' . $rel);

            return null;
        }
        if (self::write($bases, $paaRel, $paa)) {
            return $paaRel;
        }
        self::$lastError = 'write_failed';
        error_log('[atak/paa] copie .paa impossible à écrire : ' . $paaRel);

        return null;
    }

    /** Écrit $bytes sous le premier dossier inscriptible (à côté puis renommage : jamais de fichier à moitié écrit). */
    private static function write(array $bases, string $rel, string $bytes): bool
    {
        foreach ($bases as $base) {
            $dest = $base . '/' . $rel;
            $dir = dirname($dest);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $tmp = $dest . '.part';
            if (is_dir($dir) && is_writable($dir) && @file_put_contents($tmp, $bytes) === strlen($bytes) && @rename($tmp, $dest)) {
                return true;
            }
            @unlink($tmp);
        }

        return false;
    }

    /** Image du jeu en JPEG léger pour le navigateur intégré : 1600 px de large au plus, fond noir sous la transparence. */
    public static function webJpeg(\GdImage $fitted): ?string
    {
        $w = imagesx($fitted);
        $h = imagesy($fitted);
        $scale = min(1.0, 1600 / max($w, 1));
        $tw = max(1, (int) round($w * $scale));
        $th = max(1, (int) round($h * $scale));
        $out = imagecreatetruecolor($tw, $th);
        imagefill($out, 0, 0, imagecolorallocate($out, 0, 0, 0));
        imagealphablending($out, true);
        imagecopyresampled($out, $fitted, 0, 0, 0, 0, $tw, $th, $w, $h);
        ob_start();
        $ok = imagejpeg($out, null, 82);
        $bytes = (string) ob_get_clean();
        imagedestroy($out);

        return $ok && $bytes !== '' ? $bytes : null;
    }

    /** Fichier image vers image GD au cadrage $fit (null si illisible, raison dans $lastError). */
    private static function render(string $src, string $fit): ?\GdImage
    {
        if (!function_exists('imagecreatefromstring')) {
            self::$lastError = 'no_gd';

            return null;
        }
        $bin = @file_get_contents($src);
        $im = $bin !== false && $bin !== '' ? @imagecreatefromstring($bin) : false;
        unset($bin);
        if ($im === false || imagesx($im) < 1 || imagesy($im) < 1) {
            self::$lastError = 'unreadable';

            return null;
        }
        $out = self::fit($im, $fit);
        imagedestroy($im);

        return $out;
    }

    /** Fichier image (JPEG, PNG, WebP, GIF…) vers octets .paa, au cadrage $fit. */
    public static function convert(string $src, string $fit): ?string
    {
        $out = self::render($src, $fit);
        if ($out === null) {
            return null;
        }
        try {
            return PaaEncoder::encode($out);
        } finally {
            imagedestroy($out);
        }
    }

    /** Image remise aux dimensions du jeu (côtés en puissances de 2). */
    public static function fit(\GdImage $im, string $fit): \GdImage
    {
        if (!imageistruecolor($im)) {
            imagepalettetotruecolor($im);
        }
        $w = imagesx($im);
        $h = imagesy($im);
        if ($fit === self::FIT_PORTRAIT) {
            // Comme la vignette JPEG : carré centré en largeur, au quart supérieur en hauteur (le visage).
            $side = min($w, $h);
            $out = self::canvas(256, 256, false);
            imagecopyresampled($out, $im, 0, 0, (int) floor(($w - $side) / 2), (int) floor(($h - $side) * 0.25), 256, 256, $side, $side);

            return $out;
        }
        if ($fit === self::FIT_SLIDE) {
            [$cw, $ch] = [2048, 1024];
        } else {
            // Photo : 1024 px sur le grand côté, l'autre à la puissance de 2 la plus proche du rapport d'image.
            $long = 1024;
            $short = PaaEncoder::nearestPowerOfTwo((int) round($long * min($w, $h) / max($w, $h)), 128, $long);
            [$cw, $ch] = $w >= $h ? [$long, $short] : [$short, $long];
        }
        // Image entière, sans déformation, centrée ; le fond reste transparent si l'image l'est (logo PNG).
        $alpha = self::hasAlpha($im);
        $out = self::canvas($cw, $ch, $alpha);
        $scale = min($cw / $w, $ch / $h);
        $dw = max(1, (int) round($w * $scale));
        $dh = max(1, (int) round($h * $scale));
        imagecopyresampled($out, $im, intdiv($cw - $dw, 2), intdiv($ch - $dh, 2), 0, 0, $dw, $dh, $w, $h);

        return $out;
    }

    private static function canvas(int $w, int $h, bool $transparent): \GdImage
    {
        $out = imagecreatetruecolor($w, $h);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, $transparent ? imagecolorallocatealpha($out, 0, 0, 0, 127) : imagecolorallocate($out, 0, 0, 0));
        // Fond opaque : l'image est fondue sur le noir. Fond transparent : sa transparence est recopiée telle quelle.
        imagealphablending($out, !$transparent);

        return $out;
    }

    /** Transparence dans l'image source (échantillon 64 x 64 : un logo PNG détouré, pas un pixel isolé). */
    private static function hasAlpha(\GdImage $im): bool
    {
        $w = imagesx($im);
        $h = imagesy($im);
        $sx = max(1, intdiv($w, 64));
        $sy = max(1, intdiv($h, 64));
        for ($y = 0; $y < $h; $y += $sy) {
            for ($x = 0; $x < $w; $x += $sx) {
                if (((imagecolorat($im, $x, $y) >> 24) & 0x7F) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Remet à zéro le budget de conversion (tests, et chaque requête PHP repart de zéro de toute façon). */
    public static function resetBudget(): void
    {
        self::$spent = 0.0;
    }
}
