<?php
declare(strict_types=1);

use App\Support\CooperationDictionary;

$m = $interteamMission ?? [];
$events = $interteamEvents ?? [];
$sid = (int) ($m['id'] ?? 0);
$tlPage = (int) ($cooperationTimelinePage ?? 1);
$tlPer = (int) ($cooperationTimelinePerPage ?? 30);
$tlTotal = (int) ($cooperationTimelineTotal ?? 0);
$tlPages = max(1, (int) ceil($tlTotal / max(1, $tlPer)));
$canPilot = !empty($interteamCanPilot);
$canManage = !empty($interteamCanManage);
?>
<div class="max-w-4xl mx-auto px-6 py-10 space-y-8">
    <div>
        <a href="<?= htmlspecialchars(cooperation_mission_show_url($sid), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">← Synthèse</a>
        <?php require base_path('views/back_office/cooperation/missions/_nav.php'); ?>
        <h1 class="mt-4 text-2xl font-black text-slate-900">Chronologie</h1>
        <p class="mt-2 text-sm text-slate-600">Journal opérationnel de la coopération (échanges notables, autorisations, réunions).</p>
        <?php if ($canPilot && $canManage): ?>
        <p class="mt-2"><a href="<?= htmlspecialchars(cooperation_mission_timeline_export_url($sid), ENT_QUOTES, 'UTF-8') ?>" class="text-xs font-semibold text-emerald-800 underline">Exporter le journal (CSV)</a></p>
        <?php endif; ?>
    </div>

    <?php if ($events === []): ?>
    <p class="text-sm text-slate-600">Aucun événement enregistré pour l’instant.</p>
    <?php else: ?>
    <?php $filterLabels = CooperationDictionary::timelineFilterLabels(); ?>
    <div class="flex flex-wrap gap-2 mb-4" id="coop-timeline-filters" role="tablist" aria-label="Filtrer le journal">
        <?php foreach ($filterLabels as $key => $label): ?>
        <button type="button" data-timeline-filter="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" class="coop-timeline-filter-btn rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 aria-[pressed=true]:bg-slate-900 aria-[pressed=true]:text-white aria-[pressed=true]:border-slate-900"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></button>
        <?php endforeach; ?>
    </div>
    <p id="coop-timeline-empty-filter" class="hidden text-sm text-slate-600 mb-4">Aucun événement ne correspond à ce filtre.</p>
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <ul class="space-y-3 text-sm" id="coop-timeline-list">
            <?php foreach ($events as $ev): ?>
            <?php
                $et = (string) ($ev['event_type'] ?? '');
                $cat = CooperationDictionary::timelineEventCategory($et);
            ?>
            <li class="border-b border-slate-100 pb-2 coop-timeline-item" data-event-type="<?= htmlspecialchars($et, ENT_QUOTES, 'UTF-8') ?>" data-timeline-cat="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>">
                <span class="text-xs text-slate-500"><?= htmlspecialchars((string) ($ev['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="font-semibold text-slate-800 ml-2"><?= htmlspecialchars((string) ($ev['actor_display_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="text-slate-600"> — <?= htmlspecialchars(CooperationDictionary::eventTypeLabel($et), ENT_QUOTES, 'UTF-8') ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php if ($tlPages > 1): ?>
    <nav class="flex flex-wrap items-center gap-2 mb-4 text-xs" aria-label="Pagination du journal">
        <?php for ($i = 1; $i <= min($tlPages, 20); $i++): ?>
        <a href="<?= htmlspecialchars(cooperation_mission_timeline_url($sid) . '?page=' . $i, ENT_QUOTES, 'UTF-8') ?>" class="px-2 py-1 rounded border <?= $i === $tlPage ? 'bg-slate-900 text-white border-slate-900' : 'border-slate-200 bg-white text-slate-700' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </nav>
    <?php endif; ?>
    <script>
    (function () {
        var root = document.getElementById('coop-timeline-filters');
        var list = document.getElementById('coop-timeline-list');
        var emptyMsg = document.getElementById('coop-timeline-empty-filter');
        if (!root || !list) return;
        var items = list.querySelectorAll('.coop-timeline-item');
        var buttons = root.querySelectorAll('.coop-timeline-filter-btn');
        function apply(filter) {
            var visible = 0;
            items.forEach(function (li) {
                var cat = li.getAttribute('data-timeline-cat') || 'other';
                var show = filter === 'all' || cat === filter;
                li.classList.toggle('hidden', !show);
                if (show) visible++;
            });
            if (emptyMsg) emptyMsg.classList.toggle('hidden', visible > 0);
            buttons.forEach(function (btn) {
                var pressed = btn.getAttribute('data-timeline-filter') === filter;
                btn.setAttribute('aria-pressed', pressed ? 'true' : 'false');
            });
        }
        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                apply(btn.getAttribute('data-timeline-filter') || 'all');
            });
        });
        apply('all');
    })();
    </script>
    <?php endif; ?>
</div>
