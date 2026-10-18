<?php
declare(strict_types=1);
// Atelier public de traduction de l'ATAK natif (/traduire-atak, /traduire-atak/p/{pack}). Toute la logique est dans
// public/assets/js/atak-translate.js : modèle lu depuis GET /traduire-atak/modele.json, travail gardé sur l'appareil
// ou dans un pack partagé (API /api/traduire-atak/packs, lien secret), addon et zip fabriqués dans le navigateur.
$t = static fn (string $key): string => htmlspecialchars(__('site.atakt_' . $key), ENT_QUOTES, 'UTF-8');
$release = \App\Support\AtakReleaseInfo::latest();
$packId = isset($packId) && is_string($packId) ? $packId : '';
$js = [];
foreach (require base_path('lang/fr/site.php') as $k => $_) {
    if (is_string($k) && str_starts_with($k, 'atakt_js_')) {
        $js[substr($k, 9)] = __('site.' . $k);
    }
}
$gameLanguages = ['Spanish', 'German', 'Italian', 'Polish', 'Portuguese', 'Russian', 'Czech', 'Turkish', 'Chinesesimp', 'Chinese', 'Japanese', 'Korean'];
$icon = static fn (string $d): string => '<svg viewBox="0 0 24 24" aria-hidden="true" class="tw-ico">' . $d . '</svg>';
$icons = [
    'share' => '<circle cx="18" cy="5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="19" r="2.5"/><path d="M8.2 10.8l7.6-4.4M8.2 13.2l7.6 4.4"/>',
    'gear' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
    'down' => '<path d="M12 3v12M7 10l5 5 5-5M4 20h16"/>',
    'list' => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
];
?>
<article class="atakn atakt" id="atakt"
    data-template-url="<?= htmlspecialchars(url('traduire-atak/modele.json'), ENT_QUOTES, 'UTF-8') ?>"
    data-api-url="<?= htmlspecialchars(url('api/traduire-atak/packs'), ENT_QUOTES, 'UTF-8') ?>"
    data-page-url="<?= htmlspecialchars(url('traduire-atak'), ENT_QUOTES, 'UTF-8') ?>"
    data-pack-id="<?= htmlspecialchars($packId, ENT_QUOTES, 'UTF-8') ?>"
    data-version="<?= htmlspecialchars((string) ($release['atak_native'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
    data-i18n="<?= htmlspecialchars((string) json_encode($js, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
    <header class="atakn-hero atakt-hero">
        <p class="hi-kicker atakn-kicker"><?= $t('kicker') ?></p>
        <h1 class="atakn-title atakt-title"><?= $t('title') ?></h1>
        <p class="atakn-lead"><?= $t('lead') ?></p>
        <ol class="atakt-flow">
            <?php for ($i = 1; $i <= 4; $i++): ?>
            <li><span><?= sprintf('%02d', $i) ?></span><?= $t('flow_' . $i) ?></li>
            <?php endfor; ?>
        </ol>
    </header>

    <section class="tw" id="tw" aria-label="<?= $t('app_label') ?>" data-state="loading">
        <header class="tw-bar">
            <div class="tw-id">
                <button type="button" class="tw-lang" id="tw-switch" aria-haspopup="true" aria-expanded="false">
                    <span class="tw-lang__code" id="tw-code">··</span>
                    <span class="tw-lang__txt">
                        <strong id="tw-name"><?= $t('js_no_language') ?></strong>
                        <small id="tw-mode"></small>
                    </span>
                    <?= $icon('<path d="M6 9l6 6 6-6"/>') ?>
                </button>
                <div class="tw-menu" id="tw-menu" hidden></div>
            </div>
            <div class="tw-progress" aria-hidden="true">
                <div class="tw-progress__bar"><i id="tw-bar"></i></div>
                <span id="tw-pct">0 %</span>
            </div>
            <div class="tw-actions">
                <span class="tw-sync" id="tw-sync" role="status" aria-live="polite"></span>
                <button type="button" class="tw-btn" id="tw-share"><?= $icon($icons['share']) ?><span><?= $t('share') ?></span></button>
                <button type="button" class="tw-btn" id="tw-settings"><?= $icon($icons['gear']) ?><span><?= $t('settings') ?></span></button>
                <button type="button" class="tw-btn tw-btn--solid" id="tw-download"><?= $icon($icons['down']) ?><span><?= $t('download') ?></span></button>
            </div>
        </header>

        <div class="tw-loading" id="tw-loading" role="status"><?= $t('js_loading') ?></div>

        <div class="tw-setup" id="tw-setup" hidden>
            <div class="tw-card">
                <h2><?= $t('setup_title') ?></h2>
                <p><?= $t('setup_lead') ?></p>
                <form id="tw-setup-form" class="tw-form" novalidate>
                    <div class="tw-grid">
                        <label class="tw-field"><span><?= $t('field_code') ?></span>
                            <input type="text" name="code" maxlength="2" autocomplete="off" spellcheck="false" placeholder="es" required pattern="[a-z]{2}">
                            <small><?= $t('field_code_help') ?></small></label>
                        <label class="tw-field"><span><?= $t('field_name') ?></span>
                            <input type="text" name="name" maxlength="40" autocomplete="off" placeholder="Español" required>
                            <small><?= $t('field_name_help') ?></small></label>
                    </div>
                    <p class="tw-err" data-role="error" hidden></p>
                    <div class="tw-row">
                        <button type="submit" class="hi-cta hi-cta-solid"><?= $t('setup_start') ?></button>
                        <label class="tw-link tw-file"><input type="file" accept=".txt,text/plain" data-role="import"><?= $t('import') ?></label>
                    </div>
                </form>
            </div>
            <div class="tw-card tw-card--join">
                <h2><?= $t('join_title') ?></h2>
                <p><?= $t('join_lead') ?></p>
                <form id="tw-join-form" class="tw-form tw-row" novalidate>
                    <input type="url" name="link" placeholder="<?= $t('join_placeholder') ?>" aria-label="<?= $t('join_title') ?>" required>
                    <button type="submit" class="hi-cta hi-cta-ghost"><?= $t('join_open') ?></button>
                </form>
                <p class="tw-err" id="tw-join-error" hidden></p>
                <ul class="tw-packs" id="tw-mypacks"></ul>
            </div>
        </div>

        <div class="tw-body" id="tw-body" hidden>
            <aside class="tw-side" id="tw-side">
                <details class="tw-side__fold" id="tw-fold" open>
                    <summary><?= $icon($icons['list']) ?><span id="tw-fold-label"><?= $t('side_title') ?></span></summary>
                    <div class="tw-ring" id="tw-ring"></div>
                    <p class="tw-side__h"><?= $t('side_status') ?></p>
                    <ul class="tw-filters" id="tw-filters"></ul>
                    <p class="tw-side__h"><?= $t('side_categories') ?></p>
                    <input type="search" class="tw-catsearch" id="tw-catsearch" placeholder="<?= $t('cat_search') ?>" aria-label="<?= $t('cat_search') ?>">
                    <ul class="tw-cats" id="tw-cats"></ul>
                    <div id="tw-people-box" hidden>
                        <p class="tw-side__h"><?= $t('side_people') ?></p>
                        <ul class="tw-people" id="tw-people"></ul>
                    </div>
                </details>
            </aside>
            <div class="tw-main">
                <div class="tw-tools">
                    <input type="search" id="tw-search" placeholder="<?= $t('search') ?>" aria-label="<?= $t('search') ?>">
                    <div class="tw-seg" role="group" aria-label="<?= $t('source_label') ?>">
                        <button type="button" data-source="en" aria-pressed="true"><?= $t('source_en') ?></button>
                        <button type="button" data-source="fr" aria-pressed="false"><?= $t('source_fr') ?></button>
                    </div>
                    <label class="tw-check"><input type="checkbox" id="tw-preview-all"> <?= $t('preview_all') ?></label>
                </div>
                <p class="tw-hint"><?= $t('keys_hint') ?></p>
                <div class="tw-banner" id="tw-banner" hidden></div>
                <ol class="tw-list" id="tw-list" aria-live="polite"></ol>
                <nav class="tw-pager" id="tw-pager" aria-label="<?= $t('pager') ?>"></nav>
            </div>
        </div>
    </section>

    <section class="atakt-panel" id="publier" aria-labelledby="atakt-dl-h">
        <h2 class="atakt-h2" id="atakt-dl-h"><span>04</span><?= $t('dl_title') ?></h2>
        <p class="atakt-help"><?= $t('dl_lead') ?></p>
        <pre class="atakt-tree" id="atakt-tree" aria-label="<?= $t('dl_tree') ?>"></pre>
        <div class="atakt-actions">
            <button type="button" class="hi-cta hi-cta-solid" id="atakt-zip"><?= $t('dl_zip') ?></button>
            <button type="button" class="hi-cta hi-cta-ghost" id="atakt-txt"><?= $t('dl_txt') ?></button>
        </div>
        <p class="atakt-msg" id="atakt-dl-state" role="status"></p>
        <p class="atakt-help"><?= $t('dl_unsigned') ?></p>
    </section>

    <section class="atakt-panel" id="workshop" aria-labelledby="atakt-ws-h">
        <h2 class="atakt-h2" id="atakt-ws-h"><span>05</span><?= $t('ws_title') ?></h2>
        <p class="atakt-help"><?= $t('ws_lead') ?></p>
        <ol class="site-sse-steps atakt-steps">
            <?php for ($i = 1; $i <= 7; $i++): ?>
            <li class="site-sse-step">
                <span class="site-sse-step__n"><?= sprintf('%02d', $i) ?></span>
                <div>
                    <h3><?= $t('ws_' . $i . '_t') ?></h3>
                    <p><?= $t('ws_' . $i . '_b') ?></p>
                </div>
            </li>
            <?php endfor; ?>
        </ol>
        <details class="atakt-rules">
            <summary><?= $t('ws_update_t') ?></summary>
            <p><?= $t('ws_update_b') ?></p>
        </details>
        <details class="atakt-rules">
            <summary><?= $t('ws_cmd_t') ?></summary>
            <p><?= $t('ws_cmd_b') ?></p>
        </details>
        <details class="atakt-rules">
            <summary><?= $t('rules_title') ?></summary>
            <ul>
                <?php for ($i = 1; $i <= 5; $i++): ?>
                <li><?= $t('rule_' . $i) ?></li>
                <?php endfor; ?>
            </ul>
        </details>
    </section>

    <dialog class="tw-dlg" id="tw-dlg-settings" aria-labelledby="tw-dlg-settings-h">
        <form method="dialog" class="tw-form" id="tw-settings-form" novalidate>
            <h2 id="tw-dlg-settings-h"><?= $t('settings_title') ?></h2>
            <div class="tw-grid">
                <label class="tw-field"><span><?= $t('field_code') ?></span>
                    <input type="text" name="code" maxlength="2" autocomplete="off" spellcheck="false" pattern="[a-z]{2}">
                    <small data-role="code-help"><?= $t('field_code_help') ?></small></label>
                <label class="tw-field"><span><?= $t('field_name') ?></span>
                    <input type="text" name="name" maxlength="40" autocomplete="off">
                    <small><?= $t('field_name_help') ?></small></label>
                <label class="tw-field"><span><?= $t('field_game') ?></span>
                    <select name="game">
                        <option value=""><?= $t('field_game_none') ?></option>
                        <?php foreach ($gameLanguages as $g): ?>
                        <option value="<?= $g ?>"><?= $g ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small><?= $t('field_game_help') ?></small></label>
                <label class="tw-field"><span><?= $t('field_author') ?></span>
                    <input type="text" name="author" maxlength="60" autocomplete="off">
                    <small><?= $t('field_author_help') ?></small></label>
                <label class="tw-field"><span><?= $t('field_pseudo') ?></span>
                    <input type="text" name="pseudo" maxlength="32" autocomplete="nickname">
                    <small><?= $t('field_pseudo_help') ?></small></label>
                <label class="tw-field" data-role="review-field"><span><?= $t('field_review') ?></span>
                    <select name="review">
                        <option value="direct"><?= $t('review_direct') ?></option>
                        <option value="review"><?= $t('review_review') ?></option>
                    </select>
                    <small><?= $t('field_review_help') ?></small></label>
            </div>
            <p class="tw-note" data-role="locked" hidden><?= $t('settings_locked') ?></p>
            <p class="tw-err" data-role="error" hidden></p>
            <div class="tw-row tw-row--end">
                <label class="tw-link tw-file" data-role="import-wrap"><input type="file" accept=".txt,text/plain" data-role="import"><?= $t('import') ?></label>
                <button type="button" class="tw-link tw-link--danger" data-role="reset"><?= $t('reset') ?></button>
                <button type="button" class="hi-cta hi-cta-ghost" value="cancel" data-role="close"><?= $t('cancel') ?></button>
                <button type="submit" class="hi-cta hi-cta-solid" value="ok"><?= $t('save') ?></button>
            </div>
        </form>
    </dialog>

    <dialog class="tw-dlg" id="tw-dlg-share" aria-labelledby="tw-dlg-share-h">
        <div class="tw-form">
            <h2 id="tw-dlg-share-h"><?= $t('share_title') ?></h2>
            <div data-role="local">
                <p><?= $t('share_local_lead') ?></p>
                <ul class="tw-bullets">
                    <?php for ($i = 1; $i <= 4; $i++): ?><li><?= $t('share_point_' . $i) ?></li><?php endfor; ?>
                </ul>
                <div class="tw-grid">
                    <label class="tw-field"><span><?= $t('field_pseudo') ?></span>
                        <input type="text" name="pseudo" maxlength="32" autocomplete="nickname">
                        <small><?= $t('field_pseudo_help') ?></small></label>
                    <label class="tw-field"><span><?= $t('field_review') ?></span>
                        <select name="review">
                            <option value="direct"><?= $t('review_direct') ?></option>
                            <option value="review"><?= $t('review_review') ?></option>
                        </select>
                        <small><?= $t('field_review_help') ?></small></label>
                </div>
                <p class="tw-err" data-role="error" hidden></p>
                <div class="tw-row tw-row--end">
                    <button type="button" class="hi-cta hi-cta-ghost" data-role="close"><?= $t('cancel') ?></button>
                    <button type="button" class="hi-cta hi-cta-solid" data-role="create"><?= $t('share_create') ?></button>
                </div>
            </div>
            <div data-role="pack" hidden>
                <p data-role="pack-lead"></p>
                <label class="tw-field"><span><?= $t('share_editor_link') ?></span>
                    <span class="tw-copy"><input type="text" readonly data-role="editor-link"><button type="button" class="tw-btn" data-copy="editor-link"><?= $t('copy') ?></button></span>
                    <small><?= $t('share_editor_help') ?></small></label>
                <label class="tw-field" data-role="owner-only"><span><?= $t('share_owner_link') ?></span>
                    <span class="tw-copy"><input type="text" readonly data-role="owner-link"><button type="button" class="tw-btn" data-copy="owner-link"><?= $t('copy') ?></button></span>
                    <small class="tw-warn"><?= $t('share_owner_help') ?></small></label>
                <p class="tw-err" data-role="error" hidden></p>
                <div class="tw-row tw-row--end">
                    <button type="button" class="tw-link tw-link--danger" data-role="delete" data-owner="1"><?= $t('share_delete') ?></button>
                    <button type="button" class="tw-link" data-role="rotate" data-owner="1"><?= $t('share_rotate') ?></button>
                    <button type="button" class="hi-cta hi-cta-solid" data-role="close"><?= $t('close') ?></button>
                </div>
            </div>
        </div>
    </dialog>

    <dialog class="tw-dlg" id="tw-dlg-history" aria-labelledby="tw-dlg-history-h">
        <div class="tw-form">
            <h2 id="tw-dlg-history-h"><?= $t('history_title') ?></h2>
            <p class="tw-note" data-role="source"></p>
            <ol class="tw-history" data-role="list"></ol>
            <div class="tw-row tw-row--end">
                <button type="button" class="hi-cta hi-cta-solid" data-role="close"><?= $t('close') ?></button>
            </div>
        </div>
    </dialog>
</article>
