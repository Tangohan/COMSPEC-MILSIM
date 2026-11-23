<?php
declare(strict_types=1);
ob_start();

use App\Support\SseCreationBrief;
use App\Support\SseWizardUi;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,string> $classifications */
$folderParents = is_array($folderParents ?? null) ? $folderParents : [];
$parentId = (int) ($parentId ?? 0);
$maxLevel = (string) ($maxLevel ?? 'interne');
$classDefault = (string) ($classDefault ?? 'encadrement');
$stepTitles = ['Type et urgence', 'Contenu', 'Rattachements', 'Classification et accès', 'Vérifier et créer'];
$wzCancelUrl = url('atak/sse/dossiers');
$wzSubmits = [
    ['label' => 'Créer le dossier', 'when' => 'case_type=affaire'],
    ['label' => 'Créer le classeur', 'when' => 'case_type=classeur'],
];
$sseExtraStyles = '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse_wizard.css')) . '">';
?>

<div class="wz">
    <header class="wz-intro">
        <h1>Nouveau dossier</h1>
        <p>Ouvrez un dossier d’affaire, ou un classeur pour en ranger plusieurs. Le parcours tient en cinq étapes courtes ; votre saisie est gardée si vous quittez la page.</p>
    </header>

    <form method="post" action="<?= $h(url('atak/sse/dossiers')) ?>" id="sse-case-create" data-sse-wizard data-wz-key="case" novalidate>
        <?= \App\Core\Csrf::field() ?>
        <?= SseWizardUi::progress($stepTitles) ?>
        <?php require __DIR__ . '/partials/wizard_banners.php'; ?>

        <section class="wz-step" data-wz-step aria-labelledby="wz-case-t1">
            <h2 class="wz-step__title" id="wz-case-t1" data-wz-step-title>Type et urgence</h2>
            <p class="wz-step__lead">Dites de quoi il s’agit et à quelle vitesse il faut agir.</p>
            <div class="wz-alert" data-wz-alert role="alert" hidden>Un choix est nécessaire avant de continuer.</div>
            <?= SseWizardUi::cards('case_type', 'Type de dossier', SseCreationBrief::CASE_TYPES, 'affaire', [
                'required' => true,
                'help' => 'Un dossier d’affaire contient le travail d’instruction. Un classeur ne contient que d’autres dossiers : il sert à les ranger.',
                'error' => 'Choisissez le type de dossier à créer.',
            ]) ?>
            <?= SseWizardUi::cards('theme', 'Thème', SseCreationBrief::CASE_THEMES, 'autre', [
                'required' => true,
                'compact' => true,
                'when' => 'case_type=affaire',
                'hint' => 'Le thème sert à retrouver le dossier ; il est inscrit en tête de la synthèse.',
                'error' => 'Choisissez le thème le plus proche.',
            ]) ?>
            <?= SseWizardUi::cards('urgency', 'Urgence', SseCreationBrief::CASE_URGENCY, 'routine', [
                'required' => true,
                'compact' => true,
                'when' => 'case_type=affaire',
                'error' => 'Indiquez l’urgence du dossier.',
            ]) ?>
        </section>

        <section class="wz-step" data-wz-step aria-labelledby="wz-case-t2">
            <h2 class="wz-step__title" id="wz-case-t2" data-wz-step-title>Contenu</h2>
            <p class="wz-step__lead">Un intitulé clair suffit pour démarrer ; la synthèse peut rester courte.</p>
            <div class="wz-alert" data-wz-alert role="alert" hidden>Corrigez le champ signalé avant de continuer.</div>
            <div class="wz-field" data-wz-field="title" data-wz-rules="required min:3">
                <label for="title">Intitulé <span class="wz-flag wz-flag--req">obligatoire</span></label>
                <p class="wz-hint" id="title-hint">Le nom qui apparaîtra dans la liste des dossiers.</p>
                <input id="title" name="title" type="text" maxlength="200" autocomplete="off"
                       placeholder="Ex. Exploitation site Alpha" aria-describedby="title-hint">
                <?= SseWizardUi::errors('title', [
                    'required' => 'Indiquez un intitulé pour le dossier.',
                    'min' => 'L’intitulé est trop court : écrivez au moins 3 caractères.',
                ]) ?>
            </div>
            <div class="wz-field" data-wz-field="summary">
                <label for="summary">Synthèse initiale <span class="wz-flag">facultatif</span></label>
                <p class="wz-hint" id="summary-hint">Le contexte en quelques lignes : ce qui est connu, ce qui est attendu.</p>
                <textarea id="summary" name="summary" rows="5" maxlength="5000" aria-describedby="summary-hint"></textarea>
            </div>
        </section>

        <section class="wz-step" data-wz-step aria-labelledby="wz-case-t3">
            <h2 class="wz-step__title" id="wz-case-t3" data-wz-step-title>Rattachements</h2>
            <p class="wz-step__lead">Rangez le dossier dans un classeur, ou laissez-le à la racine.</p>
            <div class="wz-alert" data-wz-alert role="alert" hidden>Corrigez le champ signalé avant de continuer.</div>
            <?php if ($folderParents !== []): ?>
                <div class="wz-field" data-wz-field="parent_id">
                    <label for="parent_id">Placer dans un classeur <span class="wz-flag">facultatif</span></label>
                    <p class="wz-hint" id="parent_id-hint">Le dossier reprend par défaut le niveau de diffusion du classeur choisi.</p>
                    <select id="parent_id" name="parent_id" data-wz-follow aria-describedby="parent_id-hint"<?= $parentId > 0 ? ' data-wz-seed' : '' ?>>
                        <option value="">Racine</option>
                        <?php foreach ($folderParents as $fp): ?>
                            <option value="<?= (int) $fp['id'] ?>" data-class="<?= $h($fp['classification'] ?? '') ?>"<?= $parentId === (int) $fp['id'] ? ' selected' : '' ?>><?= $h($fp['title'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <div class="wz-empty">
                    Aucun classeur n’existe encore dans votre périmètre : le dossier sera créé à la racine. Créez d’abord un classeur (type « Classeur », étape 1) si vous voulez ranger vos dossiers.
                </div>
                <?php if ($parentId > 0): ?>
                    <input type="hidden" name="parent_id" value="<?= $parentId ?>">
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <section class="wz-step" data-wz-step aria-labelledby="wz-case-t4">
            <h2 class="wz-step__title" id="wz-case-t4" data-wz-step-title>Classification et accès</h2>
            <p class="wz-step__lead">Le niveau de diffusion décide qui pourra ouvrir le dossier, dans le périmètre de votre communauté.</p>
            <div class="wz-alert" data-wz-alert role="alert" hidden>Un choix est nécessaire avant de continuer.</div>
            <?php
            $classLegend = 'Niveau de diffusion';
            require __DIR__ . '/partials/wizard_classification.php';
            ?>
            <div class="wz-field" data-wz-field="unlock_code">
                <label for="unlock_code">Code secret du dossier <span class="wz-flag">facultatif</span></label>
                <details class="wz-help"><summary>À quoi sert ce code ?</summary>
                    <p>Il ajoute une seconde barrière : il faudra le saisir pour ouvrir le dossier. Il est distinct du code d’accès au portail. Pour votre sécurité, il n’est jamais gardé dans le brouillon du navigateur.</p>
                </details>
                <input id="unlock_code" name="unlock_code" type="text" maxlength="32" autocomplete="off" data-wz-nodraft
                       placeholder="Distinct du code d’accès au portail">
            </div>
            <p class="wz-hint">La référence (type SSE-<?= $h(date('Y')) ?>-XXXX) est attribuée automatiquement à la création.</p>
        </section>

        <section class="wz-step" data-wz-step aria-labelledby="wz-case-t5">
            <h2 class="wz-step__title" id="wz-case-t5" data-wz-step-title>Vérifier et créer</h2>
            <p class="wz-step__lead">Relisez avant de créer. Chaque ligne se corrige en un clic.</p>
            <dl class="wz-sum">
                <div class="wz-sum__row" data-wz-from="case_type"><dt>Type</dt><dd data-wz-val></dd><button type="button" class="wz-linkbtn" data-wz-goto="1">Modifier</button></div>
                <div class="wz-sum__row" data-wz-from="theme" data-wz-row-when="case_type=affaire"><dt>Thème</dt><dd data-wz-val></dd><button type="button" class="wz-linkbtn" data-wz-goto="1">Modifier</button></div>
                <div class="wz-sum__row" data-wz-from="urgency" data-wz-row-when="case_type=affaire"><dt>Urgence</dt><dd data-wz-val></dd><button type="button" class="wz-linkbtn" data-wz-goto="1">Modifier</button></div>
                <div class="wz-sum__row" data-wz-from="title"><dt>Intitulé</dt><dd data-wz-val></dd><button type="button" class="wz-linkbtn" data-wz-goto="2">Modifier</button></div>
                <div class="wz-sum__row" data-wz-from="summary"><dt>Synthèse</dt><dd data-wz-val></dd><button type="button" class="wz-linkbtn" data-wz-goto="2">Modifier</button></div>
                <?php if ($folderParents !== []): ?>
                    <div class="wz-sum__row" data-wz-from="parent_id"><dt>Classeur</dt><dd data-wz-val></dd><button type="button" class="wz-linkbtn" data-wz-goto="3">Modifier</button></div>
                <?php endif; ?>
                <div class="wz-sum__row" data-wz-from="classification"><dt>Niveau de diffusion</dt><dd data-wz-val></dd><button type="button" class="wz-linkbtn" data-wz-goto="4">Modifier</button></div>
                <div class="wz-sum__row" data-wz-from="unlock_code"><dt>Code secret</dt><dd data-wz-val data-wz-mask></dd><button type="button" class="wz-linkbtn" data-wz-goto="4">Modifier</button></div>
            </dl>
        </section>

        <?php require __DIR__ . '/partials/wizard_nav.php'; ?>
    </form>
</div>
<script src="<?= $h(asset_url('assets/js/sse-wizard.js')) ?>"></script>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
