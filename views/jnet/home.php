<?php
/**
 * JNET — Espace commun de l’organisation : situation des unités, flux d’échanges, opérations.
 * Aucun contenu de démonstration : chaque bloc affiche un état vide honnête.
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$space = is_array($space ?? null) ? $space : ['id' => 0, 'label' => (string) ($unitName ?? 'Organisation'), 'isOrg' => true];
$orgStats = is_array($orgStats ?? null) ? $orgStats : ['units' => 0, 'members' => 0, 'available' => 0, 'ops' => 0, 'exchanges24h' => 0];
$posture = is_array($posture ?? null) ? $posture : ['key' => (string) ($opsStatus ?? 'GREEN'), 'label' => (string) ($opsStatusLabel ?? 'Posture verte')];
$commands = is_array($commands ?? null) ? $commands : [];
$flow = is_array($flow ?? null) ? $flow : [];
$flowFilter = (string) ($flowFilter ?? '');
$exchangeKinds = is_array($exchangeKinds ?? null) ? $exchangeKinds : [];
$currentOps = is_array($currentOps ?? null) ? $currentOps : [];
$priorityTargets = is_array($priorityTargets ?? null) ? $priorityTargets : [];
$recentArticles = is_array($recentArticles ?? null) ? $recentArticles : [];
$recentDocuments = is_array($recentDocuments ?? null) ? $recentDocuments : [];
$quickLinks = is_array($quickLinks ?? null) ? $quickLinks : [];
$targetsTotal = (int) ($targetsTotal ?? count($priorityTargets));
$postureKey = strtolower((string) ($posture['key'] ?? 'green'));
$postureClass = in_array($postureKey, ['red', 'amber'], true) ? $postureKey : 'green';
$motto = trim((string) ($unitMotto ?? ''));
$prioKey = static fn (array $t): string => preg_replace('/[^a-z]/', '', strtolower((string) ($t['priority_key'] ?? $t['priority'] ?? 'low'))) ?: 'low';
$exBack = url('jnet');
$label = (string) ($space['label'] ?? 'Organisation');
$monogram = mb_strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]/u', '', $label) ?: 'O', 0, 2));
?>
<div class="jn-layout">
    <?php require base_path('views/jnet/_spaces_rail.php'); ?>

    <div class="jn-main">
        <section class="jn-head" aria-labelledby="jn-space-title">
            <div class="jn-head__id">
                <span class="jn-emblem" aria-hidden="true"><?= $h($monogram) ?></span>
                <div>
                    <p class="jn-kicker">Échelon organisation</p>
                    <h1 id="jn-space-title" class="jn-head__name"><?= $h($label) ?></h1>
                    <?php if ($motto !== ''): ?><p class="jn-head__motto"><?= $h($motto) ?></p><?php endif; ?>
                </div>
            </div>
            <dl class="jn-figures">
                <div><dt>Posture</dt><dd class="jn-posture jn-posture--<?= $postureClass ?>"><?= $h((string) ($posture['label'] ?? '')) ?></dd></div>
                <div><dt>Unités</dt><dd><?= (int) $orgStats['units'] ?></dd></div>
                <div><dt>Disponibles</dt><dd><?= (int) $orgStats['available'] ?><small> / <?= (int) $orgStats['members'] ?></small></dd></div>
                <div><dt>Opérations</dt><dd><?= (int) $orgStats['ops'] ?></dd></div>
                <div><dt>Échanges 24 h</dt><dd><?= (int) $orgStats['exchanges24h'] ?></dd></div>
            </dl>
        </section>

        <?php if ($quickLinks !== []): ?>
            <nav class="jnet-shortcuts jn-shortcuts" aria-label="Accès directs">
                <?php foreach ($quickLinks as $link): ?>
                    <a class="jnet-shortcut" href="<?= $h((string) ($link['href'] ?? '#')) ?>">
                        <strong><?= $h((string) ($link['label'] ?? '')) ?></strong>
                        <span><?= $h((string) ($link['desc'] ?? '')) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <section class="jn-section" id="jn-units" aria-labelledby="jn-units-title">
            <div class="jn-section__head">
                <h2 id="jn-units-title">Situation des unités</h2>
                <a class="jn-link" href="<?= $h(url('jnet/unite')) ?>">Organigramme →</a>
            </div>
            <?php if ($commands === []): ?>
                <div class="jn-empty">
                    <p><strong>L’organigramme n’est pas encore renseigné.</strong></p>
                    <p>Chaque unité de la chaîne de commandement aura ici son propre espace.</p>
                    <p><a class="jn-btn" href="<?= $h(effectifs_workspace_url('chaine')) ?>">Ouvrir la chaîne de commandement</a></p>
                </div>
            <?php endif; ?>
            <?php foreach ($commands as $g): ?>
                <div class="jn-group">
                    <p class="jn-group__head">
                        <strong><?= $h((string) $g['label']) ?></strong>
                        <span><?= (int) $g['unitCount'] ?> unité<?= (int) $g['unitCount'] > 1 ? 's' : '' ?> · <?= (int) $g['members'] ?> membre<?= (int) $g['members'] > 1 ? 's' : '' ?></span>
                    </p>
                    <div class="jn-cards">
                        <?php foreach ((array) $g['units'] as $u): ?>
                            <?php require base_path('views/jnet/_unit_card.php'); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

        <div class="jn-split">
            <section class="jn-section" aria-labelledby="jn-flow-title">
                <div class="jn-section__head">
                    <h2 id="jn-flow-title">Flux commun</h2>
                    <nav class="jn-filters" aria-label="Filtrer le flux">
                        <a href="<?= $h(url('jnet')) ?>" class="jn-filter<?= $flowFilter === '' ? ' is-active' : '' ?>">Tout</a>
                        <?php foreach ($exchangeKinds as $key => $kindLabel): ?>
                            <a href="<?= $h(url('jnet?type=' . rawurlencode((string) $key))) ?>" class="jn-filter<?= $flowFilter === $key ? ' is-active' : '' ?>"><?= $h($kindLabel) ?></a>
                        <?php endforeach; ?>
                    </nav>
                </div>
                <?php if (!empty($canPost)): ?>
                    <?php require base_path('views/jnet/_exchange_composer.php'); ?>
                <?php endif; ?>
                <?php if (empty($exchangesReady)): ?>
                    <div class="jn-empty">
                        <p><strong>Les échanges entre espaces ne sont pas encore activés.</strong></p>
                        <p>Un administrateur doit appliquer les migrations (table <code>jnet_exchanges</code>).</p>
                    </div>
                <?php elseif ($flow === []): ?>
                    <div class="jn-empty">
                        <p><strong>Aucun échange pour le moment.</strong></p>
                        <p>Ordres, comptes rendus et renseignements publiés dans les espaces d’unité apparaissent ici, selon votre place dans la chaîne de commandement.</p>
                    </div>
                <?php else: ?>
                    <div class="jn-stack">
                        <?php foreach ($flow as $ex): ?>
                            <?php require base_path('views/jnet/_exchange.php'); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <aside class="jn-side">
                <section class="jn-section" aria-labelledby="jn-ops-title">
                    <div class="jn-section__head">
                        <h2 id="jn-ops-title">Opérations en cours</h2>
                        <a class="jn-link" href="<?= $h(url('jnet/operations')) ?>">Toutes →</a>
                    </div>
                    <?php if ($currentOps === []): ?>
                        <div class="jn-empty">
                            <p><strong>Aucune opération engagée.</strong></p>
                            <p>Les missions ouvertes sur le tableau opérationnel apparaissent ici.</p>
                            <p><a class="jn-btn" href="<?= $h(url('back-office/tableau-operationnel')) ?>">Ouvrir le tableau opérationnel</a></p>
                        </div>
                    <?php else: ?>
                        <ul class="jn-list">
                            <?php foreach ($currentOps as $op): ?>
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

                <section class="jn-section" aria-labelledby="jn-intel-title">
                    <div class="jn-section__head">
                        <h2 id="jn-intel-title">Renseignement suivi</h2>
                        <a class="jn-link" href="<?= $h(url('jnet/cibles')) ?>">Voir tout<?= $targetsTotal > 0 ? ' (' . $targetsTotal . ')' : '' ?> →</a>
                    </div>
                    <?php if ($priorityTargets === []): ?>
                        <div class="jn-empty">
                            <p><strong>Aucun dossier de renseignement ouvert.</strong></p>
                            <p>Les personnes et objectifs suivis par le bureau SSE apparaissent ici.</p>
                        </div>
                    <?php else: ?>
                        <ul class="jn-list">
                            <?php foreach ($priorityTargets as $t): ?>
                                <li>
                                    <a class="jn-target jn-target--<?= $h($prioKey($t)) ?>" href="<?= $h((string) ($t['href'] ?? '#')) ?>">
                                        <span class="jn-target__mark" aria-hidden="true"><?= $h(mb_strtoupper(mb_substr((string) ($t['name'] ?? '?'), 0, 2))) ?></span>
                                        <span class="jn-target__text">
                                            <strong><?= $h((string) ($t['name'] ?? '')) ?><?php if (trim((string) ($t['code'] ?? '')) !== ''): ?> <small><?= $h((string) $t['code']) ?></small><?php endif; ?></strong>
                                            <em><?= $h(trim(implode(' · ', array_filter([(string) ($t['priority'] ?? ''), (string) ($t['lastKnown'] ?? '')])))) ?></em>
                                        </span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            </aside>
        </div>

        <div class="jn-split jn-split--even">
            <section class="jn-section" aria-labelledby="jn-articles-title">
                <div class="jn-section__head">
                    <h2 id="jn-articles-title">Articles de l’unité</h2>
                    <a class="jn-link" href="<?= $h(url('articles')) ?>">Tous les articles →</a>
                </div>
                <?php if ($recentArticles === []): ?>
                    <div class="jn-empty">
                        <p><strong>Aucun article publié.</strong></p>
                        <p>Les notes d’unité mises en ligne apparaîtront ici.</p>
                    </div>
                <?php else: ?>
                    <ul class="jnet-linklist jn-linklist">
                        <?php foreach ($recentArticles as $article): ?>
                            <li>
                                <a href="<?= $h((string) ($article['href'] ?? '#')) ?>">
                                    <strong><?= $h((string) ($article['title'] ?? '')) ?></strong>
                                    <?php if (trim((string) ($article['excerpt'] ?? '')) !== ''): ?>
                                        <span><?= $h((string) $article['excerpt']) ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="jn-section" aria-labelledby="jn-docs-title">
                <div class="jn-section__head">
                    <h2 id="jn-docs-title">Documents publiés</h2>
                    <a class="jn-link" href="<?= $h(url('jnet/bibliotheque')) ?>">Bibliothèque →</a>
                </div>
                <?php if ($recentDocuments === []): ?>
                    <div class="jn-empty">
                        <p><strong>Aucun document publié.</strong></p>
                        <p>Les consignes et doctrines mises à disposition apparaîtront ici.</p>
                    </div>
                <?php else: ?>
                    <ul class="jnet-linklist jn-linklist">
                        <?php foreach ($recentDocuments as $doc): ?>
                            <li>
                                <a href="<?= $h((string) ($doc['href'] ?? '#')) ?>">
                                    <strong><?= $h((string) ($doc['title'] ?? '')) ?></strong>
                                    <?php if (trim((string) ($doc['category'] ?? '')) !== ''): ?>
                                        <span><?= $h((string) $doc['category']) ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>
