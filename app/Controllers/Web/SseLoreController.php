<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseLoreRepository;
use App\Services\Sse\SseFileRecordService;
use App\Services\Sse\SseLoreService;
use App\Services\Sse\SseRecordsGenerator;
use App\Support\SseFileSubjectCatalog as S;
use App\Support\SseMeasure;
use App\Support\SseRecordsCountryPacks;

/**
 * Portail SSE — Lore des sessions (MJ) : préparer ce que les fichiers, le SEEK II, l'ATAK et les joueurs verront pendant
 * une mission. Personnes remontées du jeu ou créées ici, connu des fichiers / caché (MJ) donnée par donnée, phase de
 * révélation, liens entre personnes, import / export JSON et CSV, historique, verrou pendant la mission.
 *
 * Accès : atak.sse.lore.manage ou admin.access, jamais en session invitée. Chaque session appartient à la communauté de
 * la session web ; une personne n'est modifiable ici que si elle est membre de la session demandée.
 */
final class SseLoreController
{
    private const BASE = SseFilesContext::BASE . '/lore';

    private SseFilesContext $ctx;

    public function __construct(?SseFilesContext $ctx = null, private ?SseLoreService $lore = null, private ?SseFileRecordRepository $records = null)
    {
        $this->ctx = $ctx ?? new SseFilesContext();
    }

    public function index(Request $request, array $params = []): Response
    {
        if (!$this->ctx->canLore()) {
            return $this->ctx->denied($request);
        }

        return $this->ctx->render('atak.sse.files.lore_index', [
            'title' => 'Lore des sessions',
            'sessions' => $this->service()->lore()->list($this->ctx->tenantId()),
            'countries' => SseRecordsCountryPacks::codes(),
            'defaultCountry' => (string) $this->ctx->settings()['default_country'],
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        if (!$this->ctx->canLore() || !$this->ctx->csrfOk($request)) {
            return $this->ctx->denied($request, self::BASE);
        }
        $tenantId = $this->ctx->tenantId();
        $op = trim($request->inputString('op_key'));
        if (!SseLoreRepository::validOp($op) || trim($request->inputString('title')) === '') {
            Session::flash('error', 'Indiquez un titre et un code d’opération (lettres, chiffres, « - », « _ », « . » ; ex. OP-2611-07).');

            return Response::redirect(url(self::BASE));
        }
        if ($this->service()->lore()->findByOp($tenantId, $op) !== null) {
            Session::flash('error', 'Une session existe déjà pour ce code d’opération.');

            return Response::redirect(url(self::BASE));
        }
        $id = $this->service()->lore()->create($tenantId, [
            'op_key' => $op, 'title' => $request->inputString('title'), 'summary' => $request->inputString('summary'),
            'country' => SseRecordsCountryPacks::exists(strtoupper($request->inputString('country'))) ? strtoupper($request->inputString('country')) : null,
        ], $this->ctx->userId());
        $this->audit($tenantId, $id, 'lore.session.create', 'Session créée : ' . $op);
        Session::flash('success', 'Session créée. Ajoutez des personnes ici, ou synchronisez les IA depuis Eden ou Zeus.');

        return Response::redirect(url(self::BASE . '/' . $id));
    }

    public function show(Request $request, array $params = []): Response
    {
        $session = $this->session($params);
        if ($session === null) {
            return $this->ctx->denied($request, self::BASE);
        }
        $tenantId = $this->ctx->tenantId();
        $members = $this->service()->lore()->members($tenantId, (int) $session['id']);
        $ids = array_map(static fn (array $m): int => $m['identity_id'], $members);
        $identities = (new SseIdentityRepository())->findMany($tenantId, $ids);
        $profiles = $this->records()->profiles($tenantId, $ids);
        $q = \App\Support\SseFileText::norm($request->queryString('q'));
        $rows = [];
        foreach ($members as $m) {
            $i = $identities[$m['identity_id']] ?? null;
            if ($i === null || ($q !== '' && !str_contains(\App\Support\SseFileText::norm($i['display_name'] . ' ' . ($m['ai_uid'] ?? '')), $q))) {
                continue;
            }
            $p = $profiles[$m['identity_id']] ?? null;
            $hidden = 0;
            foreach (($p['visibility'] ?? []) as $cfg) {
                $hidden += ($cfg['v'] ?? 'known') === 'hidden' || (int) ($cfg['phase'] ?? 0) > (int) $session['phase'] ? 1 : 0;
            }
            $rows[] = ['member' => $m, 'identity' => $i, 'profile' => $p, 'hidden' => $hidden];
        }

        return $this->ctx->render('atak.sse.files.lore_show', [
            'title' => $session['title'] . ' — Lore',
            'session' => $session,
            'rows' => $rows,
            'q' => $request->queryString('q'),
            'history' => $this->service()->history($tenantId, (int) $session['id'], 40),
            'countries' => SseRecordsCountryPacks::codes(),
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $session = $this->session($params);
        if ($session === null || !$this->ctx->csrfOk($request)) {
            return $this->ctx->denied($request, self::BASE);
        }
        $tenantId = $this->ctx->tenantId();
        $sid = (int) $session['id'];
        $action = $request->inputString('action');
        if ($action === 'lock' || $action === 'unlock') {
            $this->service()->lore()->setLocked($tenantId, $sid, $action === 'lock', $this->ctx->userId());
            $this->audit($tenantId, $sid, 'lore.session.' . $action, $action === 'lock' ? 'Session verrouillée' : 'Session déverrouillée');
            Session::flash('success', $action === 'lock' ? 'Session verrouillée : ni le jeu ni le web ne peuvent plus modifier les personnes.' : 'Session déverrouillée.');
        } else {
            if ($session['locked'] && $action !== 'phase') {
                Session::flash('error', 'Session verrouillée : seule la phase peut changer.');

                return Response::redirect(url(self::BASE . '/' . $sid));
            }
            $data = $action === 'phase' ? ['phase' => (int) $request->input('phase', 1)] : [
                'title' => $request->inputString('title'), 'summary' => $request->inputString('summary'),
                'country' => strtoupper($request->inputString('country')), 'phase' => (int) $request->input('phase', $session['phase']),
                'auto_refresh_s' => (int) $request->input('auto_refresh_s', 0),
            ];
            $this->service()->lore()->update($tenantId, $sid, $data);
            $this->audit($tenantId, $sid, 'lore.session.update', $action === 'phase' ? 'Phase ' . $session['phase'] . ' → ' . (int) $data['phase'] : 'Réglages de la session modifiés');
            Session::flash('success', $action === 'phase' ? 'Phase changée : le jeu la prendra à la prochaine récupération.' : 'Session enregistrée.');
        }

        return Response::redirect(url(self::BASE . '/' . $sid));
    }

    /** Créer une personne à la main (avant la mission, sans passer par le jeu), éventuellement générée pour le pays. */
    public function personCreate(Request $request, array $params = []): Response
    {
        $session = $this->session($params);
        if ($session === null || !$this->ctx->csrfOk($request) || $session['locked']) {
            Session::flash('error', $session !== null && $session['locked'] ? 'Session verrouillée.' : 'Action non autorisée.');

            return Response::redirect(url(self::BASE . ($session !== null ? '/' . (int) $session['id'] : '')));
        }
        $tenantId = $this->ctx->tenantId();
        $country = SseRecordsCountryPacks::normalize($request->inputString('country') ?: ($session['country'] ?? $this->ctx->settings()['default_country']));
        $count = max(1, min(20, (int) $request->input('count', 1)));
        $generate = $request->inputString('generate') === '1';
        $made = 0;
        $last = 0;
        for ($n = 0; $n < ($generate ? $count : 1); $n++) {
            $p = $generate ? self::fromGenerator(SseRecordsGenerator::person($country, random_int(1, PHP_INT_MAX >> 1))) : [
                'last' => $request->inputString('last'), 'first' => $request->inputString('first'), 'sex' => $request->inputString('sex'),
                'dob' => $request->inputString('dob'), 'country' => $country, 'nationality' => $country,
            ];
            $p['uid'] = 'web-' . bin2hex(random_bytes(6));
            $res = $this->service()->upsertPerson($tenantId, (int) $session['id'], $p, null, true, $this->ctx->userId(), 'web');
            if ($res !== null) {
                $made++;
                $last = $res['identity_id'];
            }
        }
        $this->service()->lore()->touch($tenantId, (int) $session['id']);
        if ($made === 0) {
            Session::flash('error', 'Indiquez au moins un nom ou un prénom.');

            return Response::redirect(url(self::BASE . '/' . (int) $session['id']));
        }
        Session::flash('success', $made > 1 ? $made . ' personnes créées.' : 'Personne créée : complétez sa fiche.');

        return Response::redirect(url(self::BASE . '/' . (int) $session['id'] . ($made === 1 ? '/personnes/' . $last : '')));
    }

    /**
     * @param array<string, mixed> $g
     * @return array<string, mixed>
     */
    public static function fromGenerator(array $g): array
    {
        return [
            'last' => $g['last_name'] ?? null, 'first' => $g['first_name'] ?? null, 'sex' => $g['sex'] ?? null, 'dob' => $g['birth_date'] ?? null,
            'birth_place' => $g['birth_place'] ?? null, 'nationality' => $g['nationality'] ?? null, 'country' => $g['country'] ?? null,
            'height_cm' => $g['height_cm'] ?? null, 'weight_kg' => $g['weight_kg'] ?? null, 'build' => $g['build'] ?? null,
            'hair' => $g['hair'] ?? null, 'eyes' => $g['eyes'] ?? null,
            'addresses' => isset($g['address']['line']) ? [(string) $g['address']['line']] : [],
            'phones' => isset($g['phone']) ? [(string) $g['phone']] : [],
            'vehicles' => isset($g['vehicle']['plate']) ? [$g['vehicle']] : [],
        ];
    }

    public function person(Request $request, array $params = []): Response
    {
        $session = $this->session($params);
        $tenantId = $this->ctx->tenantId();
        $iid = (int) ($params['identityId'] ?? 0);
        if ($session === null || !$this->service()->lore()->isMember($tenantId, (int) $session['id'], $iid)) {
            return $this->ctx->denied($request, self::BASE);
        }
        $record = (new SseFileRecordService($this->records()))->record($tenantId, $iid, [
            'gm' => true, 'can_see' => $this->ctx->canSee(), 'terminal_country' => (string) $this->ctx->settings()['default_country'],
        ]);
        if ($record === null) {
            return $this->ctx->denied($request, self::BASE . '/' . (int) $session['id']);
        }
        $others = [];
        foreach ($this->service()->lore()->members($tenantId, (int) $session['id']) as $m) {
            if ($m['identity_id'] !== $iid) {
                $others[] = $m['identity_id'];
            }
        }

        return $this->ctx->render('atak.sse.files.lore_person', [
            'title' => $record['identity']['display_name'] . ' — Lore',
            'session' => $session,
            'record' => $record,
            'others' => (new SseIdentityRepository())->findMany($tenantId, array_slice($others, 0, 500)),
            'countries' => SseRecordsCountryPacks::codes(),
        ]);
    }

    public function personUpdate(Request $request, array $params = []): Response
    {
        $session = $this->session($params);
        $tenantId = $this->ctx->tenantId();
        $iid = (int) ($params['identityId'] ?? 0);
        if ($session === null || !$this->ctx->csrfOk($request) || !$this->service()->lore()->isMember($tenantId, (int) $session['id'], $iid)) {
            return $this->ctx->denied($request, self::BASE);
        }
        $back = url(self::BASE . '/' . (int) $session['id'] . '/personnes/' . $iid);
        if ($session['locked']) {
            Session::flash('error', 'Session verrouillée : déverrouillez-la pour modifier.');

            return Response::redirect($back);
        }
        $uid = $this->ctx->userId();
        $records = $this->records();
        $section = $request->inputString('section');
        $summary = 'Fiche modifiée';
        switch ($section) {
            case 'identity':
                $before = (new SseIdentityRepository())->find($iid, $tenantId) ?? [];
                $patch = [
                    'last_name' => $request->inputString('last_name'), 'first_name' => $request->inputString('first_name'),
                    'sex' => $request->inputString('sex'), 'birth_date' => $request->inputString('birth_date'),
                    'birth_place' => $request->inputString('birth_place'), 'nationality' => strtoupper($request->inputString('nationality')),
                    'profession' => $request->inputString('profession'),
                ];
                (new SseIdentityRepository())->update($iid, $tenantId, array_merge($before, $patch));
                $changed = [];
                foreach ($patch as $k => $v) {
                    if ((string) ($before[$k] ?? '') !== $v) {
                        $changed[] = $k;
                    }
                }
                $summary = 'Identité : ' . ($changed !== [] ? implode(', ', $changed) : 'inchangée');
                $height = trim($request->inputString('height'));
                $weight = trim($request->inputString('weight'));
                $records->saveProfile($tenantId, $iid, [
                    'country' => strtoupper($request->inputString('country')),
                    'height_cm' => $height !== '' ? SseMeasure::parseHeight($height) : null,
                    'weight_kg' => $weight !== '' ? SseMeasure::parseWeight($weight) : null,
                    'build' => $request->inputString('build'), 'hair' => $request->inputString('hair'), 'eyes' => $request->inputString('eyes'),
                    'marks' => $request->inputString('marks'),
                    'caution_codes' => is_array($request->input('cautions')) ? $request->input('cautions') : [],
                    'affiliation' => $request->inputString('affiliation'), 'cover_note' => $request->inputString('cover_note'),
                    'in_files' => $request->inputString('in_files') === '1' ? 1 : 0,
                ], $uid);
                break;
            case 'secrets':
                $records->saveProfile($tenantId, $iid, [
                    'knowledge' => $request->inputString('knowledge'),
                    'ai_answers' => ['answers' => $request->inputString('answers'), 'evoke' => $request->inputString('evoke'), 'banned' => $request->inputString('banned')],
                ], $uid);
                $summary = 'Ce que la personne sait et réponses de l’IA';
                break;
            case 'visibility':
                $vis = [];
                foreach (array_keys(S::VIS_FIELDS) as $f) {
                    $vis[$f] = ['v' => $request->inputString('vis_' . $f) === 'hidden' ? 'hidden' : 'known', 'phase' => max(0, min(20, (int) $request->input('phase_' . $f, 0)))];
                }
                $records->saveProfile($tenantId, $iid, ['visibility' => $vis], $uid);
                foreach (is_array($request->input('row_vis')) ? $request->input('row_vis') : [] as $key => $v) {
                    [$kind, $rid] = array_pad(explode(':', (string) $key, 2), 2, '0');
                    $phase = (int) (($request->input('row_phase')[$key] ?? 0));
                    if ($kind === 'alias') {
                        $records->setAliasVisibility($tenantId, (int) $rid, (string) $v, $phase, $iid);
                    } elseif ($kind === 'link') {
                        $records->setLinkVisibility($tenantId, (int) $rid, (string) $v, $phase, $iid);
                    }
                }
                $summary = 'Connu / caché et phases de révélation';
                break;
            case 'alias':
                $records->addAlias($tenantId, $iid, $request->inputString('alias'), $request->inputString('kind', 'aka'), $request->inputString('visibility') === 'hidden' ? 'hidden' : 'known', (int) $request->input('reveal_phase', 0), $uid);
                $summary = 'Alias ajouté : ' . mb_substr($request->inputString('alias'), 0, 60);
                break;
            case 'link':
                $linked = (int) $request->input('linked_identity_id', 0);
                if ($linked > 0 && !$this->service()->lore()->isMember($tenantId, (int) $session['id'], $linked)) {
                    $linked = 0;
                }
                $records->addLink($tenantId, $iid, [
                    'kind' => $request->inputString('kind'), 'value' => $request->inputString('value') !== '' ? $request->inputString('value') : ($linked > 0 ? 'IDT-' . $linked : ''),
                    'detail' => ['make' => $request->inputString('make'), 'model' => $request->inputString('model'), 'color' => $request->inputString('color')],
                    'linked_identity_id' => $linked > 0 ? $linked : null, 'relation' => $request->inputString('relation'), 'flag' => $request->inputString('flag'),
                    'visibility' => $request->inputString('visibility') === 'hidden' ? 'hidden' : 'known', 'reveal_phase' => (int) $request->input('reveal_phase', 0),
                ], $uid);
                $summary = 'Lien ajouté';
                break;
            case 'remove_alias':
                $records->deleteAlias($tenantId, (int) $request->input('row_id', 0), $iid);
                $summary = 'Alias retiré';
                break;
            case 'remove_link':
                $records->deleteLink($tenantId, (int) $request->input('row_id', 0), $iid);
                $summary = 'Lien retiré';
                break;
            default:
                return Response::redirect($back);
        }
        $this->service()->lore()->touch($tenantId, (int) $session['id']);
        $this->service()->audit($tenantId, $uid, 'Lore de session', 'lore.person.update', $iid, $summary);
        Session::flash('success', 'Enregistré. Le jeu le prendra à la prochaine récupération.');

        return Response::redirect($back . '#' . $section);
    }

    public function personRemove(Request $request, array $params = []): Response
    {
        $session = $this->session($params);
        $tenantId = $this->ctx->tenantId();
        $iid = (int) ($params['identityId'] ?? 0);
        if ($session === null || !$this->ctx->csrfOk($request) || $session['locked']) {
            return $this->ctx->denied($request, self::BASE);
        }
        $this->service()->lore()->removeMember($tenantId, (int) $session['id'], $iid);
        $this->records()->saveProfile($tenantId, $iid, ['lore_session_id' => null], $this->ctx->userId());
        $this->service()->lore()->touch($tenantId, (int) $session['id']);
        $this->audit($tenantId, (int) $session['id'], 'lore.person.remove', 'IDT-' . $iid . ' retirée de la session (sa fiche reste aux fichiers)');
        Session::flash('success', 'Personne retirée de la session. Sa fiche reste dans les fichiers.');

        return Response::redirect(url(self::BASE . '/' . (int) $session['id']));
    }

    public function import(Request $request, array $params = []): Response
    {
        $session = $this->session($params);
        if ($session === null || !$this->ctx->csrfOk($request)) {
            return $this->ctx->denied($request, self::BASE);
        }
        $file = $_FILES['file'] ?? null;
        $tmp = is_array($file) ? (string) ($file['tmp_name'] ?? '') : '';
        if ($tmp === '' || !is_uploaded_file($tmp) || (int) ($file['size'] ?? 0) > 2_000_000) {
            Session::flash('error', 'Choisissez un fichier JSON ou CSV de 2 Mo au plus.');

            return Response::redirect(url(self::BASE . '/' . (int) $session['id']));
        }
        $name = strtolower((string) ($file['name'] ?? ''));
        $res = $this->service()->import($this->ctx->tenantId(), (int) $session['id'], (string) file_get_contents($tmp), str_ends_with($name, '.csv') ? 'csv' : 'json', $this->ctx->userId());
        if (isset($res['error'])) {
            Session::flash('error', $res['error']);
        } else {
            $this->audit($this->ctx->tenantId(), (int) $session['id'], 'lore.session.import', 'Import : ' . $res['created'] . ' créées, ' . $res['updated'] . ' mises à jour, ' . $res['skipped'] . ' ignorées');
            Session::flash('success', 'Import terminé : ' . $res['created'] . ' personne(s) créée(s), ' . $res['updated'] . ' mise(s) à jour, ' . $res['skipped'] . ' ignorée(s).');
        }

        return Response::redirect(url(self::BASE . '/' . (int) $session['id']));
    }

    public function export(Request $request, array $params = []): Response
    {
        $session = $this->session($params);
        if ($session === null) {
            return $this->ctx->denied($request, self::BASE);
        }
        $name = 'lore-' . preg_replace('/[^A-Za-z0-9_.-]/', '-', (string) $session['op_key']);
        if ($request->queryString('format') === 'csv') {
            return (new Response())->setStatusCode(200)->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', 'attachment; filename="' . $name . '.csv"')
                ->setBody($this->service()->exportCsv($this->ctx->tenantId(), (int) $session['id']));
        }

        return (new Response())->setStatusCode(200)->header('Content-Type', 'application/json; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . $name . '.json"')
            ->setBody((string) json_encode($this->service()->export($this->ctx->tenantId(), (int) $session['id']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    // ------------------------------------------------------------ Outils

    /** @return array<string, mixed>|null session de la communauté, si le compte est MJ */
    private function session(array $params): ?array
    {
        if (!$this->ctx->canLore()) {
            return null;
        }

        return $this->service()->lore()->find($this->ctx->tenantId(), (int) ($params['id'] ?? 0));
    }

    private function audit(int $tenantId, int $sessionId, string $action, string $reason): void
    {
        try {
            (new \App\Repositories\SseAuditLogRepository())->write([
                'tenant_id' => $tenantId, 'actor_user_id' => $this->ctx->userId(), 'actor_label' => $this->ctx->operatorLabel() ?? 'Lore de session',
                'action' => $action, 'object_type' => 'lore_session', 'object_id' => $sessionId, 'reason' => $reason,
            ]);
        } catch (\Throwable $e) {
            error_log('Lore de session : audit indisponible : ' . $e->getMessage());
        }
    }

    private function service(): SseLoreService
    {
        return $this->lore ??= new SseLoreService(null, $this->records());
    }

    private function records(): SseFileRecordRepository
    {
        return $this->records ??= new SseFileRecordRepository();
    }
}
