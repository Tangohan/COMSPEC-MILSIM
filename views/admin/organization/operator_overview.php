<?php
declare(strict_types=1);

use App\Repositories\PersonnelAbsenceRepository;
use App\Repositories\PersonnelMobilityRequestRepository;
use App\Services\Effectifs\EffectifsStaffAlertService;

$tenant = is_array($operatorTenant ?? null) ? $operatorTenant : [];
$user = is_array($operatorUser ?? null) ? $operatorUser : [];
$profile = is_array($operatorProfile ?? null) ? $operatorProfile : [];
$units = is_array($operatorUnits ?? null) ? $operatorUnits : [];
$terminals = is_array($operatorTerminals ?? null) ? $operatorTerminals : [];
$mission = is_array($operatorMission ?? null) ? $operatorMission : null;
$absences = is_array($operatorAbsences ?? null) ? $operatorAbsences : [];
$elevations = is_array($operatorElevations ?? null) ? $operatorElevations : [];
$mobility = is_array($operatorMobility ?? null) ? $operatorMobility : [];
$events = is_array($operatorEvents ?? null) ? $operatorEvents : [];
$alerts = is_array($operatorAlerts ?? null) ? $operatorAlerts : [];
$onboardingRemaining = is_array($operatorOnboardingRemaining ?? null) ? $operatorOnboardingRemaining : [];
$inboxUnread = max(0, (int) ($operatorInboxUnread ?? 0));
$gradeLabel = trim((string) ($operatorGradeLabel ?? ''));
$dutyLabel = trim((string) ($operatorDutyLabel ?? ''));
$functionLabel = trim((string) ($operatorFunctionLabel ?? ''));
$portraitUrl = trim((string) ($operatorPortraitUrl ?? ''));
$onboardingNudge = trim((string) ($operatorOnboardingNudge ?? ''));

$h = static fn (mixed $value): string => htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
$displayName = trim((string) ($user['display_name'] ?? $profile['character_name'] ?? ''));
$callsign = trim((string) ($user['callsign'] ?? $profile['callsign'] ?? ''));
$communityName = trim((string) ($tenant['name'] ?? ''));
$unitNames = [];
foreach ($units as $unit) {
    if (!is_array($unit)) {
        continue;
    }
    $unitName = trim((string) ($unit['name'] ?? $unit['code'] ?? ''));
    if ($unitName !== '') {
        $unitNames[] = $unitName;
    }
}

$dateTime = static function (mixed $raw): string {
    $timestamp = strtotime((string) $raw);
    return $timestamp ? date('d/m/Y à H:i', $timestamp) : '';
};
$dateDay = static function (mixed $raw): string {
    $timestamp = strtotime((string) $raw);
    return $timestamp ? date('d/m/Y', $timestamp) : '';
};

$atakStatusLabel = static function (string $status, bool $recent): string {
    return match ($status) {
        'active' => $recent ? 'En service' : 'Lié, sans liaison récente',
        'inactive', 'offline' => 'Hors service',
        'pending' => 'En attente de liaison',
        'revoked' => 'Révoqué',
        'lost' => 'Déclaré perdu',
        default => $recent ? 'Lié' : 'Sans liaison récente',
    };
};
$rsvpLabel = static function (string $status): string {
    return match ($status) {
        'yes' => 'Présent',
        'maybe' => 'Peut-être',
        'no' => 'Absent',
        default => 'Pas encore répondu',
    };
};
$elevationStatusLabel = static function (string $status): string {
    return match ($status) {
        'pending' => 'En attente',
        'in_review' => 'En cours d’examen',
        default => 'En cours',
    };
};

$hasSituation = $absences !== [] || $elevations !== [] || $mobility !== [] || $onboardingRemaining !== [] || $inboxUnread > 0 || $alerts !== [];
$linkClass = 'text-sm font-bold text-emerald-700 hover:underline';
$cardClass = 'rounded-2xl border border-slate-200 bg-white p-6 shadow-sm';
$dtClass = 'text-xs font-bold uppercase tracking-wider text-slate-500';
?>

<div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
    <section class="<?= $h($cardClass) ?>">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
            <?php if ($portraitUrl !== ''): ?>
                <img src="<?= $h($portraitUrl) ?>" alt="" class="h-20 w-20 shrink-0 rounded-2xl object-cover ring-1 ring-slate-200">
            <?php else: ?>
                <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-lg font-black text-slate-500 ring-1 ring-slate-200" aria-hidden="true">
                    <?= $h($displayName !== '' ? mb_strtoupper(mb_substr($displayName, 0, 1)) : '?') ?>
                </div>
            <?php endif; ?>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Consultation personnelle</p>
                <h1 class="mt-2 text-2xl font-black text-slate-950">Bonjour <?= $displayName !== '' ? $h($displayName) : 'opérateur' ?></h1>
                <p class="mt-2 text-sm text-slate-600">Cet espace affiche uniquement vos données et les informations partagées par votre communauté. Il ne donne aucun droit d’administration.</p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2 sm:flex-col sm:items-end">
                <a class="<?= $h($linkClass) ?>" href="<?= $h(url('personnel/me')) ?>">Voir ma fiche</a>
                <a class="<?= $h($linkClass) ?>" href="<?= $h(url('personnel/mon-espace-rh')) ?>">Mes démarches</a>
            </div>
        </div>
        <dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <dt class="<?= $h($dtClass) ?>">Communauté</dt>
                <dd class="mt-1 font-semibold text-slate-900"><?= $communityName !== '' ? $h($communityName) : 'Non renseignée' ?></dd>
            </div>
            <div>
                <dt class="<?= $h($dtClass) ?>">Indicatif</dt>
                <dd class="mt-1 font-semibold text-slate-900"><?= $callsign !== '' ? $h($callsign) : 'Non renseigné' ?></dd>
            </div>
            <div>
                <dt class="<?= $h($dtClass) ?>">Unité</dt>
                <dd class="mt-1 font-semibold text-slate-900"><?= $unitNames !== [] ? $h(implode(' · ', $unitNames)) : 'Non affecté' ?></dd>
            </div>
            <div>
                <dt class="<?= $h($dtClass) ?>">Grade</dt>
                <dd class="mt-1 font-semibold text-slate-900"><?= $gradeLabel !== '' ? $h($gradeLabel) : 'Non renseigné' ?></dd>
            </div>
            <div>
                <dt class="<?= $h($dtClass) ?>">Fonction</dt>
                <dd class="mt-1 font-semibold text-slate-900"><?= $functionLabel !== '' ? $h($functionLabel) : 'Non renseignée' ?></dd>
            </div>
            <div>
                <dt class="<?= $h($dtClass) ?>">Position de service</dt>
                <dd class="mt-1 font-semibold text-slate-900"><?= $dutyLabel !== '' ? $h($dutyLabel) : 'Non renseignée' ?></dd>
            </div>
        </dl>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="<?= $h($cardClass) ?>">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-black text-slate-950">Ce qui vous concerne</h2>
                <a class="<?= $h($linkClass) ?>" href="<?= $h(url('personnel/mon-espace-rh')) ?>">Ouvrir Mes démarches</a>
            </div>
            <?php if (!$hasSituation): ?>
                <p class="mt-4 text-sm text-slate-600">Rien n’est en attente de votre côté pour le moment. Vos absences, demandes et messages apparaîtront ici.</p>
            <?php else: ?>
                <ul class="mt-4 space-y-3">
                    <?php if ($inboxUnread > 0): ?>
                        <li class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                            <p class="font-semibold text-slate-900"><?= $inboxUnread === 1 ? '1 message à lire' : $h((string) $inboxUnread) . ' messages à lire' ?></p>
                            <p class="mt-1 text-sm text-slate-600">Boîte de réception de la communauté.</p>
                            <a class="mt-2 inline-block <?= $h($linkClass) ?>" href="<?= $h(url('boite-reception')) ?>">Ouvrir la boîte de réception</a>
                        </li>
                    <?php endif; ?>
                    <?php foreach ($absences as $absence): ?>
                        <?php
                        $reasonKey = trim((string) ($absence['reason'] ?? ''));
                        $reason = $reasonKey !== '' ? PersonnelAbsenceRepository::reasonLabel($reasonKey) : '';
                        $from = $dateDay($absence['starts_on'] ?? '');
                        $until = $dateDay($absence['ends_on'] ?? '');
                        $period = $from !== '' ? ($until !== '' ? $from . ' → ' . $until : 'À partir du ' . $from) : '';
                        ?>
                        <li class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="font-semibold text-slate-900">Absence en cours<?= $reason !== '' ? ' — ' . $h($reason) : '' ?></p>
                            <?php if ($period !== ''): ?><p class="mt-1 text-sm text-slate-600"><?= $h($period) ?></p><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    <?php foreach ($elevations as $elevation): ?>
                        <?php
                        $kindKey = (string) ($elevation['kind'] ?? 'general');
                        $kindLabel = EffectifsStaffAlertService::ELEVATION_KIND_LABELS[$kindKey] ?? 'Situation';
                        ?>
                        <li class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="font-semibold text-slate-900">Demande d’élévation — <?= $h($kindLabel) ?></p>
                            <p class="mt-1 text-sm text-slate-600"><?= $h($elevationStatusLabel((string) ($elevation['status'] ?? ''))) ?></p>
                        </li>
                    <?php endforeach; ?>
                    <?php foreach ($mobility as $wish): ?>
                        <?php
                        $typeKey = (string) ($wish['type'] ?? '');
                        $typeLabel = PersonnelMobilityRequestRepository::TYPE_LABELS[$typeKey] ?? 'Souhait d’évolution';
                        ?>
                        <li class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="font-semibold text-slate-900"><?= $h($typeLabel) ?></p>
                            <p class="mt-1 text-sm text-slate-600">En attente de réponse.</p>
                        </li>
                    <?php endforeach; ?>
                    <?php if ($onboardingRemaining !== []): ?>
                        <li class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="font-semibold text-slate-900">Votre arrivée n’est pas terminée</p>
                            <p class="mt-1 text-sm text-slate-600">
                                <?= $onboardingNudge !== '' && $onboardingNudge !== 'RAS'
                                    ? $h($onboardingNudge)
                                    : 'Il reste des étapes à accomplir pour finaliser votre intégration.' ?>
                            </p>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-700">
                                <?php foreach ($onboardingRemaining as $step): ?>
                                    <li><?= $h((string) ($step['label'] ?? '')) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <a class="mt-2 inline-block <?= $h($linkClass) ?>" href="<?= $h(url('mon-integration')) ?>">Ouvrir Mon intégration</a>
                        </li>
                    <?php endif; ?>
                    <?php foreach ($alerts as $alert): ?>
                        <li class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="font-semibold text-slate-900"><?= $h((string) ($alert['title'] ?? '')) ?></p>
                            <?php if (trim((string) ($alert['body'] ?? '')) !== ''): ?>
                                <p class="mt-1 text-sm text-slate-600"><?= $h((string) $alert['body']) ?></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="<?= $h($cardClass) ?>">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-black text-slate-950">Prochaines manœuvres</h2>
                <a class="<?= $h($linkClass) ?>" href="<?= $h(url('evenements')) ?>">Voir le calendrier</a>
            </div>
            <?php if ($events === []): ?>
                <p class="mt-4 text-sm text-slate-600">Aucune manœuvre n’est encore annoncée. Dès qu’un créneau est publié, il apparaîtra ici.</p>
            <?php else: ?>
                <ul class="mt-4 space-y-3">
                    <?php foreach ($events as $event): ?>
                        <?php
                        $when = $dateTime($event['starts_at'] ?? '');
                        $rsvp = $rsvpLabel((string) ($event['rsvp_status'] ?? ''));
                        ?>
                        <li class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="font-semibold text-slate-900"><?= $h((string) ($event['title'] ?? 'Manœuvre')) ?></p>
                            <p class="mt-1 text-sm text-slate-600"><?= $when !== '' ? $h($when) : 'Horaire à confirmer' ?> · <?= $h($rsvp) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="mt-6 border-t border-slate-100 pt-5">
                <h3 class="text-base font-black text-slate-950">Mission en cours</h3>
                <?php if ($mission === null): ?>
                    <p class="mt-2 text-sm text-slate-600">Aucune mission n’est actuellement déclarée en direct.</p>
                <?php else: ?>
                    <p class="mt-2 text-xl font-black text-slate-950"><?= $h((string) ($mission['operation_name'] ?? $mission['title'] ?? '')) ?></p>
                    <?php
                    $missionCode = trim((string) ($mission['mission_code'] ?? ''));
                    $taskForce = trim((string) ($mission['task_force_name'] ?? ''));
                    ?>
                    <?php if ($missionCode !== '' || $taskForce !== ''): ?>
                        <p class="mt-2 text-sm text-slate-600">
                            <?= $missionCode !== '' ? $h($missionCode) : '' ?>
                            <?= $missionCode !== '' && $taskForce !== '' ? ' · ' : '' ?>
                            <?= $taskForce !== '' ? $h($taskForce) : '' ?>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <section class="<?= $h($cardClass) ?>">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-black text-slate-950">Ma liaison ATAK</h2>
            <div class="flex flex-wrap gap-3">
                <a class="<?= $h($linkClass) ?>" href="<?= $h(url('atak')) ?>">Ouvrir la carte</a>
                <a class="<?= $h($linkClass) ?>" href="<?= $h(url('account/security/devices')) ?>">Gérer les appareils</a>
                <a class="<?= $h($linkClass) ?>" href="<?= $h(url('atak/premiere-liaison')) ?>">Configurer ATAK</a>
            </div>
        </div>
        <?php if ($terminals === []): ?>
            <p class="mt-4 text-sm text-slate-600">Aucun téléphone ATAK n’est encore associé à votre compte. La première liaison se fait depuis Configurer ATAK.</p>
        <?php else: ?>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <?php foreach ($terminals as $terminal): ?>
                    <?php
                    $lastRaw = (string) ($terminal['last_seen_at'] ?? $terminal['updated_at'] ?? '');
                    $lastTs = strtotime($lastRaw);
                    $recent = $lastTs !== false && (time() - $lastTs) < 7 * 86400;
                    $statusKey = strtolower(trim((string) ($terminal['status'] ?? '')));
                    $statusText = $atakStatusLabel($statusKey, $recent);
                    $lastLabel = $dateTime($lastRaw);
                    $deviceName = trim((string) ($terminal['terminal_label'] ?? ''));
                    if ($deviceName === '' || preg_match('/^\d{2}-\d{4}-\d+$/', $deviceName)) {
                        $deviceName = 'Téléphone ATAK';
                    }
                    $badgeClass = $recent && $statusKey === 'active'
                        ? 'bg-emerald-100 text-emerald-800'
                        : 'bg-slate-200 text-slate-700';
                    ?>
                    <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="font-black text-slate-950"><?= $h($deviceName) ?></h3>
                            <span class="rounded-full px-2 py-1 text-xs font-bold <?= $h($badgeClass) ?>"><?= $h($statusText) ?></span>
                        </div>
                        <p class="mt-3 text-sm text-slate-600">
                            <?= $lastLabel !== ''
                                ? 'Dernière liaison connue : ' . $h($lastLabel)
                                : 'Aucune liaison enregistrée pour le moment.' ?>
                        </p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="<?= $h($cardClass) ?>">
        <h2 class="text-lg font-black text-slate-950">Aller plus loin</h2>
        <p class="mt-2 text-sm text-slate-600">Les pages du portail qui vous concernent, sans les écrans d’administration.</p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <?php
            $shortcuts = [
                ['label' => 'Ma fiche', 'hint' => 'Identité, grade et unité', 'href' => url('personnel/me')],
                ['label' => 'Mes démarches', 'hint' => 'Absences, élévation et documents', 'href' => url('personnel/mon-espace-rh')],
                ['label' => 'Événements', 'hint' => 'Manœuvres et inscriptions', 'href' => url('evenements')],
                ['label' => 'Carte ATAK', 'hint' => 'Situation tactique', 'href' => url('atak')],
                ['label' => 'Boîte de réception', 'hint' => $inboxUnread > 0 ? ($inboxUnread === 1 ? '1 message à lire' : $inboxUnread . ' messages à lire') : 'Messages de la communauté', 'href' => url('boite-reception')],
                ['label' => 'Mon compte', 'hint' => 'Portrait, sécurité et appareils', 'href' => url('account')],
            ];
            if ($onboardingRemaining !== []) {
                array_unshift($shortcuts, ['label' => 'Mon intégration', 'hint' => 'Étapes d’arrivée restantes', 'href' => url('mon-integration')]);
            }
            foreach ($shortcuts as $shortcut):
            ?>
                <a class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 transition hover:border-emerald-300 hover:bg-white" href="<?= $h((string) $shortcut['href']) ?>">
                    <p class="font-bold text-slate-950"><?= $h((string) $shortcut['label']) ?></p>
                    <p class="mt-1 text-sm text-slate-600"><?= $h((string) $shortcut['hint']) ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
</div>
