<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,string> $classifications */
?>
<div class="breadcrumb">
    Athena / SSE / Dossiers /
    <strong>Nouveau</strong>
</div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Ouverture // Création</div>
        <h1>Ouvrir un dossier</h1>
        <p>Création d’un nouveau dossier d’affaire dans le périmètre SSE.</p>
    </div>
    <div class="page-reference">
        <strong>Vue // Nouveau dossier</strong>
        Réf. ATH-SSE-NOUVEAU
    </div>
</div>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.02</span>
            Fiche d’ouverture
        </div>
    </div>
    <div class="panel-body">
        <form method="post" action="<?= $h(url('atak/sse/dossiers')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <label for="title">Intitulé</label>
            <input id="title" name="title" type="text" required maxlength="200" placeholder="Ex. Exploitation site Alpha">

            <label for="classification">Classification</label>
            <select id="classification" name="classification">
                <?php foreach ($classifications as $k => $lab): ?>
                    <option value="<?= $h($k) ?>" <?= $k === 'encadrement' ? 'selected' : '' ?>><?= $h($lab) ?></option>
                <?php endforeach; ?>
            </select>

            <?php
            $folderParents = is_array($folderParents ?? null) ? $folderParents : [];
            $parentId = (int) ($parentId ?? 0);
            ?>
            <?php if ($folderParents !== []): ?>
                <label for="parent_id">Placer dans un dossier</label>
                <select id="parent_id" name="parent_id">
                    <option value="">Racine</option>
                    <?php foreach ($folderParents as $fp): ?>
                        <option value="<?= (int) $fp['id'] ?>" <?= $parentId === (int) $fp['id'] ? 'selected' : '' ?>><?= $h($fp['title'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            <?php elseif ($parentId > 0): ?>
                <input type="hidden" name="parent_id" value="<?= $parentId ?>">
            <?php endif; ?>

            <label for="summary">Synthèse initiale</label>
            <textarea id="summary" name="summary" maxlength="5000"></textarea>

            <label for="unlock_code">Code secret du dossier (optionnel)</label>
            <input id="unlock_code" name="unlock_code" type="text" maxlength="32" autocomplete="off"
                   placeholder="Distinct du code d’accès au portail">
            <p class="muted">Référence automatique du type SSE-<?= date('Y') ?>-XXXX à la création.</p>

            <div class="toolbar-actions" style="margin-top:1rem">
                <button class="btn" type="submit">Créer le dossier</button>
                <a class="btn btn--ghost" href="<?= $h(url('atak/sse/dossiers')) ?>">Annuler</a>
            </div>
        </form>
    </div>
</section>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
