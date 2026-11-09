<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PlatformBoMessageRepository;
use App\Repositories\SubscriptionPlanRepository;
use App\Repositories\TenantRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Support\PlatformBoMessage;

/**
 * /admin/system/messages-communautes : messages de l'administration du site dans les back-offices des communautés
 * (une, plusieurs ou toutes, filtrées par formule ou par type). Droit admin.system revérifié à chaque requête.
 */
final class SystemPlatformBoMessagesController
{
    private const BASE = 'admin/system/messages-communautes';

    public const TYPES = ['full' => 'Complet', 'effectifs' => 'Bureau des effectifs', 'atak' => 'Carte ATAK'];

    public function __construct(
        private ?PlatformBoMessageRepository $repo = null,
        private ?TenantRepository $tenants = null,
        private ?SubscriptionPlanRepository $plans = null,
        private ?Gate $gate = null,
        private ?AuditService $audit = null,
    ) {
        $this->repo ??= new PlatformBoMessageRepository();
        $this->tenants ??= Container::get(TenantRepository::class);
        $this->plans ??= Container::get(SubscriptionPlanRepository::class);
        $this->gate ??= Gate::getInstance();
    }

    public function index(Request $request, array $params = []): Response
    {
        if (($deny = $this->deny()) !== null) {
            return $deny;
        }
        $error = null;
        $rows = [];
        $stats = [];
        try {
            $rows = $this->repo->listAll();
            $stats = $this->repo->stats();
        } catch (\Throwable) {
            $error = 'Les messages aux communautés ne sont pas disponibles (mise à jour de la base à lancer).';
        }
        $tenants = $this->tenantList();
        $now = time();
        $items = [];
        foreach ($rows as $r) {
            $targeted = 0;
            foreach ($tenants as $t) {
                if (PlatformBoMessage::targets($r, (int) $t['id'], (string) ($t['plan_slug'] ?? 'free'), (string) ($t['tenant_type'] ?? 'full'))) {
                    $targeted++;
                }
            }
            $r['_status'] = $this->status($r, $now);
            $r['_targeted'] = $targeted;
            $r['_stats'] = $stats[(int) $r['id']] ?? ['seen' => 0, 'clicked' => 0, 'dismissed' => 0, 'acked' => 0];
            $items[] = $r;
        }

        return $this->page('admin.system.platform_bo_messages_index', 'Messages aux communautés', [
            'pbmRows' => $items,
            'pbmError' => $error,
            'pbmMax' => PlatformBoMessage::MAX_ACTIVE,
            'pbmPlansNames' => $this->planNames(),
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        if (($deny = $this->deny()) !== null) {
            return $deny;
        }

        return $this->form(null, 'Nouveau message aux communautés');
    }

    public function edit(Request $request, array $params = []): Response
    {
        if (($deny = $this->deny()) !== null) {
            return $deny;
        }
        $row = $this->repo->find((int) ($params['id'] ?? 0));
        if ($row === null) {
            Session::flash('error', 'Message introuvable.');

            return Response::redirect(url(self::BASE));
        }

        return $this->form($row, 'Modifier le message');
    }

    public function store(Request $request, array $params = []): Response
    {
        if (($deny = $this->deny($request)) !== null) {
            return $deny;
        }
        $d = $this->normalize($request, null);
        if ($d['error'] !== null) {
            Session::flash('error', $d['error']);

            return Response::redirect(url(self::BASE . '/create'));
        }
        $actor = (int) Session::get('user_id') ?: null;
        $id = $this->repo->create($d['data'], $actor);
        $this->audit(AuditAction::PLATFORM_BO_MESSAGE_CREATED, $id, [], $this->snapshot($d['data']));
        Session::flash('success', 'Message enregistré.');

        return Response::redirect(url(self::BASE));
    }

    public function update(Request $request, array $params = []): Response
    {
        if (($deny = $this->deny($request)) !== null) {
            return $deny;
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $this->repo->find($id);
        if ($row === null) {
            Session::flash('error', 'Message introuvable.');

            return Response::redirect(url(self::BASE));
        }
        $d = $this->normalize($request, $id);
        if ($d['error'] !== null) {
            Session::flash('error', $d['error']);

            return Response::redirect(url(self::BASE . '/' . $id . '/edit'));
        }
        $this->repo->update($id, $d['data'], (int) Session::get('user_id') ?: null);
        $this->audit(AuditAction::PLATFORM_BO_MESSAGE_UPDATED, $id, $this->snapshot($row), $this->snapshot($d['data']));
        Session::flash('success', 'Message enregistré.');

        return Response::redirect(url(self::BASE));
    }

    public function toggle(Request $request, array $params = []): Response
    {
        if (($deny = $this->deny($request)) !== null) {
            return $deny;
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $this->repo->find($id);
        if ($row === null) {
            Session::flash('error', 'Message introuvable.');

            return Response::redirect(url(self::BASE));
        }
        $activate = empty($row['is_active']);
        if ($activate && $this->repo->countActive($id) >= PlatformBoMessage::MAX_ACTIVE) {
            Session::flash('error', 'Limite atteinte : trop de messages actifs. Désactivez-en un d’abord.');

            return Response::redirect(url(self::BASE));
        }
        $this->repo->setActive($id, $activate, (int) Session::get('user_id') ?: null);
        $this->audit(AuditAction::PLATFORM_BO_MESSAGE_TOGGLED, $id, ['is_active' => !$activate], ['is_active' => $activate]);
        Session::flash('success', $activate ? 'Message activé.' : 'Message désactivé.');

        return Response::redirect(url(self::BASE));
    }

    public function delete(Request $request, array $params = []): Response
    {
        if (($deny = $this->deny($request)) !== null) {
            return $deny;
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $this->repo->find($id);
        if ($row !== null) {
            $this->repo->delete($id);
            $this->audit(AuditAction::PLATFORM_BO_MESSAGE_DELETED, $id, $this->snapshot($row), []);
            Session::flash('success', 'Message supprimé.');
        }

        return Response::redirect(url(self::BASE));
    }

    /** POST …/apercu : rendu côté serveur (donc assaini) du message tel que la communauté le verra, en FR et en EN. */
    public function preview(Request $request, array $params = []): Response
    {
        if (($deny = $this->deny($request, true)) !== null) {
            return $deny;
        }
        $d = $this->normalize($request, null, true);
        $out = [];
        foreach (['fr', 'en'] as $loc) {
            $title = PlatformBoMessage::localized($d['data'], 'title', $loc);
            $body = PlatformBoMessage::localized($d['data'], 'body', $loc);
            $label = PlatformBoMessage::localized($d['data'], 'cta_label', $loc);
            $url = PlatformBoMessage::safeUrl($d['data']['cta_url'] ?? null);
            $platformMessages = $title === '' ? [] : [[
                'id' => 0,
                'level' => $d['data']['level'],
                'title' => $title,
                'body_html' => PlatformBoMessage::renderBody($body),
                'cta_label' => $url !== null ? $label : '',
                'cta_url' => $url !== null && $label !== '' ? $url : null,
                'dismissible' => $d['data']['level'] !== 'urgent',
                'dismissed' => false,
                'is_new' => true,
            ]];
            $pmPreview = true;
            ob_start();
            require base_path('views/partials/platform_messages_cards.php');
            $out[$loc] = (string) ob_get_clean();
        }

        return Response::json(['ok' => true, 'error' => $d['error'], 'fr' => $out['fr'], 'en' => $out['en']]);
    }

    private function deny(?Request $request = null, bool $json = false): ?Response
    {
        if (!Session::get('user_id') || !$this->gate->allows('admin.system')) {
            if ($json) {
                return Response::json(['ok' => false, 'error' => 'Accès réservé à l’administration du site.'], 403);
            }
            Session::flash('error', 'Accès réservé à l’administration du site.');

            return Response::redirect(url('login'));
        }
        if ($request !== null && !Csrf::verify($request)) {
            if ($json) {
                return Response::json(['ok' => false, 'error' => 'Session expirée. Rechargez la page.'], 419);
            }
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url(self::BASE));
        }

        return null;
    }

    /** @param array<string, mixed>|null $row */
    private function form(?array $row, string $title): Response
    {
        $pbmPlans = [];
        try {
            foreach ($this->plans->allOrdered() as $p) {
                $slug = (string) ($p['slug'] ?? '');
                if ($slug !== '') {
                    $pbmPlans[$slug] = (string) ($p['name'] ?? $slug);
                }
            }
        } catch (\Throwable) {
            $pbmPlans = [];
        }

        return $this->page('admin.system.platform_bo_messages_form', $title, [
            'pbmRow' => $row,
            'pbmTenants' => $this->tenantList(),
            'pbmPlans' => $pbmPlans,
            'pbmTypes' => self::TYPES,
            'pbmFormAction' => $row === null ? url(self::BASE) : url(self::BASE . '/' . (int) $row['id'] . '/update'),
        ]);
    }

    /** @param array<string, mixed> $data */
    private function page(string $view, string $title, array $data): Response
    {
        return Response::view('layout.main', array_merge([
            'content' => $view,
            'title' => $title,
            'isPlatformAdminShell' => true,
            'backOfficePageCss' => ['platform-admin.css', 'platform-messages.css'],
        ], $data));
    }

    /** @return list<array<string, mixed>> */
    private function tenantList(): array
    {
        try {
            return $this->tenants->listOverviewForPlatform();
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<string, string> */
    private function planNames(): array
    {
        $out = [];
        try {
            foreach ($this->plans->allOrdered() as $p) {
                $out[(string) ($p['slug'] ?? '')] = (string) ($p['name'] ?? $p['slug'] ?? '');
            }
        } catch (\Throwable) {
        }

        return $out;
    }

    /** @param array<string, mixed> $row */
    private function status(array $row, int $now): string
    {
        if (empty($row['is_active'])) {
            return 'inactive';
        }
        if (PlatformBoMessage::isLive($row, $now)) {
            return 'live';
        }
        $ends = isset($row['ends_at']) && $row['ends_at'] !== '' ? strtotime((string) $row['ends_at']) : false;

        return ($ends !== false && $ends < $now) ? 'ended' : 'scheduled';
    }

    /**
     * Saisie → données assainies. Rien de la saisie n'est gardé tel quel : texte brut, niveau, dates, adresse et ciblage
     * sont revalidés ici ; le ciblage ne retient que des communautés, formules et types qui existent.
     *
     * @return array{data: array<string, mixed>, error: ?string}
     */
    private function normalize(Request $request, ?int $exceptId, bool $lenient = false): array
    {
        $in = static fn (string $k): mixed => $request->input($k);
        $arr = static fn (string $k): array => is_array($request->input($k)) ? $request->input($k) : [];
        $titleFr = PlatformBoMessage::cleanText($in('title_fr'), PlatformBoMessage::TITLE_MAX);
        $titleEn = PlatformBoMessage::cleanText($in('title_en'), PlatformBoMessage::TITLE_MAX);
        $labelFr = PlatformBoMessage::cleanText($in('cta_label_fr'), PlatformBoMessage::CTA_LABEL_MAX);
        $labelEn = PlatformBoMessage::cleanText($in('cta_label_en'), PlatformBoMessage::CTA_LABEL_MAX);
        $urlRaw = trim((string) (is_scalar($in('cta_url')) ? $in('cta_url') : ''));
        $url = $urlRaw === '' ? null : PlatformBoMessage::safeUrl($urlRaw, ['https', 'http']);
        $starts = PlatformBoMessage::parseDateTime($in('starts_at'));
        $ends = PlatformBoMessage::parseDateTime($in('ends_at'));
        $active = $in('is_active') === '1' || $in('is_active') === 'on' || $in('is_active') === 1;

        $knownTenants = array_map(static fn (array $t): int => (int) $t['id'], $this->tenantList());
        $knownPlans = array_keys($this->planNames());
        $target = PlatformBoMessage::normalizeTarget(
            $in('target_mode'),
            $arr('target_tenants'),
            $arr('target_plans'),
            $arr('target_types'),
            $knownTenants,
            array_values(array_filter($knownPlans, static fn (string $s): bool => $s !== '')),
            array_keys(self::TYPES)
        );

        $data = [
            'level' => PlatformBoMessage::level(is_scalar($in('level')) ? (string) $in('level') : ''),
            'title_fr' => $titleFr,
            'title_en' => $titleEn,
            'body_fr' => PlatformBoMessage::cleanText($in('body_fr'), PlatformBoMessage::BODY_MAX, true),
            'body_en' => PlatformBoMessage::cleanText($in('body_en'), PlatformBoMessage::BODY_MAX, true),
            'cta_label_fr' => $labelFr,
            'cta_label_en' => $labelEn,
            'cta_url' => $url,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'is_active' => $active,
            'target_mode' => $target['mode'],
            'target_json' => $target['values'] === [] ? null : json_encode($target['values'], JSON_UNESCAPED_UNICODE),
        ];

        $error = null;
        if ($titleFr === '' && $titleEn === '') {
            $error = 'Le titre est obligatoire (en français ou en anglais).';
        } elseif ($urlRaw !== '' && $url === null) {
            $error = 'Adresse du bouton invalide : utilisez une adresse complète en https:// (ou http://).';
        } elseif ($url !== null && $labelFr === '' && $labelEn === '') {
            $error = 'Indiquez le libellé du bouton (par exemple « Rejoindre notre Discord »).';
        } elseif ($url === null && ($labelFr !== '' || $labelEn !== '')) {
            $error = 'Un libellé de bouton demande une adresse.';
        } elseif ($starts !== null && $ends !== null && $ends < $starts) {
            $error = 'La date de fin doit être postérieure au début.';
        } elseif ($target['error'] !== null) {
            $error = $target['error'];
        } elseif ($active && !$lenient && $this->repo->countActive($exceptId) >= PlatformBoMessage::MAX_ACTIVE
            && ($ends === null || strtotime($ends) >= time())) {
            $error = 'Limite atteinte : trop de messages actifs. Désactivez-en un d’abord.';
        }
        return ['data' => $data, 'error' => $error];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function snapshot(array $row): array
    {
        $keys = ['level', 'title_fr', 'title_en', 'cta_url', 'starts_at', 'ends_at', 'is_active', 'target_mode', 'target_json'];
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = $row[$k] ?? null;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     */
    private function audit(string $action, int $id, array $old, array $new): void
    {
        try {
            ($this->audit ?? Container::get(AuditService::class))->logChange($action, null, (int) Session::get('user_id') ?: null, 'platform_bo_message', $id, $old, $new);
        } catch (\Throwable $e) {
            error_log('[audit] ' . $action . ' : ' . $e->getMessage());
        }
    }
}
