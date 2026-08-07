<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $model */
/** @var array<string,mixed> $armaModel */
/** @var string $sqfSnippet */
$model = is_array($model ?? null) ? $model : [];
$armaModel = is_array($armaModel ?? null) ? $armaModel : [];
$sqfSnippet = (string) ($sqfSnippet ?? '');
$canManage = (bool) ($canManage ?? false);
$payload = is_array($model['payload'] ?? null) ? $model['payload'] : [];
$id = (int) ($model['id'] ?? 0);
require __DIR__ . '/_subnav.php';

$listPreview = static function (mixed $list, int $max = 6) use ($h): string {
    if (!is_array($list) || $list === []) {
        return '— (banques intégrées)';
    }
    $slice = array_slice(array_map('strval', $list), 0, $max);
    $out = implode(' · ', array_map($h, $slice));
    if (count($list) > $max) {
        $out .= ' …';
    }

    return $out;
};
?>
<div class="breadcrumb">
    Athena / SSE /
    <a class="link" href="<?= $h(url('atak/sse/dev')) ?>">Atelier</a> /
    <a class="link" href="<?= $h(url('atak/sse/dev/modeles')) ?>">Modèles</a> /
    <strong><?= $h($model['name'] ?? '') ?></strong>
</div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Atelier // Fiche</div>
        <h1><?= $h($model['name'] ?? 'Modèle') ?></h1>
        <p>
            <?= $h($model['profile_label'] ?? '') ?> · <?= $h($model['region_label'] ?? '') ?> ·
            <?= $h($model['theme_label'] ?? '') ?> —
            <strong><?= $h($model['status_label'] ?? '') ?></strong>
        </p>
    </div>
    <div class="page-reference" style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn" href="<?= $h(url('atak/sse/dev/modeles/' . $id . '/export')) ?>">Fichier d’échange</a>
        <a class="btn btn--ghost" href="<?= $h(url('atak/sse/dev/modeles/' . $id . '/export?format=sqf')) ?>">Script mission</a>
        <?php if ($canManage): ?>
            <a class="btn btn--ghost" href="<?= $h(url('atak/sse/dev/modeles/' . $id . '/modifier')) ?>">Modifier</a>
        <?php endif; ?>
    </div>
</div>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">01</span> Synthèse</div>
        <div class="panel-meta">v<?= (int) ($model['version'] ?? 1) ?></div>
    </div>
    <div class="panel-body">
        <div class="lab-form-grid">
            <div class="lab-form-field"><strong>Richesse</strong><div><?= $h($model['complexity_label'] ?? '') ?></div></div>
            <div class="lab-form-field"><strong>Réseau</strong><div><?= (int) ($model['network_size'] ?? 0) ?> contact(s)</div></div>
            <div class="lab-form-field"><strong>Auteur</strong><div><?= $h($model['author_label'] ?? '—') ?></div></div>
            <div class="lab-form-field"><strong>Mis à jour</strong><div><?= $h($model['updated_at'] ?? '') ?></div></div>
            <div class="lab-form-field lab-form-field--span2">
                <strong>Contenus</strong>
                <div>
                    <?= !empty($model['include_biometrics']) ? 'Biométrie' : '' ?>
                    <?= !empty($model['include_phone']) ? ' · Téléphone' : '' ?>
                    <?= !empty($model['include_documents']) ? ' · Documents' : '' ?>
                    <?= !empty($model['include_computer']) ? ' · Ordinateur' : '' ?>
                </div>
            </div>
            <?php if (!empty($model['notes'])): ?>
                <div class="lab-form-field lab-form-field--span2">
                    <strong>Notes</strong>
                    <div><?= nl2br($h($model['notes'])) ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="panel" style="margin-top:10px">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">02</span> Listes</div>
    </div>
    <div class="panel-body">
        <dl class="lab-lexicon" style="display:grid;gap:10px">
            <div><dt>Alias</dt><dd><?= $listPreview($payload['aliasPool'] ?? []) ?></dd></div>
            <div><dt>Contacts</dt><dd><?= $listPreview($payload['contactPool'] ?? []) ?></dd></div>
            <div><dt>Messages</dt><dd><?= $listPreview($payload['smsTemplates'] ?? []) ?></dd></div>
            <div><dt>Documents</dt><dd><?= $listPreview($payload['documentTemplates'] ?? []) ?></dd></div>
            <div><dt>Mots de code</dt><dd><?= $listPreview($payload['codewords'] ?? []) ?></dd></div>
        </dl>
    </div>
</section>

<section class="panel" style="margin-top:10px">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">03</span> Utilisation en mission</div>
    </div>
    <div class="panel-body">
        <ol class="lab-start__steps">
            <li>
                <strong>1. Télécharger le script</strong>
                <span>Fichier à coller dans l’init de mission ou à exécuter via Zeus / debug console.</span>
            </li>
            <li>
                <strong>2. Appliquer sur une unité</strong>
                <span>Le modèle génère identité, téléphone, documents et biométrie selon vos options.</span>
            </li>
            <li>
                <strong>3. Partager le fichier d’échange</strong>
                <span>Pour transmettre le modèle à un autre concepteur ou l’archiver hors session.</span>
            </li>
        </ol>
        <label for="sqf_snippet" style="display:block;margin:14px 0 6px"><strong>Script prêt à coller</strong></label>
        <textarea id="sqf_snippet" rows="10" readonly style="width:100%;font-family:ui-monospace,Consolas,monospace;font-size:12px"><?= $h($sqfSnippet) ?></textarea>
        <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn" href="<?= $h(url('atak/sse/dev/modeles/' . $id . '/export?format=sqf')) ?>">Télécharger le script</a>
            <a class="btn btn--ghost" href="<?= $h(url('atak/sse/dev/modeles/' . $id . '/export')) ?>">Télécharger le fichier d’échange</a>
        </div>
    </div>
</section>

<?php if ($canManage): ?>
<section class="panel" style="margin-top:10px">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">04</span> Administration</div>
    </div>
    <div class="panel-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <a class="btn" href="<?= $h(url('atak/sse/dev/modeles/' . $id . '/modifier')) ?>">Modifier</a>
        <form method="post" action="<?= $h(url('atak/sse/dev/modeles/' . $id . '/supprimer')) ?>" onsubmit="return confirm('Archiver ce modèle ?');">
            <?= \App\Core\Csrf::field() ?>
            <button class="btn btn--ghost" type="submit">Archiver</button>
        </form>
    </div>
</section>
<?php endif; ?>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
