<?php
declare(strict_types=1);

use App\Services\Personnel\GradeScaleSeedService;

$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$grades = is_array($grades ?? null) ? $grades : [];
$filieres = is_array($filieres ?? null) ? $filieres : [];
$templates = is_array($templates ?? null) ? $templates : GradeScaleSeedService::templates();
$orderIds = implode(',', array_map(static fn (array $g): int => (int) ($g['id'] ?? 0), $grades));
?>
<div class="bo-adv">
    <?php include __DIR__ . '/_flash.php'; ?>

    <div class="bo-adv__toolbar">
        <a class="ath-btn ath-btn--solid" href="<?= $h(url('back-office/organisation/grades/create')) ?>">Nouveau grade</a>
        <a class="ath-btn" href="<?= $h(url('back-office/referentiels/grades')) ?>">Catalogue global</a>
        <a class="ath-btn" href="<?= $h(url('back-office/rh/avancement')) ?>">Campagnes d’avancement</a>
    </div>

    <section class="bo-adv__panel">
        <div class="bo-adv__panel-head">
            <h2>Importer une échelle type</h2>
            <p>Duplication scopée à cette communauté — jamais d’échelle partagée entre tenants.</p>
        </div>
        <form method="post" action="<?= $h(url('back-office/organisation/grades/seed')) ?>" class="bo-adv__inline-form">
            <?= \App\Core\Csrf::field() ?>
            <select name="template_key" required>
                <?php foreach ($templates as $tpl): ?>
                    <option value="<?= $h((string) ($tpl['key'] ?? '')) ?>"><?= $h((string) ($tpl['label'] ?? '')) ?> — <?= $h((string) ($tpl['detail'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="bo-adv__check"><input type="checkbox" name="force" value="1"> Compléter les manquants</label>
            <button type="submit" class="ath-btn ath-btn--solid">Dupliquer</button>
        </form>
    </section>

    <div class="bo-adv__grid2">
        <section class="bo-adv__panel">
            <div class="bo-adv__panel-head">
                <h2>Filières</h2>
                <p>Branches configurables (Cadre général, Enlisted…).</p>
            </div>
            <ul class="bo-adv__list">
                <?php foreach ($filieres as $f): ?>
                    <li><span class="bo-adv__mono"><?= $h((string) ($f['code'] ?? '')) ?></span> <?= $h((string) ($f['label'] ?? '')) ?></li>
                <?php endforeach; ?>
                <?php if ($filieres === []): ?><li class="bo-adv__muted">Aucune filière.</li><?php endif; ?>
            </ul>
            <form method="post" action="<?= $h(url('back-office/organisation/grades/filieres')) ?>" class="bo-adv__stack">
                <?= \App\Core\Csrf::field() ?>
                <input type="text" name="code" required placeholder="Code" maxlength="32">
                <input type="text" name="label" required placeholder="Libellé">
                <input type="number" name="sort_order" value="0" min="0">
                <button type="submit" class="ath-btn">Ajouter la filière</button>
            </form>
        </section>

        <section class="bo-adv__panel">
            <div class="bo-adv__panel-head">
                <h2>Ordre hiérarchique</h2>
                <p>Du plus bas au plus haut — identifiants dans l’ordre actuel.</p>
            </div>
            <form method="post" action="<?= $h(url('back-office/organisation/grades/reorder')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <textarea name="order" rows="4" class="bo-adv__textarea"><?= $h($orderIds) ?></textarea>
                <p class="bo-adv__hint">IDs séparés par des virgules. Le premier est le grade le plus bas.</p>
                <button type="submit" class="ath-btn">Enregistrer l’ordre</button>
            </form>
        </section>
    </div>

    <div class="bo-adv__table-wrap">
        <table class="bo-adv__table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Libellé</th>
                    <th>Filière</th>
                    <th>Ordre</th>
                    <th>Ancienneté</th>
                    <th>Choix</th>
                    <th>Temps mini</th>
                    <th>Qualification requise</th>
                    <th>Statut</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grades as $g):
                    $archived = !empty($g['archived_at']);
                    ?>
                    <tr class="<?= $archived ? 'is-archived' : '' ?>">
                        <td class="bo-adv__mono"><?= $h((string) ($g['code'] ?? '')) ?></td>
                        <td>
                            <strong><?= $h((string) ($g['label'] ?? '')) ?></strong>
                            <?php if (trim((string) ($g['short_label'] ?? '')) !== ''): ?>
                                <span class="bo-adv__muted"> · <?= $h((string) $g['short_label']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= $h((string) ($g['filiere_label'] ?? '—')) ?></td>
                        <td><?= (int) ($g['rank_order'] ?? 0) ?></td>
                        <td><?= !empty($g['advancement_seniority_enabled']) ? 'Oui' : 'Non' ?></td>
                        <td><?= !empty($g['advancement_choice_enabled']) ? 'Oui' : 'Non' ?></td>
                        <td><?= isset($g['min_time_in_previous_grade_months']) && $g['min_time_in_previous_grade_months'] !== null && $g['min_time_in_previous_grade_months'] !== ''
                            ? ((int) $g['min_time_in_previous_grade_months'] . ' mois')
                            : '—' ?></td>
                        <td><?= $h((string) ($g['required_qualification_name'] ?? $g['required_qualification_code'] ?? '—')) ?></td>
                        <td><?= $archived ? 'Archivé' : 'Actif' ?></td>
                        <td class="bo-adv__actions">
                            <a href="<?= $h(url('back-office/organisation/grades/' . (int) ($g['id'] ?? 0) . '/edit')) ?>">Modifier</a>
                            <?php if (!$archived): ?>
                                <form method="post" action="<?= $h(url('back-office/organisation/grades/' . (int) ($g['id'] ?? 0) . '/archive')) ?>" onsubmit="return confirm('Archiver ce grade ? L’historique des personnels est conservé.');">
                                    <?= \App\Core\Csrf::field() ?>
                                    <button type="submit" class="bo-adv__linkbtn">Archiver</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($grades === []): ?>
                    <tr><td colspan="10" class="bo-adv__empty">Aucun grade. Importez une échelle type ou créez le premier grade.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
