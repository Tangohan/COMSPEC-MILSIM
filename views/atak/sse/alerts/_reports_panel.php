<?php
declare(strict_types=1);
/**
 * Panneau « Signalements » d'une alerte (avis de recherche ou fiche de vigilance) : adresse de signalement (lien, copie, QR code
 * téléchargeable, révocation), chronologie des signalements, changement d'état en fenêtre. À inclure dans la fiche d'un avis et dans
 * le suivi d'une fiche de vigilance :
 *
 *   <?php $panel = (new \App\Controllers\Web\SseAlertReportController())->panelVars('avis', (int) $notice['id'], (string) $notice['ref'], $notice);
 *         extract($panel); $alertBack = 'atak/sse/papiers/identites/' . (int) $iid . '#avis';
 *         require base_path('views/atak/sse/alerts/_reports_panel.php'); ?>
 *
 * (pour une fiche de vigilance : panelVars('vigilance', $issueId, $ref)). Les cinq variables viennent de panelVars() ; le panneau
 * ajoute sa feuille de style et son script tout seul.
 *
 * @var array<string, mixed>|null $alertLink lien hydraté (SseAlertRepository::hydrateLink), null sans l'étape de base « alertes SSE »
 * @var list<array<string, mixed>> $alertReports signalements du lien, du plus récent au plus ancien (SseAlertRepository::reportsFor)
 * @var bool $alertCanManage écrire dans « Fichiers et sujets » : changer l'état, voir les photos, révoquer
 * @var string $alertUrl adresse de signalement (vide si le lien manque ou est révoqué)
 * @var string $alertRef référence de l'alerte (A-0001/2026, FV-2026-0001)
 * @var string|null $alertBack (facultatif) adresse du portail où revenir après une action, ex. « atak/sse/papiers/identites/12#avis »
 * @var string|null $alertTz (facultatif) fuseau d'affichage des heures, UTC par défaut
 * @var bool|null $alertCanRead (facultatif) faux : le panneau ne s'affiche pas
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
if (array_key_exists('alertCanRead', get_defined_vars()) && !$alertCanRead) {
    return;
}
$link = is_array($alertLink ?? null) ? $alertLink : null;
$reports = is_array($alertReports ?? null) ? $alertReports : [];
$canManage = !empty($alertCanManage);
$ref = (string) ($alertRef ?? '');
$back = (string) ($alertBack ?? '');
$tzName = (string) ($alertTz ?? 'UTC');
$csrf = \App\Core\Csrf::token();
$linkId = (int) ($link['id'] ?? 0);
$revoked = $link !== null && $link['revoked_at'] !== null;
$url = (string) ($alertUrl ?? '');
$sseExtraStyles = ($sseExtraStyles ?? '') . (str_contains((string) ($sseExtraStyles ?? ''), 'sse-alerts.css') ? '' : '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-alerts.css')) . '">');
$sseExtraScripts = ($sseExtraScripts ?? '') . (str_contains((string) ($sseExtraScripts ?? ''), 'sse-alerts.js') ? '' : '<script defer src="' . $h(asset_url('assets/js/sse-alerts.js')) . '"></script>');
$stamp = static function (?string $utc) use ($tzName): string {
    $utc = trim((string) $utc);
    if ($utc === '') {
        return '';
    }
    try {
        $d = (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone($tzName));
    } catch (\Throwable) {
        return $utc;
    }

    return $d->format(\App\Controllers\Web\SseAlertReportController::readerLang() === 'en' ? 'Y-m-d H:i' : 'd/m/Y H:i');
};
$ico = static fn (string $d, int $s = 18): string => '<svg class="sal-i" width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="' . $d . '"/></svg>';
$icons = ['copy' => 'M9 9h11v11H9zM5 15V4h11', 'down' => 'M12 4v11M7 11l5 5 5-5M5 20h14', 'close' => 'M6 6l12 12M18 6L6 18', 'ban' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM5.6 5.6l12.8 12.8', 'pen' => 'M4 20l4-1 11-11-3-3L5 16zM14 7l3 3', 'camera' => 'M4 8h3l1.5-2h7L17 8h3v11H4zM12 16.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z'];
$newCount = count(array_filter($reports, static fn (array $r): bool => $r['status'] === 'new'));
$statuses = \App\Support\SseAlertLevel::REPORT_STATUSES;
$sure = \App\Support\SseAlertLevel::CERTAINTY;
$lang = \App\Controllers\Web\SseAlertReportController::readerLang();
$li = $lang === 'en' ? 1 : 0;
$qr = $url !== '' && $link !== null ? url(\App\Support\SseAlertReportTo::path((string) $link['token']) . '/qr.svg') : '';
$dlgStatus = 'sal-dlg-status-' . $linkId;
$dlgRevoke = 'sal-dlg-revoke-' . $linkId;
?>
<section class="sal-panel" id="signalements" data-sal data-sal-panel aria-labelledby="sal-panel-title-<?= $linkId ?>">
    <span hidden data-sal-msg="copied">Copié dans le presse-papiers.</span>
    <span hidden data-sal-msg="failed">La copie a échoué : sélectionnez le texte et copiez-le à la main.</span>
    <header class="sal-panel__head">
        <div>
            <h3 class="sal-panel__title" id="sal-panel-title-<?= $linkId ?>">Signalements</h3>
            <p class="sal-desc">Ce que les membres ont vu ou appris sur cette alerte, du plus récent au plus ancien.</p>
        </div>
        <ul class="sal-chips" aria-label="Décompte des signalements">
            <li class="sal-chip<?= $newCount > 0 ? ' sal-chip--warn' : '' ?>"><b><?= $newCount ?></b> <?= $newCount > 1 ? 'nouveaux' : 'nouveau' ?></li>
            <li class="sal-chip"><b><?= count($reports) ?></b> au total</li>
        </ul>
    </header>

    <?php if ($link === null): ?>
        <p class="sal-note sal-note--block">Les signalements seront disponibles dès que l’étape de base de données « alertes SSE » aura été passée par l’équipe technique.</p>
    <?php else: ?>
    <div class="sal-panel__grid">
        <div class="sal-addr">
            <h4 class="sal-sub">Adresse de signalement</h4>
            <p class="sal-desc">Imprimée sur le document : elle ouvre le formulaire pour les membres connectés.</p>
            <?php if ($revoked): ?>
                <p class="sal-chip sal-chip--warn"><?= $ico($icons['ban'], 16) ?><?= $h('Adresse révoquée le ' . $stamp((string) $link['revoked_at'])) ?></p>
            <?php else: ?>
                <?php if ($qr !== ''): ?><img class="sal-qr" src="<?= $h($qr) ?>" width="136" height="136" alt="QR code de l’adresse de signalement de <?= $h($ref) ?>" loading="lazy"><?php endif; ?>
                <input class="sal-url" type="text" readonly value="<?= $h($url) ?>" aria-label="Adresse de signalement" data-sal-url onfocus="this.select()">
                <div class="sal-actions sal-actions--wrap">
                    <button type="button" class="btn sal-btn" data-sal-copy="<?= $h($url) ?>"><?= $ico($icons['copy']) ?><span>Copier le lien</span></button>
                    <?php if ($qr !== ''): ?><a class="btn btn--ghost sal-btn" href="<?= $h($qr . '?telecharger=1') ?>" download><?= $ico($icons['down']) ?><span>Télécharger le QR code</span></a><?php endif; ?>
                    <?php if ($canManage): ?><button type="button" class="btn btn--ghost sal-btn sal-btn--danger" data-sal-open="<?= $h($dlgRevoke) ?>" aria-haspopup="dialog"><?= $ico($icons['ban']) ?><span>Révoquer l’adresse</span></button><?php endif; ?>
                </div>
                <p class="sal-live" role="status" aria-live="polite" data-sal-live></p>
            <?php endif; ?>
        </div>

        <div class="sal-timeline">
            <?php if ($reports === []): ?>
                <div class="sal-void sal-void--block">
                    <b>Aucun signalement pour l’instant</b>
                    <span>Les observations et informations envoyées apparaîtront ici dès leur réception.</span>
                </div>
            <?php else: ?>
                <ol class="sal-reports">
                    <?php foreach ($reports as $r): ?>
                        <?php $sureKey = isset($sure[$r['certainty']]) ? $r['certainty'] : 'medium'; ?>
                        <li class="sal-rep sal-rep--<?= $h($r['status']) ?>">
                            <article>
                                <header class="sal-rep__head">
                                    <strong class="sal-rep__ref" translate="no"><?= $h($r['ref']) ?></strong>
                                    <span class="sal-st sal-st--<?= $h($r['status']) ?>"><?= $h($statuses[$r['status']][0] ?? $r['status']) ?></span>
                                    <span class="sal-sure sal-sure--<?= $h($sureKey) ?>"><span class="sal-bars" aria-hidden="true"><?php for ($b = 1; $b <= 3; $b++): ?><i class="<?= $b <= ['low' => 1, 'medium' => 2, 'high' => 3][$sureKey] ? 'is-on' : '' ?>"></i><?php endfor; ?></span><?= $h($sure[$sureKey][0]) ?></span>
                                    <span class="sal-chip sal-chip--src"><?= $r['source'] === 'game' ? 'Jeu' : 'Site' ?></span>
                                    <span class="sal-chip"><?= $r['what'] === 'info' ? 'Information' : 'Vu' ?></span>
                                    <time class="sal-rep__when"><?= $h($stamp((string) $r['seen_at'])) ?></time>
                                </header>
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
            <?php endif; ?>
        </div>
    </div>

    <?php if ($canManage): ?>
        <dialog class="sal-dialog" id="<?= $h($dlgStatus) ?>" aria-labelledby="<?= $h($dlgStatus) ?>-title">
            <form method="post" action="" class="sal-dialog__card" data-sal-target-form>
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <input type="hidden" name="back" value="<?= $h($back) ?>">
                <header class="sal-dialog__head">
                    <div>
                        <h2 id="<?= $h($dlgStatus) ?>-title">Traiter le signalement</h2>
                        <p class="sal-desc">Indiquez ce que vous en faites ; un signalement écarté demande un motif.</p>
                    </div>
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
                <footer class="sal-dialog__foot">
                    <button type="button" class="btn btn--ghost sal-btn" data-sal-close>Annuler</button>
                    <button type="submit" class="btn sal-btn sal-btn--solid">Enregistrer</button>
                </footer>
            </form>
        </dialog>
        <?php if (!$revoked): ?>
        <dialog class="sal-dialog sal-dialog--small" id="<?= $h($dlgRevoke) ?>" aria-labelledby="<?= $h($dlgRevoke) ?>-title">
            <form method="post" action="<?= $h(url('atak/sse/avis/liens/' . $linkId . '/revoquer')) ?>" class="sal-dialog__card">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <input type="hidden" name="back" value="<?= $h($back) ?>">
                <header class="sal-dialog__head">
                    <div>
                        <h2 id="<?= $h($dlgRevoke) ?>-title">Révoquer l’adresse de signalement ?</h2>
                        <p class="sal-desc">Le QR code des documents déjà imprimés ne fonctionnera plus. Les signalements reçus sont conservés.</p>
                    </div>
                    <button type="button" class="sal-dialog__close" data-sal-close aria-label="Fermer la fenêtre"><?= $ico($icons['close']) ?></button>
                </header>
                <div class="sal-dialog__body"><p class="sal-subject" translate="no"><?= $h($ref) ?></p></div>
                <footer class="sal-dialog__foot">
                    <button type="button" class="btn btn--ghost sal-btn" data-sal-close autofocus>Annuler</button>
                    <button type="submit" class="btn btn--danger sal-btn">Révoquer l’adresse</button>
                </footer>
            </form>
        </dialog>
        <?php endif; ?>
    <?php endif; ?>
    <?php endif; ?>
</section>
