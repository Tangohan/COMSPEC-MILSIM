<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\MemberAvailabilityCheckRepository;
use App\Repositories\PlayerSessionRepository;
use App\Repositories\UserRepository;
use App\Services\Effectifs\Availability\AvailabilityCheck;
use App\Services\Effectifs\Availability\AvailabilityCheckRecorder;
use App\Services\Effectifs\Availability\AvailabilityFactsCollector;
use App\Services\Effectifs\Dossier\LiveDossierPorts;
use App\Services\Effectifs\Dossier\MemberDossierStepActions;
use App\Services\Effectifs\Dossier\MemberDossierTimeline;
use App\Services\Personnel\RoleplayGameSessionSettings;
use App\Support\EffectifsLmsAccess;
use Closure;

/**
 * Vérification de disponibilité guidée (Effectifs) : depuis la fiche d'un membre ou une sélection du tableur.
 *
 *  - `run` (GET) : exécute les étapes de recherche sur les données existantes et rend étapes et conclusion proposée ;
 *  - `save` (POST) : le responsable confirme ou corrige, note et suite ; les étapes sont ré-exécutées côté serveur ;
 *  - `saveMany` (POST) : validation groupée (25 membres au plus), ligne par ligne ou en bloc.
 *
 * Aucun droit nouveau : il faut l'accès au pilotage des effectifs ET le droit de gérer le suivi des dossiers
 * (effectifs.suivi.manage, ou administration de l'organisation / des accès), comme pour les autres gestes du suivi.
 * Réponses JSON ; chaque écriture vérifie le jeton CSRF et l'appartenance du membre à la communauté.
 */
final class MemberAvailabilityCheckController
{
    public const BULK_MAX = 25;

    /**
     * @param (Closure(int, int): bool)|null $memberInTenant
     * @param (Closure(int, list<int>): array<int, string>)|null $names
     */
    public function __construct(
        private ?AvailabilityFactsCollector $collector = null,
        private ?MemberAvailabilityCheckRepository $checks = null,
        private ?AvailabilityCheckRecorder $recorder = null,
        private ?Closure $memberInTenant = null,
        private ?Closure $names = null,
        private ?int $now = null,
    ) {
    }

    /** Lancer et enregistrer une vérification : mêmes droits que la gestion du suivi des dossiers. */
    public static function canCheck(Gate $gate): bool
    {
        return EffectifsLmsAccess::allows($gate) && EffectifsLmsAccess::canManageSuivi($gate);
    }

    /** Lire l'historique des vérifications : mêmes droits que la lecture du suivi des dossiers. */
    public static function canView(Gate $gate): bool
    {
        return EffectifsLmsAccess::allows($gate) && EffectifsLmsAccess::canViewSuivi($gate);
    }

    public function run(Request $request, array $params = []): Response
    {
        $ctx = $this->context(false, $request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        $memberId = (int) ($params['id'] ?? 0);
        if (!$this->inTenant($tenantId, $memberId)) {
            return Response::json(['ok' => false, 'code' => 'member', 'message' => 'Membre introuvable.'], 404);
        }
        $run = $this->execute($tenantId, $memberId);
        $checks = $this->checks();

        return Response::json([
            'ok' => true,
            'member' => ['id' => $memberId, 'name' => $this->name($tenantId, $memberId)],
            'steps' => $run['steps'],
            'verdict' => $run['verdict'],
            'counts' => AvailabilityCheck::counts($run['steps']),
            'can_save' => $checks->schemaReady(),
            'notice' => $checks->schemaReady() ? '' : AvailabilityCheckRecorder::degradedMessage(),
        ]);
    }

    public function save(Request $request, array $params = []): Response
    {
        $ctx = $this->context(true, $request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $actorId] = $ctx;
        $memberId = (int) ($params['id'] ?? 0);
        if (!$this->inTenant($tenantId, $memberId)) {
            return Response::json(['ok' => false, 'code' => 'member', 'message' => 'Membre introuvable.'], 404);
        }
        $run = $this->execute($tenantId, $memberId);
        $r = $this->recorder()->record(
            $tenantId,
            $actorId,
            $memberId,
            $run,
            (string) $request->input('conclusion', ''),
            (string) $request->input('note', ''),
            (string) $request->input('follow_up', AvailabilityCheckRecorder::FOLLOW_NONE),
            (int) $request->input('recheck_days', 0)
        );
        $out = ['ok' => $r['ok'], 'code' => $r['code'], 'message' => $r['message'], 'verdict' => $run['verdict']];
        if (isset($r['follow_up_message'])) {
            $out['notice'] = $r['follow_up_message'];
            $out['follow_up_ok'] = (bool) ($r['follow_up_ok'] ?? false);
        }
        if ($r['ok'] && (string) $request->input('follow_up', '') === AvailabilityCheckRecorder::FOLLOW_POSITION) {
            $out['position_url'] = effectifs_workspace_url('membres/' . $memberId) . '?onglet=statut#statut-position';
        }

        return Response::json($out, $r['status']);
    }

    public function saveMany(Request $request, array $params = []): Response
    {
        $ctx = $this->context(true, $request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $actorId] = $ctx;
        $raw = $request->input('items', []);
        $items = is_array($raw) ? array_values(array_filter($raw, 'is_array')) : [];
        if ($items === []) {
            return Response::json(['ok' => false, 'code' => 'empty', 'message' => 'Sélectionnez au moins un membre.'], 422);
        }
        if (count($items) > self::BULK_MAX) {
            return Response::json(['ok' => false, 'code' => 'too_many', 'message' => str_replace('%1', (string) self::BULK_MAX, 'Vérifiez %1 membres au plus à la fois.')], 422);
        }
        if (!$this->checks()->schemaReady()) {
            return Response::json(['ok' => false, 'code' => 'schema', 'message' => AvailabilityCheckRecorder::degradedMessage()], 503);
        }
        $followUp = (string) $request->input('follow_up', AvailabilityCheckRecorder::FOLLOW_NONE);
        $days = (int) $request->input('recheck_days', 0);
        $batch = bin2hex(random_bytes(8));
        $rows = [];
        $saved = 0;
        $seen = [];
        foreach ($items as $item) {
            $memberId = (int) ($item['id'] ?? 0);
            if (isset($seen[$memberId])) {
                continue;
            }
            $seen[$memberId] = true;
            if (!$this->inTenant($tenantId, $memberId)) {
                $rows[] = ['id' => $memberId, 'ok' => false, 'message' => 'Membre introuvable.'];
                continue;
            }
            $run = $this->execute($tenantId, $memberId);
            // Validation en bloc : la conclusion proposée de chaque ligne, sauf correction faite sur la ligne.
            $conclusion = trim((string) ($item['conclusion'] ?? ''));
            $r = $this->recorder()->record($tenantId, $actorId, $memberId, $run, $conclusion !== '' ? $conclusion : $run['verdict']['key'], (string) ($item['note'] ?? ''), $followUp, $days, $batch);
            $saved += $r['ok'] ? 1 : 0;
            $row = ['id' => $memberId, 'ok' => $r['ok'], 'message' => $r['message']];
            if (isset($r['follow_up_message'])) {
                $row['notice'] = $r['follow_up_message'];
            }
            $rows[] = $row;
        }
        $total = count($rows);

        return Response::json([
            'ok' => $saved > 0,
            'code' => $saved === $total ? 'saved' : ($saved > 0 ? 'partial' : 'none'),
            'message' => str_replace(['%1', '%2'], [(string) $saved, (string) $total], '%1 vérification(s) enregistrée(s) sur %2.'),
            'saved' => $saved,
            'rows' => $rows,
        ], $saved > 0 ? 200 : 422);
    }

    /**
     * Exécute les étapes pour un membre (toujours côté serveur, même à l'enregistrement).
     *
     * @return array{steps: list<array<string, mixed>>, verdict: array{key: string, label: string, detail: string}, rules: array<string, int>}
     */
    private function execute(int $tenantId, int $memberId): array
    {
        $pdo = Database::getPdo();
        $rules = RoleplayGameSessionSettings::playerSessionRules($tenantId);
        $tz = (new PlayerSessionRepository($pdo))->tenantTimezone($tenantId);
        $collector = $this->collector ?? AvailabilityFactsCollector::live($pdo, EffectifsLmsAccess::canViewSanctions(Gate::getInstance()));
        $facts = $collector->collect($tenantId, $memberId, $rules, $this->now ?? time(), $tz);

        return ['steps' => AvailabilityCheck::steps($facts), 'verdict' => AvailabilityCheck::verdict($facts), 'rules' => $facts['rules']];
    }

    /** @return array{0: int, 1: int}|Response communauté et responsable, ou la réponse de refus */
    private function context(bool $write, Request $request): array|Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        if ($tenantId < 1 || $actorId < 1) {
            return Response::json(['ok' => false, 'code' => 'session', 'message' => 'Session expirée. Reconnectez-vous.'], 401);
        }
        if (!self::canCheck(Gate::getInstance())) {
            return Response::json(['ok' => false, 'code' => 'forbidden', 'message' => 'Vous n’êtes pas habilité à vérifier la disponibilité des membres.'], 403);
        }
        if ($write && !Csrf::verify($request)) {
            return Response::json(['ok' => false, 'code' => 'csrf', 'message' => 'Session expirée. Rechargez la page.'], 403);
        }

        return [$tenantId, $actorId];
    }

    private function inTenant(int $tenantId, int $memberId): bool
    {
        if ($memberId < 1) {
            return false;
        }
        if ($this->memberInTenant !== null) {
            return ($this->memberInTenant)($tenantId, $memberId);
        }

        return Container::get(UserRepository::class)->findById($memberId, $tenantId) !== null;
    }

    private function name(int $tenantId, int $memberId): string
    {
        try {
            $labels = $this->names !== null
                ? ($this->names)($tenantId, [$memberId])
                : (new \App\Services\Personnel\MemberNameService())->labelsFor($tenantId, [$memberId], 'list');
        } catch (\Throwable) {
            $labels = [];
        }

        return (string) ($labels[$memberId] ?? ('Membre n° ' . $memberId));
    }

    private function checks(): MemberAvailabilityCheckRepository
    {
        return $this->checks ??= new MemberAvailabilityCheckRepository();
    }

    private function recorder(): AvailabilityCheckRecorder
    {
        if ($this->recorder === null) {
            $timeline = MemberDossierTimeline::make();
            $ports = new LiveDossierPorts();
            $this->recorder = new AvailabilityCheckRecorder($this->checks(), $timeline->store(), new MemberDossierStepActions($timeline, $timeline->store(), $ports), $ports);
        }

        return $this->recorder;
    }
}
