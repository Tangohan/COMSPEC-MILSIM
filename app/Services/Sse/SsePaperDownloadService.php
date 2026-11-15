<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Support\SseDocumentTypes;
use App\Support\SseIdMarking;
use App\Support\SseImageStorage;
use App\Support\TrainingCertificatePdfEngine;

/**
 * Téléchargement de la « pièce complète » d'un papier du registre : toutes les faces en PDF (A4, échelle réelle), un PNG par
 * face (300 dpi) et l'archive ZIP de ces PNG. Les faces viennent de SsePaperRenderer, la même image que l'aperçu de la page.
 *
 * Ce service ne décide d'AUCUN droit : l'appelant (SseIdentityRegistryController) a déjà vérifié la section « papiers », la
 * communauté, l'habilitation du papier et le droit sur les images d'un membre, et passe ici $imagesAllowed. Une face qui ne peut
 * pas être rendue complètement (photo absente, image refusée, fond manquant, bande de lecture vide) est quand même produite
 * avec le repli habituel, et chaque repli est listé dans warnings() : jamais un fichier vide ni une erreur muette.
 */
final class SsePaperDownloadService
{
    public const FORMATS = ['pdf', 'zip', 'png'];

    /** Plafond de lecture d'une image source (octets) : celles du registre sont déjà bornées à 4 Mo à l'envoi. */
    private const MAX_SOURCE = 6 * 1024 * 1024;

    public function __construct(private ?SsePaperRenderer $renderer = null)
    {
        $this->renderer ??= new SsePaperRenderer();
    }

    /** Faisable sur ce serveur (GD, FreeType, polices). */
    public static function available(): bool
    {
        return SsePaperRenderer::available();
    }

    /**
     * Nom de fichier sûr : « DOC-000001_US47036925_recto.png ». Seuls A-Z a-z 0-9 - _ restent, aucun point doublé, aucune barre.
     *
     * @param array<string, mixed> $doc
     */
    public static function filename(array $doc, string $part, string $ext): string
    {
        $ref = trim((string) preg_replace('/[^A-Za-z0-9-]+/', '', (string) ($doc['ref'] ?? '')), '-');
        $num = trim((string) preg_replace('/[^A-Za-z0-9]+/', '', (string) ($doc['doc_number'] ?? '')));
        $part = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '', $part), '_-');
        $ext = (string) preg_replace('/[^a-z0-9]+/', '', strtolower($ext));

        return ($ref !== '' ? $ref : 'DOC') . '_' . ($num !== '' ? substr($num, 0, 40) : 'papier') . '_' . ($part !== '' ? $part : 'papier') . '.' . ($ext !== '' ? $ext : 'bin');
    }

    /**
     * Ce que contient le téléchargement et ce qui sera remplacé, sans lire ni décoder aucune image.
     *
     * @param array<string, mixed> $doc
     * @param array<string, mixed> $identity identité NON masquée (chemins d'origine)
     * @return array{faces: array<string, string>, doc_type: string, size_mm: array{0: float, 1: float}, size_px: array{0: int, 1: int}, warnings: list<string>, real: bool}
     */
    public function plan(array $doc, array $identity, ?array $country, bool $imagesAllowed): array
    {
        $type = (string) ($doc['doc_type'] ?? 'id_card');
        $warnings = [];
        $hasPhotoFile = $this->readable((string) ($identity['photo_path'] ?? '')) !== null;
        $hasPhotoRaw = trim((string) ($identity['photo_path'] ?? '')) !== '';
        if (!$imagesAllowed && ($hasPhotoRaw || trim((string) ($identity['signature_path'] ?? '')) !== '')) {
            $warnings[] = 'Photo et signature de ce titulaire réservées : silhouette et signature imprimée à la place.';
        } elseif (!$hasPhotoRaw) {
            $warnings[] = 'Aucune photo enregistrée pour ce titulaire : silhouette à la place.';
        } elseif (!$hasPhotoFile) {
            $warnings[] = 'Le fichier de la photo est introuvable : silhouette à la place.';
        }
        if (($doc['layout'] ?? '') === 'custom' && ($doc['background_path'] ?? '') !== '' && $this->readable((string) $doc['background_path']) === null) {
            $warnings[] = 'L’image de fond du gabarit est introuvable : fond uni à la place.';
        }
        $mrzType = (string) ((SseDocumentTypes::get($type) ?? [])['mrz'] ?? 'td1');
        if ($mrzType !== '' && trim((string) ($doc['doc_number'] ?? '')) === '') {
            $warnings[] = 'Numéro du papier manquant : la bande de lecture est incomplète.';
        }

        return [
            'faces' => SsePaperRenderer::faces($type),
            'doc_type' => $type,
            'size_mm' => SsePaperRenderer::sizeMm($type),
            'size_px' => SsePaperRenderer::sizePx($type, SsePaperRenderer::DPI_PRINT),
            'warnings' => $warnings,
            'real' => SseIdMarking::isRealCountry($country, (string) ($doc['country_code'] ?? '')),
        ];
    }

    /**
     * Images à poser sur les faces (photo, signature, fond, drapeau), décodées une fois.
     *
     * @param array<string, mixed> $doc
     * @param array<string, mixed> $identity identité NON masquée
     * @return array{photo?: \GdImage, signature?: \GdImage, background?: \GdImage, flag?: \GdImage}
     */
    public function assets(array $doc, array $identity, ?array $country, bool $imagesAllowed): array
    {
        $out = [];
        if ($imagesAllowed) {
            foreach (['photo', 'signature'] as $kind) {
                $img = $this->load((string) ($identity[$kind . '_path'] ?? ''));
                if ($img !== null) {
                    $out[$kind] = $img;
                }
            }
        }
        if (($doc['layout'] ?? '') === 'custom') {
            $bg = $this->load((string) ($doc['background_path'] ?? ''));
            if ($bg !== null) {
                $out['background'] = $bg;
            }
        }
        if (empty($country['real_sim'])) {
            $flag = $this->load((string) ($country['flag_path'] ?? ''));
            if ($flag !== null) {
                $out['flag'] = $flag;
            }
        }

        return $out;
    }

    /**
     * Une face en PNG.
     *
     * @param array<string, mixed> $model SseIdWorkshopService::previewModel()
     * @param array<string, \GdImage> $assets
     */
    public function png(array $model, array $assets, string $face, int $dpi = SsePaperRenderer::DPI_PRINT, bool $transparent = true): string
    {
        return $this->renderer->render($model, $assets, $face, $dpi, $transparent);
    }

    /**
     * Toutes les faces en ZIP (un PNG par face, noms sûrs ; AVERTISSEMENTS.txt s'il y a des replis).
     *
     * @param array<string, mixed> $doc
     * @param array<string, mixed> $model
     * @param array<string, \GdImage> $assets
     * @param list<string> $warnings
     */
    public function zip(array $doc, array $model, array $assets, array $warnings): string
    {
        $files = [];
        foreach (array_keys(SsePaperRenderer::faces((string) $model['doc_type'])) as $face) {
            $files[self::filename($doc, $face, 'png')] = $this->png($model, $assets, $face);
        }
        if ($warnings !== []) {
            $files['AVERTISSEMENTS.txt'] = "Remarques sur ce téléchargement\r\n\r\n- " . implode("\r\n- ", $warnings) . "\r\n";
        }

        return self::zipStore($files);
    }

    /**
     * Toutes les faces en un PDF A4 à l'échelle réelle (1:1), faces empilées avec leur légende, remarques en fin de page.
     *
     * @param array<string, mixed> $doc
     * @param array<string, mixed> $model
     * @param array<string, \GdImage> $assets
     * @param list<string> $warnings
     * @return string|null null si TCPDF ou ses polices manquent
     */
    public function pdf(array $doc, array $model, array $assets, array $warnings, string $holder, ?string $generatedAt = null): ?string
    {
        if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded() || !TrainingCertificatePdfEngine::tcpdfCertificateFontsReady()) {
            return null;
        }
        $type = (string) $model['doc_type'];
        $faces = SsePaperRenderer::faces($type);
        $real = !empty($model['country']['real_sim']);
        $generatedAt ??= gmdate('d/m/Y H:i') . ' UTC';
        // Les faces sont dessinées AVANT de créer le document : TCPDF passe mb_internal_encoding en ASCII le temps de sa vie,
        // ce qui casserait les accents du rendu.
        $pngs = [];
        foreach (array_keys($faces) as $key) {
            $pngs[$key] = $this->renderer->render($model, $assets, (string) $key, SsePaperRenderer::DPI_PRINT, false);
        }

        return TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(function () use ($doc, $model, $warnings, $holder, $generatedAt, $type, $faces, $real, $pngs): string {
            $pdf = new class ('P', 'mm', 'A4', true, 'UTF-8', false) extends \TCPDF {
                public function __construct(string $o, string $u, string $f, bool $uni, string $enc, bool $disk)
                {
                    parent::__construct($o, $u, $f, $uni, $enc, $disk);
                    $this->tcpdflink = false;
                }
            };
            $sans = \App\Support\Pdf\AthenaPdfTheme::sans();
            [$fw, $fh] = SsePaperRenderer::sizeMm($type);
            $ref = (string) ($doc['ref'] ?? '');
            $title = trim($ref . ' — ' . (string) ($doc['doc_type_label'] ?? ''));
            $pdf->SetCreator('Athena');
            $pdf->SetAuthor('Athena');
            $pdf->SetTitle($title);
            $pdf->SetSubject($title);
            if ($real) {
                // Même mention que dans les images : métadonnées du fichier d'un pays réel stylisé (SseIdMarking).
                $pdf->SetKeywords(SseIdMarking::META);
            }
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetAutoPageBreak(false, 0);
            $pdf->setFontSubsetting(true);
            $pdf->SetCellPadding(0);
            $pdf->AddPage();

            $ink = [29, 39, 51];
            $muted = [96, 105, 116];
            $pdf->SetTextColor(...$ink);
            $pdf->SetFont($sans, 'B', 15);
            $pdf->SetXY(15, 15);
            $pdf->Cell(180, 7, $title, 0, 1, 'L');
            $pdf->SetFont($sans, '', 9.5);
            $pdf->SetTextColor(...$muted);
            $sub = trim((string) ($model['country']['name'] ?? '') . ' · N° ' . (string) ($doc['doc_number'] ?? '') . ($holder !== '' ? ' · ' . $holder : ''), ' ·');
            $pdf->SetXY(15, 23);
            $pdf->Cell(180, 5, $sub, 0, 1, 'L');
            $names = implode(', ', array_values($faces));
            $pdf->SetXY(15, 28.5);
            $pdf->Cell(180, 5, 'Faces incluses : ' . $names . ' · échelle réelle 1:1 (' . number_format($fw, 1, ',', '') . ' × ' . number_format($fh, 1, ',', '') . ' mm) · 300 dpi', 0, 1, 'L');
            $pdf->SetDrawColor(210, 214, 220);
            $pdf->Line(15, 35.5, 195, 35.5);

            $y = 41.0;
            $x = (210 - $fw) / 2;
            $page = 1;
            foreach ($faces as $key => $label) {
                if ($y + $fh + 9 > 282) {
                    $pdf->AddPage();
                    ++$page;
                    $y = 15.0;
                }
                $pdf->SetFont($sans, 'B', 9);
                $pdf->SetTextColor(...$ink);
                $pdf->SetXY($x, $y);
                $pdf->Cell($fw, 4.5, $label, 0, 0, 'L');
                $pdf->Image('@' . $pngs[$key], $x, $y + 5.5, $fw, $fh, 'PNG', '', '', false, 300);
                $pdf->SetDrawColor(190, 194, 200);
                $pdf->SetLineWidth(0.15);
                $pdf->RoundedRect($x, $y + 5.5, $fw, $fh, $type === 'passport' ? 1.4 : 3.2, '1111', 'D');
                $y += $fh + 12;
            }
            if ($warnings !== []) {
                if ($y + 8 + count($warnings) * 5.2 > 285) {
                    $pdf->AddPage();
                    $y = 15.0;
                }
                $pdf->SetFont($sans, 'B', 9);
                $pdf->SetTextColor(...$ink);
                $pdf->SetXY(15, $y);
                $pdf->Cell(180, 5, 'Remarques', 0, 1, 'L');
                $pdf->SetFont($sans, '', 8.5);
                $pdf->SetTextColor(...$muted);
                $yy = $y + 5.5;
                foreach ($warnings as $w) {
                    $pdf->SetXY(15, $yy);
                    $pdf->MultiCell(180, 4.4, '• ' . $w, 0, 'L');
                    $yy = $pdf->GetY() + 0.8;
                }
            }
            for ($p = 1; $p <= $pdf->getNumPages(); ++$p) {
                $pdf->setPage($p);
                $pdf->SetFont($sans, '', 7.5);
                $pdf->SetTextColor(...$muted);
                $pdf->SetXY(15, 288);
                $pdf->Cell(150, 4, $ref . ' · ' . $generatedAt, 0, 0, 'L');
                $pdf->Cell(30, 4, 'Page ' . $p . '/' . $pdf->getNumPages(), 0, 0, 'R');
            }

            return (string) $pdf->Output('papier.pdf', 'S');
        });
    }

    // ------------------------------------------------------------------ Outils

    private function readable(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }
        $abs = SseImageStorage::readable($path);

        return $abs !== null && is_file($abs) && filesize($abs) > 0 && filesize($abs) <= self::MAX_SOURCE ? $abs : null;
    }

    private function load(string $path): ?\GdImage
    {
        $abs = $this->readable($path);
        if ($abs === null || SseImageStorage::verifiedMime($abs) === null || !function_exists('imagecreatefromstring')) {
            return null;
        }
        $img = @imagecreatefromstring((string) file_get_contents($abs));

        return $img instanceof \GdImage ? $img : null;
    }

    /**
     * Archive ZIP sans compression (méthode « stored ») : aucune extension requise, lisible partout.
     *
     * @param array<string, string> $files nom => contenu
     */
    public static function zipStore(array $files): string
    {
        $time = (int) gmdate('H') << 11 | (int) gmdate('i') << 5 | (int) ((int) gmdate('s') / 2);
        $date = ((int) gmdate('Y') - 1980) << 9 | (int) gmdate('n') << 5 | (int) gmdate('j');
        $body = '';
        $central = '';
        $n = 0;
        foreach ($files as $name => $data) {
            $name = (string) preg_replace('/[^A-Za-z0-9._-]+/', '_', $name);
            $crc = crc32($data);
            $len = strlen($data);
            $offset = strlen($body);
            $body .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 0, $time, $date, $crc, $len, $len, strlen($name), 0) . $name . $data;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 0, $time, $date, $crc, $len, $len, strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
            ++$n;
        }

        return $body . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, $n, $n, strlen($central), strlen($body), 0);
    }
}
