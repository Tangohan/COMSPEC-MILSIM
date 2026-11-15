<?php
declare(strict_types=1);
/**
 * Réglages de la notice de recherche, par communauté : autorité émettrice et service demandeur FICTIFS, format de
 * référence, libellé de la bannière, types d'avis, pied de page. Réservé à la gestion des fichiers SSE.
 *
 * @var array<string,mixed> $config
 * @var list<string> $errors
 */
use App\Core\Csrf;
use App\Support\SseWantedCatalog as W;
use App\Support\SseWantedCodes;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$lang = (string) ($lang ?? 'fr') === 'en' ? 'en' : 'fr';
$config = is_array($config ?? null) ? $config : W::DEFAULTS;
$errors = is_array($errors ?? null) ? $errors : [];
$customLines = [];
foreach (is_array($config['custom_types'] ?? null) ? $config['custom_types'] : [] as $key => $labels) {
    $customLines[] = $key . '|' . ($labels[0] ?? '') . '|' . ($labels[1] ?? '');
}
$disabled = is_array($config['disabled_types'] ?? null) ? $config['disabled_types'] : [];
$catalogue = SseWantedCodes::catalogue();
$customCodeLines = [];
foreach ($config['codes']['custom'] ?? [] as $code => $c) {
    $customCodeLines[] = implode('|', [$code, $c['fr'], $c['en'], $c['text_fr'], $c['text_en'], $c['threat']]);
}
$customTplLines = [];
foreach ($config['codes']['custom_templates'] ?? [] as $key => $t) {
    $customTplLines[] = implode('|', [$key, $t['fr'], $t['en'], $t['type'], $t['threat'], $t['armed'], implode(' ', $t['reasons']), implode(' ', $t['measures'])]);
}
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-papers.css')) . '"><link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-identity-dossier.css')) . '"><link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-wanted-codes.css')) . '">';
$sseExtraScripts = ($sseExtraScripts ?? '') . '<script src="' . $h(asset_url('assets/js/sse-wanted-settings.js')) . '" defer></script>';
?>
<div class="page-heading">
    <div>
        <h1>Réglages de la notice</h1>
        <p>Ce que la notice PDF affiche pour votre communauté. Tout est fictif : aucune organisation, aucun emblème ni aucun nom réel.</p>
    </div>
    <div class="page-reference"><a class="link" href="<?= $h(url('atak/sse/avis')) ?>">Avis de recherche</a></div>
</div>
<?php if ($errors !== []): ?>
    <div class="dos-errors" role="alert"><strong>Les réglages n’ont pas été enregistrés.</strong><ul><?php foreach ($errors as $e): ?><li><?= $h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<section class="panel">
    <div class="panel-header"><div class="panel-title">Autorité, référence et libellés</div></div>
    <div class="panel-body">
        <form class="dos-form wc-wide" method="post" action="<?= $h(url('atak/sse/avis/reglages')) ?>">
            <?= Csrf::field() ?>
            <nav class="wc-settings-tabs" role="tablist" aria-label="Sections des réglages" data-wc-stabs hidden>
                <button class="wc-tab" type="button" role="tab" id="wc-stab-notice" aria-controls="wc-pane-notice" aria-selected="true" data-wc-stab="notice">Notice</button>
                <button class="wc-tab" type="button" role="tab" id="wc-stab-codes" aria-controls="wc-pane-codes" aria-selected="false" data-wc-stab="codes">Codification</button>
            </nav>
            <div class="wc-settings-pane" id="wc-pane-notice" data-wc-pane="notice" role="tabpanel" aria-labelledby="wc-stab-notice">
            <div class="dos-form__row">
                <div><label for="s-auth-fr">Autorité émettrice fictive (français)</label><input id="s-auth-fr" type="text" name="authority_fr" maxlength="160" value="<?= $h($config['authority_fr'] ?? '') ?>" required></div>
                <div><label for="s-auth-en">Autorité émettrice fictive (anglais)</label><input id="s-auth-en" type="text" name="authority_en" maxlength="160" value="<?= $h($config['authority_en'] ?? '') ?>" required></div>
            </div>
            <div class="dos-form__row">
                <div><label for="s-contact">Service demandeur par défaut (fictif)</label><input id="s-contact" type="text" name="contact_label" maxlength="160" value="<?= $h($config['contact_label'] ?? '') ?>"></div>
                <div><label for="s-contact-d">Coordonnées par défaut (fictives)</label><input id="s-contact-d" type="text" name="contact_detail" maxlength="200" value="<?= $h($config['contact_detail'] ?? '') ?>"></div>
            </div>
            <div class="dos-form__row">
                <div>
                    <label for="s-format">Format de la référence</label>
                    <input id="s-format" type="text" name="ref_format" maxlength="40" value="<?= $h($config['ref_format'] ?? 'A-{N}/{Y}') ?>" required>
                    <p class="hint">{N} : numéro sur 4 chiffres ({N:5} sur 5), {Y} : année sur 4 chiffres, {y} sur 2. Exemple : « A-{N}/{Y} » donne A-0123/2026.</p>
                </div>
                <div>
                    <label for="s-banner">Libellé du bandeau</label>
                    <select id="s-banner" name="banner">
                        <?php foreach (W::BANNERS as $k => $labels): ?><option value="<?= $h($k) ?>" <?= ($config['banner'] ?? 'red') === $k ? 'selected' : '' ?>><?= $h($labels[0] . ' / ' . $labels[1]) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="s-emblem">Emblème</label>
                    <select id="s-emblem" name="emblem">
                        <?php foreach (W::EMBLEMS as $k => $labels): ?><option value="<?= $h($k) ?>" <?= ($config['emblem'] ?? 'neutral') === $k ? 'selected' : '' ?>><?= $h($lang === 'en' ? $labels[1] : $labels[0]) ?></option><?php endforeach; ?>
                    </select>
                    <p class="hint">Le logo de la communauté n’est utilisé que si vous le choisissez ; sinon un pictogramme neutre.</p>
                </div>
            </div>
            <div class="dos-form__row">
                <div>
                    <label for="s-lang">Langue de la notice</label>
                    <select id="s-lang" name="pdf_lang">
                        <option value="auto" <?= ($config['pdf_lang'] ?? 'auto') === 'auto' ? 'selected' : '' ?>>Celle du lecteur</option>
                        <option value="fr" <?= ($config['pdf_lang'] ?? '') === 'fr' ? 'selected' : '' ?>>Français</option>
                        <option value="en" <?= ($config['pdf_lang'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
                    </select>
                </div>
                <div>
                    <label for="s-class">Classification par défaut des fiches (génération rapide)</label>
                    <select id="s-class" name="default_classification">
                        <?php foreach (\App\Repositories\SseCaseRepository::CLASSIFICATION_LABELS as $ck => $cl): ?>
                            <option value="<?= $h($ck) ?>" <?= ($config['default_classification'] ?? 'encadrement') === $ck ? 'selected' : '' ?>><?= $h($cl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="s-expiry">Expiration par défaut (jours, 0 : aucune)</label>
                    <input id="s-expiry" type="number" name="default_expiry_days" min="0" max="3650" value="<?= (int) ($config['default_expiry_days'] ?? 0) ?>">
                </div>
            </div>
            <div class="dos-form__row">
                <div><label for="s-instr-fr">Consigne par défaut (français)</label><textarea id="s-instr-fr" name="default_instructions_fr" maxlength="<?= (int) W::INSTRUCTIONS_MAX ?>"><?= $h($config['default_instructions_fr'] ?? '') ?></textarea></div>
                <div><label for="s-instr-en">Consigne par défaut (anglais)</label><textarea id="s-instr-en" name="default_instructions_en" maxlength="<?= (int) W::INSTRUCTIONS_MAX ?>"><?= $h($config['default_instructions_en'] ?? '') ?></textarea></div>
            </div>
            <fieldset>
                <legend>Types d’avis proposés</legend>
                <?php foreach (W::TYPES as $k => $labels): ?>
                    <label class="check"><input type="checkbox" name="enabled_types[]" value="<?= $h($k) ?>" <?= in_array($k, $disabled, true) ? '' : 'checked' ?>> <?= $h($lang === 'en' ? $labels[1] : $labels[0]) ?></label>
                <?php endforeach; ?>
                <label for="s-custom">Types propres à la communauté (une ligne chacun : clé|libellé français|libellé anglais, huit au plus)</label>
                <textarea id="s-custom" name="custom_types" rows="4"><?= $h(implode("\n", $customLines)) ?></textarea>
            </fieldset>
            <fieldset>
                <legend>Aspect de la notice</legend>
                <label class="check"><input type="checkbox" name="watermark" value="1" <?= !empty($config['watermark']) ? 'checked' : '' ?>> Filigrane discret « Fiction » sur chaque page</label>
                <label class="check"><input type="checkbox" name="footer" value="1" <?= !empty($config['footer']) ? 'checked' : '' ?>> Mention « Document de fiction — jeu de rôle Arma 3 » en pied de chaque page</label>
                <p class="hint">Ne retirez cette mention que pour un usage strictement interne à la communauté : la notice ne doit jamais sortir du jeu de rôle. Le fichier PDF garde toujours cette mention dans ses propriétés.</p>
            </fieldset>
            </div>
            <div class="wc-settings-pane" id="wc-pane-codes" data-wc-pane="codes" role="tabpanel" aria-labelledby="wc-stab-codes">
                <input type="hidden" name="codes_present" value="1">
                <p class="wc-sec__desc">Les codes de motifs (M-…) et de mesures (A-…) proposés dans le formulaire d’avis. Désactivez, renommez, réordonnez ou ajoutez les vôtres : un code posé sur un avis garde son libellé du jour de la pose.</p>
                <?php foreach ([SseWantedCodes::REASONS => 'Motifs', SseWantedCodes::MEASURES => 'Mesures à prendre'] as $kind => $kindTitle): ?>
                    <h3 class="wc-set-title"><?= $h($kindTitle) ?></h3>
                    <?php
                    $famKey = $kind === SseWantedCodes::REASONS ? 'reason_families' : 'measure_families';
                    $groups = [];
                    foreach (SseWantedCodes::all($config, $kind) as $code => $c) {
                        $groups[$c['custom'] ? '_custom' : (string) $c['family']][$code] = $c;
                    }
                    $pos = 0;
                    $rankOf = [];
                    foreach (array_keys(SseWantedCodes::all($config, $kind)) as $code) {
                        $rankOf[$code] = ++$pos;
                    }
                    ?>
                    <?php foreach ($groups as $fam => $rows): $famName = $fam === '_custom' ? 'Codes propres à la communauté' : (string) (($catalogue[$famKey][$fam][$lang === 'en' ? 'en' : 'fr']) ?? $fam); ?>
                        <details class="wc-fam">
                            <summary><?= $h($famName) ?> <span class="wc-mini"><?= count($rows) ?></span></summary>
                            <div class="wc-fam__rows">
                                <?php foreach ($rows as $code => $c): ?>
                                    <div class="wc-set">
                                        <input type="checkbox" name="codes_enabled[]" value="<?= $h($code) ?>" <?= $c['enabled'] ? 'checked' : '' ?> aria-label="Proposer <?= $h($code) ?>">
                                        <input type="hidden" name="codes_row[]" value="<?= $h($code) ?>">
                                        <span class="wc-set__code" translate="no"><?= $h($code) ?></span>
                                        <?php if ($c['custom']): ?>
                                            <span translate="no"><?= $h($c['fr']) ?></span><span class="wc-set__en" translate="no"><?= $h($c['en']) ?></span>
                                        <?php else: ?>
                                            <input type="text" name="codes_fr[<?= $h($code) ?>]" maxlength="80" value="<?= $h($c['fr']) ?>" aria-label="Libellé français de <?= $h($code) ?>" translate="no">
                                            <input type="text" data-en name="codes_en[<?= $h($code) ?>]" maxlength="80" value="<?= $h($c['en']) ?>" aria-label="Libellé anglais de <?= $h($code) ?>" translate="no">
                                        <?php endif; ?>
                                        <input type="number" name="codes_rank[<?= $h($code) ?>]" min="1" max="999" value="<?= (int) $rankOf[$code] ?>" aria-label="Rang de <?= $h($code) ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                <div>
                    <label for="s-codes-custom">Codes propres (une ligne chacun : code|libellé français|libellé anglais|texte français|texte anglais|menace)</label>
                    <textarea id="s-codes-custom" name="codes_custom" rows="4" placeholder="M-TER-90|Réseau de passeurs|Smuggling ring|Anime un réseau de passeurs.|Runs a smuggling ring.|medium"><?= $h(implode("\n", $customCodeLines)) ?></textarea>
                    <p class="hint">Motif : M-ABC-12. Mesure : A-ABC. Menace : low, medium, high ou critical. Trente codes propres au plus. Un code du catalogue se renomme dans la liste ci-dessus.</p>
                </div>
                <fieldset>
                    <legend>Codes préremplis par type d’avis</legend>
                    <p class="hint">Codes séparés par des virgules. Ils sont choisis d’office dans le formulaire tant que l’opérateur n’a pas fait son propre choix.</p>
                    <?php foreach (W::types($config) as $type => $labels): $d = SseWantedCodes::defaultsForType((string) $type, $config); ?>
                        <div class="dos-form__row">
                            <div><label for="s-dr-<?= $h($type) ?>">Motifs · <?= $h($lang === 'en' ? $labels[1] : $labels[0]) ?></label><input id="s-dr-<?= $h($type) ?>" type="text" name="codes_default_reasons[<?= $h($type) ?>]" value="<?= $h(implode(', ', $d['reasons'])) ?>" translate="no"></div>
                            <div><label for="s-dm-<?= $h($type) ?>">Mesures · <?= $h($lang === 'en' ? $labels[1] : $labels[0]) ?></label><input id="s-dm-<?= $h($type) ?>" type="text" name="codes_default_measures[<?= $h($type) ?>]" value="<?= $h(implode(', ', $d['measures'])) ?>" translate="no"></div>
                        </div>
                    <?php endforeach; ?>
                </fieldset>
                <fieldset>
                    <legend>Modèles d’avis</legend>
                    <?php $tplOff = $config['codes']['templates_disabled'] ?? []; foreach ($catalogue['templates'] as $key => $t): ?>
                        <label class="check"><input type="checkbox" name="codes_templates[]" value="<?= $h($key) ?>" <?= in_array($key, $tplOff, true) ? '' : 'checked' ?>> <?= $h($lang === 'en' ? $t['en'] : $t['fr']) ?> <span class="wc-mini" translate="no"><?= $h(implode(' ', array_merge($t['reasons'], $t['measures']))) ?></span></label>
                    <?php endforeach; ?>
                    <label for="s-codes-tpl">Modèles propres (une ligne chacun : clé|libellé français|libellé anglais|type|menace|armé|motifs|mesures)</label>
                    <textarea id="s-codes-tpl" name="codes_custom_templates" rows="3" placeholder="courrier|Courrier de cellule|Cell courier|surveillance|medium|unknown|M-TRF-06|A-OBS A-REP"><?= $h(implode("\n", $customTplLines)) ?></textarea>
                </fieldset>
            </div>
            <div class="idp-actions"><button class="btn" type="submit">Enregistrer</button></div>
        </form>
    </div>
</section>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
