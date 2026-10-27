<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AarReportRepository;
use App\Repositories\AarReportTemplateRepository;
use App\Repositories\AtakDebriefRepository;
use App\Services\Atak\AtakDebriefService;
use App\Services\Rbac\RolePermissionMatrixCatalog;
use App\Support\AtakDebriefSpec;
use App\Support\AtakMissionKey;
use App\Support\ModuleFeatureAccess;
use App\Support\SteamId;
use App\Support\UtcDateTime;

/**
 * Back-office — suivi des CR de fin d'opération (/back-office/atak/suivi-cr).
 *
 * Une ligne par joueur de l'opération : état de son ATAK (dernière synchro), état du CR imposé (envoyé, affiché, ouvert,
 * remis, lu), réponses, remontées envoyées pendant la session. Les gestionnaires imposent le CR (à remplir ou à lire) aux
 * joueurs d'une session ou à ceux dont l'ATAK est en ligne, le relancent, le clôturent, et règlent ce que le jeu peut faire
 * (Zeus, UID autorisés, questionnaire par défaut).
 */
final class AdminAtakDebriefController
{
    private const BASE = 'back-office/atak/suivi-cr';

    /** Filtres de la liste (onglets). */
    public const FILTERS = [
        'tous' => 'Tous',
        'attente' => 'En attente',
        'remis' => 'Remis',
        'non_recu' => 'Pas reçu',
        'hors_ligne' => 'ATAK hors ligne',
    ];

    /** Un ATAK vu il y a moins de 5 minutes est « en ligne ». */
    public const ONLINE_SECONDS = 300;

    public function __construct(
        private ?AtakDebriefService $service = null,
    ) {
        $this->service ??= new AtakDebriefService();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('view', self::BASE);
        if ($forbidden instanceof Response) {
            return $forbidden;
        }
        $repo = $this->service->repository();
        $ready = $repo->ready();
        $debriefs = $ready ? $repo->listForTenant($tenantId) : [];
        $operations = $ready ? $repo->recentOperations($tenantId) : [];

        $selected = null;
        $crId = (int) $request->query('cr', 0);
        $op = AtakMissionKey::normalize((string) $request->query('op', ''));
        if ($crId > 0) {
            $selected = $repo->find($tenantId, $crId);
        } elseif ($op === '' && $operations !== []) {
            // Rien de choisi : ouvrir la dernière session (évite le « 0 joueur » quand un CR
            // plus récent prenait la main alors que le sélecteur montrait déjà cette session).
            $op = (string) $operations[0]['mission_key'];
        } elseif ($op === '' && $debriefs !== []) {
            $selected = $repo->find($tenantId, (int) $debriefs[0]['id']);
        }
        if ($selected !== null) {
            $op = (string) ($selected['mission_key'] ?? '');
        }
        $rows = $this->trackingRows($tenantId, $selected, $op);
        $filter = (string) $request->query('filtre', 'tous');
        if (!isset(self::FILTERS[$filter])) {
            $filter = 'tous';
        }
        $counts = array_fill_keys(array_keys(self::FILTERS), 0);
        foreach ($rows as $r) {
            foreach (array_keys(self::FILTERS) as $f) {
                if (self::matches($r, $f)) {
                    $counts[$f]++;
                }
            }
        }
        $templates = [];
        $aars = [];
        try {
            $templates = (new AarReportTemplateRepository())->listForTenant($tenantId, true);
            $aars = array_slice((new AarReportRepository())->listForTenant($tenantId, []), 0, 40);
        } catch (\Throwable) {
        }

        return Response::view('layout.main', [
            'content' => 'admin.atak_debrief.index',
            'title' => 'Suivi des CR de fin d’opération',
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Opérations',
            'boPageTitle' => 'Suivi des CR de fin d’opération',
            'boPageKicker' => 'OPÉRATIONS · RETOURS',
            'boPageSubtitle' => 'Qui a reçu le CR imposé sur son ATAK, qui l’a remis, et quand son téléphone s’est synchronisé pour la dernière fois.',
            'backOfficePageCss' => ['back-office-aar.css', 'back-office-debrief.css'],
            'dbfReady' => $ready,
            'dbfDebriefs' => $debriefs,
            'dbfOperations' => $operations,
            'dbfSelected' => $selected,
            'dbfOp' => $op,
            'dbfOpLabel' => $op !== '' ? $repo->opLabel($tenantId, $op) : '',
            'dbfRows' => array_values(array_filter($rows, static fn (array $r): bool => self::matches($r, $filter))),
            'dbfAllRows' => $rows,
            'dbfCounts' => $counts,
            'dbfFilter' => $filter,
            'dbfSettings' => $ready ? $repo->settings($tenantId) : null,
            'dbfTemplates' => $templates,
            'dbfAars' => $aars,
            'dbfCanManage' => ModuleFeatureAccess::allows(RolePermissionMatrixCatalog::MODULE_ATAK, 'manage'),
            'dbfOpen' => (string) $request->query('ouvrir', ''),
            'csrfToken' => Csrf::token(),
        ]);
    }

    /** Imposer le CR : aux joueurs d'une session (clé de mission) ou aux ATAK en ligne depuis N minutes. */
    public function force(Request $request, array $params = []): Response
    {
        $ctx = $this->manageContext($request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        $repo = $this->service->repository();
        $op = AtakMissionKey::normalize((string) $request->input('mission_key', ''));
        $target = (string) $request->input('cible', $op !== '' ? 'operation' : 'en_ligne');
        $players = match ($target) {
            'operation' => $repo->participants($tenantId, $op),
            'liste' => $this->playersFromText($tenantId, (string) $request->input('joueurs', '')),
            default => $repo->recentlySynced($tenantId, max(5, min(24 * 60, (int) $request->input('minutes', 30)))),
        };
        if ($players === []) {
            Session::flash('error', $target === 'operation'
                ? 'Aucun joueur connu pour cette session : elle n’a encore rien remonté.'
                : 'Aucun joueur ciblé : aucun ATAK synchronisé dans ce délai, ou aucun UID Steam reconnu.');

            return Response::redirect(url(self::BASE . ($op !== '' ? '?op=' . rawurlencode($op) : '')));
        }
        $mode = (string) $request->input('mode', 'fill') === 'read' ? 'read' : 'fill';
        $label = trim((string) $request->input('titre', ''));
        if ($label === '') {
            $label = $op !== '' ? AtakMissionKey::label($op) : 'Fin d’opération du ' . date('d/m/Y');
        }
        $readText = trim((string) $request->input('texte', ''));
        $aarId = (int) $request->input('aar_report_id', 0);
        if ($mode === 'read' && $readText === '' && $aarId < 1) {
            Session::flash('error', 'Pour un CR à lire, écrivez le texte ou choisissez un compte rendu post-op.');

            return Response::redirect(url(self::BASE . ($op !== '' ? '?op=' . rawurlencode($op) : '')));
        }
        [$actorId, $actorLabel] = self::actor();
        $res = $this->service->sendFromSite($tenantId, [
            'mission_key' => $op,
            'label' => mb_substr($label, 0, 200),
            'mode' => $mode,
            'template_id' => (int) $request->input('template_id', $repo->settings($tenantId)['template_id']),
            'aar_report_id' => $aarId,
            'intro' => mb_substr(trim((string) $request->input('intro', '')), 0, 600),
            'read_text' => mb_substr($readText, 0, 6000),
            'hours' => (int) $request->input('validite_heures', 48),
        ], $players, $actorId, $actorLabel);
        if ($res['id'] < 1) {
            Session::flash('error', 'Le CR n’a pas pu être enregistré : lancez run-migrations.php puis réessayez.');

            return Response::redirect(url(self::BASE));
        }
        Session::flash('success', $res['sent'] . ' joueur(s) recevront le CR dans leur téléphone ATAK à la prochaine synchro (30 s environ si leur ATAK est en ligne).');

        return Response::redirect(url(self::BASE . '?cr=' . $res['id']));
    }

    public function remind(Request $request, array $params = []): Response
    {
        $ctx = $this->manageContext($request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        $d = $this->service->repository()->find($tenantId, (int) ($params['id'] ?? 0));
        if ($d === null || !empty($d['closed_at'])) {
            Session::flash('error', 'CR introuvable ou clôturé.');

            return Response::redirect(url(self::BASE));
        }
        [$actorId] = self::actor();
        $n = $this->service->remind($tenantId, $d, $actorId);
        Session::flash('success', $n > 0 ? $n . ' rappel(s) envoyé(s) aux joueurs qui n’ont pas répondu.' : 'Tout le monde a déjà répondu.');

        return Response::redirect(url(self::BASE . '?cr=' . (int) $d['id']));
    }

    public function close(Request $request, array $params = []): Response
    {
        $ctx = $this->manageContext($request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        $id = (int) ($params['id'] ?? 0);
        $ok = $this->service->repository()->close($tenantId, $id);
        Session::flash($ok ? 'success' : 'error', $ok ? 'CR clôturé : il ne s’affichera plus sur les téléphones qui ne l’ont pas encore reçu.' : 'CR introuvable.');

        return Response::redirect(url(self::BASE . '?cr=' . $id));
    }

    /** Renommer après coup : une demande de CR (champ cr) ou une session jouée (champ op ; vide = nom de la mission). */
    public function rename(Request $request, array $params = []): Response
    {
        $ctx = $this->manageContext($request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        $repo = $this->service->repository();
        $label = trim((string) $request->input('nom', ''));
        $crId = (int) $request->input('cr', 0);
        if ($crId > 0) {
            $ok = $repo->renameDebrief($tenantId, $crId, $label);
            Session::flash($ok ? 'success' : 'error', $ok ? 'CR renommé.' : 'Donnez un nom au CR.');

            return Response::redirect(url(self::BASE . '?cr=' . $crId));
        }
        $op = AtakMissionKey::normalize((string) $request->input('op', ''));
        [$actorId] = self::actor();
        $ok = $repo->renameOp($tenantId, $op, $label, $actorId);
        Session::flash($ok ? 'success' : 'error', $ok ? ($label !== '' ? 'Opération renommée.' : 'L’opération reprend le nom de la mission.') : 'Opération introuvable.');

        return Response::redirect(url(self::BASE . ($op !== '' ? '?op=' . rawurlencode($op) : '')));
    }

    /** Supprimer une demande de CR et son suivi (les réponses déjà remontées restent dans les remontées). */
    public function delete(Request $request, array $params = []): Response
    {
        $ctx = $this->manageContext($request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        $ok = $this->service->repository()->deleteDebrief($tenantId, (int) ($params['id'] ?? 0));
        Session::flash($ok ? 'success' : 'error', $ok ? 'CR supprimé de l’historique. Les réponses déjà remontées restent dans les remontées.' : 'CR introuvable.');

        return Response::redirect(url(self::BASE));
    }

    /** Retirer une session de la liste (ses données de jeu ne sont pas effacées). */
    public function hideSession(Request $request, array $params = []): Response
    {
        $ctx = $this->manageContext($request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        [$actorId] = self::actor();
        $n = $this->service->repository()->hideOps($tenantId, [(string) $request->input('op', '')], $actorId);
        Session::flash($n > 0 ? 'success' : 'error', $n > 0 ? 'Session retirée de la liste.' : 'Session introuvable.');

        return Response::redirect(url(self::BASE));
    }

    /** Effacer tout l'historique du suivi, après avoir tapé EFFACER. */
    public function clearHistory(Request $request, array $params = []): Response
    {
        $ctx = $this->manageContext($request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        if (mb_strtoupper(trim((string) $request->input('confirmation', ''))) !== 'EFFACER') {
            Session::flash('error', 'Tapez EFFACER pour confirmer : rien n’a été effacé.');

            return Response::redirect(url(self::BASE . '?ouvrir=historique'));
        }
        [$actorId] = self::actor();
        $res = $this->service->repository()->clearHistory($tenantId, $actorId);
        Session::flash('success', 'Historique effacé : ' . $res['debriefs'] . ' CR supprimé(s), ' . $res['sessions'] . ' session(s) retirée(s) de la liste.');

        return Response::redirect(url(self::BASE));
    }

    public function settings(Request $request, array $params = []): Response
    {
        $ctx = $this->manageContext($request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        [$actorId] = self::actor();
        $uids = AtakDebriefSpec::parseUids((string) $request->input('uids', ''));
        $this->service->repository()->saveSettings($tenantId, [
            'default_mode' => (string) $request->input('mode_defaut', 'fill') === 'read' ? 'read' : 'fill',
            'template_id' => max(0, (int) $request->input('template_id', 0)),
            'intro' => (string) $request->input('intro', ''),
            'zeus_allowed' => $request->input('zeus') === '1',
            'authorized_uids' => $uids,
        ], $actorId);
        Session::flash('success', 'Réglages enregistrés. Les téléphones les reçoivent à leur prochaine synchro.');

        return Response::redirect(url(self::BASE . '?ouvrir=reglages'));
    }

    public function exportCsv(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $forbidden = ModuleFeatureAccess::guardAtak('view', self::BASE);
        if ($tenantId < 1 || $forbidden instanceof Response) {
            return $forbidden ?? Response::redirect(url('dashboard'));
        }
        $d = $this->service->repository()->find($tenantId, (int) ($params['id'] ?? 0));
        if ($d === null) {
            return Response::redirect(url(self::BASE));
        }
        $rows = $this->trackingRows($tenantId, $d, (string) ($d['mission_key'] ?? ''));
        $questions = $d['questions'] !== [] ? $d['questions'] : AtakDebriefSpec::BUILTIN;
        $fh = fopen('php://temp', 'r+');
        $head = ['Joueur', 'Rôle', 'État du CR', 'Remis le', 'Dernière synchro ATAK', 'Remontées'];
        foreach ($questions as $q) {
            $head[] = (string) $q['label'];
        }
        fputcsv($fh, $head, ';', '"', '');
        foreach ($rows as $r) {
            $line = [$r['name'], $r['role'], AtakDebriefRepository::STATES[$r['state']] ?? 'Pas demandé', $r['done_at'], $r['sync_at'], (string) $r['reports']];
            foreach ($questions as $q) {
                $line[] = (string) ($r['answers'][(string) $q['id']] ?? '');
            }
            fputcsv($fh, $line, ';', '"', '');
        }
        rewind($fh);
        $csv = "\xEF\xBB\xBF" . stream_get_contents($fh);
        fclose($fh);
        $response = new Response();
        $response->setStatusCode(200)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="cr-fin-operation-' . (int) $d['id'] . '.csv"')
            ->header('Cache-Control', 'no-store')
            ->setBody($csv);

        return $response;
    }

    /**
     * Lignes du suivi : joueurs de la demande, plus ceux de la session qui n'ont rien reçu (état « none »).
     *
     * @param array<string, mixed>|null $debrief
     * @return list<array<string, mixed>>
     */
    private function trackingRows(int $tenantId, ?array $debrief, string $missionKey): array
    {
        $repo = $this->service->repository();
        $rows = [];
        $mode = (string) ($debrief['mode'] ?? 'fill');
        foreach ($debrief !== null ? $repo->recipients($tenantId, (int) $debrief['id']) : [] as $r) {
            $who = AtakDebriefRepository::whoKey(((int) ($r['user_id'] ?? 0)) ?: null, ((string) ($r['steam_uid'] ?? '')) ?: null);
            $state = AtakDebriefRepository::state($r, $mode);
            $rows[$who] = [
                'who' => $who,
                'user_id' => (int) ($r['user_id'] ?? 0),
                'steam' => (string) ($r['steam_uid'] ?? $r['user_steam'] ?? ''),
                'name' => trim((string) ($r['user_callsign'] ?? '')) ?: (trim((string) ($r['callsign'] ?? '')) ?: (trim((string) ($r['display_name'] ?? '')) ?: (string) ($r['steam_uid'] ?? '—'))),
                'role' => '',
                'state' => $state,
                'sent_at' => (string) ($r['sent_at'] ?? ''),
                'seen_at' => (string) ($r['opened_at'] ?? $r['delivered_at'] ?? $r['msg_delivered_at'] ?? ''),
                'done_at' => (string) ($r['submitted_at'] ?? $r['read_at'] ?? ''),
                'answers' => is_array($r['answers'] ?? null) ? $r['answers'] : [],
                'report_id' => (int) ($r['report_id'] ?? 0),
            ];
        }
        $participants = $missionKey !== '' ? $repo->participants($tenantId, $missionKey) : [];
        foreach ($participants as $p) {
            $whoU = AtakDebriefRepository::whoKey($p['user_id'] ?? null, null);
            $whoS = AtakDebriefRepository::whoKey(null, $p['steam_uid'] ?? null);
            $key = isset($rows[$whoU]) ? $whoU : (isset($rows[$whoS]) ? $whoS : null);
            if ($key !== null) {
                $rows[$key]['role'] = (string) $p['role'];
                continue;
            }
            $who = $whoU !== '' ? $whoU : $whoS;
            if ($who === '') {
                continue;
            }
            $rows[$who] = [
                'who' => $who, 'user_id' => (int) ($p['user_id'] ?? 0), 'steam' => (string) ($p['steam_uid'] ?? ''),
                'name' => (string) ($p['callsign'] ?: ($p['steam_uid'] ?? '—')), 'role' => (string) $p['role'],
                'state' => 'none', 'sent_at' => '', 'seen_at' => '', 'done_at' => '', 'answers' => [], 'report_id' => 0,
            ];
        }
        $sync = $repo->lastSync($tenantId, array_column($rows, 'user_id'), array_column($rows, 'steam'));
        $reports = $repo->reportCounts($tenantId, $missionKey);
        $now = time();
        foreach ($rows as &$row) {
            $s = $sync['users'][$row['user_id']] ?? ($sync['steams'][$row['steam']] ?? null);
            $row['sync_at'] = (string) ($s['at'] ?? '');
            $row['mod_version'] = (string) ($s['version'] ?? '');
            $t = UtcDateTime::timestamp($row['sync_at'] !== '' ? $row['sync_at'] : null);
            $row['online'] = $t !== null && $now - $t <= self::ONLINE_SECONDS;
            $row['sync_age'] = $t !== null ? max(0, $now - $t) : null;
            $rc = $reports[$row['who']] ?? ($row['steam'] !== '' ? ($reports['s:' . $row['steam']] ?? null) : null);
            $row['reports'] = (int) ($rc['n'] ?? 0);
            $row['reports_last'] = (string) ($rc['last'] ?? '');
        }
        unset($row);
        $order = ['opened' => 0, 'delivered' => 1, 'sent' => 2, 'expired' => 3, 'none' => 4, 'submitted' => 5, 'read' => 5, 'cancelled' => 6];
        uasort($rows, static fn (array $a, array $b): int => [$order[$a['state']] ?? 9, mb_strtolower($a['name'])] <=> [$order[$b['state']] ?? 9, mb_strtolower($b['name'])]);

        return array_values($rows);
    }

    /** @param array<string, mixed> $r */
    public static function matches(array $r, string $filter): bool
    {
        return match ($filter) {
            'attente' => in_array($r['state'], ['sent', 'delivered', 'opened'], true),
            'remis' => in_array($r['state'], ['submitted', 'read'], true),
            'non_recu' => in_array($r['state'], ['sent', 'expired', 'none'], true),
            'hors_ligne' => empty($r['online']),
            default => true,
        };
    }

    /** Joueurs tirés d'une liste d'UID Steam collée. @return list<array{user_id: ?int, steam_uid: ?string, callsign: string, role: string}> */
    private function playersFromText(int $tenantId, string $raw): array
    {
        $users = new \App\Repositories\UserRepository();
        $out = [];
        foreach (array_slice(AtakDebriefSpec::parseUids($raw), 0, 200) as $uid) {
            $u = null;
            try {
                $u = $users->findBySteamIdForTenant($tenantId, $uid);
            } catch (\Throwable) {
            }
            $out[] = ['user_id' => $u !== null ? (int) $u['id'] : null, 'steam_uid' => SteamId::normalize($uid), 'callsign' => (string) ($u['callsign'] ?? $u['display_name'] ?? ''), 'role' => ''];
        }

        return $out;
    }

    /** @return array{0: int}|Response */
    private function manageContext(Request $request): array|Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1 || (int) Session::get('user_id') < 1) {
            return Response::redirect(url('dashboard'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('manage', self::BASE);
        if ($forbidden instanceof Response) {
            return $forbidden;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url(self::BASE));
        }

        return [$tenantId];
    }

    /** @return array{0: ?int, 1: string} */
    private static function actor(): array
    {
        $name = trim((string) Session::get('callsign', '')) ?: trim((string) Session::get('display_name', ''));

        return [((int) Session::get('user_id')) ?: null, $name !== '' ? $name : 'Gestion de la communauté'];
    }
}
