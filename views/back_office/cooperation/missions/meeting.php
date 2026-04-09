<?php
declare(strict_types=1);

use App\Support\CooperationDictionary;

$m = $interteamMission ?? [];
$meetingStateLabels = CooperationDictionary::meetingStateLabels();
$meetings = $interteamMeetings ?? [];
$canManage = !empty($interteamCanManage);
$canPilot = !empty($interteamCanPilot);
$csrf = $csrfToken ?? \App\Core\Csrf::token();
$sid = (int) ($m['id'] ?? 0);
$status = (string) ($m['status'] ?? '');
$jitsiRoom = trim((string) ($interteamJitsiRoom ?? ''));
$jitsiDomain = trim((string) ($interteamJitsiDomain ?? 'meet.jit.si'));
$jitsiEnabled = !empty($interteamJitsiEnabled);
$jitsiSrc = $jitsiEnabled && $jitsiRoom !== '' && $jitsiDomain !== '' && $status === 'active'
    ? 'https://' . htmlspecialchars($jitsiDomain, ENT_QUOTES, 'UTF-8') . '/' . htmlspecialchars($jitsiRoom, ENT_QUOTES, 'UTF-8') . '#config.prejoinPageEnabled=false'
    : '';
?>
<div class="max-w-4xl mx-auto px-6 py-10 space-y-8">
    <div>
        <a href="<?= htmlspecialchars(cooperation_mission_show_url($sid), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">← Synthèse</a>
        <?php require base_path('views/back_office/cooperation/missions/_nav.php'); ?>
        <h1 class="mt-4 text-2xl font-black text-slate-900">Réunion</h1>
        <p class="mt-2 text-sm text-slate-600">Salon vidéo temporaire et suivi des réunions notées dans le journal.</p>
    </div>

    <?php if ($jitsiSrc !== ''): ?>
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Visioconférence</h2>
        <p class="mt-2 text-xs text-slate-600 leading-relaxed">Salon pour la coordination entre unités. L’enregistrement dépend de votre instance de visioconférence.</p>
        <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-black/5">
            <iframe src="<?= $jitsiSrc ?>" class="w-full h-[420px] border-0" allow="camera; microphone; fullscreen; display-capture; autoplay" title="Visioconférence coopération"></iframe>
        </div>
    </section>
    <?php elseif ($status !== 'active'): ?>
    <p class="text-sm text-slate-600">La visioconférence est disponible lorsque la coopération est en cours.</p>
    <?php endif; ?>

    <?php if ($canPilot && $canManage && $status === 'active'): ?>
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Planifier une réunion</h2>
        <p class="mt-2 text-xs text-slate-600 leading-relaxed">Indiquez un titre, l’ordre du jour et, si vous le souhaitez, la date et l’heure prévues. Cela alimente le journal de la coopération.</p>
        <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/meeting-schedule'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 space-y-3">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <label class="block text-xs font-semibold text-slate-700">Intitulé</label>
            <input type="text" name="meeting_title" maxlength="255" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Ex. Point de coordination inter-unités">
            <label class="block text-xs font-semibold text-slate-700 mt-2">Ordre du jour</label>
            <textarea name="meeting_agenda" rows="4" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Points à traiter, objectifs, documents de référence…"></textarea>
            <label class="block text-xs font-semibold text-slate-700 mt-2">Date et heure prévues (facultatif)</label>
            <input type="datetime-local" name="scheduled_at" class="w-full max-w-xs rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <label class="block text-xs font-semibold text-slate-700 mt-2">Participants attendus (facultatif)</label>
            <textarea name="expected_participants_note" rows="2" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Rôles ou unités attendus, lien interne…"></textarea>
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Ajouter au journal</button>
        </form>
    </section>
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Compte rendu ou enregistrement</h2>
        <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/meta'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 space-y-3">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="url" name="meeting_replay_url" value="<?= htmlspecialchars((string) ($m['meeting_replay_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Lien vers le compte rendu ou la rediffusion">
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Enregistrer le lien</button>
        </form>
        <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/meeting-start'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Noter une réunion dans le journal</button>
        </form>
    </section>
    <?php endif; ?>

    <?php if ($meetings !== []): ?>
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Réunions enregistrées</h2>
        <ul class="mt-3 text-sm text-slate-700 space-y-4">
            <?php foreach ($meetings as $mt): ?>
            <?php
                $mtTitle = trim((string) ($mt['meeting_title'] ?? ''));
                $sched = trim((string) ($mt['scheduled_at'] ?? ''));
                $agenda = trim((string) ($mt['meeting_agenda'] ?? ''));
                $mstate = trim((string) ($mt['meeting_state'] ?? ''));
                $stateLab = $mstate !== '' ? ($meetingStateLabels[$mstate] ?? 'État enregistré') : '';
                $expPart = trim((string) ($mt['expected_participants_note'] ?? ''));
                $minutes = trim((string) ($mt['minutes_text'] ?? ''));
                $line = $mtTitle !== '' ? $mtTitle : 'Réunion notée le ' . (string) ($mt['created_at'] ?? '');
            ?>
            <li class="border-b border-slate-100 pb-3 last:border-0">
                <p class="font-semibold text-slate-900"><?= htmlspecialchars($line, ENT_QUOTES, 'UTF-8') ?></p>
                <?php if ($stateLab !== ''): ?>
                <p class="mt-1 text-xs text-slate-600">Statut : <strong><?= htmlspecialchars($stateLab, ENT_QUOTES, 'UTF-8') ?></strong></p>
                <?php endif; ?>
                <?php if ($sched !== ''): ?>
                <p class="mt-1 text-xs text-slate-600">Prévu : <strong><?= htmlspecialchars($sched, ENT_QUOTES, 'UTF-8') ?></strong></p>
                <?php endif; ?>
                <?php if ($expPart !== ''): ?>
                <p class="mt-1 text-xs text-slate-600">Participants attendus : <?= htmlspecialchars($expPart, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($minutes !== ''): ?>
                <?php
                    $minPrev = $minutes;
                    if (function_exists('mb_strlen') && mb_strlen($minutes, 'UTF-8') > 600) {
                        $minPrev = mb_substr($minutes, 0, 600, 'UTF-8') . '…';
                    } elseif (strlen($minutes) > 600) {
                        $minPrev = substr($minutes, 0, 600) . '…';
                    }
                ?>
                <p class="mt-2 text-xs text-slate-600 leading-relaxed whitespace-pre-wrap"><span class="font-semibold text-slate-800">Compte rendu :</span> <?= htmlspecialchars($minPrev, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($agenda !== ''): ?>
                <?php
                    $agendaPreview = $agenda;
                    if (function_exists('mb_strlen') && mb_strlen($agenda, 'UTF-8') > 400) {
                        $agendaPreview = mb_substr($agenda, 0, 400, 'UTF-8') . '…';
                    } elseif (strlen($agenda) > 400) {
                        $agendaPreview = substr($agenda, 0, 400) . '…';
                    }
                ?>
                <p class="mt-2 text-xs text-slate-600 leading-relaxed whitespace-pre-wrap"><?= htmlspecialchars($agendaPreview, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>
</div>
