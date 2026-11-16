<?php
declare(strict_types=1);

/**
 * Contenu d'un widget de l'accueil du back-office (inclus par _home_widgets.php pour chaque widget).
 * $hwCompact : widget vide, rendu sur une rangée (titre, état, action utile).
 *
 * @var string $hwId
 * @var string $hwDom
 * @var string $hwName
 * @var bool $hwCompact
 * @var bool $hwEdit
 * @var array<string, mixed> $hwR
 * @var callable(string): string $pilH
 */

use App\Services\BackOffice\Home\HomeLayout;

$hwT = $hwDom . '-t';
$hwDesc = $hwHint($hwId);
/** En-tête d'une carte : titre, une ligne de description, lien ou compteur à droite. */
$hwHead = static function (string $title, string $titleId, string $desc, string $aside = '') use ($pilH): string {
    return '<header class="dw-card__h"><div class="dw-card__ttl"><h2 class="dw-card__title" id="' . $pilH($titleId) . '">' . $pilH($title) . '</h2>'
        . ($desc !== '' ? '<p class="dw-card__desc">' . $pilH($desc) . '</p>' : '') . '</div>' . $aside . '</header>';
};
$hwLink = static fn (?string $href, string $label): string => $href !== null && $href !== '' ? '<a class="pil-link" href="' . $pilH($href) . '">' . $pilH($label) . '</a>' : '';
/** Version compacte : une rangée avec l'état « rien pour l'instant » et une action utile. */
$hwCompactCard = static function (string $title, string $titleId, string $state, ?string $href = null, string $action = '') use ($pilH): string {
    return '<section class="dw-card dw-card--compact" aria-labelledby="' . $pilH($titleId) . '"><h2 class="dw-card__title" id="' . $pilH($titleId) . '">' . $pilH($title) . '</h2>'
        . '<p class="dw-card__state">' . $pilH($state) . '</p>'
        . ($href !== null && $href !== '' && $action !== '' ? '<a class="dw-btn dw-btn--sm" href="' . $pilH($href) . '">' . $pilH($action) . '</a>' : '')
        . '</section>';
};
$hwDuration = static function (int $s): string {
    $m = intdiv(max(0, $s), 60);

    return $m >= 60 ? intdiv($m, 60) . ' h ' . sprintf('%02d', $m % 60) : $m . ' min';
};
$hwKpiIndex = ['kpi_members' => 0, 'kpi_presence' => 1, 'kpi_month' => 2, 'kpi_files' => 3];
?>
<?php if (isset($hwKpiIndex[$hwId])): ?>
    <?php $pilKpi = $pilKpis[$hwKpiIndex[$hwId]] ?? null; ?>
    <?php if ($pilKpi === null): ?>
    <section class="dw-card dw-tile" aria-label="<?= $pilH($hwName) ?>"><p class="dw-tile__label"><?= $pilH($hwName) ?></p><p class="dw-tile__v"><b>—</b></p><p class="dw-tile__note">Donnée indisponible pour le moment.</p></section>
    <?php else: ?>
    <section class="dw-card dw-tile" aria-label="<?= $pilH((string) $pilKpi['label']) ?>">
        <p class="dw-tile__label"><?= $pilH((string) $pilKpi['label']) ?></p>
        <div class="dw-tile__v">
            <b><?= $pilH((string) $pilKpi['value']) ?></b>
            <?php if ((string) $pilKpi['unit'] !== ''): ?><span><?= $pilH((string) $pilKpi['unit']) ?></span><?php endif; ?>
            <?php if (is_array($pilKpi['badge'] ?? null)): ?><span class="pil-dl pil-dl--<?= $pilH((string) $pilKpi['badge']['tone']) ?>"><?= $pilH((string) $pilKpi['badge']['text']) ?></span><?php endif; ?>
            <?= $pilSpark(is_array($pilKpi['spark'] ?? null) ? $pilKpi['spark'] : null, (string) $pilKpi['spark_tone']) ?>
        </div>
        <p class="dw-tile__note">
            <?php if (!empty($pilKpi['note_href'])): ?>
            <a href="<?= $pilH((string) $pilKpi['note_href']) ?>"><?= $pilH((string) $pilKpi['note']) ?></a>
            <?php else: ?>
            <?= $pilH((string) $pilKpi['note']) ?>
            <?php endif; ?>
        </p>
    </section>
    <?php endif; ?>

<?php elseif ($hwId === 'operation'): ?>
    <?php if ($pilHero !== null): ?>
    <?php $pilR = $pilHero['rsvp']; $pilCover = (string) ($pilHero['cover_url'] ?? ''); ?>
    <section class="bo-band bo-band--emerald pil-op" aria-labelledby="pil-op-title">
        <?php if ($pilCover !== ''): ?>
        <div class="bo-band__media" style="background-image:url('<?= $pilH($pilCover) ?>')" aria-hidden="true"></div>
        <?php else: ?>
        <div class="bo-band__media bo-band__media--fallback" aria-hidden="true"></div>
        <?php endif; ?>
        <div class="bo-band__glow" aria-hidden="true"></div>
        <div class="bo-band__veil" aria-hidden="true"></div>
        <div class="bo-band__body">
            <div class="bo-band__identity">
                <span class="bo-band__index" aria-hidden="true">/ 01</span>
                <div class="bo-band__who">
                    <h2 class="bo-band__title" id="pil-op-title"><?= $pilH((string) $pilHero['title']) ?></h2>
                    <?php if ($pilHero['date_label'] !== '' || $pilHero['location'] !== ''): ?>
                    <p class="bo-band__line pil-op__det">
                        <?php if ($pilHero['date_label'] !== ''): ?>
                        <span><?= $pilIcon('cal') ?><b><?= $pilH((string) $pilHero['date_label']) ?></b> <?= $pilH((string) $pilHero['time_label']) ?></span>
                        <?php endif; ?>
                        <?php if ($pilHero['location'] !== ''): ?>
                        <span><?= $pilIcon('pin') ?><b><?= $pilH((string) $pilHero['location']) ?></b></span>
                        <?php endif; ?>
                    </p>
                    <?php endif; ?>
                    <p class="bo-band__tag"><?= $pilH((string) $pilHero['kicker']) ?><?= $pilHero['campaign'] !== '' ? ' · ' . $pilH((string) $pilHero['campaign']) : '' ?></p>
                </div>
            </div>
            <div class="bo-band__side">
                <?php if (!empty($pilHero['ongoing'])): ?>
                <p class="bo-band__meta"><span>En cours</span> depuis <?= $pilH((string) $pilHero['time_label']) ?></p>
                <?php elseif (is_array($pilHero['countdown'] ?? null)): ?>
                <p class="bo-band__meta pil-cd" data-pil-countdown="<?= $pilH((string) $pilHero['starts_at_iso']) ?>" role="timer" aria-label="Temps restant avant le début">
                    <span>Début dans</span>
                    <b data-cd="d"><?= sprintf('%02d', (int) $pilHero['countdown']['days']) ?></b> j
                    <b data-cd="h"><?= sprintf('%02d', (int) $pilHero['countdown']['hours']) ?></b> h
                    <b data-cd="m"><?= sprintf('%02d', (int) $pilHero['countdown']['minutes']) ?></b> min
                </p>
                <?php endif; ?>
                <div class="bo-band__row">
                    <?php if ($pilHero['href'] !== null): ?>
                    <div class="bo-band__actions">
                        <?php if (!empty($pilHero['answers_href'])): ?>
                        <a class="bo-band__btn" href="<?= $pilH((string) $pilHero['answers_href']) ?>">Voir les réponses</a>
                        <?php endif; ?>
                        <a class="bo-band__btn bo-band__btn--solid" href="<?= $pilH((string) $pilHero['href']) ?>">Ouvrir la fiche</a>
                    </div>
                    <?php endif; ?>
                    <p class="bo-figure is-hot"><strong><?= (int) $pilR['yes'] ?><small> / <?= (int) $pilR['base'] ?></small></strong><span>présences confirmées</span></p>
                </div>
            </div>
            <div class="bo-band__foot pil-op__rsvp">
                <div class="pil-rb" role="img" aria-label="<?= (int) $pilR['yes'] ?> confirmés, <?= (int) $pilR['maybe'] ?> peut-être, <?= (int) $pilR['no'] ?> déclinés, <?= (int) $pilR['no_reply'] ?> sans réponse">
                    <i class="pil-rb__yes" style="width:<?= $pilH((string) $pilR['pct']['yes']) ?>%"></i><i class="pil-rb__maybe" style="width:<?= $pilH((string) $pilR['pct']['maybe']) ?>%"></i><i class="pil-rb__no" style="width:<?= $pilH((string) $pilR['pct']['no']) ?>%"></i>
                </div>
                <div class="pil-rl">
                    <span><em class="pil-rl__yes"></em><b><?= (int) $pilR['yes'] ?></b> <?= (int) $pilR['yes'] > 1 ? 'confirmés' : 'confirmé' ?></span>
                    <span><em class="pil-rl__maybe"></em><b><?= (int) $pilR['maybe'] ?></b> peut-être</span>
                    <span><em class="pil-rl__no"></em><b><?= (int) $pilR['no'] ?></b> <?= (int) $pilR['no'] > 1 ? 'déclinés' : 'décliné' ?></span>
                    <span><em class="pil-rl__none"></em><b><?= (int) $pilR['no_reply'] ?></b> sans réponse</span>
                </div>
            </div>
        </div>
    </section>
    <?php else: ?>
    <section class="bo-band bo-band--empty bo-band--sky pil-op pil-op--empty" aria-labelledby="pil-op-title">
        <div class="bo-band__media bo-band__media--fallback" aria-hidden="true"></div>
        <div class="bo-band__glow" aria-hidden="true"></div>
        <div class="bo-band__veil" aria-hidden="true"></div>
        <div class="bo-band__body">
            <div class="bo-band__identity">
                <span class="bo-band__index" aria-hidden="true">/ 01</span>
                <div class="bo-band__who">
                    <h2 class="bo-band__title" id="pil-op-title">À planifier</h2>
                    <p class="bo-band__line">Aucune activité n’est programmée. Créez le prochain créneau pour que les membres puissent répondre.</p>
                </div>
            </div>
            <?php if (($pilLinks['events'] ?? null) !== null): ?>
            <div class="bo-band__side">
                <div class="bo-band__actions">
                    <a class="bo-band__btn bo-band__btn--solid" href="<?= $pilH((string) $pilLinks['events']) ?>">Programmer une activité <span class="bo-band__arrow" aria-hidden="true">→</span></a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

<?php elseif ($hwId === 'decisions'): ?>
    <?php if ($pilDecisions['items'] === []): ?>
    <?= $hwCompactCard('À décider', 'pil-dec-title', 'Tout est à jour : plus rien n’attend votre validation.') ?>
    <?php else: ?>
    <section class="dw-card" aria-labelledby="pil-dec-title">
        <?= $hwHead('À décider', 'pil-dec-title', $hwDesc, '<span class="pil-count" aria-label="' . $pilH((int) $pilDecisions['total'] . ' en attente') . '">' . (int) $pilDecisions['total'] . '</span>') ?>
        <div class="dw-card__body">
            <ul class="pil-dec">
                <?php foreach ($pilDecisions['items'] as $pilItem): ?>
                <li class="pil-dec__it">
                    <span class="pil-dec__ic pil-tone--<?= $pilH((string) $pilItem['tone']) ?>"><?= $pilIcon((string) $pilItem['icon']) ?></span>
                    <span class="pil-dec__txt"><b><?= $pilH((string) $pilItem['title']) ?></b><small><?= $pilH((string) $pilItem['meta']) ?></small></span>
                    <a class="pil-btn pil-btn--sm" href="<?= $pilH((string) $pilItem['href']) ?>"><?= $pilH((string) $pilItem['cta']) ?></a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
    <?php endif; ?>

<?php elseif ($hwId === 'tasks'): ?>
    <?php if ($homeTasks === []): ?>
    <?= $hwCompactCard('Tâches en attente', 'pil-task-title', 'Rien en attente : candidatures, corrections, signalements et mise à niveau sont à jour.') ?>
    <?php else: ?>
    <section class="dw-card" aria-labelledby="pil-task-title">
        <?= $hwHead('Tâches en attente', 'pil-task-title', $hwDesc, $homeTaskTotal > 0 ? '<span class="pil-count" aria-label="' . $pilH((int) $homeTaskTotal . ' en attente') . '">' . $pilH(\App\Services\BackOffice\Menu\BackOfficeMenuBadges::format($homeTaskTotal)) . '</span>' : '') ?>
        <div class="dw-card__body">
            <ul class="home-tasks">
                <?php $homeBandN = 0; foreach ($homeTasks as $homeTaskN => $homeTask): ?>
                <?php $homeTd = ($homeTask['detail'] ?? null) instanceof \App\Services\BackOffice\Menu\PendingTaskDetail ? $homeTask['detail'] : null; ?>
                <?php if ($homeTd !== null): ?>
                <?php
                // Textes déjà composés dans la langue de la personne (formule) : protégés de la traduction de la page.
                $homeTdNo = $homeTd->localized ? ' translate="no" lang="' . $pilH($homeTd->lang) . '"' : '';
                $homeTdAccent = !empty($homeTask['important']) ? 'rose' : ['amber', 'sky', 'violet', 'emerald'][$homeBandN % 4];
                ?>
                <li class="home-task">
                    <article class="bo-band bo-band--compact bo-band--<?= $homeTdAccent ?>" style="--bo-band-i:<?= (int) $homeBandN ?>" aria-labelledby="home-task-title-<?= (int) $homeTaskN ?>">
                        <div class="bo-band__media bo-band__media--fallback" aria-hidden="true"></div>
                        <div class="bo-band__glow" aria-hidden="true"></div>
                        <div class="bo-band__veil" aria-hidden="true"></div>
                        <div class="bo-band__body">
                            <div class="bo-band__identity">
                                <span class="bo-band__index" aria-hidden="true">/ <?= sprintf('%02d', $homeBandN + 1) ?></span>
                                <div class="bo-band__who">
                                    <h3 class="bo-band__title home-task__title" id="home-task-title-<?= (int) $homeTaskN ?>"<?= $homeTdNo ?>><?= $pilH($homeTd->title !== '' ? $homeTd->title : (string) $homeTask['title']) ?></h3>
                                    <p class="bo-band__line home-task__why"<?= $homeTdNo ?>><?= $pilH($homeTd->explain) ?></p>
                                </div>
                            </div>
                            <div class="bo-band__side">
                                <p class="bo-figure<?= !empty($homeTask['important']) ? ' is-hot' : '' ?>"><strong><?= $pilH(\App\Services\BackOffice\Menu\BackOfficeMenuBadges::format((int) $homeTask['count'])) ?></strong><span>en attente</span></p>
                                <a class="bo-band__btn bo-band__btn--solid home-task__btn" href="<?= $pilH(url($homeTd->path)) ?>"><span<?= $homeTdNo ?>><?= $pilH($homeTd->button) ?></span> <span class="bo-band__arrow" aria-hidden="true">→</span></a>
                            </div>
                            <details class="home-task__more">
                                <summary>Que se passe-t-il si j’attends ?</summary>
                                <dl class="home-task__facts">
                                    <div><dt>Si vous ne faites rien</dt><dd<?= $homeTdNo ?>><?= $pilH($homeTd->consequence) ?></dd></div>
                                    <div><dt>Ce qu’il faut faire</dt><dd<?= $homeTdNo ?>><?= $pilH($homeTd->action) ?></dd></div>
                                    <?php if ($homeTd->help !== ''): ?>
                                    <div><dt>Qu’est-ce que c’est ?</dt><dd<?= $homeTdNo ?>><?= $pilH($homeTd->help) ?></dd></div>
                                    <?php endif; ?>
                                    <?php if ($homeTd->who !== ''): ?>
                                    <div><dt>Qui peut le faire ?</dt><dd<?= $homeTdNo ?>><?= $pilH($homeTd->who) ?></dd></div>
                                    <?php endif; ?>
                                </dl>
                            </details>
                        </div>
                    </article>
                </li>
                <?php $homeBandN++; ?>
                <?php else: ?>
                <li>
                    <a href="<?= $pilH(url($homeTask['path'])) ?>" class="home-tasks__it">
                        <span class="ath-mbadge <?= !empty($homeTask['important']) ? 'ath-mbadge--important' : 'ath-mbadge--notif' ?>"><?= $pilH(\App\Services\BackOffice\Menu\BackOfficeMenuBadges::format((int) $homeTask['count'])) ?></span>
                        <span><?= $pilH((string) $homeTask['title']) ?></span>
                    </a>
                </li>
                <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
    <?php endif; ?>

<?php elseif ($hwId === 'participation'): ?>
    <?php if ($pilPart['bars'] === []): ?>
    <?= $hwCompactCard('Participation', 'pil-part-title', 'Aucune activité terminée pour l’instant : le graphique se remplira après les premiers créneaux pointés.', $pilLinks['events'] ?? null, 'Programmer une activité') ?>
    <?php else: ?>
    <section class="dw-card dw-chart" aria-labelledby="pil-part-title">
        <?= $hwHead('Participation' . (count($pilPart['bars']) > 1 ? ' · ' . count($pilPart['bars']) . ' dernières' : ' · dernière'), 'pil-part-title', empty($pilPart['has_checkins']) ? 'Aucun pointage enregistré : seuls les inscrits apparaissent.' : $hwDesc, '<div class="pil-lg" aria-hidden="true"><span><em class="pil-lg__present"></em>Présents</span><span><em class="pil-lg__reg"></em>Inscrits</span></div>') ?>
        <div class="dw-chart__plot">
            <div class="pil-chart" style="--pil-cols:<?= count($pilPart['bars']) ?>" role="list" aria-label="Inscrits et présents des dernières activités">
                <?php foreach ($pilPart['gridlines'] as $pilGl): ?>
                <div class="pil-chart__gl" style="bottom:<?= round((int) $pilGl / max(1, (int) $pilPart['scale']) * 100, 2) ?>%" aria-hidden="true"><span><?= (int) $pilGl ?></span></div>
                <?php endforeach; ?>
                <?php foreach ($pilPart['bars'] as $pilBar): ?>
                <?php $pilTip = $pilBar['title'] . ($pilBar['date'] !== '' ? ' (' . $pilBar['date'] . ')' : '') . ' · ' . $pilBar['present'] . ' présent' . ($pilBar['present'] > 1 ? 's' : '') . ' sur ' . $pilBar['registered'] . ' inscrit' . ($pilBar['registered'] > 1 ? 's' : ''); ?>
                <div class="pil-col" role="listitem" tabindex="0" aria-label="<?= $pilH($pilTip) ?>">
                    <div class="pil-col__bar" style="height:<?= $pilH((string) $pilBar['outer_pct']) ?>%"><i style="height:<?= $pilH((string) $pilBar['inner_pct']) ?>%"></i></div>
                    <span class="pil-col__tip" aria-hidden="true"><?= $pilH($pilTip) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="pil-xl" style="--pil-cols:<?= count($pilPart['bars']) ?>" aria-hidden="true">
                <?php foreach ($pilPart['bars'] as $pilBar): ?>
                <span title="<?= $pilH((string) $pilBar['title']) ?>"><?= $pilH((string) $pilBar['short']) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

<?php elseif ($hwId === 'units'): ?>
    <?php if ($pilUnits === []): ?>
    <?= $hwCompactCard('État des unités', 'pil-units-title', 'Aucune unité avec des postes ou des membres pour l’instant.', $pilLinks['effectifs'] ?? null, 'Construire l’organigramme') ?>
    <?php else: ?>
    <section class="dw-card" aria-labelledby="pil-units-title">
        <?= $hwHead('État des unités', 'pil-units-title', 'Score : postes pourvus, qualifications à jour et activité récente.', $hwLink($pilLinks['readiness'] ?? null, 'Détail par unité')) ?>
        <div class="dw-card__body" tabindex="0" role="region" aria-labelledby="pil-units-title">
            <ul class="pil-units">
                <?php foreach ($pilUnits as $pilUnit): ?>
                <li class="pil-u pil-u--<?= $pilH((string) $pilUnit['tone']) ?>">
                    <span class="pil-u__sw" aria-hidden="true"></span>
                    <div class="pil-u__body">
                        <p class="pil-u__name"><b><?= $pilH((string) $pilUnit['name']) ?></b><small><?= $pilH((string) $pilUnit['posts']) ?></small></p>
                        <div class="pil-u__tr" aria-hidden="true"><i style="width:<?= (int) $pilUnit['score'] ?>%"></i></div>
                    </div>
                    <span class="pil-u__pc"><?= (int) $pilUnit['score'] ?> %</span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
    <?php endif; ?>

<?php elseif ($hwId === 'pipeline'): ?>
    <?php if (!empty($hwR['empty']) && $hwCompact): ?>
    <?= $hwCompactCard('Recrutement', 'pil-rec-title', 'Aucune candidature en cours.', $pilLinks['recruitments'] ?? null, 'Voir le recrutement') ?>
    <?php else: ?>
    <section class="dw-card" aria-labelledby="pil-rec-title">
        <?= $hwHead('Recrutement', 'pil-rec-title', $hwDesc, $hwLink($pilLinks['recruitments'] ?? null, 'Tout voir')) ?>
        <div class="dw-card__body">
            <div class="pil-pipe">
                <?php foreach ($pilPipeline['tiles'] as $pilTile): ?>
                <div class="<?= !empty($pilTile['hot']) ? 'is-hot' : '' ?>"><b><?= (int) $pilTile['n'] ?></b><small><?= $pilH((string) $pilTile['label']) ?></small></div>
                <?php endforeach; ?>
            </div>
            <?php if ($pilPipeline['people'] === []): ?>
            <p class="pil-empty">Aucune candidature en cours.</p>
            <?php else: ?>
            <ul class="pil-ppl">
                <?php foreach ($pilPipeline['people'] as $pilP): ?>
                <li>
                    <a class="pil-ppl__p" href="<?= $pilH((string) $pilP['href']) ?>">
                        <span class="pil-av"><?= $pilH((string) $pilP['initials']) ?></span>
                        <span class="pil-ppl__txt"><b><?= $pilH((string) $pilP['name']) ?></b><small><?= $pilH((string) $pilP['meta']) ?></small></span>
                        <span class="pil-tag pil-tone--<?= $pilH((string) $pilP['tone']) ?>"><?= $pilH((string) $pilP['tag']) ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

<?php elseif ($hwId === 'agenda'): ?>
    <?php if ($pilAgenda['items'] === []): ?>
    <?= $hwCompactCard('Agenda à venir', 'pil-ag-title', 'Rien n’est prévu pour le moment.', $pilLinks['events'] ?? null, 'Calendrier') ?>
    <?php else: ?>
    <section class="dw-card" aria-labelledby="pil-ag-title">
        <?= $hwHead('Agenda · ' . mb_strtolower((string) $pilAgenda['title']), 'pil-ag-title', $hwDesc, $hwLink($pilLinks['events'] ?? null, 'Calendrier')) ?>
        <div class="dw-card__body">
            <ul class="pil-ag">
                <?php foreach ($pilAgenda['items'] as $pilEv): ?>
                <li class="pil-ev">
                    <span class="pil-ev__dt<?= !empty($pilEv['highlight']) ? ' is-op' : '' ?>"><small><?= $pilH((string) $pilEv['day']) ?></small><b><?= $pilH((string) $pilEv['num']) ?></b></span>
                    <span class="pil-ev__txt">
                        <?php if (!empty($pilEv['href'])): ?>
                        <a href="<?= $pilH((string) $pilEv['href']) ?>"><b><?= $pilH((string) $pilEv['title']) ?></b></a>
                        <?php else: ?>
                        <b><?= $pilH((string) $pilEv['title']) ?></b>
                        <?php endif; ?>
                        <small><?= $pilH((string) $pilEv['meta']) ?></small>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
    <?php endif; ?>

<?php elseif ($hwId === 'feed'): ?>
    <?php if ($pilFeed === []): ?>
    <?= $hwCompactCard('Ce qui a bougé', 'pil-feed-title', 'Aucune action récente dans la communauté.', $pilLinks['journal'] ?? null, 'Journal') ?>
    <?php else: ?>
    <section class="dw-card" aria-labelledby="pil-feed-title">
        <?= $hwHead('Ce qui a bougé', 'pil-feed-title', $hwDesc, $hwLink($pilLinks['journal'] ?? null, 'Journal')) ?>
        <div class="dw-card__body" tabindex="0" role="region" aria-labelledby="pil-feed-title">
            <ul class="pil-feed">
                <?php foreach ($pilFeed as $pilF): ?>
                <li class="pil-fi">
                    <span class="pil-av pil-av--<?= $pilH((string) $pilF['tone']) ?>"><?= $pilH((string) $pilF['initials']) ?></span>
                    <p><b><?= $pilH((string) $pilF['actor']) ?></b> · <?= $pilH((string) $pilF['action']) ?><?php if ((string) $pilF['target'] !== ''): ?> <b><?= $pilH((string) $pilF['target']) ?></b><?php endif; ?><?php if ((string) $pilF['when'] !== ''): ?><small><?= $pilH((string) $pilF['when']) ?></small><?php endif; ?></p>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
    <?php endif; ?>

<?php elseif ($hwId === 'usage'): ?>
    <?php if ($pilDays === [] || $hwPortalSum === 0): ?>
    <?= $hwCompactCard('Connexions sur ' . $pilPeriod . ' jours', 'pil-use-title', 'Aucune connexion au site ni téléphone ATAK actif sur la période.') ?>
    <?php else: ?>
    <section class="dw-card dw-chart" aria-labelledby="pil-use-title">
        <?= $hwHead('Connexions sur ' . $pilPeriod . ' jours', 'pil-use-title', 'Site et téléphones ATAK, jour par jour.', '<div class="pil-lg" aria-hidden="true"><span><em class="pil-lg__portal"></em>Site</span><span><em class="pil-lg__atak"></em>ATAK</span></div>') ?>
        <div class="dw-chart__plot">
            <div class="pil-usage" style="--pil-cols:<?= count($pilDays) ?>" role="img" aria-label="Connexions au site et téléphones ATAK actifs sur <?= $pilPeriod ?> jours">
                <?php foreach ($pilDays as $pilI => $pilDay): ?>
                <?php $pilPv = (int) ($pilPortal[$pilI] ?? 0); $pilAv = (int) ($pilAtak[$pilI] ?? 0); ?>
                <div class="pil-usage__d" title="<?= $pilH('Jour ' . $pilDay . ' · site ' . $pilPv . ' · ATAK ' . $pilAv) ?>">
                    <i class="pil-usage__p" style="height:<?= round($pilPv / $pilMax * 100, 1) ?>%"></i>
                    <i class="pil-usage__a" style="height:<?= round($pilAv / $pilMax * 100, 1) ?>%"></i>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="pil-xl pil-xl--days" style="--pil-cols:<?= count($pilDays) ?>" aria-hidden="true">
                <?php foreach ($pilDays as $pilDay): ?><span><?= $pilH((string) $pilDay) ?></span><?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

<?php elseif ($hwId === 'messages'): ?>
    <?php if ($pilMessages === []): ?>
    <?= $hwCompactCard('Derniers messages', 'pil-msg-title', 'Aucune conversation récente dans votre messagerie.', (string) ($pilLinks['messages'] ?? url('messages')), 'Ouvrir') ?>
    <?php else: ?>
    <section class="dw-card" aria-labelledby="pil-msg-title">
        <?= $hwHead('Derniers messages', 'pil-msg-title', $hwDesc, $hwLink((string) ($pilLinks['messages'] ?? url('messages')), 'Ouvrir')) ?>
        <div class="dw-card__body">
            <ul class="pil-msgs">
                <?php foreach ($pilMessages as $pilMsg): ?>
                <?php
                $pilSubject = trim((string) ($pilMsg['subject'] ?? ''));
                $pilPreview = trim(preg_replace('/\s+/u', ' ', (string) ($pilMsg['last_preview'] ?? '')) ?? '');
                if (mb_strlen($pilPreview) > 90) {
                    $pilPreview = mb_substr($pilPreview, 0, 89) . '…';
                }
                $pilUpd = strtotime((string) ($pilMsg['updated_at'] ?? '')) ?: null;
                $pilTid = (int) ($pilMsg['id'] ?? 0);
                ?>
                <li>
                    <a class="pil-msg<?= !empty($pilMsg['thread_unread']) ? ' is-unread' : '' ?>" href="<?= $pilH($pilTid > 0 ? url('messages/' . $pilTid) : url('messages')) ?>">
                        <b><?= $pilH($pilSubject !== '' ? $pilSubject : 'Conversation') ?><?php if (!empty($pilMsg['thread_unread'])): ?><span class="pil-tag pil-tone--primary">Non lu</span><?php endif; ?></b>
                        <small><?= $pilH($pilPreview !== '' ? $pilPreview : 'Aucun aperçu') ?><?= $pilUpd !== null ? ' · ' . $pilH(\App\Support\PilotageDashboardPresenter::ago($pilUpd, time())) : '' ?></small>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
    <?php endif; ?>

<?php elseif ($hwId === 'gaps'): ?>
    <?php $hwGapRows = is_array($hwGaps['rows'] ?? null) ? $hwGaps['rows'] : []; ?>
    <?php if ($hwCompact): ?>
    <div id="dossiers-incomplets"><?= $hwCompactCard('Dossiers à compléter', 'pil-gaps-title', 'Aucun trou de dossier sur les membres actifs.') ?></div>
    <?php else: ?>
    <section class="dw-card" id="dossiers-incomplets" aria-labelledby="pil-gaps-title">
        <?= $hwHead('Dossiers à compléter', 'pil-gaps-title', $hwGapTotal > 0 ? $pilPlural($hwGapTotal, 'membre actif concerné', 'membres actifs concernés') . ' : fonction, grade, rôle ou image manquants.' : $hwDesc, $hwLink($pilLinks['effectifs'] ?? null, 'Effectifs')) ?>
        <div class="dw-card__body">
            <?php if ($hwGapError !== ''): ?>
            <p class="pil-empty"><?= $pilH($hwGapError) ?></p>
            <?php elseif ($hwGapRows === []): ?>
            <p class="pil-empty">Tous les profils actifs ont une fonction, un grade, un rôle et une image opérateur.</p>
            <?php else: ?>
            <ul class="dw-gaps">
                <?php foreach ($hwGapRows as $hwGap): ?>
                <?php if (!is_array($hwGap)) { continue; } ?>
                <?php
                $hwGapName = trim((string) ($hwGap['display_name'] ?? '')) ?: 'Membre';
                $hwGapMiss = array_keys(array_filter([
                    'Fonction' => !empty($hwGap['missing_function']),
                    'Grade' => !empty($hwGap['missing_rank']),
                    'Rôle' => !empty($hwGap['missing_role']),
                    'Image' => !empty($hwGap['missing_operator_image']),
                ]));
                $hwGapUrl = trim((string) ($hwGap['fiche_url'] ?? ''));
                ?>
                <li class="dw-gaps__it">
                    <?php if ($hwGapUrl !== ''): ?><a href="<?= $pilH($hwGapUrl) ?>"><b translate="no"><?= $pilH($hwGapName) ?></b></a><?php else: ?><b translate="no"><?= $pilH($hwGapName) ?></b><?php endif; ?>
                    <span class="dw-gaps__miss"><?php foreach ($hwGapMiss as $hwM): ?><span class="pil-tag pil-tone--warning"><?= $pilH($hwM) ?></span><?php endforeach; ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

<?php elseif ($hwId === 'availability'): ?>
    <?php
    $hwAvDays = (int) ($hwAvail['days'] ?? HomeLayout::AVAILABILITY_DAYS);
    $hwAvTotal = (int) ($hwAvail['total'] ?? 0);
    $hwAvHref = (string) ($hwAvail['href'] ?? '');
    ?>
    <?php if (empty($hwAvail['ready'])): ?>
    <?= $hwCompactCard('Disponibilité à vérifier', 'pil-av-title', 'Le suivi des sessions de jeu n’est pas encore actif sur le site.') ?>
    <?php elseif ($hwAvTotal === 0): ?>
    <?= $hwCompactCard('Disponibilité à vérifier', 'pil-av-title', 'Tous les membres actifs ont joué une session qui compte depuis ' . $hwAvDays . ' jours.') ?>
    <?php else: ?>
    <section class="dw-card" aria-labelledby="pil-av-title">
        <?= $hwHead('Disponibilité à vérifier', 'pil-av-title', 'Sans session de jeu qui compte ni vérification depuis ' . $hwAvDays . ' jours.', $hwLink($hwAvHref, 'Vérifier')) ?>
        <div class="dw-card__body">
            <p class="dw-figure"><b><?= $hwAvTotal ?></b><span><?= $hwAvTotal > 1 ? 'membres à vérifier' : 'membre à vérifier' ?></span></p>
            <ul class="dw-rows">
                <?php foreach (is_array($hwAvail['rows'] ?? null) ? $hwAvail['rows'] : [] as $hwAv): ?>
                <li class="dw-rows__it">
                    <a href="<?= $pilH(effectifs_workspace_url('membres/' . (int) $hwAv['id'])) ?>"><b translate="no"><?= $pilH((string) ($hwAv['name'] !== '' ? $hwAv['name'] : 'Membre')) ?></b></a>
                    <small><?= empty($hwAv['linked']) ? 'Steam non relié' : ($hwAv['last_at'] !== null ? 'Dernière session ' . $pilH(mb_strtolower(\App\Support\PilotageDashboardPresenter::ago((int) $hwAv['last_at'], time()))) : 'Jamais vu en jeu') ?></small>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
    <?php endif; ?>

<?php elseif ($hwId === 'sessions'): ?>
    <?php $hwSesRows = is_array($hwSessions['rows'] ?? null) ? $hwSessions['rows'] : []; $hwSesMin = (int) ($hwSessions['min_minutes'] ?? 60); ?>
    <?php if (empty($hwSessions['ready'])): ?>
    <?= $hwCompactCard('Sessions de jeu récentes', 'pil-ses-title', 'Le suivi des sessions de jeu n’est pas encore actif sur le site.') ?>
    <?php elseif ($hwSesRows === []): ?>
    <?= $hwCompactCard('Sessions de jeu récentes', 'pil-ses-title', 'Aucune session de jeu sur les 14 derniers jours.') ?>
    <?php else: ?>
    <section class="dw-card" aria-labelledby="pil-ses-title">
        <?= $hwHead('Sessions de jeu récentes', 'pil-ses-title', 'Une session compte pour la disponibilité à partir de ' . $hwSesMin . ' min.', $hwLink((string) ($hwSessions['href'] ?? ''), 'Effectifs')) ?>
        <div class="dw-card__body">
            <ul class="dw-rows">
                <?php foreach ($hwSesRows as $hwSes): ?>
                <?php $hwSesOk = (int) $hwSes['duration_s'] >= $hwSesMin * 60; ?>
                <li class="dw-rows__it">
                    <?php if ((int) $hwSes['user_id'] > 0): ?>
                    <a href="<?= $pilH(effectifs_workspace_url('membres/' . (int) $hwSes['user_id'])) ?>"><b translate="no"><?= $pilH((string) ($hwSes['name'] !== '' ? $hwSes['name'] : 'Membre')) ?></b></a>
                    <?php else: ?>
                    <b>Joueur non relié</b>
                    <?php endif; ?>
                    <small><?= $pilH(\App\Support\PilotageDashboardPresenter::ago((int) $hwSes['last_seen_at'], time())) ?> · <?= $pilH($hwDuration((int) $hwSes['duration_s'])) ?></small>
                    <span class="pil-tag <?= $hwSesOk ? 'pil-tone--primary' : 'pil-tone--muted' ?>"><?= $hwSesOk ? 'Comptée' : 'Trop courte' ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
    <?php endif; ?>

<?php elseif ($hwId === 'shortcuts'): ?>
    <?php if ($homeShortcuts === []): ?>
    <?= $hwCompactCard('Vos raccourcis', 'pil-sc-title', 'Aucun raccourci : cliquez sur l’étoile d’une page du menu pour l’épingler ici.') ?>
    <?php else: ?>
    <section class="dw-card" aria-labelledby="pil-sc-title">
        <?= $hwHead('Vos raccourcis', 'pil-sc-title', $hwDesc) ?>
        <div class="dw-card__body">
            <ul class="home-sc">
                <?php foreach ($homeShortcuts as $homeSc): ?>
                <li><a class="home-sc__it" href="<?= $pilH(url((string) $homeSc['path'])) ?>"><svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"></path></svg><span><?= $pilH((string) $homeSc['label']) ?></span></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
    <?php endif; ?>

<?php elseif ($hwId === 'custom'): ?>
    <?php if (($hwCustom['blocks'] ?? []) === []): ?>
    <?= $hwCompactCard('Contenu de la communauté', 'pil-cu-title', 'Aucun contenu pour l’instant : message d’accueil, consigne, vidéo ou tableau pour toute l’équipe.', !empty($hwCustom['can_edit']) ? url('back-office/accueil/bloc') : null, 'Créer le contenu') ?>
    <?php else: ?>
    <section class="dw-card" aria-labelledby="pil-cu-title">
        <?= $hwHead('Contenu de la communauté', 'pil-cu-title', $hwDesc, !empty($hwCustom['can_edit']) ? '<a class="pil-link" href="' . $pilH(url('back-office/accueil/bloc')) . '">' . $pilH('Modifier le contenu') . '</a>' : '') ?>
        <div class="dw-card__body">
            <?php $hcuNoBar = true; require base_path('views/admin/organization/_home_custom.php'); ?>
        </div>
    </section>
    <?php endif; ?>

<?php elseif (HomeLayout::isSheet($hwId)): ?>
    <?php $homeSid = HomeLayout::sheetId($hwId); ?>
    <?php if (isset($hwSheets[$homeSid])): ?>
    <section class="dw-card home-sheet" aria-label="<?= $pilH('Tableau ' . $hwSheets[$homeSid]['title']) ?>">
        <div class="dw-card__body">
            <?php $sheetView = $hwSheets[$homeSid]; $sheetCompact = true; require base_path('views/admin/organization/_sheet.php'); ?>
        </div>
    </section>
    <?php else: ?>
    <?= $hwCompactCard($hwName, $hwDom . '-t', 'Ce tableau s’affichera après l’enregistrement de la disposition.') ?>
    <?php endif; ?>
<?php endif; ?>
