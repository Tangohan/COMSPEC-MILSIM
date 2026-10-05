<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseCaseRepository;
use App\Services\Intel\IntelIntakeService;
use App\Services\Intel\IntelRedaction;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseRedactionService;

/**
 * Back-office « Remontées » : tous les comptes rendus et toutes les fiches de renseignement dans un fil de suivi,
 * à la manière des pull requests (liste ouverte / close, fil de discussion, attribution, état d'exploitation).
 *   GET  /back-office/remontees                        liste (onglets, filtres)
 *   GET  /back-office/remontees/{source}/{id}          fil d'une remontée
 *   POST /back-office/remontees/{source}/{id}/{action} commentaire, état, attribution, type, données,
 *                                                      caviardage, flou, suppression, restauration
 * Accès : administrateurs de la communauté et responsables SSE (gestion des dossiers ou délivrance des accès).
 */
final class AdminIntelIntakeController
{
    private const ACTIONS = ['comment', 'state', 'assign', 'type', 'edit', 'redact', 'unredact', 'blur', 'delete', 'restore'];

    public function __construct(
        private ?IntelIntakeService $intake = null,
        private ?SseClearanceService $clearance = null,
    ) {
        $this->intake ??= new IntelIntakeService();
    }

    public static function allowed(): bool
    {
        if (!function_exists('can')) {
            return false;
        }
        foreach (['admin.system', 'admin.organization', 'admin.access', 'atak.sse.case.manage', 'atak.sse.grant'] as $perm) {
            if (can($perm)) {
                return true;
            }
        }

        return false;
    }

    public function index(Request $request, array $params = []): Response
    {
        $guard = $this->guard();
        if ($guard instanceof Response) {
            return $guard;
        }
        $tenantId = $guard;
        $ready = $this->intake->schemaReady();
        $tab = (string) ($request->query('etat') ?? 'open');
        $tab = in_array($tab, ['open', 'closed', 'mine', 'deleted', 'all'], true) || isset(IntelIntakeService::STATES[$tab]) ? $tab : 'open';
        $source = (string) ($request->query('source') ?? '');
        $source = isset(IntelIntakeService::SOURCES[$source]) ? $source : '';
        $filters = [
            'source' => $source,
            'type' => strtoupper(trim((string) ($request->query('type') ?? ''))),
            'q' => mb_substr(trim((string) ($request->query('q') ?? '')), 0, 80),
            'assignee' => (string) ($request->query('attribue') ?? ''),
        ];
        $me = (int) Session::get('user_id');
        $listFilters = $filters + [
            'state' => $tab === 'mine' ? 'open' : ($tab === 'deleted' ? 'all' : $tab),
            'deleted' => $tab === 'deleted',
        ];
        if ($tab === 'mine') {
            $listFilters['assignee'] = 'me';
        }
        $items = $ready ? $this->intake->listItems($tenantId, $listFilters, $me) : [];
        // Les extraits de la liste respectent aussi les caviardages.
        if ($items !== []) {
            $level = $this->viewerLevel();
            foreach (['cr', 'fiche'] as $src) {
                $ids = array_column(array_filter($items, static fn (array $i): bool => $i['source'] === $src), 'id');
                $red = $this->intake->redactions($tenantId, $src, $ids);
                foreach ($items as &$it) {
                    if ($it['source'] === $src && isset($red[$it['id']])) {
                        $it['title'] = IntelRedaction::apply((string) $it['title'], $red[$it['id']], $level, $me);
                        $it['redacted'] = true;
                    }
                }
                unset($it);
            }
        }

        return Response::view('layout.main', [
            'content' => 'admin.intel.intake_index',
            'title' => 'Remontées',
            'pageTitle' => 'Remontées',
            'intakeReady' => $ready,
            'intakeItems' => $items,
            'intakeCounts' => $ready ? $this->intake->counts($tenantId, $filters, $me) : ['open' => 0, 'closed' => 0, 'mine' => 0, 'deleted' => 0],
            'intakeTab' => $tab,
            'intakeFilters' => $filters,
            'intakeTypes' => $source !== '' ? IntelIntakeService::typeOptions($source) : IntelIntakeService::typeOptions('cr') + IntelIntakeService::typeOptions('fiche'),
            'intakeMembers' => $ready ? $this->intake->members($tenantId) : [],
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $guard = $this->guard();
        if ($guard instanceof Response) {
            return $guard;
        }
        $tenantId = $guard;
        if (!$this->intake->schemaReady()) {
            return Response::redirect(url('back-office/remontees'));
        }
        $source = (string) ($params['source'] ?? '');
        $item = $this->intake->find($tenantId, $source, (int) ($params['id'] ?? 0));
        if ($item === null) {
            Session::flash('error', 'Remontée introuvable.');

            return Response::redirect(url('back-office/remontees'));
        }
        $me = (int) Session::get('user_id');
        $this->intake->recordRead($tenantId, $source, (int) $item['id'], $me, 'bo');

        return Response::view('layout.main', [
            'content' => 'admin.intel.intake_show',
            'title' => $item['ref'] . ' · Remontées',
            'pageTitle' => 'Remontées',
            'intake' => $item,
            'intakeViewerLevel' => $this->viewerLevel(),
            'intakeViewerId' => $me,
            'intakeTypes' => IntelIntakeService::typeOptions($source),
            'intakeMembers' => $this->intake->members($tenantId),
            'intakeLevels' => SseCaseRepository::CLASSIFICATION_LABELS,
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function action(Request $request, array $params = []): Response
    {
        $guard = $this->guard();
        if ($guard instanceof Response) {
            return $guard;
        }
        $tenantId = $guard;
        $source = (string) ($params['source'] ?? '');
        $id = (int) ($params['id'] ?? 0);
        $action = (string) ($params['action'] ?? '');
        $back = url('back-office/remontees/' . rawurlencode($source) . '/' . $id);
        if (!in_array($action, self::ACTIONS, true) || !$this->intake->schemaReady() || $this->intake->find($tenantId, $source, $id) === null) {
            Session::flash('error', 'Action impossible.');

            return Response::redirect(url('back-office/remontees'));
        }
        $actor = $this->actor();
        $in = static fn (string $k, string $d = ''): string => trim((string) $request->input($k, $d));
        $ok = match ($action) {
            'comment' => $this->intake->comment($tenantId, $source, $id, $actor, $in('body')),
            'state' => $this->intake->setState($tenantId, $source, $id, $actor, $in('state'), $in('note')),
            'assign' => $this->intake->assign($tenantId, $source, $id, $actor, (int) $in('user_id', '0') ?: null),
            'type' => $this->intake->changeType($tenantId, $source, $id, $actor, $in('type')),
            'edit' => $this->intake->updateData($tenantId, $source, $id, $actor, array_intersect_key($request->all(), IntelIntakeService::EDITABLE[$source] ?? []), $this->viewerLevel()) !== [],
            'redact' => $this->redactAllowed() && $this->intake->addRedaction(
                $tenantId, $source, $id, $actor, $in('field', 'body'), (string) $request->input('phrase', ''), $in('level'),
                array_map('intval', (array) $request->input('allowed', [])), $in('reason')
            ),
            'unredact' => $this->redactAllowed() && $this->intake->removeRedaction($tenantId, $source, $id, $actor, (int) $in('redaction_id', '0')),
            'blur' => $source === 'fiche' && $this->intake->setBlur($tenantId, $id, $actor, (int) $in('attachment_id', '0'), $in('blurred') === '1'),
            'delete' => $this->intake->delete($tenantId, $source, $id, $actor, $in('reason')),
            'restore' => $this->intake->restore($tenantId, $source, $id, $actor),
        };
        $messages = [
            'comment' => ['Commentaire ajouté.', 'Le commentaire est vide.'],
            'state' => ['État mis à jour.', 'État inchangé.'],
            'assign' => ['Attribution mise à jour.', 'Membre introuvable.'],
            'type' => ['Type de fiche changé.', 'Type inchangé ou inconnu.'],
            'edit' => ['Données enregistrées.', 'Aucune modification.'],
            'redact' => ['Passage caviardé.', 'Passage introuvable dans le champ choisi (copiez-le tel qu’il est écrit), ou caviardage réservé aux habilités « Très restreint ».'],
            'unredact' => ['Caviardage levé.', 'Caviardage introuvable ou réservé aux habilités « Très restreint ».'],
            'blur' => ['Pièce jointe mise à jour.', 'Pièce jointe introuvable.'],
            'delete' => ['Remontée supprimée. Elle reste restaurable depuis l’onglet Supprimées.', 'Suppression impossible.'],
            'restore' => ['Remontée restaurée.', 'Restauration impossible.'],
        ];
        Session::flash($ok ? 'success' : 'error', $messages[$action][$ok ? 0 : 1]);

        return Response::redirect($action === 'delete' && $ok ? url('back-office/remontees') : $back . ($action === 'comment' ? '#fil-fin' : ''));
    }

    /** Caviarder : réservé au plus haut niveau, sinon on pourrait cacher à soi-même… ou lever ce qu'on ne lit pas. */
    private function redactAllowed(): bool
    {
        return $this->viewerLevel() === SseCaseRepository::CLASS_RESTRICTED;
    }

    private function viewerLevel(): string
    {
        try {
            $this->clearance ??= new SseClearanceService();

            return $this->clearance->maxLevel();
        } catch (\Throwable) {
            return function_exists('can') && (can('admin.access') || can('atak.sse.grant'))
                ? SseCaseRepository::CLASS_RESTRICTED
                : SseCaseRepository::CLASS_INTERNAL;
        }
    }

    /** @return array{id: int, label: string} */
    private function actor(): array
    {
        $label = '';
        foreach (['callsign', 'display_name', 'username'] as $k) {
            $label = trim((string) (Session::get($k) ?? ''));
            if ($label !== '') {
                break;
            }
        }

        return ['id' => (int) Session::get('user_id'), 'label' => $label !== '' ? $label : 'Administrateur'];
    }

    private function guard(): int|Response
    {
        if (!Session::get('user_id')) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1 || !self::allowed()) {
            Session::flash('error', 'Accès réservé aux administrateurs et aux responsables du renseignement.');

            return Response::redirect(url('dashboard'));
        }

        return $tenantId;
    }

    /** Libellé d'un niveau, pour les vues. */
    public static function levelLabel(string $level): string
    {
        return SseRedactionService::levelLabel($level);
    }
}
