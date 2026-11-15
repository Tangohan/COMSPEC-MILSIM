<?php
declare(strict_types=1);
/**
 * Unités liées : les liaisons de cet espace avec d'autres unités, hors hiérarchie de l'ORBAT (appui, partenaire,
 * rattachement temporaire). Deux présentations, selon $ulMode :
 * - 'block' : le bloc de l'accueil (quelques lignes, lien vers la rubrique) ;
 * - 'page' : la rubrique « Unités liées » (?vue=liaisons) avec le détail, l'historique et, pour le chef, le formulaire.
 *
 * Une liaison ne donne jamais plus de droits que le modèle de JNET : elle marque l'autre unité, permet de lui partager une
 * information (jamais un ordre) et, si le chef l'a coché, donne à ses membres la lecture de cet espace. Une unité que le
 * lecteur ne voit pas dans son ORBAT reste « Unité liée masquée ».
 *
 * @var array<string, mixed> $usw
 * @var string $base
 * @var string $label
 * @var string $ulMode
 */
use App\Services\Jnet\JnetLinkRules;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$ul = is_array($usw['links'] ?? null) ? $usw['links'] : [];
$units = is_array($ul['units'] ?? null) ? $ul['units'] : [];
$chief = !empty($ul['viewerChief']);
$ready = !empty($ul['unitReady']);
$mode = ($ulMode ?? 'block') === 'page' ? 'page' : 'block';
$tone = ['appui' => '#60a5fa', 'partenaire' => '#34d399', 'rattachement' => '#fbbf24'];
$statusTone = ['planned' => '#94a3b8', 'active' => '#34d399', 'expired' => '#94a3b8'];
$open = array_values(array_filter($units, static fn (array $l): bool => $l['status'] !== 'expired'));
$past = array_values(array_filter($units, static fn (array $l): bool => $l['status'] === 'expired'));
$arrow = '<svg class="ja-i" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
$nameOf = static function (array $l) use ($h, $arrow): string {
    if (!empty($l['hidden'])) {
        return '<b class="jl-unit__n is-hidden">Unité liée masquée</b>';
    }

    return '<a class="jl-unit__n" href="' . $h(url('jnet/u/' . (int) $l['otherId'])) . '"><b>' . $h((string) $l['otherLabel']) . '</b></a>';
};
?>
<?php if ($mode === 'block'): ?>
    <?php if ($ready && ($open !== [] || $chief)): ?>
    <section class="us-sec jl-units" aria-labelledby="jl-units-title">
        <header class="ja-sh"><h2 class="ja-disp ja-disp--sm" id="jl-units-title">Unités liées</h2><a class="ja-sh__link" href="<?= $h($base . '?vue=liaisons&direct=1') ?>"><?= $chief ? 'Gérer' : 'Détail' ?> →</a></header>
        <?php if ($open === []): ?>
            <p class="us-empty">Aucune liaison avec une autre unité. Un appui, un partenariat ou un rattachement temporaire se crée depuis la rubrique.</p>
        <?php else: ?>
            <ul class="jl-units__list">
                <?php foreach (array_slice($open, 0, 4) as $l): ?>
                    <li class="jl-unit">
                        <span class="ja-pill" style="--p: <?= $h($tone[$l['kind']] ?? '#94a3b8') ?>"><?= $h((string) $l['kindLabel']) ?></span>
                        <span class="jl-unit__t">
                            <?= $nameOf($l) ?>
                            <?php if (empty($l['hidden'])): ?><small><?= $h(JnetLinkRules::periodLabel((string) $l['startsOn'], (string) $l['endsOn'])) ?><?= $l['status'] === 'planned' ? ' · à venir' : '' ?></small><?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (count($open) > 4): ?><a class="ja-sh__link" href="<?= $h($base . '?vue=liaisons&direct=1') ?>">Les <?= count($open) ?> liaisons →</a><?php endif; ?>
        <?php endif; ?>
    </section>
    <?php endif; ?>
<?php else: ?>
    <header class="us-page">
        <h1 class="ja-disp ja-disp--md">Unités liées.</h1>
        <p>Les liaisons de <?= $h($label) ?> avec des unités qui ne sont pas dans sa filiation de l’ORBAT : appui, partenaire, rattachement temporaire. Une liaison n’ajoute aucun droit : elle marque l’autre unité, permet de lui partager une information (jamais un ordre), et ne donne la lecture de l’espace que si le chef l’a coché.</p>
    </header>
    <?php if (!$ready): ?>
        <div class="jn-empty"><p>Les liaisons d’unités arrivent avec la prochaine mise à jour du site (étape jnet_links).</p></div>
    <?php else: ?>
        <?php if ($open === []): ?>
            <div class="jn-empty"><p>Aucune liaison avec une autre unité pour l’instant.<?= $chief ? ' Créez la première ci-dessous.' : '' ?></p></div>
        <?php else: ?>
            <ul class="jl-units__rows">
                <?php foreach ($open as $l): ?>
                    <li class="jl-urow">
                        <div class="jl-urow__top">
                            <span class="ja-pill" style="--p: <?= $h($tone[$l['kind']] ?? '#94a3b8') ?>"><?= $h((string) $l['kindLabel']) ?></span>
                            <span class="ja-pill" style="--p: <?= $h($statusTone[$l['status']] ?? '#94a3b8') ?>"><?= $h((string) $l['statusLabel']) ?></span>
                        </div>
                        <?php if (!empty($l['hidden'])): ?>
                            <p class="jl-urow__s"><b class="is-hidden">Unité liée masquée</b></p>
                            <p class="uw-note">Cette unité n’apparaît pas dans votre organigramme : son nom n’est pas affiché.</p>
                        <?php else: ?>
                            <p class="jl-urow__s"><?= $h((string) $l['sentence']) ?>.</p>
                            <p class="jl-urow__m"><?= $nameOf($l) ?> · <?= $h((string) $l['period']) ?></p>
                            <?php if ((string) $l['note'] !== ''): ?><p class="uw-note"><?= $h((string) $l['note']) ?></p><?php endif; ?>
                            <ul class="jl-urow__fx">
                                <li>Une information peut être partagée entre les deux unités, depuis « Partager ». Jamais un ordre.</li>
                                <?php if (!empty($l['grantRead'])): ?><li>Les membres de <?= $h((string) $l['grantTo']) ?> peuvent lire l’espace de <?= $h((string) $l['grantFrom']) ?> (lecture seule).</li><?php endif; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if (!empty($l['canEnd'])): ?>
                            <details class="uw-relay jl-urow__end">
                                <summary>Mettre fin à la liaison</summary>
                                <form method="post" action="<?= $h($base . '/liaisons/' . (int) $l['id'] . '/fin') ?>">
                                    <?= \App\Core\Csrf::field() ?>
                                    <p class="uw-note">La liaison cesse tout de suite : le marquage, le partage et la lecture accordée s’arrêtent. L’historique reste visible du chef.</p>
                                    <button class="ja-b2 sm uw-bad" type="submit">Mettre fin maintenant</button>
                                </form>
                            </details>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($past !== []): ?>
            <section class="us-sec" aria-labelledby="jl-past-title">
                <header class="ja-sh"><h2 class="ja-disp ja-disp--sm" id="jl-past-title">Échues et terminées</h2></header>
                <ul class="jl-units__rows jl-units__rows--past">
                    <?php foreach ($past as $l): ?>
                        <li class="jl-urow">
                            <div class="jl-urow__top"><span class="ja-pill" style="--p: #94a3b8"><?= $h((string) $l['kindLabel']) ?> · <?= $h((string) $l['statusLabel']) ?></span></div>
                            <?php if (!empty($l['hidden'])): ?>
                                <p class="jl-urow__s"><b class="is-hidden">Unité liée masquée</b></p>
                            <?php else: ?>
                                <p class="jl-urow__s"><?= $h((string) $l['sentence']) ?>.</p>
                                <p class="jl-urow__m"><?= $h((string) $l['period']) ?></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if ($chief): ?>
            <section class="us-sec jl-new" aria-labelledby="jl-new-title">
                <header class="ja-sh"><h2 class="ja-disp ja-disp--sm" id="jl-new-title">Nouvelle liaison</h2></header>
                <?php $partners = is_array($ul['partners'] ?? null) ? $ul['partners'] : []; ?>
                <?php if ($partners === []): ?>
                    <p class="us-empty">Aucune autre unité hors filiation à lier : les unités au-dessus ou en dessous de <?= $h($label) ?> sont déjà reliées par l’ORBAT.</p>
                <?php else: ?>
                <form class="jl-form" method="post" action="<?= $h($base . '/liaisons') ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <label class="uw-field" for="jl-partner">Unité à lier</label>
                    <select id="jl-partner" name="partner" required>
                        <option value="">Choisir une unité</option>
                        <?php foreach ($partners as $p): ?><option value="<?= (int) $p['id'] ?>"><?= $h((string) $p['label']) ?></option><?php endforeach; ?>
                    </select>
                    <label class="uw-field" for="jl-kind">Type de liaison</label>
                    <select id="jl-kind" name="kind" required>
                        <?php foreach (JnetLinkRules::UNIT_KINDS as $key => $name): ?><option value="<?= $h($key) ?>"><?= $h($name) ?></option><?php endforeach; ?>
                    </select>
                    <label class="uw-field" for="jl-direction">Sens <small>(sans effet pour un partenaire : la liaison est réciproque)</small></label>
                    <select id="jl-direction" name="direction">
                        <option value="out">Cette unité appuie ou accueille l’autre</option>
                        <option value="in">Cette unité est appuyée par l’autre ou lui est rattachée</option>
                    </select>
                    <div class="jl-form__dates">
                        <div><label class="uw-field" for="jl-start">Début <small>(facultatif)</small></label><input id="jl-start" type="date" name="starts_on"></div>
                        <div><label class="uw-field" for="jl-end">Fin <small>(facultatif, conseillée pour un rattachement)</small></label><input id="jl-end" type="date" name="ends_on"></div>
                    </div>
                    <label class="uw-field" for="jl-note">Précision <small>(facultatif)</small></label>
                    <input id="jl-note" type="text" name="note" maxlength="<?= (int) JnetLinkRules::NOTE_MAX ?>" placeholder="Par exemple : appui feu pour l’opération en cours" autocomplete="off">
                    <label class="jn-check jl-form__grant"><input type="checkbox" name="grant_read" value="1"><span><b>Donner à l’autre unité la lecture de l’espace de <?= $h($label) ?></b><small>Lecture seule : aucune écriture, aucune publication. Cette case n’a d’effet que si l’espace est réservé à certaines unités.</small></span></label>
                    <p class="uw-note">Une liaison ne donne jamais le droit de publier un ordre. Elle cesse à la date de fin, ou dès que vous y mettez fin.</p>
                    <div class="uw-form-actions"><button class="ja-b1 sm" type="submit">Créer la liaison</button></div>
                </form>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <p class="us-note">Seul le chef de l’unité et l’encadrement de l’organisation créent une liaison.</p>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
