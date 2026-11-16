<?php
declare(strict_types=1);
/**
 * Bureau SSE — page de signalement d'une alerte (avis de recherche ou fiche de vigilance), ouverte depuis le QR code d'un document.
 * Une bande d'en-tête rappelle l'alerte (niveau, consigne, sujet selon l'habilitation), puis un formulaire court ; après l'envoi, le
 * même écran montre l'accusé de réception. Alerte levée ou expirée : lecture seule. Jeton inconnu : « introuvable ».
 * Styles : public/assets/css/sse-alerts.css (.sal-*) ; script : public/assets/js/sse-alerts.js (aucun texte).
 *
 * @var string $mode form|receipt|closed|notfound|denied|unavailable
 * @var array<string, mixed> $view alerte (SseAlertReportController::heroOf)
 * @var array<string, mixed> $link
 * @var list<string> $errors
 * @var array<string, string> $old
 * @var array<string, mixed>|null $report
 * @var bool $replay
 * @var string $action
 * @var string $lang
 * @var string $tz
 * @var string $nowLocal
 * @var string $cid
 * @var string $message
 */
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$mode = (string) ($mode ?? 'notfound');
$lang = (string) ($lang ?? 'fr') === 'en' ? 'en' : 'fr';
$tz = (string) ($tz ?? 'UTC');
$view = is_array($view ?? null) ? $view : [];
$errors = is_array($errors ?? null) ? $errors : [];
$old = is_array($old ?? null) ? $old : [];
$report = is_array($report ?? null) ? $report : null;
$csrf = (string) ($csrfToken ?? \App\Core\Csrf::token());
$stamp = static function (?string $utc, bool $time = true) use ($tz, $lang): string {
    $utc = trim((string) $utc);
    if ($utc === '') {
        return '';
    }
    try {
        $d = new \DateTimeImmutable(strlen($utc) <= 10 ? $utc . ' 00:00:00' : $utc, new \DateTimeZone('UTC'));
        $d = strlen($utc) <= 10 ? $d : $d->setTimezone(new \DateTimeZone($tz));
    } catch (\Throwable) {
        return $utc;
    }
    $date = $lang === 'en' ? 'Y-m-d' : 'd/m/Y';

    return $d->format($time && strlen($utc) > 10 ? $date . ' H:i' : $date);
};
$ico = static fn (string $d, int $s = 20): string => '<svg class="sal-i" width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="' . $d . '"/></svg>';
$icons = [
    'eye' => 'M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
    'info' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 11v5M12 8h.01',
    'check' => 'M5 12.5l4.5 4.5L19 7.5',
    'camera' => 'M4 8h3l1.5-2h7L17 8h3v11H4zM12 16.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z',
    'copy' => 'M9 9h11v11H9zM5 15V4h11',
    'arrow' => 'M5 12h14M13 6l6 6-6 6',
    'lock' => 'M6 11h12v9H6zM8.5 11V8a3.5 3.5 0 0 1 7 0v3',
    'search' => 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4.3-4.3',
    'clock' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7v5l3 2',
];
$shapeSvg = static function (string $shape, string $fill, string $ink, int $s = 30): string {
    $inner = match ($shape) {
        'triangle' => '<path d="M12 3.2 22 20.5H2z" fill="' . $fill . '"/><path d="M12 9.5v5M12 17.4h.01" stroke="' . $ink . '" stroke-width="2.2" stroke-linecap="round"/>',
        'diamond' => '<path d="M12 1.8 22.2 12 12 22.2 1.8 12z" fill="' . $fill . '"/><path d="M12 7.5v5.5M12 16.2h.01" stroke="' . $ink . '" stroke-width="2.2" stroke-linecap="round"/>',
        default => '<path d="M8.2 2.5h7.6l5.7 5.7v7.6l-5.7 5.7H8.2l-5.7-5.7V8.2z" fill="' . $fill . '"/><path d="M12 7v6M12 16.4h.01" stroke="' . $ink . '" stroke-width="2.2" stroke-linecap="round"/>',
    };

    return '<svg class="sal-shape" width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $inner . '</svg>';
};
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-alerts.css')) . '">';
$sseExtraScripts = ($sseExtraScripts ?? '') . '<script defer src="' . $h(asset_url('assets/js/sse-alerts.js')) . '"></script>';
$certainty = [
    'low' => ['Incertain', 'Aperçu rapide, de loin ou dans le doute.', 1],
    'medium' => ['Probable', 'Bonne vue, mais pas de certitude totale.', 2],
    'high' => ['Certain', 'Vu clairement, de près.', 3],
];
$isForm = in_array($mode, ['form', 'receipt', 'closed'], true);
$accent = (string) ($view['accent'] ?? '#34d399');
$ink = (string) ($view['ink'] ?? '#ffffff');
$level = is_array($view['level'] ?? null) ? $view['level'] : ['rank' => 1, 'title' => '', 'hex' => $accent, 'ink' => $ink, 'shape' => 'triangle', 'hint' => '', 'key' => 'watch'];
$notice = is_array($view['notice'] ?? null) ? $view['notice'] : null;
$isNotice = ($view['doc'] ?? '') === 'avis';
$docLabel = $isNotice ? 'Avis de recherche' : 'Fiche de vigilance';
$state = (string) ($view['state'] ?? 'active');
// Contrat du formulaire (config/sse_alert_report_form.php) : champs montrés et obligatoires selon la fiche et sa conduite à tenir.
$form = is_array($form ?? null) ? $form : \App\Support\SseAlertReportForm::resolve([], 1);
$fi = $lang === 'en' ? 1 : 0;
$shows = static fn (string $f): bool => in_array($f, $form['fields'], true);
$needs = static fn (string $f): bool => in_array($f, $form['required'], true);
$oldExtra = static fn (string $k): string => (string) (is_array($old['extras'] ?? null) ? ($old['extras'][$k] ?? '') : '');
$fieldHtml = static function (string $id) use ($form, $fi, $h, $old, $oldExtra, $needs): string {
    $def = \App\Support\SseAlertReportForm::field($id);
    if ($def === null || $def['type'] === 'photo') {
        return '';
    }
    $label = $h($def['label'][$fi]) . ($needs($id) ? ' <em class="sal-req">' . $h($fi === 1 ? 'Required' : 'Obligatoire') . '</em>' : '');
    $val = in_array($id, ['dir', 'vehicle'], true) ? (string) ($old[$id] ?? '') : $oldExtra($id);
    $req = $needs($id) ? ' required' : '';
    if ($def['type'] === 'choice') {
        $out = '<div class="sal-choice" role="radiogroup" aria-labelledby="sal-c-' . $h($id) . '"><p class="sal-choice__t" id="sal-c-' . $h($id) . '">' . $label . '</p><div class="sal-pick sal-pick--' . count($def['options']) . '">';
        foreach ($def['options'] as $key => $text) {
            $out .= '<label class="sal-opt sal-opt--sure"><input type="radio" name="' . $h($id) . '" value="' . $h($key) . '"' . ($val === (string) $key ? ' checked' : '') . $req . '><span class="sal-opt__body"><b>' . $h($text[$fi]) . '</b></span></label>';
        }

        return $out . '</div></div>';
    }
    $attrs = $def['type'] === 'count' ? ' type="number" inputmode="numeric" min="1" max="' . (int) $def['max'] . '"' : ' type="text" maxlength="' . (int) $def['max'] . '"';

    return '<label class="sal-field" for="sal-' . $h($id) . '"><span>' . $label . '</span><input id="sal-' . $h($id) . '" name="' . $h($id) . '"' . $attrs . ' value="' . $h($val) . '" placeholder="' . $h($def['hint'][$fi] ?? '') . '" autocomplete="off"' . $req . '></label>';
};
?>
<div class="sal" data-sal>
<span hidden data-sal-msg="copied">Copié dans le presse-papiers.</span>
<span hidden data-sal-msg="failed">La copie a échoué : sélectionnez le texte et copiez-le à la main.</span>
<?php if (!$isForm): ?>
    <?php
    $notFound = $mode === 'notfound';
    $iconKey = $notFound ? 'search' : 'lock';
    $heading = match ($mode) {
        'denied' => 'Signalement impossible avec cette session',
        'unavailable' => 'Signalements pas encore disponibles',
        default => 'Signalement introuvable',
    };
    $text = match ($mode) {
        'denied', 'unavailable' => (string) ($message ?? ''),
        default => 'Cette adresse de signalement n’existe pas ou n’est plus valable. Vérifiez le QR code ou demandez le lien au service concerné.',
    };
    ?>
    <section class="sal-empty" role="status">
        <span class="sal-empty__icon"><?= $ico($icons[$iconKey], 28) ?></span>
        <h1 class="sal-empty__title"><?= $h($heading) ?></h1>
        <p class="sal-desc"><?= $h($text) ?></p>
        <a class="btn sal-btn" href="<?= $h(url('atak/sse/accueil')) ?>"><?= $ico($icons['arrow']) ?><span>Retour à l’accueil du bureau</span></a>
    </section>
<?php else: ?>
    <header class="sal-hero<?= $state !== 'active' ? ' sal-hero--closed' : '' ?>" style="--sal-accent: <?= $h($accent) ?>; --sal-on: <?= $h($ink) ?>;">
        <div class="sal-hero__inner">
            <div class="sal-hero__main">
                <h1 class="sal-title">
                    <span class="sal-title__text"><?= $h($notice !== null ? $notice['title'] : $level['title']) ?></span>
                    <span class="sal-title__ref" translate="no"><?= $h($view['ref'] ?? '') ?></span>
                </h1>
                <p class="sal-hero__desc"><?php if ($mode === 'receipt'): ?>Merci : votre signalement est enregistré.<?php elseif ($state !== 'active'): ?>Cette alerte n’est plus en cours : elle reste lisible mais ne reçoit plus de signalement.<?php else: ?>Vous avez vu cette personne ou vous savez quelque chose ? Dites-le au service ci-dessous.<?php endif; ?></p>
                <ul class="sal-chips" aria-label="L’alerte en un coup d’œil">
                    <li class="sal-chip sal-chip--kind"><?= $h($docLabel) ?></li>
                    <?php if ($notice !== null && $notice['subtitle'] !== ''): ?><li class="sal-chip"><?= $h($notice['subtitle']) ?></li><?php endif; ?>
                    <?php if ($notice !== null): ?><li class="sal-chip sal-chip--level"><?= $shapeSvg((string) $level['shape'], (string) $level['hex'], (string) $level['ink'], 18) ?><?= $h($level['title']) ?></li><?php endif; ?>
                    <?php if ((string) ($view['name'] ?? '') !== ''): ?>
                        <li class="sal-chip sal-chip--subject" translate="no"><b><?= $h($view['name']) ?></b><?php if ((string) ($view['alias'] ?? '') !== ''): ?><span> · <?= $h($view['alias']) ?></span><?php endif; ?></li>
                    <?php endif; ?>
                </ul>
                <?php if ((string) ($view['order'] ?? '') !== '' && $state === 'active'): ?>
                    <p class="sal-order"><?= $ico($icons['info'], 20) ?><span><?= $h($view['order']) ?></span></p>
                <?php endif; ?>
                <?php if ($mode === 'form'): ?>
                    <div class="sal-hero__actions">
                        <a class="btn sal-btn sal-btn--solid" href="#sal-form"><?= $ico($icons['eye']) ?><span>Faire un signalement</span></a>
                        <a class="btn btn--ghost sal-btn" href="#sal-brief" data-sal-brief><?= $ico($icons['info']) ?><span>Conduite à tenir</span></a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="sal-figure" role="img" aria-label="<?= $h('Niveau ' . (int) $level['rank'] . ' sur 4 : ' . $level['title']) ?>">
                <?= $shapeSvg((string) $level['shape'], (string) $level['hex'], (string) $level['ink'], 44) ?>
                <span class="sal-figure__n"><?= (int) $level['rank'] ?><small>/4</small></span>
                <span class="sal-figure__bars" aria-hidden="true"><?php for ($b = 1; $b <= 4; $b++): ?><i class="<?= $b <= (int) $level['rank'] ? 'is-on' : '' ?>"></i><?php endfor; ?></span>
                <span class="sal-figure__cap">Niveau</span>
            </div>
        </div>
    </header>

    <div class="sal-layout">
        <div class="sal-main">
        <?php if ($mode === 'receipt' && $report !== null): ?>
            <section class="sal-card sal-receipt" aria-labelledby="sal-receipt-title" role="status" data-sal-receipt>
                <span class="sal-receipt__tick"><?= $ico($icons['check'], 30) ?></span>
                <h2 id="sal-receipt-title">Signalement reçu</h2>
                <p class="sal-desc"><?= $replay ? 'Ce signalement avait déjà été enregistré : voici son numéro.' : 'Les gestionnaires de l’alerte sont prévenus et le traiteront. Gardez ce numéro.' ?></p>
                <div class="sal-receipt__ref">
                    <strong translate="no" data-sal-ref><?= $h($report['ref']) ?></strong>
                    <button type="button" class="btn btn--ghost sal-btn" data-sal-copy="<?= $h($report['ref']) ?>"><?= $ico($icons['copy']) ?><span>Copier le numéro</span></button>
                </div>
                <p class="sal-live" role="status" aria-live="polite" data-sal-live></p>
                <dl class="sal-facts">
                    <div><dt>Observé le</dt><dd><time><?= $h($stamp((string) $report['seen_at'])) ?></time></dd></div>
                    <?php if ($report['place'] !== '' || $report['grid'] !== ''): ?><div><dt>Lieu</dt><dd translate="no"><?= $h(trim($report['place'] . ($report['grid'] !== '' ? ' · ' . $report['grid'] : ''), ' ·')) ?></dd></div><?php endif; ?>
                    <div><dt>Certitude</dt><dd><?= $h(\App\Support\SseAlertLevel::CERTAINTY[$report['certainty']][0] ?? '') ?></dd></div>
                    <div><dt>Nature</dt><dd><?= $report['what'] === 'info' ? 'Information' : 'Observation directe' ?></dd></div>
                    <?php if ($report['photo_path'] !== null): ?><div><dt>Photo</dt><dd>Jointe</dd></div><?php endif; ?>
                </dl>
                <div class="sal-actions">
                    <a class="btn sal-btn sal-btn--solid" href="<?= $h($action) ?>"><?= $ico($icons['eye']) ?><span>Envoyer un autre signalement</span></a>
                    <a class="btn btn--ghost sal-btn" href="<?= $h(url('atak/sse/accueil')) ?>"><span>Retour au bureau</span></a>
                </div>
            </section>
        <?php elseif ($mode === 'closed'): ?>
            <section class="sal-card sal-closed" aria-labelledby="sal-closed-title" role="status">
                <span class="sal-closed__icon"><?= $ico($icons['clock'], 26) ?></span>
                <h2 id="sal-closed-title"><?php
                    $when = $state === 'lifted' ? $stamp((string) ($view['lifted_at'] ?? '')) : $stamp((string) ($view['until'] ?? ''), false);
                    if ($isNotice) {
                        echo $h($state === 'lifted' ? 'Avis levé le ' . $when : 'Avis expiré le ' . $when);
                    } else {
                        echo $h($state === 'lifted' ? 'Fiche de vigilance levée le ' . $when : 'Fiche de vigilance expirée le ' . $when);
                    }
                ?></h2>
                <p class="sal-desc">L’alerte ne reçoit plus de signalement. Si vous avez une information urgente, appelez directement le service.</p>
                <?php if ((string) ($view['lift_reason'] ?? '') !== ''): ?><p class="sal-closed__reason" translate="no"><?= $h($view['lift_reason']) ?></p><?php endif; ?>
            </section>
        <?php else: ?>
            <form class="sal-card sal-form" id="sal-form" method="post" action="<?= $h($action) ?>" enctype="multipart/form-data" data-sal-form data-sal-max="<?= (int) ($maxBody ?? 600) ?>" data-sal-min="<?= (int) ($minBody ?? 3) ?>" data-sal-photo-max="<?= (int) ($maxPhotoMb ?? 4) * 1048576 ?>">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <input type="hidden" name="cid" value="<?= $h(($old['cid'] ?? '') !== '' ? $old['cid'] : ($cid ?? '')) ?>">
                <?php if ($errors !== []): ?>
                    <div class="sal-alert" role="alert" tabindex="-1" data-sal-errors>
                        <?php foreach ($errors as $e): ?><p><?= $h($e) ?></p><?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <section class="sal-brief<?= $form['urgent'] ? ' sal-brief--urgent' : '' ?>" aria-labelledby="sal-brief-title" data-sal-form-brief>
                    <h2 id="sal-brief-title" class="sal-brief__t"><?= $form['urgent'] ? 'Urgence : prévenez le service sans attendre' : 'Avant de signaler' ?></h2>
                    <?php if ((string) ($view['order'] ?? '') !== ''): ?><p class="sal-brief__order"><?= $h($view['order']) ?></p><?php endif; ?>
                    <?php foreach ($form['notes'] as $note): ?><p class="sal-brief__note"><?= $h($note[$fi]) ?></p><?php endforeach; ?>
                    <p class="sal-brief__svc" translate="no"><b><?= $h($view['service'] ?? '') ?></b><?php if ((string) ($view['channel'] ?? '') !== ''): ?><span><?= $h($view['channel']) ?></span><?php endif; ?><span><?= $h($view['delay'] ?? '') ?></span></p>
                </section>

                <fieldset class="sal-sec">
                    <legend>Ce que vous signalez</legend>
                    <p class="sal-desc">Vous avez vu la personne, ou vous avez une information utile.</p>
                    <div class="sal-pick sal-pick--2">
                        <label class="sal-opt"><input type="radio" name="what" value="seen" <?= ($old['what'] ?? 'seen') !== 'info' ? 'checked' : '' ?>><span class="sal-opt__body"><?= $ico($icons['eye'], 24) ?><b>Je l’ai vu</b><small>Observation directe, en cours ou récente.</small></span></label>
                        <label class="sal-opt"><input type="radio" name="what" value="info" <?= ($old['what'] ?? '') === 'info' ? 'checked' : '' ?>><span class="sal-opt__body"><?= $ico($icons['info'], 24) ?><b>J’ai une information</b><small>Ce que vous savez, sans l’avoir vu vous-même.</small></span></label>
                    </div>
                </fieldset>

                <fieldset class="sal-sec">
                    <legend>Quand et où</legend>
                    <p class="sal-desc">L’heure de l’observation et l’endroit, aussi précis que possible.</p>
                    <div class="sal-grid">
                        <label class="sal-field" for="sal-when"><span>Date et heure</span>
                            <input id="sal-when" type="datetime-local" name="when" value="<?= $h($old['when'] ?? $nowLocal) ?>" max="<?= $h($nowLocal ?? '') ?>" required>
                        </label>
                        <label class="sal-field" for="sal-grid"><span>Repère de grille</span><input id="sal-grid" type="text" name="grid" maxlength="24" value="<?= $h($old['grid'] ?? '') ?>" placeholder="Ex. 045 123" autocomplete="off" inputmode="numeric"></label>
                        <label class="sal-field sal-field--wide" for="sal-place"><span>Lieu<?= $needs('place') ? ' <em class="sal-req">Obligatoire</em>' : '' ?></span><input id="sal-place" type="text" name="place" maxlength="160" value="<?= $h($old['place'] ?? '') ?>" placeholder="Village, carrefour, bâtiment…" autocomplete="off"<?= $needs('place') ? ' required' : '' ?>></label>
                    </div>
                </fieldset>

                <fieldset class="sal-sec">
                    <legend>Ce que vous avez vu</legend>
                    <p class="sal-desc">Quelques mots précis : qui, quoi, comment la personne se comportait.</p>
                    <label class="sal-field" for="sal-text"><span>Description</span>
                        <textarea id="sal-text" name="text" rows="4" maxlength="<?= (int) ($maxBody ?? 600) ?>" required placeholder="Ce que vous avez vu ou appris" data-sal-text><?= $h($old['text'] ?? '') ?></textarea>
                        <span class="sal-count" data-sal-count aria-live="polite"><?= mb_strlen((string) ($old['text'] ?? '')) ?> / <?= (int) ($maxBody ?? 600) ?></span>
                    </label>
                    <?php $details = array_values(array_filter($form['fields'], static fn (string $f): bool => $f !== 'photo')); ?>
                    <?php if ($details !== []): ?>
                    <div class="sal-grid sal-grid--2" data-sal-details>
                        <?php foreach ($details as $f): ?><?= $fieldHtml($f) ?><?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </fieldset>

                <fieldset class="sal-sec">
                    <legend>Votre certitude</legend>
                    <p class="sal-desc">Dites honnêtement ce dont vous êtes sûr : cela aide à recouper.</p>
                    <div class="sal-pick sal-pick--3">
                        <?php foreach ($certainty as $key => [$label, $hint, $bars]): ?>
                            <label class="sal-opt sal-opt--sure"><input type="radio" name="sure" value="<?= $h($key) ?>" <?= ($old['sure'] ?? 'medium') === $key ? 'checked' : '' ?>><span class="sal-opt__body"><span class="sal-bars" aria-hidden="true"><?php for ($b = 1; $b <= 3; $b++): ?><i class="<?= $b <= $bars ? 'is-on' : '' ?>"></i><?php endfor; ?></span><b><?= $h($label) ?></b><small><?= $h($hint) ?></small></span></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

<?php if ($shows('photo')): ?>
                <fieldset class="sal-sec">
                    <legend>Photo</legend>
                    <p class="sal-desc">Facultative : JPEG, PNG ou WebP, <?= (int) ($maxPhotoMb ?? 4) ?> Mo au plus, sans position ni données de l’appareil.</p>
                    <label class="sal-drop" for="sal-photo" data-sal-drop>
                        <?= $ico($icons['camera'], 24) ?>
                        <span class="sal-drop__txt" data-sal-photo-name>Ajouter une photo</span>
                        <input id="sal-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" capture="environment">
                    </label>
                    <p class="sal-alert sal-alert--inline" role="alert" hidden data-sal-photo-error>La photo dépasse la taille permise.</p>
                </fieldset>
<?php endif; ?>

                <div class="sal-actions sal-actions--submit">
                    <button type="submit" class="btn sal-btn sal-btn--solid sal-btn--big" data-sal-submit><?= $ico($icons['arrow']) ?><span>Envoyer le signalement</span></button>
                    <p class="sal-note">Votre indicatif est transmis avec le signalement. Il n’est jamais publié.</p>
                </div>
            </form>
        <?php endif; ?>
        </div>

        <aside class="sal-aside" id="sal-brief" aria-label="À savoir avant de signaler">
            <details class="sal-fold" open data-sal-fold>
                <summary><span class="sal-fold__t">Conduite à tenir</span></summary>
                <p class="sal-desc">Ce qu’il faut faire, et ne pas faire, si vous croisez le sujet.</p>
                <?php if ((string) ($view['order'] ?? '') !== ''): ?><p class="sal-order sal-order--aside"><?= $h($view['order']) ?></p><?php endif; ?>
                <?php if (($view['conduct'] ?? []) !== []): ?>
                    <ol class="sal-conduct" translate="no">
                        <?php foreach ($view['conduct'] as $line): ?><li><?= $h($line) ?></li><?php endforeach; ?>
                    </ol>
                <?php else: ?>
                    <p class="sal-void"><?= $h($level['hint']) ?></p>
                <?php endif; ?>
            </details>
            <details class="sal-fold" open data-sal-fold>
                <summary><span class="sal-fold__t">Service à informer</span></summary>
                <p class="sal-desc">Pour une urgence, joignez directement ce service.</p>
                <p class="sal-svc" translate="no"><?= $h($view['service'] ?? '') ?></p>
                <?php if ((string) ($view['channel'] ?? '') !== ''): ?><p class="sal-channel" translate="no"><?= $h($view['channel']) ?></p><?php endif; ?>
                <ul class="sal-chips sal-chips--aside">
                    <li class="sal-chip"><?= $ico($icons['clock'], 16) ?><?= $h($view['delay'] ?? '') ?></li>
                    <?php if ((string) ($view['until'] ?? '') !== ''): ?><li class="sal-chip<?= !empty($view['soon']) ? ' sal-chip--warn' : '' ?>"><?= $h('Valable jusqu’au ' . $stamp((string) $view['until'], false)) ?></li><?php endif; ?>
                    <?php if (!empty($view['soon'])): ?><li class="sal-chip sal-chip--warn">Périssable</li><?php endif; ?>
                </ul>
            </details>
        </aside>
    </div>
<?php endif; ?>
</div>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
