<?php
declare(strict_types=1);

use App\Support\YearReviewCalendar;

/** @var YearReviewCalendar $yrCalendar */
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$cal = $yrCalendar instanceof YearReviewCalendar ? $yrCalendar : new YearReviewCalendar();
$now = (int) ($yrNow ?? time());
$current = $yrCurrent ?? null;
$next = (int) ($yrNext ?? YearReviewCalendar::FIRST_YEAR);
$previewYear = (int) ($yrPreviewYear ?? (int) date('Y'));
$tenants = is_array($yrTenants ?? null) ? $yrTenants : [];
$md = $cal->publishMd();
$isPreset = isset(YearReviewCalendar::PRESETS[$md]);
$fmt = static fn (int $ts): string => date('d/m/Y', $ts);
$ok = \App\Core\Session::getFlash('success');
$err = \App\Core\Session::getFlash('error');
?>
<div class="pa">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb">
                <a href="<?= $h(url('admin')) ?>">Administration du site</a>
                <span aria-hidden="true"> / </span>
                Communication
                <span aria-hidden="true"> / </span>
                Rétrospective annuelle
            </p>
            <h1 class="pa-hero__title">Rétrospective annuelle</h1>
            <p class="pa-hero__lead">
                Comme les applications de musique, chaque joueur reçoit le bilan de son année : heures de jeu, kilomètres,
                heures de vol, blessures, actions SSE, comptes rendus, missions. Elle s’ouvre d’elle-même à la connexion après
                la publication, une seule fois, puis reste dans Mon compte. Le bilan de toute l’unité est réservé aux formules
                Standard et supérieures (module « Rétrospective annuelle de l’unité »).
            </p>
            <div class="pa-hero__actions">
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('retrospective/' . $previewYear . '?apercu=1')) ?>">Aperçu de ma rétrospective <?= $previewYear ?></a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/system/subscription-plans')) ?>">Formules d’accès</a>
            </div>
        </header>

        <div class="pa-panel">
            <?php if ($ok): ?>
                <p class="pa-flash pa-flash--ok"><?= $h((string) $ok) ?></p>
            <?php endif; ?>
            <?php if ($err): ?>
                <p class="pa-flash pa-flash--err"><?= $h((string) $err) ?></p>
            <?php endif; ?>

            <ul class="pa-kpi-strip" style="margin-bottom:1.25rem;">
                <li class="pa-kpi<?= $cal->enabled() ? '' : ' pa-kpi--warn' ?>">
                    <span class="pa-kpi__label">État</span>
                    <span class="pa-kpi__value"><?= $cal->enabled() ? 'Active' : 'Coupée' ?></span>
                    <span class="pa-kpi__hint"><?= $cal->enabled() ? 'Publiée à la date réglée' : 'Rien n’est montré aux joueurs' ?></span>
                </li>
                <li class="pa-kpi">
                    <span class="pa-kpi__label">Dernière publiée</span>
                    <span class="pa-kpi__value"><?= $current !== null ? (int) $current : '—' ?></span>
                    <span class="pa-kpi__hint"><?= $current !== null ? 'Depuis le ' . $h($fmt($cal->publishAt((int) $current))) : 'Aucune pour l’instant' ?></span>
                </li>
                <li class="pa-kpi">
                    <span class="pa-kpi__label">Prochaine</span>
                    <span class="pa-kpi__value"><?= $next ?></span>
                    <span class="pa-kpi__hint">Le <?= $h($fmt($cal->publishAt($next))) ?></span>
                </li>
                <li class="pa-kpi">
                    <span class="pa-kpi__label">Fenêtre à la connexion</span>
                    <span class="pa-kpi__value"><?= $cal->popupDays() ?> j</span>
                    <span class="pa-kpi__hint"><?= $current !== null && $cal->inPopupWindow((int) $current, $now) ? 'En cours' : 'Fermée' ?></span>
                </li>
            </ul>

            <form class="pa-card" method="post" action="<?= $h(url('admin/system/retrospective/reglages')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <p class="pa-card__kicker">Réglages</p>
                <h2 class="pa-card__title">Publication</h2>
                <p class="pa-card__help">Spotify et Apple Music publient début décembre, sur l’année en cours (janvier à fin octobre environ). Ici les chiffres vont jusqu’au jour où le joueur l’ouvre.</p>

                <div class="pa-field">
                    <label><input type="checkbox" name="enabled" value="1" <?= $cal->enabled() ? 'checked' : '' ?>> Rétrospective active</label>
                </div>
                <div class="pa-field">
                    <label for="yr_preset">Date de publication</label>
                    <select id="yr_preset" name="publish_preset">
                        <?php foreach (YearReviewCalendar::PRESETS as $k => $label): ?>
                            <option value="<?= $h($k) ?>" <?= $k === $md ? 'selected' : '' ?>><?= $h($label) ?></option>
                        <?php endforeach; ?>
                        <option value="autre" <?= $isPreset ? '' : 'selected' ?>>Autre date (MM-JJ)</option>
                    </select>
                </div>
                <div class="pa-field">
                    <label for="yr_md">Autre date (MM-JJ)</label>
                    <p class="pa-hint">Utilisée seulement avec « Autre date ». De juillet à décembre : publiée sur l’année en cours. De janvier à juin : publiée l’année suivante, sur l’année écoulée entière.</p>
                    <input id="yr_md" type="text" name="publish_md" value="<?= $h($md) ?>" placeholder="12-01" pattern="\d{1,2}-\d{1,2}" maxlength="5" style="max-width:10rem;box-sizing:border-box">
                </div>
                <div class="pa-field">
                    <label for="yr_days">Jours pendant lesquels elle s’ouvre à la connexion</label>
                    <p class="pa-hint">Une seule fois par joueur. 0 : jamais d’ouverture automatique, seulement depuis Mon compte.</p>
                    <input id="yr_days" type="number" name="popup_days" min="0" max="366" value="<?= $cal->popupDays() ?>" style="max-width:10rem;box-sizing:border-box">
                </div>
                <div class="pa-actions" style="margin-top:1rem;">
                    <button type="submit" class="pa-btn pa-btn--ink">Enregistrer</button>
                </div>
            </form>

            <form class="pa-card" method="get" action="<?= $h(url('retrospective/' . $previewYear)) ?>">
                <input type="hidden" name="apercu" value="1">
                <p class="pa-card__kicker">Aperçu</p>
                <h2 class="pa-card__title">Bilan d’une communauté</h2>
                <p class="pa-card__help">Voir le bilan collectif <?= $previewYear ?> d’une communauté, avant publication et quelle que soit sa formule.</p>
                <div class="pa-field">
                    <label for="yr_tenant">Communauté</label>
                    <select id="yr_tenant" name="communaute">
                        <?php foreach ($tenants as $t): ?>
                            <option value="<?= (int) $t['id'] ?>"><?= $h((string) $t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="pa-actions">
                    <button type="submit" class="pa-btn pa-btn--line">Ouvrir l’aperçu</button>
                </div>
            </form>

            <form class="pa-card" method="post" action="<?= $h(url('admin/system/retrospective/recalculer')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <p class="pa-card__kicker">Chiffres</p>
                <h2 class="pa-card__title">Recalculer</h2>
                <p class="pa-card__help">Les chiffres sont gardés 30 minutes par communauté (un jour pour les années passées). Recalculez après une correction des données, par exemple les heures de vol.</p>
                <div class="pa-actions">
                    <button type="submit" class="pa-btn pa-btn--line">Recalculer toutes les rétrospectives</button>
                </div>
            </form>
        </div>
    </div>
</div>
