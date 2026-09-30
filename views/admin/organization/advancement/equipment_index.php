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
                <h2>Nouvel article</h2>
                <p>Référentiel matériel — distinct du catalogue documentaire.</p>
            </div>
            <form method="post" action="<?= $h(url('back-office/referentiels/dotation')) ?>" class="bo-adv__stack">
                <?= \App\Core\Csrf::field() ?>
                <input type="text" name="code" required placeholder="Code" maxlength="32">
                <input type="text" name="name" required placeholder="Nom">
                <input type="text" name="category" placeholder="Catégorie (radio, arme, outil…)">
                <textarea name="description" rows="3" class="bo-adv__textarea" placeholder="Description"></textarea>
                <button class="ath-btn ath-btn--solid" type="submit">Créer</button>
            </form>
        </section>
        <section class="bo-adv__panel">
            <div class="bo-adv__panel-head">
                <h2>Attribuer nominativement</h2>
                <p>Numéro de série fictif, statut en dotation, historique conservé.</p>
            </div>
            <form method="post" action="<?= $h(url('back-office/referentiels/dotation/attribuer')) ?>" class="bo-adv__stack">
                <?= \App\Core\Csrf::field() ?>
                <select name="personnel_id" required>
                    <option value="">Personnel</option>
                    <?php foreach ($members as $u): ?>
                        <option value="<?= (int) ($u['id'] ?? 0) ?>"><?= $h($personLabel($u)) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="definition_id" required>
                    <option value="">Article</option>
                    <?php foreach ($definitions as $d): ?>
                        <?php if (!empty($d['archived_at'])) {
                            continue;
                        } ?>
                        <option value="<?= (int) ($d['id'] ?? 0) ?>"><?= $h((string) ($d['name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="serial_number" placeholder="N° de série">
                <input type="date" name="assigned_at" value="<?= $h(date('Y-m-d')) ?>">
                <textarea name="notes" rows="2" class="bo-adv__textarea" placeholder="Notes"></textarea>
                <button class="ath-btn ath-btn--solid" type="submit">Doter</button>
            </form>
        </section>
    </div>

    <div class="bo-adv__table-wrap">
        <table class="bo-adv__table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Article</th>
                    <th>Catégorie</th>
                    <th>En dotation</th>
                    <th>Statut</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($definitions as $d): ?>
                    <tr class="<?= !empty($d['archived_at']) ? 'is-archived' : '' ?>">
                        <td class="bo-adv__mono"><?= $h((string) ($d['code'] ?? '')) ?></td>
                        <td><strong><?= $h((string) ($d['name'] ?? '')) ?></strong></td>
                        <td><?= $h((string) ($d['category'] ?? '—')) ?></td>
                        <td><?= (int) ($d['issued_count'] ?? 0) ?></td>
                        <td><?= !empty($d['archived_at']) ? 'Archivé' : 'Actif' ?></td>
                        <td class="bo-adv__actions">
                            <a href="<?= $h(url('back-office/referentiels/dotation/' . (int) ($d['id'] ?? 0))) ?>">Carnet</a>
                            <?php if (empty($d['archived_at'])): ?>
                                <form method="post" action="<?= $h(url('back-office/referentiels/dotation/' . (int) ($d['id'] ?? 0) . '/archive')) ?>">
                                    <?= \App\Core\Csrf::field() ?>
                                    <button type="submit" class="bo-adv__linkbtn">Archiver</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($definitions === []): ?>
                    <tr><td colspan="6" class="bo-adv__empty">Aucun article de dotation.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
