<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseAlertRepository;
use App\Repositories\SseSheetRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Repositories\SseWantedSettingsRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseAlertGameService;
use App\Services\Sse\SseAlertProposals;
use App\Services\Sse\SseAlertReportPhotos;
use App\Services\Sse\SseAlertService;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseRedactionService;
use App\Services\Sse\SseSheetAccess;
use App\Support\SseAccessCatalog as Rights;
use App\Support\SseAlertLevel;
use App\Support\SseAlertReportForm;
use App\Support\SseAlertReportTo;
use App\Support\SseWantedCatalog as C;
use App\Support\SseWantedCodes;

/**
 * Signalements des alertes SSE (avis de recherche et fiches de vigilance) : page de signalement ouverte depuis le QR code d'un
 * document, liste des signalements et file des propositions à valider, photo et QR code servis par des routes contrôlées.
 *
 * Droits (rien de nouveau : sections existantes de SseAccessPolicy, via SseSheetAccess / SseWantedAccess) :
 *  - signaler : lire « Fichiers et sujets » avec un compte (jamais une session invitée) ;
 *  - voir les signalements, le QR code et l'adresse : lire « Fichiers et sujets » ;
 *  - changer l'état d'un signalement, révoquer l'adresse, décider une proposition, voir la photo jointe, voir la file des
 *    propositions : écrire dans « Fichiers et sujets » (SseWantedAccess::canManage).
 * La communauté est toujours celle de la session. Un jeton inconnu, d'une autre communauté, révoqué, ou d'une alerte au-dessus de
 * l'habilitation du lecteur donne la MÊME page « Signalement introuvable » (404). Chaque écriture exige le jeton CSRF.
 */
final class SseAlertReportController
{
    public const BASE = 'atak/sse/avis/signalements';

    public const TOKEN_EXPIRED = 'Votre page est restée ouverte trop longtemps ou la session a changé : rechargez la page puis recommencez.';

    public const NEED_READ = 'Droit manquant : lire « Fichiers et sujets ». Demandez-le à un gestionnaire des accès SSE.';

    public const NEED_MANAGE = 'Droit manquant : écrire dans « Fichiers et sujets » (gestion des dossiers SSE). Demandez-le à un gestionnaire des accès SSE.';

    public const NEED_ACCOUNT = 'Un compte de la communauté est nécessaire pour signaler : une session invitée ne peut pas envoyer de signalement. Connectez-vous avec votre compte.';

    public const UNAVAILABLE = 'Les signalements seront disponibles dès que l’étape de base de données « alertes SSE » aura été passée par l’équipe technique.';

    /** Onglets de la liste : clé => état filtré (« » : tous). */
    public const TABS = ['nouveau' => 'new', 'pris-en-compte' => 'acknowledged', 'recoupe' => 'corroborated', 'ecarte' => 'dismissed', 'tous' => ''];

    public const TAB_PROPOSALS = 'a-valider';

    private ?SseSheetAccess $rights;

    private SseAlertService $alerts;

    private ?SseAlertGameService $game;

    private ?SseAlertProposals $proposals;

    private SseAlertReportPhotos $photos;

    /** @var (\Closure(string, array<string, mixed>): Response)|null */
    private ?\Closure $render;

    /**
     * @param (\Closure(string, array<string, mixed>): Response)|null $render rendu d'une vue (tests) ; sans lui, la coque du portail SSE
     */
    public function __construct(
        private ?SseAccessCodeService $access = null,
        private ?SseClearanceService $clearance = null,
        ?SseSheetAccess $rights = null,
        ?SseAlertService $alerts = null,
        ?SseAlertGameService $game = null,
        ?SseAlertProposals $proposals = null,
        ?SseAlertReportPhotos $photos = null,
        ?\Closure $render = null,
        private bool $trustUploads = false,
    ) {
        $this->rights = $rights;
        $this->alerts = $alerts ?? new SseAlertService();
        $this->game = $game;
        $this->proposals = $proposals;
        $this->photos = $photos ?? new SseAlertReportPhotos();
        $this->render = $render;
    }

    // ------------------------------------------------------------ Page de signalement

    public function show(Request $request, array $params = []): Response
    {
        $denial = $this->gate();
        if ($denial !== null) {
            return $this->denialPage($denial);
        }
        $found = $this->resolve((string) ($params['token'] ?? ''));
        if ($found === null) {
            return $this->notFound();
        }

        return $this->reportPage($found['alert'], $found['link'], $found['alert']['state'] === 'active' ? 'form' : 'closed');
    }

    public function submit(Request $request, array $params = []): Response
    {
        $denial = $this->gate();
        if ($denial !== null) {
            return $this->denialPage($denial);
        }
        $found = $this->resolve((string) ($params['token'] ?? ''));
        if ($found === null) {
            return $this->notFound();
        }
        ['alert' => $alert, 'link' => $link] = $found;
        $old = $this->oldInput($request);
        if (!self::csrfOk($request)) {
            return $this->reportPage($alert, $link, $alert['state'] === 'active' ? 'form' : 'closed', ['errors' => [self::TOKEN_EXPIRED], 'old' => $old], 403);
        }
        if ($alert['state'] !== 'active') {
            return $this->reportPage($alert, $link, 'closed', ['errors' => ['Cette alerte n’est plus en cours : elle ne reçoit plus de signalement.']], 409);
        }
        $r = $this->rights();
        $tenantId = $r->tenantId();
        $photoPath = null;
        $form = $this->formOf($alert);
        // Une photo n'est reçue que si la conduite à tenir de la fiche la permet.
        $file = in_array('photo', $form['fields'], true) ? $this->uploadedPhoto() : null;
        if ($file !== null) {
            $stored = $this->photos->store($tenantId, $file);
            if (!$stored['ok']) {
                return $this->reportPage($alert, $link, 'form', ['errors' => [$stored['error']], 'old' => $old], 422);
            }
            $photoPath = $stored['path'];
        }
        $tz = $this->timezone($tenantId);
        $res = $this->alerts->report($tenantId, $link, [
            'what' => $old['what'], 'when' => SseWantedNoticeController::localToUtc($old['when'], $tz), 'place' => $old['place'], 'grid' => $old['grid'],
            'text' => $old['text'], 'dir' => $old['dir'], 'vehicle' => $old['vehicle'], 'sure' => $old['sure'], 'photo_path' => $photoPath, 'cid' => $old['cid'],
            'form' => $form, 'extras' => $old['extras'] ?? [], 'lang' => self::readerLang(),
        ], ['user' => $r->userId(), 'label' => $r->actorLabel(), 'source' => 'web'], ['ref' => (string) $alert['ref'], 'closed' => false, 'label' => '']);
        if (!$res['ok'] || $res['report'] === null) {
            $this->dropPhoto($photoPath);
            $status = match ($res['error']) {
                'rate_limited' => 429, 'closed', 'conflict' => 409, 'unavailable' => 503, 'account_required' => 403, default => 422,
            };

            return $this->reportPage($alert, $link, 'form', ['errors' => $res['errors'], 'old' => $old], $status);
        }
        if ($res['replay']) {
            // Même envoi rejoué (rechargement de la page) : la photo reçue en double n'est pas gardée.
            $this->dropPhoto($photoPath);
        }

        return $this->reportPage($alert, $link, 'receipt', ['report' => $res['report'], 'replay' => $res['replay']]);
    }

    /** QR code de l'adresse de signalement, en SVG : généré ici (TCPDF), aucun service externe. */
    public function qr(Request $request, array $params = []): Response
    {
        if ($this->gate() !== null || $this->rights()->tenantId() < 1) {
            return $this->plainNotFound();
        }
        $found = $this->resolve((string) ($params['token'] ?? ''));
        if ($found === null) {
            return $this->plainNotFound();
        }
        $svg = self::qrSvg(SseAlertReportTo::url((string) $found['link']['token']));
        if ($svg === '') {
            return $this->plainNotFound();
        }
        $response = (new Response())
            ->header('Content-Type', 'image/svg+xml; charset=utf-8')
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'")
            ->setBody($svg);
        if ($request->queryString('telecharger') !== '') {
            $response->header('Content-Disposition', 'attachment; filename="qr-signalement-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $found['alert']['ref']) . '.svg"');
        }

        return $response;
    }

    /** Photo jointe à un signalement : gestionnaires de la communauté seulement, type revérifié par le contenu. */
    public function photo(Request $request, array $params = []): Response
    {
        $r = $this->rights();
        if ($this->gate() !== null || !$r->wanted()->canManage()) {
            return $this->plainNotFound();
        }
        $report = $this->readableReport((int) ($params['id'] ?? 0));
        $file = $report !== null && $report['photo_path'] !== null ? $this->photos->resolve((string) $report['photo_path']) : null;
        if ($file === null) {
            return $this->plainNotFound();
        }
        $abs = $file['file'];
        $ext = ['image/png' => 'png', 'image/webp' => 'webp', 'image/jpeg' => 'jpg'][$file['mime']] ?? 'bin';

        return (new Response())
            ->header('Content-Type', $file['mime'])
            ->header('Content-Length', (string) filesize($abs))
            ->header('Content-Disposition', 'inline; filename="' . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $report['ref']) . '.' . $ext . '"')
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Referrer-Policy', 'no-referrer')
            ->setBodyStream(static function () use ($abs): void {
                $h = fopen($abs, 'rb');
                if ($h !== false) {
                    fpassthru($h);
                    fclose($h);
                }
            });
    }

    // ------------------------------------------------------------ Liste, états, révocation, propositions

    /** Tous les signalements de la communauté (onglets par état) et la file « À valider ». */
    public function index(Request $request, array $params = []): Response
    {
        $r = $this->rights();
        $w = $r->wanted();
        if (!$w->canRead() || $w->tenantId() < 1) {
            return $this->back(url('atak/sse/accueil'), 'error', self::NEED_READ);
        }
        $tenantId = $w->tenantId();
        $ready = SseAlertRepository::ready();
        $canManage = $w->canManage();
        $tab = $request->queryString('onglet', 'nouveau');
        if ($tab !== self::TAB_PROPOSALS && !isset(self::TABS[$tab])) {
            $tab = 'nouveau';
        }
        if ($tab === self::TAB_PROPOSALS && !$canManage) {
            $tab = 'nouveau';
        }
        $reports = [];
        $alerts = [];
        $counts = ['new' => 0, 'acknowledged' => 0, 'corroborated' => 0, 'dismissed' => 0, 'all' => 0];
        $proposals = [];
        if ($ready) {
            $all = $this->safe(fn (): array => $this->alerts->alerts()->listReports($tenantId, '', 300)) ?? [];
            $alerts = $this->alertsOf($tenantId, $all);
            foreach ($all as $row) {
                if (!($alerts[(int) $row['link_id']]['readable'] ?? false)) {
                    continue;
                }
                $counts['all']++;
                $counts[$row['status']] = ($counts[$row['status']] ?? 0) + 1;
                if ($tab === self::TAB_PROPOSALS || self::TABS[$tab] === '' || self::TABS[$tab] === $row['status']) {
                    $reports[] = $row;
                }
            }
            if ($canManage) {
                $proposals = $this->proposalRows($tenantId, $this->proposals()->listProposals($tenantId, 'pending', 100));
            }
        }

        return $this->page('atak.sse.alerts.reports', [
            'title' => 'Signalements',
            'tab' => $tab, 'reports' => $tab === self::TAB_PROPOSALS ? [] : $reports, 'alerts' => $alerts, 'counts' => $counts,
            'proposals' => $proposals, 'proposalCount' => count($proposals),
            'canManage' => $canManage, 'ready' => $ready, 'lang' => self::readerLang(), 'tz' => $this->timezone($tenantId),
            'csrfToken' => Csrf::token(), 'activeNav' => 'avis-signalements',
        ]);
    }

    public function status(Request $request, array $params = []): Response
    {
        $r = $this->rights();
        $back = $this->backUrl($request);
        if (!self::csrfOk($request)) {
            return $this->back($back, 'error', self::TOKEN_EXPIRED);
        }
        if (!$r->wanted()->canRead()) {
            return $this->back($back, 'error', self::NEED_READ);
        }
        if (!$r->wanted()->canManage()) {
            return $this->back($back, 'error', self::NEED_MANAGE);
        }
        $tenantId = $r->tenantId();
        $report = $this->readableReport((int) ($params['id'] ?? 0));
        if ($report === null) {
            return $this->back($back, 'error', 'Signalement introuvable.');
        }
        $status = $request->inputString('status');
        $res = $this->alerts->handleReport($tenantId, (int) $report['id'], $status, $request->inputString('note'), $r->wanted());
        if (!$res['ok']) {
            return $this->back($back, 'error', $res['errors'][0] ?? 'Le signalement n’a pas été modifié.');
        }

        return $this->back($back, 'success', sprintf('Signalement %s : %s.', $report['ref'], mb_strtolower(SseAlertLevel::REPORT_STATUSES[$status][0] ?? $status)));
    }

    public function revoke(Request $request, array $params = []): Response
    {
        $r = $this->rights();
        $back = $this->backUrl($request);
        if (!self::csrfOk($request)) {
            return $this->back($back, 'error', self::TOKEN_EXPIRED);
        }
        if (!$r->wanted()->canRead()) {
            return $this->back($back, 'error', self::NEED_READ);
        }
        if (!$r->wanted()->canManage()) {
            return $this->back($back, 'error', self::NEED_MANAGE);
        }
        $tenantId = $r->tenantId();
        $link = SseAlertRepository::ready() ? $this->safe(fn (): ?array => $this->alerts->alerts()->linkById($tenantId, (int) ($params['id'] ?? 0))) : null;
        $alert = $link !== null ? $this->alertOfLink($link) : null;
        if ($link === null || $alert === null) {
            return $this->back($back, 'error', 'Adresse de signalement introuvable.');
        }
        if ($link['revoked_at'] !== null) {
            return $this->back($back, 'error', 'Cette adresse de signalement est déjà révoquée.');
        }
        if (!$this->alerts->alerts()->revoke($tenantId, (int) $link['id'])) {
            return $this->back($back, 'error', 'Cette adresse de signalement est déjà révoquée.');
        }
        $this->alerts->audit($tenantId, $r->userId(), $r->actorLabel(), 'alert.link_revoked', 'sse_alert_link', (int) $link['id'], '', ['alert' => $alert['ref'], 'doc' => $link['doc']]);

        return $this->back($back, 'success', sprintf('Adresse de signalement de %s révoquée : son QR code ne fonctionne plus.', $alert['ref']));
    }

    public function decide(Request $request, array $params = []): Response
    {
        $r = $this->rights();
        $back = url(self::BASE) . '?onglet=' . self::TAB_PROPOSALS;
        if (!self::csrfOk($request)) {
            return $this->back($back, 'error', self::TOKEN_EXPIRED);
        }
        if (!$r->wanted()->canRead()) {
            return $this->back($back, 'error', self::NEED_READ);
        }
        if (!$r->wanted()->canManage()) {
            return $this->back($back, 'error', self::NEED_MANAGE);
        }
        $decision = $request->inputString('decision');
        if (!in_array($decision, ['accept', 'reject'], true)) {
            return $this->back($back, 'error', 'Choisissez d’accepter ou de refuser la proposition.');
        }
        $subject = trim($request->inputString('subject'));
        $res = $this->proposals()->decide($r->tenantId(), (int) ($params['id'] ?? 0), $decision === 'accept', $request->inputString('note'), $r, $subject !== '' ? $subject : null);
        if (!$res['ok']) {
            return $this->back($back, 'error', $res['errors'][0] ?? 'La proposition n’a pas été modifiée.');
        }

        return $this->back($back, 'success', $decision === 'accept' ? sprintf('Proposition acceptée : alerte %s émise.', $res['ref']) : 'Proposition refusée : son auteur est prévenu.');
    }

    // ------------------------------------------------------------ Panneau réutilisable

    /**
     * Variables du partiel views/atak/sse/alerts/_reports_panel.php pour une alerte.
     *
     * @param array<string, mixed>|null $notice avis (SseWantedNoticeRepository::hydrate) quand $doc vaut « avis »
     * @return array{alertLink: ?array<string, mixed>, alertReports: list<array<string, mixed>>, alertCanManage: bool, alertUrl: string, alertRef: string, alertCanRead: bool}
     */
    public function panelVars(string $doc, int $targetId, string $alertRef, ?array $notice = null): array
    {
        $w = $this->rights()->wanted();
        $tenantId = $w->tenantId();
        $empty = ['alertLink' => null, 'alertReports' => [], 'alertCanManage' => false, 'alertUrl' => '', 'alertRef' => $alertRef, 'alertCanRead' => $w->canRead()];
        if (!$w->canRead() || !SseAlertRepository::ready() || $tenantId < 1) {
            return $empty;
        }
        $link = $this->safe(function () use ($doc, $targetId, $tenantId, $notice): ?array {
            if ($doc === SseAlertRepository::DOC_NOTICE && $notice !== null) {
                return $this->alerts->noticeLink($tenantId, $notice);
            }

            return $this->alerts->alerts()->linkFor($tenantId, $doc, $targetId);
        });
        if ($link === null) {
            return $empty;
        }
        $usable = $link['revoked_at'] === null;

        return [
            'alertLink' => $link, 'alertReports' => $this->safe(fn (): array => $this->alerts->alerts()->reportsFor($tenantId, (int) $link['id'], 100)) ?? [],
            'alertCanManage' => $w->canManage(), 'alertUrl' => $usable ? SseAlertReportTo::url((string) $link['token']) : '', 'alertRef' => $alertRef, 'alertCanRead' => true,
        ];
    }

    // ------------------------------------------------------------------ Outils

    /**
     * QR code (SVG autonome, zone de silence de 4 modules) d'une adresse. Chaîne vide si l'adresse est vide ou si le code est impossible.
     */
    public static function qrSvg(string $text): string
    {
        if ($text === '') {
            return '';
        }
        // phpqrcode (celle des autres QR du site) : la classe QRcode de TCPDF a le même nom et ne se charge pas dans le même processus.
        $rows = (new \App\Services\Qr\QrPngGenerator())->matrix($text);
        if ($rows === null || $rows === []) {
            return '';
        }
        $code = ['bcode' => array_map(static fn (string $r): array => array_map('intval', str_split($r)), $rows), 'num_rows' => count($rows), 'num_cols' => strlen($rows[0])];
        $n = (int) $code['num_cols'];
        $quiet = 4;
        $path = '';
        foreach ($code['bcode'] as $y => $row) {
            foreach ($row as $x => $on) {
                if ((int) $on === 1) {
                    $path .= 'M' . ($x + $quiet) . ' ' . ($y + $quiet) . 'h1v1h-1z';
                }
            }
        }
        $size = $n + 2 * $quiet;

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $size . ' ' . $size . '" width="' . ($size * 8) . '" height="' . ($size * 8) . '" shape-rendering="crispEdges" role="img" aria-label="QR code">'
            . '<rect width="' . $size . '" height="' . $size . '" fill="#fff"/><path d="' . $path . '" fill="#000"/></svg>';
    }

    /** Langue du lecteur : français, sinon anglais. */
    public static function readerLang(): string
    {
        return SseWantedNoticeController::readerLang();
    }

    /** Jeton CSRF présent et valable (un jeton vide n'est jamais accepté). */
    private static function csrfOk(Request $request): bool
    {
        $token = $request->input('_csrf_token');

        return is_string($token) && $token !== '' && Csrf::validate($token);
    }

    private function rights(): SseSheetAccess
    {
        return $this->rights ??= SseSheetAccess::fromSession($this->access, $this->clearance);
    }

    private function game(): SseAlertGameService
    {
        return $this->game ??= new SseAlertGameService(null, null, $this->alerts);
    }

    private function proposals(): SseAlertProposals
    {
        return $this->proposals ??= new SseAlertProposals($this->alerts);
    }

    /** Pourquoi cette session ne peut pas signaler : [statut, mode, message], ou null. @return array{0: int, 1: string, 2: string}|null */
    private function gate(): ?array
    {
        $r = $this->rights();
        if (!SseAlertRepository::ready()) {
            return [503, 'unavailable', self::UNAVAILABLE];
        }
        if (!$r->wanted()->canRead() || $r->tenantId() < 1) {
            return [403, 'denied', self::NEED_READ];
        }
        if (!$r->canDraft()) {
            return [403, 'denied', self::NEED_ACCOUNT];
        }

        return null;
    }

    /**
     * L'alerte d'un jeton pour CETTE communauté et CETTE habilitation, ou null (jeton inconnu, autre communauté, révoqué, alerte
     * au-dessus de l'habilitation, ancienne de plus de 30 jours après sa levée : même réponse).
     *
     * @return array{alert: array<string, mixed>, link: array<string, mixed>}|null
     */
    private function resolve(string $token): ?array
    {
        if (!SseAlertRepository::validToken($token)) {
            return null;
        }
        $r = $this->rights();
        $alert = $this->safe(fn (): ?array => $this->game()->findByToken($r->tenantId(), $r->level(), $r->userId(), $token, self::readerLang()));
        $link = is_array($alert['_link'] ?? null) ? $alert['_link'] : null;
        if ($alert === null || $link === null || (int) $link['tenant_id'] !== $r->tenantId() || $link['revoked_at'] !== null) {
            return null;
        }

        return ['alert' => $alert, 'link' => $link];
    }

    /** L'alerte (lisible par ce compte) d'un lien, ou null. @param array<string, mixed> $link @return array<string, mixed>|null */
    private function alertOfLink(array $link): ?array
    {
        $r = $this->rights();

        return $this->safe(fn (): ?array => $this->game()->findByTarget($r->tenantId(), $r->level(), $r->userId(), (string) $link['doc'], (int) $link['target_id'], self::readerLang()));
    }

    /** Un signalement de CETTE communauté dont l'alerte est lisible à cette habilitation, sinon null (IDOR). @return array<string, mixed>|null */
    private function readableReport(int $id): ?array
    {
        $r = $this->rights();
        $tenantId = $r->tenantId();
        if ($id < 1 || $tenantId < 1 || !SseAlertRepository::ready()) {
            return null;
        }
        $report = $this->safe(fn (): ?array => $this->alerts->alerts()->findReport($tenantId, $id));
        $link = $report !== null ? $this->safe(fn (): ?array => $this->alerts->alerts()->linkById($tenantId, (int) $report['link_id'])) : null;

        return $report !== null && $link !== null && $this->alertOfLink($link) !== null ? $report : null;
    }

    /**
     * Pour chaque lien des signalements : référence, genre, lien vers l'alerte, et lisibilité à l'habilitation du lecteur
     * (jamais le nom du sujet : la liste n'en a pas besoin).
     *
     * @param list<array<string, mixed>> $reports
     * @return array<int, array{ref: string, doc: string, href: string, readable: bool}>
     */
    private function alertsOf(int $tenantId, array $reports): array
    {
        $level = SseRedactionService::levelRank($this->rights()->level());
        $out = [];
        foreach ($reports as $row) {
            $linkId = (int) $row['link_id'];
            if (isset($out[$linkId])) {
                continue;
            }
            $entry = ['ref' => '', 'doc' => (string) $row['link_doc'], 'href' => '', 'readable' => false];
            try {
                if ($row['link_doc'] === SseAlertRepository::DOC_NOTICE) {
                    $n = (new SseWantedNoticeRepository())->find($tenantId, (int) $row['link_target']);
                    if ($n !== null) {
                        $entry = ['ref' => (string) $n['ref'], 'doc' => 'avis', 'href' => url('atak/sse/papiers/identites/' . (int) $n['identity_id']) . '#avis', 'readable' => true];
                    }
                } else {
                    $i = (new SseSheetRepository())->findIssue($tenantId, (int) $row['link_target']);
                    if ($i !== null && SseRedactionService::levelRank((string) $i['classification']) <= $level) {
                        $href = $i['person_id'] !== null ? url('atak/sse/fichiers/sujet/' . (int) $i['person_id']) : url('atak/sse/papiers/identites/' . (int) $i['identity_id']);
                        $entry = ['ref' => (string) $i['ref'], 'doc' => 'vigilance', 'href' => $href, 'readable' => true];
                    }
                }
            } catch (\Throwable) {
                $entry['readable'] = false;
            }
            $out[$linkId] = $entry;
        }

        return $out;
    }

    /**
     * Propositions en attente, prêtes à afficher (sujet par sa référence seulement, jamais un nom).
     *
     * @param list<array<string, mixed>> $list
     * @return list<array<string, mixed>>
     */
    private function proposalRows(int $tenantId, array $list): array
    {
        $config = $this->safe(fn (): array => (new SseWantedSettingsRepository())->get($tenantId)) ?? [];
        $out = [];
        foreach ($list as $p) {
            $pl = $p['payload'];
            $subject = strtoupper(trim((string) ($pl['subject'] ?? '')));
            $href = '';
            if (preg_match('/^IDT-0*(\d{1,9})$/', $subject, $m) === 1) {
                $href = url('atak/sse/papiers/identites/' . (int) $m[1]);
            } elseif (preg_match('/^IDN-0*(\d{1,9})$/', $subject, $m) === 1) {
                $href = url('atak/sse/fichiers/sujet/' . (int) $m[1]);
            }
            $level = SseAlertLevel::of((string) ($pl['threat'] ?? ''), (string) ($pl['armed'] ?? ''));
            $codes = [];
            foreach (array_slice((array) ($pl['measures'] ?? []), 0, 6) as $code) {
                $found = SseWantedCodes::find((string) $code, $config);
                $codes[] = ['code' => (string) $code, 'fr' => (string) ($found['fr'] ?? ''), 'en' => (string) ($found['en'] ?? '')];
            }
            $out[] = [
                'id' => (int) $p['id'], 'ref' => (string) $p['ref'], 'doc' => (string) $p['doc'], 'subject' => $subject, 'subject_href' => $href,
                'unknown' => (string) ($pl['unknown'] ?? ''), 'type' => (string) ($pl['type'] ?? '') !== '' ? C::typeLabel((string) $pl['type'], $config, self::readerLang()) : '',
                'level' => $level['key'], 'level_rank' => $level['rank'], 'level_title' => $level['title'], 'level_hex' => SseAlertLevel::hex($level['key']),
                'codes' => $codes, 'zone' => (string) ($pl['zone'] ?? ''), 'place' => (string) ($pl['place'] ?? ''), 'grid' => (string) ($pl['grid'] ?? ''),
                'text' => (string) ($pl['text'] ?? ''), 'days' => (int) ($pl['days'] ?? 0), 'author' => (string) $p['created_by_label'], 'source' => (string) $p['source'],
                'created_at' => (string) $p['created_at'],
            ];
        }

        return $out;
    }

    // ------------------------------------------------------------------ Pages

    /**
     * @param array<string, mixed> $alert alerte de SseAlertGameService
     * @param array<string, mixed> $link
     * @param array<string, mixed> $extra errors, old, report, replay
     */
    private function reportPage(array $alert, array $link, string $mode, array $extra = [], int $status = 200): Response
    {
        $tz = $this->timezone($this->rights()->tenantId());
        $lang = self::readerLang();
        $response = $this->page('atak.sse.alerts.report', [
            'title' => 'Signalement', 'mode' => $mode, 'view' => $this->heroOf($alert, $lang), 'link' => $link,
            'errors' => $extra['errors'] ?? [], 'old' => $extra['old'] ?? $this->emptyInput($tz), 'report' => $extra['report'] ?? null, 'replay' => (bool) ($extra['replay'] ?? false),
            'action' => url(SseAlertReportTo::path((string) $link['token'])), 'qrUrl' => url(SseAlertReportTo::path((string) $link['token']) . '/qr.svg'),
            'lang' => $lang, 'tz' => $tz, 'nowLocal' => $this->nowLocal($tz), 'csrfToken' => Csrf::token(), 'cid' => bin2hex(random_bytes(12)), 'activeNav' => 'avis-signalements',
            'form' => $this->formOf($alert), 'maxBody' => SseAlertService::BODY_MAX, 'minBody' => SseAlertService::BODY_MIN, 'maxPhotoMb' => (int) (SseAlertReportPhotos::MAX_BYTES / 1048576),
        ]);

        return $status === 200 ? $response : $response->setStatusCode($status);
    }

    /**
     * Contrat du formulaire de cette alerte : champs montrés et exigés selon ses conduites à tenir et son niveau.
     *
     * @param array<string, mixed> $alert
     * @return array{fields: list<string>, required: list<string>, urgent: bool, notes: list<array{0: string, 1: string}>, codes: list<string>}
     */
    private function formOf(array $alert): array
    {
        return SseAlertReportForm::resolve(array_map('strval', (array) ($alert['_measures'] ?? [])), (int) ($alert['rank'] ?? 1), (string) ($alert['armed'] ?? ''));
    }

    /** @param array{0: int, 1: string, 2: string} $denial */
    private function denialPage(array $denial): Response
    {
        return $this->page('atak.sse.alerts.report', ['title' => 'Signalement', 'mode' => $denial[1], 'message' => $denial[2], 'lang' => self::readerLang(), 'csrfToken' => Csrf::token(), 'activeNav' => 'avis-signalements'])->setStatusCode($denial[0]);
    }

    /** « Introuvable » : la même page pour un jeton inconnu, d'une autre communauté, révoqué ou au-dessus de l'habilitation. */
    private function notFound(): Response
    {
        return $this->page('atak.sse.alerts.report', ['title' => 'Signalement introuvable', 'mode' => 'notfound', 'lang' => self::readerLang(), 'csrfToken' => Csrf::token(), 'activeNav' => 'avis-signalements'])->setStatusCode(404);
    }

    private function plainNotFound(): Response
    {
        return (new Response())->setStatusCode(404)->header('Content-Type', 'text/plain; charset=utf-8')->header('Cache-Control', 'no-store')->setBody('Not found');
    }

    /** @param array<string, mixed> $data */
    private function page(string $view, array $data): Response
    {
        if ($this->render !== null) {
            return ($this->render)($view, $data);
        }

        return (new SseIdentityRegistryController($this->access, $this->clearance))->page($view, $data);
    }

    /**
     * Ce que la page montre de l'alerte : niveau (libellé, forme, rang), notice de couleur, consigne dominante, conduite à tenir,
     * service à informer, validité, sujet selon l'habilitation (déjà caviardé par SseAlertGameService).
     *
     * @param array<string, mixed> $a
     * @return array<string, mixed>
     */
    private function heroOf(array $a, string $lang): array
    {
        $i = $lang === 'en' ? 1 : 0;
        $level = SseAlertLevel::LEVELS[$a['level']] ?? SseAlertLevel::LEVELS['watch'];
        $rgb = static fn (array $c): string => sprintf('#%02X%02X%02X', $c[0], $c[1], $c[2]);
        $notice = null;
        if ($a['doc'] === SseAlertRepository::DOC_NOTICE && isset(SseAlertLevel::NOTICE_COLORS[$a['color']])) {
            $nc = SseAlertLevel::NOTICE_COLORS[$a['color']];
            $sub = SseAlertLevel::noticeColor((string) $a['type'], ['banner' => 'red'])['subtitle'][$i] ?? '';
            $notice = ['key' => $a['color'], 'title' => $nc['title'][$i], 'subtitle' => $sub, 'hex' => $rgb($nc['rgb']), 'ink' => $rgb($nc['ink'])];
        }
        $detail = is_array($a['_detail'] ?? null) ? $a['_detail'] : [];
        $conduct = array_slice(array_values(array_filter(array_map('strval', (array) $a['conduct']), static fn (string $l): bool => trim($l) !== '')), 0, 6);
        $liftedAt = (string) ($detail['lifted_at'] ?? '');

        return [
            'doc' => (string) $a['doc'], 'ref' => (string) $a['ref'], 'state' => (string) $a['state'],
            'accent' => $notice['hex'] ?? SseAlertLevel::hex((string) $a['level']), 'ink' => $notice['ink'] ?? $rgb($level['ink']),
            'notice' => $notice,
            'level' => ['key' => (string) $a['level'], 'rank' => (int) $a['rank'], 'title' => $level['title'][$i], 'hex' => SseAlertLevel::hex((string) $a['level']), 'ink' => $rgb($level['ink']), 'shape' => (string) $level['shape'], 'hint' => $level['hint'][$i]],
            'order' => $a['order'] !== '' ? (SseAlertLevel::ORDERS[$a['order']][$i] ?? '') : '',
            'name' => (string) $a['name'], 'alias' => (string) $a['alias'], 'conduct' => $conduct,
            'service' => (string) $a['service'], 'channel' => (string) $a['channel'], 'delay' => SseAlertReportTo::delayLabel((string) $a['delay'], $lang),
            'issued' => (string) $a['issued'], 'until' => (string) $a['until'], 'soon' => (bool) $a['soon'],
            'lifted_at' => $liftedAt, 'lift_reason' => (string) ($detail['lift_reason'] ?? ''), 'zone' => (string) $a['zone'], 'place' => (string) $a['place'],
        ];
    }

    // ------------------------------------------------------------------ Saisie

    /** @return array{what: string, when: string, place: string, grid: string, text: string, dir: string, vehicle: string, sure: string, cid: string} */
    private function oldInput(Request $request): array
    {
        $line = static fn (string $k, int $max): string => C::line($request->inputString($k), $max);
        $sure = $request->inputString('sure');
        $cid = $request->inputString('cid');

        return [
            'what' => $request->inputString('what') === 'info' ? 'info' : 'seen', 'when' => $line('when', 32), 'place' => $line('place', 160), 'grid' => $line('grid', 24),
            'text' => C::text($request->inputString('text'), SseAlertService::BODY_MAX + 100), 'dir' => $line('dir', 120), 'vehicle' => $line('vehicle', 160),
            'sure' => isset(SseAlertLevel::CERTAINTY[$sure]) ? $sure : 'medium', 'cid' => SseAlertRepository::validClientId($cid) ? $cid : '',
            // Champs d'observation adaptables : le service ne garde que ceux que la fiche affiche.
            'extras' => array_filter(array_map(fn (string $k): string => $request->inputString($k), array_combine(SseAlertReportForm::FOLDED, SseAlertReportForm::FOLDED))),
        ];
    }

    /** @return array{what: string, when: string, place: string, grid: string, text: string, dir: string, vehicle: string, sure: string, cid: string} */
    private function emptyInput(string $tz): array
    {
        return ['what' => 'seen', 'when' => $this->nowLocal($tz), 'place' => '', 'grid' => '', 'text' => '', 'dir' => '', 'vehicle' => '', 'sure' => 'medium', 'cid' => ''];
    }

    /** Octets de la photo envoyée, ou null (aucune photo). */
    private function uploadedPhoto(): ?string
    {
        $f = $_FILES['photo'] ?? null;
        if (!is_array($f) || (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $tmp = is_string($f['tmp_name'] ?? null) ? $f['tmp_name'] : '';
        if ((int) $f['error'] !== UPLOAD_ERR_OK) {
            // Trop lourde pour le serveur : le contrôle de taille se fait avec un octet de plus que la limite.
            return in_array((int) $f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? str_repeat('x', SseAlertReportPhotos::MAX_BYTES + 1) : '';
        }
        if ($tmp === '' || (!$this->trustUploads && !is_uploaded_file($tmp)) || !is_file($tmp)) {
            return '';
        }
        $size = (int) filesize($tmp);
        if ($size > SseAlertReportPhotos::MAX_BYTES) {
            return str_repeat('x', SseAlertReportPhotos::MAX_BYTES + 1);
        }

        return (string) file_get_contents($tmp);
    }

    private function dropPhoto(?string $path): void
    {
        $file = $path !== null ? $this->photos->resolve($path) : null;
        if ($file !== null) {
            @unlink($file['file']);
        }
    }

    private function timezone(int $tenantId): string
    {
        $tz = (string) ($this->safe(fn (): array => (new SseWantedSettingsRepository())->get($tenantId))['process']['timezone'] ?? '');
        try {
            new \DateTimeZone($tz !== '' ? $tz : 'UTC');
        } catch (\Throwable) {
            return 'UTC';
        }

        return $tz !== '' ? $tz : 'UTC';
    }

    private function nowLocal(string $tz): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone($tz)))->format('Y-m-d\TH:i');
    }

    /** Retour demandé après une action : une adresse du portail SSE seulement, jamais un site extérieur. */
    private function backUrl(Request $request): string
    {
        $back = trim($request->inputString('back'));
        if ($back !== '' && preg_match('#^atak/sse/[A-Za-z0-9/_\-?=&\#.]*$#', $back) === 1 && !str_contains($back, '..')) {
            return url($back);
        }

        return url(self::BASE);
    }

    private function back(string $to, string $type, string $message): Response
    {
        Session::flash($type, $message);

        return Response::redirect($to);
    }

    /**
     * @template T
     * @param \Closure(): T $fn
     * @return T|null
     */
    private function safe(\Closure $fn): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            error_log('Signalements SSE : ' . $e->getMessage());

            return null;
        }
    }
}
