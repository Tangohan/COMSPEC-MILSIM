<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Support\Pdf\AthenaPdfTheme;
use App\Support\SseSheetText;
use App\Support\TrainingCertificatePdfEngine;

/**
 * Gabarit PDF des fiches d'une personne (identification, vigilance, renseignement, contrôle terrain) pour le jeu de rôle :
 * même moteur (TCPDF) et même identité visuelle fictive que la notice de recherche (SseWantedNoticePdf) : en-tête blanc avec
 * pictogramme neutre et autorité émettrice fictive, bandeau de couleur du modèle, blocs d'information, code-barres de la
 * référence et pied de page. Une à trois pages A4, la suite passant sur la page suivante.
 *
 * Règle de fond : accessoire de JEU DE RÔLE pour des personnages FICTIFS. Aucun logo, emblème, sceau ni nom d'organisation,
 * de police ou de gouvernement réels. Chaque page porte « Document de fiction — jeu de rôle Arma 3 » (désactivable par la
 * gestion SSE seulement) ; les métadonnées du fichier la portent toujours. Aucun rendu n'imite un document officiel réel sans
 * cette mention.
 *
 * Le gabarit ne reçoit que des données DÉJÀ caviardées au niveau de diffusion (SseSheetFactsService::mask) et des textes déjà
 * assainis (SseSheetText::clean) : il n'interprète jamais de HTML saisi.
 *
 * @phpstan-type Sheet array<string, mixed>
 */
final class SseSheetPdf
{
    private const INK = [24, 30, 38];
    private const MUTED = [98, 108, 120];
    private const LINE = [200, 205, 211];
    private const PAPER = [247, 248, 249];
    private const RED = [176, 18, 31];

    private const MARGIN = 12.0;
    private const PAGE_W = 210.0;
    private const CONTENT_W = 186.0;
    private const BOTTOM = 271.0;

    public const MAX_PAGES = 3;

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
    ];

    private const SEX = ['M' => ['Masculin', 'Male'], 'F' => ['Féminin', 'Female'], 'X' => ['Non précisé', 'Unspecified']];

    private const ARMED = ['yes' => ['Armé et dangereux', 'Armed and dangerous'], 'no' => ['Non armé', 'Not armed'], 'unknown' => ['Inconnu : prudence', 'Unknown: caution']];

    private const THREAT = ['low' => [1, ['Faible', 'Low']], 'medium' => [2, ['Moyen', 'Medium']], 'high' => [3, ['Élevé', 'High']], 'critical' => [4, ['Critique', 'Critical']]];

    private const DISCLAIMER = [
        'Cette fiche est un accessoire de jeu de rôle : les personnes, l’autorité émettrice et les faits sont imaginaires. Elle n’a aucune valeur en dehors du jeu et ne doit jamais être diffusée à l’extérieur de la communauté.',
        'This sheet is a role-play prop: the persons, the issuing authority and the facts are fictional. It has no value outside the game and must never be shared outside the community.',
    ];

    private string $sans = 'freesans';

    private int $lang = 0;

    /** @var \TCPDF */
    private \TCPDF $pdf;

    /** @var array<string, mixed> */
    private array $d = [];

    private float $y = 0.0;

    private int $pages = 1;

    /** @var list<string> */
    private array $overflow = [];

    /**
     * PDF de la fiche, ou null si le moteur PDF ou ses polices manquent.
     *
     * @param Sheet $d
     * @return array{bytes: string, pages: int, overflow: list<string>}|null
     */
    public function render(array $d): ?array
    {
        if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded() || !TrainingCertificatePdfEngine::tcpdfCertificateFontsReady()) {
            return null;
        }

        return TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(function () use ($d): array {
            return $this->draw($d);
        });
    }

    /** @param Sheet $d @return array{bytes: string, pages: int, overflow: list<string>} */
    private function draw(array $d): array
    {
        $this->d = $d;
        $this->sans = AthenaPdfTheme::sans();
        $this->lang = $d['lang'] === 'en' ? 1 : 0;
        $this->overflow = [];
        $this->pages = 1;
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
        $pdf->SetSubject(self::fiction(0) . ' / ' . self::fiction(1));
        $pdf->SetKeywords('fiction, jeu de rôle, role-play, Arma 3');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(self::MARGIN, self::MARGIN, self::MARGIN);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->setFontSubsetting(true);
        $pdf->SetCellPadding(0);
        $pdf->setCellHeightRatio(1.22);

        $pdf->AddPage();
        $this->front();
        match ($d['model']) {
            'identification' => $this->identification(),
            'vigilance' => $this->vigilance(),
            'profile' => $this->profile(),
            default => $this->control(),
        };
        if ($d['faces'] === '2' && $this->pages === 1) {
            $this->newPage();
        }
        if ($this->pages > 1 || $d['faces'] === '2') {
            $this->about();
        }
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
        $this->watermark();
        $accent = $this->accent();
        $pdf->SetFillColor(...$accent);
        $pdf->Rect(self::MARGIN, 10.0, self::CONTENT_W, 1.2, 'F');
        $pdf->SetFont($this->sans, 'B', 8.5);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY(self::MARGIN, 12.6);
        $pdf->Cell(130.0, 4.6, mb_strtoupper($this->d['title'][$this->lang], 'UTF-8') . ' · ' . $this->t('cont')[$this->lang], 0, 0, 'L');
        $pdf->SetFont($this->sans, '', 8);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY(self::MARGIN + 130.0, 12.6);
        $pdf->Cell(self::CONTENT_W - 130.0, 4.6, $this->d['ref'], 0, 0, 'R');
        $this->y = 20.0;

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
        $this->watermark();
        $this->emblem($l, 7.0, 15.0);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetFont($this->sans, 'B', 10.5);
        $pdf->SetXY($l + 19.0, 8.0);
        $pdf->Cell(112.0, 5.0, mb_strtoupper($d['authority'][$this->lang], 'UTF-8'), 0, 1, 'L');
        $pdf->SetFont($this->sans, '', 8);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($l + 19.0, 13.5);
        $pdf->Cell(112.0, 4.0, $d['service'] !== '' ? $this->t('issuer')[$this->lang] . ' : ' . $d['service'] : $d['authority'][1 - $this->lang], 0, 1, 'L');
        // Référence et diffusion, à droite.
        $x = 146.0;
        $pdf->SetDrawColor(...self::LINE);
        $pdf->SetLineWidth(0.25);
        $pdf->Rect($x, 7.0, 52.0, 15.0, 'D');
        $pdf->SetFont($this->sans, '', 6.4);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($x + 2.0, 8.0);
        $pdf->Cell(48.0, 2.8, mb_strtoupper($this->t('ref')[$this->lang], 'UTF-8') . ' · ' . mb_strtoupper($this->t('diffusion')[$this->lang], 'UTF-8'), 0, 0, 'L');
        $pdf->SetFont($this->sans, 'B', 10.5);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY($x + 2.0, 11.2);
        $pdf->Cell(48.0, 5.0, $d['ref'], 0, 0, 'L');
        $pdf->SetFont($this->sans, '', 7.4);
        $pdf->SetTextColor(...$this->accent());
        $pdf->SetXY($x + 2.0, 17.0);
        $pdf->Cell(48.0, 3.6, $d['classification'][$this->lang], 0, 0, 'L');

        // Bandeau du modèle.
        $accent = $this->accent();
        $pdf->SetFillColor(...$accent);
        $pdf->Rect($l, 25.0, self::CONTENT_W, 13.0, 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont($this->sans, 'B', 15.5);
        $pdf->SetXY($l + 4.0, 26.2);
        $pdf->Cell(128.0, 6.4, mb_strtoupper($d['title'][$this->lang], 'UTF-8'), 0, 1, 'L');
        $pdf->SetFont($this->sans, '', 8.4);
        $pdf->SetXY($l + 4.0, 32.4);
        $pdf->Cell(128.0, 4.0, mb_strtoupper($d['title'][1 - $this->lang], 'UTF-8'), 0, 0, 'L');
        $pdf->SetFont($this->sans, '', 7.4);
        $pdf->SetXY($l + 130.0, 27.0);
        $pdf->Cell(52.0, 4.0, $this->t('issued')[$this->lang] . ' ' . $this->date((string) $d['issued_at']), 0, 1, 'R');
        if (($d['valid_until'] ?? null) !== null) {
            $pdf->SetFont($this->sans, 'B', 8.2);
            $pdf->SetXY($l + 120.0, 32.4);
            $pdf->Cell(62.0, 4.0, $this->t('valid')[$this->lang] . ' ' . $this->date((string) $d['valid_until']), 0, 0, 'R');
        }
        $this->y = 42.0;
    }

    /** Identité, signalement et portrait : bloc commun. */
    private function identityBlock(bool $full): void
    {
        $f = $this->facts();
        $pdf = $this->pdf;
        $l = self::MARGIN;
        $top = $this->y;
        $this->portrait($l + 134.0, $top, 52.0, 64.0);
        $w = 128.0;
        $sex = self::SEX[$f['sex']] ?? self::SEX['X'];
        $age = $f['age_estimated'] !== null ? $f['age_estimated'] . ($this->lang === 1 ? ' yrs' : ' ans') : '';
        $items = [
            [$this->t('family'), (string) $f['last_name']], [$this->t('forename'), (string) $f['first_name']],
            [$this->t('sex'), $sex[$this->lang] . ($age !== '' ? ' · ' . $age : '')], [$this->t('dob'), $this->dateOrRaw((string) $f['birth_date'])],
            [$this->t('pob'), (string) $f['birth_place']], [$this->t('nat'), (string) $f['nationality']],
        ];
        $y = $this->grid($items, $l, $top, $w, 2);
        $sig = implode(' · ', array_filter([
            $f['height_cm'] !== null ? $f['height_cm'] . ' cm' : '', $f['weight_kg'] !== null ? $f['weight_kg'] . ' kg' : '',
            $f['build'] !== '' ? (string) $f['build'] : '',
        ]));
        $y = $this->grid([[$this->t('height'), $sig], [$this->t('lang'), (string) $f['language']], [$this->t('eyes'), (string) $f['eyes']], [$this->t('hair'), (string) $f['hair']]], $l, $y, $w, 2);
        $aliases = implode(' ; ', (array) $f['aliases']);
        $y = $this->grid([[$this->t('alias'), $aliases]], $l, $y, $w, 1);
        if ($full || $f['marks'] !== '') {
            $y = $this->grid([[$this->t('marks'), (string) $f['marks']]], $l, $y, $w, 1);
        }
        $this->y = max($y, $top + 64.0) + 3.0;
        unset($pdf);
    }

    private function portrait(float $x, float $y, float $w, float $h): void
    {
        $pdf = $this->pdf;
        $pdf->SetDrawColor(...self::INK);
        $pdf->SetLineWidth(0.4);
        $pdf->SetFillColor(...self::PAPER);
        $pdf->Rect($x, $y, $w, $h, 'DF');
        $bytes = $this->d['photo'] ?? null;
        if (is_string($bytes) && $bytes !== '') {
            try {
                $pdf->Image('@' . $bytes, $x + 0.4, $y + 0.4, $w - 0.8, $h - 0.8, '', '', '', true, 300, '', false, false, 0, 'CM');
                $pdf->SetLineWidth(0.25);

                return;
            } catch (\Throwable) {
                // Image illisible : la silhouette neutre prend le relais.
            }
        }
        $pdf->SetFillColor(215, 219, 224);
        $pdf->Circle($x + $w / 2, $y + $h * 0.38, $w * 0.19, 0, 360, 'F');
        $pdf->Ellipse($x + $w / 2, $y + $h * 0.82, $w * 0.34, $h * 0.2, 0, 0, 360, 'F');
        $pdf->SetFont($this->sans, '', 6.6);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($x, $y + $h - 7.0);
        $pdf->MultiCell($w, 3.0, mb_strtoupper($this->t('photo_none')[$this->lang], 'UTF-8'), 0, 'C', false, 1, null, null, true, 0, false, true, 6.0, 'T', true);
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

    private function vigilance(): void
    {
        $f = $this->facts();
        $this->identityBlock(false);
        $this->threatRow(true);
        $this->textSection('reason');
        $this->textSection('instructions', true, true);
        $this->textSection('behaviour');
        $this->seenBlock();
        $this->twoLists([['vehicles', (array) $f['links']['vehicle']], ['addresses', (array) $f['links']['address']]]);
        $this->twoLists([['linked', (array) $f['links']['associate']], ['phones', array_merge((array) $f['links']['phone'], (array) $f['links']['email'])]]);
        $this->textSection('mention', false);
    }

    private function profile(): void
    {
        $f = $this->facts();
        $this->identityBlock(true);
        $this->threatRow(false);
        $this->textSection('summary');
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
        if (!$this->ensure(26.0)) {
            return;
        }
        $this->heading($this->t('bio'));
        $this->y = $this->grid($items, self::MARGIN, $this->y + 6.4, self::CONTENT_W, 3) + 2.0;
    }

    private function seenBlock(): void
    {
        $f = $this->facts();
        if (!$this->ensure(20.0)) {
            return;
        }
        $this->heading($this->t('seen'));
        $when = (string) $f['last_seen_at'];
        $when = str_starts_with($when, '█') ? $when : ($when !== '' ? $this->dateOrRaw(substr($when, 0, 10)) : '');
        $this->y = $this->grid([[$this->t('where'), (string) $f['last_seen_place']], [$this->t('when'), $when], [$this->t('zone'), (string) $f['zone']]], self::MARGIN, $this->y + 6.4, self::CONTENT_W, 3) + 2.0;
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
            $pdf->SetFont($this->sans, '', 8.8);
            $pdf->SetTextColor(...self::INK);
            foreach (array_slice($l[1], 0, 8) as $item) {
                $pdf->SetXY($x + 1.0, $y);
                $pdf->Cell(3.0, 4.4, '•', 0, 0, 'L');
                $pdf->SetXY($x + 4.0, $y);
                $pdf->MultiCell($colW - 5.0, 4.4, (string) $item, 0, 'L', false, 1, null, null, true, 0, false, true, 0, 'T', false);
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
        foreach (array_slice($items, 0, 8) as $item) {
            $h += min(9.0, max(4.6, $this->pdf->getStringHeight($w, (string) $item) + 0.4));
        }

        return $h;
    }

    private function checksTable(int $max): void
    {
        $checks = array_slice((array) $this->facts()['checks'], 0, $max);
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
            $cells = [
                (string) $c['at'], (string) $c['place'], (string) $c['doc'] . ((string) $c['ref'] !== '' ? "\n" . $c['ref'] : ''),
                (string) $c['levels'], (string) $c['presented'], SseSheetFactsService::verdictLabel((string) ($c['verdict_key'] ?? ''), $this->lang === 1 ? 'en' : 'fr') . ((string) $c['controller'] !== '' ? "\n" . $c['controller'] : ''),
            ];
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
        if ($text === '' && !$required) {
            return;
        }
        if ($text === '') {
            return;
        }
        $label = $this->d['text_labels'][$key] ?? [$key, $key];
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
        return '<div style="font-size:' . $size . 'pt;line-height:1.28;">' . $html . '</div>';
    }

    /** Hauteur (mm) d'un HTML dans une cellule : écrit puis annule (transaction TCPDF). */
    private function htmlHeight(string $html, float $w, float $size): float
    {
        $pdf = $this->pdf;
        $pdf->SetFont($this->sans, '', $size);
        $pdf->startTransaction();
        $y0 = $pdf->GetY();
        $pageBefore = $pdf->getPage();
        $pdf->writeHTMLCell($w, 0, self::MARGIN + 4.0, 20.0, $this->wrapHtml($html, $size), 0, 1, false, true, 'L', true);
        $h = ($pdf->getPage() === $pageBefore ? $pdf->GetY() : 280.0) - 20.0;
        $pdf->rollbackTransaction(true);
        $pdf->SetY($y0);

        return max(4.0, $h);
    }

    /** Mentions de la dernière page : informations sur le document et avertissement de jeu. */
    private function about(): void
    {
        $pdf = $this->pdf;
        $pdf->setPage($this->pages);
        $room = self::BOTTOM - $this->y;
        if ($room < 34.0) {
            if (!$this->newPage()) {
                return;
            }
        }
        $d = $this->d;
        $this->heading($this->t('about'));
        $y = $this->y + 6.4;
        $y = $this->grid([
            [$this->t('ref'), (string) $d['ref']], [$this->t('issued'), $this->date((string) $d['issued_at'])],
            [$this->t('validity'), ($d['valid_until'] ?? null) !== null ? $this->date((string) $d['valid_until']) : $this->t('unknown')[$this->lang]],
            [$this->t('diffusion'), (string) $d['classification'][$this->lang]], [$this->t('issuer'), (string) ($d['service'] !== '' ? $d['service'] : $d['authority'][$this->lang])],
        ], self::MARGIN, $y, self::CONTENT_W, 3);
        $pdf->SetFont($this->sans, 'I', 7.8);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY(self::MARGIN, $y + 2.0);
        $pdf->MultiCell(self::CONTENT_W, 3.8, self::DISCLAIMER[$this->lang], 0, 'L', false, 1, null, null, true, 0, false, true, 16.0, 'T', true);
        $this->y = $pdf->GetY() + 2.0;
    }

    // ------------------------------------------------------------- Éléments

    private function footer(int $page): void
    {
        $d = $this->d;
        $pdf = $this->pdf;
        $l = self::MARGIN;
        $pdf->SetDrawColor(...self::LINE);
        $pdf->SetLineWidth(0.25);
        $pdf->Line($l, 276.0, self::PAGE_W - $l, 276.0);
        $style = ['position' => '', 'border' => false, 'padding' => 0, 'fgcolor' => [0, 0, 0], 'bgcolor' => false, 'text' => false];
        $pdf->write1DBarcode((string) $d['ref'], 'C128', $l, 279.0, 48.0, 8.0, 0.4, $style, 'N');
        $pdf->SetFont($this->sans, '', 6.5);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($l, 287.6);
        $pdf->Cell(48.0, 3.0, (string) $d['ref'], 0, 0, 'L');
        if ($d['footer']) {
            $pdf->SetFont($this->sans, '', 7.2);
            $pdf->SetXY(64.0, 280.0);
            $pdf->Cell(82.0, 3.6, self::fiction($this->lang), 0, 1, 'C');
            $pdf->SetFont($this->sans, 'I', 6.4);
            $pdf->SetXY(64.0, 284.2);
            $pdf->Cell(82.0, 3.2, self::fiction(1 - $this->lang), 0, 0, 'C');
        }
        $pdf->SetFont($this->sans, '', 7);
        $pdf->SetXY(150.0, 280.0);
        $pdf->Cell(48.0, 3.6, $this->t('page')[$this->lang] . ' ' . $page . '/' . $this->pages, 0, 1, 'R');
        $pdf->SetFont($this->sans, '', 6.4);
        $pdf->SetXY(150.0, 284.2);
        $pdf->Cell(48.0, 3.2, (string) $d['generated_at'], 0, 0, 'R');
    }

    private function watermark(): void
    {
        if (!$this->d['watermark']) {
            return;
        }
        $pdf = $this->pdf;
        $pdf->StartTransform();
        $pdf->SetAlpha(0.05);
        $pdf->SetTextColor(...$this->accent());
        $pdf->SetFont($this->sans, 'B', 62);
        $text = 'FICTION - RP';
        $pdf->Rotate(38, 105.0, 160.0);
        $pdf->Text(105.0 - $pdf->GetStringWidth($text) / 2, 152.0, $text);
        $pdf->SetAlpha(1.0);
        $pdf->StopTransform();
    }

    private static function fiction(int $lang): string
    {
        return $lang === 1 ? 'Fictional document — Arma 3 role-play' : 'Document de fiction — jeu de rôle Arma 3';
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

    /** @param array{0: string, 1: string} $title */
    private function heading(array $title, ?float $x = null, ?float $w = null): void
    {
        $pdf = $this->pdf;
        $x ??= self::MARGIN;
        $w ??= self::CONTENT_W;
        $y = $this->y;
        $pdf->SetFillColor(...$this->accent());
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
        $pdf->Cell(max(10.0, $w - $primaryW - 12.0), 4.4, $title[1 - $this->lang], 0, 0, 'L');
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
            [$text, $size] = $this->shrink($text, $colW - 3.0, 9.6, 7.0);
            // La barre de caviardage n'existe pas dans la police grasse : elle s'écrit en police normale.
            $pdf->SetFont($this->sans, trim($value) !== '' && !str_contains($value, '█') ? 'B' : '', $size);
            $pdf->SetTextColor(...(trim($value) !== '' ? self::INK : self::MUTED));
            $pdf->SetXY($cx, $rowY + 3.9);
            $pdf->MultiCell($colW - 3.0, 4.0, $text, 0, 'L', false, 1, null, null, true, 0, false, true, 11.0, 'T', true);
            $rowMax = max($rowMax, 4.6 + min(11.0, $pdf->getStringHeight($colW - 3.0, $text)));
        }

        return $rowY + max(9.2, $rowMax);
    }

    /** @return array{0: string, 1: float} */
    private function shrink(string $text, float $w, float $size, float $min): array
    {
        $pdf = $this->pdf;
        for ($s = $size; $s >= $min; $s -= 0.4) {
            $pdf->SetFont($this->sans, 'B', $s);
            if ($pdf->getStringHeight($w, $text) <= 4.4 * 2 && $pdf->GetStringWidth($text) <= $w * 2.0) {
                return [$text, $s];
            }
        }
        $pdf->SetFont($this->sans, 'B', $min);
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
