<?php
declare(strict_types=1);
/**
 * JNET — Fiche d’unité.
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
$recentEvents = is_array($recentEvents ?? null) ? $recentEvents : [];
$taskings = is_array($unitTaskings ?? null) ? $unitTaskings : [];
$hasRealOrbat = (bool) ($hasRealOrbat ?? false);
$posture = strtoupper((string) ($opsStatus ?? 'GREEN'));
$postureClass = match ($posture) {
    'RED' => 'is-red',
    'AMBER' => 'is-amber',
    default => 'is-green',
};
$postureLabel = (string) ($opsStatusLabel ?? 'Posture verte');
$badge = strtoupper(substr(preg_replace('/\s+/', '', $unitName) ?: 'U', 0, 3));

$gauge = static function (int $value): string {
    return match (true) {
        $value >= 85 => 'is-green',
        $value >= 65 => 'is-amber',
        default => 'is-red',
    };
};
$present = (int) ($stats['personnelPresent'] ?? 0);
$authorized = (int) ($stats['personnelAuth'] ?? 0);
$fillRate = $authorized > 0 ? (int) round(($present / $authorized) * 100) : 0;
?>
<section class="jnet-unit-head">
    <div class="jnet-unit-head__id">
        <div class="jnet-unit-badge jnet-unit-badge--lg" aria-hidden="true"><?= $h($badge) ?></div>
        <div class="jnet-unit-head__names">
            <p class="jnet-kicker">Fiche d’unité<?php if (trim((string) ($identity['code'] ?? '')) !== ''): ?> · <?= $h((string) $identity['code']) ?><?php endif; ?></p>
            <h1><?= $h($unitName) ?></h1>
            <?php if ($unitMotto !== ''): ?>
                <p class="jnet-unit-head__motto">« <?= $h($unitMotto) ?> »</p>
            <?php endif; ?>
            <div class="jnet-unit-head__chips">
                <span class="jnet-status <?= $h($postureClass) ?>"><?= $h($postureLabel) ?></span>
                <?php if (trim((string) ($readiness['label'] ?? '')) !== ''): ?>
                    <span class="jnet-pill"><?= $h((string) $readiness['label']) ?></span>
                <?php endif; ?>
                <?php if (count($subUnits) > 0): ?>
                    <span class="jnet-pill"><?= count($subUnits) ?> élément<?= count($subUnits) > 1 ? 's' : '' ?> subordonné<?= count($subUnits) > 1 ? 's' : '' ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <dl class="jnet-unit-head__facts">
        <?php if (trim((string) ($identity['higher'] ?? '')) !== ''): ?>
            <div><dt>Rattachement</dt><dd><?= $h((string) $identity['higher']) ?></dd></div>
        <?php endif; ?>
        <?php if (trim((string) ($identity['theatre'] ?? '')) !== ''): ?>
            <div><dt>Théâtre</dt><dd><?= $h((string) $identity['theatre']) ?></dd></div>
        <?php endif; ?>
        <?php if (trim((string) ($identity['activated'] ?? '')) !== ''): ?>
            <div><dt>Créée le</dt><dd><?= $h((string) $identity['activated']) ?></dd></div>
        <?php endif; ?>
        <div><dt>Effectif tenu</dt><dd><?= $present ?><?= $authorized > 0 ? ' / ' . $authorized : '' ?></dd></div>
    </dl>
</section>

<section class="jnet-metrics" aria-label="Indicateurs de l’unité">
    <article class="jnet-metric">
        <p class="jnet-metric__label">Personnel en service</p>
        <p class="jnet-metric__value"><?= $present ?><?php if ($authorized > 0): ?><small>/<?= $authorized ?></small><?php endif; ?></p>
        <?php if ($authorized > 0): ?>
            <div class="jnet-gauge"><i class="<?= $h($gauge($fillRate)) ?>" style="--w:<?= max(3, min(100, $fillRate)) ?>%"></i></div>
            <p class="jnet-metric__note"><?= $fillRate ?> % de l’annuaire en service</p>
        <?php else: ?>
            <p class="jnet-metric__note">Aucun membre actif dans l’annuaire</p>
        <?php endif; ?>
    </article>

    <article class="jnet-metric">
        <p class="jnet-metric__label">Disponibilité</p>
        <p class="jnet-metric__value"><?= (int) ($readiness['overall'] ?? 0) ?><small>%</small></p>
        <div class="jnet-gauge"><i class="<?= $h($gauge((int) ($readiness['overall'] ?? 0))) ?>" style="--w:<?= max(3, min(100, (int) ($readiness['overall'] ?? 0))) ?>%"></i></div>
        <p class="jnet-metric__note"><?= $h((string) ($readiness['label'] ?? '')) ?></p>
    </article>

    <article class="jnet-metric">
        <p class="jnet-metric__label">Opérations en cours</p>
        <p class="jnet-metric__value"><?= (int) ($stats['activeOps'] ?? 0) ?></p>
        <p class="jnet-metric__note"><?= count($taskings) ?> mission<?= count($taskings) > 1 ? 's' : '' ?> au tableau</p>
    </article>

    <article class="jnet-metric">
        <p class="jnet-metric__label">Dossiers suivis</p>
        <p class="jnet-metric__value"><?= (int) ($stats['priorityTargets'] ?? 0) ?></p>
        <p class="jnet-metric__note">Personnes et objectifs ouverts</p>
    </article>
</section>

<div class="jnet-grid-2">
    <section class="jnet-panel">
        <div class="jnet-panel__head">
            <h2>Chaîne de commandement</h2>
            <span class="jnet-meta"><?= count($keyPosts) ?> poste<?= count($keyPosts) > 1 ? 's' : '' ?> identifié<?= count($keyPosts) > 1 ? 's' : '' ?></span>
        </div>
        <div class="jnet-panel__body">
            <?php if ($keyPosts === []): ?>
                <div class="jnet-empty">
                    <p>Aucun poste de commandement identifié.</p>
                    <p>Les fonctions renseignées dans les dossiers (commandant, adjoint, opérations, renseignement) apparaissent ici.</p>
                </div>
            <?php else: ?>
                <ul class="jnet-postlist">
                    <?php foreach ($keyPosts as $post): ?>
                        <li class="jnet-post">
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
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>

    <section class="jnet-panel">
        <div class="jnet-panel__head">
            <h2>État de l’effectif</h2>
            <span class="jnet-meta"><?= $present ?> en service</span>
        </div>
        <div class="jnet-panel__body">
            <?php if ($duty === []): ?>
                <p class="jnet-empty">Aucun effectif à afficher.</p>
            <?php else: ?>
                <div class="jnet-breakdown">
                    <?php foreach ($duty as $key => $row): ?>
                        <div class="jnet-breakdown__row">
                            <span><?= $h((string) ($row['label'] ?? $key)) ?></span>
                            <div class="jnet-gauge">
                                <i class="<?= $key === 'off' ? 'is-dim' : 'is-green' ?>"
                                   style="--w:<?= max(2, min(100, (int) ($row['share'] ?? 0))) ?>%"></i>
                            </div>
                            <strong><?= (int) ($row['count'] ?? 0) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

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
        <span class="jnet-meta"><?= $hasRealOrbat ? count($subUnits) . ' éléments' : 'Non renseigné' ?></span>
    </div>
    <div class="jnet-panel__body">
        <?php if (!$hasRealOrbat): ?>
            <div class="jnet-empty">
                <p>L’organigramme n’est pas encore renseigné.</p>
                <p>Lorsque les sous-unités sont posées dans l’organisation, elles apparaissent ici avec leur responsable et l’effectif tenu.</p>
            </div>
        <?php else: ?>
            <div class="jnet-orbat">
                <div class="jnet-orbat__root">
                    <span class="jnet-unit-badge" aria-hidden="true"><?= $h($badge) ?></span>
                    <div>
                        <strong><?= $h((string) (($orbatRoot['label'] ?? null) ?: $unitName)) ?></strong>
                        <?php if (trim((string) ($orbatRoot['leader'] ?? '')) !== ''): ?>
                            <span><?= $h((string) $orbatRoot['leader']) ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="jnet-orbat__count"><?= $present ?><?= $authorized > 0 ? '/' . $authorized : '' ?></span>
                </div>

                <div class="jnet-orbat__branches">
                    <?php foreach ($subUnits as $u): ?>
                        <?php $ready = isset($u['readiness']) && $u['readiness'] !== null && $u['readiness'] !== '' ? (int) $u['readiness'] : null; ?>
                        <a class="jnet-orbat-card" href="<?= $h((string) ($u['href'] ?? '#')) ?>">
                            <div class="jnet-orbat-card__head">
                                <span class="jnet-orbat-card__code"><?= $h((string) $u['code']) ?></span>
                                <?php if ($ready !== null): ?>
                                    <span class="jnet-badge <?= $ready >= 85 ? 'jnet-badge--ok' : ($ready >= 65 ? 'jnet-badge--watch' : 'jnet-badge--warn') ?>">
                                        <?= $ready ?> %
                                    </span>
                                <?php endif; ?>
                            </div>
                            <strong><?= $h((string) $u['name']) ?></strong>
                            <?php if (trim((string) ($u['leader'] ?? '')) !== ''): ?>
                                <span class="jnet-orbat-card__leader"><?= $h((string) $u['leader']) ?></span>
                            <?php endif; ?>
                            <?php if ($ready !== null): ?>
                                <div class="jnet-gauge">
                                    <i class="<?= $h($gauge($ready)) ?>" style="--w:<?= max(3, min(100, $ready)) ?>%"></i>
                                </div>
                            <?php endif; ?>
                            <span class="jnet-orbat-card__foot">
                                <?= (int) $u['present'] ?><?= (int) $u['authorized'] > 0 ? '/' . (int) $u['authorized'] : '' ?> tenus · <?= $h((string) $u['status']) ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($subUnits !== []): ?>
<section class="jnet-panel">
    <div class="jnet-panel__head">
        <h2>Tableau des sous-unités</h2>
        <span class="jnet-meta"><?= count($subUnits) ?> éléments</span>
    </div>
    <div class="jnet-panel__body jnet-panel__body--flush">
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
                        <?php $ready = isset($u['readiness']) && $u['readiness'] !== null && $u['readiness'] !== '' ? (int) $u['readiness'] : null; ?>
                        <tr>
                            <th scope="row">
                                <a class="jnet-unit-table__name" href="<?= $h((string) ($u['href'] ?? '#')) ?>"
                                   style="--depth:<?= max(0, min(3, (int) ($u['depth'] ?? 1) - 1)) ?>">
                                    <span class="jnet-unit-table__code"><?= $h((string) $u['code']) ?></span>
                                    <span>
                                        <strong><?= $h((string) $u['name']) ?></strong>
                                        <?php if (trim((string) ($u['mission'] ?? '')) !== ''): ?>
                                            <em><?= $h((string) $u['mission']) ?></em>
                                        <?php endif; ?>
                                    </span>
                                </a>
                            </th>
                            <td>
                                <?php if (trim((string) ($u['leader'] ?? '')) !== ''): ?>
                                    <span class="jnet-unit-table__leader">
                                        <span class="jnet-avatar"><span><?= $h((string) $u['leader_initials']) ?></span></span>
                                        <?= $h((string) $u['leader']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="jnet-meta">Non désigné</span>
                                <?php endif; ?>
                            </td>
                            <td class="jnet-unit-table__num">
                                <strong><?= (int) $u['present'] ?></strong><?php if ((int) $u['authorized'] > 0): ?> / <?= (int) $u['authorized'] ?><?php endif; ?>
                            </td>
                            <td class="jnet-unit-table__gauge">
                                <?php if ($ready !== null): ?>
                                    <div class="jnet-gauge">
                                        <i class="<?= $h($gauge($ready)) ?>" style="--w:<?= max(3, min(100, $ready)) ?>%"></i>
                                    </div>
                                    <span><?= $ready ?> %</span>
                                <?php else: ?>
                                    <span>—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="jnet-badge <?= $ready === null ? 'jnet-badge--watch' : ($ready >= 85 ? 'jnet-badge--ok' : ($ready >= 65 ? 'jnet-badge--watch' : 'jnet-badge--warn')) ?>">
                                    <?= $h((string) $u['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (trim((string) ($u['tasking'] ?? '')) !== ''): ?>
                                    <span class="jnet-unit-table__task">
                                        <?= $h((string) $u['tasking']) ?>
                                        <?php if (trim((string) ($u['tasking_state'] ?? '')) !== ''): ?>
                                            <em><?= $h((string) $u['tasking_state']) ?></em>
                                        <?php endif; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="jnet-meta">Aucune mission rattachée</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php endif; ?>

<div class="jnet-grid-2">
    <section class="jnet-panel">
        <div class="jnet-panel__head">
            <h2>Missions au tableau</h2>
            <a class="jnet-btn" href="<?= $h(url('jnet/operations')) ?>">Opérations</a>
        </div>
        <div class="jnet-panel__body">
            <?php if ($taskings === []): ?>
                <div class="jnet-empty">
                    <p>Aucune mission ouverte.</p>
                    <p><a class="jnet-btn" href="<?= $h(url('back-office/tableau-operationnel')) ?>">Ouvrir le tableau opérationnel</a></p>
                </div>
            <?php else: ?>
                <?php foreach ($taskings as $op): ?>
                    <a class="jnet-op-row" href="<?= $h(url('jnet/operations/' . (int) ($op['id'] ?? 0))) ?>">
                        <strong><?= $h((string) ($op['title'] ?? '')) ?></strong>
                        <span class="jnet-badge <?= ($op['state_key'] ?? '') === 'active' ? 'jnet-badge--ok' : 'jnet-badge--watch' ?>"><?= $h((string) ($op['state'] ?? '')) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
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
                            <?php if (trim((string) ($ev['time'] ?? '')) !== ''): ?>
                                <time><?= $h((string) $ev['time']) ?></time>
                            <?php endif; ?>
                            <div>
                                <strong><?= $h((string) ($ev['title'] ?? '')) ?></strong>
                                <?php if (trim((string) ($ev['detail'] ?? '')) !== ''): ?>
                                    <span><?= $h((string) $ev['detail']) ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
