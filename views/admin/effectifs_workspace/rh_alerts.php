<?php
declare(strict_types=1);

$summary = is_array($rhAlertSummary ?? null) ? $rhAlertSummary : ['items' => [], 'total' => 0];
$items = is_array($summary['items'] ?? null) ? $summary['items'] : [];
$inactive = is_array($rhInactiveMembers ?? null) ? $rhInactiveMembers : [];
$absences = is_array($rhProlongedAbsences ?? null) ? $rhProlongedAbsences : [];
$inactivityDays = (int) ($rhInactivityDays ?? 45);
$absenceDays = (int) ($rhProlongedAbsenceDays ?? 14);
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$total = (int) ($summary['total'] ?? 0);
?>
<div class="eff-catalog">
    <div class="eff-catalog__head">
        <div class="min-w-0">
            <p class="eff-catalog__kicker">Dossier individuel</p>
            <h1 class="eff-catalog__title">Alertes RH</h1>
            <p class="eff-catalog__lead">
                Vue consolidée : qualifications à recyclage, absences prolongées, inactivité,
                mobilité en attente et postes ORBAT sous-pourvus.
            </p>
        </div>
        <div class="eff-catalog__tools">
            <span class="eff-catalog__btn"><?= $total ?> signalement<?= $total > 1 ? 's' : '' ?></span>
        </div>
    </div>

    <div class="eff-rail-alerts" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(11rem,1fr));gap:.75rem;margin-bottom:1.5rem">
        <?php foreach ($items as $item): ?>
            <?php
            $count = (int) ($item['count'] ?? 0);
            $href = (string) ($item['href'] ?? effectifs_workspace_url('alertes'));
            ?>
            <a href="<?= $h($href) ?>" class="eff-alert-chip" style="display:block;padding:1rem;text-decoration:none">
                <strong style="font-size:1.4rem"><?= $count ?></strong>
                <span style="display:block;margin-top:.35rem"><?= $h((string) ($item['label'] ?? '')) ?></span>
                <span class="eff-sheets__meta" style="display:block;margin-top:.25rem"><?= $h((string) ($item['severity'] ?? '')) ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <div style="display:grid;gap:1.5rem;grid-template-columns:repeat(auto-fit,minmax(18rem,1fr))">
        <section>
            <h2 class="eff-catalog__title" style="font-size:1.05rem">Sans activité (≥ <?= $inactivityDays ?> j)</h2>
            <?php if ($inactive === []): ?>
                <p class="eff-sheets__meta">Aucun membre actif concerné.</p>
            <?php else: ?>
                <ul class="eff-sheets__meta" style="list-style:none;padding:0;margin:.75rem 0 0">
                    <?php foreach ($inactive as $m): ?>
                        <?php $uid = (int) ($m['id'] ?? 0); ?>
                        <li style="margin:.4rem 0">
                            <a href="<?= $h(effectifs_workspace_url('membres/' . $uid)) ?>"><?= $h(trim((string) ($m['display_name'] ?? '')) ?: (string) ($m['email'] ?? 'Membre')) ?></a>
                            — <?= $h((string) ($m['last_login_at'] ?? 'jamais')) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        <section>
            <h2 class="eff-catalog__title" style="font-size:1.05rem">Absences prolongées (≥ <?= $absenceDays ?> j)</h2>
            <?php if ($absences === []): ?>
                <p class="eff-sheets__meta">Aucune absence prolongée en cours.</p>
            <?php else: ?>
                <ul class="eff-sheets__meta" style="list-style:none;padding:0;margin:.75rem 0 0">
                    <?php foreach ($absences as $a): ?>
                        <?php $uid = (int) ($a['user_id'] ?? 0); ?>
                        <li style="margin:.4rem 0">
                            <a href="<?= $h(effectifs_workspace_url('membres/' . $uid)) ?>"><?= $h(trim((string) ($a['user_display_name'] ?? '')) ?: (string) ($a['user_email'] ?? 'Membre')) ?></a>
                            — depuis <?= $h((string) ($a['starts_on'] ?? '')) ?>
                            (<?= (int) ($a['days_open'] ?? 0) ?> j)
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>
