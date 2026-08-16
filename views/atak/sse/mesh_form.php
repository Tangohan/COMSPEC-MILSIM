<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $cases */
/** @var array<string,string> $classifications */
/** @var int $seedCaseId */
$seedCaseId = (int) ($seedCaseId ?? 0);
?>
<div class="breadcrumb">
    Athena / SSE /
    <a class="link" href="<?= $h(url('atak/sse/toiles')) ?>">Investigations</a> /
    <strong>Nouvelle</strong>
</div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Création // Investigation</div>
        <h1>Ouvrir une investigation</h1>
        <p>Nommez l’investigation, rattachez éventuellement un dossier, puis peupler le graphe d’entités et de liens.</p>
    </div>
</div>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">07.02</span> Ouverture d’investigation</div>
    </div>
    <div class="panel-body">
        <form method="post" action="<?= $h(url('atak/sse/toiles')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <label for="title">Intitulé</label>
            <input id="title" name="title" type="text" required maxlength="200" placeholder="Ex. Réseau site Alpha — phase 1">

            <label for="classification">Niveau de diffusion</label>
            <select id="classification" name="classification">
                <?php foreach ($classifications as $k => $lab): ?>
                    <option value="<?= $h($k) ?>" <?= $k === 'confidentiel' ? 'selected' : '' ?>><?= $h($lab) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="case_id">Dossier d’affaire lié (optionnel)</label>
            <select id="case_id" name="case_id">
                <option value="">Aucun</option>
                <?php foreach ($cases as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= $seedCaseId === (int) $c['id'] ? 'selected' : '' ?>>
                        <?= $h(($c['reference_code'] ?? '') . ' — ' . ($c['title'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label class="sse-check">
                <input type="checkbox" name="seed_from_case" value="1" <?= $seedCaseId > 0 ? 'checked' : '' ?>>
                Importer les entités et liens déjà connus du dossier (propositions de rapprochement)
            </label>

            <label for="summary">Objet de l’investigation</label>
            <textarea id="summary" name="summary" rows="5" maxlength="5000" placeholder="Hypothèse, périmètre, questions à trancher…"></textarea>

            <button class="btn" type="submit">Ouvrir l’investigation</button>
        </form>
    </div>
</section>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
