<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Support\Pdf\AthenaPdfTheme;
use App\Support\SseUsClassification;
use App\Support\SseSheetText;
use App\Support\TrainingCertificatePdfEngine;

/**
 * Gabarit PDF des fiches d'une personne (identification, vigilance, renseignement, contrôle terrain) :
 * même moteur (TCPDF) et même identité visuelle que la notice de recherche (SseWantedNoticePdf) : en-tête blanc avec
 * pictogramme neutre et autorité émettrice fictive, bandeau de couleur du modèle, blocs d'information, code-barres de la
 * référence et pied de page. Une à trois pages A4, la suite passant sur la page suivante.
 *
 * Règle de fond : aucun logo, emblème, sceau ni nom d'organisation, de police ou de gouvernement réels (service émetteur du
 * catalogue de la communauté). Le document ne porte aucune mention de jeu : ni filigrane, ni ligne de pied, ni avertissement.
 *
 * Le gabarit ne reçoit que des données DÉJÀ caviardées au niveau de diffusion (SseSheetFactsService::mask) et des textes déjà
 * assainis (SseSheetText::clean) : il n'interprète jamais de HTML saisi.
 *
 * @phpstan-type Sheet array<string, mixed>
 */
final class SseSheetPdf
{
    use SsePdfAlertArt;
    use SseSheetPdfVigilance;
    use SseSheetPdfRedaction;

    private const INK = [24, 30, 38];
    private const MUTED = [98, 108, 120];
    private const LINE = [200, 205, 211];
    private const PAPER = [247, 248, 249];
    private const RED = [176, 18, 31];

    private const MARGIN = 12.0;
    private const PAGE_W = 210.0;
    private const CONTENT_W = 186.0;
    private const BOTTOM = 268.0;

    /** Hauteur des bandeaux de classification (haut et bas de chaque page). */
    private const BAND_H = 6.4;
    private const PAGE_H = 297.0;

    public const MAX_PAGES = 3;

    /** Caractère de caviardage des données (SseRedactionService) : jamais écrit dans le PDF, remplacé par un rectangle noir. */
    private const BLOCK = "\u{2588}";

    /** Largeur (mm) d'un rectangle de caviardage : valeur par défaut du champ, jamais dérivée de la valeur masquée. */
    private const BAR_W = 16.0;

    /** Couleur du bandeau par modèle. */
    public const ACCENTS = ['identification' => [27, 79, 114], 'vigilance' => [176, 92, 8], 'profile' => [55, 65, 81], 'control' => [15, 118, 110]];

    /** Libellés bilingues : clé => [français, anglais]. */
    private const L = [
        'family' => ['Nom de famille', 'Family name'], 'forename' => ['Prénom(s)', 'Forename(s)'], 'sex' => ['Sexe', 'Sex'],
        'dob' => ['Date de naissance', 'Date of birth'], 'pob' => ['Lieu de naissance', 'Place of birth'], 'nat' => ['Nationalité', 'Nationality'],
        'age' => ['Âge estimé', 'Estimated age'], 'lang' => ['Langue', 'Language'], 'height' => ['Taille', 'Height'], 'weight' => ['Poids', 'Weight'],
        'build' => ['Corpulence', 'Build'], 'eyes' => ['Yeux', 'Eyes'], 'hair' => ['Cheveux', 'Hair'], 'marks' => ['Signes particuliers', 'Distinguishing marks'],
        'alias' => ['Alias', 'Aliases'], 'affil' => ['Affiliation', 'Affiliation'], 'idoc' => ['Pièce d’identité', 'ID document'],
        'bio' => ['Identifiants et biométrie', 'Identifiers and biometrics'], 'prints' => ['Empreintes', 'Fingerprints'], 'iris' => ['Iris', 'Iris'], 'dna' => ['ADN', 'DNA'],
        'quality' => ['Qualité moyenne', 'Average quality'], 'tier' => ['Niveau d’identité', 'Identity tier'], 'subject' => ['Identifiant sujet', 'Subject ID'],
        'seen' => ['Dernière observation', 'Last sighting'], 'where' => ['Lieu', 'Place'], 'when' => ['Date', 'Date'], 'zone' => ['Zone', 'Area'],
        'threat' => ['Niveau de menace', 'Threat level'], 'armed' => ['Armement', 'Armament'], 'ref' => ['Référence', 'Ref.'], 'issued' => ['Émis le', 'Issued on'],
        'valid' => ['Valable jusqu’au', 'Valid until'], 'validity' => ['Validité', 'Validity'], 'diffusion' => ['Diffusion', 'Distribution'],
        'vehicles' => ['Véhicules', 'Vehicles'], 'addresses' => ['Adresses', 'Addresses'], 'phones' => ['Téléphones et courriels', 'Phones and e-mails'],
        'linked' => ['Personnes liées', 'Linked persons'], 'weapons' => ['Armes', 'Weapons'], 'equipment' => ['Équipement', 'Equipment'],
        'circ' => ['Circonstances', 'Circumstances'], 'stmt' => ['Déclarations', 'Statements'], 'checks' => ['Contrôles', 'Checks'],
        'c_date' => ['Date', 'Date'], 'c_place' => ['Lieu', 'Place'], 'c_doc' => ['Papier', 'Document'], 'c_levels' => ['Vérifications', 'Verifications'], 'c_verdict' => ['Résultat', 'Result'],
        'c_presented' => ['Identité présentée', 'Presented identity'], 'c_by' => ['Contrôleur', 'Checked by'], 'none' => ['Aucun', 'None'], 'unknown' => ['Non communiqué', 'Not available'],
        'photo_none' => ['Photo non disponible', 'Photograph not available'], 'page' => ['Page', 'Page'], 'more' => ['Autres photographies', 'Other photographs'],
        'cont' => ['suite', 'continued'], 'cut' => ['Texte coupé : trop long pour la mise en page', 'Text cut: too long for the layout'],
        'issuer' => ['Service émetteur', 'Issuing service'], 'about' => ['Informations sur le document', 'About this document'],
        'to' => ['À', 'TO'], 'from' => ['DE', 'FROM'], 'subj' => ['OBJET', 'SUBJ'], 'dist' => ['DIFFUSION', 'DIST'], 'dtg' => ['GDH', 'DTG'],
        'control_no' => ['N° de contrôle', 'Control no.'], 'no_data' => ['Aucune donnée', 'No data'], 'photo' => ['Photo', 'Photo'],
        'source' => ['Source et fiabilité', 'Source and reliability'], 'reliab' => ['Fiabilité de la source', 'Source reliability'], 'cred' => ['Crédibilité de l’information', 'Information credibility'],
        'classified_by' => ['Classifié par', 'Classified by'], 'derived' => ['Dérivé de', 'Derived from'], 'declass' => ['Déclassification', 'Declassify on'],
        'sources_multi' => ['Sources multiples', 'Multiple sources'], 'declass_na' => ['Sans objet', 'N/A'],
        'physical_line' => ['Signalement', 'Description'],
    ];

    /** Fiabilité de la source (A à F) et crédibilité de l'information (1 à 6), échelle de l'Amirauté : [fr, en]. */
    private const RELIABILITY = ['A' => ['Totalement fiable', 'Completely reliable'], 'B' => ['Habituellement fiable', 'Usually reliable'], 'C' => ['Assez fiable', 'Fairly reliable'], 'D' => ['Peu fiable', 'Not usually reliable'], 'E' => ['Non fiable', 'Unreliable'], 'F' => ['Ne peut être jugée', 'Cannot be judged']];

    private const CREDIBILITY = [1 => ['Confirmée', 'Confirmed'], 2 => ['Probablement vraie', 'Probably true'], 3 => ['Possiblement vraie', 'Possibly true'], 4 => ['Douteuse', 'Doubtful'], 5 => ['Improbable', 'Improbable'], 6 => ['Ne peut être jugée', 'Cannot be judged']];

    private const SEX = ['M' => ['Masculin', 'Male'], 'F' => ['Féminin', 'Female'], 'X' => ['Non précisé', 'Unspecified']];

    private const ARMED = ['yes' => ['Armé et dangereux', 'Armed and dangerous'], 'no' => ['Non armé', 'Not armed'], 'unknown' => ['Inconnu : prudence', 'Unknown: caution']];

    private const THREAT = ['low' => [1, ['Faible', 'Low']], 'medium' => [2, ['Moyen', 'Medium']], 'high' => [3, ['Élevé', 'High']], 'critical' => [4, ['Critique', 'Critical']]];

    private string $sans = 'freesans';

    private int $lang = 0;

    /** @var \TCPDF */
    private \TCPDF $pdf;

    /** @var array<string, mixed> */
    private array $d = [];

    private float $y = 0.0;

    private int $pages = 1;

    /** Numéro du dernier paragraphe titré. */
    private int $n = 0;

    /** @var list<string> */
    private array $overflow = [];

    /** @var list<array{0: string, 1: string}> sections sans donnée, regroupées en une ligne */
    private array $none = [];

    /**
     * PDF de la fiche, ou null si le moteur PDF ou ses polices manquent.
     *
     * @param Sheet $d
     * @return array{bytes: string, pages: int, overflow: list<string>, redaction: array{total: int, by: array<string, int>}}|null
     */
    public function render(array $d): ?array
    {
        if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded() || !TrainingCertificatePdfEngine::tcpdfCertificateFontsReady()) {
            return null;
        }

        return TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(function () use ($d): array {
            // Première passe : compte les barres réellement dessinées. S'il y en a, seconde passe avec le bloc d'explication et les lettres de repère.
            $this->rxCounts = [];
            $this->rxStart(null);
            $out = $this->draw($d);
            $seen = array_keys(array_filter($this->rxCounts, static fn (int $n): bool => $n > 0));
            if ($seen !== []) {
                $this->rxStart(\App\Support\SseRedactionExplain::letters($seen));
                $out = $this->draw($d);
            }
            $out['redaction'] = $this->rxResult();

            return $out;
        });
    }

    /** @param Sheet $d @return array{bytes: string, pages: int, overflow: list<string>} */
    private function draw(array $d): array
    {
        $this->d = $d;
        $this->sans = AthenaPdfTheme::sans();
        $this->lang = $d['lang'] === 'en' ? 1 : 0;
        $this->overflow = [];
        $this->none = [];
        $this->n = 0;
        $this->pages = 1;
        $this->rxCounts = [];
        $this->rxMeasuring = 0;
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
        $pdf->SetTitle($d['title'][$this->lang] . ' ' . $d['ref']);
        $pdf->SetSubject(SseUsClassification::banner($this->level()));
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(self::MARGIN, self::MARGIN, self::MARGIN);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->setFontSubsetting(true);
        $pdf->SetCellPadding(0);
        $pdf->setCellHeightRatio(1.22);

        $pdf->AddPage();
        if ($d['model'] === 'vigilance') {
            $this->vigilanceFront();
        } else {
            $this->front();
        }
        $this->rxBlock();
        match ($d['model']) {
            'identification' => $this->identification(),
            'vigilance' => $this->vigilance(),
            'profile' => $this->profile(),
            default => $this->control(),
        };
        $this->noData();
        if ($d['faces'] === '2' && $this->pages === 1) {
            $this->newPage();
        }
        $this->about();
        for ($p = 1; $p <= $this->pages; ++$p) {
            $pdf->setPage($p);
            $this->footer($p);
        }

        return ['bytes' => (string) $pdf->Output('fiche.pdf', 'S'), 'pages' => $this->pages, 'overflow' => array_values(array_unique($this->overflow))];
    }

    // ------------------------------------------------------------------ Pages

    private function newPage(): bool
    {
        $max = $this->d['faces'] === '1' ? 1 : self::MAX_PAGES;
        if ($this->pages >= $max) {
            return false;
        }
        ++$this->pages;
        $pdf = $this->pdf;
        $pdf->AddPage();
        $this->band(true);
        $pdf->SetFont($this->sans, 'B', 8.5);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY(self::MARGIN, 9.6);
        $pdf->Cell(110.0, 4.6, mb_strtoupper($this->d['title'][$this->lang], 'UTF-8') . ' · ' . $this->t('cont')[$this->lang], 0, 0, 'L');
        $pdf->SetFont($this->sans, '', 8);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY(self::MARGIN + 100.0, 9.6);
        $pdf->Cell(self::CONTENT_W - 100.0, 4.6, $this->t('control_no')[$this->lang] . ' ' . $this->d['ref'], 0, 0, 'R');
        $pdf->SetDrawColor(...self::INK);
        $pdf->SetLineWidth(0.4);
        $pdf->Line(self::MARGIN, 15.0, self::MARGIN + self::CONTENT_W, 15.0);
        $pdf->SetLineWidth(0.25);
        $this->y = 19.0;

        return true;
    }

    /** Réserve $h mm ; ouvre une page si besoin. Faux si la page suivante est interdite (le contenu sera coupé). */
    private function ensure(float $h): bool
    {
        if ($this->y + $h <= self::BOTTOM) {
            return true;
        }

        return $this->newPage() && $this->y + $h <= self::BOTTOM;
    }

    // ------------------------------------------------------------ Page de tête

    private function front(): void
    {
        $d = $this->d;
        $pdf = $this->pdf;
        $l = self::MARGIN;
        $this->band(true);
        // Service émetteur : emblème vectoriel générique, nom entier (réduit pour tenir sur la ligne, jamais coupé).
        $this->emblem($l, 9.6, 13.0);
        $nameW = 124.0;
        $name = mb_strtoupper($d['authority'][$this->lang], 'UTF-8');
        $size = 11.0;
        $pdf->SetFont($this->sans, 'B', $size);
        while ($size > 6.8 && $pdf->GetStringWidth($name) > $nameW) {
            $size -= 0.3;
            $pdf->SetFont($this->sans, 'B', $size);
        }
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY($l + 16.0, 11.6);
        $pdf->Cell($nameW, 5.0, $name, 0, 1, 'L');
        $pdf->SetFont($this->sans, '', 7.8);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($l + 16.0, 17.2);
        $pdf->Cell($nameW, 4.0, $d['service'] !== '' ? $this->t('issuer')[$this->lang] . ' : ' . $d['service'] : $d['authority'][1 - $this->lang], 0, 1, 'L');
        // Numéro de contrôle et groupe date-heure.
        $x = 148.0;
        $pdf->SetDrawColor(...self::INK);
        $pdf->SetLineWidth(0.3);
        $pdf->Rect($x, 9.6, 50.0, 14.0, 'D');
        $pdf->SetFont($this->sans, '', 6.2);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($x + 2.0, 10.4);
        $pdf->Cell(46.0, 2.8, mb_strtoupper($this->t('control_no')[$this->lang] . ' / ' . $this->t('control_no')[1 - $this->lang], 'UTF-8'), 0, 0, 'L');
        $pdf->SetFont($this->sans, 'B', 10.5);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY($x + 2.0, 13.4);
        $pdf->Cell(46.0, 5.0, $d['ref'], 0, 0, 'L');
        $pdf->SetFont($this->sans, '', 7.2);
        $pdf->SetXY($x + 2.0, 19.0);
        $pdf->Cell(46.0, 3.6, 'DTG ' . $this->dtg(), 0, 0, 'L');
        $pdf->SetLineWidth(0.25);

        // Titre du document.
        $pdf->SetDrawColor(...self::INK);
        $pdf->SetLineWidth(0.6);
        $pdf->Line($l, 26.0, $l + self::CONTENT_W, 26.0);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetFont($this->sans, 'B', 15.0);
        $pdf->SetXY($l, 27.6);
        $pdf->Cell(118.0, 6.4, mb_strtoupper($d['title'][$this->lang], 'UTF-8'), 0, 1, 'L');
        $pdf->SetFont($this->sans, '', 8.0);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($l, 34.0);
        $pdf->Cell(118.0, 4.0, mb_strtoupper($d['title'][1 - $this->lang], 'UTF-8'), 0, 0, 'L');
        $pdf->SetFont($this->sans, '', 7.6);
        $pdf->SetXY($l + 118.0, 28.4);
        $pdf->Cell(68.0, 4.0, $this->t('issued')[$this->lang] . ' ' . $this->date((string) $d['issued_at']), 0, 1, 'R');
        if (($d['valid_until'] ?? null) !== null) {
            $pdf->SetFont($this->sans, 'B', 8.0);
            $pdf->SetTextColor(...self::INK);
            $pdf->SetXY($l + 108.0, 33.2);
            $pdf->Cell(78.0, 4.0, $this->t('valid')[$this->lang] . ' ' . $this->date((string) $d['valid_until']), 0, 0, 'R');
        }
        $this->y = $this->memo(40.0) + 4.0;
    }

    /** Bloc TO / FROM / SUBJ / DIST d'un rapport d'information. Retourne le bas du bloc. */
    private function memo(float $y): float
    {
        $d = $this->d;
        $pdf = $this->pdf;
        $l = self::MARGIN;
        $f = $this->facts();
        $name = trim(trim((string) ($f['first_name'] ?? '')) . ' ' . trim((string) ($f['last_name'] ?? '')));
        $name = str_contains($name, self::BLOCK) ? '' : $name;
        $subject = mb_strtoupper($d['title'][$this->lang], 'UTF-8') . ($name !== '' ? ' — ' . $name : '');
        $from = $d['service'] !== '' ? $d['service'] . ' — ' . $d['authority'][$this->lang] : $d['authority'][$this->lang];
        $to = $this->lang === 1 ? 'Cleared personnel — SSE field distribution' : 'Personnel habilité — diffusion SSE de terrain';
        $dist = implode(' / ', SseUsClassification::dissemination($this->level())) . ' · ' . $d['classification'][$this->lang];
        $rows = [['to', $to], ['from', $from], ['subj', $subject], ['dist', $dist]];
        $pdf->SetDrawColor(...self::LINE);
        $pdf->SetLineWidth(0.2);
        foreach ($rows as [$key, $value]) {
            $pdf->Line($l, $y, $l + self::CONTENT_W, $y);
            $pdf->SetFont(AthenaPdfTheme::mono(), 'B', 8.2);
            $pdf->SetTextColor(...self::INK);
            $pdf->SetXY($l, $y + 1.1);
            $pdf->Cell(20.0, 4.0, $this->t($key)[$this->lang] . ':', 0, 0, 'L');
            [$text, $size] = $this->fit((string) $value, self::CONTENT_W - 22.0, 9.0, 6.8);
            $pdf->SetFont($this->face($text), '', $size);
            $pdf->SetXY($l + 21.0, $y + 0.9);
            $pdf->Cell(self::CONTENT_W - 22.0, 4.4, $text, 0, 0, 'L');
            $y += 5.6;
        }
        $pdf->Line($l, $y, $l + self::CONTENT_W, $y);
        $pdf->SetLineWidth(0.25);

        return $y;
    }

    /** Une ligne qui tient dans $w : taille réduite jusqu'à $min, puis points de suspension. @return array{0: string, 1: float} */
    private function fit(string $text, float $w, float $size, float $min): array
    {
        $pdf = $this->pdf;
        for ($s = $size; $s >= $min; $s -= 0.3) {
            $pdf->SetFont($this->face($text), '', $s);
            if ($pdf->GetStringWidth($text) <= $w) {
                return [$text, $s];
            }
        }
        $pdf->SetFont($this->sans, '', $min);
        $t = $text;
        while (mb_strlen($t, 'UTF-8') > 4 && $pdf->GetStringWidth($t . '…') > $w) {
            $t = mb_substr($t, 0, mb_strlen($t, 'UTF-8') - 2, 'UTF-8');
        }

        return [rtrim($t) . '…', $min];
    }

    /** Identité, signalement et portrait : bloc commun. Sans photo : pas de cadre, la grille prend toute la largeur. */
    private function identityBlock(bool $full): void
    {
        $f = $this->facts();
        $l = self::MARGIN;
        $top = $this->y;
        $hasPhoto = $this->hasPhoto();
        $w = $hasPhoto ? 142.0 : self::CONTENT_W;
        $cols = $hasPhoto ? 2 : 3;
        if ($hasPhoto) {
            $this->portrait($l + 146.0, $top, 40.0, 50.0);
        }
        $sex = self::SEX[$f['sex']] ?? self::SEX['X'];
        $age = $f['age_estimated'] !== null ? $f['age_estimated'] . ($this->lang === 1 ? ' yrs' : ' ans') : '';
        $items = [
            [$this->t('family'), (string) $f['last_name']], [$this->t('forename'), (string) $f['first_name']],
            [$this->t('sex'), $sex[$this->lang] . ($age !== '' ? ' · ' . $age : '')], [$this->t('dob'), $this->dateOrRaw((string) $f['birth_date'])],
            [$this->t('pob'), (string) $f['birth_place']], [$this->t('nat'), (string) $f['nationality']],
        ];
        $y = $this->grid($items, $l, $top, $w, $cols);
        $sig = implode(' · ', array_filter([
            $f['height_cm'] !== null ? $f['height_cm'] . ' cm' : '', $f['weight_kg'] !== null ? $f['weight_kg'] . ' kg' : '',
            $f['build'] !== '' ? (string) $f['build'] : '',
        ]));
        $second = [[$this->t('height'), $sig], [$this->t('lang'), (string) $f['language']], [$this->t('eyes'), (string) $f['eyes']], [$this->t('hair'), (string) $f['hair']]];
        if (!$hasPhoto) {
            $second[] = [$this->t('photo'), 'INDISPONIBLE / UNAVAILABLE'];
        }
        $y = $this->grid($second, $l, $y, $w, $cols);
        $aliases = implode(' ; ', (array) $f['aliases']);
        $y = $this->grid([[$this->t('alias'), $aliases]], $l, $y, $w, 1);
        if ($full || $f['marks'] !== '') {
            $y = $this->grid([[$this->t('marks'), (string) $f['marks']]], $l, $y, $w, 1);
        }
        $this->y = ($hasPhoto ? max($y, $top + 50.0) : $y) + 3.0;
    }

    private function hasPhoto(): bool
    {
        $bytes = $this->d['photo'] ?? null;

        return is_string($bytes) && $bytes !== '';
    }

    /** Cadre fin autour de la photographie (appelé seulement quand une photo existe). */
    private function portrait(float $x, float $y, float $w, float $h): void
    {
        $pdf = $this->pdf;
        $pdf->SetDrawColor(...self::INK);
        $pdf->SetLineWidth(0.3);
        try {
            $pdf->Image('@' . (string) $this->d['photo'], $x + 0.3, $y + 0.3, $w - 0.6, $h - 0.6, '', '', '', true, 300, '', false, false, 0, 'CM');
        } catch (\Throwable) {
            // Image illisible : le cadre seul reste.
        }
        $pdf->Rect($x, $y, $w, $h, 'D');
        $pdf->SetLineWidth(0.25);
    }

    // ---------------------------------------------------------------- Modèles

    private function identification(): void
    {
        $f = $this->facts();
        $this->identityBlock(true);
        $this->threatRow(false);
        $this->bioBlock();
        $this->seenBlock();
        $this->textSection('description');
        $this->textSection('remarks');
        $this->listSection('addresses', (array) $f['links']['address'], 2);
        $this->textSection('mention', false);
    }

    private function profile(): void
    {
        $f = $this->facts();
        $this->identityBlock(true);
        $this->threatRow(false);
        $this->textSection('summary');
        $this->sourceGrid();
        $this->grid1Section('affil', (string) $f['affiliation']);
        $this->bioBlock();
        $this->seenBlock();
        $this->twoLists([['vehicles', (array) $f['links']['vehicle']], ['addresses', (array) $f['links']['address']]]);
        $this->twoLists([['linked', (array) $f['links']['associate']], ['phones', array_merge((array) $f['links']['phone'], (array) $f['links']['email'])]]);
        $this->twoLists([['weapons', (array) $f['weapons']], ['equipment', (array) $f['equipment']]]);
        $this->checksTable(5);
        $this->textSection('assessment');
        $this->textSection('recommendations');
        $this->textSection('mention', false);
    }

    private function control(): void
    {
        $f = $this->facts();
        $this->identityBlock(false);
        $this->textSection('summary');
        $this->checksTable(12);
        $this->bioBlock(true);
        $this->textSection('remarks');
        $this->textSection('mention', false);
        unset($f);
    }

    // ----------------------------------------------------------------- Blocs

    private function threatRow(bool $withArmed): void
    {
        $f = $this->facts();
        if (!$this->ensure(15.0)) {
            return;
        }
        $pdf = $this->pdf;
        $x = self::MARGIN;
        $y = $this->y;
        $threat = self::THREAT[$f['threat']] ?? null;
        $level = $threat[0] ?? 0;
        $critical = $level >= 3;
        $half = $withArmed ? 90.0 : self::CONTENT_W;
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
        $pdf->Cell(52.0, 5.0, $threat !== null ? mb_strtoupper($threat[1][$this->lang], 'UTF-8') : $this->t('unknown')[$this->lang], 0, 0, 'L');
        for ($i = 1; $i <= 4; $i++) {
            $pdf->SetFillColor(...($i <= $level ? ($critical ? self::RED : self::INK) : [222, 226, 230]));
            $pdf->Rect($x + $half - 29.0 + ($i - 1) * 6.5, $y + 4.0, 5.0, 4.0 + $i * 0.5, 'F');
        }
        if ($withArmed) {
            $armed = self::ARMED[$f['armed']] ?? self::ARMED['unknown'];
            if ($f['armed'] === 'yes') {
                $pdf->SetFillColor(...self::RED);
                $pdf->Rect($x + $half + 6.0, $y, $half, 12.0, 'F');
                $pdf->SetTextColor(255, 255, 255);
            } else {
                $pdf->SetDrawColor(...self::LINE);
                $pdf->SetLineWidth(0.25);
                $pdf->Rect($x + $half + 6.0, $y, $half, 12.0, 'D');
                $pdf->SetTextColor(...self::INK);
            }
            $pdf->SetFont($this->sans, 'B', $f['armed'] === 'yes' ? 11.0 : 9.5);
            $pdf->SetXY($x + $half + 6.0, $y + 1.8);
            $pdf->Cell($half, 5.4, $armed[$this->lang], 0, 1, 'C');
            $pdf->SetFont($this->sans, '', 7);
            $pdf->SetXY($x + $half + 6.0, $y + 7.2);
            $pdf->Cell($half, 3.4, $armed[1 - $this->lang], 0, 0, 'C');
        }
        $pdf->SetLineWidth(0.25);
        $this->y += 15.0;
    }

    private function bioBlock(bool $compact = false): void
    {
        $b = (array) $this->facts()['bio'];
        $items = [
            [$this->t('prints'), (string) ($b['prints'] ?? '')], [$this->t('iris'), (string) ($b['iris'] ?? '')], [$this->t('dna'), (string) ($b['dna'] ?? '')],
            [$this->t('quality'), isset($b['quality']) && $b['quality'] !== null ? $b['quality'] . ' %' : ''],
        ];
        if (!$compact) {
            $items[] = [$this->t('tier'), (string) ($b['tier'] ?? '')];
            $items[] = [$this->t('subject'), (string) ($b['subject_id'] ?? '')];
            $idoc = (string) $this->facts()['id_document'];
            $items[] = [$this->t('idoc'), $idoc];
        }
        if (!$this->hasValue($items)) {
            $this->none[] = $this->t('bio');

            return;
        }
        if (!$this->ensure(26.0)) {
            return;
        }
        $this->heading($this->t('bio'));
        $this->y = $this->grid($items, self::MARGIN, $this->y + 6.4, self::CONTENT_W, 3) + 2.0;
    }

    private function seenBlock(): void
    {
        $f = $this->facts();
        $when = (string) $f['last_seen_at'];
        $when = str_starts_with($when, self::BLOCK) ? $when : ($when !== '' ? $this->dateOrRaw(substr($when, 0, 10)) : '');
        $items = [[$this->t('where'), (string) $f['last_seen_place']], [$this->t('when'), $when], [$this->t('zone'), (string) $f['zone']]];
        if (!$this->hasValue($items)) {
            $this->none[] = $this->t('seen');

            return;
        }
        if (!$this->ensure(20.0)) {
            return;
        }
        $this->heading($this->t('seen'));
        $this->y = $this->grid($items, self::MARGIN, $this->y + 6.4, self::CONTENT_W, 3) + 2.0;
    }

    private function grid1Section(string $labelKey, string $value): void
    {
        if (!$this->ensure(12.0)) {
            return;
        }
        $this->y = $this->grid([[$this->t($labelKey), $value]], self::MARGIN, $this->y, self::CONTENT_W, 1) + 2.0;
    }

    /** @param list<string> $items */
    private function listSection(string $labelKey, array $items, int $cols): void
    {
        $this->twoLists([[$labelKey, $items]], $cols === 1);
    }

    /**
     * Une ou deux listes côte à côte (puces).
     *
     * @param list<array{0: string, 1: list<string>}> $lists
     */
    private function twoLists(array $lists, bool $single = false): void
    {
        foreach ($lists as $l) {
            if ($l[1] === []) {
                $this->none[] = $this->t($l[0]);
            }
        }
        $lists = array_values(array_filter($lists, static fn (array $l): bool => $l[1] !== []));
        if ($lists === []) {
            return;
        }
        $n = $single ? 1 : count($lists);
        $colW = ($n === 1 ? self::CONTENT_W : (self::CONTENT_W - 6.0) / 2);
        $pdf = $this->pdf;
        $height = 7.0;
        foreach ($lists as $l) {
            $height = max($height, 7.0 + $this->listHeight($l[1], $colW - 5.0));
        }
        if (!$this->ensure($height)) {
            $this->overflow[] = 'lists';

            return;
        }
        foreach ($lists as $k => $l) {
            $x = self::MARGIN + $k * ($colW + 6.0);
            $this->heading($this->t($l[0]), $x, $colW);
            $y = $this->y + 6.4;
            $pdf->SetFont($this->face(implode(' ', $l[1])), '', 8.8);
            $pdf->SetTextColor(...self::INK);
            foreach (array_slice($l[1], 0, 8) as $item) {
                $pdf->SetXY($x + 1.0, $y);
                $pdf->Cell(3.0, 4.4, '•', 0, 0, 'L');
                $pdf->SetXY($x + 4.0, $y);
                $pdf->MultiCell($colW - 5.0, 4.4, $this->plain((string) $item), 0, 'L', false, 1, null, null, true, 0, false, true, 0, 'T', false);
                $y = max($y + 4.6, $pdf->GetY());
            }
        }
        $this->y += $height + 1.5;
    }

    /** @param list<string> $items */
    private function listHeight(array $items, float $w): float
    {
        $this->pdf->SetFont($this->sans, '', 8.8);
        $h = 0.0;
        ++$this->rxMeasuring;
        foreach (array_slice($items, 0, 8) as $item) {
            $h += min(9.0, max(4.6, $this->pdf->getStringHeight($w, $this->plain((string) $item)) + 0.4));
        }
        --$this->rxMeasuring;

        return $h;
    }

    private function checksTable(int $max): void
    {
        $checks = array_slice((array) $this->facts()['checks'], 0, $max);
        if ($checks === []) {
            $this->none[] = $this->t('checks');

            return;
        }
        if (!$this->ensure(18.0)) {
            return;
        }
        $pdf = $this->pdf;
        $this->heading($this->t("checks"));
        $this->y += 6.4;
        $cols = [[$this->t('c_date'), 27.0], [$this->t('c_place'), 28.0], [$this->t('c_doc'), 38.0], [$this->t('c_levels'), 22.0], [$this->t('c_presented'), 38.0], [$this->t('c_verdict'), 33.0]];
        $x0 = self::MARGIN;
        $pdf->SetFillColor(...self::PAPER);
        $pdf->Rect($x0, $this->y, self::CONTENT_W, 5.2, 'F');
        $x = $x0;
        $pdf->SetFont($this->sans, 'B', 6.8);
        $pdf->SetTextColor(...self::MUTED);
        foreach ($cols as [$label, $w]) {
            $pdf->SetXY($x + 1.0, $this->y + 1.0);
            $pdf->Cell($w - 1.0, 3.2, mb_strtoupper($label[$this->lang], 'UTF-8'), 0, 0, 'L');
            $x += $w;
        }
        $this->y += 5.6;
        if ($checks === []) {
            $pdf->SetFont($this->sans, 'I', 8.4);
            $pdf->SetXY($x0 + 1.0, $this->y + 0.6);
            $pdf->Cell(100.0, 4.0, $this->t('none')[$this->lang], 0, 0, 'L');
            $this->y += 7.0;

            return;
        }
        foreach ($checks as $c) {
            $cells = array_map($this->plain(...), [
                (string) $c['at'], (string) $c['place'], (string) $c['doc'] . ((string) $c['ref'] !== '' ? "\n" . $c['ref'] : ''),
                (string) $c['levels'], (string) $c['presented'], SseSheetFactsService::verdictLabel((string) ($c['verdict_key'] ?? ''), $this->lang === 1 ? 'en' : 'fr') . ((string) $c['controller'] !== '' ? "\n" . $c['controller'] : ''),
            ]);
            $rowH = 5.4;
            $pdf->SetFont($this->sans, '', 7.8);
            foreach ($cells as $k => $text) {
                $rowH = max($rowH, min(12.0, $pdf->getStringHeight($cols[$k][1] - 1.5, $text) + 1.6));
            }
            if (!$this->ensure($rowH + 1.0)) {
                $this->overflow[] = 'checks';
                break;
            }
            $x = $x0;
            $pdf->SetDrawColor(...self::LINE);
            $pdf->SetLineWidth(0.15);
            $pdf->Line($x0, $this->y, $x0 + self::CONTENT_W, $this->y);
            $pdf->SetTextColor(...self::INK);
            foreach ($cells as $k => $text) {
                $pdf->SetXY($x + 1.0, $this->y + 0.9);
                $pdf->MultiCell($cols[$k][1] - 1.5, 3.6, $text, 0, 'L', false, 1, null, null, true, 0, false, true, $rowH - 1.0, 'T', true);
                $x += $cols[$k][1];
            }
            $this->y += $rowH;
        }
        $pdf->SetLineWidth(0.25);
        $this->y += 3.0;
    }

    /** Texte rédigé (mini-balisage déjà assaini) : titre, puis HTML fabriqué ici. */
    private function textSection(string $key, bool $required = true, bool $emphasis = false): void
    {
        $text = trim((string) ($this->d['texts'][$key] ?? ''));
        $label = $this->d['text_labels'][$key] ?? [$key, $key];
        if ($text === '') {
            if ($required) {
                $this->none[] = [(string) $label[0], (string) $label[1]];
            }

            return;
        }
        $w = self::CONTENT_W - 8.0;
        $size = 9.2;
        $html = SseSheetText::toHtml($text);
        $h = $this->htmlHeight($html, $w, $size) + 5.0;
        $cut = false;
        if (!$this->ensure(min($h + 8.0, 40.0))) {
            $cut = true;
        }
        if (!$cut && $this->y + 8.0 + $h > self::BOTTOM && $this->pages < ($this->d['faces'] === '1' ? 1 : self::MAX_PAGES)) {
            $this->newPage();
        }
        $room = self::BOTTOM - $this->y - 8.0;
        if ($room < 14.0) {
            $this->overflow[] = $key;

            return;
        }
        $this->heading([(string) $label[0], (string) $label[1]]);
        $y = $this->y + 6.4;
        if ($h > $room) {
            // Réduit d'abord, puis coupe : la mise en page ne déborde jamais du pied de page.
            for ($s = $size; $s >= 7.4; $s -= 0.4) {
                $h2 = $this->htmlHeight($html, $w, $s) + 5.0;
                if ($h2 <= $room) {
                    $size = $s;
                    $h = $h2;
                    break;
                }
                $size = $s;
                $h = $h2;
            }
        }
        $boxH = min($h, $room);
        $pdf = $this->pdf;
        $pdf->SetFillColor(...self::PAPER);
        $pdf->Rect(self::MARGIN, $y, self::CONTENT_W, $boxH, 'F');
        $pdf->SetFillColor(...($emphasis ? $this->accent() : self::LINE));
        $pdf->Rect(self::MARGIN, $y, 0.9, $boxH, 'F');
        $pdf->SetFont($this->sans, '', $size);
        $pdf->SetTextColor(...self::INK);
        if ($h > $room) {
            $this->overflow[] = $key;
            $pdf->StartTransform();
            $pdf->Rect(self::MARGIN + 1.0, $y, self::CONTENT_W - 1.0, $boxH - 4.0, 'CNZ');
            $pdf->writeHTMLCell($w, 0, self::MARGIN + 4.0, $y + 2.0, $this->wrapHtml($html, $size), 0, 1, false, true, 'L', true);
            $pdf->StopTransform();
            $pdf->SetFont($this->sans, 'I', 6.8);
            $pdf->SetTextColor(...self::RED);
            $pdf->SetXY(self::MARGIN + 4.0, $y + $boxH - 3.8);
            $pdf->Cell($w, 3.2, '… ' . $this->t('cut')[$this->lang], 0, 0, 'L');
        } else {
            $pdf->writeHTMLCell($w, 0, self::MARGIN + 4.0, $y + 2.0, $this->wrapHtml($html, $size), 0, 1, false, true, 'L', true);
        }
        $this->y = $y + $boxH + 3.5;
    }

    private function wrapHtml(string $html, float $size): string
    {
        // Caviardage : rectangle noir (fond noir sur espaces insécables), largeur fixe.
        $html = (string) preg_replace_callback('/' . self::BLOCK . '+/u', function (array $m): string {
            $letter = $this->rxMark($m[0]);

            return '<span style="background-color:#000000;color:#000000;">' . str_repeat('&nbsp;', 9) . '</span>' . ($letter !== '' ? '<sup style="font-size:62%;"><b>' . $letter . '</b></sup>' : '');
        }, $html);

        $family = $this->face(strip_tags($html)) !== $this->sans ? 'font-family:dejavusans;' : '';

        return '<div dir="ltr" style="' . $family . 'font-size:' . $size . 'pt;line-height:1.28;">' . $html . '</div>';
    }

    /** Hauteur (mm) d'un HTML dans une cellule : écrit puis annule (transaction TCPDF). */
    private function htmlHeight(string $html, float $w, float $size): float
    {
        $pdf = $this->pdf;
        $pdf->SetFont($this->sans, '', $size);
        $pdf->startTransaction();
        $y0 = $pdf->GetY();
        $pageBefore = $pdf->getPage();
        ++$this->rxMeasuring;
        $pdf->writeHTMLCell($w, 0, self::MARGIN + 4.0, 20.0, $this->wrapHtml($html, $size), 0, 1, false, true, 'L', true);
        --$this->rxMeasuring;
        $h = ($pdf->getPage() === $pageBefore ? $pdf->GetY() : 280.0) - 20.0;
        $pdf->rollbackTransaction(true);
        $pdf->SetY($y0);

        return max(4.0, $h);
    }

    /** Sections sans donnée : une seule ligne au lieu de grands blancs. */
    private function noData(): void
    {
        if ($this->none === []) {
            return;
        }
        $labels = [];
        foreach ($this->none as $t) {
            $labels[] = (string) $t[$this->lang];
        }
        $labels = array_values(array_unique($labels));
        if (!$this->ensure(11.0)) {
            return;
        }
        $pdf = $this->pdf;
        $pdf->SetFont($this->sans, 'B', 8.0);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY(self::MARGIN, $this->y);
        $head = mb_strtoupper($this->t('no_data')[$this->lang] . ' / ' . $this->t('no_data')[1 - $this->lang], 'UTF-8') . ' : ';
        $pdf->Cell($pdf->GetStringWidth($head) + 1.0, 4.4, $head, 0, 0, 'L');
        $pdf->SetFont($this->sans, 'I', 8.0);
        $pdf->SetTextColor(...self::MUTED);
        $x = self::MARGIN + $pdf->GetStringWidth($head) + 1.0;
        $line = implode(' · ', $labels);
        $pdf->SetFont($this->sans, 'I', 8.0);
        $w = self::CONTENT_W - ($x - self::MARGIN);
        $pdf->SetXY($x, $this->y + 0.2);
        $pdf->MultiCell($w, 3.9, $line, 0, 'L', false, 1, null, null, true, 0, false, true, 0, 'T', false);
        $this->y = max($this->y + 5.0, $pdf->GetY()) + 3.0;
    }

    /** Bloc de la dernière page : autorité de classification (compact, sans page dédiée). */
    private function about(): void
    {
        $pdf = $this->pdf;
        $pdf->setPage($this->pages);
        $d = $this->d;
        $need = 13.0;
        if (self::BOTTOM - $this->y < $need) {
            // Pas de page de plus pour ce bloc, sauf si deux pages sont demandées.
            if (!($d['faces'] === '2' && $this->pages === 1 && $this->newPage())) {
                return;
            }
        }
        $y = $this->y + 1.0;
        $y = $this->grid([
            [$this->t('classified_by'), (string) ($d['service'] !== '' ? $d['service'] : $d['authority'][$this->lang])],
            [$this->t('derived'), $this->t('sources_multi')[$this->lang]],
            [$this->t('declass'), $this->t('declass_na')[$this->lang]],
        ], self::MARGIN, $y, self::CONTENT_W, 3);
        $this->y = $y + 2.0;
    }

    /**
     * Source, fiabilité de la source (A à F) et crédibilité de l'information (1 à 6) : valeurs de JEU déduites de la qualité
     * biométrique moyenne et du nombre de contrôles recoupés.
     */
    private function sourceGrid(): void
    {
        if (!$this->ensure(30.0)) {
            return;
        }
        $f = $this->facts();
        $quality = (int) (((array) $f['bio'])['quality'] ?? 0);
        $letter = $quality >= 90 ? 'A' : ($quality >= 75 ? 'B' : ($quality >= 60 ? 'C' : ($quality >= 40 ? 'D' : ($quality > 0 ? 'E' : 'F'))));
        $checks = count((array) $f['checks']);
        $cred = $checks >= 5 ? 1 : ($checks === 4 ? 2 : ($checks === 3 ? 3 : ($checks === 2 ? 4 : ($checks === 1 ? 5 : 6))));
        $pdf = $this->pdf;
        $this->heading($this->t('source'));
        $y = $this->y + 7.0;
        $pdf->SetFont($this->sans, '', 7.6);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY(self::MARGIN, $y);
        $pdf->Cell(self::CONTENT_W, 4.0, 'SOURCE : ' . ($this->lang === 1 ? 'SSE registry and field checks' : 'Registre SSE et contrôles de terrain'), 0, 0, 'L');
        $y += 5.0;
        $half = (self::CONTENT_W - 8.0) / 2;
        foreach ([[self::MARGIN, 'reliab', array_keys(self::RELIABILITY), $letter, self::RELIABILITY[$letter]], [self::MARGIN + $half + 8.0, 'cred', array_keys(self::CREDIBILITY), $cred, self::CREDIBILITY[$cred]]] as [$x, $key, $keys, $on, $desc]) {
            $pdf->SetFont($this->sans, 'B', 6.8);
            $pdf->SetTextColor(...self::INK);
            $pdf->SetXY($x, $y);
            $pdf->Cell($half, 3.4, mb_strtoupper($this->t($key)[$this->lang], 'UTF-8'), 0, 0, 'L');
            $bw = 9.0;
            foreach ($keys as $i => $k) {
                $bx = $x + $i * ($bw + 1.2);
                $active = (string) $k === (string) $on;
                $pdf->SetDrawColor(...self::INK);
                $pdf->SetLineWidth($active ? 0.5 : 0.2);
                $pdf->SetFillColor(...($active ? self::INK : [255, 255, 255]));
                $pdf->Rect($bx, $y + 4.2, $bw, 6.0, 'DF');
                $pdf->SetFont($this->sans, 'B', 9.0);
                $pdf->SetTextColor(...($active ? [255, 255, 255] : self::MUTED));
                $pdf->SetXY($bx, $y + 5.2);
                $pdf->Cell($bw, 4.0, (string) $k, 0, 0, 'C');
            }
            $pdf->SetFont($this->sans, 'I', 7.2);
            $pdf->SetTextColor(...self::MUTED);
            $pdf->SetXY($x, $y + 11.0);
            $pdf->Cell($half, 3.6, $on . ' — ' . $desc[$this->lang], 0, 0, 'L');
        }
        $pdf->SetLineWidth(0.25);
        $this->y = $y + 16.6;
    }

    /** @param list<array{0: array{0: string, 1: string}, 1: string}> $items au moins une valeur renseignée ? */
    private function hasValue(array $items): bool
    {
        foreach ($items as [, $value]) {
            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------- Éléments

    private function footer(int $page): void
    {
        $d = $this->d;
        $pdf = $this->pdf;
        $l = self::MARGIN;
        $this->rxLegend();
        $pdf->SetDrawColor(...self::INK);
        $pdf->SetLineWidth(0.3);
        $pdf->Line($l, 272.6, self::PAGE_W - $l, 272.6);
        $pdf->SetLineWidth(0.25);
        $style = ['position' => '', 'border' => false, 'padding' => 0, 'fgcolor' => [0, 0, 0], 'bgcolor' => false, 'text' => false];
        $pdf->write1DBarcode((string) $d['ref'], 'C128', $l, 275.0, 42.0, 7.0, 0.4, $style, 'N');
        $pdf->SetFont($this->sans, '', 6.5);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($l, 282.4);
        $pdf->Cell(42.0, 3.0, (string) $d['ref'], 0, 0, 'L');
        // Colonne centrale : la seule mention de diffusion, centrée entre le filet et le bandeau de classification.
        $pdf->SetFont(AthenaPdfTheme::mono(), 'B', 7.0);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY(60.0, 278.4);
        $pdf->Cell(90.0, 3.4, implode(' · ', SseUsClassification::dissemination($this->level())), 0, 0, 'C');
        if (!empty($d['copy_level'])) {
            // Copie rendue à un autre niveau de diffusion que celui de l'émission : même référence, aucun nouveau document.
            $pdf->SetFont($this->sans, 'B', 6.4);
            $pdf->SetXY(60.0, 274.9);
            $pdf->Cell(90.0, 3.0, $this->lang === 1 ? 'COPY AT A DISTRIBUTION LEVEL · ISSUED AT ' . SseUsClassification::abbr((string) $d['copy_level']) : 'COPIE À UN NIVEAU DE DIFFUSION · ÉMISE À ' . SseUsClassification::abbr((string) $d['copy_level']), 0, 0, 'C');
        }
        $pdf->SetFont($this->sans, 'B', 7.6);
        $pdf->SetXY(150.0, 275.0);
        $pdf->Cell(48.0, 3.6, $this->t('page')[$this->lang] . ' ' . $page . ' ' . ($this->lang === 1 ? 'of' : 'sur') . ' ' . $this->pages, 0, 1, 'R');
        $pdf->SetFont($this->sans, '', 6.4);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY(150.0, 279.4);
        $pdf->Cell(48.0, 3.2, (string) $d['generated_at'], 0, 0, 'R');
        $this->band(false);
    }

    /** Bandeau de classification pleine largeur, en haut ou en bas de page : marquage en gras centré, couleur conventionnelle. */
    private function band(bool $top): void
    {
        $pdf = $this->pdf;
        $rgb = SseUsClassification::rgb($this->level());
        $y = $top ? 0.0 : self::PAGE_H - self::BAND_H;
        $pdf->SetFillColor(...$rgb);
        $pdf->Rect(0.0, $y, self::PAGE_W, self::BAND_H, 'F');
        $luma = 0.299 * $rgb[0] + 0.587 * $rgb[1] + 0.114 * $rgb[2];
        $pdf->SetTextColor(...($luma > 150 ? [0, 0, 0] : [255, 255, 255]));
        $pdf->SetFont($this->sans, 'B', 10.0);
        $pdf->SetXY(0.0, $y + 0.9);
        $pdf->Cell(self::PAGE_W, 4.6, SseUsClassification::banner($this->level()), 0, 0, 'C');
    }

    /** Groupe date-heure (DTG) militaire, p. ex. « 151423Z NOV 26 ». */
    private function dtg(): string
    {
        $raw = (string) ($this->d['issued_dt'] ?? '');
        $ts = $raw !== '' ? strtotime($raw . ' UTC') : strtotime(substr((string) $this->d['issued_at'], 0, 10) . ' 12:00:00 UTC');
        if ($ts === false) {
            return '—';
        }
        $months = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];

        return gmdate('dHi', $ts) . 'Z ' . $months[(int) gmdate('n', $ts) - 1] . ' ' . gmdate('y', $ts);
    }

    /** Code stable du niveau de diffusion de la fiche (voir SseUsClassification). */
    private function level(): string
    {
        return SseUsClassification::normalize((string) ($this->d['level'] ?? ''));
    }

    private function emblem(float $x, float $y, float $size): void
    {
        $pdf = $this->pdf;
        $path = $this->d['emblem_path'] ?? null;
        if (is_string($path) && $path !== '' && is_file($path)) {
            try {
                $pdf->Image($path, $x, $y, $size, $size, '', '', '', true, 300, '', false, false, 0, 'CM');

                return;
            } catch (\Throwable) {
                // Logo illisible : le pictogramme neutre prend le relais.
            }
        }
        $pdf->SetDrawColor(...self::INK);
        $pdf->SetLineWidth(0.5);
        $c = $size / 2;
        $pdf->Circle($x + $c, $y + $c, $c - 0.5, 0, 360, 'D');
        $pdf->SetLineWidth(0.3);
        $pdf->Ellipse($x + $c, $y + $c, $c * 0.42, $c - 0.5, 0, 0, 360, 'D');
        $pdf->Line($x + 0.5, $y + $c, $x + $size - 0.5, $y + $c);
        $pdf->Line($x + $c, $y + 0.5, $x + $c, $y + $size - 0.5);
        $pdf->SetFillColor(...$this->accent());
        $pdf->Circle($x + $c, $y + $c, 1.2, 0, 360, 'F');
    }

    /** Titre de paragraphe numéroté, marquage de portion à droite : « 3. SIGNALEMENT (S//NF) ». @param array{0: string, 1: string} $title */
    private function heading(array $title, ?float $x = null, ?float $w = null): void
    {
        $pdf = $this->pdf;
        $x ??= self::MARGIN;
        $w ??= self::CONTENT_W;
        $y = $this->y;
        ++$this->n;
        $pdf->SetFont($this->sans, 'B', 9.4);
        $pdf->SetTextColor(...self::INK);
        $primary = $this->n . '. ' . mb_strtoupper($title[$this->lang], 'UTF-8');
        $primaryW = $pdf->GetStringWidth($primary);
        $pdf->SetXY($x, $y);
        $pdf->Cell($primaryW + 1.0, 5.0, $primary, 0, 0, 'L');
        $mark = '(' . SseUsClassification::portion($this->level()) . ')';
        $pdf->SetFont(AthenaPdfTheme::mono(), 'B', 7.6);
        $markW = $pdf->GetStringWidth($mark);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($x + $w - $markW - 0.5, $y + 0.5);
        $pdf->Cell($markW + 0.5, 4.2, $mark, 0, 0, 'R');
        $pdf->SetFont($this->sans, '', 7.2);
        $roomW = $w - $primaryW - $markW - 8.0;
        if ($roomW > 12.0 && $pdf->GetStringWidth($title[1 - $this->lang]) <= $roomW) {
            $pdf->SetXY($x + $primaryW + 3.0, $y + 0.5);
            $pdf->Cell($roomW, 4.2, $title[1 - $this->lang], 0, 0, 'L');
        }
        $pdf->SetDrawColor(...self::INK);
        $pdf->SetLineWidth(0.3);
        $pdf->Line($x, $y + 5.1, $x + $w, $y + 5.1);
        $pdf->SetLineWidth(0.25);
    }

    /**
     * Grille de champs : libellé en petit, valeur dessous. Retourne le bas de la grille.
     *
     * @param list<array{0: array{0: string, 1: string}, 1: string}> $items
     */
    private function grid(array $items, float $x, float $y, float $w, int $cols): float
    {
        $pdf = $this->pdf;
        $colW = $w / $cols;
        $rowY = $y;
        $rowMax = 0.0;
        foreach ($items as $k => [$label, $value]) {
            $c = $k % $cols;
            if ($c === 0 && $k > 0) {
                $rowY += max(9.2, $rowMax);
                $rowMax = 0.0;
            }
            $cx = $x + $c * $colW;
            $pdf->SetDrawColor(...self::LINE);
            $pdf->SetLineWidth(0.2);
            $pdf->Line($cx, $rowY, $cx + $colW - 3.0, $rowY);
            $pdf->SetFont($this->sans, '', 6.2);
            $pdf->SetTextColor(...self::MUTED);
            $pdf->SetXY($cx, $rowY + 0.8);
            $pdf->Cell($colW - 3.0, 2.8, mb_strtoupper($label[$this->lang], 'UTF-8') . ' / ' . $label[1 - $this->lang], 0, 0, 'L');
            $text = trim($value) !== '' ? $value : '—';
            if (str_contains($text, self::BLOCK)) {
                $this->barLine($text, $cx, $rowY + 3.9, $colW - 3.0, 9.0);
                $rowMax = max($rowMax, 9.2);

                continue;
            }
            [$text, $size] = $this->shrink($text, $colW - 3.0, 9.6, 7.0);
            // La barre de caviardage n'existe pas dans la police grasse : elle s'écrit en police normale.
            $pdf->SetFont($this->face($text), trim($value) !== '' && !str_contains($value, self::BLOCK) ? 'B' : '', $size);
            $pdf->SetTextColor(...(trim($value) !== '' ? self::INK : self::MUTED));
            $pdf->SetXY($cx, $rowY + 3.9);
            $pdf->MultiCell($colW - 3.0, 4.0, $text, 0, 'L', false, 1, null, null, true, 0, false, true, 11.0, 'T', true);
            $rowMax = max($rowMax, 4.6 + min(11.0, $pdf->getStringHeight($colW - 3.0, $text)));
        }

        return $rowY + max(9.2, $rowMax);
    }

    /**
     * Valeur masquée sur une ligne : le texte clair reste du texte, chaque suite de caviardage devient un RECTANGLE noir plein de
     * largeur fixe (jamais un glyphe : un bloc plein absent de la police sortait en « ? », et la longueur de la barre trahirait celle
     * de la valeur).
     */
    private function barLine(string $text, float $x, float $y, float $w, float $size): void
    {
        $pdf = $this->pdf;
        $pdf->SetFont($this->sans, '', $size);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetFillColor(0, 0, 0);
        $parts = preg_split('/(' . self::BLOCK . '+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $cx = $x;
        foreach ($parts as $part) {
            if (str_starts_with($part, self::BLOCK)) {
                $bw = min(self::BAR_W, max(4.0, $x + $w - $cx));
                $pdf->Rect($cx, $y + 0.5, $bw, $size * 0.3528 * 0.85, 'F');
                $cx += $bw + 1.0 + $this->rxLetter($this->rxMark($part), $cx + $bw + 0.4, $y + 0.5, $size);
                $pdf->SetFillColor(0, 0, 0);

                continue;
            }
            $tw = $pdf->GetStringWidth($part);
            $pdf->SetXY($cx, $y);
            $pdf->Cell($tw + 0.5, 4.4, $part, 0, 0, 'L');
            $cx += $tw;
        }
    }

    /** Police du texte : FreeSans couvre latin, grec et cyrillique ; arabe, hébreu et indien passent à DejaVu Sans (même jeu embarqué). */
    private function face(string $text): string
    {
        return preg_match('/[\x{0590}-\x{0DFF}\x{FB1D}-\x{FEFF}]/u', $text) === 1 ? 'dejavusans' : $this->sans;
    }

    /** Texte sans aucun bloc de caviardage (listes, tableaux, objet) : un caviardage devient « [CAVIARDÉ] » / « [REDACTED] ». */
    private function plain(string $text): string
    {
        return (string) preg_replace_callback('/' . self::BLOCK . '+/u', function (array $m): string {
            $letter = $this->rxMark($m[0]);

            return ($this->lang === 1 ? '[REDACTED' : '[CAVIARDÉ') . ($letter !== '' ? ' ' . $letter : '') . ']';
        }, $text);
    }

    /** @return array{0: string, 1: float} */
    private function shrink(string $text, float $w, float $size, float $min): array
    {
        $pdf = $this->pdf;
        for ($s = $size; $s >= $min; $s -= 0.4) {
            $pdf->SetFont($this->face($text), 'B', $s);
            if ($pdf->getStringHeight($w, $text) <= 4.4 * 2 && $pdf->GetStringWidth($text) <= $w * 2.0) {
                return [$text, $s];
            }
        }
        $pdf->SetFont($this->face($text), 'B', $min);
        $t = $text;
        while (mb_strlen($t, 'UTF-8') > 4 && $pdf->getStringHeight($w, $t . '…') > 8.6) {
            $t = mb_substr($t, 0, mb_strlen($t, 'UTF-8') - 4, 'UTF-8');
        }

        return [rtrim($t) . '…', $min];
    }

    // ---------------------------------------------------------------- Divers

    /** @return array<string, mixed> */
    private function facts(): array
    {
        return (array) $this->d['facts'];
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function accent(): array
    {
        $a = $this->d['accent'] ?? self::ACCENTS[$this->d['model']] ?? self::ACCENTS['profile'];

        return [(int) $a[0], (int) $a[1], (int) $a[2]];
    }

    /** @return array{0: string, 1: string} */
    private function t(string $key): array
    {
        return self::L[$key] ?? [$key, $key];
    }

    private function dateOrRaw(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $this->date($value) : $value;
    }

    private function date(string $value): string
    {
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

        return $this->lang === 1 ? $month . ' ' . $day . ', ' . gmdate('Y', $ts) : ($day === 1 ? '1er' : (string) $day) . ' ' . $month . ' ' . gmdate('Y', $ts);
    }
}
