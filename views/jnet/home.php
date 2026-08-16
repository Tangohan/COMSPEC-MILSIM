<?php
/** Accueil JNET — situation d’unité */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$unitName = (string) ($unitName ?? 'Unité');
$opsStatus = (string) ($opsStatus ?? 'GREEN');
$stats = is_array($stats ?? null) ? $stats : [];
$commandStaff = is_array($commandStaff ?? null) ? $commandStaff : [];
$priorityTargets = is_array($priorityTargets ?? null) ? $priorityTargets : [];
$currentOps = is_array($currentOps ?? null) ? $currentOps : [];
$intelFeed = is_array($intelFeed ?? null) ? $intelFeed : [];
$personnelPreview = is_array($personnelPreview ?? null) ? $personnelPreview : [];
$viewerLens = (string) ($viewerLens ?? 'operator');
$targetsTotal = (int) ($targetsTotal ?? count($priorityTargets));
$statusClass = match (strtoupper($opsStatus)) {
    'RED' => 'is-red',
    'AMBER' => 'is-amber',
    default => 'is-green',
};
$lensLabel = match ($viewerLens) {
    'command' => 'Vue commandement',
    'intel' => 'Vue renseignement',
    'leader' => 'Vue chef d’équipe',
    default => 'Vue opérateur',
};

$face = static function (array $p) use ($h): string {
    $photo = $p['photo'] ?? null;
    $initials = $h((string) ($p['initials'] ?? '?'));
    if (is_string($photo) && $photo !== '') {
        return '<img src="' . $h($photo) . '" alt="">';
    }

    return '<span>' . $initials . '</span>';
};
?>
<section class="jnet-hero-unit">
    <div class="jnet-hero-unit__row">
        <div class="jnet-hero-unit__id">
            <div class="jnet-unit-badge" aria-hidden="true"><?= $h(strtoupper(substr(preg_replace('/\s+/', '', $unitName) ?: 'U', 0, 3))) ?></div>
            <div>
                <p class="jnet-kicker"><?= $h($lensLabel) ?></p>
                <h1 class="jnet-hero-unit__name"><?= $h($unitName) ?></h1>
                <p class="jnet-hero-unit__motto"><?= $h((string) ($unitMotto ?? '')) ?></p>
            </div>
        </div>
        <div class="jnet-statstrip">
            <div>
                <span>État opérationnel</span>
                <strong class="jnet-status <?= $statusClass ?>"><?= $h($opsStatus) ?></strong>
            </div>
            <div>
                <span>Personnel</span>
                <strong><?= (int) ($stats['personnelPresent'] ?? 0) ?>/<?= (int) ($stats['personnelAuth'] ?? 0) ?></strong>
            </div>
            <div>
                <span>Ops actives</span>
                <strong><?= (int) ($stats['activeOps'] ?? 0) ?></strong>
            </div>
            <div>
                <span>Cibles prioritaires</span>
                <strong><?= (int) ($stats['priorityTargets'] ?? 0) ?></strong>
            </div>
        </div>
    </div>
</section>

<div class="jnet-home-grid">
    <section class="jnet-panel">
        <div class="jnet-panel__head">
            <h2>Commandement d’unité</h2>
            <a class="jnet-btn" href="<?= $h(url('jnet/unite')) ?>">Fiche unité</a>
        </div>
        <div class="jnet-panel__body jnet-face-list">
            <?php if ($commandStaff === []): ?>
                <p class="jnet-empty">Aucun cadre renseigné pour le moment.</p>
            <?php endif; ?>
            <?php foreach ($commandStaff as $p): ?>
                <a class="jnet-face-row" href="<?= $h((string) ($p['href'] ?? '#')) ?>">
                    <div class="jnet-avatar jnet-avatar--lg"><?= $face($p) ?></div>
                    <div>
                        <strong><?= $h((string) ($p['name'] ?? '')) ?></strong>
                        <span><?= $h((string) ($p['function'] ?? '')) ?></span>
                        <em><?= $h((string) ($p['meta_line'] ?? trim(($p['grade'] ?? '') . ' · ' . ($p['callsign'] ?? ''), ' ·'))) ?></em>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="jnet-panel">
        <div class="jnet-panel__head">
            <h2>Renseignement prioritaire</h2>
            <a class="jnet-btn" href="<?= $h(url('jnet/cibles')) ?>">Voir tout (<?= $targetsTotal ?>)</a>
        </div>
        <div class="jnet-panel__body jnet-face-list">
            <?php if ($priorityTargets === []): ?>
                <p class="jnet-empty">Aucune cible prioritaire ouverte.</p>
            <?php endif; ?>
            <?php foreach ($priorityTargets as $t): ?>
                <a class="jnet-face-row jnet-face-row--target" href="<?= $h((string) ($t['href'] ?? '#')) ?>"
                   title="<?= $h(trim(($t['alias'] ?? '') . ' · ' . ($t['org'] ?? '') . ' · ' . ($t['lastKnown'] ?? ''))) ?>">
                    <div class="jnet-avatar jnet-avatar--lg jnet-avatar--target"><?= $face(['initials' => substr((string) ($t['name'] ?? '?'), 0, 2), 'photo' => $t['photo'] ?? null]) ?></div>
                    <div>
                        <strong><?= $h((string) ($t['name'] ?? '')) ?> <small><?= $h((string) ($t['code'] ?? '')) ?></small></strong>
                        <span class="jnet-prio jnet-prio--<?= strtolower((string) ($t['priority'] ?? 'low')) ?>"><?= $h((string) ($t['priority'] ?? '')) ?></span>
                        <em>Confiance <?= (int) ($t['confidence'] ?? 0) ?>% · <?= $h((string) ($t['lastKnown'] ?? '')) ?></em>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="jnet-panel">
        <div class="jnet-panel__head">
            <h2>Opérations en cours</h2>
            <a class="jnet-btn" href="<?= $h(url('jnet/operations')) ?>">Mission board</a>
        </div>
        <div class="jnet-panel__body">
            <?php foreach ($currentOps as $op): ?>
                <a class="jnet-op-row" href="<?= $h(url('jnet/operations/' . (int) ($op['id'] ?? 0))) ?>">
                    <strong><?= $h((string) ($op['title'] ?? '')) ?></strong>
                    <span class="jnet-badge <?= ($op['state_key'] ?? '') === 'active' ? 'jnet-badge--ok' : 'jnet-badge--watch' ?>"><?= $h((string) ($op['state'] ?? '')) ?></span>
                </a>
            <?php endforeach; ?>
            <?php if ($currentOps === []): ?>
                <div class="jnet-empty">
                    <p>Aucune opération engagée.</p>
                    <p>Les missions ouvertes sur le tableau opérationnel apparaîtront ici.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="jnet-panel">
        <div class="jnet-panel__head">
            <h2>Flux de renseignement</h2>
            <a class="jnet-btn" href="<?= $h(url('jnet/renseignement')) ?>">Tableau intel</a>
        </div>
        <div class="jnet-panel__body jnet-feed">
            <?php if ($intelFeed === []): ?>
                <p class="jnet-empty">Aucun événement récent.</p>
            <?php endif; ?>
            <?php foreach ($intelFeed as $ev): ?>
                <a class="jnet-feed__item" href="<?= $h((string) ($ev['href'] ?? '#')) ?>">
                    <time><?= $h((string) ($ev['time'] ?? '')) ?></time>
                    <div>
                        <strong><?= $h((string) ($ev['kind'] ?? '')) ?> · <?= $h((string) ($ev['title'] ?? '')) ?></strong>
                        <span><?= $h((string) ($ev['detail'] ?? '')) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<section class="jnet-panel jnet-panel--faces">
    <div class="jnet-panel__head">
        <h2>Personnel — aperçu</h2>
        <a class="jnet-btn" href="<?= $h(url('jnet/personnel')) ?>">Annuaire complet</a>
    </div>
    <div class="jnet-panel__body">
        <div class="jnet-gallery">
            <?php foreach ($personnelPreview as $p): ?>
                <a class="jnet-person-card" href="<?= $h((string) ($p['href'] ?? '#')) ?>">
                    <div class="jnet-avatar jnet-avatar--xl"><?= $face($p) ?></div>
                    <strong><?= $h((string) ($p['name'] ?? '')) ?></strong>
                    <span><?= $h((string) ($p['grade'] ?? '')) ?></span>
                    <span><?= $h((string) ($p['unit'] ?? '')) ?> · <?= $h((string) ($p['function'] ?? '')) ?></span>
                    <em class="jnet-duty"><?= $h((string) ($p['duty_label'] ?? '')) ?></em>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
