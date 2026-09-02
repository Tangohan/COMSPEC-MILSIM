<?php
declare(strict_types=1);

$kits = is_array($kits ?? null) ? $kits : [];
$board = is_array($board ?? null) ? $board : [];
$assignMembers = is_array($assignMembers ?? null) ? $assignMembers : [];
$kitsSelected = !empty($kitsSelected);
$activeTab = $activeTab ?? 'kits';
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
$isAthShell = !empty($isBackOfficeShell);
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$enabledCount = 0;
foreach ($kits as $kit) {
    if (!empty($kit['enabled'])) {
        $enabledCount++;
    }
}
?>
<?php if (!$isAthShell): ?>
<div class="mx-auto max-w-6xl px-6 py-12 bo-catalog">
    <?php require __DIR__ . '/_nav.php'; ?>
<?php else: ?>
<div class="bo-catalog bo-kits">
    <div class="flex flex-wrap gap-2 mb-6 ath-rise">
        <a href="<?= url('back-office/personnel-job-roles/kits') ?>" class="ath-btn ath-btn--solid">Kits de fonctions</a>
        <a href="<?= url('back-office/personnel-job-roles') ?>" class="ath-btn">Référentiel</a>
        <a href="<?= url('back-office/personnel-job-roles/assignments') ?>" class="ath-btn">Attributions effectifs</a>
    </div>
<?php endif; ?>

    <header class="bo-catalog__hero">
        <p class="bo-catalog__kicker">Effectifs</p>
        <h1 class="bo-catalog__title">Choisissez ce que fait votre communauté</h1>
        <p class="bo-catalog__lead">
            Cochez les domaines dont vous avez besoin — infanterie, santé, logistique…
            Un seul enregistrement suffit. Si vous n’en cochez aucun, le catalogue complet reste disponible.
            Rien n’est retiré des dossiers déjà remplis.
        </p>
    </header>

    <?php if ($flashSuccess): ?>
        <p class="ath-flash ath-flash--ok" role="status"><?= $h((string) $flashSuccess) ?></p>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <p class="ath-flash ath-flash--err" role="alert"><?= $h((string) $flashError) ?></p>
    <?php endif; ?>

    <form method="post" action="<?= $h(url('back-office/personnel-job-roles/kits/save')) ?>" class="bo-kits__form">
        <?= \App\Core\Csrf::field() ?>
        <div class="bo-kits__grid" role="group" aria-label="Domaines de fonctions">
            <?php foreach ($kits as $kit):
                $kid = trim((string) ($kit['id'] ?? ''));
                if ($kid === '') {
                    continue;
                }
                $checked = !empty($kit['enabled']);
                ?>
            <label class="bo-kits__card<?= $checked ? ' is-on' : '' ?>">
                <input type="checkbox" name="kit_ids[]" value="<?= $h($kid) ?>"<?= $checked ? ' checked' : '' ?>>
                <span class="bo-kits__card-kicker"><?= (int) ($kit['key_count'] ?? 0) ?> fonctions clés</span>
                <strong class="bo-kits__card-title"><?= $h((string) ($kit['label'] ?? '')) ?></strong>
                <span class="bo-kits__card-text"><?= $h((string) ($kit['summary'] ?? '')) ?></span>
                <span class="bo-kits__card-state"><?= $checked ? 'Choisi' : 'Disponible' ?></span>
            </label>
            <?php endforeach; ?>
        </div>
        <div class="bo-kits__actions">
            <button type="submit" class="ath-btn ath-btn--solid">Enregistrer</button>
            <p class="bo-kits__hint">
                <?= $enabledCount > 0
                    ? $enabledCount . ' domaine' . ($enabledCount > 1 ? 's' : '') . ' retenu' . ($enabledCount > 1 ? 's' : '') . '.'
                    : 'Aucun domaine coché : tout le catalogue reste proposé.' ?>
            </p>
        </div>
    </form>

    <?php if ($kitsSelected && $board !== []): ?>
    <section class="bo-kits__board" aria-labelledby="bo-kits-board-title">
        <h2 id="bo-kits-board-title" class="bo-catalog__section-title">Qui assure quoi</h2>
        <p class="bo-catalog__section-lead">Pour chaque fonction clé des domaines choisis, désignez un membre. Vous pouvez en ajouter plusieurs.</p>
        <div class="bo-kits__board-grid">
            <?php foreach ($board as $row):
                $roleId = isset($row['role_id']) ? (int) $row['role_id'] : 0;
                $holders = is_array($row['holders'] ?? null) ? $row['holders'] : [];
                ?>
            <article class="bo-kits__post">
                <p class="bo-kits__post-kicker"><?= $h((string) ($row['kit_label'] ?? '')) ?></p>
                <h3 class="bo-kits__post-title"><?= $h((string) ($row['name'] ?? '')) ?></h3>
                <?php if ($holders === []): ?>
                    <p class="bo-kits__empty">Personne n’assure encore cette fonction.</p>
                <?php else: ?>
                    <ul class="bo-kits__holders">
                        <?php foreach ($holders as $holder):
                            $hid = (int) ($holder['user_id'] ?? 0);
                            $hname = trim((string) ($holder['display_name'] ?? ''));
                            ?>
                        <li>
                            <span><?= $h($hname !== '' ? $hname : 'Membre') ?></span>
                            <?php if ($hid > 0 && $roleId > 0): ?>
                            <form method="post" action="<?= $h(url('back-office/personnel-job-roles/kits/unassign')) ?>" class="bo-kits__inline">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="user_id" value="<?= $hid ?>">
                                <input type="hidden" name="job_role_id" value="<?= $roleId ?>">
                                <button type="submit" class="bo-kits__unlink">Retirer</button>
                            </form>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ($roleId > 0): ?>
                <form method="post" action="<?= $h(url('back-office/personnel-job-roles/kits/assign')) ?>" class="bo-kits__assign">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="job_role_id" value="<?= $roleId ?>">
                    <label class="bo-kits__assign-label">
                        <span>Qui l’assure ?</span>
                        <select name="user_id" required>
                            <option value="">Choisir un membre</option>
                            <?php foreach ($assignMembers as $m):
                                $mid = (int) ($m['id'] ?? 0);
                                if ($mid < 1) {
                                    continue;
                                }
                                $mname = trim((string) ($m['display_name'] ?? ''));
                                ?>
                            <option value="<?= $mid ?>"><?= $h($mname !== '' ? $mname : 'Membre') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" class="ath-btn ath-btn--solid">Attribuer</button>
                </form>
                <?php else: ?>
                    <p class="bo-kits__empty">Cette fonction n’est pas encore dans le référentiel de la communauté.</p>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php elseif (!$kitsSelected): ?>
    <p class="bo-kits__next">Cochez au moins un domaine puis enregistrez pour voir le tableau d’attribution.</p>
    <?php endif; ?>
</div>
