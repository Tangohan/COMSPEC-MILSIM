<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Support\SseIdMarking;
use App\Support\SseIdRealCountryCatalog;

/**
 * Rendu d'une face de papier du registre en PNG (GD) : MÊME gabarit que l'aperçu de l'atelier (views/atak/sse/papers/
 * _doc_preview.php + public/assets/css/sse-id-workshop.css), redessiné côté serveur parce que l'export de l'atelier
 * (html2canvas) ne tourne que dans un navigateur. Cette classe est la source UNIQUE de ce que la page d'un papier montre,
 * de ce que le PDF et les PNG contiennent : l'aperçu de la page est l'image de cette classe, au même gabarit.
 *
 * Faces : carte (recto, verso) ; passeport (couverture, page de données, page d'observations). Dimensions réelles : carte
 * 85,6 × 54 mm, page de passeport 125 × 88 mm ; la résolution est bornée (DPIS).
 *
 * Marquage (SseIdMarking::effective) : pays imaginaire, aucune marque ni métadonnée ; pays réel stylisé, micro-texte discret
 * sous chaque face (ou filigrane et bandeau en mode « standard ») ET métadonnées PNG. Il est dessiné sur CHAQUE face, par-dessus
 * la photo, la signature et l'image de fond, dans la même fonction que la face : aucune face sans marque ne peut en sortir.
 *
 * Polices : DejaVu (fournies avec TCPDF, décompressées une fois dans un dossier de cache) ; sans elles ou sans GD,
 * available() est faux et l'appelant refuse proprement.
 */
final class SsePaperRenderer
{
    /** Résolutions permises (points par pouce) : aperçu écran, intermédiaire, impression. */
    public const DPIS = [150, 240, 300];
    public const DPI_PRINT = 300;
    public const DPI_PREVIEW = 240;

    private const SS = 2;

    /** Faces d'une carte et d'un passeport : clé => [libellé, kind]. */
    public const CARD_FACES = ['recto' => 'Recto', 'verso' => 'Verso'];
    public const PASSPORT_FACES = ['couverture' => 'Couverture', 'donnees' => 'Page de données', 'observations' => 'Page d’observations'];

    private const TYPE_LINES = [
        'passport' => 'PASSEPORT · PASSPORT',
        'driving_license' => 'PERMIS DE CONDUIRE · DRIVING LICENCE',
        'military_id' => 'CARTE MILITAIRE · MILITARY ID',
        'residence_permit' => 'TITRE DE SÉJOUR · RESIDENCE PERMIT',
        'vehicle_registration' => 'CERTIFICAT D’IMMATRICULATION · VEHICLE REGISTRATION',
        'id_card' => 'CARTE D’IDENTITÉ · IDENTITY CARD',
    ];

    private const FONT_FILES = ['sans' => 'dejavusanscondensed', 'bold' => 'dejavusanscondensedb', 'mono' => 'dejavusansmono', 'monob' => 'dejavusansmonob', 'ital' => 'dejavuserifcondensedi'];

    /** @var array<string, string> */
    private static array $fonts = [];

    /** @var \GdImage */
    private $im;
    private float $s = 1.0;
    private float $cssW = 460.0;
    private float $cssH = 290.0;
    private int $pxW = 0;
    private int $pxH = 0;

    /** @var array<string, mixed> */
    private array $m = [];
    /** @var array<string, mixed> */
    private array $a = [];
    private string $mode = SseIdMarking::NONE;
    private string $c1 = '#1f4e79';
    private string $c2 = '#c9a227';
    private float $reserve = 0.0;
    private float $markSize = 9.0;
    private string $face = '';

    public static function available(): bool
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagettftext') && self::fontPath('sans') !== null;
    }

    /** @return array<string, string> clé de face => libellé */
    public static function faces(string $docType): array
    {
        return $docType === 'passport' ? self::PASSPORT_FACES : self::CARD_FACES;
    }

    /** @return array{0: float, 1: float} largeur et hauteur réelles d'une face, en millimètres */
    public static function sizeMm(string $docType): array
    {
        return $docType === 'passport' ? [125.0, 88.0] : [85.6, 54.0];
    }

    /** @return array{0: int, 1: int} dimensions en pixels de l'image finale */
    public static function sizePx(string $docType, int $dpi): array
    {
        [$w, $h] = self::sizeMm($docType);

        return [(int) round($w / 25.4 * $dpi), (int) round($h / 25.4 * $dpi)];
    }

    public static function normalizeDpi(int $dpi): int
    {
        return in_array($dpi, self::DPIS, true) ? $dpi : self::DPI_PRINT;
    }

    /** Chemin d'une police décompressée (cache), ou null si indisponible. */
    public static function fontPath(string $role): ?string
    {
        if (isset(self::$fonts[$role])) {
            return self::$fonts[$role];
        }
        $name = self::FONT_FILES[$role] ?? null;
        if ($name === null) {
            return null;
        }
        $src = base_path('tcpdf/fonts/' . $name . '.z');
        if (!is_file($src)) {
            return null;
        }
        $dirs = [base_path('storage/cache/sse-fonts'), rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'athena-sse-fonts'];
        foreach ($dirs as $dir) {
            $dst = $dir . DIRECTORY_SEPARATOR . $name . '.ttf';
            if (is_file($dst) && filesize($dst) > 10000) {
                return self::$fonts[$role] = $dst;
            }
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                continue;
            }
            $raw = @gzuncompress((string) @file_get_contents($src));
            if (!is_string($raw) || strlen($raw) < 10000) {
                return null;
            }
            $tmp = $dst . '.' . bin2hex(random_bytes(4)) . '.tmp';
            if (@file_put_contents($tmp, $raw) === false) {
                continue;
            }
            @rename($tmp, $dst);
            if (is_file($dst)) {
                return self::$fonts[$role] = $dst;
            }
        }

        return null;
    }

    /**
     * Rend une face.
     *
     * @param array<string, mixed> $model SseIdWorkshopService::previewModel() (marking déjà décidé par SseIdMarking::effective)
     * @param array{photo?: ?\GdImage, signature?: ?\GdImage, background?: ?\GdImage, flag?: ?\GdImage} $assets images déjà décidées (droits, gate) ;
     *        absentes : silhouette, signature imprimée, gabarit sans fond, bandes du pays
     * @return string PNG (transparent hors des coins arrondis si $transparent)
     */
    public function render(array $model, array $assets, string $face, int $dpi = self::DPI_PRINT, bool $transparent = true): string
    {
        // TCPDF, tant qu'un document existe, force mb_internal_encoding en ASCII : le rendu exige UTF-8.
        $prev = mb_internal_encoding();
        mb_internal_encoding('UTF-8');
        try {
            return $this->draw($model, $assets, $face, $dpi, $transparent);
        } finally {
            mb_internal_encoding($prev);
        }
    }

    /** @param array<string, mixed> $model @param array<string, mixed> $assets */
    private function draw(array $model, array $assets, string $face, int $dpi, bool $transparent): string
    {
        $docType = (string) ($model['doc_type'] ?? 'id_card');
        if (!isset(self::faces($docType)[$face])) {
            throw new \InvalidArgumentException('Face inconnue : ' . $face);
        }
        $dpi = self::normalizeDpi($dpi);
        $this->m = $model;
        $this->a = $assets;
        $this->face = $face;
        $this->mode = in_array($model['marking'] ?? '', SseIdMarking::MODES, true) ? (string) $model['marking'] : SseIdMarking::DISCREET;
        $passport = $docType === 'passport';
        $this->cssW = $passport ? 500.0 : 460.0;
        $this->cssH = $passport ? 352.0 : 460.0 * 54 / 85.6;
        [$fw, $fh] = self::sizePx($docType, $dpi);
        $this->pxW = $fw * self::SS;
        $this->pxH = $fh * self::SS;
        $this->s = $this->pxW / $this->cssW;
        $this->c1 = self::hex((string) ($model['country']['colors'][0] ?? ''), '#1f4e79');
        $this->c2 = self::hex((string) ($model['country']['colors'][1] ?? ''), '#c9a227');

        $im = imagecreatetruecolor($this->pxW, $this->pxH);
        imagealphablending($im, true);
        imagesavealpha($im, true);
        $this->im = $im;
        if (function_exists('imageantialias')) {
            imageantialias($im, true);
        }
        // Bande réservée en bas : micro-texte discret ou bandeau du mode standard (jamais posés sur la bande de lecture).
        $toCss = $this->cssW / $fw;
        $this->markSize = max(9.0, round($fh * 0.026)) * $toCss;
        $this->reserve = match ($this->mode) {
            SseIdMarking::DISCREET => $this->markSize * 1.7,
            SseIdMarking::STANDARD => max(16.0, round($fh * 0.075)) * $toCss,
            default => 0.0,
        };

        $this->paper();
        match ($face) {
            'recto' => $this->front(),
            'verso' => $this->back(),
            'couverture' => $this->cover(),
            'donnees' => $this->passportData(),
            default => $this->passportNotes(),
        };
        $this->marking();

        $out = imagecreatetruecolor($fw, $fh);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 255, 255, 255, 127));
        imagecopyresampled($out, $im, 0, 0, 0, 0, $fw, $fh, $this->pxW, $this->pxH);
        $this->corners($out, $fw, $fh, (float) ($passport ? 6 : 14) * $fw / $this->cssW, $transparent);
        imageresolution($out, $dpi, $dpi);
        if (!$transparent) {
            imagesavealpha($out, false);
        }
        ob_start();
        imagepng($out, null, 6);
        $png = (string) ob_get_clean();
        $this->im = null;

        return $this->isReal() ? SseIdMarking::pngWithMention($png) : $png;
    }

    private function isReal(): bool
    {
        return !empty($this->m['country']['real_sim']);
    }

    // ------------------------------------------------------------------ Faces

    private function front(): void
    {
        $y = $this->band(self::TYPE_LINES[$this->m['doc_type']] ?? self::TYPE_LINES['id_card']);
        $y = $this->body($y);
        $this->signature($y + 6);
        $this->stateStamp();
    }

    private function back(): void
    {
        $y = $this->band('MENTIONS · DETAILS');
        $pr = (array) ($this->m['print'] ?? []);
        $doc = (array) $this->m['doc'];
        $rows = [
            ['half', 'Délivré le · Date of issue', (string) $doc['issued_on']],
            ['half', 'Autorité · Authority', (string) $doc['authority']],
        ];
        if (($this->m['doc_type'] ?? '') === 'driving_license') {
            $rows[] = ['full', 'Catégories · Categories', (string) ($pr['categories'] ?? '')];
        }
        foreach ([['place_of_issue', 'Lieu de délivrance · Place of issue'], ['address', 'Adresse · Address'], ['height', 'Taille · Height'], ['eyes', 'Yeux · Eyes'],
            ['personal_number', 'N° personnel · Personal no.'], ['service', 'Service · Employer'], ['rank', 'Grade · Rank'], ['phone', 'Téléphone · Phone'], ['remarks', 'Observations · Remarks']] as [$k, $label]) {
            $rows[] = ['full', $label, (string) ($pr[$k] ?? '')];
        }
        $this->fieldRows(12.0, $y, $this->cssW - 24.0, array_values(array_filter($rows, static fn (array $r): bool => $r[2] !== '' || $r[0] === 'half')));
        $this->stamp();
        $this->mrz();
    }

    private function cover(): void
    {
        $this->background();
        $r1 = self::rgb($this->c1);
        $this->rect(0, 0, $this->cssW, $this->cssH, $this->col($r1, 1.0));
        // Halo radial clair (rgba(255,255,255,.2) au centre, nul à 65 %).
        $cx = $this->cssW * 0.5;
        $cy = $this->cssH * 0.4;
        $maxR = max($this->cssW, $this->cssH) * 0.65;
        for ($i = 90; $i >= 1; --$i) {
            $rr = $maxR * $i / 90;
            $alpha = 0.2 * (1 - ($i - 0.5) / 90) / 1.0;
            $this->ellipse($cx, $cy, $rr * 2, $rr * 2, $this->col([255, 255, 255], $alpha * 0.1));
        }
        $zone = (string) ($this->m['country']['zone'] ?? '');
        $gold = $zone !== '' ? [230, 199, 102] : self::rgb($this->c2);
        $name = mb_strtoupper((string) ($this->m['country']['name'] ?? ''));
        $cy2 = $this->cssH / 2;
        $standard = $this->mode === SseIdMarking::STANDARD;
        $stack = 15 * 1.25 + 46 + 20 * 1.25 + 11 * 1.25 + ($standard ? 9 * 1.25 : 0) + 8 * ($standard ? 4 : 3);
        $y = $cy2 - $stack / 2;
        $this->textC($this->cssW / 2, $y, $name, 15, $this->col($gold, 1.0), 'bold', 0.18);
        $y += 15 * 1.25 + 8;
        $this->flag($this->cssW / 2 - 36, $y, 72, 46);
        $y += 46 + 8;
        $this->textC($this->cssW / 2, $y, 'PASSEPORT', 20, $this->col($gold, 1.0), 'bold', 0.3);
        $y += 20 * 1.25 + 8;
        $this->textC($this->cssW / 2, $y, 'PASSPORT', 11, $this->col($gold, 1.0), 'sans', 0.0);
        if ($standard) {
            $y += 11 * 1.25 + 8;
            $this->textC($this->cssW / 2, $y, 'SPÉCIMEN DE JEU — SIMULATION', 9, $this->col($gold, 0.85), 'sans', 0.16);
        }
    }

    private function passportData(): void
    {
        $y = $this->band('PASSEPORT · PASSPORT');
        $y = $this->body($y);
        $doc = (array) $this->m['doc'];
        $pr = (array) ($this->m['print'] ?? []);
        $rows = [['half', 'Délivré le · Date of issue', (string) $doc['issued_on']], ['half', 'Autorité · Authority', (string) $doc['authority']]];
        if ((string) ($pr['personal_number'] ?? '') !== '') {
            $rows[] = ['full', 'N° personnel · Personal no.', (string) $pr['personal_number']];
        }
        $y = $this->fieldRows(12.0, $y + 6, $this->cssW - 24.0, $rows);
        $this->signature($y + 6);
        $this->stateStamp();
        $this->mrz();
    }

    private function passportNotes(): void
    {
        $y = $this->band('OBSERVATIONS · REMARKS');
        $pr = (array) ($this->m['print'] ?? []);
        $rows = [];
        foreach ([['place_of_issue', 'Lieu de délivrance · Place of issue'], ['address', 'Adresse · Address'], ['height', 'Taille · Height'], ['eyes', 'Yeux · Eyes'],
            ['service', 'Service · Employer'], ['rank', 'Fonction · Position'], ['phone', 'Téléphone · Phone'], ['remarks', 'Observations · Remarks']] as [$k, $label]) {
            if ((string) ($pr[$k] ?? '') !== '') {
                $rows[] = ['full', $label, (string) $pr[$k]];
            }
        }
        $this->fieldRows(12.0, $y, $this->cssW - 24.0, $rows);
        $this->stamp();
    }

    // ------------------------------------------------------------------ Éléments

    /** Fond du papier, motif de gabarit, image de fond importée ; renvoie rien (le fond couvre toute la face). */
    private function paper(): void
    {
        $layout = (string) ($this->m['layout'] ?? 'modern');
        $paper = match ($layout) {
            'classic' => [246, 243, 234],
            'compact' => [251, 251, 250],
            'custom' => [244, 241, 234],
            default => [244, 241, 232],
        };
        imagefilledrectangle($this->im, 0, 0, $this->pxW, $this->pxH, $this->col($paper, 1.0));
        if ($layout === 'modern') {
            // Fines diagonales (repeating-linear-gradient 115°, 2 px sur 9 px, noir à 2,5 %) : teinte opaque, sans lissage.
            $step = 9 * $this->s;
            $col = (int) imagecolorallocate($this->im, 238, 235, 226);
            if (function_exists('imageantialias')) {
                imageantialias($this->im, false);
            }
            imagesetthickness($this->im, max(1, (int) round(2 * $this->s)));
            for ($x = -$this->pxH; $x < $this->pxW + $this->pxH; $x += $step) {
                imageline($this->im, (int) $x, 0, (int) ($x - $this->pxH * 0.466), $this->pxH, $col);
            }
            imagesetthickness($this->im, 1);
            if (function_exists('imageantialias')) {
                imageantialias($this->im, true);
            }
        }
        if ($layout === 'custom') {
            $this->background();
        }
    }

    private function background(): void
    {
        $bg = $this->a['background'] ?? null;
        if (!$bg instanceof \GdImage || ($this->m['layout'] ?? '') !== 'custom') {
            return;
        }
        $this->cover2($bg, 0, 0, $this->cssW, $this->cssH);
    }

    /** Bande d'en-tête ; renvoie l'ordonnée (px CSS) où le corps commence. */
    private function band(string $sub): float
    {
        $layout = (string) ($this->m['layout'] ?? 'modern');
        $zone = (string) ($this->m['country']['zone'] ?? '');
        $textH = 12 * 1.25 + 9 * 1.25;
        $h = $textH + 12;
        $w = $this->cssW;
        $ink = [29, 39, 51];
        $white = [255, 255, 255];
        $textCol = $white;
        $bottom = null;
        if ($layout === 'classic') {
            $textCol = self::rgb($this->c1);
            $bottom = [2.0, self::rgb($this->c2)];
        } elseif ($layout === 'compact') {
            $this->rect(0, 0, $w, $h, $this->col($white, 1.0));
            $textCol = $ink;
            $bottom = [4.0, self::rgb($this->c1)];
        } elseif ($layout === 'custom') {
            $this->rect(0, 0, $w, $h, $this->col($white, 0.72));
            $textCol = $ink;
            $bottom = [3.0, self::rgb($this->c1)];
        } else {
            $stop = $zone === 'europe' ? 0.70 : 0.62;
            if ($zone === 'americas' || $zone === 'other') {
                $this->rect(0, 0, $w, $h, $this->col(self::rgb($this->c1), 1.0));
                $bottom = $zone === 'americas' ? [3.0, self::rgb($this->c2)] : [3.0, self::rgb($this->c2), true];
            } else {
                $this->gradientH(0, 0, $w, $h, self::rgb($this->c1), self::rgb($this->c2), $stop);
            }
        }
        if ($bottom !== null) {
            $bh = (float) $bottom[0];
            if (!empty($bottom[2])) {
                $this->rect(0, $h - 1.0, $w, 1.0, $this->col($bottom[1], 1.0));
                $this->rect(0, $h - $bh, $w, 1.0, $this->col($bottom[1], 1.0));
            } else {
                $this->rect(0, $h - $bh, $w, $bh, $this->col($bottom[1], 1.0));
            }
        }
        $this->flag(12, ($h - 22) / 2, 34, 22);
        $tx = 12 + 34 + 8;
        $name = mb_strtoupper((string) ($this->m['country']['name'] ?? ''));
        $this->text($tx, 6 + 12 * 0.93 * 1.0 + 1, $this->fit($name, $this->cssW - $tx - 90, 12, 'bold', 0.08), 12, $this->col($textCol, 1.0), 'bold', 0.08);
        $this->text($tx, 6 + 12 * 1.25 + 9 * 0.93 + 1.5, $sub, 9, $this->col($textCol, 0.9), 'sans', 0.12);
        if ($this->mode === SseIdMarking::STANDARD) {
            $bw = $this->width('SIMULATION', 9, 'bold', 0.2) + 10;
            $bx = $w - 12 - $bw;
            $this->strokeRect($bx, ($h - 17) / 2, $bw, 17, 1, $this->col($layout === 'compact' ? [179, 38, 30] : $textCol, 0.7));
            $this->text($bx + 5, ($h - 17) / 2 + 12.2, 'SIMULATION', 9, $this->col($layout === 'compact' ? [179, 38, 30] : $textCol, 1.0), 'bold', 0.2);
        }
        if ($layout === 'classic') {
            $this->strokeRect(0, 0, $w, $this->cssH, 2, $this->col(self::rgb($this->c1), 1.0));
        }

        return $h + 8;
    }

    /** Photo (26 % de la largeur utile, 35 × 45) et champs ; renvoie l'ordonnée sous le bloc. */
    private function body(float $y): float
    {
        $cw = $this->cssW - 24;
        $pw = $cw * 0.26;
        $ph = $pw * 45 / 35;
        $this->photo(12, $y, $pw, $ph);
        $id = (array) $this->m['identity'];
        $doc = (array) $this->m['doc'];
        $fx = 12 + $pw + 10;
        $fw = $cw - $pw - 10;
        $rows = [
            ['full', 'Nom · Surname', mb_strtoupper((string) $id['last_name'])],
            ['full', 'Prénoms · Given names', (string) $id['first_name']],
            ['half', 'Sexe · Sex', (string) $id['sex']],
            ['half', 'Nationalité · Nationality', (string) $id['nationality']],
            ['half', 'Né(e) le · Date of birth', (string) $id['birth_date']],
            ['half', 'Lieu · Place of birth', (string) $id['birth_place']],
            ['half', 'N° · Number', (string) $doc['number'], 'num'],
            ['half', 'Expire le · Expiry', (string) $doc['expires_on']],
        ];
        $end = $this->fieldRows($fx, $y, $fw, $rows);

        return max($y + $ph, $end);
    }

    /**
     * Champs étiquetés (dt petit, dd gras) en lignes pleines ou à deux colonnes ; renvoie l'ordonnée sous le dernier.
     *
     * @param list<array{0: string, 1: string, 2: string, 3?: string}> $rows
     */
    private function fieldRows(float $x, float $y, float $w, array $rows): float
    {
        $ink = [29, 39, 51];
        $i = 0;
        $n = count($rows);
        $half = ($w - 10) / 2;
        $first = true;
        while ($i < $n) {
            $r = $rows[$i];
            $pair = [$r];
            if ($r[0] === 'half' && $i + 1 < $n && $rows[$i + 1][0] === 'half') {
                $pair[] = $rows[$i + 1];
            }
            $i += count($pair);
            if (!$first) {
                $y += 3;
            }
            $first = false;
            $rowH = 0.0;
            $cells = [];
            foreach ($pair as $k => $f) {
                $cw = $f[0] === 'full' ? $w : (count($pair) === 2 ? $half : $half);
                $cx = $x + ($k === 1 ? $half + 10 : 0);
                $lines = $this->wrap($f[2], $cw, 11, 'bold', ($f[3] ?? '') === 'num' ? 0.06 : 0.0);
                $h = 7.5 * 1.25 + max(1, count($lines)) * 11 * 1.25;
                $rowH = max($rowH, $h);
                $cells[] = [$cx, $f, $lines];
            }
            foreach ($cells as [$cx, $f, $lines]) {
                $num = ($f[3] ?? '') === 'num';
                $cwid = $f[0] === 'full' ? $w : $half;
                $this->text($cx, $y + 7.5 * 0.93 + 0.8, $this->fit(mb_strtoupper($f[1]), $cwid, 7.5, 'sans', 0.06), 7.5, $this->col($ink, 0.62), 'sans', 0.06);
                $ly = $y + 7.5 * 1.25;
                foreach ($lines as $line) {
                    if ($num) {
                        $this->text($cx, $ly + 11 * 0.93 + 1, $line, 11, $this->col([179, 38, 30], 1.0), 'monob', 0.06);
                    } else {
                        $this->text($cx, $ly + 11 * 0.93 + 1, $line, 11, $this->col($ink, 1.0), 'bold', 0.0);
                    }
                    $ly += 11 * 1.25;
                }
            }
            $y += $rowH;
        }

        return $y;
    }

    private function photo(float $x, float $y, float $w, float $h): void
    {
        $this->rect($x, $y, $w, $h, $this->col([227, 224, 216], 1.0));
        $img = $this->a['photo'] ?? null;
        if ($img instanceof \GdImage) {
            $this->cover2($img, $x, $y, $w, $h);
        } else {
            // Silhouette (viewBox 60 × 75) aux couleurs du pays.
            $k = $w / 60;
            $c = self::rgb($this->c1);
            $this->rect($x, $y, $w, $h, $this->col($c, 0.08));
            $this->ellipse($x + 30 * $k, $y + 27 * $k, 26 * $k, 26 * $k, $this->col($c, 0.35));
            $pts = [];
            for ($t = 0; $t <= 24; ++$t) {
                $ang = M_PI * $t / 24;
                $pts[] = [$x + (30 - 24 * cos($ang)) * $k, $y + (75 - 25 * sin($ang)) * $k];
            }
            $this->poly($pts, $this->col($c, 0.35));
        }
        $this->strokeRect($x, $y, $w, $h, 1, $this->col([0, 0, 0], 0.25));
    }

    private function signature(float $y): void
    {
        $x = 12.0;
        $cw = $this->cssW - 24;
        $this->text($x, $y + 7.5 * 0.93 + 0.8, $this->fit(mb_strtoupper('Signature du titulaire · Holder’s signature'), $cw, 7.5, 'sans', 0.06), 7.5, $this->col([29, 39, 51], 0.62), 'sans', 0.06);
        $sig = $this->a['signature'] ?? null;
        $top = $y + 7.5 * 1.25;
        // Jamais sous la bande de lecture : la signature rétrécit plutôt que d'être recouverte.
        $mrzTop = $this->mrzTop();
        $maxH = $mrzTop !== null ? max(10.0, min(28.0, $mrzTop - $top - 3)) : 28.0;
        if ($sig instanceof \GdImage) {
            $iw = imagesx($sig);
            $ih = imagesy($sig);
            $k = min($cw * 0.62 / $iw, $maxH / $ih);
            $this->blit($sig, $x, $top, $iw * $k, $ih * $k);
        } else {
            $pr = (array) ($this->m['print'] ?? []);
            $id = (array) $this->m['identity'];
            $text = trim((string) ($pr['signature'] ?? '')) !== '' ? (string) $pr['signature'] : trim($id['first_name'] . ' ' . $id['last_name']);
            $this->text($x, $top + 15 * 0.95 + 1, $this->fit($text, $cw * 0.8, 15, 'ital', 0.0), 15, $this->col([27, 58, 138], 1.0), 'ital', 0.0);
        }
    }

    /** @return list<string> */
    private function mrzLines(): array
    {
        return array_values(array_filter(array_map('strval', (array) ($this->m['mrz'] ?? [])), static fn (string $l): bool => $l !== ''));
    }

    /** Ordonnée (px CSS) du haut de la bande de lecture de la face en cours, ou null si elle n'en porte pas. */
    private function mrzTop(): ?float
    {
        $lines = $this->mrzLines();
        $face = $this->face;
        if ($lines === [] || !in_array($face, ['verso', 'donnees'], true)) {
            return null;
        }
        $size = ($this->m['doc_type'] ?? '') === 'passport' ? 9.2 : 10.5;

        return $this->cssH - $this->reserve - (count($lines) * $size * 1.35 + 14);
    }

    private function mrz(): void
    {
        $lines = $this->mrzLines();
        if ($lines === []) {
            return;
        }
        $passport = ($this->m['doc_type'] ?? '') === 'passport';
        $size = $passport ? 9.2 : 10.5;
        $lh = $size * 1.35;
        $h = count($lines) * $lh + 6 + 8;
        $top = $this->cssH - $this->reserve - $h;
        $this->rect(0, $top, $this->cssW, $h, $this->col([255, 255, 255], 0.72));
        $y = $top + 6;
        $maxW = $this->cssW - 24;
        foreach ($lines as $line) {
            $sp = 0.08;
            $w = $this->width($line, $size, 'mono', $sp);
            $sz = $w > $maxW ? $size * $maxW / $w : $size;
            $this->text(12, $y + $sz * 0.93 + ($lh - $sz * 1.164) / 2, $line, $sz, $this->col([17, 17, 17], 1.0), 'mono', $sp);
            $y += $lh;
        }
    }

    private function stamp(): void
    {
        $doc = (array) $this->m['doc'];
        $pr = (array) ($this->m['print'] ?? []);
        $text = trim((string) ($pr['stamp'] ?? '')) !== '' ? (string) $pr['stamp'] : (string) $doc['authority'];
        $d = $this->cssW < 480 ? 86.0 : 86.0;
        $cx = $this->cssW - 18 - $d / 2;
        $cy = $this->cssH * 0.42 + $d / 2;
        $c = self::rgb($this->c1);
        $col = $this->col($c, 0.75);
        $this->ring($cx, $cy, $d, 1.4, $col);
        $this->ring($cx, $cy, $d - 8, 1.4, $col);
        $lines = array_slice($this->wrap(mb_strtoupper($text), $d - 22, 7, 'bold', 0.0), 0, 3);
        $total = count($lines) * 7 * 1.25 + 2 + 13 * 1.25;
        $y = -$total / 2;
        $rot = 12.0;
        foreach ($lines as $line) {
            $this->textRot($cx, $cy, 0, $y + 7 * 0.93, $line, 7, $col, 'bold', 0.0, $rot, true);
            $y += 7 * 1.25;
        }
        $this->textRot($cx, $cy, 0, $y + 2 + 13 * 0.93, (string) $this->m['country']['code'], 13, $col, 'bold', 0.12, $rot, true);
    }

    private function stateStamp(): void
    {
        $doc = (array) $this->m['doc'];
        $label = ($doc['st_validity'] ?? 'valid') !== 'valid' ? 'ANNULÉ / INVALID' : (($doc['st_report'] ?? 'none') !== 'none' ? 'SIGNALÉ / REPORTED' : '');
        if ($label === '') {
            return;
        }
        $red = [179, 38, 30];
        $w = $this->width($label, 15, 'bold', 0.12) + 20;
        $h = 15 * 1.25 + 6 + 6;
        $cx = $this->cssW / 2;
        $cy = $this->cssH * 0.46;
        $rot = 8.0;
        $r = deg2rad($rot);
        $pt = static fn (float $dx, float $dy): array => [$cx + $dx * cos($r) + $dy * sin($r), $cy - $dx * sin($r) + $dy * cos($r)];
        $this->poly([$pt(-$w / 2, -$h / 2), $pt($w / 2, -$h / 2), $pt($w / 2, $h / 2), $pt(-$w / 2, $h / 2)], $this->col([255, 255, 255], 0.6));
        imagesetthickness($this->im, max(1, (int) round(3 * $this->s)));
        $p = [$pt(-$w / 2, -$h / 2), $pt($w / 2, -$h / 2), $pt($w / 2, $h / 2), $pt(-$w / 2, $h / 2)];
        for ($i = 0; $i < 4; ++$i) {
            $q = $p[($i + 1) % 4];
            imageline($this->im, (int) ($p[$i][0] * $this->s), (int) ($p[$i][1] * $this->s), (int) ($q[0] * $this->s), (int) ($q[1] * $this->s), $this->col($red, 1.0));
        }
        imagesetthickness($this->im, 1);
        $this->textRot($cx, $cy, 0, 15 * 0.38, $label, 15, $this->col($red, 1.0), 'bold', 0.12, $rot, true);
    }

    /** Marquage de la face : la dernière couche, après tout le reste. Aucun marquage pour un pays imaginaire. */
    private function marking(): void
    {
        if ($this->mode === SseIdMarking::DISCREET) {
            $cssSize = $this->markSize;
            $text = SseIdMarking::MENTION;
            $fx = $this->cssW - $cssSize * 0.9 - $this->width($text, $cssSize, 'bold', 0.0);
            $y = $this->cssH - $cssSize * 0.8;
            // Halo blanc puis texte gris : lisible sur tout fond, sans masquer ce qui est dessous.
            foreach ([[-1, 0], [1, 0], [0, -1], [0, 1]] as [$dx, $dy]) {
                $this->text($fx + $dx * 0.5, $y + $dy * 0.5, $text, $cssSize, $this->col([255, 255, 255], 0.55), 'bold', 0.0);
            }
            $this->text($fx, $y, $text, $cssSize, $this->col([40, 40, 48], 0.5), 'bold', 0.0);

            return;
        }
        if ($this->mode !== SseIdMarking::STANDARD) {
            return;
        }
        $diag = sqrt($this->cssW ** 2 + $this->cssH ** 2);
        $size = max(14.0, round($this->cssH / 9));
        $col = $this->col([179, 38, 30], 0.16);
        for ($y = -$diag / 2; $y <= $diag / 2; $y += $size * 1.9) {
            $this->textRot($this->cssW / 2, $this->cssH / 2, 0, $y, 'SIMULATION   SIMULATION   SIMULATION   SIMULATION', $size, $col, 'bold', 0.0, 24.0, true);
        }
        $bh = $this->reserve;
        $this->rect(0, $this->cssH - $bh, $this->cssW, $bh, $this->col([168, 25, 30], 1.0));
        $band = 'DOCUMENT DE JEU – SANS VALEUR · GAME DOCUMENT – NO VALUE';
        $fs = $bh * 0.5;
        $bw = $this->width($band, $fs, 'bold', 0.0);
        if ($bw > $this->cssW - 16) {
            $fs *= ($this->cssW - 16) / $bw;
            $bw = $this->cssW - 16;
        }
        $this->text(($this->cssW - $bw) / 2, $this->cssH - $bh / 2 + $fs * 0.35, $band, $fs, $this->col([255, 255, 255], 1.0), 'bold', 0.0);
        // Mention en haut à droite (languette rouge).
        $m = SseIdRealCountryCatalog::FACE_MENTION;
        $mw = $this->width($m, 7, 'bold', 0.12) + 12;
        $this->rect($this->cssW - $mw, 0, $mw, 7 * 1.25 + 2, $this->col([168, 25, 30], 1.0));
        $this->text($this->cssW - $mw + 6, 1 + 7 * 0.93 + 0.5, $m, 7, $this->col([255, 255, 255], 1.0), 'bold', 0.12);
    }

    // ------------------------------------------------------------------ Drapeau

    private function flag(float $x, float $y, float $w, float $h): void
    {
        $this->rect($x - 0.8, $y - 0.8, $w + 1.6, $h + 1.6, $this->col([255, 255, 255], 0.6));
        $img = $this->a['flag'] ?? null;
        if ($img instanceof \GdImage) {
            $this->cover2($img, $x, $y, $w, $h);

            return;
        }
        $code = strtoupper((string) ($this->m['country']['code'] ?? ''));
        if (!empty($this->m['country']['real_sim']) && ($svg = SseIdRealCountryCatalog::flagSvg($code)) !== '' && preg_match_all('/<rect([^>]*)\/>/', $svg, $mm) > 0) {
            $this->rect($x, $y, $w, $h, $this->col([255, 255, 255], 1.0));
            foreach ($mm[1] as $attrs) {
                $get = static fn (string $k, float $d): float => preg_match('/\b' . $k . '="([\d.]+)"/', $attrs, $v) === 1 ? (float) $v[1] : $d;
                $fill = preg_match('/fill="(#[0-9a-fA-F]{6})"/', $attrs, $f) === 1 ? $f[1] : '#ffffff';
                $this->rect($x + $get('x', 0) / 30 * $w, $y + $get('y', 0) / 20 * $h, $get('width', 30) / 30 * $w, $get('height', 20) / 20 * $h, $this->col(self::rgb($fill), 1.0));
            }

            return;
        }
        // Pays sans drapeau : trois bandes (couleur principale, blanc, couleur secondaire).
        $this->rect($x, $y, $w / 3 + 0.2, $h, $this->col(self::rgb($this->c1), 1.0));
        $this->rect($x + $w / 3, $y, $w / 3 + 0.2, $h, $this->col([255, 255, 255], 1.0));
        $this->rect($x + 2 * $w / 3, $y, $w / 3, $h, $this->col(self::rgb($this->c2), 1.0));
    }

    // ------------------------------------------------------------------ Primitives (unités : pixels CSS du gabarit)

    /** @param array{0: int, 1: int, 2: int} $rgb */
    private function col(array $rgb, float $opacity): int
    {
        $alpha = (int) round((1 - max(0.0, min(1.0, $opacity))) * 127);

        return (int) imagecolorallocatealpha($this->im, $rgb[0], $rgb[1], $rgb[2], $alpha);
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }

    private static function hex(string $v, string $default): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $v) === 1 ? $v : $default;
    }

    private function rect(float $x, float $y, float $w, float $h, int $color): void
    {
        imagefilledrectangle($this->im, (int) round($x * $this->s), (int) round($y * $this->s), (int) round(($x + $w) * $this->s) - 1, (int) round(($y + $h) * $this->s) - 1, $color);
    }

    private function strokeRect(float $x, float $y, float $w, float $h, float $t, int $color): void
    {
        $this->rect($x, $y, $w, $t, $color);
        $this->rect($x, $y + $h - $t, $w, $t, $color);
        $this->rect($x, $y, $t, $h, $color);
        $this->rect($x + $w - $t, $y, $t, $h, $color);
    }

    private function ellipse(float $cx, float $cy, float $w, float $h, int $color): void
    {
        imagefilledellipse($this->im, (int) round($cx * $this->s), (int) round($cy * $this->s), (int) round($w * $this->s), (int) round($h * $this->s), $color);
    }

    /** Anneau d'épaisseur $t (px CSS) : polygone extérieur puis intérieur, relié par une couture. */
    private function ring(float $cx, float $cy, float $d, float $t, int $color): void
    {
        $pts = [];
        $n = 120;
        for ($i = 0; $i <= $n; ++$i) {
            $a = 2 * M_PI * $i / $n;
            $pts[] = [$cx + $d / 2 * cos($a), $cy + $d / 2 * sin($a)];
        }
        for ($i = $n; $i >= 0; --$i) {
            $a = 2 * M_PI * $i / $n;
            $pts[] = [$cx + ($d / 2 - $t) * cos($a), $cy + ($d / 2 - $t) * sin($a)];
        }
        $this->poly($pts, $color);
    }

    /** @param list<array{0: float, 1: float}> $pts */
    private function poly(array $pts, int $color): void
    {
        $flat = [];
        foreach ($pts as [$px, $py]) {
            $flat[] = (int) round($px * $this->s);
            $flat[] = (int) round($py * $this->s);
        }
        imagefilledpolygon($this->im, $flat, $color);
    }

    /**
     * @param array{0: int, 1: int, 2: int} $a
     * @param array{0: int, 1: int, 2: int} $b
     */
    private function gradientH(float $x, float $y, float $w, float $h, array $a, array $b, float $stop): void
    {
        $x0 = (int) round($x * $this->s);
        $x1 = (int) round(($x + $w) * $this->s);
        $y0 = (int) round($y * $this->s);
        $y1 = (int) round(($y + $h) * $this->s);
        $span = max(1, $x1 - $x0);
        for ($px = $x0; $px < $x1; ++$px) {
            $t = ($px - $x0) / $span;
            $k = $t <= $stop ? 0.0 : ($t - $stop) / (1 - $stop);
            $c = imagecolorallocate($this->im, (int) round($a[0] + ($b[0] - $a[0]) * $k), (int) round($a[1] + ($b[1] - $a[1]) * $k), (int) round($a[2] + ($b[2] - $a[2]) * $k));
            imageline($this->im, $px, $y0, $px, $y1 - 1, (int) $c);
        }
    }

    /** Image recadrée « cover » dans la boîte. */
    private function cover2(\GdImage $src, float $x, float $y, float $w, float $h): void
    {
        $sw = imagesx($src);
        $sh = imagesy($src);
        if ($sw < 1 || $sh < 1) {
            return;
        }
        $k = max($w / $sw, $h / $sh);
        $cw = $w / $k;
        $ch = $h / $k;
        imagecopyresampled($this->im, $src, (int) round($x * $this->s), (int) round($y * $this->s), (int) round(($sw - $cw) / 2), (int) round(($sh - $ch) / 2),
            (int) round($w * $this->s), (int) round($h * $this->s), (int) round($cw), (int) round($ch));
    }

    private function blit(\GdImage $src, float $x, float $y, float $w, float $h): void
    {
        imagecopyresampled($this->im, $src, (int) round($x * $this->s), (int) round($y * $this->s), 0, 0, max(1, (int) round($w * $this->s)), max(1, (int) round($h * $this->s)), imagesx($src), imagesy($src));
    }

    /** Largeur d'un texte (px CSS), interlettrage en em. */
    private function width(string $text, float $size, string $font, float $sp): float
    {
        if ($text === '') {
            return 0.0;
        }
        $path = (string) self::fontPath($font);
        $pt = $size * $this->s * 0.75;
        $box = imagettfbbox($pt, 0, $path, $text);
        $w = ($box[2] - $box[0]) / $this->s;

        return $w + $sp * $size * max(0, mb_strlen($text) - 1);
    }

    /** Texte à la ligne de base ($y en px CSS) ; interlettrage > 0 : lettre par lettre. */
    private function text(float $x, float $baseline, string $text, float $size, int $color, string $font, float $sp): void
    {
        if ($text === '') {
            return;
        }
        $path = (string) self::fontPath($font);
        $pt = $size * $this->s * 0.75;
        if ($sp <= 0.0) {
            imagettftext($this->im, $pt, 0, (int) round($x * $this->s), (int) round($baseline * $this->s), $color, $path, $text);

            return;
        }
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $mono = str_starts_with($font, 'mono');
        $adv = $mono ? $this->advance('0', $pt, $path) : 0.0;
        $cx = $x * $this->s;
        foreach ($chars as $ch) {
            imagettftext($this->im, $pt, 0, (int) round($cx), (int) round($baseline * $this->s), $color, $path, $ch);
            $cx += ($mono ? $adv : $this->advance($ch, $pt, $path)) + $sp * $size * $this->s;
        }
    }

    private function advance(string $ch, float $pt, string $path): float
    {
        static $cache = [];
        $key = $path . '|' . round($pt, 2) . '|' . $ch;
        if (!isset($cache[$key])) {
            $w = imagettfbbox($pt, 0, $path, '|' . $ch . '|');
            $b = imagettfbbox($pt, 0, $path, '||');
            $cache[$key] = (float) (($w[2] - $w[0]) - ($b[2] - $b[0]));
        }

        return $cache[$key];
    }

    /** Texte centré horizontalement sur $cx, haut de ligne à $top. */
    private function textC(float $cx, float $top, string $text, float $size, int $color, string $font, float $sp): void
    {
        $w = $this->width($text, $size, $font, $sp);
        $this->text($cx - $w / 2, $top + $size * 0.93 + ($size * 0.25) / 2, $text, $size, $color, $font, $sp);
    }

    /**
     * Texte tourné autour de ($cx, $cy), centré sur son point ($dx, $dy) (ligne de base).
     */
    private function textRot(float $cx, float $cy, float $dx, float $dy, string $text, float $size, int $color, string $font, float $sp, float $angle, bool $center): void
    {
        $path = (string) self::fontPath($font);
        $pt = $size * $this->s * 0.75;
        $w = $this->width($text, $size, $font, $sp);
        $r = deg2rad($angle);
        $lx = $dx - ($center ? $w / 2 : 0);
        $ly = $dy;
        $px = $cx + $lx * cos($r) + $ly * sin($r);
        $py = $cy - $lx * sin($r) + $ly * cos($r);
        if ($sp <= 0.0) {
            imagettftext($this->im, $pt, $angle, (int) round($px * $this->s), (int) round($py * $this->s), $color, $path, $text);

            return;
        }
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $off = 0.0;
        foreach ($chars as $ch) {
            imagettftext($this->im, $pt, $angle, (int) round(($px + $off * cos($r)) * $this->s), (int) round(($py - $off * sin($r)) * $this->s), $color, $path, $ch);
            $off += $this->advance($ch, $pt, $path) / $this->s + $sp * $size;
        }
    }

    /** Réduit le texte (…) pour qu'il tienne ; l'interlettrage est compté. */
    private function fit(string $text, float $maxW, float $size, string $font, float $sp): string
    {
        if ($this->width($text, $size, $font, $sp) <= $maxW) {
            return $text;
        }
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        while ($chars !== [] && $this->width(implode('', $chars) . '…', $size, $font, $sp) > $maxW) {
            array_pop($chars);
        }

        return implode('', $chars) . '…';
    }

    /**
     * Découpe à la largeur donnée (mots, puis caractères pour un mot trop long : « overflow-wrap: anywhere »).
     *
     * @return list<string>
     */
    private function wrap(string $text, float $maxW, float $size, string $font, float $sp): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($text === '') {
            return [''];
        }
        $lines = [];
        $cur = '';
        foreach (explode(' ', $text) as $word) {
            $try = $cur === '' ? $word : $cur . ' ' . $word;
            if ($this->width($try, $size, $font, $sp) <= $maxW) {
                $cur = $try;
                continue;
            }
            if ($cur !== '') {
                $lines[] = $cur;
                $cur = '';
            }
            while ($this->width($word, $size, $font, $sp) > $maxW) {
                $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $part = '';
                foreach ($chars as $ch) {
                    if ($this->width($part . $ch, $size, $font, $sp) > $maxW && $part !== '') {
                        break;
                    }
                    $part .= $ch;
                }
                $lines[] = $part;
                $word = (string) mb_substr($word, mb_strlen($part));
                if ($word === '') {
                    break;
                }
            }
            $cur = $word;
        }
        if ($cur !== '') {
            $lines[] = $cur;
        }

        return $lines;
    }

    /** Coins arrondis : pixels hors du rectangle arrondi rendus transparents (ou blancs si $transparent est faux). */
    private function corners(\GdImage $im, int $w, int $h, float $r, bool $transparent): void
    {
        $r = max(2.0, $r);
        $n = (int) ceil($r) + 1;
        $fill = $transparent ? imagecolorallocatealpha($im, 255, 255, 255, 127) : imagecolorallocate($im, 255, 255, 255);
        imagealphablending($im, false);
        for ($i = 0; $i < $n; ++$i) {
            for ($j = 0; $j < $n; ++$j) {
                $dx = $r - $i - 0.5;
                $dy = $r - $j - 0.5;
                if ($dx <= 0 || $dy <= 0) {
                    continue;
                }
                $d = sqrt($dx * $dx + $dy * $dy);
                if ($d <= $r - 0.5) {
                    continue;
                }
                if ($d >= $r + 0.5) {
                    foreach ([[$i, $j], [$w - 1 - $i, $j], [$i, $h - 1 - $j], [$w - 1 - $i, $h - 1 - $j]] as [$px, $py]) {
                        imagesetpixel($im, $px, $py, (int) $fill);
                    }
                }
            }
        }
    }
}
