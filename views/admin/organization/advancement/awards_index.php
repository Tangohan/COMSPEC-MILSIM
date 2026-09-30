<?php
declare(strict_types=1);
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$definitions = is_array($definitions ?? null) ? $definitions : [];
$members = is_array($members ?? null) ? $members : [];
$personLabel = static function (array $u): string {
    $n = trim((string) ($u['display_name'] ?? ''));

    return $n !== '' ? $n : (string) ($u['username'] ?? ('#' . ($u['id'] ?? '')));
};
?>
<div class="bo-adv">
    <?php include __DIR__ . '/_flash.php'; ?>

    <div class="bo-adv__grid2">
        <section class="bo-adv__panel">
            <div class="bo-adv__panel-head">
                <h2>Nouvelle décoration</h2>
                <p>Référentiel : nom, grade de la décoration, critère d’attribution.</p>
            </div>
            <form method="post" action="<?= $h(url('back-office/referentiels/decorations')) ?>" class="bo-adv__stack">
                <?= \App\Core\Csrf::field() ?>
                <input type="text" name="code" required placeholder="Code" maxlength="32">
                <input type="text" name="name" required placeholder="Nom">
                <input type="text" name="decoration_grade" placeholder="Grade de la décoration (ex. Bronze, Croix)">
                <textarea name="award_criterion" rows="3" class="bo-adv__textarea" placeholder="Critère d’attribution"></textarea>
                <input type="number" name="sort_order" value="0" min="0">
                <button class="ath-btn ath-btn--solid" type="submit">Créer</button>
            </form>
        </section>
        <section class="bo-adv__panel">
            <div class="bo-adv__panel-head">
                <h2>Attribuer une citation</h2>
                <p>Attribution réelle : texte, autorité, date. Distinct d’une qualification.</p>
            </div>
            <form method="post" action="<?= $h(url('back-office/referentiels/decorations/attribuer')) ?>" class="bo-adv__stack">
                <?= \App\Core\Csrf::field() ?>
                <select name="personnel_id" required>
                    <option value="">Personnel</option>
                    <?php foreach ($members as $u): ?>
                        <option value="<?= (int) ($u['id'] ?? 0) ?>"><?= $h($personLabel($u)) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="definition_id" required>
                    <option value="">Décoration</option>
                    <?php foreach ($definitions as $d): ?>
                        <?php if (!empty($d['archived_at'])) {
                            continue;
                        } ?>
                        <option value="<?= (int) ($d['id'] ?? 0) ?>"><?= $h((string) ($d['name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="authority" placeholder="Autorité">
                <input type="date" name="awarded_at" value="<?= $h(date('Y-m-d')) ?>">
                <textarea name="citation_text" rows="4" class="bo-adv__textarea" placeholder="Texte de citation"></textarea>
                <button class="ath-btn ath-btn--solid" type="submit">Attribuer</button>
            </form>
        </section>
    </div>

    <div class="bo-adv__table-wrap">
        <table class="bo-adv__table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Nom</th>
                    <th>Grade</th>
                    <th>Critère</th>
                    <th>Attributions</th>
                    <th>Statut</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($definitions as $d): ?>
                    <tr class="<?= !empty($d['archived_at']) ? 'is-archived' : '' ?>">
                        <td class="bo-adv__mono"><?= $h((string) ($d['code'] ?? '')) ?></td>
                        <td><strong><?= $h((string) ($d['name'] ?? '')) ?></strong></td>
                        <td><?= $h((string) ($d['decoration_grade'] ?? '—')) ?></td>
                        <td><?= $h((string) ($d['award_criterion'] ?? '—')) ?></td>
                        <td><?= (int) ($d['holders_count'] ?? 0) ?></td>
                        <td><?= !empty($d['archived_at']) ? 'Archivée' : 'Active' ?></td>
                        <td>
                            <?php if (empty($d['archived_at'])): ?>
                                <form method="post" action="<?= $h(url('back-office/referentiels/decorations/' . (int) ($d['id'] ?? 0) . '/archive')) ?>" onsubmit="return confirm('Archiver cette décoration ?');">
                                    <?= \App\Core\Csrf::field() ?>
                                    <button type="submit" class="bo-adv__linkbtn">Archiver</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($definitions === []): ?>
                    <tr><td colspan="7" class="bo-adv__empty">Aucune décoration dans le référentiel.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
