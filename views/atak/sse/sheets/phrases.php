<?php
declare(strict_types=1);
/**
 * Trames de phrases des fiches PDF : textes réutilisables par la communauté, avec variables ({nom}, {alias}, {menace}…)
 * résolues à l'émission selon l'habilitation du rédacteur. Lire : toute personne qui rédige ; créer, modifier, supprimer :
 * gestion des dossiers SSE.
 *
 * @var bool $ready
 * @var list<array<string, mixed>> $phrases
 * @var ?array<string, mixed> $edit
 * @var list<string> $errors
 * @var array<string, mixed> $old
 * @var bool $canManagePhrases
 * @var array<string, array<string, mixed>> $models
 * @var array<string, array{0: string, 1: string, 2: ?string}> $variables
 */
use App\Core\Csrf;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$ui = ($lang ?? 'fr') === 'en' ? 1 : 0;
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-papers.css')) . '"><link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-identity-dossier.css')) . '"><link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-sheets.css')) . '">';
$val = static function (string $k, string $d = '') use ($old, $edit): string {
    return (string) ($old[$k] ?? ($edit[$k] ?? $d));
};
$editId = (int) ($old['id'] ?? ($edit['id'] ?? 0));
$self = url('atak/sse/identites/emission/phrases');
?>
<div class="page-heading">
    <div>
        <h1>Trames de phrases</h1>
        <p>Textes réutilisables dans l’éditeur des fiches PDF, propres à votre communauté.</p>
    </div>
    <div class="page-reference"><a class="link" href="<?= $h(url('atak/sse/identites')) ?>">Retour aux identités</a></div>
</div>

<?php if ($errors !== []): ?>
    <div class="dos-errors" role="alert"><strong>La trame n’a pas été enregistrée.</strong><ul><?php foreach ($errors as $e): ?><li><?= $h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<?php if (!$ready): ?>
    <div class="dos-note dos-note--info" role="note">Les trames seront disponibles dès que l’étape de base de données « fiches PDF » aura été passée par l’équipe technique.</div>
<?php endif; ?>

<?php if ($canManagePhrases && $ready): ?>
    <section class="panel">
        <div class="panel-header"><div class="panel-title"><?= $editId > 0 ? 'Modifier la trame' : 'Nouvelle trame' ?></div></div>
        <div class="panel-body">
            <form class="dos-form" method="post" action="<?= $h($self) ?>">
                <?= Csrf::field() ?>
                <input type="hidden" name="id" value="<?= $editId ?>">
                <div class="shx-grid">
                    <div>
                        <label for="ph-label">Nom de la trame</label>
                        <input id="ph-label" type="text" name="label" maxlength="80" required value="<?= $h($val('label')) ?>">
                    </div>
                    <div>
                        <label for="ph-model">Modèle</label>
                        <select id="ph-model" name="model">
                            <option value="">Tous les modèles</option>
                            <?php foreach ($models as $k => $def): ?>
                                <option value="<?= $h($k) ?>" <?= $val('model') === $k ? 'selected' : '' ?>><?= $h($def['label'][$ui]) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="ph-lang">Langue</label>
                        <select id="ph-lang" name="lang">
                            <option value="fr" <?= $val('lang', 'fr') === 'fr' ? 'selected' : '' ?>>Français</option>
                            <option value="en" <?= $val('lang') === 'en' ? 'selected' : '' ?>>English</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label for="ph-body">Texte (600 caractères au plus)</label>
                    <textarea id="ph-body" name="body" rows="5" maxlength="600" required><?= $h($val('body')) ?></textarea>
                    <p class="hint">Variables : <?php foreach ($variables as $vk => $vd): ?><code title="<?= $h($vd[$ui]) ?>">{<?= $h($vk) ?>}</code> <?php endforeach; ?>. Une variable de donnée masquée à l’émission donne une barre de caviardage, jamais la valeur.</p>
                </div>
                <div class="idp-actions">
                    <button class="btn shx__btn shx__btn--primary" type="submit">Enregistrer la trame</button>
                    <?php if ($editId > 0): ?><a class="btn btn--ghost shx__btn" href="<?= $h($self) ?>">Annuler</a><?php endif; ?>
                </div>
            </form>
        </div>
    </section>
<?php elseif ($ready): ?>
    <div class="dos-note dos-note--info" role="note">Vous pouvez insérer les trames dans vos textes ; les créer ou les modifier demande d’écrire dans « Fichiers et sujets » (gestion des dossiers SSE).</div>
<?php endif; ?>

<section class="panel" style="margin-top:12px">
    <div class="panel-header"><div class="panel-title">Trames de la communauté</div><div class="panel-meta"><?= count($phrases) ?> trame(s)</div></div>
    <div class="panel-body">
        <?php if ($phrases === []): ?>
            <p class="muted">Aucune trame pour le moment.</p>
        <?php else: ?>
            <ul class="shx-phrases">
                <?php foreach ($phrases as $p): ?>
                    <li>
                        <div>
                            <strong translate="no"><?= $h($p['label']) ?></strong>
                            <span class="muted"><?= $h($p['model'] !== '' ? ($models[$p['model']]['label'][$ui] ?? $p['model']) : 'Tous les modèles') ?> · <?= $h(strtoupper($p['lang'])) ?></span>
                            <p translate="no"><?= nl2br($h($p['body'])) ?></p>
                        </div>
                        <?php if ($canManagePhrases): ?>
                            <div class="shx-phrases__act">
                                <a class="btn shx__btn shx__btn--small" href="<?= $h($self . '?modifier=' . (int) $p['id']) ?>">Modifier</a>
                                <form method="post" action="<?= $h($self . '/' . (int) $p['id'] . '/supprimer') ?>" onsubmit="return confirm('Supprimer cette trame ?');">
                                    <?= Csrf::field() ?>
                                    <button class="btn btn--ghost shx__btn shx__btn--small" type="submit">Supprimer</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
