<?php
declare(strict_types=1);
/**
 * Bureau SSE — signalements des alertes : tous les signalements de la communauté (onglets par état avec compteurs, lien vers
 * l'alerte), et l'onglet « À valider » : propositions d'alerte venues du jeu, à accepter ou refuser (réservé à ceux qui écrivent
 * dans « Fichiers et sujets »). Les formulaires s'ouvrent en fenêtre. Styles : sse-alerts.css (.sal-*), script : sse-alerts.js.
 *
 * @var string $tab nouveau|pris-en-compte|recoupe|ecarte|tous|a-valider
 * @var list<array<string, mixed>> $reports
 * @var array<int, array{ref: string, doc: string, href: string, readable: bool}> $alerts
 * @var array<string, int> $counts
 * @var list<array<string, mixed>> $proposals
 * @var int $proposalCount
 * @var bool $canManage
 * @var bool $ready
 * @var string $lang
 * @var string $tz
 */
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$tab = (string) ($tab ?? 'nouveau');
$reports = is_array($reports ?? null) ? $reports : [];
$alerts = is_array($alerts ?? null) ? $alerts : [];
$counts = is_array($counts ?? null) ? $counts : [];
$proposals = is_array($proposals ?? null) ? $proposals : [];
$canManage = !empty($canManage);
$lang = (string) ($lang ?? 'fr') === 'en' ? 'en' : 'fr';
$li = $lang === 'en' ? 1 : 0;
$tz = (string) ($tz ?? 'UTC');
$csrf = (string) ($csrfToken ?? \App\Core\Csrf::token());
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-alerts.css')) . '">';
$sseExtraScripts = ($sseExtraScripts ?? '') . '<script defer src="' . $h(asset_url('assets/js/sse-alerts.js')) . '"></script>';
$stamp = static function (?string $utc) use ($tz, $lang): string {
    $utc = trim((string) $utc);
    if ($utc === '') {
        return '';
    }
    try {
        $d = (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone($tz));
    } catch (\Throwable) {
        return $utc;
    }

    return $d->format($lang === 'en' ? 'Y-m-d H:i' : 'd/m/Y H:i');
};
$ico = static fn (string $d, int $s = 18): string => '<svg class="sal-i" width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="' . $d . '"/></svg>';
$icons = ['close' => 'M6 6l12 12M18 6L6 18', 'pen' => 'M4 20l4-1 11-11-3-3L5 16zM14 7l3 3', 'camera' => 'M4 8h3l1.5-2h7L17 8h3v11H4zM12 16.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z', 'ok' => 'M5 12.5l4.5 4.5L19 7.5', 'no' => 'M6 6l12 12M18 6L6 18', 'out' => 'M14 4h6v6M20 4l-9 9M18 14v6H4V6h6'];
$statuses = \App\Support\SseAlertLevel::REPORT_STATUSES;
$sure = \App\Support\SseAlertLevel::CERTAINTY;
$tabs = [
    'nouveau' => ['Nouveaux', (int) ($counts['new'] ?? 0)],
    'pris-en-compte' => ['Pris en compte', (int) ($counts['acknowledged'] ?? 0)],
    'recoupe' => ['Recoupés', (int) ($counts['corroborated'] ?? 0)],
    'ecarte' => ['Écartés', (int) ($counts['dismissed'] ?? 0)],
    'tous' => ['Tous', (int) ($counts['all'] ?? 0)],
];
if ($canManage) {
    $tabs['a-valider'] = ['À valider', (int) ($proposalCount ?? 0)];
}
$base = url(\App\Controllers\Web\SseAlertReportController::BASE);
$here = \App\Controllers\Web\SseAlertReportController::BASE . ($tab !== 'nouveau' ? '?onglet=' . $tab : '');
$dlgStatus = 'sal-dlg-status-0';
?>
<div class="sal sal-list" data-sal>
    <header class="sal-head">
        <div>
            <h1 class="sal-head__title">Signalements</h1>
            <p class="sal-desc">Ce que les membres ont vu ou appris sur les avis de recherche et les fiches de vigilance.</p>
        </div>
    </header>

    <?php if (empty($ready)): ?>
        <p class="sal-note sal-note--block">Les signalements seront disponibles dès que l’étape de base de données « alertes SSE » aura été passée par l’équipe technique.</p>
    <?php else: ?>
    <nav class="sal-tabs" aria-label="États des signalements">
        <?php foreach ($tabs as $key => [$label, $n]): ?>
            <a class="sal-tab<?= $tab === $key ? ' is-active' : '' ?>" href="<?= $h($base . ($key === 'nouveau' ? '' : '?onglet=' . $key)) ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>><span><?= $h($label) ?></span><span class="sal-tab__n<?= $n > 0 && in_array($key, ['nouveau', 'a-valider'], true) ? ' is-hot' : '' ?>"><?= $n > 99 ? '99+' : $n ?></span></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($tab === 'a-valider' && $canManage): ?>
        <?php if ($proposals === []): ?>
            <div class="sal-void sal-void--block"><b>Aucune proposition à valider</b><span>Les alertes proposées depuis le jeu par des comptes sans droit d’émission arrivent ici.</span></div>
        <?php else: ?>
            <p class="sal-desc sal-desc--list">Une proposition devient une alerte à votre acceptation : elle est émise avec vos droits.</p>
            <ol class="sal-reports">
                <?php foreach ($proposals as $p): ?>
                    <li class="sal-rep sal-rep--prop">
                        <article>
                            <header class="sal-rep__head">
                                <strong class="sal-rep__ref" translate="no"><?= $h($p['ref']) ?></strong>
                                <span class="sal-chip sal-chip--kind"><?= $p['doc'] === 'vigilance' ? 'Fiche de vigilance' : 'Avis de recherche' ?></span>
                                <span class="sal-chip sal-chip--lvl" style="--sal-lvl: <?= $h($p['level_hex']) ?>"><i aria-hidden="true"></i><?= $h($p['level_title'][$li]) ?> · <?= (int) $p['level_rank'] ?>/4</span>
                                <span class="sal-chip sal-chip--src"><?= $p['source'] === 'game' ? 'Jeu' : 'Site' ?></span>
                                <time class="sal-rep__when"><?= $h($stamp((string) $p['created_at'])) ?></time>
                            </header>
                            <p class="sal-rep__subject" translate="no">
                                <?php if ($p['subject'] !== ''): ?>
                                    <?php if ($p['subject_href'] !== ''): ?><a href="<?= $h($p['subject_href']) ?>"><?= $h($p['subject']) ?></a><?php else: ?><?= $h($p['subject']) ?><?php endif; ?>
                                <?php else: ?>
                                    <span><?= $h($p['unknown']) ?></span>
                                <?php endif; ?>
                            </p>
                            <?php if ($p['subject'] === ''): ?><p class="sal-chip sal-chip--warn">Sujet inconnu : indiquez sa référence à l’acceptation.</p><?php endif; ?>
                            <?php if ($p['text'] !== ''): ?><p class="sal-rep__body" translate="no"><?= nl2br($h($p['text'])) ?></p><?php endif; ?>
                            <dl class="sal-rep__facts" translate="no">
                                <?php if ($p['type'] !== ''): ?><div><dt>Type</dt><dd><?= $h($p['type']) ?></dd></div><?php endif; ?>
                                <?php if ($p['zone'] !== '' || $p['place'] !== '' || $p['grid'] !== ''): ?><div><dt>Zone</dt><dd><?= $h(trim(implode(' · ', array_filter([$p['zone'], $p['place'], $p['grid']])), ' ·')) ?></dd></div><?php endif; ?>
                                <?php if ($p['days'] > 0): ?><div><dt>Validité</dt><dd><?= (int) $p['days'] ?> j</dd></div><?php endif; ?>
                            </dl>
                            <?php if ($p['codes'] !== []): ?>
                                <ul class="sal-chips" aria-label="Mesures demandées"><?php foreach ($p['codes'] as $c): ?><li class="sal-chip" translate="no" title="<?= $h($lang === 'en' ? $c['en'] : $c['fr']) ?>"><b><?= $h($c['code']) ?></b><?= $h($lang === 'en' ? $c['en'] : $c['fr']) ?></li><?php endforeach; ?></ul>
                            <?php endif; ?>
                            <footer class="sal-rep__foot">
                                <span class="sal-rep__by">Proposée par <span translate="no"><?= $h($p['author'] !== '' ? $p['author'] : 'compte inconnu') ?></span></span>
                                <span class="sal-rep__btns">
                                    <button type="button" class="btn btn--ghost sal-btn sal-btn--danger" data-sal-open="sal-dlg-reject" data-sal-action="<?= $h(url('atak/sse/avis/propositions/' . (int) $p['id'] . '/decision')) ?>" data-sal-subject="<?= $h($p['ref']) ?>" aria-haspopup="dialog"><?= $ico($icons['no'], 16) ?><span>Refuser</span></button>
                                    <button type="button" class="btn sal-btn sal-btn--solid" data-sal-open="sal-dlg-accept" data-sal-action="<?= $h(url('atak/sse/avis/propositions/' . (int) $p['id'] . '/decision')) ?>" data-sal-subject="<?= $h($p['ref']) ?>" data-sal-unknown="<?= $p['subject'] === '' ? '1' : '0' ?>" aria-haspopup="dialog"><?= $ico($icons['ok'], 16) ?><span>Accepter</span></button>
                                </span>
                            </footer>
                        </article>
                    </li>
                <?php endforeach; ?>
            </ol>
            <dialog class="sal-dialog" id="sal-dlg-accept" aria-labelledby="sal-dlg-accept-title">
                <form method="post" action="" class="sal-dialog__card" data-sal-target-form>
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                    <input type="hidden" name="decision" value="accept">
                    <header class="sal-dialog__head">
                        <div><h2 id="sal-dlg-accept-title">Accepter la proposition</h2><p class="sal-desc">L’alerte est émise tout de suite, avec vos droits ; son auteur est prévenu.</p></div>
                        <button type="button" class="sal-dialog__close" data-sal-close aria-label="Fermer la fenêtre"><?= $ico($icons['close']) ?></button>
                    </header>
                    <div class="sal-dialog__body">
                        <p class="sal-subject" translate="no" data-sal-subject-out></p>
                        <label class="sal-field" for="sal-acc-subject" data-sal-unknown-field hidden><span>Référence du sujet</span><input id="sal-acc-subject" type="text" name="subject" maxlength="40" placeholder="Ex. IDT-000012" autocomplete="off"></label>
                        <label class="sal-field" for="sal-acc-note"><span>Commentaire <small>(facultatif)</small></span><textarea id="sal-acc-note" name="note" rows="3" maxlength="500" placeholder="Ce que vous avez vérifié"></textarea></label>
                    </div>
                    <footer class="sal-dialog__foot"><button type="button" class="btn btn--ghost sal-btn" data-sal-close>Annuler</button><button type="submit" class="btn sal-btn sal-btn--solid">Accepter et émettre</button></footer>
                </form>
            </dialog>
            <dialog class="sal-dialog" id="sal-dlg-reject" aria-labelledby="sal-dlg-reject-title">
                <form method="post" action="" class="sal-dialog__card" data-sal-target-form>
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                    <input type="hidden" name="decision" value="reject">
                    <header class="sal-dialog__head">
                        <div><h2 id="sal-dlg-reject-title">Refuser la proposition</h2><p class="sal-desc">Le motif est obligatoire : il est transmis à l’auteur.</p></div>
                        <button type="button" class="sal-dialog__close" data-sal-close aria-label="Fermer la fenêtre"><?= $ico($icons['close']) ?></button>
                    </header>
                    <div class="sal-dialog__body">
                        <p class="sal-subject" translate="no" data-sal-subject-out></p>
                        <label class="sal-field" for="sal-rej-note"><span>Motif du refus</span><textarea id="sal-rej-note" name="note" rows="3" maxlength="500" required placeholder="Pourquoi la proposition est refusée"></textarea></label>
                    </div>
                    <footer class="sal-dialog__foot"><button type="button" class="btn btn--ghost sal-btn" data-sal-close>Annuler</button><button type="submit" class="btn btn--danger sal-btn">Refuser</button></footer>
                </form>
            </dialog>
        <?php endif; ?>
    <?php elseif ($reports === []): ?>
        <div class="sal-void sal-void--block">
            <b><?= $tab === 'nouveau' ? 'Aucun nouveau signalement' : 'Aucun signalement dans cet état' ?></b>
            <span>Les signalements arrivent ici dès qu’un membre scanne le QR code d’un avis ou d’une fiche de vigilance.</span>
        </div>
    <?php else: ?>
        <ol class="sal-reports">
            <?php foreach ($reports as $r): ?>
                <?php $sureKey = isset($sure[$r['certainty']]) ? $r['certainty'] : 'medium'; $al = $alerts[(int) $r['link_id']] ?? ['ref' => '', 'doc' => '', 'href' => '']; ?>
                <li class="sal-rep sal-rep--<?= $h($r['status']) ?>">
                    <article>
                        <header class="sal-rep__head">
                            <strong class="sal-rep__ref" translate="no"><?= $h($r['ref']) ?></strong>
                            <span class="sal-st sal-st--<?= $h($r['status']) ?>"><?= $h($statuses[$r['status']][0] ?? $r['status']) ?></span>
                            <span class="sal-sure sal-sure--<?= $h($sureKey) ?>"><span class="sal-bars" aria-hidden="true"><?php for ($b = 1; $b <= 3; $b++): ?><i class="<?= $b <= ['low' => 1, 'medium' => 2, 'high' => 3][$sureKey] ? 'is-on' : '' ?>"></i><?php endfor; ?></span><?= $h($sure[$sureKey][0]) ?></span>
                            <span class="sal-chip sal-chip--src"><?= $r['source'] === 'game' ? 'Jeu' : 'Site' ?></span>
                            <time class="sal-rep__when"><?= $h($stamp((string) $r['seen_at'])) ?></time>
                        </header>
                        <p class="sal-rep__alert"><span><?= $al['doc'] === 'vigilance' ? 'Fiche de vigilance' : 'Avis de recherche' ?></span> <a href="<?= $h($al['href']) ?>" translate="no"><?= $h($al['ref']) ?><?= $ico($icons['out'], 14) ?></a></p>
                        <p class="sal-rep__body" translate="no"><?= nl2br($h($r['body'])) ?></p>
                        <?php if ($r['place'] !== '' || $r['grid'] !== '' || $r['direction'] !== '' || $r['vehicle'] !== ''): ?>
                            <dl class="sal-rep__facts" translate="no">
                                <?php if ($r['place'] !== '' || $r['grid'] !== ''): ?><div><dt>Lieu</dt><dd><?= $h(trim($r['place'] . ($r['grid'] !== '' ? ' · ' . $r['grid'] : ''), ' ·')) ?></dd></div><?php endif; ?>
                                <?php if ($r['direction'] !== ''): ?><div><dt>Direction</dt><dd><?= $h($r['direction']) ?></dd></div><?php endif; ?>
                                <?php if ($r['vehicle'] !== ''): ?><div><dt>Véhicule</dt><dd><?= $h($r['vehicle']) ?></dd></div><?php endif; ?>
                            </dl>
                        <?php endif; ?>
                        <?php if ($r['photo_path'] !== null): ?>
                            <?php if ($canManage): ?>
                                <?php $photoUrl = url('atak/sse/avis/signalements/' . (int) $r['id'] . '/photo'); ?>
                                <a class="sal-photo" href="<?= $h($photoUrl) ?>" target="_blank" rel="noopener"><img src="<?= $h($photoUrl) ?>" alt="Photo jointe au signalement <?= $h($r['ref']) ?>" loading="lazy" width="160" height="110"><span>Ouvrir la photo</span></a>
                            <?php else: ?>
                                <p class="sal-chip"><?= $ico($icons['camera'], 16) ?>Photo jointe, réservée aux gestionnaires</p>
                            <?php endif; ?>
                        <?php endif; ?>
                        <footer class="sal-rep__foot">
                            <span class="sal-rep__by"><span translate="no"><?= $h($r['reporter_label'] !== '' ? $r['reporter_label'] : 'Compte inconnu') ?></span> · <time><?= $h($stamp((string) $r['created_at'])) ?></time></span>
                            <?php if ($r['status_note'] !== ''): ?><span class="sal-rep__note" translate="no"><?= $h($r['status_note']) ?></span><?php endif; ?>
                            <?php if ($canManage): ?>
                                <button type="button" class="btn btn--ghost sal-btn sal-btn--small" data-sal-open="<?= $h($dlgStatus) ?>" data-sal-action="<?= $h(url('atak/sse/avis/signalements/' . (int) $r['id'] . '/statut')) ?>" data-sal-subject="<?= $h($r['ref']) ?>" data-sal-status="<?= $h($r['status']) ?>" aria-haspopup="dialog"><?= $ico($icons['pen'], 16) ?><span>Traiter</span></button>
                            <?php endif; ?>
                        </footer>
                    </article>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php if ($canManage): ?>
        <dialog class="sal-dialog" id="<?= $h($dlgStatus) ?>" aria-labelledby="<?= $h($dlgStatus) ?>-title">
            <form method="post" action="" class="sal-dialog__card" data-sal-target-form>
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <input type="hidden" name="back" value="<?= $h($here) ?>">
                <header class="sal-dialog__head">
                    <div><h2 id="<?= $h($dlgStatus) ?>-title">Traiter le signalement</h2><p class="sal-desc">Indiquez ce que vous en faites ; un signalement écarté demande un motif.</p></div>
                    <button type="button" class="sal-dialog__close" data-sal-close aria-label="Fermer la fenêtre"><?= $ico($icons['close']) ?></button>
                </header>
                <div class="sal-dialog__body">
                    <p class="sal-subject" translate="no" data-sal-subject-out></p>
                    <div class="sal-pick sal-pick--4">
                        <?php foreach ($statuses as $key => $names): ?>
                            <label class="sal-opt sal-opt--tiny"><input type="radio" name="status" value="<?= $h($key) ?>" data-sal-status-radio><span class="sal-opt__body"><b><?= $h($names[0]) ?></b></span></label>
                        <?php endforeach; ?>
                    </div>
                    <label class="sal-field" for="<?= $h($dlgStatus) ?>-note"><span>Motif ou commentaire <small data-sal-note-need hidden>(obligatoire pour écarter)</small></span>
                        <textarea id="<?= $h($dlgStatus) ?>-note" name="note" rows="3" maxlength="500" placeholder="Ce que vous en faites, en une phrase" data-sal-note></textarea>
                    </label>
                </div>
                <footer class="sal-dialog__foot"><button type="button" class="btn btn--ghost sal-btn" data-sal-close>Annuler</button><button type="submit" class="btn sal-btn sal-btn--solid">Enregistrer</button></footer>
            </form>
        </dialog>
        <?php endif; ?>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
