<?php

declare(strict_types=1);

/**
 * Portail missions — fiche récapitulatif.
 *
 * @var array<string, mixed> $mpDetail
 * @var string $mpSection
 */

$detail = is_array($mpDetail ?? null) ? $mpDetail : [];
$section = (string) ($mpSection ?? 'recapitulatif');
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$plan = is_array($detail['plan'] ?? null) ? $detail['plan'] : [];
$progress = is_array($detail['progress'] ?? null) ? $detail['progress'] : [];
$participants = is_array($detail['participants'] ?? null) ? $detail['participants'] : [];
$comms = is_array($detail['comms'] ?? null) ? $detail['comms'] : [];
$liaison = is_array($detail['liaison'] ?? null) ? $detail['liaison'] : [];
$cycle = is_array($detail['cycle'] ?? null) ? $detail['cycle'] : null;
$links = is_array($detail['links'] ?? null) ? $detail['links'] : [];
$counts = is_array($detail['counts'] ?? null) ? $detail['counts'] : [];
$planId = (int) ($detail['plan_id'] ?? 0);
$filled = (int) ($progress['filled'] ?? 1);
$total = max(1, (int) ($progress['total'] ?? 4));
$tone = (string) ($progress['tone'] ?? 'prep');

$baseUrl = url('back-office/missions/' . $planId);
$sectionUrl = static fn (string $vue): string => $baseUrl . '?vue=' . rawurlencode($vue);

$progressClass = match ($tone) {
    'done' => 'bo-mportal__progress--done',
    'prep' => 'bo-mportal__progress--prep',
    default => '',
};

$commsDot = match ((string) ($comms['overall'] ?? '')) {
    'linked' => 'is-ok',
    'delayed' => 'is-warn',
    'offline' => 'is-off',
    default => '',
};

$railItems = [
    'recapitulatif' => ['label' => 'Récapitulatif', 'icon' => 'doc'],
    'participants' => ['label' => 'Participants', 'icon' => 'people'],
    'atak' => ['label' => 'Communications ATAK', 'icon' => 'radio'],
    'liaisons' => ['label' => 'Liaisons', 'icon' => 'link'],
];

$iconSvg = static function (string $kind): string {
    return match ($kind) {
        'people' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'radio' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4.9 19.1C1 15.2 1 8.8 4.9 4.9"/><path d="M7.8 16.2c-2.3-2.3-2.3-6.1 0-8.5"/><circle cx="12" cy="12" r="2"/><path d="M16.2 7.8c2.3 2.3 2.3 6.1 0 8.5"/><path d="M19.1 4.9C23 8.8 23 15.1 19.1 19"/></svg>',
        'link' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
        default => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>',
    };
};
?>
<div class="bo-mportal" data-bo-mportal-detail>

<p class="bo-mportal__breadcrumb">
    <a href="<?= $h(url('back-office/missions')) ?>">Portail missions</a>
    · <?= $h($plan['title'] ?? 'Mission') ?>
</p>

<div class="bo-mportal__layout">
    <nav class="bo-mportal__rail" aria-label="Sections de la mission">
        <?php foreach ($railItems as $key => $item): ?>
            <a
                class="bo-mportal__rail-link"
                href="<?= $h($sectionUrl($key)) ?>"
                <?= $section === $key ? ' aria-current="page"' : '' ?>
            >
                <?= $iconSvg($item['icon']) ?>
                <?= $h($item['label']) ?>
            </a>
        <?php endforeach; ?>
        <a class="bo-mportal__rail-link bo-mportal__rail-action" href="<?= $h($links['planning'] ?? url('back-office/planification/' . $planId)) ?>">
            Ouvrir la planification
        </a>
    </nav>

    <div class="bo-mportal__main">
        <header class="bo-mportal__status ath-rise">
            <h1 class="bo-mportal__status-title"><?= $h($detail['status_label'] ?? ($progress['label'] ?? 'Mission')) ?></h1>
            <p class="bo-mportal__status-step">Étape <?= $h((string) $filled) ?> sur <?= $h((string) $total) ?></p>
            <div class="bo-mportal__progress <?= $h($progressClass) ?>" role="img" aria-label="Progression de la mission">
                <?php for ($i = 1; $i <= $total; $i++): ?>
                    <span class="bo-mportal__progress-seg<?= $i <= $filled ? ' is-filled' : '' ?>"></span>
                <?php endfor; ?>
            </div>
            <?php if (!empty($progress['next_label'])): ?>
                <p class="bo-mportal__next">Étape suivante : <strong><?= $h($progress['next_label']) ?></strong></p>
            <?php else: ?>
                <p class="bo-mportal__next">Cycle de planification achevé</p>
            <?php endif; ?>
        </header>

        <dl class="bo-mportal__infobar ath-rise">
            <div>
                <dt>Référence</dt>
                <dd><?= $h(($plan['mission_code'] ?? '') !== '' ? $plan['mission_code'] : ('#' . $planId)) ?></dd>
            </div>
            <div>
                <dt>Statut</dt>
                <dd><?= $h($detail['status_label'] ?? '') ?></dd>
            </div>
            <div>
                <dt>Carte</dt>
                <dd><?= $h($detail['map_label'] ?? '—') ?></dd>
            </div>
        </dl>

        <?php if ($section === 'recapitulatif'): ?>
            <section class="bo-mportal__section ath-rise" aria-labelledby="mp-id-title">
                <h2 id="mp-id-title" class="bo-mportal__section-head">Identification</h2>
                <div class="bo-mportal__section-body">
                    <dl class="bo-mportal__dl">
                        <div>
                            <dt>Titre</dt>
                            <dd><?= $h($plan['title'] ?? '—') ?></dd>
                        </div>
                        <div>
                            <dt>Opération</dt>
                            <dd><?= $h($plan['operation_name'] ?? '—') ?></dd>
                        </div>
                        <div>
                            <dt>Task force</dt>
                            <dd><?= $h($plan['task_force_name'] ?? '—') ?></dd>
                        </div>
                        <div>
                            <dt>DTG</dt>
                            <dd><?= $h($plan['dtg'] ?? '—') ?></dd>
                        </div>
                        <div>
                            <dt>Classification</dt>
                            <dd><?= $h($plan['classification'] ?? '—') ?></dd>
                        </div>
                    </dl>
                    <?php if (trim((string) ($detail['mission_sentence'] ?? '')) !== ''): ?>
                        <p class="bo-mportal__section-lead" style="margin-top:1rem;"><?= $h($detail['mission_sentence']) ?></p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="bo-mportal__section ath-rise" aria-labelledby="mp-eff-title">
                <h2 id="mp-eff-title" class="bo-mportal__section-head">Effectifs prévus</h2>
                <div class="bo-mportal__section-body">
                    <dl class="bo-mportal__dl">
                        <div>
                            <dt>Postes autorisés</dt>
                            <dd><?= $h((string) ($counts['auth'] ?? 0)) ?></dd>
                        </div>
                        <div>
                            <dt>Affectés</dt>
                            <dd><?= $h((string) ($counts['assigned'] ?? 0)) ?></dd>
                        </div>
                        <div>
                            <dt>Présents</dt>
                            <dd><?= $h((string) ($counts['present'] ?? 0)) ?></dd>
                        </div>
                        <div>
                            <dt>Participants listés</dt>
                            <dd><?= $h((string) count($participants)) ?></dd>
                        </div>
                    </dl>
                </div>
            </section>

            <?php if ($cycle !== null): ?>
            <section class="bo-mportal__section ath-rise" aria-labelledby="mp-cycle-title">
                <h2 id="mp-cycle-title" class="bo-mportal__section-head">Cycle théâtre</h2>
                <div class="bo-mportal__section-body">
                    <dl class="bo-mportal__dl">
                        <div>
                            <dt>Mission PC</dt>
                            <dd><?= $h($cycle['title'] ?? '—') ?></dd>
                        </div>
                        <div>
                            <dt>Statut</dt>
                            <dd><?= $h($cycle['status_label'] ?? '—') ?></dd>
                        </div>
                    </dl>
                    <div class="bo-mportal__actions" style="margin-top:1rem;">
                        <a class="bo-mportal__btn bo-mportal__btn--ghost" href="<?= $h($cycle['portal_cycle_url'] ?? ($links['cycle'] ?? '#')) ?>">Ouvrir le cycle</a>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <section class="bo-mportal__section ath-rise" aria-labelledby="mp-snap-title">
                <h2 id="mp-snap-title" class="bo-mportal__section-head">État des communications</h2>
                <div class="bo-mportal__section-body">
                    <p class="bo-mportal__section-lead">
                        <span class="bo-mportal__comms-dot <?= $h($commsDot) ?>" aria-hidden="true"></span>
                        <?= $h($comms['overall_label'] ?? '') ?>
                        · <?= $h((string) (($comms['linked'] ?? 0) + ($comms['delayed'] ?? 0))) ?> contact(s) en liaison
                    </p>
                    <div class="bo-mportal__actions">
                        <a class="bo-mportal__btn bo-mportal__btn--ghost" href="<?= $h($sectionUrl('atak')) ?>">Détail ATAK</a>
                        <a class="bo-mportal__btn bo-mportal__btn--ghost" href="<?= $h($sectionUrl('liaisons')) ?>">Liaisons</a>
                    </div>
                </div>
            </section>

        <?php elseif ($section === 'participants'): ?>
            <section class="bo-mportal__section ath-rise" aria-labelledby="mp-part-title">
                <h2 id="mp-part-title" class="bo-mportal__section-head">Participants</h2>
                <div class="bo-mportal__section-body">
                    <p class="bo-mportal__section-lead">Organisation de combat et affectations du plan.</p>
                    <?php if ($participants === []): ?>
                        <p class="bo-mportal__empty">Aucun poste dans l’organisation pour le moment.</p>
                    <?php else: ?>
                        <ul class="bo-mportal__list">
                            <?php foreach ($participants as $p): ?>
                                <li>
                                    <div>
                                        <p class="bo-mportal__list-title"><?= $h($p['callsign'] ?? '') ?><?= ($p['function_label'] ?? '') !== '' ? ' — ' . $h($p['function_label']) : '' ?></p>
                                        <p class="bo-mportal__list-meta">
                                            <?= $h($p['element_label'] ?? '') ?>
                                            · <?= $h($p['person_label'] ?? 'Vacant') ?>
                                            <?php if (trim((string) ($p['arma_status'] ?? '')) !== ''): ?>
                                                · <?= $h($p['arma_label']) ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <span class="bo-mportal__chip"><?= $h($p['presence_label'] ?? '') ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <div class="bo-mportal__actions" style="margin-top:1rem;">
                        <a class="bo-mportal__btn bo-mportal__btn--ghost" href="<?= $h($links['planning_org'] ?? '#') ?>">Modifier l’organisation</a>
                    </div>
                </div>
            </section>

        <?php elseif ($section === 'atak'): ?>
            <section class="bo-mportal__section ath-rise" aria-labelledby="mp-atak-title">
                <h2 id="mp-atak-title" class="bo-mportal__section-head">Communications ATAK</h2>
                <div class="bo-mportal__section-body">
                    <p class="bo-mportal__section-lead">
                        <span class="bo-mportal__comms-dot <?= $h($commsDot) ?>" aria-hidden="true"></span>
                        <?= $h($comms['overall_label'] ?? '') ?>
                    </p>
                    <dl class="bo-mportal__dl">
                        <div>
                            <dt>Serveur TAK</dt>
                            <dd><?= $h($comms['tak_label'] ?? '—') ?> — <?= $h($comms['tak_host'] ?? '') ?></dd>
                        </div>
                        <div>
                            <dt>Carte</dt>
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
                        <ul class="bo-mportal__list" style="margin-top:0.85rem;">
                            <?php foreach ($contacts as $c): ?>
                                <li>
                                    <div>
                                        <p class="bo-mportal__list-title"><?= $h($c['call_sign'] ?? '') ?></p>
                                        <p class="bo-mportal__list-meta"><?= $h($c['status_label'] ?? '') ?></p>
                                    </div>
                                    <span class="bo-mportal__chip <?= ($c['status'] ?? '') === 'delayed' ? 'bo-mportal__chip--warn' : 'bo-mportal__chip--done' ?>"><?= $h($c['status_label'] ?? '') ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="bo-mportal__empty">Aucun contact en liaison sur la carte liée à cette mission.</p>
                    <?php endif; ?>
                    <div class="bo-mportal__actions" style="margin-top:1rem;">
                        <a class="bo-mportal__btn bo-mportal__btn--ghost" href="<?= $h($links['operators'] ?? '#') ?>">Sessions &amp; connexions</a>
                        <a class="bo-mportal__btn bo-mportal__btn--ghost" href="<?= $h($links['atak_hub'] ?? '#') ?>">Poste de situation</a>
                        <a class="bo-mportal__btn bo-mportal__btn--ghost" href="<?= $h($links['map'] ?? '#') ?>">Carte</a>
                    </div>
                </div>
            </section>

        <?php else: /* liaisons */ ?>
            <section class="bo-mportal__section ath-rise" aria-labelledby="mp-liai-title">
                <h2 id="mp-liai-title" class="bo-mportal__section-head">Liaisons</h2>
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
                                        <p class="bo-mportal__list-title"><?= $h($g['label'] ?? '') ?></p>
                                        <p class="bo-mportal__list-meta"><?= $h($g['status_label'] ?? '') ?></p>
                                    </div>
                                    <span class="bo-mportal__chip"><?= $h($g['status_label'] ?? '') ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="bo-mportal__empty">Aucune passerelle enregistrée.</p>
                    <?php endif; ?>
                    <ul class="bo-mportal__list" style="margin-top:0.85rem;">
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
        <?php endif; ?>
    </div>
</div>

</div>
