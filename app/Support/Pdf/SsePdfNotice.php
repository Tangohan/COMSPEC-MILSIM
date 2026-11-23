<?php

declare(strict_types=1);

namespace App\Support\Pdf;

use App\Services\Sse\SseRedactionService;
use App\Support\SseUsClassification;

/**
 * Page explicative (« notice ») placée au début des PDF du bureau SSE : à quoi sert le document, d'où viennent les données,
 * comment le lire (niveaux de classification, marques de masquage, sigles), ce qui a été retiré à ce niveau, règles de
 * diffusion et de conservation, identifiants (référence, date, empreinte courte), avertissement et sommaire cliquable.
 *
 * Une seule page, dessinée avec les primitives TCPDF (Cell, MultiCell, Rect) : le même dessin sert aux documents au gabarit
 * Athena (AthenaTcpdf) et aux fiches à bandeaux de couleur (SseSheetPdf). Elle se dessine APRÈS le reste du document (la page est
 * réservée au début, puis remplie) : le sommaire connaît ainsi les numéros de page. Elle ne contient que des catégories, des
 * niveaux et des nombres : jamais une valeur masquée.
 *
 * Textes : paires [français, anglais], le PDF suit la langue du document ('lang' => 'fr'|'en').
 */
final class SsePdfNotice
{
    /** Types de document : clé => [titre fr, titre en, à quoi il sert fr, en, d'où viennent les données fr, en]. */
    private const TYPES = [
        'case' => [
            ['Dossier SSE', 'SSE case file'],
            ['Ce dossier réunit, pour une même affaire, les pièces rassemblées par le bureau SSE : flash opérationnel, compte rendu initial, personnes et sites rattachés, corrélations, notes et preuves. Il sert à instruire l’affaire et à transmettre un état de la situation à un destinataire habilité.', 'This file gathers, for a single case, the items collected by the SSE office: operational flash, initial report, linked persons and sites, correlations, notes and evidence. It is used to work the case and to hand an up-to-date picture to a cleared recipient.'],
            ['Registre SSE du portail Athena : dossier, personnes, sites, notes et preuves saisis par les opérateurs et les analystes, arrêtés à la date de génération.', 'Athena portal SSE register: case, persons, sites, notes and evidence entered by operators and analysts, as of the generation date.'],
        ],
        'identification' => [
            ['Fiche d’identification', 'Identification sheet'],
            ['Cette fiche présente l’état civil, le signalement, les identifiants et la biométrie d’une personne du registre. Elle sert à la reconnaître et à la rapprocher d’un dossier.', 'This sheet shows the civil status, description, identifiers and biometrics of a person in the register. It is used to recognise the person and to match them to a case.'],
            ['Fiche de la personne au registre SSE et contrôles de terrain, arrêtés à la date d’émission.', 'The person’s SSE register entry and field checks, as of the issue date.'],
        ],
        'vigilance' => [
            ['Fiche de vigilance', 'Vigilance sheet'],
            ['Cette fiche est un avertissement : elle signale une personne à surveiller (niveau de menace, armement, dernière observation) et rappelle la conduite à tenir et le service à informer.', 'This sheet is a warning: it flags a person of interest (threat level, armament, last sighting) and recalls the expected conduct and the service to inform.'],
            ['Fiche de la personne au registre SSE, signalements et contrôles de terrain, arrêtés à la date d’émission.', 'The person’s SSE register entry, reports and field checks, as of the issue date.'],
        ],
        'profile' => [
            ['Fiche de renseignement', 'Intelligence sheet'],
            ['Cette fiche résume ce que le bureau sait d’une personne, avec la fiabilité de la source et la crédibilité de l’information. Elle sert à orienter l’exploitation et la décision.', 'This sheet summarises what the office knows about a person, with source reliability and information credibility. It is used to guide exploitation and decision-making.'],
            ['Fiche de la personne au registre SSE et contrôles de terrain, arrêtés à la date d’émission.', 'The person’s SSE register entry and field checks, as of the issue date.'],
        ],
        'control' => [
            ['Fiche de contrôle terrain', 'Field check sheet'],
            ['Cette fiche relève les contrôles d’identité effectués sur une personne (date, lieu, papier présenté, vérifications, résultat). Elle sert à garder la trace d’un contrôle.', 'This sheet records the identity checks carried out on a person (date, place, document presented, verifications, result). It is used to keep a trace of a check.'],
            ['Contrôles de terrain enregistrés au registre SSE, arrêtés à la date d’émission.', 'Field checks recorded in the SSE register, as of the issue date.'],
        ],
        'document' => [
            ['Document du bureau SSE', 'SSE office document'],
            ['Ce document a été rédigé dans le bureau SSE (note, rapport, compte rendu ou modèle). Il sert à consigner et à diffuser une information aux personnes habilitées.', 'This document was written in the SSE office (note, report, minutes or template). It is used to record and circulate information to cleared personnel.'],
            ['Texte saisi dans l’éditeur du bureau SSE, dans son dernier état enregistré.', 'Text entered in the SSE office editor, in its last saved state.'],
        ],
        'interest' => [
            ['Dossier d’intérêt', 'Case of interest'],
            ['Ce dossier suit une hypothèse de travail sur une personne ou un réseau : synthèse, analyse, graphe des liens, fiches liées, discussion et historique. Aucune identité n’y est confirmée sans validation humaine.', 'This file follows a working hypothesis on a person or a network: summary, analysis, link graph, linked sheets, discussion and history. No identity is confirmed in it without human validation.'],
            ['Dossier d’intérêt du bureau SSE, fiches de terrain et analyses rattachées, arrêtés à la date de génération.', 'SSE office case of interest, with attached field sheets and analyses, as of the generation date.'],
        ],
        'transmission' => [
            ['Transmission terrain', 'Field transmission'],
            ['Ce document restitue des transmissions reçues du terrain (journal ou événement) avec leurs pièces. Il sert à garder une trace datée de ce qui a été transmis et à qui.', 'This document reproduces transmissions received from the field (log or event) with their attachments. It is used to keep a dated trace of what was sent and to whom.'],
            ['Journal des transmissions du bureau SSE, arrêté à la date de génération.', 'SSE office transmission log, as of the generation date.'],
        ],
        'notice' => [
            ['Avis', 'Notice'],
            ['Cet avis signale une personne recherchée ou à surveiller et indique les faits, les mesures à prendre et le service à informer. Il est destiné aux personnes habilitées.', 'This notice flags a wanted person or a person of interest and states the facts, the measures to take and the service to inform. It is intended for cleared personnel.'],
            ['Avis émis par le bureau SSE à partir de la fiche de la personne, arrêté à la date d’émission.', 'Notice issued by the SSE office from the person’s entry, as of the issue date.'],
        ],
    ];

    /** Textes de la notice : clé => [français, anglais]. */
    private const S = [
        'kicker' => ['À LIRE EN PREMIER', 'READ FIRST'],
        'title' => ['Notice du document', 'About this document'],
        'purpose' => ['À quoi sert ce document', 'What this document is for'],
        'ids' => ['Identifiants du document', 'Document identifiers'],
        'origin' => ['D’où viennent les données', 'Where the data comes from'],
        'toc' => ['Sommaire', 'Contents'],
        'read' => ['Comment le lire', 'How to read it'],
        'removed' => ['Ce qui a été retiré, et pourquoi', 'What was withheld, and why'],
        'rules' => ['Diffusion et conservation', 'Distribution and retention'],
        'legal' => ['Avertissement', 'Notice'],
        'ref' => ['Référence', 'Reference'],
        'generated' => ['Générée le', 'Generated on'],
        'fingerprint' => ['Empreinte du contenu', 'Content fingerprint'],
        'level' => ['Niveau de ce document', 'Level of this document'],
        'issuer' => ['Émetteur', 'Issuer'],
        'period' => ['Période couverte', 'Period covered'],
        'version' => ['Version', 'Version'],
        'source' => ['Source', 'Source'],
        'page' => ['p.', 'p.'],
        'levels_head' => ['Niveaux de classification (couleur du bandeau)', 'Classification levels (banner colour)'],
        'mark' => ['Masquage', 'Withholding'],
        'mark_text' => ['Un rectangle noir (ou une suite « ██ ») remplace une information retirée. Sa longueur ne dit rien de la valeur retirée. Quand une lettre l’accompagne, elle renvoie à la légende des catégories en bas de page.', 'A black rectangle (or a « ██ » run) replaces withheld information. Its length says nothing about the withheld value. When a letter accompanies it, it refers to the category legend at the foot of the page.'],
        'acro_head' => ['Sigles', 'Acronyms'],
        'acro' => ['SSE : exploitation de site sensible · DTG : groupe date-heure · CUI : information non classifiée contrôlée · NOFORN : pas de diffusion à l’étranger · ORCON : diffusion contrôlée par l’émetteur · SCI : renseignement compartimenté.', 'SSE: sensitive site exploitation · DTG: date-time group · CUI: controlled unclassified information · NOFORN: no foreign dissemination · ORCON: originator controlled · SCI: sensitive compartmented information.'],
        'lvl_interne' => ['usage interne au bureau, information sensible non classifiée.', 'internal office use, sensitive unclassified information.'],
        'lvl_encadrement' => ['diffusion limitée aux personnes qui ont le besoin d’en connaître.', 'distribution limited to personnel with a need to know.'],
        'lvl_confidentiel' => ['accès nominatif habilité ; identité et biométrie lisibles à ce niveau.', 'named, cleared access; identity and biometrics readable at this level.'],
        'lvl_tres_restreint' => ['niveau le plus élevé ; toutes les catégories sont lisibles.', 'highest level; every category is readable.'],
        'this' => ['ce document', 'this document'],
        'rem_none' => ['Aucune information n’est retirée à ce niveau : toutes les catégories sont lisibles en clair.', 'Nothing is withheld at this level: every category is readable in clear.'],
        'rem_some' => ['Niveau de diffusion de cet exemplaire : %s. Les catégories suivantes sont retirées, car elles ne sont lisibles qu’à partir du niveau indiqué :', 'Distribution level of this copy: %s. The following categories are withheld, as they are only readable from the level shown:'],
        'rem_from' => ['lisible à partir de %s', 'readable from %s'],
        'rem_count' => ['%d passage(s) masqué(s) dans cet exemplaire.', '%d portion(s) withheld in this copy.'],
        'rem_how' => ['Le retrait dépend du niveau de diffusion du document et de la catégorie de chaque donnée, pas de l’habilitation du lecteur. Pour obtenir les passages retirés, demander une communication à un niveau supérieur au service émetteur, en citant la référence.', 'Withholding depends on the distribution level of the document and on the category of each item, not on the reader’s clearance. To obtain withheld portions, ask the issuing service for release at a higher level, quoting the reference.'],
        'rem_na' => ['Ce document n’est pas expurgé par catégorie : il est produit au niveau de classification indiqué. Une version à un autre niveau ne s’obtient qu’auprès du service émetteur, en citant la référence.', 'This document is not redacted by category: it is produced at the classification level shown. A version at another level can only be obtained from the issuing service, quoting the reference.'],
        'rule1' => ['Réservé aux personnes habilitées au niveau %s ou supérieur et ayant le besoin d’en connaître.', 'For personnel cleared at level %s or higher with a need to know only.'],
        'rule2' => ['Toute reproduction, extraction ou transmission hors du bureau SSE exige l’accord écrit du chef de bureau. Ne pas dissocier le document de ses pièces jointes.', 'Any copy, extract or onward transmission outside the SSE office requires the written agreement of the head of office. Do not separate the document from its attachments.'],
        'rule3' => ['Conserver l’original dans le dossier SSE d’origine, selon le délai de conservation et la date de révision de classification portés par le document ; ne pas garder de copie hors contrôle.', 'Keep the original in the originating SSE file, in line with the retention period and classification review date carried by the document; keep no uncontrolled copy.'],
        'rule4' => ['Un exemplaire imprimé se restitue ou se détruit dès que le besoin cesse.', 'A printed copy is returned or destroyed as soon as the need ends.'],
        'legal_text' => ['Document produit automatiquement par le portail Athena d’après les données du registre à la date indiquée : il ne vaut que pour cette date et ce niveau de diffusion. Toute divulgation, copie ou utilisation non autorisée engage la responsabilité de son auteur. En cas de doute sur l’authenticité, comparer la référence et l’empreinte avec le registre SSE.', 'Document generated automatically by the Athena portal from register data at the date shown: it is valid only for that date and distribution level. Any unauthorised disclosure, copying or use is the responsibility of its author. If in doubt about authenticity, compare the reference and fingerprint with the SSE register.'],
        'fp_note' => ['Empreinte : les 12 premiers caractères du condensat SHA-256 du contenu.', 'Fingerprint: the first 12 characters of the SHA-256 digest of the content.'],
    ];

    private \TCPDF $pdf;

    /** @var array<string, mixed> */
    private array $p;

    private int $lang;

    private string $sans;

    private string $mono;

    /** @var array{0: int, 1: int, 2: int} */
    private array $ink;

    /** @var array{0: int, 1: int, 2: int} */
    private array $muted;

    /** @var array{0: int, 1: int, 2: int} */
    private array $line;

    /** @var array{0: int, 1: int, 2: int} */
    private array $accent;

    private float $x;

    private float $w;

    private float $y;

    private float $bottom;

    private float $k = 1.0;

    private bool $dry = false;

    /**
     * Paramètre d'appel « avec_notice » : activé par défaut ; '0', 'false', 'non', 'off', false et 0 le désactivent.
     */
    public static function enabled(mixed $value, bool $default = true): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }
        if (is_bool($value)) {
            return $value;
        }
        $v = strtolower(trim((string) $value));
        if (in_array($v, ['0', 'false', 'non', 'no', 'off', 'sans'], true)) {
            return false;
        }

        return in_array($v, ['1', 'true', 'oui', 'yes', 'on', 'avec'], true) ? true : $default;
    }

    /** Empreinte courte du contenu : 12 caractères hexadécimaux du SHA-256, en groupes de quatre (« 0FD7 C3CF D1B3 »). */
    public static function fingerprint(mixed $data): string
    {
        $json = json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $hex = strtoupper(substr(hash('sha256', is_string($json) ? $json : serialize($data)), 0, 12));

        return trim(chunk_split($hex, 4, ' '));
    }

    /** Titre du type de document dans la langue donnée. */
    public static function typeTitle(string $type, string $lang): string
    {
        return (self::TYPES[$type] ?? self::TYPES['document'])[0][$lang === 'en' ? 1 : 0];
    }

    /**
     * Dessine la notice sur la page courante. Retourne le bas du contenu (mm).
     *
     * Clés de $p : lang, type, title, ref, level (code de classification), generated_at, ids (liste [libellé|[fr, en], valeur]),
     * issuer, period, version, source, fingerprint, redaction (null | {level, required?, masked?}), toc (liste {label, page}),
     * geometry {x, y, w, bottom}.
     *
     * @param array<string, mixed> $p
     */
    public static function draw(\TCPDF $pdf, array $p): float
    {
        $n = new self($pdf, $p);
        // Mise à l'échelle des textes : la première taille qui tient sur la page est dessinée.
        foreach ([1.0, 0.94, 0.88, 0.82, 0.76] as $k) {
            $n->k = $k;
            $n->dry = true;
            $n->y = (float) $n->p['geometry']['y'];
            $n->layout();
            if ($n->y <= $n->bottom || $k === 0.76) {
                break;
            }
        }
        $n->dry = false;
        $n->y = (float) $n->p['geometry']['y'];
        $n->layout();

        return $n->y;
    }

    /**
     * Remplit la page réservée `$page` d'un document au gabarit Athena (AthenaTcpdf), puis revient à la dernière page.
     *
     * @param array<string, mixed> $p mêmes clés que draw(), sans geometry
     */
    public static function fillReserved(AthenaTcpdf $pdf, int $page, array $p): void
    {
        $last = $pdf->getPage();
        $pdf->setPage($page);
        $pdf->SetAutoPageBreak(false, 0);
        self::draw($pdf, $p + ['geometry' => ['x' => AthenaPdfTheme::MARGIN, 'y' => $pdf->contentTop(), 'w' => $pdf->getPageWidth() - 2 * AthenaPdfTheme::MARGIN, 'bottom' => $pdf->getPageHeight() - AthenaPdfTheme::BOTTOM - 3.0]]);
        $pdf->SetAutoPageBreak(true, AthenaPdfTheme::BOTTOM + ($pdf->athenaMeta['note'] !== '' ? 3 : 0));
        $pdf->setPage($last);
    }

    /** @param array<string, mixed> $p */
    private function __construct(\TCPDF $pdf, array $p)
    {
        $g = (array) ($p['geometry'] ?? []);
        $p['geometry'] = ['x' => (float) ($g['x'] ?? 15.0), 'y' => (float) ($g['y'] ?? 38.0), 'w' => (float) ($g['w'] ?? 180.0), 'bottom' => (float) ($g['bottom'] ?? 272.0)];
        $this->pdf = $pdf;
        $this->p = $p;
        $this->lang = ($p['lang'] ?? 'fr') === 'en' ? 1 : 0;
        $this->sans = AthenaPdfTheme::sans();
        $this->mono = AthenaPdfTheme::mono();
        $this->ink = AthenaPdfTheme::rgb(AthenaPdfTheme::INK);
        $this->muted = AthenaPdfTheme::rgb(AthenaPdfTheme::MUTED);
        $this->line = AthenaPdfTheme::rgb(AthenaPdfTheme::LINE);
        $this->accent = AthenaPdfTheme::rgb(AthenaPdfTheme::ACCENT_DARK);
        $this->x = $p['geometry']['x'];
        $this->w = $p['geometry']['w'];
        $this->y = $p['geometry']['y'];
        $this->bottom = $p['geometry']['bottom'];
    }

    private function s(string $key): string
    {
        return self::S[$key][$this->lang];
    }

    private function level(): string
    {
        return SseUsClassification::normalize((string) ($this->p['level'] ?? ''));
    }

    // ---------------------------------------------------------------- Dessin

    private function layout(): void
    {
        $type = self::TYPES[(string) ($this->p['type'] ?? '')] ?? self::TYPES['document'];
        $level = $this->level();
        $levelLabel = SseUsClassification::label($level, $this->lang === 1 ? 'en' : 'fr');

        // Titre.
        $this->text($this->x, $this->y, $this->w, $this->s('kicker') . ' · ' . mb_strtoupper($type[0][$this->lang], 'UTF-8'), 'B', 6.8, $this->accent, 'L', 0.25);
        $this->y += 3.6 * $this->k;
        $this->y += $this->text($this->x, $this->y, $this->w, $this->s('title'), 'B', 16.0, $this->ink, 'L', 0.0);
        $ref = trim((string) ($this->p['ref'] ?? ''));
        $sub = trim((string) ($this->p['title'] ?? ''));
        $subLine = trim($sub . ($ref !== '' && !str_contains($sub, $ref) ? ' · ' . $ref : ''), ' ·');
        if ($subLine !== '') {
            $this->y += $this->text($this->x, $this->y + 0.4, $this->w, $subLine, '', 8.6, $this->muted, 'L', 0.0) + 0.4;
        }
        $this->rule(1.4, $this->ink, 0.5);

        // À quoi sert + identifiants / origine (deux colonnes).
        $this->head($this->s('purpose'));
        $this->para($type[1][$this->lang]);

        $gap = 6.0;
        $cw = ($this->w - $gap) / 2;
        $top = $this->y;
        $this->head($this->s('ids'), $this->x, $cw);
        $ids = [[$this->s('ref'), $ref !== '' ? $ref : '—', true]];
        foreach ((array) ($this->p['ids'] ?? []) as $pair) {
            $label = is_array($pair[0]) ? (string) ($pair[0][$this->lang] ?? '') : (string) $pair[0];
            if (trim((string) ($pair[1] ?? '')) !== '') {
                $ids[] = [$label, (string) $pair[1], true];
            }
        }
        $ids[] = [$this->s('level'), $levelLabel, false];
        $ids[] = [$this->s('generated'), (string) ($this->p['generated_at'] ?? ''), false];
        $ids[] = [$this->s('fingerprint'), (string) ($this->p['fingerprint'] ?? '—'), true];
        $yl = $this->kv($ids, $this->x, $cw);
        $this->y = $top;
        $this->head($this->s('origin'), $this->x + $cw + $gap, $cw);
        $orig = [[$this->s('source'), (string) (($this->p['source'] ?? '') !== '' ? $this->p['source'] : $type[2][$this->lang]), false]];
        foreach (['period', 'version', 'issuer'] as $key) {
            if (trim((string) ($this->p[$key] ?? '')) !== '') {
                $orig[] = [$this->s($key), (string) $this->p[$key], false];
            }
        }
        $yr = $this->kv($orig, $this->x + $cw + $gap, $cw, true);
        $this->y = max($yl, $yr) + 1.0 * $this->k;

        // Sommaire.
        $toc = (array) ($this->p['toc'] ?? []);
        if ($toc !== []) {
            $this->head($this->s('toc'));
            $this->toc($toc);
        }

        // Lecture.
        $this->head($this->s('read'));
        $this->levels($level);
        $this->mark();
        $this->para($this->s('acro_head') . ' : ' . $this->s('acro'), 6.8, $this->muted);

        // Ce qui a été retiré.
        $this->head($this->s('removed'));
        $this->removed($level, $levelLabel, $ref);

        // Diffusion et conservation.
        $this->head($this->s('rules'));
        foreach (['rule1', 'rule2', 'rule3', 'rule4'] as $key) {
            $text = $key === 'rule1' ? sprintf($this->s($key), SseUsClassification::abbr($level)) : $this->s($key);
            $this->bullet($text);
        }

        // Avertissement.
        $this->head($this->s('legal'));
        $this->para($this->s('legal_text'), 6.9, $this->muted);
        $this->para($this->s('fp_note'), 6.4, $this->muted);
    }

    /** Titre de rubrique : capitales grasses et filet. */
    private function head(string $label, ?float $x = null, ?float $w = null): void
    {
        $x ??= $this->x;
        $w ??= $this->w;
        $this->y += 2.2 * $this->k;
        $this->text($x, $this->y, $w, mb_strtoupper($label, 'UTF-8'), 'B', 7.0, $this->accent, 'L', 0.3);
        $this->y += 3.7 * $this->k;
        $this->ruleAt($x, $w, $this->y, $this->line, 0.2);
        $this->y += 1.0 * $this->k;
    }

    private function rule(float $before, array $rgb, float $width): void
    {
        $this->y += $before;
        $this->ruleAt($this->x, $this->w, $this->y, $rgb, $width);
        $this->y += 0.6;
    }

    /** @param array{0: int, 1: int, 2: int} $rgb */
    private function ruleAt(float $x, float $w, float $y, array $rgb, float $width): void
    {
        if ($this->dry) {
            return;
        }
        $this->pdf->SetDrawColor(...$rgb);
        $this->pdf->SetLineWidth($width);
        $this->pdf->Line($x, $y, $x + $w, $y);
    }

    /** @param array{0: int, 1: int, 2: int}|null $color */
    private function para(string $text, float $size = 7.8, ?array $color = null): void
    {
        $this->y += $this->text($this->x, $this->y, $this->w, $text, '', $size, $color ?? $this->ink, 'L', 0.0) + 1.0 * $this->k;
    }

    private function bullet(string $text): void
    {
        if (!$this->dry) {
            $this->pdf->SetFillColor(...$this->accent);
            $this->pdf->Rect($this->x + 0.4, $this->y + 1.3 * $this->k, 1.2, 1.2, 'F');
        }
        $this->y += $this->text($this->x + 3.2, $this->y, $this->w - 3.2, $text, '', 7.6, $this->ink, 'L', 0.0) + 0.7 * $this->k;
    }

    /** Hauteur d'un texte, sans rien dessiner. */
    private function measure(float $w, string $text, string $style, float $size): float
    {
        $this->pdf->SetFont($this->sans, $style, $size * $this->k);

        return max(0.1, $this->pdf->getStringHeight($w, $text));
    }

    /**
     * Texte en bloc : dessine (sauf à blanc) et retourne la hauteur.
     *
     * @param array{0: int, 1: int, 2: int} $rgb
     */
    private function text(float $x, float $y, float $w, string $text, string $style, float $size, array $rgb, string $align, float $spacing, string $font = ''): float
    {
        $pdf = $this->pdf;
        $pdf->SetFont($font !== '' ? $font : $this->sans, $style, $size * $this->k);
        $h = max(0.1, $pdf->getStringHeight($w, $text));
        if (!$this->dry) {
            $pdf->SetTextColor(...$rgb);
            $pdf->setFontSpacing($spacing);
            $pdf->SetXY($x, $y);
            $pdf->MultiCell($w, $h / max(1, $pdf->getNumLines($text, $w)), $text, 0, $align, false, 1, $x, $y, true, 0, false, true, 0, 'T', false);
            $pdf->setFontSpacing(0);
        }

        return $h;
    }

    /**
     * Tableau étiquette / valeur, retourne le bas.
     *
     * @param list<array{0: string, 1: string, 2: bool}> $rows [étiquette, valeur, police à chasse fixe]
     */
    private function kv(array $rows, float $x, float $w, bool $stack = false): float
    {
        $lw = $stack ? 0.0 : 30.0;
        $y = $this->y;
        foreach ($rows as [$label, $value, $mono]) {
            $this->pdf->SetFont($this->sans, '', 6.8 * $this->k);
            if ($stack) {
                $lh = $this->text($x, $y, $w, mb_strtoupper($label, 'UTF-8'), 'B', 6.2, $this->muted, 'L', 0.2);
                $vh = $this->text($x, $y + $lh, $w, $value, '', 7.6, $this->ink, 'L', 0.0);
                $y += $lh + $vh + 1.2 * $this->k;
                continue;
            }
            $lh = $this->text($x, $y + 0.2, $lw - 1.0, $label, '', 6.9, $this->muted, 'L', 0.0);
            $vh = $this->text($x + $lw, $y, $w - $lw, $value, $mono ? 'B' : '', 7.6, $this->ink, 'L', 0.0, $mono ? $this->mono : '');
            $y += max($lh + 0.2, $vh) + 0.9 * $this->k;
        }

        return $y;
    }

    /** @param list<array{label?: string, page?: int|string}> $toc */
    private function toc(array $toc): void
    {
        $pdf = $this->pdf;
        $rh = 4.0 * $this->k;
        $cols = count($toc) > 6 ? 2 : 1;
        $gap = 8.0;
        $cw = ($this->w - ($cols - 1) * $gap) / $cols;
        $per = (int) ceil(count($toc) / $cols);
        $top = $this->y;
        foreach (array_values($toc) as $i => $row) {
            $col = intdiv($i, $per);
            $x = $this->x + $col * ($cw + $gap);
            $y = $top + ($i % $per) * $rh;
            $page = (int) ($row['page'] ?? 0);
            $label = (string) ($row['label'] ?? '');
            if (!$this->dry) {
                $link = $page > 0 ? $pdf->AddLink() : '';
                if ($link !== '') {
                    $pdf->SetLink($link, 0, $page);
                }
                $pdf->SetFont($this->sans, '', 7.6 * $this->k);
                $pdf->SetTextColor(...$this->ink);
                $pdf->SetXY($x, $y);
                $pdf->Cell($cw - 12.0, $rh, $label, 0, 0, 'L', false, $link, 1);
                $pdf->SetFont($this->mono, 'B', 7.4 * $this->k);
                $pdf->SetTextColor(...$this->accent);
                $pdf->SetXY($x + $cw - 12.0, $y);
                $pdf->Cell(12.0, $rh, $page > 0 ? $this->s('page') . ' ' . $page : '', 0, 0, 'R', false, $link);
                $pdf->SetDrawColor(...$this->line);
                $pdf->SetLineWidth(0.1);
                $pdf->Line($x, $y + $rh, $x + $cw, $y + $rh);
            }
        }
        $this->y = $top + $per * $rh + 1.0;
    }

    /** Légende des niveaux de classification : pastille de la couleur du bandeau + sens ; le niveau du document est en gras. */
    private function levels(string $current): void
    {
        $pdf = $this->pdf;
        $this->text($this->x, $this->y, $this->w, $this->s('levels_head'), 'B', 6.6, $this->muted, 'L', 0.0);
        $this->y += 3.5 * $this->k;
        $lang = $this->lang === 1 ? 'en' : 'fr';
        foreach (SseUsClassification::codes() as $code) {
            $rgb = SseUsClassification::rgb($code);
            $on = $code === $current;
            $text = SseUsClassification::label($code, $lang) . ' — ' . $this->s('lvl_' . $code);
            $rh = max(3.8 * $this->k, $this->measure($this->w - 26.0, $text, $on ? 'B' : '', 7.2) + 0.6);
            if (!$this->dry) {
                $pdf->SetFillColor(...$rgb);
                $pdf->Rect($this->x, $this->y, 22.0, $rh - 0.5, 'F');
                $luma = 0.299 * $rgb[0] + 0.587 * $rgb[1] + 0.114 * $rgb[2];
                $pdf->SetFont($this->sans, 'B', 6.2 * $this->k);
                $pdf->SetTextColor(...($luma > 150 ? [0, 0, 0] : [255, 255, 255]));
                $pdf->SetXY($this->x, $this->y);
                $pdf->Cell(22.0, $rh - 0.5, SseUsClassification::abbr($code), 0, 0, 'C', false, '', 0, false, 'T', 'M');
                $this->text($this->x + 25.0, $this->y, $this->w - 26.0, $text, $on ? 'B' : '', 7.2, $this->ink, 'L', 0.0);
            }
            $this->y += $rh;
        }
        $this->y += 0.8 * $this->k;
    }

    /** Marque de masquage : exemple dessiné (rectangle noir) et explication. */
    private function mark(): void
    {
        $h = max(4.2 * $this->k, $this->measure($this->w - 26.0, $this->s('mark_text'), '', 7.2));
        if (!$this->dry) {
            $this->pdf->SetFillColor(0, 0, 0);
            $this->pdf->Rect($this->x, $this->y + 0.6, 22.0, min(3.2, $h - 0.8), 'F');
            $this->text($this->x + 25.0, $this->y, $this->w - 26.0, $this->s('mark_text'), '', 7.2, $this->ink, 'L', 0.0);
        }
        $this->y += $h + 1.0 * $this->k;
    }

    private function removed(string $level, string $levelLabel, string $ref): void
    {
        $red = $this->p['redaction'] ?? null;
        if (!is_array($red)) {
            $this->para($this->s('rem_na'), 7.4);

            return;
        }
        $required = is_array($red['required'] ?? null) ? $red['required'] : null;
        $rl = (string) ($red['level'] ?? $level);
        $hidden = [];
        foreach (array_keys(SseRedactionService::CATEGORIES) as $cat) {
            if (!SseRedactionService::visibleAt($cat, $rl, $required)) {
                $hidden[] = $cat;
            }
        }
        if ($hidden === []) {
            $this->para($this->s('rem_none'), 7.4);

            return;
        }
        $lang = $this->lang === 1 ? 'en' : 'fr';
        $this->para(sprintf($this->s('rem_some'), SseUsClassification::label($rl, $lang)), 7.4);
        foreach ($hidden as $cat) {
            $need = SseUsClassification::label(SseRedactionService::requiredLevel($cat, $required), $lang);
            $label = \App\Support\SseRedactionExplain::label($cat, $lang);
            $this->bullet($label . ' : ' . sprintf($this->s('rem_from'), $need));
        }
        $masked = (int) ($red['masked'] ?? 0);
        if ($masked > 0) {
            $this->para(sprintf($this->s('rem_count'), $masked), 7.4);
        }
        $this->para($this->s('rem_how') . ($ref !== '' ? ' (' . $ref . ')' : ''), 7.0, $this->muted);
    }
}
