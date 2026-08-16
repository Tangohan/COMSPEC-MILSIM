<?php
declare(strict_types=1);
/**
 * JNET — Tableau d’unité.
 * Fiche d’identité, disponibilité, chaîne de commandement, ordre de bataille,
 * tableau des sous-unités, moyens et journal.
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$unitName = (string) ($unitName ?? 'Unité');
$unitMotto = trim((string) ($unitMotto ?? ''));
$identity = is_array($unitIdentity ?? null) ? $unitIdentity : [];
$stats = is_array($stats ?? null) ? $stats : [];
$readiness = is_array($readiness ?? null) ? $readiness : ['overall' => 0, 'label' => '—', 'components' => []];
$duty = is_array($dutyBreakdown ?? null) ? $dutyBreakdown : [];
$keyPosts = is_array($keyPosts ?? null) ? $keyPosts : [];
$subUnits = is_array($subUnits ?? null) ? $subUnits : [];
$specialities = is_array($specialities ?? null) ? $specialities : [];
$assets = is_array($unitAssets ?? null) ? $unitAssets : [];
$recentEvents = is_array($recentEvents ?? null) ? $recentEvents : [];
$taskings = is_array($unitTaskings ?? null) ? $unitTaskings : [];
$hasRealOrbat = (bool) ($hasRealOrbat ?? false);
$posture = strtoupper((string) ($opsStatus ?? 'GREEN'));
$postureClass = match ($posture) {
    'RED' => 'is-red',
    'AMBER' => 'is-amber',
    default => 'is-green',
};
$badge = strtoupper(substr(preg_replace('/\s+/', '', $unitName) ?: 'U', 0, 3));

$gauge = static function (int $value): string {
    return match (true) {
        $value >= 85 => 'is-green',
        $value >= 65 => 'is-amber',
        default => 'is-red',
    };
};
$present = (int) ($stats['personnelPresent'] ?? 0);
$authorized = max(1, (int) ($stats['personnelAuth'] ?? 1));
$fillRate = (int) round(($present / $authorized) * 100);
?>
<section class="jnet-unit-head">
    <div class="jnet-unit-head__id">
        <div class="jnet-unit-badge jnet-unit-badge--lg" aria-hidden="true"><?= $h($badge) ?></div>
        <div class="jnet-unit-head__names">
            <p class="jnet-kicker">Fiche d’unité · <?= $h((string) ($identity['code'] ?? '—')) ?></p>
            <h1><?= $h($unitName) ?></h1>
            <?php if ($unitMotto !== ''): ?>
                <p class="jnet-unit-head__motto">« <?= $h($unitMotto) ?> »</p>
            <?php endif; ?>
            <div class="jnet-unit-head__chips">
                <span class="jnet-status <?= $h($postureClass) ?>">Posture <?= $h($posture) ?></span>
                <span class="jnet-pill"><?= $h((string) ($readiness['label'] ?? '—')) ?></span>
                <span class="jnet-pill"><?= (int) count($subUnits) ?> éléments subordonnés</span>
            </div>
        </div>
    </div>

    <dl class="jnet-unit-head__facts">
        <div><dt>Rattachement</dt><dd><?= $h((string) ($identity['higher'] ?? '—')) ?></dd></div>
        <div><dt>Implantation</dt><dd><?= $h((string) ($identity['garrison'] ?? '—')) ?></dd></div>
        <div><dt>Activée le</dt><dd><?= $h((string) ($identity['activated'] ?? '—')) ?></dd></div>
        <div><dt>Réseau</dt><dd><?= $h((string) ($identity['net'] ?? '—')) ?></dd></div>
    </dl>
</section>

<section class="jnet-metrics" aria-label="Indicateurs de l’unité">
    <article class="jnet-metric">
        <p class="jnet-metric__label">Effectif tenu</p>
        <p class="jnet-metric__value"><?= $present ?><small>/<?= $authorized ?></small></p>
        <div class="jnet-gauge"><i class="<?= $h($gauge($fillRate)) ?>" style="--w:<?= max(3, min(100, $fillRate)) ?>%"></i></div>
        <p class="jnet-metric__note"><?= $fillRate ?> % des postes autorisés</p>
    </article>

    <article class="jnet-metric">
        <p class="jnet-metric__label">Disponibilité opérationnelle</p>
        <p class="jnet-metric__value"><?= (int) ($readiness['overall'] ?? 0) ?><small>%</small></p>
        <div class="jnet-gauge"><i class="<?= $h($gauge((int) ($readiness['overall'] ?? 0))) ?>" style="--w:<?= max(3, min(100, (int) ($readiness['overall'] ?? 0))) ?>%"></i></div>
        <p class="jnet-metric__note">Moyenne des éléments subordonnés</p>
    </article>

    <article class="jnet-metric">
        <p class="jnet-metric__label">Opérations en cours</p>
        <p class="jnet-metric__value"><?= (int) ($stats['activeOps'] ?? 0) ?></p>
        <div class="jnet-gauge"><i class="is-green" style="--w:<?= min(100, max(6, (int) ($stats['activeOps'] ?? 0) * 20)) ?>%"></i></div>
        <p class="jnet-metric__note"><?= count($taskings) ?> missions au tableau</p>
    </article>

    <article class="jnet-metric">
        <p class="jnet-metric__label">Objectifs suivis</p>
        <p class="jnet-metric__value"><?= (int) ($stats['priorityTargets'] ?? 0) ?></p>
        <div class="jnet-gauge"><i class="is-amber" style="--w:<?= min(100, max(6, (int) ($stats['priorityTargets'] ?? 0) * 12)) ?>%"></i></div>
        <p class="jnet-metric__note">Dossiers d’intérêt actifs</p>
    </article>
</section>

<div class="jnet-grid-2">
    <section class="jnet-panel">
        <div class="jnet-panel__head">
            <h2>Chaîne de commandement</h2>
            <span class="jnet-meta"><?= count(array_filter($keyPosts, static fn (array $p): bool => empty($p['vacant']))) ?>/<?= count($keyPosts) ?> pourvus</span>
        </div>
        <div class="jnet-panel__body">
            <ul class="jnet-postlist">
                <?php foreach ($keyPosts as $post): ?>
                    <li class="jnet-post<?= !empty($post['vacant']) ? ' is-vacant' : '' ?>">
                        <?php if (!empty($post['vacant'])): ?>
                            <span class="jnet-avatar jnet-avatar--lg" aria-hidden="true"><span>··</span></span>
                            <div class="jnet-post__body">
                                <strong><?= $h((string) $post['title']) ?></strong>
                                <span>Poste à pourvoir</span>
                            </div>
                            <span class="jnet-badge jnet-badge--warn">Vacant</span>
                        <?php else: ?>
                            <a class="jnet-avatar jnet-avatar--lg" href="<?= $h((string) $post['href']) ?>">
                                <?php if (!empty($post['photo'])): ?>
                                    <img src="<?= $h((string) $post['photo']) ?>" alt="">
                                <?php else: ?>
                                    <span><?= $h((string) $post['initials']) ?></span>
                                <?php endif; ?>
                            </a>
                            <div class="jnet-post__body">
                                <strong><?= $h((string) $post['title']) ?></strong>
                                <a class="jnet-post__holder" href="<?= $h((string) $post['href']) ?>"><?= $h((string) $post['holder']) ?></a>
                                <span>
                                    <?= $h(trim((string) $post['grade'], ' -—')) ?>
                                    <?php if (trim((string) $post['callsign'], ' -—') !== ''): ?>
                                        · Indicatif <?= $h((string) $post['callsign']) ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>

    <section class="jnet-panel">
        <div class="jnet-panel__head">
            <h2>État de l’effectif</h2>
            <span class="jnet-meta"><?= $present ?> présents</span>
        </div>
        <div class="jnet-panel__body">
            <div class="jnet-breakdown">
                <?php foreach ($duty as $key => $row): ?>
                    <div class="jnet-breakdown__row">
                        <span><?= $h((string) ($row['label'] ?? $key)) ?></span>
                        <div class="jnet-gauge">
                            <i class="<?= $key === 'off' ? 'is-dim' : ($key === 'deployed' ? 'is-amber' : 'is-green') ?>"
                               style="--w:<?= max(2, min(100, (int) ($row['share'] ?? 0))) ?>%"></i>
                        </div>
                        <strong><?= (int) ($row['count'] ?? 0) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <h3 class="jnet-section-title">Composantes de disponibilité</h3>
            <div class="jnet-breakdown">
                <?php foreach (($readiness['components'] ?? []) as $comp): ?>
                    <div class="jnet-breakdown__row">
                        <span><?= $h((string) ($comp['label'] ?? '')) ?></span>
                        <div class="jnet-gauge">
                            <i class="<?= $h($gauge((int) ($comp['value'] ?? 0))) ?>" style="--w:<?= max(3, min(100, (int) ($comp['value'] ?? 0))) ?>%"></i>
                        </div>
                        <strong><?= (int) ($comp['value'] ?? 0) ?> %</strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($specialities !== []): ?>
                <h3 class="jnet-section-title">Spécialités détenues</h3>
                <ul class="jnet-speclist">
                    <?php foreach ($specialities as $spec): ?>
                        <li><span><?= $h((string) $spec['label']) ?></span><strong><?= (int) $spec['count'] ?></strong></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
</div>

<section class="jnet-panel">
    <div class="jnet-panel__head">
        <h2>Ordre de bataille</h2>
        <span class="jnet-meta"><?= $hasRealOrbat ? 'Structure Athena' : 'Structure de démonstration' ?></span>
    </div>
    <div class="jnet-panel__body">
        <div class="jnet-orbat">
            <div class="jnet-orbat__root">
                <span class="jnet-unit-badge" aria-hidden="true"><?= $h($badge) ?></span>
                <div>
                    <strong><?= $h((string) (($orbatRoot['label'] ?? null) ?: $unitName)) ?></strong>
                    <span><?= $h((string) (($orbatRoot['leader'] ?? null) ?: 'Commandement d’unité')) ?></span>
                </div>
                <span class="jnet-orbat__count"><?= $present ?>/<?= $authorized ?></span>
            </div>

            <div class="jnet-orbat__branches">
                <?php foreach ($subUnits as $u): ?>
                    <a class="jnet-orbat-card" href="<?= $h((string) ($u['href'] ?? '#')) ?>">
                        <div class="jnet-orbat-card__head">
                            <span class="jnet-orbat-card__code"><?= $h((string) $u['code']) ?></span>
                            <span class="jnet-badge <?= (int) $u['readiness'] >= 85 ? 'jnet-badge--ok' : ((int) $u['readiness'] >= 65 ? 'jnet-badge--watch' : 'jnet-badge--warn') ?>">
                                <?= (int) $u['readiness'] ?> %
                            </span>
                        </div>
                        <strong><?= $h((string) $u['name']) ?></strong>
                        <span class="jnet-orbat-card__leader"><?= $h((string) $u['leader']) ?></span>
                        <div class="jnet-gauge">
                            <i class="<?= $h($gauge((int) $u['readiness'])) ?>" style="--w:<?= max(3, min(100, (int) $u['readiness'])) ?>%"></i>
                        </div>
                        <span class="jnet-orbat-card__foot">
                            <?= (int) $u['present'] ?>/<?= (int) $u['authorized'] ?> présents · <?= $h((string) $u['status']) ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<section class="jnet-panel">
    <div class="jnet-panel__head">
        <h2>Tableau des sous-unités</h2>
        <span class="jnet-meta"><?= count($subUnits) ?> éléments</span>
    </div>
    <div class="jnet-panel__body jnet-panel__body--flush">
        <?php if ($subUnits === []): ?>
            <p class="jnet-empty">Aucune sous-unité renseignée pour le moment.</p>
        <?php else: ?>
            <div class="jnet-table-wrap">
                <table class="jnet-table jnet-unit-table">
                    <thead>
                        <tr>
                            <th scope="col">Élément</th>
                            <th scope="col">Responsable</th>
                            <th scope="col">Effectif</th>
                            <th scope="col">Disponibilité</th>
                            <th scope="col">État</th>
                            <th scope="col">Mission courante</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subUnits as $u): ?>
                            <tr>
                                <th scope="row">
                                    <a class="jnet-unit-table__name" href="<?= $h((string) ($u['href'] ?? '#')) ?>"
                                       style="--depth:<?= max(0, min(3, (int) ($u['depth'] ?? 1) - 1)) ?>">
                                        <span class="jnet-unit-table__code"><?= $h((string) $u['code']) ?></span>
                                        <span>
                                            <strong><?= $h((string) $u['name']) ?></strong>
                                            <em><?= $h((string) $u['mission']) ?></em>
                                        </span>
                                    </a>
                                </th>
                                <td>
                                    <span class="jnet-unit-table__leader">
                                        <span class="jnet-avatar"><span><?= $h((string) $u['leader_initials']) ?></span></span>
                                        <?= $h((string) $u['leader']) ?>
                                    </span>
                                </td>
                                <td class="jnet-unit-table__num">
                                    <strong><?= (int) $u['present'] ?></strong> / <?= (int) $u['authorized'] ?>
                                </td>
                                <td class="jnet-unit-table__gauge">
                                    <div class="jnet-gauge">
                                        <i class="<?= $h($gauge((int) $u['readiness'])) ?>" style="--w:<?= max(3, min(100, (int) $u['readiness'])) ?>%"></i>
                                    </div>
                                    <span><?= (int) $u['readiness'] ?> %</span>
                                </td>
                                <td>
                                    <span class="jnet-badge <?= (int) $u['readiness'] >= 85 ? 'jnet-badge--ok' : ((int) $u['readiness'] >= 65 ? 'jnet-badge--watch' : 'jnet-badge--warn') ?>">
                                        <?= $h((string) $u['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="jnet-unit-table__task">
                                        <?= $h((string) $u['tasking']) ?>
                                        <?php if (trim((string) $u['tasking_state']) !== ''): ?>
                                            <em><?= $h((string) $u['tasking_state']) ?></em>
                                        <?php endif; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<div class="jnet-grid-2">
    <section class="jnet-panel">
        <div class="jnet-panel__head">
            <h2>Moyens de l’unité</h2>
            <span class="jnet-meta">Suivi indicatif</span>
        </div>
        <div class="jnet-panel__body">
            <ul class="jnet-assets">
                <?php foreach ($assets as $asset): ?>
                    <?php
                    $total = max(1, (int) $asset['total']);
                    $rate = (int) round(((int) $asset['ready'] / $total) * 100);
                    ?>
                    <li>
                        <div class="jnet-assets__line">
                            <strong><?= $h((string) $asset['label']) ?></strong>
                            <span><?= (int) $asset['ready'] ?>/<?= $total ?></span>
                        </div>
                        <div class="jnet-gauge"><i class="<?= $h($gauge($rate)) ?>" style="--w:<?= max(3, min(100, $rate)) ?>%"></i></div>
                        <p class="jnet-meta"><?= $h((string) $asset['note']) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>

    <section class="jnet-panel">
        <div class="jnet-panel__head">
            <h2>Journal d’unité</h2>
            <span class="jnet-meta">Dernières entrées</span>
        </div>
        <div class="jnet-panel__body">
            <?php if ($recentEvents === []): ?>
                <p class="jnet-empty">Aucune entrée récente.</p>
            <?php else: ?>
                <div class="jnet-feed">
                    <?php foreach ($recentEvents as $ev): ?>
                        <a class="jnet-feed__item" href="<?= $h((string) ($ev['href'] ?? '#')) ?>">
                            <time><?= $h((string) ($ev['time'] ?? '')) ?></time>
                            <div>
                                <strong><?= $h((string) ($ev['title'] ?? '')) ?></strong>
                                <span><?= $h((string) ($ev['detail'] ?? '')) ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
