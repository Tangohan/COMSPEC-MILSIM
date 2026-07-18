<?php
/** @var string $auditScope */
/** @var list<array<string, mixed>> $auditRows */
/** @var int $auditTotal */
/** @var int $auditPage */
/** @var int $auditTotalPages */
/** @var array<string, mixed> $auditFilters */
/** @var array<string, string> $auditActionFilterOptions */

use App\Support\Audit\AuditSnapshotPresenter;

$auditScope = $auditScope ?? 'system';
$auditActionFilterOptions = is_array($auditActionFilterOptions ?? null) ? $auditActionFilterOptions : [];
$basePath = $auditScope === 'organization' ? 'back-office/audit' : 'admin/audit';
$showTenantCol = $auditScope === 'system';
$tableColspan = $showTenantCol ? 8 : 7;
$backUrl = $auditScope === 'organization' ? 'back-office' : 'admin';
$pageTitle = $auditScope === 'organization' ? 'Journal d\'activité' : 'Journal d\'activité plateforme';

$buildLink = static function (int $page) use ($auditFilters, $basePath): string {
    $q = array_merge($auditFilters, ['page' => $page > 1 ? $page : null]);
    $q = array_filter($q, static fn ($v) => $v !== null && $v !== '');

    return url($basePath) . ($q ? '?' . http_build_query($q) : '');
};

$selectedSlug = (string) ($auditFilters['action_slug'] ?? '');
$selectedDomain = (string) ($auditFilters['action_domain'] ?? '');
$selectedEntityType = (string) ($auditFilters['entity_type'] ?? '');

$domainOptions = [
    'auth' => 'Authentification',
    'tenant' => 'Communauté',
    'invitation' => 'Invitations',
    'user' => 'Utilisateurs',
    'role' => 'Rôles',
    'group' => 'Groupes',
    'document' => 'Documents',
    'training' => 'Formation',
    'course' => 'Formation (cours)',
    'deployment' => 'Déploiement',
    'platform' => 'Plateforme',
    'site_role' => 'Rôles du site',
    'moderation' => 'Modération',
    'security' => 'Sécurité',
];

$entityTypeOptions = [
    'user' => 'Compte',
    'auth' => 'Connexion',
    'tenant' => 'Communauté',
    'document' => 'Document',
    'role' => 'Rôle',
    'group' => 'Groupe',
    'invitation' => 'Invitation',
    'course' => 'Formation',
    'enrollment' => 'Inscription',
    'module' => 'Fonctionnalité',
    'access_rule' => 'Règle d’accès',
];

$entityTypeLabel = static function (?string $type): string {
    return AuditSnapshotPresenter::entityTypeLabel($type);
};

$inputCls = 'h-8 w-full rounded border border-slate-300 bg-white px-2 text-xs text-slate-800 shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-400';
$labelCls = 'mb-0.5 block text-[10px] font-bold uppercase tracking-wide text-slate-500';

$colKeys = $showTenantCol
    ? ['date', 'tenant', 'actor', 'event', 'target', 'changes', 'origin', 'detail']
    : ['date', 'actor', 'event', 'target', 'changes', 'origin', 'detail'];
$colDefaults = $showTenantCol
    ? [140, 140, 200, 180, 200, 280, 120, 80]
    : [140, 200, 180, 200, 280, 120, 80];
?>
<style>
    .audit-sheets {
        border: 1px solid #cbd5e1;
        background: #ffffff;
        overflow: auto;
        max-height: min(72vh, 56rem);
    }
    .audit-sheets__table {
        width: 100%;
        min-width: 72rem;
        table-layout: fixed;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.8125rem;
        line-height: 1.35;
    }
    .audit-sheets__table th,
    .audit-sheets__table td {
        border-right: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
        padding: 0.35rem 0.5rem;
        vertical-align: top;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .audit-sheets__table th:last-child,
    .audit-sheets__table td:last-child {
        border-right: 0;
    }
    .audit-sheets__table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #f1f5f9;
        color: #475569;
        font-size: 0.625rem;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        white-space: nowrap;
        border-bottom: 1px solid #94a3b8;
        box-shadow: 0 1px 0 #94a3b8;
        user-select: none;
    }
    .audit-sheets__table thead th .audit-col-label {
        display: block;
        padding-right: 0.5rem;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .audit-sheets__table thead th .audit-col-resizer {
        position: absolute;
        top: 0;
        right: 0;
        width: 6px;
        height: 100%;
        cursor: col-resize;
        touch-action: none;
    }
    .audit-sheets__table thead th .audit-col-resizer:hover,
    .audit-sheets__table thead th .audit-col-resizer.is-active {
        background: rgba(16, 185, 129, 0.35);
    }
    .audit-sheets__table tbody tr:nth-child(even) td {
        background: #f8fafc;
    }
    .audit-sheets__table tbody tr:hover td {
        background: #eff6ff;
    }
    .audit-sheets__meta {
        display: block;
        margin-top: 0.05rem;
        font-size: 0.6875rem;
        color: #64748b;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .audit-sheets__cell-wrap {
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        white-space: normal;
        word-break: break-word;
    }
    body.audit-col-resizing {
        cursor: col-resize !important;
        user-select: none !important;
    }
</style>

<div class="audit-journal flex min-h-0 w-full max-w-none flex-1 flex-col bg-slate-50">
    <div class="shrink-0 border-b border-slate-200 bg-white px-3 py-2.5 sm:px-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="min-w-0">
                <h1 class="truncate text-base font-black tracking-tight text-slate-900 sm:text-lg"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="text-xs text-slate-500">
                    <?= (int) $auditTotal ?> événement<?= (int) $auditTotal === 1 ? '' : 's' ?>
                    <?php if ($auditTotalPages > 1): ?>
                        · page <?= (int) $auditPage ?> / <?= (int) $auditTotalPages ?>
                    <?php endif; ?>
                    · tirez le bord droit d’un en-tête pour ajuster la largeur
                </p>
            </div>
            <a href="<?= url($backUrl) ?>" class="inline-flex h-8 items-center rounded border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Retour</a>
        </div>
    </div>

    <form method="get" action="<?= url($basePath) ?>" class="shrink-0 border-b border-slate-200 bg-slate-50/90 px-3 py-2 sm:px-4">
        <div class="flex flex-wrap items-end gap-x-2 gap-y-2">
            <div>
                <label class="<?= $labelCls ?>" for="audit-date-from">Du</label>
                <input id="audit-date-from" type="date" name="date_from" value="<?= htmlspecialchars((string) ($auditFilters['date_from'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="<?= $inputCls ?> min-w-[9.5rem]" />
            </div>
            <div>
                <label class="<?= $labelCls ?>" for="audit-date-to">Au</label>
                <input id="audit-date-to" type="date" name="date_to" value="<?= htmlspecialchars((string) ($auditFilters['date_to'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="<?= $inputCls ?> min-w-[9.5rem]" />
            </div>
            <div class="min-w-[12rem] flex-1">
                <label class="<?= $labelCls ?>" for="audit-action-slug">Type d’événement</label>
                <select id="audit-action-slug" name="action_slug" class="<?= $inputCls ?> bo-select">
                    <option value="">Tous</option>
                    <?php foreach ($auditActionFilterOptions as $slug => $label): ?>
                        <option value="<?= htmlspecialchars((string) $slug, ENT_QUOTES, 'UTF-8') ?>"<?= $selectedSlug === (string) $slug ? ' selected' : '' ?>><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="min-w-[10rem]">
                <label class="<?= $labelCls ?>" for="audit-action-domain">Domaine</label>
                <select id="audit-action-domain" name="action_domain" class="<?= $inputCls ?>">
                    <option value="">Tous</option>
                    <?php foreach ($domainOptions as $domain => $label): ?>
                        <option value="<?= htmlspecialchars($domain, ENT_QUOTES, 'UTF-8') ?>"<?= $selectedDomain === $domain ? ' selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="min-w-[12rem] flex-1">
                <label class="<?= $labelCls ?>" for="audit-search">Recherche</label>
                <input id="audit-search" type="text" name="search" value="<?= htmlspecialchars((string) ($auditFilters['search'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="<?= $inputCls ?>" placeholder="Acteur, événement, communauté…" />
            </div>
            <div class="min-w-[10rem]">
                <label class="<?= $labelCls ?>" for="audit-actor-email">Acteur (e-mail)</label>
                <input id="audit-actor-email" type="text" name="actor_email" value="<?= htmlspecialchars((string) ($auditFilters['actor_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="<?= $inputCls ?>" placeholder="adresse@…" />
            </div>
            <div class="min-w-[9rem]">
                <label class="<?= $labelCls ?>" for="audit-entity-type">Élément concerné</label>
                <select id="audit-entity-type" name="entity_type" class="<?= $inputCls ?>">
                    <option value="">Tous</option>
                    <?php foreach ($entityTypeOptions as $type => $label): ?>
                        <option value="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>"<?= $selectedEntityType === $type ? ' selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                    <?php if ($selectedEntityType !== '' && !isset($entityTypeOptions[$selectedEntityType])): ?>
                        <option value="<?= htmlspecialchars($selectedEntityType, ENT_QUOTES, 'UTF-8') ?>" selected><?= htmlspecialchars($entityTypeLabel($selectedEntityType), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="w-[6.5rem]">
                <label class="<?= $labelCls ?>" for="audit-entity-id">N° élément</label>
                <input id="audit-entity-id" type="number" name="entity_id" value="<?= $auditFilters['entity_id'] !== null && $auditFilters['entity_id'] !== '' ? (int) $auditFilters['entity_id'] : '' ?>" class="<?= $inputCls ?>" min="1" />
            </div>
            <div class="w-[6.5rem]">
                <label class="<?= $labelCls ?>" for="audit-user-id">N° compte</label>
                <input id="audit-user-id" type="number" name="user_id" value="<?= $auditFilters['user_id'] !== null && $auditFilters['user_id'] !== '' ? (int) $auditFilters['user_id'] : '' ?>" class="<?= $inputCls ?>" min="1" />
            </div>
            <?php if ($showTenantCol): ?>
            <div class="w-[6.5rem]">
                <label class="<?= $labelCls ?>" for="audit-tenant-id">N° communauté</label>
                <input id="audit-tenant-id" type="number" name="tenant_id" value="<?= isset($auditFilters['tenant_id']) && $auditFilters['tenant_id'] ? (int) $auditFilters['tenant_id'] : '' ?>" class="<?= $inputCls ?>" min="1" />
            </div>
            <?php endif; ?>
            <div class="flex items-center gap-1.5 pb-px">
                <button type="submit" class="inline-flex h-8 items-center rounded bg-slate-900 px-3 text-xs font-semibold text-white hover:bg-slate-800">Filtrer</button>
                <a href="<?= url($basePath) ?>" class="inline-flex h-8 items-center px-2 text-xs font-medium text-slate-600 hover:underline">Effacer</a>
            </div>
        </div>
        <?php
        $legacyAction = trim((string) ($auditFilters['action'] ?? ''));
        if ($legacyAction !== ''):
        ?>
            <input type="hidden" name="action" value="<?= htmlspecialchars($legacyAction, ENT_QUOTES, 'UTF-8') ?>" />
        <?php endif; ?>
    </form>

    <div class="min-h-0 flex-1 px-0">
        <div class="audit-sheets">
            <table
                class="audit-sheets__table text-left"
                id="audit-sheets-table"
                data-audit-scope="<?= htmlspecialchars((string) $auditScope, ENT_QUOTES, 'UTF-8') ?>"
                data-col-keys="<?= htmlspecialchars(implode(',', $colKeys), ENT_QUOTES, 'UTF-8') ?>"
                data-col-defaults="<?= htmlspecialchars(implode(',', array_map('strval', $colDefaults)), ENT_QUOTES, 'UTF-8') ?>"
            >
                <colgroup>
                    <?php foreach ($colKeys as $i => $ck): ?>
                        <col data-col="<?= htmlspecialchars($ck, ENT_QUOTES, 'UTF-8') ?>" style="width: <?= (int) $colDefaults[$i] ?>px" />
                    <?php endforeach; ?>
                </colgroup>
                <thead>
                    <tr>
                        <th data-col="date" style="position:relative"><span class="audit-col-label">Date</span><span class="audit-col-resizer" aria-hidden="true"></span></th>
                        <?php if ($showTenantCol): ?>
                        <th data-col="tenant" style="position:relative"><span class="audit-col-label">Communauté</span><span class="audit-col-resizer" aria-hidden="true"></span></th>
                        <?php endif; ?>
                        <th data-col="actor" style="position:relative"><span class="audit-col-label">Acteur</span><span class="audit-col-resizer" aria-hidden="true"></span></th>
                        <th data-col="event" style="position:relative"><span class="audit-col-label">Événement</span><span class="audit-col-resizer" aria-hidden="true"></span></th>
                        <th data-col="target" style="position:relative"><span class="audit-col-label">Élément concerné</span><span class="audit-col-resizer" aria-hidden="true"></span></th>
                        <th data-col="changes" style="position:relative"><span class="audit-col-label">Modifications</span><span class="audit-col-resizer" aria-hidden="true"></span></th>
                        <th data-col="origin" style="position:relative"><span class="audit-col-label">Origine</span><span class="audit-col-resizer" aria-hidden="true"></span></th>
                        <th data-col="detail" style="position:relative"><span class="audit-col-label">Détail</span><span class="audit-col-resizer" aria-hidden="true"></span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($auditRows)): ?>
                        <tr>
                            <td colspan="<?= (int) $tableColspan ?>" class="px-3 py-10 text-center text-sm text-slate-500">
                                Aucun événement pour ces critères.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($auditRows as $row): ?>
                            <?php
                            $rid = (int) ($row['id'] ?? 0);
                            $detailUrl = url($basePath . '/' . $rid);
                            $actorPrimary = AuditSnapshotPresenter::actorPrimaryLabel($row);
                            $actorSecondary = AuditSnapshotPresenter::actorSecondaryLabel($row);
                            $target = AuditSnapshotPresenter::entityTargetLabels($row);
                            $changes = AuditSnapshotPresenter::listSummary(
                                isset($row['old_value']) ? (string) $row['old_value'] : null,
                                isset($row['new_value']) ? (string) $row['new_value'] : null
                            );
                            $ipMasked = AuditSnapshotPresenter::maskIpForDisplay(isset($row['ip']) ? (string) $row['ip'] : null);
                            $browser = AuditSnapshotPresenter::browserHint(isset($row['user_agent']) ? (string) $row['user_agent'] : null);
                            $createdAt = (string) ($row['created_at'] ?? '');
                            $createdLabel = $createdAt;
                            $createdMeta = '';
                            if ($createdAt !== '' && ($ts = strtotime($createdAt)) !== false) {
                                $createdLabel = date('d/m/Y H:i', $ts);
                                $createdMeta = 'Réf. ' . $rid;
                            }
                            ?>
                            <tr>
                                <td class="tabular-nums text-slate-700" title="<?= htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8') ?>">
                                    <span class="font-medium"><?= htmlspecialchars($createdLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if ($createdMeta !== ''): ?><span class="audit-sheets__meta"><?= htmlspecialchars($createdMeta, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                                </td>
                                <?php if ($showTenantCol): ?>
                                    <td class="text-slate-700"><?= htmlspecialchars((string) ($row['tenant_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                <?php endif; ?>
                                <td class="text-slate-700">
                                    <span class="font-medium"><?= htmlspecialchars($actorPrimary, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if ($actorSecondary !== ''): ?>
                                        <span class="audit-sheets__meta" title="<?= htmlspecialchars($actorSecondary, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($actorSecondary, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-slate-800">
                                    <?php $act = (string) ($row['action'] ?? ''); ?>
                                    <span class="font-medium audit-sheets__cell-wrap"><?= htmlspecialchars(audit_action_label_fr($act), ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td class="text-slate-700">
                                    <span class="font-medium audit-sheets__cell-wrap"><?= htmlspecialchars($target['primary'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if ($target['secondary'] !== ''): ?>
                                        <span class="audit-sheets__meta"><?= htmlspecialchars($target['secondary'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-slate-600">
                                    <span class="audit-sheets__cell-wrap" title="<?= htmlspecialchars($changes, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($changes, ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td class="text-slate-700">
                                    <span class="font-medium tabular-nums"><?= htmlspecialchars($ipMasked, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if ($browser !== ''): ?>
                                        <span class="audit-sheets__meta"><?= htmlspecialchars($browser, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-xs font-bold text-emerald-800 underline decoration-emerald-200 underline-offset-2 hover:text-emerald-950">Ouvrir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($auditTotalPages > 1): ?>
        <div class="flex shrink-0 items-center justify-between gap-3 border-t border-slate-200 bg-white px-3 py-2 text-xs sm:px-4">
            <span class="text-slate-600">Page <?= (int) $auditPage ?> / <?= (int) $auditTotalPages ?></span>
            <div class="flex gap-1.5">
                <?php if ($auditPage > 1): ?>
                    <a class="inline-flex h-8 items-center rounded border border-slate-300 bg-white px-2.5 font-semibold text-slate-700 hover:bg-slate-50" href="<?= htmlspecialchars($buildLink($auditPage - 1), ENT_QUOTES, 'UTF-8') ?>">Précédent</a>
                <?php endif; ?>
                <?php if ($auditPage < $auditTotalPages): ?>
                    <a class="inline-flex h-8 items-center rounded border border-slate-300 bg-white px-2.5 font-semibold text-slate-700 hover:bg-slate-50" href="<?= htmlspecialchars($buildLink($auditPage + 1), ENT_QUOTES, 'UTF-8') ?>">Suivant</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<script>
(function () {
    var table = document.getElementById('audit-sheets-table');
    if (!table) return;
    var scope = table.getAttribute('data-audit-scope') || 'org';
    var storageKey = 'comspec.audit.colWidths.v1.' + scope;
    var keys = (table.getAttribute('data-col-keys') || '').split(',').filter(Boolean);
    var defaults = (table.getAttribute('data-col-defaults') || '').split(',').map(function (n) { return parseInt(n, 10) || 120; });
    var cols = table.querySelectorAll('colgroup col');
    var minW = 64;

    function loadWidths() {
        var out = defaults.slice();
        try {
            var raw = localStorage.getItem(storageKey);
            if (!raw) return out;
            var parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== 'object') return out;
            keys.forEach(function (k, i) {
                var v = parseInt(parsed[k], 10);
                if (v >= minW && v <= 900) out[i] = v;
            });
        } catch (e) { /* ignore */ }
        return out;
    }

    function applyWidths(widths) {
        cols.forEach(function (col, i) {
            if (widths[i]) col.style.width = widths[i] + 'px';
        });
        var total = widths.reduce(function (a, b) { return a + b; }, 0);
        table.style.minWidth = Math.max(total, 720) + 'px';
        table.style.width = Math.max(total, 720) + 'px';
    }

    function saveWidths(widths) {
        var obj = {};
        keys.forEach(function (k, i) { obj[k] = widths[i]; });
        try { localStorage.setItem(storageKey, JSON.stringify(obj)); } catch (e) { /* ignore */ }
    }

    var widths = loadWidths();
    applyWidths(widths);

    var headers = table.querySelectorAll('thead th[data-col]');
    headers.forEach(function (th) {
        var handle = th.querySelector('.audit-col-resizer');
        if (!handle) return;
        handle.addEventListener('pointerdown', function (ev) {
            ev.preventDefault();
            ev.stopPropagation();
            var colKey = th.getAttribute('data-col');
            var idx = keys.indexOf(colKey);
            if (idx < 0) return;
            var startX = ev.clientX;
            var startW = widths[idx];
            handle.classList.add('is-active');
            document.body.classList.add('audit-col-resizing');
            try { handle.setPointerCapture(ev.pointerId); } catch (e) { /* ignore */ }

            function onMove(e) {
                var next = Math.max(minW, Math.min(900, startW + (e.clientX - startX)));
                widths[idx] = next;
                applyWidths(widths);
            }
            function onUp() {
                handle.classList.remove('is-active');
                document.body.classList.remove('audit-col-resizing');
                handle.removeEventListener('pointermove', onMove);
                handle.removeEventListener('pointerup', onUp);
                handle.removeEventListener('pointercancel', onUp);
                saveWidths(widths);
            }
            handle.addEventListener('pointermove', onMove);
            handle.addEventListener('pointerup', onUp);
            handle.addEventListener('pointercancel', onUp);
        });
    });
})();
</script>
