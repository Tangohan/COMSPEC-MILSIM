<?php

declare(strict_types=1);

/**
 * Portail missions — vue d’ensemble.
 *
 * @var array<string, mixed> $mpHub
 */

$hub = is_array($mpHub ?? null) ? $mpHub : [];
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$plans = is_array($hub['plans'] ?? null) ? $hub['plans'] : [];
$cycles = is_array($hub['cycles'] ?? null) ? $hub['cycles'] : [];
$comms = is_array($hub['comms'] ?? null) ? $hub['comms'] : [];
$liaison = is_array($hub['liaison'] ?? null) ? $hub['liaison'] : [];
$kpis = is_array($hub['kpis'] ?? null) ? $hub['kpis'] : [];
$focus = is_array($hub['focus'] ?? null) ? $hub['focus'] : null;
$maps = is_array($hub['maps'] ?? null) ? $hub['maps'] : [];
$mapId = (int) ($hub['map_id'] ?? 1);
$flashError = \App\Core\Session::getFlash('error');
$flashOk = \App\Core\Session::getFlash('success');

$chipClass = static function (string $tone): string {
    return match ($tone) {
        'live' => 'bo-mportal__chip--live',
        'done' => 'bo-mportal__chip--done',
        'progress' => 'bo-mportal__chip--live',
        default => 'bo-mportal__chip--prep',
    };
};

$progressTone = static function (string $tone): string {
    return match ($tone) {
        'done' => 'bo-mportal__progress--done',
        'live', 'progress' => '',
        default => 'bo-mportal__progress--prep',
    };
};

$commsDot = match ((string) ($comms['overall'] ?? '')) {
    'linked' => 'is-ok',
    'delayed' => 'is-warn',
    'offline' => 'is-off',
    default => '',
};
?>
<div class="bo-mportal" data-bo-mportal>

<?php if ($flashOk): ?>
    <div class="ath-banner-warn ath-rise" style="background:#e6f8f0;border-color:#bfe9d8;" role="status">
        <div class="ath-banner-warn__text" style="color:#0b6b47;"><?= $h($flashOk) ?></div>
    </div>
<?php endif; ?>
<?php if ($flashError): ?>
    <div class="ath-banner-warn ath-rise" role="alert">
        <div class="ath-banner-warn__text"><?= $h($flashError) ?></div>
    </div>
<?php endif; ?>

<div class="bo-mportal__toolbar">
    <?php if (count($maps) > 1): ?>
    <form method="get" action="<?= $h(url('back-office/missions')) ?>">
        <label for="mp-carte">Carte suivie</label>
        <select id="mp-carte" name="carte" onchange="this.form.submit()">
            <?php foreach ($maps as $m): ?>
                <option value="<?= (int) ($m['id'] ?? 0) ?>"<?= (int) ($m['id'] ?? 0) === $mapId ? ' selected' : '' ?>><?= $h($m['label'] ?? '') ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php endif; ?>
    <div class="bo-mportal__actions">
        <a class="bo-mportal__btn" href="<?= $h(url('back-office/planification')) ?>">Planifier une mission</a>
        <a class="bo-mportal__btn bo-mportal__btn--ghost" href="<?= $h(url('back-office/atak/cycle-mission')) ?>">Cycle de mission</a>
        <a class="bo-mportal__btn bo-mportal__btn--ghost" href="<?= $h(url('back-office/atak')) ?>">Poste de situation</a>
    </div>
</div>

<?php if ($kpis !== []): ?>
<div class="bo-mportal__kpis ath-rise" aria-label="Indicateurs">
    <?php foreach ($kpis as $kpi): ?>
        <article class="bo-mportal__kpi">
            <p class="bo-mportal__kpi-label"><?= $h($kpi['label'] ?? '') ?></p>
            <p class="bo-mportal__kpi-value"><?= $h($kpi['value'] ?? '—') ?></p>
            <p class="bo-mportal__kpi-note"><?= $h($kpi['note'] ?? '') ?></p>
        </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($focus !== null):
    $progress = is_array($focus['progress'] ?? null) ? $focus['progress'] : [];
    $filled = (int) ($progress['filled'] ?? 1);
    $total = max(1, (int) ($progress['total'] ?? 3));
    $tone = (string) ($progress['tone'] ?? 'prep');
    ?>
<section class="bo-mportal__status ath-rise" aria-labelledby="mp-focus-title">
    <h2 id="mp-focus-title" class="bo-mportal__status-title"><?= $h($progress['label'] ?? ($focus['status_label'] ?? 'Mission')) ?></h2>
    <p class="bo-mportal__status-step">Étape <?= $h((string) $filled) ?> sur <?= $h((string) $total) ?> — <?= $h($focus['title'] ?? '') ?></p>
    <div class="bo-mportal__progress <?= $h($progressTone($tone)) ?>" role="img" aria-label="Progression : étape <?= $h((string) $filled) ?> sur <?= $h((string) $total) ?>">
        <?php for ($i = 1; $i <= $total; $i++): ?>
            <span class="bo-mportal__progress-seg<?= $i <= $filled ? ' is-filled' : '' ?>"></span>
        <?php endfor; ?>
    </div>
    <?php if (!empty($progress['next_label'])): ?>
        <p class="bo-mportal__next">Étape suivante : <strong><?= $h($progress['next_label']) ?></strong></p>
    <?php endif; ?>
    <div class="bo-mportal__meta">
        <div class="bo-mportal__meta-item">
            <p class="bo-mportal__meta-label">Référence</p>
            <p class="bo-mportal__meta-value"><?= $h(($focus['code'] ?? '') !== '' ? $focus['code'] : ('#' . (int) ($focus['id'] ?? 0))) ?></p>
        </div>
        <div class="bo-mportal__meta-item">
            <p class="bo-mportal__meta-label">Statut</p>
            <p class="bo-mportal__meta-value"><?= $h($focus['status_label'] ?? '') ?></p>
        </div>
        <div class="bo-mportal__meta-item">
            <p class="bo-mportal__meta-label">Type</p>
            <p class="bo-mportal__meta-value"><?= ($focus['kind'] ?? '') === 'cycle' ? 'Cycle de mission' : 'Plan de mission' ?></p>
        </div>
        <div class="bo-mportal__meta-item">
            <p class="bo-mportal__meta-label">Ouvrir</p>
            <p class="bo-mportal__meta-value"><a href="<?= $h($focus['url'] ?? '#') ?>">Voir le récapitulatif</a></p>
        </div>
    </div>
</section>
<?php endif; ?>

<div class="bo-mportal__panel-grid">
    <section class="bo-mportal__section ath-rise" aria-labelledby="mp-comms-title">
        <h2 id="mp-comms-title" class="bo-mportal__section-head">Communications ATAK</h2>
        <div class="bo-mportal__section-body">
            <p class="bo-mportal__section-lead">
                <span class="bo-mportal__comms-dot <?= $h($commsDot) ?>" aria-hidden="true"></span>
                <?= $h($comms['overall_label'] ?? 'Aucune activité') ?>
                · Serveur TAK : <?= $h($comms['tak_label'] ?? '—') ?>
                <?php if (!empty($comms['tak_host'])): ?>
                    (<?= $h($comms['tak_host']) ?>)
                <?php endif; ?>
            </p>
            <dl class="bo-mportal__dl">
                <div>
                    <dt>Carte suivie</dt>
                    <dd><?= $h($comms['map_label'] ?? '—') ?></dd>
                </div>
                <div>
                    <dt>En liaison</dt>
                    <dd><?= $h((string) ($comms['linked'] ?? 0)) ?></dd>
                </div>
                <div>
                    <dt>Liaison différée</dt>
                    <dd><?= $h((string) ($comms['delayed'] ?? 0)) ?></dd>
                </div>
                <div>
                    <dt>Hors liaison</dt>
                    <dd><?= $h((string) ($comms['offline'] ?? 0)) ?></dd>
                </div>
            </dl>
            <?php
            $contacts = is_array($comms['contacts'] ?? null) ? $comms['contacts'] : [];
            if ($contacts !== []):
                ?>
                <ul class="bo-mportal__list" style="margin-top:0.75rem;">
                    <?php foreach ($contacts as $c): ?>
                        <li>
                            <div>
                                <p class="bo-mportal__list-title"><?= $h($c['call_sign'] ?? 'Contact') ?></p>
                                <p class="bo-mportal__list-meta"><?= $h($c['status_label'] ?? '') ?></p>
                            </div>
                            <span class="bo-mportal__chip <?= ($c['status'] ?? '') === 'delayed' ? 'bo-mportal__chip--warn' : 'bo-mportal__chip--done' ?>"><?= $h($c['status_label'] ?? '') ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="bo-mportal__empty">Aucun contact actuellement en liaison sur cette carte.</p>
            <?php endif; ?>
            <div class="bo-mportal__actions" style="margin-top:1rem;">
                <a class="bo-mportal__btn bo-mportal__btn--ghost" href="<?= $h(url('back-office/atak/operateurs') . '?carte=' . $mapId) ?>">Sessions &amp; connexions</a>
                <a class="bo-mportal__btn bo-mportal__btn--ghost" href="<?= $h(url('atak')) ?>">Ouvrir la carte</a>
            </div>
        </div>
    </section>

    <section class="bo-mportal__section ath-rise" aria-labelledby="mp-liaison-title">
        <h2 id="mp-liaison-title" class="bo-mportal__section-head">Liaisons</h2>
        <div class="bo-mportal__section-body">
            <p class="bo-mportal__section-lead">
                Passerelles actives : <?= $h((string) ($liaison['active_count'] ?? 0)) ?>
                · En attente : <?= $h((string) ($liaison['pending_count'] ?? 0)) ?>
            </p>
            <?php
            $gateways = is_array($liaison['gateways'] ?? null) ? $liaison['gateways'] : [];
            if ($gateways !== []):
                ?>
                <ul class="bo-mportal__list">
                    <?php foreach ($gateways as $g): ?>
                        <li>
                            <div>
                                <p class="bo-mportal__list-title"><?= $h($g['label'] ?? 'Passerelle') ?></p>
                                <p class="bo-mportal__list-meta"><?= $h($g['status_label'] ?? '') ?></p>
                            </div>
                            <span class="bo-mportal__chip"><?= $h($g['status_label'] ?? '') ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="bo-mportal__empty">Aucune passerelle carte pour le moment.</p>
            <?php endif; ?>
            <ul class="bo-mportal__list" style="margin-top:0.75rem;">
                <?php foreach ((array) ($liaison['links'] ?? []) as $link): ?>
                    <li>
                        <div>
                            <p class="bo-mportal__list-title"><a href="<?= $h($link['href'] ?? '#') ?>"><?= $h($link['label'] ?? '') ?></a></p>
                            <p class="bo-mportal__list-meta"><?= $h($link['help'] ?? '') ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
</div>

<section class="bo-mportal__section ath-rise" aria-labelledby="mp-plans-title">
    <h2 id="mp-plans-title" class="bo-mportal__section-head">Plans de mission</h2>
    <div class="bo-mportal__section-body">
        <?php if (empty($hub['plans_ready'])): ?>
            <p class="bo-mportal__empty">La planification n’est pas encore disponible sur cette communauté.</p>
        <?php elseif ($plans === []): ?>
            <p class="bo-mportal__empty">Aucun plan pour le moment. Créez-en un depuis la planification.</p>
            <div class="bo-mportal__actions" style="margin-top:0.75rem;">
                <a class="bo-mportal__btn" href="<?= $h(url('back-office/planification')) ?>">Ouvrir la planification</a>
            </div>
        <?php else: ?>
            <ul class="bo-mportal__list">
                <?php foreach ($plans as $plan):
                    $prog = is_array($plan['progress'] ?? null) ? $plan['progress'] : [];
                    $tone = (string) ($prog['tone'] ?? 'prep');
                    ?>
                    <li>
                        <div>
                            <p class="bo-mportal__list-title">
                                <a href="<?= $h($plan['portal_url'] ?? '#') ?>"><?= $h($plan['title'] ?? 'Mission') ?></a>
                            </p>
                            <p class="bo-mportal__list-meta">
                                <?= $h($plan['mission_code'] ?? '') ?>
                                · <?= $h((string) ($plan['assigned_count'] ?? 0)) ?> / <?= $h((string) ($plan['auth_count'] ?? 0)) ?> postes
                                · Étape <?= $h((string) ($prog['filled'] ?? 1)) ?> / <?= $h((string) ($prog['total'] ?? 4)) ?>
                            </p>
                        </div>
                        <span class="bo-mportal__chip <?= $h($chipClass($tone)) ?>"><?= $h($plan['status_label'] ?? '') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>

<?php if ($cycles !== []): ?>
<section class="bo-mportal__section ath-rise" aria-labelledby="mp-cycles-title">
    <h2 id="mp-cycles-title" class="bo-mportal__section-head">Cycles de mission (poste de commandement)</h2>
    <div class="bo-mportal__section-body">
        <ul class="bo-mportal__list">
            <?php foreach ($cycles as $cycle):
                $prog = is_array($cycle['progress'] ?? null) ? $cycle['progress'] : [];
                $tone = (string) ($prog['tone'] ?? 'prep');
                ?>
                <li>
                    <div>
                        <p class="bo-mportal__list-title">
                            <a href="<?= $h($cycle['portal_cycle_url'] ?? '#') ?>"><?= $h($cycle['title'] ?? 'Mission') ?></a>
                        </p>
                        <p class="bo-mportal__list-meta">
                            Étape <?= $h((string) ($prog['filled'] ?? 1)) ?> / <?= $h((string) ($prog['total'] ?? 3)) ?>
                        </p>
                    </div>
                    <span class="bo-mportal__chip <?= $h($chipClass($tone)) ?>"><?= $h($cycle['status_label'] ?? '') ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

</div>
