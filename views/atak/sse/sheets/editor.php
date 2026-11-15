<?php
declare(strict_types=1);
/**
 * Éditeur d'une fiche PDF : textes rédigeables (pré-remplis, modifiables), brouillons enregistrés tout seuls, aperçu,
 * émission. Aucun HTML n'est accepté : gras, italique et listes par mini-balisage. Fait de jeu de rôle : aucune donnée réelle.
 *
 * @var array<string, mixed> $subject
 * @var string $kind
 * @var string $model
 * @var array<string, mixed> $modelDef
 * @var string $lang langue du document
 * @var string $level niveau de diffusion
 * @var list<string> $levels
 * @var array<string, string> $texts
 * @var list<array<string, mixed>> $textFields
 * @var list<string> $errors
 * @var ?string $denial
 * @var bool $ready
 */
use App\Core\Csrf;
use App\Services\Sse\SseRedactionService;
use App\Services\Sse\SseSheetService;
use App\Support\SseSheetCatalog as D;
use App\Support\SseWantedCatalog as W;

// Fiche de vigilance : écran d'émission en étapes (niveau, consignes, cadre, service à informer, aperçu et émission).
if ($model === 'vigilance') {
    require __DIR__ . '/vigilance_editor.php';

    return;
}

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$ui = ($readerLang ?? 'fr') === 'en' ? 1 : 0;
$mLabel = $modelDef['label'][$ui];
$base = url('atak/sse/identites/emission/' . $kind . '/' . (int) ($kind === 'identite' ? $subject['identity_id'] : $subject['person_id']) . '/' . $model);
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-papers.css')) . '"><link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-identity-dossier.css')) . '"><link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-sheets.css')) . '">';
$sseExtraScripts = ($sseExtraScripts ?? '') . '<script src="' . $h(asset_url('assets/js/sse-sheets.js')) . '" defer></script>';
$errors = is_array($errors ?? null) ? $errors : [];
$canIssue = $denial === null && ($ready || $model === 'notice');
$sheetMenu = ['issues' => $issues, 'lang' => $readerLang ?? 'fr', 'level' => $rights->level()];
$subjectName = (string) ($subject['person']['display_name'] ?? $subject['identity']['display_name'] ?? $subject['ref']);
?>
<div class="page-heading">
    <div>
        <h1><?= $h($mLabel) ?></h1>
        <p><span translate="no"><?= $h($subjectName) ?></span> · <span translate="no"><?= $h($subject['ref']) ?></span></p>
    </div>
    <div class="page-reference"><a class="link" href="<?= $h($back) ?>">Retour à la fiche</a></div>
</div>

<?php if ($errors !== []): ?>
    <div class="dos-errors" role="alert"><strong>Le document n’a pas été émis.</strong><ul><?php foreach ($errors as $e): ?><li><?= $h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if (is_array($issuedDoc ?? null)): ?>
    <section class="shx-done" role="status" aria-live="polite">
        <div>
            <strong>Document <span translate="no"><?= $h($issuedDoc['ref']) ?></span> émis.</strong>
            <span class="muted">Le texte imprimé est conservé dans « Documents émis ». La fiche n’a pas été modifiée.</span>
        </div>
        <a id="shx-download" class="btn shx__btn shx__btn--primary" href="<?= $h(url('atak/sse/identites/emissions/' . (int) $issuedDoc['id'] . '/pdf')) ?>" data-autodownload="<?= (int) $issuedDoc['id'] ?>">Télécharger le PDF</a>
        <a class="btn shx__btn" href="<?= $h(url('atak/sse/identites/emissions/' . (int) $issuedDoc['id'] . '/pdf?mode=impression')) ?>" target="_blank" rel="noopener">Imprimer</a>
    </section>
<?php endif; ?>

<?php if ($denial !== null): ?>
    <div class="dos-note dos-note--info" role="note">Vous pouvez rédiger et enregistrer des brouillons, pas émettre ce modèle. <?= $h($denial) ?></div>
<?php elseif (!$ready && $model !== 'notice'): ?>
    <div class="dos-note dos-note--info" role="note">Les fiches PDF seront disponibles dès que l’étape de base de données « fiches PDF » aura été passée par l’équipe technique. Les brouillons et l’émission restent fermés en attendant.</div>
<?php endif; ?>
<?php if ($model === 'notice' && ($subject['identity_id'] ?? null) === null): ?>
    <div class="dos-note dos-note--info" role="note">Cette fiche n’a pas d’identité du registre : créez-la depuis la fiche personne pour placer un avis de recherche.</div>
<?php endif; ?>

<form class="shx-form" id="shx-form" method="post" action="<?= $h($base . '/emettre') ?>"
      data-base="<?= $h($base) ?>" data-csrf="<?= $h(Csrf::token()) ?>" data-ready="<?= $ready ? '1' : '0' ?>" data-model="<?= $h($model) ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="draft_id" id="shx-draft-id" value="<?= (int) ($draft['id'] ?? 0) ?>">

    <section class="panel">
        <div class="panel-header"><div class="panel-title">Réglages du document</div></div>
        <div class="panel-body shx-grid">
            <div>
                <label for="shx-lang">Langue du document</label>
                <select id="shx-lang" name="lang">
                    <option value="fr" <?= $lang === 'fr' ? 'selected' : '' ?>>Français</option>
                    <option value="en" <?= $lang === 'en' ? 'selected' : '' ?>>English</option>
                </select>
            </div>
            <div>
                <label for="shx-class">Classification</label>
                <select id="shx-class" name="classification">
                    <?php foreach ($levels as $l): ?>
                        <option value="<?= $h($l) ?>" <?= $level === $l ? 'selected' : '' ?>><?= $h(SseRedactionService::levelLabel($l) . ' — ' . \App\Support\SseUsClassification::banner($l)) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="hint">Les données au-dessus de ce niveau (identité, lieu, biométrie…) sont remplacées par une barre dans le PDF et dans les variables.</p>
            </div>
            <?php if ($model !== 'notice'): ?>
                <div>
                    <label for="shx-faces">Pages</label>
                    <select id="shx-faces" name="faces">
                        <option value="auto" <?= $faces === 'auto' ? 'selected' : '' ?>>Selon le contenu</option>
                        <option value="1" <?= $faces === '1' ? 'selected' : '' ?>>Une seule page (texte coupé si trop long)</option>
                        <option value="2" <?= $faces === '2' ? 'selected' : '' ?>>Recto et verso (mentions au verso)</option>
                    </select>
                </div>
                <div>
                    <label for="shx-threat">Niveau de menace</label>
                    <select id="shx-threat" name="threat">
                        <option value="">Non précisé</option>
                        <?php foreach (W::THREATS as $k => $labels): ?>
                            <option value="<?= $h($k) ?>" <?= $threat === $k ? 'selected' : '' ?>><?= $h($labels[$ui]) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <?php if ($model === 'vigilance'): ?>
                <div>
                    <label for="shx-armed">Armement</label>
                    <select id="shx-armed" name="armed">
                        <?php foreach (W::ARMED as $k => $labels): ?>
                            <option value="<?= $h($k) ?>" <?= $armed === $k ? 'selected' : '' ?>><?= $h($labels[$ui]) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="shx-validity">Durée de validité</label>
                    <select id="shx-validity" name="validity">
                        <?php foreach (D::VALIDITY_DAYS as $d): ?>
                            <option value="<?= (int) $d ?>" <?= $validity === $d ? 'selected' : '' ?>><?= (int) $d ?> jours</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($model === 'control'): ?>
        <section class="panel">
            <div class="panel-header"><div class="panel-title">Contrôles repris dans la fiche</div><div class="panel-meta"><?= count($checks) ?> contrôle(s)</div></div>
            <div class="panel-body">
                <input type="hidden" name="checks_sent" value="1">
                <?php if ($checks === []): ?>
                    <p class="muted">Aucun contrôle d’identité enregistré pour cette fiche : le document sera émis sans tableau de contrôles.</p>
                <?php else: ?>
                    <ul class="shx-checks">
                        <?php foreach ($checks as $c): ?>
                            <li>
                                <label class="shx-check">
                                    <input type="checkbox" name="checks[]" value="<?= $h($c['ref']) ?>" <?= in_array((string) $c['ref'], $selectedChecks, true) ? 'checked' : '' ?>>
                                    <span><strong translate="no"><?= $h($c['ref']) ?></strong> · <?= $h($c['at']) ?> · <?= $h($c['doc'] !== '' ? $c['doc'] : '—') ?> · <?= $h($c['verdict'] !== '' ? $c['verdict'] : '—') ?></span>
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="panel">
        <div class="panel-header">
            <div class="panel-title">Textes du document</div>
            <div class="panel-meta" id="shx-save-state" aria-live="polite"><?= !empty($draft) ? 'Brouillon « ' . $h($draft['title']) . ' »' : ($ready ? 'Brouillon enregistré automatiquement' : 'Brouillons indisponibles') ?></div>
        </div>
        <div class="panel-body shx-texts">
            <p class="muted">Les textes sont pré-remplis à partir de la fiche ; modifiez-les librement. Gras : <code>**texte**</code>, italique : <code>*texte*</code>, liste : une ligne qui commence par <code>- </code>. Aucun HTML n’est accepté.</p>
            <?php foreach ($textFields as [$key, $max, $labels, $hints]): ?>
                <?php $val = (string) ($texts[$key] ?? ''); $fid = 'shx-t-' . $key; ?>
                <div class="shx-field" data-field="<?= $h($key) ?>" data-max="<?= (int) $max ?>">
                    <div class="shx-field__top">
                        <label for="<?= $h($fid) ?>"><?= $h($labels[$ui]) ?></label>
                        <span class="shx-count" aria-live="polite"><span data-count>0</span> / <?= (int) $max ?></span>
                    </div>
                    <div class="shx-toolbar" role="toolbar" aria-label="Mise en forme de « <?= $h($labels[$ui]) ?> »">
                        <button type="button" class="shx-tool" data-wrap="**" title="Gras" aria-label="Gras"><strong>B</strong></button>
                        <button type="button" class="shx-tool" data-wrap="*" title="Italique" aria-label="Italique"><em>I</em></button>
                        <button type="button" class="shx-tool" data-list="1" title="Liste à puces" aria-label="Liste à puces">• Liste</button>
                        <select class="shx-tool shx-tool--select" data-insert="var" aria-label="Insérer une variable">
                            <option value="">Insérer une variable</option>
                            <?php foreach ($variableDefs as $vk => $vd): ?>
                                <option value="{<?= $h($vk) ?>}"><?= $h('{' . $vk . '} — ' . $vd[$ui] . (($variables[$vk] ?? '') !== '' ? ' (' . mb_strimwidth((string) $variables[$vk], 0, 36, '…', 'UTF-8') . ')' : '') . ($vd[2] !== null ? ' · sensible' : '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($phrases !== []): ?>
                            <select class="shx-tool shx-tool--select" data-insert="phrase" aria-label="Insérer une trame de phrase">
                                <option value="">Insérer une trame</option>
                                <?php foreach ($phrases as $p): ?>
                                    <option value="<?= $h($p['body']) ?>"><?= $h($p['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        <button type="button" class="shx-tool" data-reset="1" title="Remettre le texte pré-rempli">↺ Texte par défaut</button>
                    </div>
                    <textarea id="<?= $h($fid) ?>" name="texts[<?= $h($key) ?>]" rows="<?= $max > 700 ? 8 : ($max > 350 ? 6 : 4) ?>" maxlength="<?= (int) $max * 2 ?>" data-orig="<?= $h($val) ?>"><?= $h($val) ?></textarea>
                    <p class="hint"><?= $h($hints[$ui]) ?></p>
                    <p class="shx-over" data-over hidden role="status">Ce texte dépasse la place prévue sur la page : il sera réduit puis coupé dans le PDF.</p>
                </div>
            <?php endforeach; ?>
            <p class="shx-layout" id="shx-layout" aria-live="polite">Mise en page : vérifiée à la demande et après chaque modification.</p>
            <p class="shx-sensitive" id="shx-sensitive" hidden role="note"></p>
        </div>
    </section>

    <?php if ($ready): ?>
        <section class="panel">
            <div class="panel-header"><div class="panel-title">Brouillons</div><div class="panel-meta"><?= count($drafts) ?> brouillon(s) pour ce modèle</div></div>
            <div class="panel-body">
                <label for="shx-draft-title">Nom du brouillon</label>
                <input id="shx-draft-title" type="text" name="draft_title" maxlength="80" value="<?= $h($draft['title'] ?? '') ?>" placeholder="Ex. version pour le briefing">
                <?php if ($drafts !== []): ?>
                    <ul class="shx-drafts">
                        <?php foreach ($drafts as $d): ?>
                            <li>
                                <a class="link" href="<?= $h($base . '?brouillon=' . (int) $d['id']) ?>"><?= $h($d['title'] !== '' ? $d['title'] : 'Sans nom') ?></a>
                                <span class="muted"><?= $h(strtoupper($d['lang'])) ?> · <?= $h($d['updated_at']) ?> UTC</span>
                                <button type="submit" class="btn btn--ghost shx__btn shx__btn--small" formmethod="post" formaction="<?= $h($base . '/brouillon/' . (int) $d['id'] . '/supprimer') ?>" formnovalidate onclick="return confirm('Supprimer ce brouillon ?');">Supprimer</button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <p class="hint">Le brouillon est enregistré tout seul pendant la frappe. <a class="link" href="<?= $h($base) ?>">Commencer un nouveau brouillon</a> (vide, pré-rempli).</p>
            </div>
        </section>
    <?php endif; ?>

    <div class="shx-actions" role="group" aria-label="Actions du document">
        <button type="submit" class="btn shx__btn" formaction="<?= $h($base . '/apercu') ?>" formtarget="_blank" formnovalidate id="shx-preview">Aperçu PDF</button>
        <?php if ($ready): ?>
            <button type="button" class="btn shx__btn" id="shx-save">Enregistrer le brouillon</button>
        <?php endif; ?>
        <?php if ($canIssue): ?>
            <button type="submit" class="btn shx__btn shx__btn--primary" id="shx-issue">Émettre le PDF</button>
        <?php endif; ?>
    </div>
    <p class="muted">À ne jamais diffuser hors de la communauté. Émettre ne modifie jamais la fiche.</p>
</form>

<?php require dirname(__DIR__) . '/_sheet_issues.php'; ?>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
