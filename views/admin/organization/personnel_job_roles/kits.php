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
$toneLabel = static function (string $tone): string {
    return match ($tone) {
        'lecture' => 'Lecture',
        'modification' => 'Lecture & modification',
        'admin' => 'Administration',
        default => 'Accès',
    };
};
?>
<?php if (!$isAthShell): ?>
<div class="mx-auto max-w-6xl px-6 py-12 bo-catalog">
    <?php require __DIR__ . '/_nav.php'; ?>
<?php else: ?>
<div class="bo-catalog bo-kits">
    <div class="flex flex-wrap gap-2 mb-6 ath-rise">
        <a href="<?= url('back-office/personnel-job-roles/kits') ?>" class="ath-btn ath-btn--solid">Kits d’accès</a>
        <a href="<?= url('back-office/personnel-job-roles') ?>" class="ath-btn">Référentiel</a>
        <a href="<?= url('back-office/personnel-job-roles/assignments') ?>" class="ath-btn">Attributions effectifs</a>
    </div>
<?php endif; ?>

    <header class="bo-catalog__hero">
        <p class="bo-catalog__kicker">Accès au site</p>
        <h1 class="bo-catalog__title">Qui peut faire quoi</h1>
        <p class="bo-catalog__lead">
            Cochez les kits dont vous avez besoin — lecture, modification, recrutement, paramètres…
            Puis attribuez-les aux membres. Plusieurs kits peuvent se cumuler sur la même personne.
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
        <div class="bo-kits__grid" role="group" aria-label="Kits d’accès">
            <?php foreach ($kits as $kit):
                $kid = trim((string) ($kit['id'] ?? ''));
                if ($kid === '') {
                    continue;
                }
                $checked = !empty($kit['enabled']);
                $tone = trim((string) ($kit['tone'] ?? ''));
                $permCount = (int) ($kit['permission_count'] ?? $kit['key_count'] ?? 0);
                ?>
            <label class="bo-kits__card<?= $checked ? ' is-on' : '' ?><?= $tone !== '' ? ' bo-kits__card--' . $h($tone) : '' ?>">
                <span class="bo-kits__card-top">
                    <input type="checkbox" name="kit_ids[]" value="<?= $h($kid) ?>"<?= $checked ? ' checked' : '' ?>>
                    <span class="bo-kits__card-kicker"><?= $h($toneLabel($tone)) ?> · <?= $permCount ?> droit<?= $permCount > 1 ? 's' : '' ?></span>
                </span>
                <strong class="bo-kits__card-title"><?= $h((string) ($kit['label'] ?? '')) ?></strong>
                <span class="bo-kits__card-text"><?= $h((string) ($kit['summary'] ?? '')) ?></span>
                <span class="bo-kits__card-state">
                    <span class="bo-kits__card-state-off">Disponible</span>
                    <span class="bo-kits__card-state-on">Sélectionné</span>
                </span>
            </label>
            <?php endforeach; ?>
        </div>
        <div class="bo-kits__actions">
            <button type="submit" class="ath-btn ath-btn--solid">Enregistrer les kits</button>
            <p class="bo-kits__hint" data-bo-kits-hint>
                <?= $enabledCount > 0
                    ? $enabledCount . ' kit' . ($enabledCount > 1 ? 's' : '') . ' sélectionné' . ($enabledCount > 1 ? 's' : '') . ' — multi-sélection possible.'
                    : 'Aucun kit coché pour l’instant.' ?>
            </p>
        </div>
    </form>

    <?php if ($kitsSelected && $board !== []): ?>
    <section class="bo-kits__board" aria-labelledby="bo-kits-board-title">
        <h2 id="bo-kits-board-title" class="bo-catalog__section-title">Attribuer les kits</h2>
        <p class="bo-catalog__section-lead">Pour chaque kit activé, désignez un ou plusieurs membres. Les droits s’ajoutent à leurs accès existants.</p>
        <div class="bo-kits__board-grid">
            <?php foreach ($board as $row):
                $kitId = trim((string) ($row['kit_id'] ?? ''));
                $roleId = isset($row['role_id']) ? (int) $row['role_id'] : 0;
                $holders = is_array($row['holders'] ?? null) ? $row['holders'] : [];
                $tone = trim((string) ($row['tone'] ?? ''));
                ?>
            <article class="bo-kits__post<?= $tone !== '' ? ' bo-kits__post--' . $h($tone) : '' ?>">
                <p class="bo-kits__post-kicker"><?= $h($toneLabel($tone)) ?></p>
                <h3 class="bo-kits__post-title"><?= $h((string) ($row['name'] ?? '')) ?></h3>
                <?php if (!empty($row['summary'])): ?>
                    <p class="bo-kits__empty" style="margin-bottom:0.65rem;"><?= $h((string) $row['summary']) ?></p>
                <?php endif; ?>
                <?php if ($holders === []): ?>
                    <p class="bo-kits__empty">Personne n’a encore ce kit.</p>
                <?php else: ?>
                    <ul class="bo-kits__holders">
                        <?php foreach ($holders as $holder):
                            $hid = (int) ($holder['user_id'] ?? 0);
                            $hname = trim((string) ($holder['display_name'] ?? ''));
                            ?>
                        <li>
                            <span><?= $h($hname !== '' ? $hname : 'Membre') ?></span>
                            <?php if ($hid > 0 && $kitId !== ''): ?>
                            <form method="post" action="<?= $h(url('back-office/personnel-job-roles/kits/unassign')) ?>" class="bo-kits__inline">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="user_id" value="<?= $hid ?>">
                                <input type="hidden" name="kit_id" value="<?= $h($kitId) ?>">
                                <button type="submit" class="bo-kits__unlink">Retirer</button>
                            </form>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ($roleId > 0 && $kitId !== ''): ?>
                <form method="post" action="<?= $h(url('back-office/personnel-job-roles/kits/assign')) ?>" class="bo-kits__assign">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="kit_id" value="<?= $h($kitId) ?>">
                    <label class="bo-kits__assign-label">
                        <span>Qui l’obtient ?</span>
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
                    <p class="bo-kits__empty">Enregistrez d’abord les kits pour pouvoir les attribuer.</p>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php elseif (!$kitsSelected): ?>
    <p class="bo-kits__next">Cochez au moins un kit puis enregistrez pour l’attribuer aux membres.</p>
    <?php endif; ?>
</div>
<?php if (is_file(base_path('public/assets/js/bo-kits-selection.js'))): ?>
<script defer src="<?= $h(asset_url('assets/js/bo-kits-selection.js')) ?>"></script>
<?php endif; ?>
