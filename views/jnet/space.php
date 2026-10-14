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
$guideContext = 'unit';
$leadOp = $spaceOps[0] ?? null;
$composeLabel = 'Partager avec ' . $label;
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
        <?php $diffusionText = 'Espace ' . $label . ' — diffusion : membres de ' . $label . ' et chaîne de commandement'; require base_path('views/jnet/_diffusion.php'); ?>

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
                    <span class="jn-shield" aria-hidden="true">
                        <svg viewBox="0 0 80 92" width="72" height="83"><path d="M40 3 76 15v27c0 22-15 38-36 47C19 80 4 64 4 42V15z" fill="var(--jn-shield-fill)" stroke="var(--jn-unit)" stroke-width="4" stroke-linejoin="round"/></svg>
                        <span class="jn-shield__mark"><?= $h($monogram) ?></span>
                    </span>
                <?php endif; ?>
                <div class="jn-head__text">
                    <p class="jn-chip"><?= $hasParentUnit ? 'Rattachée à ' . $h($parentLabel) : 'Espace d’unité' ?></p>
                    <h1 id="jn-space-title" class="jn-head__name"><?= $h($label) ?></h1>
                    <p class="jn-head__lead">
                        Chef : <b><?= $h((string) ($space['leader'] ?? '') !== '' ? (string) $space['leader'] : 'non désigné') ?></b>
                        · Adjoint : <b><?= $h((string) ($space['deputy'] ?? '') !== '' ? (string) $space['deputy'] : 'non désigné') ?></b>
                    </p>
                </div>
            </div>
            <dl class="jn-figures jn-figures--big">
                <div class="is-ok"><dt>disponibles</dt><dd><?= (int) $strength['available'] ?><small>/<?= (int) $strength['total'] ?></small></dd></div>
                <?php if ($leadOp !== null): ?>
                    <div class="is-accent jn-figures__op"><dt><?= $h((string) ($leadOp['state'] ?? '')) ?><?= (int) ($spaceOpsTotal ?? 0) > 1 ? ' · ' . (int) $spaceOpsTotal . ' opérations' : '' ?></dt><dd><a href="<?= $h(url('jnet/operations/' . (int) ($leadOp['id'] ?? 0))) ?>"><?= $h((string) ($leadOp['title'] ?? '')) ?></a></dd></div>
                <?php else: ?>
                    <div><dt>opération</dt><dd>0</dd></div>
                <?php endif; ?>
                <div><dt>sous-unité<?= count($subTree) > 1 ? 's' : '' ?></dt><dd><?= count($subTree) ?></dd></div>
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
                <h2 id="jn-ex-title" class="sr-only">Échanges</h2>
                <?php if (empty($canPost)): ?>
                    <p class="jn-hint">Vous consultez cet espace sans en être membre : la publication est réservée à l’unité et à son encadrement.</p>
                <?php endif; ?>
                <div class="jn-flows">
                    <?php
                    $upTarget = $hasParentUnit ? $parentLabel : 'l’échelon supérieur';
                    $columns = [
                        ['key' => 'down', 'icon' => '↓', 'title' => 'Reçu du commandement', 'lead' => 'Ordres et consignes descendus' . ($hasParentUnit ? ' de ' . $parentLabel . ' et au-dessus' : ' du commandement'), 'items' => $received, 'empty' => 'Rien de reçu pour l’instant.', 'variant' => 'card', 'perspective' => 'down'],
                        ['key' => 'internal', 'icon' => '≡', 'title' => 'Dans l’unité', 'lead' => 'Le fil de ' . $label . ' et de ses sous-unités', 'items' => $internal, 'empty' => 'Aucun échange interne. Publiez le premier ci-dessus.', 'variant' => 'chat', 'perspective' => ''],
                        ['key' => 'up', 'icon' => '↑', 'title' => 'Remonté vers ' . $upTarget, 'lead' => 'Comptes rendus et renseignement transmis', 'items' => $sentUp, 'empty' => 'Rien de remonté pour l’instant.', 'variant' => 'card', 'perspective' => 'up'],
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
                            <?php if ($col['key'] === 'internal' && !empty($canPost)): ?>
                                <?php require base_path('views/jnet/_exchange_composer.php'); ?>
                            <?php endif; ?>
                            <?php if ($col['items'] === []): ?>
                                <p class="jn-flow__empty"><?= $h($col['empty']) ?></p>
                            <?php endif; ?>
                            <?php $exVariant = $col['variant']; $exPerspective = $col['perspective']; ?>
                            <?php foreach ($col['items'] as $ex): ?>
                                <?php require base_path('views/jnet/_exchange.php'); ?>
                            <?php endforeach; ?>
                        </section>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($subTree !== []): ?>
            <section class="jn-section" aria-labelledby="jn-sub-title">
                <div class="jn-section__head">
                    <div>
                        <h2 id="jn-sub-title">Sous-unités de <?= $h($label) ?></h2>
                        <p class="jn-section__lead">Chaque sous-unité a son espace. Ce qu’elle publie pour <?= $h($label) ?> apparaît dans « Dans l’unité ».</p>
                    </div>
                </div>
                <?php $viewNodes = $subTree; $viewId = 'jn-sub-units'; $viewGrouped = false; require base_path('views/jnet/_unit_views.php'); ?>
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
