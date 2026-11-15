<?php
declare(strict_types=1);
/**
 * Formulaire « Placer un avis de recherche » sur une identité fictive. Fait de jeu de rôle : aucune donnée réelle.
 *
 * @var array<string,mixed> $identity
 * @var array<string,mixed> $old valeurs saisies (en cas d'erreur)
 * @var list<string> $errors
 * @var array<string,mixed> $wantedConfig
 * @var array<string, array{0:string,1:string}> $wantedTypes
 * @var array<string,mixed>|null $active
 */
use App\Core\Csrf;
use App\Support\SseWantedCatalog as W;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$lang = (string) ($lang ?? 'fr') === 'en' ? 'en' : 'fr';
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$wantedConfig = is_array($wantedConfig ?? null) ? $wantedConfig : [];
$wantedTypes = is_array($wantedTypes ?? null) ? $wantedTypes : W::types();
$val = static fn (string $k, string $default = ''): string => (string) ($old[$k] ?? $default);
$iid = (int) $identity['id'];
$back = url('atak/sse/papiers/identites/' . $iid);
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-papers.css')) . '"><link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-identity-dossier.css')) . '">';
?>
<div class="page-heading">
    <div>
        <h1>Placer un avis de recherche</h1>
        <p><span translate="no"><?= $h($identity['display_name']) ?></span> · <span translate="no"><?= $h($identity['ref']) ?></span></p>
    </div>
    <div class="page-reference"><a class="link" href="<?= $h($back) ?>">Retour à la fiche</a></div>
</div>

<?php if ($errors !== []): ?>
    <div class="dos-errors" role="alert"><strong>L’avis n’a pas été enregistré.</strong><ul><?php foreach ($errors as $e): ?><li><?= $h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if (empty($wantedReady)): ?>
    <div class="dos-note dos-note--info">Les avis de recherche seront disponibles dès que l’étape de base de données « avis de recherche » aura été passée par l’équipe technique.</div>
<?php elseif (is_array($active ?? null)): ?>
    <div class="dos-note">Cette identité fait déjà l’objet de l’avis <strong translate="no"><?= $h($active['ref']) ?></strong>. <a class="link" href="<?= $h($back . '#avis') ?>">Lever cet avis</a> avant d’en placer un autre.</div>
<?php else: ?>
    <section class="panel">
        <div class="panel-header"><div class="panel-title">Avis de jeu de rôle</div></div>
        <div class="panel-body">
            <p class="muted">L’avis est un fait du jeu de rôle : il ne concerne que le personnage fictif de cette fiche et ne cite aucune organisation réelle. Autorité émettrice (fictive) : <strong translate="no"><?= $h($lang === 'en' ? $wantedConfig['authority_en'] ?? '' : $wantedConfig['authority_fr'] ?? '') ?></strong>. <?php if (!empty($rights) && $rights->canManage()): ?><a class="link" href="<?= $h(url('atak/sse/avis/reglages')) ?>">Réglages de la notice</a><?php endif; ?></p>
            <form class="dos-form" method="post" action="<?= $h(url('atak/sse/papiers/identites/' . $iid . '/avis')) ?>">
                <?= Csrf::field() ?>
                <div class="dos-form__row">
                    <div>
                        <label for="w-type">Type d’avis</label>
                        <select id="w-type" name="notice_type" required>
                            <?php foreach ($wantedTypes as $k => $labels): ?>
                                <option value="<?= $h($k) ?>" <?= $val('notice_type', 'poi') === $k ? 'selected' : '' ?>><?= $h($lang === 'en' ? $labels[1] : $labels[0]) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="w-threat">Niveau de menace</label>
                        <select id="w-threat" name="threat_level" required>
                            <?php foreach (W::THREATS as $k => $labels): ?>
                                <option value="<?= $h($k) ?>" <?= $val('threat_level', 'medium') === $k ? 'selected' : '' ?>><?= $h($lang === 'en' ? $labels[1] : $labels[0]) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="w-armed">Armé et dangereux</label>
                        <select id="w-armed" name="armed">
                            <?php foreach (W::ARMED as $k => $labels): ?>
                                <option value="<?= $h($k) ?>" <?= $val('armed', 'unknown') === $k ? 'selected' : '' ?>><?= $h($lang === 'en' ? $labels[1] : $labels[0]) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label for="w-reason">Motifs, charges ou raison de l’intérêt</label>
                    <textarea id="w-reason" name="reason" required minlength="3" maxlength="<?= (int) W::REASON_MAX ?>"><?= $h($val('reason')) ?></textarea>
                    <p class="hint"><?= (int) W::REASON_MAX ?> caractères au plus. Texte imaginaire, propre au jeu de rôle.</p>
                </div>
                <div>
                    <label for="w-instructions">Mesures à prendre</label>
                    <textarea id="w-instructions" name="instructions" maxlength="<?= (int) W::INSTRUCTIONS_MAX ?>" placeholder="<?= $h($wantedConfig['default_instructions_fr'] ?? '') ?>"><?= $h($val('instructions')) ?></textarea>
                    <p class="hint">Laissez vide pour reprendre la consigne par défaut de la communauté.</p>
                </div>
                <div class="dos-form__row">
                    <div><label for="w-zone">Pays ou zone</label><input id="w-zone" type="text" name="zone" maxlength="120" value="<?= $h($val('zone')) ?>"></div>
                    <div><label for="w-place">Dernier lieu connu</label><input id="w-place" type="text" name="last_seen_place" maxlength="160" value="<?= $h($val('last_seen_place')) ?>"></div>
                    <div><label for="w-seen">Date du dernier lieu connu</label><input id="w-seen" type="datetime-local" name="last_seen_at" value="<?= $h($val('last_seen_at')) ?>"></div>
                </div>
                <div class="dos-form__row">
                    <div><label for="w-contact">Service demandeur (fictif)</label><input id="w-contact" type="text" name="contact_label" maxlength="160" value="<?= $h($val('contact_label', (string) ($wantedConfig['contact_label'] ?? ''))) ?>"></div>
                    <div><label for="w-contact-d">Coordonnées du service (fictives)</label><input id="w-contact-d" type="text" name="contact_detail" maxlength="200" value="<?= $h($val('contact_detail', (string) ($wantedConfig['contact_detail'] ?? ''))) ?>"></div>
                </div>
                <div class="dos-form__row">
                    <div><label for="w-issued">Date d’émission</label><input id="w-issued" type="datetime-local" name="issued_at" value="<?= $h($val('issued_at')) ?>"><p class="hint">Vide : maintenant.</p></div>
                    <div><label for="w-expires">Date d’expiration (facultative)</label><input id="w-expires" type="datetime-local" name="expires_at" value="<?= $h($val('expires_at')) ?>"></div>
                </div>
                <div class="idp-actions">
                    <button class="btn btn--danger" type="submit">Placer l’avis</button>
                    <a class="btn btn--ghost" href="<?= $h($back) ?>">Annuler</a>
                </div>
            </form>
        </div>
    </section>
<?php endif; ?>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
