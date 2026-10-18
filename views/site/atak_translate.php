<?php
declare(strict_types=1);
// Atelier public de traduction de l'ATAK natif (/traduire-atak). Toute la logique est dans
// public/assets/js/atak-translate.js : le modèle est lu depuis GET /traduire-atak/modele.json, la saisie reste dans le
// navigateur (sauvegarde locale), l'addon et le zip sont fabriqués sur place. Rien n'est envoyé au serveur.
$t = static fn (string $key): string => htmlspecialchars(__('site.atakt_' . $key), ENT_QUOTES, 'UTF-8');
$release = \App\Support\AtakReleaseInfo::latest();
$js = [];
foreach ([
    'loading', 'load_error', 'template_info', 'progress', 'saved', 'save_failed', 'empty_list', 'page', 'prev', 'next',
    'copy_en', 'clear', 'original', 'english', 'translation', 'placeholder_tr',
    'err_placeholders', 'err_newline', 'warn_space', 'warn_tags', 'warn_percent', 'warn_lone_percent',
    'code_invalid', 'name_missing', 'import_done', 'import_none', 'confirm_reset', 'confirm_switch',
    'zip_building', 'zip_done', 'zip_skipped', 'zip_failed', 'issues_none', 'issues_more', 'stat_done', 'stat_left',
    'stat_errors', 'stat_warnings', 'preview_line',
] as $k) {
    $js[$k] = __('site.atakt_js_' . $k);
}
$gameLanguages = ['Spanish', 'German', 'Italian', 'Polish', 'Portuguese', 'Russian', 'Czech', 'Turkish', 'Chinesesimp', 'Chinese', 'Japanese', 'Korean'];
?>
<article class="atakn atakt" id="atakt"
    data-template-url="<?= htmlspecialchars(url('traduire-atak/modele.json'), ENT_QUOTES, 'UTF-8') ?>"
    data-version="<?= htmlspecialchars((string) ($release['atak_native'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
    data-i18n="<?= htmlspecialchars((string) json_encode($js, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
    <header class="atakn-hero">
        <p class="hi-kicker atakn-kicker"><?= $t('kicker') ?></p>
        <h1 class="atakn-title atakt-title"><?= $t('title') ?></h1>
        <p class="atakn-lead"><?= $t('lead') ?></p>
        <ol class="atakt-flow">
            <?php for ($i = 1; $i <= 4; $i++): ?>
            <li><span><?= sprintf('%02d', $i) ?></span><?= $t('flow_' . $i) ?></li>
            <?php endfor; ?>
        </ol>
        <p class="atakn-note"><?= $t('privacy') ?></p>
    </header>

    <section class="atakt-panel" aria-labelledby="atakt-lang-h">
        <h2 class="atakt-h2" id="atakt-lang-h"><span>01</span><?= $t('lang_title') ?></h2>
        <div class="atakt-grid">
            <label class="atakt-field">
                <span><?= $t('field_code') ?></span>
                <input type="text" id="atakt-code" maxlength="2" autocomplete="off" spellcheck="false" placeholder="es">
                <small><?= $t('field_code_help') ?></small>
            </label>
            <label class="atakt-field">
                <span><?= $t('field_name') ?></span>
                <input type="text" id="atakt-name" maxlength="40" autocomplete="off" placeholder="Español">
                <small><?= $t('field_name_help') ?></small>
            </label>
            <label class="atakt-field">
                <span><?= $t('field_game') ?></span>
                <select id="atakt-game">
                    <option value=""><?= $t('field_game_none') ?></option>
                    <?php foreach ($gameLanguages as $g): ?>
                    <option value="<?= $g ?>"><?= $g ?></option>
                    <?php endforeach; ?>
                </select>
                <small><?= $t('field_game_help') ?></small>
            </label>
            <label class="atakt-field">
                <span><?= $t('field_author') ?></span>
                <input type="text" id="atakt-author" maxlength="60" autocomplete="off">
                <small><?= $t('field_author_help') ?></small>
            </label>
        </div>
        <div class="atakt-actions">
            <label class="hi-cta hi-cta-ghost atakt-file">
                <input type="file" id="atakt-import" accept=".txt,text/plain">
                <?= $t('import') ?>
            </label>
            <button type="button" class="atakt-link" id="atakt-reset"><?= $t('reset') ?></button>
            <span class="atakt-msg" id="atakt-save-state" role="status"></span>
        </div>
        <p class="atakt-help"><?= $t('import_help') ?></p>
    </section>

    <section class="atakt-panel" aria-labelledby="atakt-tr-h">
        <h2 class="atakt-h2" id="atakt-tr-h"><span>02</span><?= $t('tr_title') ?></h2>
        <p class="atakt-help" id="atakt-template-info"><?= $t('js_loading') ?></p>
        <div class="atakt-progress" aria-hidden="true"><i id="atakt-bar"></i></div>
        <p class="atakt-progress-label" id="atakt-progress" role="status"></p>
        <div class="atakt-toolbar">
            <input type="search" id="atakt-search" placeholder="<?= $t('search') ?>" aria-label="<?= $t('search') ?>">
            <select id="atakt-filter" aria-label="<?= $t('filter') ?>">
                <option value="todo"><?= $t('filter_todo') ?></option>
                <option value="all"><?= $t('filter_all') ?></option>
                <option value="done"><?= $t('filter_done') ?></option>
                <option value="errors"><?= $t('filter_errors') ?></option>
                <option value="warnings"><?= $t('filter_warnings') ?></option>
            </select>
        </div>
        <details class="atakt-rules">
            <summary><?= $t('rules_title') ?></summary>
            <ul>
                <?php for ($i = 1; $i <= 5; $i++): ?>
                <li><?= $t('rule_' . $i) ?></li>
                <?php endfor; ?>
            </ul>
        </details>
        <ol class="atakt-list" id="atakt-list" aria-live="polite"></ol>
        <nav class="atakt-pager" id="atakt-pager" aria-label="<?= $t('pager') ?>"></nav>
    </section>

    <section class="atakt-panel" aria-labelledby="atakt-check-h">
        <h2 class="atakt-h2" id="atakt-check-h"><span>03</span><?= $t('check_title') ?></h2>
        <p class="atakt-help"><?= $t('check_lead') ?></p>
        <dl class="atakn-stats atakt-stats" id="atakt-stats"></dl>
        <ul class="atakt-issues" id="atakt-issues"></ul>
    </section>

    <section class="atakt-panel" aria-labelledby="atakt-dl-h">
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
    </section>
</article>
