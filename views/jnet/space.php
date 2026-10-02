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
$subTree = is_array($subTree ?? null) ? $subTree : [];
$received = is_array($received ?? null) ? $received : [];
$internal = is_array($internal ?? null) ? $internal : [];
$sentUp = is_array($sentUp ?? null) ? $sentUp : [];
$members = is_array($members ?? null) ? $members : [];
$spaceOps = is_array($spaceOps ?? null) ? $spaceOps : [];
$unitAccent = (string) ($space['accent'] ?? '');
$label = (string) ($space['label'] ?? 'Unité');
$badge = (string) ($space['badge'] ?? '');
$monogram = mb_strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]/u', '', $label) ?: 'U', 0, 2));
$exBack = (string) ($space['href'] ?? url('jnet'));
$parentLabel = (string) ($parent['label'] ?? '');
$parentHref = (string) ($parent['href'] ?? '');
$hasParentUnit = (int) ($parent['id'] ?? -1) > 0;
$noExchanges = $received === [] && $internal === [] && $sentUp === [];
$guideContext = 'unit';
$composeLabel = 'Publier un échange';
$face = static function (array $p) use ($h): string {
    $photo = $p['photo'] ?? null;
    if (is_string($photo) && $photo !== '') {
        return '<img src="' . $h($photo) . '" alt="">';
    }

    return '<span>' . $h((string) ($p['initials'] ?? '?')) . '</span>';
};
?>
<div class="jn-layout"<?= $unitAccent !== '' ? ' style="--jn-unit: ' . $h($unitAccent) . '"' : '' ?>>
    <?php require base_path('views/jnet/_spaces_rail.php'); ?>

    <div class="jn-main">
        <nav class="jn-crumbs" aria-label="Échelons">
            <ol>
                <?php foreach ($chain as $c): ?>
                    <li>
                        <?php if ((int) $c['id'] === (int) ($space['id'] ?? 0)): ?>
                            <span class="jn-crumbs__here" aria-current="page" title="<?= $h((string) $c['label']) ?>"><?= $h((string) $c['label']) ?></span>
                        <?php else: ?>
                            <a href="<?= $h((string) $c['href']) ?>" title="<?= $h((string) $c['label']) ?>"><?= $h((string) $c['label']) ?></a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>

        <section class="jn-head jn-head--unit" aria-labelledby="jn-space-title">
            <div class="jn-head__id">
                <?php if ($badge !== ''): ?>
                    <img class="jn-emblem jn-emblem--img" src="<?= $h($badge) ?>" alt="Insigne de <?= $h($label) ?>">
                <?php else: ?>
                    <span class="jn-emblem" aria-hidden="true"><?= $h($monogram) ?></span>
                <?php endif; ?>
                <div class="jn-head__text">
                    <p class="jn-kicker">Espace d’unité</p>
                    <h1 id="jn-space-title" class="jn-head__name"><?= $h($label) ?></h1>
                    <p class="jn-head__lead">
                        <?php if ($hasParentUnit): ?>
                            Sous <a href="<?= $h($parentHref) ?>"><?= $h($parentLabel) ?></a> ·
                        <?php endif; ?>
                        Chef <b><?= $h((string) ($space['leader'] ?? '') !== '' ? (string) $space['leader'] : 'non désigné') ?></b>
                        · Adjoint <b><?= $h((string) ($space['deputy'] ?? '') !== '' ? (string) $space['deputy'] : 'non désigné') ?></b>
                    </p>
                </div>
            </div>
            <dl class="jn-figures">
                <div><dt>Disponibles</dt><dd><?= (int) $strength['available'] ?><small> / <?= (int) $strength['total'] ?></small></dd></div>
                <div><dt>Opérations</dt><dd><?= (int) ($spaceOpsTotal ?? 0) ?></dd></div>
                <div><dt>Sous-unités</dt><dd><?= count($subTree) ?></dd></div>
            </dl>
        </section>

        <?php require base_path('views/jnet/_guide.php'); ?>

        <?php if (empty($exchangesReady)): ?>
            <div class="jn-empty">
                <p><strong>Les échanges entre espaces ne sont pas encore activés.</strong></p>
                <p>Un administrateur doit appliquer les migrations.</p>
            </div>
        <?php else: ?>
            <section class="jn-section" aria-labelledby="jn-ex-title">
                <div class="jn-section__head">
                    <div>
                        <h2 id="jn-ex-title">Échanges</h2>
                        <p class="jn-section__lead">Ce qui descend du commandement, ce qui circule dans l’unité, ce qui remonte.</p>
                    </div>
                </div>

                <?php if (!empty($canPost)): ?>
                    <?php require base_path('views/jnet/_exchange_composer.php'); ?>
                <?php else: ?>
                    <p class="jn-hint">Vous consultez cet espace sans en être membre : la publication est réservée à l’unité et à son encadrement.</p>
                <?php endif; ?>

                <?php if ($noExchanges): ?>
                    <div class="jn-empty jn-empty--wide">
                        <p><strong>Aucun échange pour l’instant dans cet espace.</strong></p>
                        <p>
                            <?= !empty($canPost)
                                ? 'Cliquez sur « Publier un échange » : choisissez un type, un titre, puis à qui le diffuser.'
                                : 'Les ordres, comptes rendus et renseignements de l’unité apparaîtront ici.' ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="jn-flows">
                        <?php
                        $columns = [
                            ['key' => 'down', 'icon' => '↓', 'title' => 'Reçu', 'lead' => 'Du commandement' . ($hasParentUnit ? ' (' . $parentLabel . ' et au-dessus)' : '') . ' et des unités partenaires.', 'items' => $received, 'empty' => 'Rien de reçu.'],
                            ['key' => 'internal', 'icon' => '≡', 'title' => 'Dans l’unité', 'lead' => 'Le fil de l’unité et de ses sous-unités.', 'items' => $internal, 'empty' => 'Aucun échange interne.'],
                            ['key' => 'up', 'icon' => '↑', 'title' => 'Remonté', 'lead' => $hasParentUnit ? 'Envoyé vers ' . $parentLabel . '.' : 'Envoyé vers l’échelon supérieur.', 'items' => $sentUp, 'empty' => 'Rien de remonté.'],
                        ];
                        ?>
                        <?php foreach ($columns as $col): ?>
                            <section class="jn-flow jn-flow--<?= $h($col['key']) ?>" aria-labelledby="jn-col-<?= $h($col['key']) ?>">
                                <header class="jn-flow__head">
                                    <span class="jn-flow__icon" aria-hidden="true"><?= $col['icon'] ?></span>
                                    <div>
                                        <h3 id="jn-col-<?= $h($col['key']) ?>"><?= $h($col['title']) ?> <span class="jn-flow__count"><?= count($col['items']) ?></span></h3>
                                        <p><?= $h($col['lead']) ?></p>
                                    </div>
                                </header>
                                <?php if ($col['items'] === []): ?>
                                    <p class="jn-flow__empty"><?= $h($col['empty']) ?></p>
                                <?php endif; ?>
                                <?php foreach ($col['items'] as $ex): ?>
                                    <?php require base_path('views/jnet/_exchange.php'); ?>
                                <?php endforeach; ?>
                            </section>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($subTree !== []): ?>
            <section class="jn-section" aria-labelledby="jn-sub-title">
                <div class="jn-section__head">
                    <div>
                        <h2 id="jn-sub-title">Sous-unités</h2>
                        <p class="jn-section__lead">Chaque sous-unité a son espace. Ce qu’elle remonte apparaît ici dans « Dans l’unité ».</p>
                    </div>
                </div>
                <?php $treeNodes = $subTree; require base_path('views/jnet/_unit_tree.php'); ?>
            </section>
        <?php endif; ?>

        <div class="jn-split">
            <section class="jn-section" aria-labelledby="jn-mem-title">
                <div class="jn-section__head">
                    <h2 id="jn-mem-title">Membres <span class="jn-flow__count"><?= count($members) ?></span></h2>
                    <a class="jn-link" href="<?= $h(url('jnet/personnel?filtre=' . rawurlencode($label))) ?>">Annuaire →</a>
                </div>
                <?php if ($members === []): ?>
                    <div class="jn-empty"><p>Aucun membre affecté directement à cette unité.</p></div>
                <?php else: ?>
                    <div class="jn-people">
                        <?php foreach ($members as $p): ?>
                            <a class="jn-person" href="<?= $h((string) ($p['href'] ?? '#')) ?>">
                                <span class="jn-avatar<?= ($p['duty'] ?? '') === 'off' ? ' is-off' : '' ?>" title="<?= ($p['duty'] ?? '') === 'off' ? 'Indisponible' : 'Disponible' ?>"><?= $face($p) ?></span>
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
