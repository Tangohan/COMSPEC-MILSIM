<?php
/**
 * JNET — Espace d’une unité : reçu du commandement, fil de l’unité, remonté vers l’échelon supérieur.
 * @var array<string, mixed> $space
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$space = is_array($space ?? null) ? $space : [];
$chain = is_array($space['chain'] ?? null) ? $space['chain'] : [];
$parent = is_array($space['parent'] ?? null) ? $space['parent'] : [];
$strength = is_array($space['strength'] ?? null) ? $space['strength'] : ['total' => 0, 'available' => 0];
$subUnits = is_array($subUnits ?? null) ? $subUnits : [];
$received = is_array($received ?? null) ? $received : [];
$internal = is_array($internal ?? null) ? $internal : [];
$sentUp = is_array($sentUp ?? null) ? $sentUp : [];
$members = is_array($members ?? null) ? $members : [];
$spaceOps = is_array($spaceOps ?? null) ? $spaceOps : [];
$accent = (string) ($space['accent'] ?? '');
$label = (string) ($space['label'] ?? 'Unité');
$badge = (string) ($space['badge'] ?? '');
$monogram = mb_strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]/u', '', $label) ?: 'U', 0, 2));
$exBack = (string) ($space['href'] ?? url('jnet'));
$parentLabel = (string) ($parent['label'] ?? '');
$hasParentUnit = (int) ($parent['id'] ?? -1) > 0;
$face = static function (array $p) use ($h): string {
    $photo = $p['photo'] ?? null;
    if (is_string($photo) && $photo !== '') {
        return '<img src="' . $h($photo) . '" alt="">';
    }

    return '<span>' . $h((string) ($p['initials'] ?? '?')) . '</span>';
};
?>
<div class="jn-layout"<?= $accent !== '' ? ' style="--jn-unit: ' . $h($accent) . '"' : '' ?>>
    <?php require base_path('views/jnet/_spaces_rail.php'); ?>

    <div class="jn-main">
        <nav class="jn-crumbs" aria-label="Échelons">
            <?php foreach ($chain as $i => $c): ?>
                <?php if ($i > 0): ?><span class="jn-crumbs__sep" aria-hidden="true">›</span><?php endif; ?>
                <?php if ((int) $c['id'] === (int) ($space['id'] ?? 0)): ?>
                    <span class="jn-crumbs__here" aria-current="page"><?= $h((string) $c['label']) ?></span>
                <?php else: ?>
                    <a href="<?= $h((string) $c['href']) ?>" title="<?= $h((string) $c['label']) ?>"><?= $h((string) $c['label']) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($subUnits !== []): ?>
                <span class="jn-crumbs__sep" aria-hidden="true">›</span>
                <details class="jn-crumbs__down">
                    <summary><?= count($subUnits) ?> sous-unité<?= count($subUnits) > 1 ? 's' : '' ?></summary>
                    <ul>
                        <?php foreach ($subUnits as $su): ?>
                            <li><a href="<?= $h((string) $su['href']) ?>"><?= $h((string) $su['label']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
        </nav>

        <section class="jn-head jn-head--unit" aria-labelledby="jn-space-title">
            <div class="jn-head__id">
                <?php if ($badge !== ''): ?>
                    <img class="jn-emblem jn-emblem--img" src="<?= $h($badge) ?>" alt="Insigne de <?= $h($label) ?>">
                <?php else: ?>
                    <span class="jn-emblem" aria-hidden="true"><?= $h($monogram) ?></span>
                <?php endif; ?>
                <div>
                    <p class="jn-kicker"><?= $hasParentUnit ? 'Rattachée à ' . $h($parentLabel) : 'Unité de tête' ?></p>
                    <h1 id="jn-space-title" class="jn-head__name"><?= $h($label) ?></h1>
                    <?php if (trim((string) ($space['motto'] ?? '')) !== ''): ?>
                        <p class="jn-head__motto"><?= $h((string) $space['motto']) ?></p>
                    <?php endif; ?>
                    <?php $leader = (string) ($space['leader'] ?? ''); $deputy = (string) ($space['deputy'] ?? ''); ?>
                    <?php if ($leader !== '' || $deputy !== ''): ?>
                        <p class="jn-head__lead">
                            Chef : <b><?= $h($leader !== '' ? $leader : 'à désigner') ?></b>
                            · Adjoint : <b><?= $h($deputy !== '' ? $deputy : 'à désigner') ?></b>
                        </p>
                    <?php else: ?>
                        <p class="jn-head__lead">Chef et adjoint à désigner dans la <a class="jn-link" href="<?= $h(effectifs_workspace_url('chaine')) ?>">chaîne de commandement</a>.</p>
                    <?php endif; ?>
                </div>
            </div>
            <dl class="jn-figures">
                <div><dt>Disponibles</dt><dd><?= (int) $strength['available'] ?><small> / <?= (int) $strength['total'] ?></small></dd></div>
                <div><dt>Opérations</dt><dd><?= (int) ($spaceOpsTotal ?? 0) ?></dd></div>
                <div><dt>Sous-unités</dt><dd><?= count($subUnits) ?></dd></div>
            </dl>
        </section>

        <?php
        $guideMode = 'unit';
        $guideOpen = $received === [] && $internal === [] && $sentUp === [];
        require base_path('views/jnet/_guide.php');
        ?>

        <?php if (empty($exchangesReady)): ?>
            <div class="jn-empty">
                <p><strong>Les échanges entre espaces ne sont pas encore activés.</strong></p>
                <p>Un administrateur doit appliquer les migrations (table <code>jnet_exchanges</code>).</p>
            </div>
        <?php endif; ?>

        <?php if (!empty($canPost)): ?>
            <?php require base_path('views/jnet/_exchange_composer.php'); ?>
        <?php endif; ?>

        <div class="jn-flows">
            <section class="jn-flow jn-flow--down" aria-labelledby="jn-down-title">
                <header class="jn-flow__head">
                    <span class="jn-flow__icon" aria-hidden="true">↓</span>
                    <div>
                        <h2 id="jn-down-title">Reçu</h2>
                        <p>Ordres et consignes du commandement<?= $hasParentUnit ? ' (' . $h($parentLabel) . ' et au-dessus)' : '' ?>, échanges des unités partenaires.</p>
                    </div>
                </header>
                <?php if ($received === []): ?>
                    <p class="jn-flow__empty">Rien de reçu pour l’instant.</p>
                <?php endif; ?>
                <?php foreach ($received as $ex): ?>
                    <?php require base_path('views/jnet/_exchange.php'); ?>
                <?php endforeach; ?>
            </section>

            <section class="jn-flow jn-flow--internal" aria-labelledby="jn-int-title">
                <header class="jn-flow__head">
                    <span class="jn-flow__icon" aria-hidden="true">≡</span>
                    <div>
                        <h2 id="jn-int-title">Dans l’unité</h2>
                        <p>Le fil de <?= $h($label) ?> et de ses sous-unités.</p>
                    </div>
                </header>
                <?php if ($internal === []): ?>
                    <p class="jn-flow__empty">Aucun échange interne<?= !empty($canPost) ? ' : utilisez « Nouvel échange » au-dessus' : '' ?>.</p>
                <?php endif; ?>
                <?php foreach ($internal as $ex): ?>
                    <?php require base_path('views/jnet/_exchange.php'); ?>
                <?php endforeach; ?>
            </section>

            <section class="jn-flow jn-flow--up" aria-labelledby="jn-up-title">
                <header class="jn-flow__head">
                    <span class="jn-flow__icon" aria-hidden="true">↑</span>
                    <div>
                        <h2 id="jn-up-title">Remonté<?= $hasParentUnit ? ' vers ' . $h($parentLabel) : '' ?></h2>
                        <p>Comptes rendus et renseignement transmis à l’échelon supérieur.</p>
                    </div>
                </header>
                <?php if ($sentUp === []): ?>
                    <p class="jn-flow__empty">Rien de remonté pour l’instant.</p>
                <?php endif; ?>
                <?php foreach ($sentUp as $ex): ?>
                    <?php require base_path('views/jnet/_exchange.php'); ?>
                <?php endforeach; ?>
            </section>
        </div>

        <?php if ($subUnits !== []): ?>
            <section class="jn-section" aria-labelledby="jn-sub-title">
                <div class="jn-section__head"><h2 id="jn-sub-title">Sous-unités</h2></div>
                <div class="jn-cards">
                    <?php foreach ($subUnits as $u): ?>
                        <?php require base_path('views/jnet/_unit_card.php'); ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <div class="jn-split">
            <section class="jn-section" aria-labelledby="jn-mem-title">
                <div class="jn-section__head">
                    <h2 id="jn-mem-title">Membres</h2>
                    <a class="jn-link" href="<?= $h(url('jnet/personnel?filtre=' . rawurlencode($label))) ?>">Annuaire →</a>
                </div>
                <?php if ($members === []): ?>
                    <div class="jn-empty"><p>Aucun membre affecté directement à cette unité.</p></div>
                <?php else: ?>
                    <div class="jn-people">
                        <?php foreach ($members as $p): ?>
                            <a class="jn-person" href="<?= $h((string) ($p['href'] ?? '#')) ?>">
                                <span class="jn-avatar<?= ($p['duty'] ?? '') === 'off' ? ' is-off' : '' ?>"><?= $face($p) ?></span>
                                <strong><?= $h((string) ($p['name'] ?? '')) ?></strong>
                                <span><?= $h(trim(implode(' · ', array_filter([(string) ($p['grade'] ?? ''), (string) ($p['function'] ?? '')])))) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <aside class="jn-side">
                <section class="jn-section" aria-labelledby="jn-sops-title">
                    <div class="jn-section__head"><h2 id="jn-sops-title">Opérations de l’unité</h2></div>
                    <?php if ($spaceOps === []): ?>
                        <div class="jn-empty"><p>Aucune opération engagée par cette unité.</p></div>
                    <?php else: ?>
                        <ul class="jn-list">
                            <?php foreach ($spaceOps as $op): ?>
                                <li>
                                    <a class="jn-op" href="<?= $h(url('jnet/operations/' . (int) ($op['id'] ?? 0))) ?>">
                                        <strong><?= $h((string) ($op['title'] ?? '')) ?></strong>
                                        <span class="jn-badge<?= ($op['state_key'] ?? '') === 'active' ? ' jn-badge--ok' : '' ?>"><?= $h((string) ($op['state'] ?? '')) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            </aside>
        </div>
    </div>
</div>
