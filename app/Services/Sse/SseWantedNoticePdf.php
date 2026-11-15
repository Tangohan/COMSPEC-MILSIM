<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Support\Pdf\AthenaPdfTheme;
use App\Support\TrainingCertificatePdfEngine;

/**
 * Gabarit PDF « notice de recherche » : une page A4 (et un verso si besoin) à la mise en page d'une
 * notice rouge d'organisation internationale de police criminelle : en-tête blanc (pictogramme neutre, autorité émettrice
 * de la communauté, référence), bandeau rouge, bloc d'identité à gauche et portrait à droite, mention de danger, faits, mesures à
 * prendre, code-barres de la référence, pied de page.
 *
 * Règle de fond : le gabarit ne porte aucun logo, emblème, sceau ni nom d'organisation, de police ou de gouvernement réels :
 * l'autorité émettrice vient des réglages de la communauté (jamais un nom réel, voir SseWantedRealNames), l'emblème est un
 * pictogramme neutre dessiné ici ou, si la communauté le choisit, son propre logo. La notice ne porte aucune mention de jeu :
 * ni filigrane, ni ligne de pied, ni avertissement.
 *
 * Aucune donnée de membre réel n'entre ici : le gabarit ne reçoit que les champs de l'identité fictive et de l'avis.
 *
 * @phpstan-type Notice array{
 *   lang: string, banner: array{0: string, 1: string}, authority: array{0: string, 1: string}, ref: string,
 *   issued_at: string, expires_at: ?string, last_name: string, first_name: string, sex: string, birth_date: ?string,
 *   birth_place: ?string, nationality: string, height_cm: ?int, weight_kg: ?int, eyes: string, hair: string, marks: string,
 *   aliases: list<string>, languages: string, type_label: string, threat_level: string, threat_label: string, armed: string,
 *   reason: string, instructions: string, zone: string, last_seen_place: string, last_seen_at: ?string, contact_label: string,
 *   contact_detail: string, photo: ?string, extra_photos: list<string>, signature: ?string, emblem_path: ?string,
 *   faces: string, generated_at: string,
 *   service?: ?array{code: string, ac: string, color: ?array{0: int, 1: int, 2: int}, name?: string},
 *   classification?: array{code: string, banner: string, rgb: array{0: int, 1: int, 2: int}},
 *   reasons?: list<array{code: string, label: string}>, measures?: list<array{code: string, label: string}>,
 *   physical_rows?: list<array{field: string, label: string, value: string}>, physical_line?: string,
 *   attached?: array{main: ?string, thumbs: list<array{bytes: string, caption: string}>}
 * }
 */
final class SseWantedNoticePdf
{
    private const RED = [176, 18, 31];
    private const INK = [24, 30, 38];
    private const MUTED = [98, 108, 120];
    private const LINE = [200, 205, 211];
    private const PAPER = [247, 248, 249];

    private const MARGIN = 12.0;
    private const PAGE_W = 210.0;
    private const CONTENT_W = 186.0;

    /** Libellés bilingues : clé => [français, anglais]. */
    private const L = [
        'family' => ['Nom de famille', 'Family name'],
        'forename' => ['Prénom(s)', 'Forename(s)'],
        'sex' => ['Sexe', 'Sex'],
        'dob' => ['Date de naissance', 'Date of birth'],
        'pob' => ['Lieu de naissance', 'Place of birth'],
        'nat' => ['Nationalité', 'Nationality'],
        'height' => ['Taille', 'Height'],
        'weight' => ['Poids', 'Weight'],
        'eyes' => ['Couleur des yeux', 'Colour of eyes'],
        'hair' => ['Couleur des cheveux', 'Colour of hair'],
        'lang' => ['Langues parlées', 'Languages spoken'],
        'marks' => ['Signes particuliers', 'Distinguishing marks'],
        'alias' => ['Alias', 'Aliases'],
        'facts' => ['Faits', 'Facts'],
        'qualification' => ['Qualification', 'Category'],
        'action' => ['Mesures à prendre', 'Action to be taken'],
        'contact' => ['Service demandeur', 'Requesting service'],
        'seen' => ['Dernier lieu connu', 'Last known location'],
        'zone' => ['Pays ou zone', 'Country or area'],
        'threat' => ['Niveau de menace', 'Threat level'],
        'ref' => ['Référence', 'File no.'],
        'issued' => ['Émis le', 'Issued on'],
        'expires' => ['Valable jusqu’au', 'Valid until'],
        'photo_none' => ['Photo non disponible', 'Photograph not available'],
        'unknown' => ['Non communiqué', 'Not available'],
        'verso' => ['Compléments', 'Further details'],
        'photos' => ['Autres photographies', 'Other photographs'],
        'signature' => ['Signature connue', 'Known signature'],
        'prints' => ['Empreintes digitales', 'Fingerprints'],
        'prints_none' => ['Non communiquées', 'Not provided'],
        'general' => ['Instructions générales', 'General instructions'],
        'identity_more' => ['Renseignements complémentaires', 'Additional particulars'],
        'page' => ['Page', 'Page'],
        'reasons' => ['Motifs', 'Grounds'],
        'measures' => ['Mesures', 'Measures'],
        'physical' => ['Signalement', 'Description'],
        'physical_full' => ['Signalement physique', 'Physical description'],
    ];

    private const SEX = ['M' => ['Masculin', 'Male'], 'F' => ['Féminin', 'Female'], 'X' => ['Non précisé', 'Unspecified']];

    private const ARMED = [
        'yes' => ['ARMÉ ET DANGEREUX', 'ARMED AND DANGEROUS'],
        'no' => ['Non armé', 'Not armed'],
        'unknown' => ['Armement inconnu : prudence', 'Armament unknown: caution'],
    ];

    private const THREAT_BARS = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];

    private const GENERAL = [
        'Toute personne ou unité qui détient une information sur la personne recherchée est invitée à la transmettre au service demandeur indiqué en première page, par la voie de la chaîne de commandement. Ne tentez aucune intervention en dehors des règles d’engagement de votre mission.',
        'Anyone holding information on the person sought is asked to pass it to the requesting service named on the first page, through the chain of command. Do not attempt any intervention outside the rules of engagement of your mission.',
    ];

    private string $sans = 'freesans';

    private int $lang = 0;

    /** @var \TCPDF */
    private \TCPDF $pdf;

    /**
     * PDF de la notice (octets), ou null si le moteur PDF ou ses polices manquent.
     *
     * @param Notice $d
     */
    public function render(array $d): ?string
    {
        if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded() || !TrainingCertificatePdfEngine::tcpdfCertificateFontsReady()) {
            return null;
        }

        return TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(function () use ($d): string {
            return $this->draw($d);
        });
    }

    /**
     * La notice tiendrait-elle sur une seule page ? Faux quand un texte de la première page est coupé, qu'il y a d'autres
     * photos ou une signature : le verso est alors ajouté en mode « auto ».
     *
     * @param Notice $d
     */
    public static function needsVerso(array $d): bool
    {
        return mb_strlen(self::factsText($d), 'UTF-8') > 520 || mb_strlen(self::actionText($d), 'UTF-8') > 360 || $d['extra_photos'] !== [] || $d['signature'] !== null
            || mb_strlen($d['marks'], 'UTF-8') > 140 || count($d['aliases']) > 4 || ($d['attached']['thumbs'] ?? []) !== [];
    }

    /**
     * Faits : motifs codifiés (code et libellé), puis le texte libre de l'avis.
     *
     * @param Notice $d
     */
    public static function factsText(array $d): string
    {
        return self::withCodes($d['reasons'] ?? [], 'reasons', (string) $d['lang'], (string) $d['reason']);
    }

    /**
     * Mesures à prendre : mesures codifiées (code et libellé), puis les instructions de l'avis.
     *
     * @param Notice $d
     */
    public static function actionText(array $d): string
    {
        return self::withCodes($d['measures'] ?? [], 'measures', (string) $d['lang'], (string) $d['instructions']);
    }

    /** @param list<array{code: string, label: string}> $codes */
    private static function withCodes(array $codes, string $key, string $lang, string $text): string
    {
        if ($codes === []) {
            return $text;
        }
        $head = self::L[$key][$lang === 'en' ? 1 : 0] . ' : ' . implode(' ; ', array_map(static fn (array $c): string => $c['code'] . ' ' . $c['label'], $codes));

        return trim($text) === '' ? $head : $head . "\n" . $text;
    }

    /** @param Notice $d */
    private function draw(array $d): string
    {
        $this->sans = AthenaPdfTheme::sans();
        $this->lang = $d['lang'] === 'en' ? 1 : 0;
        // Sans le lien de la bibliothèque dans l'en-tête (propriété protégée : sous-classe anonyme).
        $pdf = new class ('P', 'mm', 'A4', true, 'UTF-8', false) extends \TCPDF {
            public function __construct(string $orientation, string $unit, string $format, bool $unicode, string $encoding, bool $diskcache)
            {
                parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache);
                $this->tcpdflink = false;
            }
        };
        $this->pdf = $pdf;
        $pdf->SetCreator('Athena');
        $pdf->SetAuthor($d['authority'][$this->lang]);
        $pdf->SetTitle($d['banner'][$this->lang] . ' ' . $d['ref']);
        $pdf->SetSubject((string) ($d['classification']['banner'] ?? ''));
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(self::MARGIN, self::MARGIN, self::MARGIN);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->setFontSubsetting(true);
        $pdf->SetCellPadding(0);
        $pdf->setCellHeightRatio(1.22);

        $verso = $d['faces'] === '2' || ($d['faces'] === 'auto' && self::needsVerso($d));
        $pages = $verso ? 2 : 1;

        $pdf->AddPage();
        $truncated = $this->front($d);
        $this->footer($d, 1, $pages);
        if ($verso) {
            $pdf->AddPage();
            $this->back($d, $truncated);
            $this->footer($d, 2, $pages);
        }

        return (string) $pdf->Output('notice.pdf', 'S');
    }

    // ------------------------------------------------------------------ Recto

    /**
     * @param Notice $d
     * @return array{reason: bool, instructions: bool, marks: bool}
     */
    private function front(array $d): array
    {
        $pdf = $this->pdf;
        $l = self::MARGIN;
        // Couleur d'en-tête du service demandeur : filet en haut de page et autorité (sobre, jamais un insigne).
        $this->classification($d);
        $tint = is_array($d['service']['color'] ?? null) ? $d['service']['color'] : self::INK;
        if ($tint !== self::INK) {
            $pdf->SetFillColor(...$tint);
            $pdf->Rect(0, 5.0, self::PAGE_W, 1.6, 'F');
        }

        // En-tête blanc : pictogramme, autorité, référence.
        $this->emblem($d, $l, 7.0, 15.0, false);
        $pdf->SetTextColor(...$tint);
        $pdf->SetFont($this->sans, 'B', 10.5);
        $pdf->SetXY($l + 19.0, 8.0);
        $pdf->Cell(112.0, 5.0, mb_strtoupper($d['authority'][$this->lang], 'UTF-8'), 0, 1, 'L');
        $pdf->SetFont($this->sans, '', 8);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($l + 19.0, 13.5);
        $pdf->Cell(112.0, 4.0, $d['authority'][1 - $this->lang], 0, 1, 'L');
        $pdf->SetFont($this->sans, '', 6.5);
        $pdf->SetXY(138.0, 7.0);
        $pdf->Cell(60.0, 3.0, mb_strtoupper($this->t('ref')[0] . ' / ' . $this->t('ref')[1], 'UTF-8'), 0, 1, 'R');
        $pdf->SetTextColor(...self::INK);
        $pdf->SetFont($this->sans, 'B', 15);
        $pdf->SetXY(138.0, 10.5);
        $pdf->Cell(60.0, 7.0, $d['ref'], 0, 1, 'R');
        $pdf->SetFont($this->sans, '', 7.5);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY(138.0, 17.5);
        $pdf->Cell(60.0, 3.5, $this->t('issued')[$this->lang] . ' ' . $this->date($d['issued_at']), 0, 1, 'R');
        $pdf->SetDrawColor(...self::LINE);
        $pdf->SetLineWidth(0.25);
        $pdf->Line($l, 25.0, self::PAGE_W - $l, 25.0);

        // Bandeau rouge.
        $pdf->SetFillColor(...self::RED);
        $pdf->Rect(0, 27.0, self::PAGE_W, 22.0, 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->setFontSpacing(1.6);
        $pdf->SetFont($this->sans, 'B', 27);
        $pdf->SetXY(0, 30.0);
        $pdf->Cell(self::PAGE_W, 12.0, $d['banner'][$this->lang], 0, 1, 'C');
        $pdf->setFontSpacing(0.6);
        $pdf->SetFont($this->sans, '', 11);
        $pdf->SetXY(0, 42.2);
        $pdf->Cell(self::PAGE_W, 5.0, $d['banner'][1 - $this->lang] === $d['banner'][$this->lang] ? '' : $d['banner'][1 - $this->lang], 0, 1, 'C');
        $pdf->setFontSpacing(0);

        // Identité (gauche) et portrait (droite).
        $top = 55.0;
        $leftW = 128.0;
        $this->label($this->t('family'), $l, $top, $leftW);
        [$name, $nameSize] = $this->fit(mb_strtoupper($d['last_name'], 'UTF-8'), $leftW, 9.0, 21.0, 11.0, 'B');
        $pdf->SetFont($this->sans, 'B', $nameSize);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY($l, $top + 4.5);
        $pdf->MultiCell($leftW, 9.0, $name, 0, 'L', false, 1, null, null, true, 0, false, true, 9.0, 'T', true);
        $this->label($this->t('forename'), $l, $top + 15.0, $leftW);
        [$first, $firstSize] = $this->fit($d['first_name'], $leftW, 6.5, 13.5, 9.0, 'B');
        $pdf->SetFont($this->sans, 'B', $firstSize);
        $pdf->SetXY($l, $top + 19.5);
        $pdf->MultiCell($leftW, 6.5, $first, 0, 'L', false, 1, null, null, true, 0, false, true, 6.5, 'T', true);

        $rows = $this->identityRows($d);
        $y = $top + 29.0;
        $marksCut = false;
        $rowMax = 168.0;
        foreach ($rows as [$labelKey, $value, $isMarks]) {
            if ($y > $rowMax) {
                break;
            }
            $max = $isMarks ? 15.0 : 7.5;
            $text = $value;
            [$text, $size, $cut] = $this->fitOrCut($text, 84.0, $max, 8.6, 6.8, '');
            $marksCut = $marksCut || ($isMarks && $cut);
            $h = max(6.6, $this->pdf->getStringHeight(84.0, $text) + 1.8);
            $this->row($this->t($labelKey), $text, $size, $l, $y, 41.0, 84.0, $h);
            $y += $h;
        }

        $this->portrait($d, 146.0, $top, 52.0, 66.0);
        $pdf->SetFont($this->sans, '', 6.5);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY(146.0, $top + 67.5);
        $pdf->Cell(52.0, 3.0, $d['ref'], 0, 0, 'C');

        // Danger : niveau de menace et armement.
        $y = max($y + 4.0, 130.0);
        $this->danger($d, $l, $y);
        $y += 16.0;

        // Lignes du service demandeur, du dernier lieu connu, de la zone et de l'expiration (réservées en bas de page).
        $line = [];
        $serviceName = (string) ($d['service']['name'] ?? '');
        $serviceLabel = $d['contact_label'] !== '' ? $d['contact_label'] : ($serviceName !== '' ? $serviceName . (($d['service']['ac'] ?? '') !== '' ? ' (' . $d['service']['ac'] . ')' : '') : '');
        if ($serviceLabel !== '' || $d['contact_detail'] !== '') {
            $line[] = $this->t('contact')[$this->lang] . ' : ' . trim($serviceLabel . ($d['contact_detail'] !== '' ? ' — ' . $d['contact_detail'] : ''));
        }
        $seen = trim($d['last_seen_place'] . ($d['last_seen_at'] !== null ? ' (' . $this->date($d['last_seen_at']) . ')' : ''));
        if ($seen !== '') {
            $line[] = $this->t('seen')[$this->lang] . ' : ' . $seen;
        }
        if ($d['zone'] !== '') {
            $line[] = $this->t('zone')[$this->lang] . ' : ' . $d['zone'];
        }
        if ($d['expires_at'] !== null) {
            $line[] = $this->t('expires')[$this->lang] . ' ' . $this->date($d['expires_at']);
        }
        $contactH = count($line) * 4.2 + ($line !== [] ? 1.5 : 0.0);

        // Faits, puis mesures à prendre : ce qui reste de la page est partagé entre les deux cadres.
        $this->heading($this->t('facts'), $l, $y);
        $y += 7.5;
        $pdf->SetFont($this->sans, 'B', 8.6);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY($l, $y);
        $pdf->Cell(self::CONTENT_W, 4.2, mb_strtoupper($this->t('qualification')[$this->lang], 'UTF-8') . ' : ' . $d['type_label'], 0, 1, 'L');
        $y += 5.5;
        $avail = max(34.0, 273.0 - $y - 7.5 - 5.0 - $contactH - 1.0);
        $actionMax = min(24.0, max(13.0, $avail * 0.34));
        $factsMax = min(41.0, $avail - $actionMax);
        [$reason, $reasonSize, $reasonCut] = $this->fitOrCut(self::factsText($d), self::CONTENT_W - 8.0, $factsMax - 3.0, 9.2, 7.2, '');
        $factsH = min($factsMax, max(18.0, $this->boxHeight($reason, $reasonSize, self::CONTENT_W - 8.0) + 6.0));
        $this->textBox($reason, $reasonSize, $l, $y, self::CONTENT_W, $factsH);
        $y += $factsH + 5.0;

        $this->heading($this->t('action'), $l, $y);
        $y += 7.5;
        [$instr, $instrSize, $instrCut] = $this->fitOrCut(self::actionText($d), self::CONTENT_W - 8.0, $actionMax - 3.0, 8.8, 7.0, '');
        $actionH = min($actionMax, max(13.0, $this->boxHeight($instr, $instrSize, self::CONTENT_W - 8.0) + 6.0));
        $this->textBox($instr, $instrSize, $l, $y, self::CONTENT_W, $actionH);
        $y += $actionH + 2.5;
        $pdf->SetFont($this->sans, '', 7.8);
        $pdf->SetTextColor(...self::INK);
        foreach ($line as $one) {
            [$one] = $this->fitOrCut($one, self::CONTENT_W, 4.0, 7.8, 6.6, '');
            $pdf->SetXY($l, $y);
            $pdf->MultiCell(self::CONTENT_W, 3.9, $one, 0, 'L', false, 1, null, null, true, 0, false, true, 4.0, 'T', true);
            $y += 4.2;
        }

        return ['reason' => $reasonCut, 'instructions' => $instrCut, 'marks' => $marksCut];
    }

    /**
     * @param Notice $d
     * @return list<array{0: string, 1: string, 2: bool}>
     */
    private function identityRows(array $d): array
    {
        $unknown = $this->t('unknown')[$this->lang];
        $rows = [];
        $rows[] = ['sex', self::SEX[$d['sex']][$this->lang] ?? self::SEX['X'][$this->lang], false];
        $rows[] = ['dob', $d['birth_date'] !== null ? $this->date($d['birth_date']) : $unknown, false];
        $rows[] = ['pob', $d['birth_place'] !== null && $d['birth_place'] !== '' ? $d['birth_place'] : $unknown, false];
        $rows[] = ['nat', $d['nationality'] !== '' ? $d['nationality'] : $unknown, false];
        if ($d['height_cm'] !== null) {
            $rows[] = ['height', $d['height_cm'] . ' cm', false];
        }
        if ($d['weight_kg'] !== null) {
            $rows[] = ['weight', $d['weight_kg'] . ' kg', false];
        }
        if ($d['eyes'] !== '') {
            $rows[] = ['eyes', $d['eyes'], false];
        }
        if ($d['hair'] !== '') {
            $rows[] = ['hair', $d['hair'], false];
        }
        if ($d['languages'] !== '') {
            $rows[] = ['lang', $d['languages'], false];
        }
        if ($d['aliases'] !== []) {
            $rows[] = ['alias', implode(' ; ', array_slice($d['aliases'], 0, 4)), true];
        }
        if ($d['marks'] !== '') {
            $rows[] = ['marks', $d['marks'], true];
        }
        if (($d['physical_line'] ?? '') !== '') {
            $rows[] = ['physical', $d['physical_line'], true];
        }

        return $rows;
    }

    // ------------------------------------------------------------------ Verso

    /**
     * @param Notice $d
     * @param array{reason: bool, instructions: bool, marks: bool} $truncated
     */
    private function back(array $d, array $truncated): void
    {
        $pdf = $this->pdf;
        $l = self::MARGIN;
        $bottom = 270.0;
        $pdf->SetFillColor(...self::RED);
        $pdf->Rect(0, 5.0, self::PAGE_W, 13.0, 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->setFontSpacing(1.0);
        $pdf->SetFont($this->sans, 'B', 13);
        $pdf->SetXY($l, 7.4);
        $pdf->Cell(110.0, 7.0, $d['banner'][$this->lang], 0, 0, 'L');
        $pdf->setFontSpacing(0);
        $pdf->SetFont($this->sans, 'B', 11);
        $pdf->SetXY(120.0, 8.0);
        $pdf->Cell(78.0, 6.0, $d['ref'], 0, 0, 'R');
        $this->classification($d);
        $pdf->SetTextColor(...self::INK);
        [$title, $titleSize] = $this->fit(mb_strtoupper($this->t('verso')[$this->lang], 'UTF-8') . ' — ' . mb_strtoupper($d['last_name'], 'UTF-8') . ' ' . $d['first_name'], self::CONTENT_W, 5.5, 10.5, 7.5, 'B');
        $pdf->SetFont($this->sans, 'B', $titleSize);
        $pdf->SetXY($l, 21.0);
        $pdf->Cell(self::CONTENT_W, 5.0, $title, 0, 1, 'L');

        $y = 31.0;
        // Textes complets (le recto les résume).
        $this->block($this->t('facts'), self::factsText($d), 9.2, 7.4, 52.0, $y);
        $this->block($this->t('action'), self::actionText($d), 8.8, 7.4, 28.0, $y);
        if (($d['physical_rows'] ?? []) !== []) {
            $phys = implode("\n", array_map(static fn (array $r): string => $r['label'] . ' : ' . $r['value'], $d['physical_rows']));
            $this->block($this->t('physical_full'), $phys, 8.8, 7.2, 30.0, $y);
        }
        $others = [];
        if (count($d['aliases']) > 4) {
            $others[] = $this->t('alias')[$this->lang] . ' : ' . implode(' ; ', $d['aliases']);
        }
        if ($truncated['marks'] || mb_strlen($d['marks'], 'UTF-8') > 140) {
            $others[] = $this->t('marks')[$this->lang] . ' : ' . $d['marks'];
        }
        if ($others !== []) {
            $this->block($this->t('identity_more'), implode("
", $others), 8.8, 7.4, 30.0, $y);
        }

        // Autres photographies.
        $thumbs = $d['attached']['thumbs'] ?? [];
        if (($d['extra_photos'] !== [] || $thumbs !== []) && $y + 52.0 <= $bottom) {
            $this->heading($this->t('photos'), $l, $y);
            $y += 7.5;
            $x = $l;
            foreach (array_slice($d['extra_photos'], 0, 3) as $photo) {
                $this->drawPhoto($photo, $x, $y, 30.0, 38.0);
                $x += 34.0;
            }
            // Pièces de l'avis : miniatures légendées (3 au plus).
            foreach (array_slice($thumbs, 0, 3) as $thumb) {
                $this->drawPhoto($thumb['bytes'], $x, $y, 30.0, 33.0);
                $pdf->SetFont($this->sans, '', 6.2);
                $pdf->SetTextColor(...self::MUTED);
                $pdf->SetXY($x, $y + 34.0);
                $pdf->MultiCell(30.0, 2.8, $thumb['caption'], 0, 'C', false, 1, null, null, true, 0, false, true, 5.6, 'T', true);
                $x += 34.0;
            }
            $y += 43.0;
        }

        // Signature et empreintes.
        if ($y + 36.0 <= $bottom) {
            $this->heading($this->t('signature'), $l, $y);
            $this->heading($this->t('prints'), $l + 96.0, $y, 90.0);
            $y += 7.5;
            $pdf->SetDrawColor(...self::LINE);
            $pdf->SetLineWidth(0.25);
            $pdf->SetFillColor(...self::PAPER);
            $pdf->Rect($l, $y, 90.0, 24.0, 'DF');
            $pdf->Rect($l + 96.0, $y, 90.0, 24.0, 'DF');
            if ($d['signature'] !== null) {
                $this->drawPhoto($d['signature'], $l + 4.0, $y + 2.0, 82.0, 20.0);
            } else {
                $this->muted($this->t('unknown')[$this->lang], $l, $y + 9.0, 90.0);
            }
            $this->muted($this->t('prints_none')[$this->lang] . ' / ' . $this->t('prints_none')[1 - $this->lang], $l + 96.0, $y + 9.0, 90.0);
            $y += 29.0;
        }

        // Instructions générales.
        if ($y + 40.0 <= $bottom) {
            $this->heading($this->t('general'), $l, $y);
            $y += 7.5;
            foreach ([self::GENERAL[$this->lang], self::GENERAL[1 - $this->lang]] as $k => $para) {
                $pdf->SetFont($this->sans, $k === 0 ? '' : 'I', 8.2);
                $pdf->SetTextColor(...($k === 0 ? self::INK : self::MUTED));
                $pdf->SetXY($l, $y);
                $pdf->MultiCell(self::CONTENT_W, 3.8, $para, 0, 'L', false, 1, null, null, true, 0, false, true, 0, 'T', false);
                $y = $pdf->GetY() + 2.0;
            }
        }
    }

    /**
     * Titre et cadre de texte du verso ; avance $y. Le texte est réduit puis coupé s'il dépasse la hauteur donnée.
     *
     * @param array{0: string, 1: string} $title
     */
    private function block(array $title, string $text, float $size, float $min, float $maxH, float &$y): void
    {
        if (trim($text) === '') {
            return;
        }
        $l = self::MARGIN;
        $this->heading($title, $l, $y);
        $y += 7.5;
        [$t, $s] = $this->fitOrCut($text, self::CONTENT_W - 8.0, $maxH - 3.0, $size, $min, '');
        $h = min($maxH, max(12.0, $this->boxHeight($t, $s, self::CONTENT_W - 8.0) + 6.0));
        $this->textBox($t, $s, $l, $y, self::CONTENT_W, $h);
        $y += $h + 5.0;
    }

    // ------------------------------------------------------------ Éléments

    /** @param Notice $d */
    private function footer(array $d, int $page, int $pages): void
    {
        $pdf = $this->pdf;
        $l = self::MARGIN;
        $pdf->SetDrawColor(...self::LINE);
        $pdf->SetLineWidth(0.25);
        $pdf->Line($l, 276.0, self::PAGE_W - $l, 276.0);
        $style = ['position' => '', 'border' => false, 'padding' => 0, 'fgcolor' => [0, 0, 0], 'bgcolor' => false, 'text' => false];
        $pdf->write1DBarcode($d['ref'], 'C128', $l, 279.0, 48.0, 8.0, 0.4, $style, 'N');
        $pdf->SetFont($this->sans, '', 6.5);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($l, 287.6);
        $pdf->Cell(48.0, 3.0, $d['ref'], 0, 0, 'L');
        $pdf->SetFont($this->sans, '', 7);
        $pdf->SetXY(150.0, 280.0);
        $pdf->Cell(48.0, 3.6, $this->t('page')[$this->lang] . ' ' . $page . '/' . $pages, 0, 1, 'R');
        $pdf->SetFont($this->sans, '', 6.4);
        $pdf->SetXY(150.0, 284.2);
        $pdf->Cell(48.0, 3.2, $d['generated_at'], 0, 0, 'R');
    }

    /**
     * Bandeaux de classification en haut et en bas de page, au marquage et à la couleur du niveau.
     *
     * @param Notice $d
     */
    private function classification(array $d): void
    {
        $c = $d['classification'] ?? null;
        if (!is_array($c) || ($c['banner'] ?? '') === '') {
            return;
        }
        $pdf = $this->pdf;
        $rgb = $c['rgb'];
        $light = ($rgb[0] * 299 + $rgb[1] * 587 + $rgb[2] * 114) / 1000 > 140;
        $pdf->SetFillColor(...$rgb);
        $pdf->SetTextColor(...($light ? [0, 0, 0] : [255, 255, 255]));
        $pdf->SetFont($this->sans, 'B', 7.4);
        $pdf->setFontSpacing(0.5);
        foreach ([0.0, 292.2] as $y) {
            $pdf->Rect(0, $y, self::PAGE_W, 4.8, 'F');
            $pdf->SetXY(0, $y + 0.8);
            $pdf->Cell(self::PAGE_W, 3.2, (string) $c['banner'], 0, 0, 'C');
        }
        $pdf->setFontSpacing(0);
    }

    /** @param Notice $d */
    private function emblem(array $d, float $x, float $y, float $size, bool $white): void
    {
        $pdf = $this->pdf;
        if ($d['emblem_path'] !== null && is_file($d['emblem_path'])) {
            try {
                $pdf->Image($d['emblem_path'], $x, $y, $size, $size, '', '', '', true, 300, '', false, false, 0, 'CM');

                return;
            } catch (\Throwable) {
                // Logo illisible : le pictogramme neutre prend le relais.
            }
        }
        // Pictogramme neutre : globe quadrillé et réticule, sans rien de réel.
        $pdf->SetDrawColor(...($white ? [255, 255, 255] : self::INK));
        $pdf->SetLineWidth(0.5);
        $c = $size / 2;
        $pdf->Circle($x + $c, $y + $c, $c - 0.5, 0, 360, 'D');
        $pdf->SetLineWidth(0.3);
        $pdf->Ellipse($x + $c, $y + $c, $c * 0.42, $c - 0.5, 0, 0, 360, 'D');
        $pdf->Line($x + 0.5, $y + $c, $x + $size - 0.5, $y + $c);
        $pdf->Line($x + $c, $y + 0.5, $x + $c, $y + $size - 0.5);
        $pdf->Line($x + 2.0, $y + $c * 0.55, $x + $size - 2.0, $y + $c * 0.55);
        $pdf->Line($x + 2.0, $y + $size - $c * 0.55, $x + $size - 2.0, $y + $size - $c * 0.55);
        $pdf->SetFillColor(...self::RED);
        $pdf->Circle($x + $c, $y + $c, 1.2, 0, 360, 'F');
    }

    private function portrait(array $d, float $x, float $y, float $w, float $h): void
    {
        $pdf = $this->pdf;
        $pdf->SetDrawColor(...self::INK);
        $pdf->SetLineWidth(0.4);
        $pdf->SetFillColor(...self::PAPER);
        $pdf->Rect($x, $y, $w, $h, 'DF');
        if (is_string($d['photo']) && $d['photo'] !== '' && $this->drawPhoto($d['photo'], $x + 0.4, $y + 0.4, $w - 0.8, $h - 0.8)) {
            $pdf->SetLineWidth(0.25);

            return;
        }
        // Silhouette neutre.
        $pdf->SetFillColor(215, 219, 224);
        $pdf->Circle($x + $w / 2, $y + $h * 0.38, $w * 0.19, 0, 360, 'F');
        $pdf->Ellipse($x + $w / 2, $y + $h * 0.82, $w * 0.34, $h * 0.2, 0, 0, 360, 'F');
        $pdf->SetFont($this->sans, '', 6.6);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($x, $y + $h - 7.0);
        $pdf->MultiCell($w, 3.0, mb_strtoupper($this->t('photo_none')[$this->lang], 'UTF-8'), 0, 'C', false, 1, null, null, true, 0, false, true, 6.0, 'T', true);
        $pdf->SetLineWidth(0.25);
    }

    /** Image binaire dans un cadre (proportions gardées). Faux si l'image est illisible. */
    private function drawPhoto(string $bytes, float $x, float $y, float $w, float $h): bool
    {
        if ($bytes === '') {
            return false;
        }
        try {
            $this->pdf->Image('@' . $bytes, $x, $y, $w, $h, '', '', '', true, 300, '', false, false, 0, 'CM');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param Notice $d */
    private function danger(array $d, float $x, float $y): void
    {
        $pdf = $this->pdf;
        $half = 90.0;
        $level = self::THREAT_BARS[$d['threat_level']] ?? 2;
        $critical = $level >= 3;
        // Niveau de menace : libellé et barres.
        $pdf->SetDrawColor(...($critical ? self::RED : self::LINE));
        $pdf->SetLineWidth($critical ? 0.5 : 0.25);
        $pdf->Rect($x, $y, $half, 12.0, 'D');
        $pdf->SetFont($this->sans, '', 6.6);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($x + 3.0, $y + 1.6);
        $pdf->Cell(60.0, 3.0, mb_strtoupper($this->t('threat')[0] . ' / ' . $this->t('threat')[1], 'UTF-8'), 0, 0, 'L');
        $pdf->SetFont($this->sans, 'B', 10.5);
        $pdf->SetTextColor(...($critical ? self::RED : self::INK));
        $pdf->SetXY($x + 3.0, $y + 5.0);
        $pdf->Cell(52.0, 5.0, mb_strtoupper($d['threat_label'], 'UTF-8'), 0, 0, 'L');
        for ($i = 1; $i <= 4; $i++) {
            $pdf->SetFillColor(...($i <= $level ? ($critical ? self::RED : self::INK) : [222, 226, 230]));
            $pdf->Rect($x + $half - 29.0 + ($i - 1) * 6.5, $y + 4.0, 5.0, 4.0 + $i * 0.5, 'F');
        }
        // Armement.
        $armed = $d['armed'];
        $labels = self::ARMED[$armed] ?? self::ARMED['unknown'];
        if ($armed === 'yes') {
            $pdf->SetFillColor(...self::RED);
            $pdf->Rect($x + $half + 6.0, $y, $half, 12.0, 'F');
            $pdf->SetTextColor(255, 255, 255);
        } else {
            $pdf->SetDrawColor(...self::LINE);
            $pdf->SetLineWidth(0.25);
            $pdf->Rect($x + $half + 6.0, $y, $half, 12.0, 'D');
            $pdf->SetTextColor(...self::INK);
        }
        $pdf->SetFont($this->sans, 'B', $armed === 'yes' ? 11.5 : 9.5);
        $pdf->SetXY($x + $half + 6.0, $y + 1.8);
        $pdf->Cell($half, 5.4, $labels[$this->lang], 0, 1, 'C');
        $pdf->SetFont($this->sans, '', 7);
        $pdf->SetXY($x + $half + 6.0, $y + 7.2);
        $pdf->Cell($half, 3.4, $labels[1 - $this->lang], 0, 0, 'C');
        $pdf->SetLineWidth(0.25);
    }

    /** @param array{0: string, 1: string} $label */
    private function label(array $label, float $x, float $y, float $w): void
    {
        $pdf = $this->pdf;
        $pdf->SetFont($this->sans, '', 6.6);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($x, $y);
        $pdf->Cell($w, 3.0, mb_strtoupper($label[$this->lang], 'UTF-8') . ' / ' . $label[1 - $this->lang], 0, 0, 'L');
    }

    /** @param array{0: string, 1: string} $label */
    private function row(array $label, string $value, float $size, float $x, float $y, float $labelW, float $valueW, float $h): void
    {
        $pdf = $this->pdf;
        $pdf->SetDrawColor(...self::LINE);
        $pdf->SetLineWidth(0.2);
        $pdf->Line($x, $y, $x + $labelW + $valueW, $y);
        $pdf->SetFont($this->sans, 'B', 7.4);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY($x, $y + 1.0);
        $pdf->Cell($labelW, 3.2, $label[$this->lang], 0, 0, 'L');
        $pdf->SetFont($this->sans, '', 6.2);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($x, $y + 4.0);
        $pdf->Cell($labelW, 2.6, $label[1 - $this->lang], 0, 0, 'L');
        $pdf->SetFont($this->sans, '', $size);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY($x + $labelW, $y + 1.0);
        $pdf->MultiCell($valueW, 3.8, $value, 0, 'L', false, 1, null, null, true, 0, false, true, max(3.8, $h - 1.4), 'T', true);
    }

    /** @param array{0: string, 1: string} $title */
    private function heading(array $title, float $x, float $y, float $w = self::CONTENT_W): void
    {
        $pdf = $this->pdf;
        $pdf->SetFillColor(...self::RED);
        $pdf->Rect($x, $y + 0.4, 1.6, 4.4, 'F');
        $pdf->SetFont($this->sans, 'B', 9.4);
        $pdf->SetTextColor(...self::INK);
        $primary = mb_strtoupper($title[$this->lang], 'UTF-8');
        $primaryW = $pdf->GetStringWidth($primary);
        $pdf->SetXY($x + 3.6, $y);
        $pdf->Cell($primaryW + 1.0, 5.0, $primary, 0, 0, 'L');
        $pdf->SetFont($this->sans, '', 7.4);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($x + 3.6 + $primaryW + 4.0, $y + 0.4);
        $pdf->Cell(max(10.0, $w - 40.0), 4.4, $title[1 - $this->lang], 0, 0, 'L');
    }

    private function textBox(string $text, float $size, float $x, float $y, float $w, float $h): void
    {
        $pdf = $this->pdf;
        $pdf->SetFillColor(...self::PAPER);
        $pdf->Rect($x, $y, $w, $h, 'F');
        $pdf->SetFillColor(...self::RED);
        $pdf->Rect($x, $y, 0.9, $h, 'F');
        $pdf->SetFont($this->sans, '', $size);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY($x + 4.0, $y + 2.0);
        $pdf->MultiCell($w - 8.0, $size * 0.45, $text, 0, 'L', false, 1, null, null, true, 0, false, true, $h - 3.0, 'T', true);
    }

    private function muted(string $text, float $x, float $y, float $w): void
    {
        $pdf = $this->pdf;
        $pdf->SetFont($this->sans, 'I', 8);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($x, $y);
        $pdf->Cell($w, 5.0, $text, 0, 0, 'C');
    }

    private function boxHeight(string $text, float $size, float $w): float
    {
        $this->pdf->SetFont($this->sans, '', $size);

        return $this->pdf->getStringHeight($w, $text);
    }

    // ------------------------------------------------------------- Mesures

    /**
     * Plus grande taille qui fait tenir le texte dans la hauteur, sans le couper (une ligne).
     *
     * @return array{0: string, 1: float}
     */
    private function fit(string $text, float $w, float $maxH, float $size, float $min, string $style): array
    {
        $text = $text !== '' ? $text : '—';
        for ($s = $size; $s >= $min; $s -= 0.5) {
            $this->pdf->SetFont($this->sans, $style, $s);
            if ($this->pdf->getStringHeight($w, $text) <= $maxH && $this->pdf->GetStringWidth($text) <= $w * 1.0) {
                return [$text, $s];
            }
        }
        $this->pdf->SetFont($this->sans, $style, $min);
        $t = $text;
        while (mb_strlen($t, 'UTF-8') > 2 && $this->pdf->GetStringWidth($t . '…') > $w) {
            $t = mb_substr($t, 0, mb_strlen($t, 'UTF-8') - 1, 'UTF-8');
        }

        return [rtrim($t) . '…', $min];
    }

    /**
     * Texte réduit puis coupé avec « … » pour tenir dans la hauteur.
     *
     * @return array{0: string, 1: float, 2: bool} texte, taille, coupé ?
     */
    private function fitOrCut(string $text, float $w, float $maxH, float $size, float $min, string $style): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['—', $size, false];
        }
        for ($s = $size; $s >= $min; $s -= 0.4) {
            $this->pdf->SetFont($this->sans, $style, $s);
            if ($this->pdf->getStringHeight($w, $text) <= $maxH) {
                return [$text, $s, false];
            }
        }
        $this->pdf->SetFont($this->sans, $style, $min);
        $t = $text;
        $guard = 0;
        while (mb_strlen($t, 'UTF-8') > 4 && $this->pdf->getStringHeight($w, $t . '…') > $maxH && $guard++ < 400) {
            $t = mb_substr($t, 0, (int) max(1, mb_strlen($t, 'UTF-8') - max(2, (int) (mb_strlen($t, 'UTF-8') * 0.05))), 'UTF-8');
        }

        return [rtrim($t) . '…', $min, true];
    }

    /** @return array{0: string, 1: string} */
    private function t(string $key): array
    {
        return self::L[$key] ?? [$key, $key];
    }

    private function date(?string $value): string
    {
        if ($value === null || $value === '') {
            return $this->t('unknown')[$this->lang];
        }
        $ts = strtotime(substr($value, 0, 10) . ' 12:00:00 UTC');
        if ($ts === false) {
            return $value;
        }
        $months = [
            ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'],
            ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
        ];
        $day = (int) gmdate('j', $ts);
        $month = $months[$this->lang][(int) gmdate('n', $ts) - 1];
        $year = gmdate('Y', $ts);

        return $this->lang === 1 ? $month . ' ' . $day . ', ' . $year : ($day === 1 ? '1er' : (string) $day) . ' ' . $month . ' ' . $year;
    }
}
