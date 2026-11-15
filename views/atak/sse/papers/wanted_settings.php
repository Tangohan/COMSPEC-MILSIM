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
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-papers.css')) . '"><link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-identity-dossier.css')) . '">';
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
        <form class="dos-form" method="post" action="<?= $h(url('atak/sse/avis/reglages')) ?>">
            <?= Csrf::field() ?>
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
            <div class="idp-actions"><button class="btn" type="submit">Enregistrer</button></div>
        </form>
    </div>
</section>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
