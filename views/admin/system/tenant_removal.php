<?php
declare(strict_types=1);
$p = is_array($removalPreview ?? null) ? $removalPreview : [];
$tenant = is_array($p['tenant'] ?? null) ? $p['tenant'] : [];
$archive = is_array($p['archive'] ?? null) ? $p['archive'] : null;
$accounts = is_array($p['accounts'] ?? null) ? $p['accounts'] : [];
$tables = is_array($p['tables'] ?? null) ? $p['tables'] : [];
$archiveBlockers = is_array($p['archive_blockers'] ?? null) ? $p['archive_blockers'] : [];
$deleteBlockers = is_array($p['delete_blockers'] ?? null) ? $p['delete_blockers'] : [];
$protected = !empty($p['protected']);
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$n = static fn (mixed $v): string => number_format((int) $v, 0, ',', ' ');
$name = (string) ($tenant['name'] ?? '');
$slug = (string) ($tenant['slug'] ?? '');
$archivedAt = $archive !== null ? strtotime((string) ($archive['archived_at'] ?? '')) : false;
$ruleLabels = [
    'CASCADE' => ['Supprimé', 'rose'],
    'PURGE' => ['Purgé avant suppression (journal des interventions admin)', 'rose'],
    'SET NULL' => ['Détaché, conservé', 'amber'],
    'NONE' => ['Conservé', 'slate'],
    'RESTRICT' => ['Bloque la suppression', 'amber'],
    'NO ACTION' => ['Bloque la suppression', 'amber'],
];
$ok = \App\Core\Session::getFlash('success');
$err = \App\Core\Session::getFlash('error');
?>
<div class="pa">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb">
                <a href="<?= $h(url('admin')) ?>">Administration du site</a>
                <span aria-hidden="true"> / </span>
                <a href="<?= $h(url('admin/tenants')) ?>">Communautés</a>
                <span aria-hidden="true"> / </span>
                Archiver ou supprimer
            </p>
            <h1 class="pa-hero__title">Retirer « <?= $h($name) ?> »</h1>
            <p class="pa-hero__lead">
                En deux temps. L’archivage ferme la communauté sans rien effacer et se défait d’un clic.
                La suppression définitive ne s’applique qu’à une communauté archivée, après une sauvegarde complète de ses données.
                Les comptes des joueurs ne sont jamais supprimés.
            </p>
            <div class="pa-hero__actions">
                <a class="pa-btn pa-btn--ghost" href="<?= $h((string) ($removalEditUrl ?? url('admin/tenants'))) ?>">Administrer la communauté</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/tenants')) ?>">Retour à l’annuaire</a>
            </div>
        </header>

        <div class="pa-panel">
            <?php if ($ok): ?>
                <p class="pa-flash pa-flash--ok"><?= $h((string) $ok) ?></p>
            <?php endif; ?>
            <?php if ($err): ?>
                <p class="pa-flash pa-flash--err"><?= $h((string) $err) ?></p>
            <?php endif; ?>

            <section class="pa-card" aria-labelledby="bilan-heading">
                <p class="pa-card__kicker">Bilan</p>
                <h2 id="bilan-heading" class="pa-card__title">
                    <?= $h($name) ?>
                    <?php if ($archive !== null): ?>
                        <span class="pa-pill pa-pill--amber">Archivée</span>
                    <?php else: ?>
                        <span class="pa-pill pa-pill--mint">Active</span>
                    <?php endif; ?>
                </h2>
                <p class="pa-card__help">Adresse courte : <strong><?= $h($slug) ?></strong> · n° <?= (int) ($tenant['id'] ?? 0) ?></p>
                <ul class="pa-kpi-strip" style="margin-top:1rem;">
                    <li class="pa-kpi">
                        <span class="pa-kpi__label">Membres actifs</span>
                        <span class="pa-kpi__value"><?= $n($accounts['members'] ?? 0) ?></span>
                        <span class="pa-kpi__hint"><?= $n($accounts['archived_memberships'] ?? 0) ?> appartenance(s) en sommeil</span>
                    </li>
                    <li class="pa-kpi">
                        <span class="pa-kpi__label">Comptes à reloger</span>
                        <span class="pa-kpi__value"><?= $n($accounts['home'] ?? 0) ?></span>
                        <span class="pa-kpi__hint"><?= $n($accounts['to_other'] ?? 0) ?> vers une autre de leurs communautés, <?= $n($accounts['to_no_org'] ?? 0) ?> vers « Sans communauté »</span>
                    </li>
                    <li class="pa-kpi<?= (int) ($p['rows_deleted'] ?? 0) > 0 ? ' pa-kpi--warn' : '' ?>">
                        <span class="pa-kpi__label">Lignes supprimées à l’étape 2</span>
                        <span class="pa-kpi__value"><?= $n($p['rows_deleted'] ?? 0) ?></span>
                        <span class="pa-kpi__hint">au moins, sur <?= $n(count(array_filter($tables, static fn (array $t): bool => !empty($t['deleted'])))) ?> table(s)</span>
                    </li>
                    <li class="pa-kpi">
                        <span class="pa-kpi__label">Lignes conservées</span>
                        <span class="pa-kpi__value"><?= $n($p['rows_kept'] ?? 0) ?></span>
                        <span class="pa-kpi__hint">journaux et tables sans lien en cascade</span>
                    </li>
                </ul>
                <?php if ((int) ($accounts['blocked'] ?? 0) > 0): ?>
                    <p class="pa-flash pa-flash--warn" style="margin-top:1rem;"><?= $n($accounts['blocked']) ?> compte(s) n’ont aucune destination : la suppression définitive reste bloquée tant qu’ils sont rattachés ici.</p>
                <?php endif; ?>
                <?php if ((int) ($accounts['technical'] ?? 0) > 0): ?>
                    <p class="pa-card__help" style="margin-top:.75rem;"><?= $n($accounts['technical']) ?> compte(s) technique(s) propres à la communauté (comptes de service, modération système) partiront avec elle.</p>
                <?php endif; ?>
            </section>

            <section id="archiver" class="pa-card" style="margin-top:1.25rem;" aria-labelledby="archive-heading">
                <p class="pa-card__kicker">Étape 1 · Réversible</p>
                <?php if ($archive === null): ?>
                    <h2 id="archive-heading" class="pa-card__title">Archiver la communauté</h2>
                    <ul class="pa-card__help" style="margin:.5rem 0 0 1.1rem;list-style:disc;">
                        <li>Elle disparaît de l’annuaire, sa page publique et ses liens (recrutement, forum, code rejoindre) ne répondent plus.</li>
                        <li>Ses membres ne la voient plus dans leur sélecteur de communauté. Ceux dont c’était la communauté courante sont relogés dans une autre de leurs communautés, ou dans « Sans communauté ».</li>
                        <li>Rien n’est effacé : unités, effectifs, documents, ATAK, forum restent en base. « Restaurer » remet toutes les appartenances.</li>
                    </ul>
                    <?php if ($archiveBlockers !== []): ?>
                        <?php foreach ($archiveBlockers as $b): ?>
                            <p class="pa-flash pa-flash--err" style="margin-top:1rem;"><?= $h((string) $b) ?></p>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php if (!empty($p['billing_live'])): ?>
                            <p class="pa-flash pa-flash--warn" style="margin-top:1rem;">Un abonnement payant est en cours : l’archivage ne le résilie pas.</p>
                        <?php endif; ?>
                        <form method="post" action="<?= $h((string) ($removalArchiveAction ?? '')) ?>" style="margin-top:1rem;">
                            <?= \App\Core\Csrf::field() ?>
                            <div class="pa-field">
                                <label for="archive_reason">Motif (gardé dans le journal)</label>
                                <input id="archive_reason" name="archive_reason" type="text" maxlength="500" placeholder="Doublon, communauté de test, demande du fondateur…">
                            </div>
                            <label class="pa-choice">
                                <input type="checkbox" name="confirm_archive" value="1" required>
                                <span>
                                    <strong>Confirmation</strong>
                                    <em>J’archive « <?= $h($name) ?> ». Je pourrai la restaurer.</em>
                                </span>
                            </label>
                            <div class="pa-actions">
                                <button type="submit" class="pa-btn pa-btn--ink">Archiver la communauté</button>
                            </div>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <h2 id="archive-heading" class="pa-card__title">Communauté archivée</h2>
                    <p class="pa-card__help">
                        Depuis le <?= $archivedAt ? $h(date('d/m/Y à H:i', $archivedAt)) : '—' ?>.
                        <?php if (trim((string) ($archive['reason'] ?? '')) !== ''): ?>
                            Motif : <?= $h((string) $archive['reason']) ?>.
                        <?php endif; ?>
                        Toutes ses données sont encore en base.
                    </p>
                    <form method="post" action="<?= $h((string) ($removalRestoreAction ?? '')) ?>" style="margin-top:1rem;">
                        <?= \App\Core\Csrf::field() ?>
                        <div class="pa-actions">
                            <button type="submit" class="pa-btn pa-btn--mint">Restaurer la communauté</button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>

            <section id="supprimer" class="pa-card" style="margin-top:1.25rem;" aria-labelledby="delete-heading">
                <p class="pa-card__kicker">Étape 2 · Définitive</p>
                <h2 id="delete-heading" class="pa-card__title">Supprimer définitivement</h2>
                <ul class="pa-card__help" style="margin:.5rem 0 0 1.1rem;list-style:disc;">
                    <li>Une sauvegarde complète (toutes les lignes de la communauté, format JSON compressé) est écrite dans <code>storage/private/tenant-deletions</code> avant toute suppression. Si elle échoue, rien n’est supprimé.</li>
                    <li>Supprimé : la fiche de la communauté et tout ce qui y est rattaché en cascade (unités, effectifs, grades, documents, formations, ATAK, forum, réglages…), soit les tables marquées « Supprimé » ci-dessous.</li>
                    <li>Conservé : les comptes des joueurs (relogés avant), le journal d’administration, les fichiers envoyés sur le disque (images, documents), les paiements déjà passés chez le prestataire.</li>
                    <li>L’adresse courte « <?= $h($slug) ?> » redevient libre.</li>
                </ul>
                <?php if ($deleteBlockers !== []): ?>
                    <?php foreach ($deleteBlockers as $b): ?>
                        <p class="pa-flash pa-flash--warn" style="margin-top:1rem;"><?= $h((string) $b) ?></p>
                    <?php endforeach; ?>
                <?php elseif (!$protected): ?>
                    <form method="post" action="<?= $h((string) ($removalDeleteAction ?? '')) ?>" style="margin-top:1rem;">
                        <?= \App\Core\Csrf::field() ?>
                        <div class="pa-field">
                            <label for="confirm_slug">Recopiez l’adresse courte <strong><?= $h($slug) ?></strong></label>
                            <input id="confirm_slug" name="confirm_slug" type="text" autocomplete="off" spellcheck="false" required>
                        </div>
                        <label class="pa-choice">
                            <input type="checkbox" name="confirm_delete" value="1" required>
                            <span>
                                <strong>Je comprends que c’est définitif</strong>
                                <em>Seule la sauvegarde permettra de retrouver ces données.</em>
                            </span>
                        </label>
                        <div class="pa-actions">
                            <button type="submit" class="pa-btn pa-btn--danger">Supprimer définitivement</button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>

            <section class="pa-card" style="margin-top:1.25rem;" aria-labelledby="tables-heading">
                <p class="pa-card__kicker">Détail</p>
                <h2 id="tables-heading" class="pa-card__title">Données de la communauté, table par table</h2>
                <p class="pa-card__help">
                    <?= $n(count($tables)) ?> table(s) contiennent des lignes de cette communauté, sur <?= $n($p['tables_scanned'] ?? 0) ?> examinée(s).
                    Les tables enfants sans colonne de communauté suivent leur parent et ne sont pas comptées ici.
                </p>
                <?php if ($tables === []): ?>
                    <p class="pa-card__help" style="margin-top:1rem;">Aucune donnée rattachée.</p>
                <?php else: ?>
                    <details style="margin-top:1rem;">
                        <summary class="pa-btn pa-btn--line pa-btn--sm">Afficher les <?= $n(count($tables)) ?> tables</summary>
                        <div class="pa-table-wrap" style="margin-top:1rem;box-shadow:none;">
                            <table class="pa-table" style="min-width:30rem;">
                                <thead>
                                    <tr>
                                        <th>Table</th>
                                        <th style="text-align:right;">Lignes</th>
                                        <th>À la suppression</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tables as $t): ?>
                                        <?php $rule = $ruleLabels[(string) ($t['rule'] ?? 'NONE')] ?? $ruleLabels['NONE']; ?>
                                        <tr>
                                            <td><code><?= $h((string) ($t['table'] ?? '')) ?></code></td>
                                            <td style="text-align:right;font-weight:800;"><?= $n($t['rows'] ?? 0) ?></td>
                                            <td><span class="pa-pill pa-pill--<?= $h($rule[1]) ?>"><?= $h($rule[0]) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </details>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>
