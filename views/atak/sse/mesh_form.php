<?php
declare(strict_types=1);
ob_start();

use App\Support\SseCreationBrief;
use App\Support\SseWizardUi;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $cases */
/** @var array<string,string> $classifications */
/** @var int $seedCaseId */
$cases = is_array($cases ?? null) ? $cases : [];
$seedCaseId = (int) ($seedCaseId ?? 0);
$maxLevel = (string) ($maxLevel ?? 'interne');
$classDefault = (string) ($classDefault ?? 'confidentiel');
$hasCases = $cases !== [];

$typeOptions = [];
foreach (SseCreationBrief::MESH_TYPES as $k => $o) {
    $typeOptions[$k] = ['label' => $o['label'], 'desc' => $o['desc'], 'tpl' => $k];
}
$startOptions = [];
foreach (SseCreationBrief::MESH_STARTS as $k => $o) {
    $startOptions[$k] = [
        'label' => $o['label'],
        'desc' => $o['desc'],
        'disabled' => $k === 'dossier' && !$hasCases,
        'note' => $k === 'dossier' && !$hasCases ? 'Aucun dossier dans votre périmètre' : '',
    ];
}
$startDefault = $seedCaseId > 0 && $hasCases ? 'dossier' : 'vide';
$stepTitles = ['Type et objectif', 'Contenu', 'Rattachements', 'Classification et accès', 'Vérifier et créer'];
$wzCancelUrl = url('atak/sse/toiles');
$wzSubmits = [['label' => 'Créer la toile']];
$sseExtraStyles = '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse_wizard.css')) . '">';
?>

<div class="wz">
    <header class="wz-intro">
        <h1>Nouvelle toile</h1>
        <p>Une toile réunit des entités et leurs liens pour mener une investigation. Quelques questions suffisent pour la préparer ; vous la remplirez ensuite sur le canevas.</p>
    </header>

    <form method="post" action="<?= $h(url('atak/sse/toiles')) ?>" id="sse-mesh-create" data-sse-wizard data-wz-key="mesh" novalidate>
        <?= \App\Core\Csrf::field() ?>
        <?= SseWizardUi::progress($stepTitles) ?>
        <?php require __DIR__ . '/partials/wizard_banners.php'; ?>

        <section class="wz-step" data-wz-step aria-labelledby="wz-mesh-t1">
            <h2 class="wz-step__title" id="wz-mesh-t1" data-wz-step-title>Type et objectif</h2>
            <p class="wz-step__lead">Que voulez-vous comprendre, et avec quoi démarrez-vous ?</p>
            <div class="wz-alert" data-wz-alert role="alert" hidden>Un choix est nécessaire avant de continuer.</div>
            <?php foreach (SseCreationBrief::MESH_TYPES as $k => $o): ?>
                <div hidden data-wz-tpl-src="<?= $h($k) ?>"><?= $h($o['tpl']) ?></div>
            <?php endforeach; ?>
            <?= SseWizardUi::cards('mesh_type', 'Type de graphe', $typeOptions, 'reseau', [
                'required' => true,
                'hint' => 'Le type oriente la suite : il prépare le texte d’objectif et se retrouve dans la fiche.',
                'help' => 'Pas sûr ? Prenez « Réseau de relations » pour relier des personnes et des objets, « Chronologie » pour ordonner des faits, « Toile libre » si vous hésitez.',
                'error' => 'Choisissez le type de graphe qui correspond à votre investigation.',
            ]) ?>
            <?= SseWizardUi::cards('start', 'Point de départ', $startOptions, $startDefault, [
                'required' => true,
                'seeded' => $seedCaseId > 0,
                'error' => 'Choisissez comment démarrer la toile.',
            ]) ?>
        </section>

        <section class="wz-step" data-wz-step aria-labelledby="wz-mesh-t2">
            <h2 class="wz-step__title" id="wz-mesh-t2" data-wz-step-title>Contenu</h2>
            <p class="wz-step__lead">Donnez un nom reconnaissable à la toile et dites en deux phrases ce que vous cherchez.</p>
            <div class="wz-alert" data-wz-alert role="alert" hidden>Corrigez le champ signalé avant de continuer.</div>
            <div class="wz-field" data-wz-field="title" data-wz-rules="required min:3">
                <label for="title">Intitulé <span class="wz-flag wz-flag--req">obligatoire</span></label>
                <p class="wz-hint" id="title-hint">Un nom court qui permettra de la retrouver dans la liste des toiles.</p>
                <input id="title" name="title" type="text" maxlength="200" autocomplete="off"
                       placeholder="Ex. Réseau site Alpha — phase 1" aria-describedby="title-hint">
                <?= SseWizardUi::errors('title', [
                    'required' => 'Indiquez un intitulé pour la toile.',
                    'min' => 'L’intitulé est trop court : écrivez au moins 3 caractères.',
                ]) ?>
            </div>
            <div class="wz-field" data-wz-field="summary">
                <label for="summary">Objet de l’investigation <span class="wz-flag">facultatif</span></label>
                <p class="wz-hint" id="summary-hint">Hypothèse, périmètre, questions à trancher. Un texte d’amorce est proposé selon le type choisi.</p>
                <textarea id="summary" name="summary" rows="5" maxlength="5000" data-wz-tpl-name="mesh_type" aria-describedby="summary-hint"
                          placeholder="Hypothèse, périmètre, questions à trancher…"></textarea>
            </div>
        </section>

        <section class="wz-step" data-wz-step aria-labelledby="wz-mesh-t3">
            <h2 class="wz-step__title" id="wz-mesh-t3" data-wz-step-title>Rattachements</h2>
            <p class="wz-step__lead">Reliez la toile à un dossier d’affaire pour retrouver l’une depuis l’autre.</p>
            <div class="wz-alert" data-wz-alert role="alert" hidden>Corrigez le champ signalé avant de continuer.</div>
            <?php if (!$hasCases): ?>
                <div class="wz-empty">
                    Aucun dossier d’affaire n’est visible dans votre périmètre. La toile sera créée sans rattachement ; vous pourrez la lier plus tard.
                    <a href="<?= $h(url('atak/sse/dossiers/nouveau')) ?>">Créer un dossier d’abord</a>
                </div>
            <?php else: ?>
                <div class="wz-field" data-wz-field="case_id" data-wz-need-when="start=dossier">
                    <label for="case_id">Dossier d’affaire lié <span class="wz-flag" data-wz-when="start=vide">facultatif</span><span class="wz-flag wz-flag--req" data-wz-when="start=dossier">obligatoire</span></label>
                    <p class="wz-hint" id="case_id-hint">Seuls les dossiers de votre périmètre sont proposés.</p>
                    <select id="case_id" name="case_id" data-wz-follow aria-describedby="case_id-hint"<?= $seedCaseId > 0 ? ' data-wz-seed' : '' ?>>
                        <option value="">Aucun</option>
                        <?php foreach ($cases as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" data-class="<?= $h($c['classification'] ?? '') ?>"<?= $seedCaseId === (int) $c['id'] ? ' selected' : '' ?>>
                                <?= $h(($c['reference_code'] ?? '') . ' — ' . ($c['title'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= SseWizardUi::errors('case_id', ['required' => 'Choisissez le dossier dont vous voulez reprendre les entités et les liens.']) ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="wz-step" data-wz-step aria-labelledby="wz-mesh-t4">
            <h2 class="wz-step__title" id="wz-mesh-t4" data-wz-step-title>Classification et accès</h2>
            <p class="wz-step__lead">Le niveau de diffusion décide qui pourra ouvrir la toile. La toile reste dans le périmètre de votre communauté.</p>
            <div class="wz-alert" data-wz-alert role="alert" hidden>Un choix est nécessaire avant de continuer.</div>
            <?php
            $classLegend = 'Niveau de diffusion';
            require __DIR__ . '/partials/wizard_classification.php';
            ?>
        </section>

        <section class="wz-step" data-wz-step aria-labelledby="wz-mesh-t5">
            <h2 class="wz-step__title" id="wz-mesh-t5" data-wz-step-title>Vérifier et créer</h2>
            <p class="wz-step__lead">Relisez avant de créer. Chaque ligne se corrige en un clic.</p>
            <dl class="wz-sum">
                <div class="wz-sum__row" data-wz-from="mesh_type"><dt>Type de graphe</dt><dd data-wz-val></dd><button type="button" class="wz-linkbtn" data-wz-goto="1">Modifier</button></div>
                <div class="wz-sum__row" data-wz-from="start"><dt>Point de départ</dt><dd data-wz-val></dd><button type="button" class="wz-linkbtn" data-wz-goto="1">Modifier</button></div>
                <div class="wz-sum__row" data-wz-from="title"><dt>Intitulé</dt><dd data-wz-val></dd><button type="button" class="wz-linkbtn" data-wz-goto="2">Modifier</button></div>
                <div class="wz-sum__row" data-wz-from="summary"><dt>Objet</dt><dd data-wz-val></dd><button type="button" class="wz-linkbtn" data-wz-goto="2">Modifier</button></div>
                <?php if ($hasCases): ?>
                    <div class="wz-sum__row" data-wz-from="case_id"><dt>Dossier lié</dt><dd data-wz-val></dd><button type="button" class="wz-linkbtn" data-wz-goto="3">Modifier</button></div>
                <?php endif; ?>
                <div class="wz-sum__row" data-wz-from="classification"><dt>Niveau de diffusion</dt><dd data-wz-val></dd><button type="button" class="wz-linkbtn" data-wz-goto="4">Modifier</button></div>
            </dl>
        </section>

        <?php require __DIR__ . '/partials/wizard_nav.php'; ?>
    </form>
</div>
<script src="<?= $h(asset_url('assets/js/sse-wizard.js')) ?>"></script>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
