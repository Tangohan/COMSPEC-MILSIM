<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $case */
?>
<div class="breadcrumb">
    Athena / SSE /
    <a class="link" href="<?= $h(url('atak/sse/dossiers')) ?>">Dossiers</a> /
    <strong><?= $h($case['reference_code'] ?? '') ?></strong>
</div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Dossier // Verrouillage</div>
        <h1>Code du dossier requis</h1>
        <p>
            Ce dossier dispose d’un code secret d’ouverture, distinct du code d’accès au portail.
            Saisissez-le pour consulter le contenu pendant cette session.
        </p>
    </div>
    <div class="page-reference">
        <strong><?= $h($case['reference_code'] ?? '') ?></strong>
        <?= $h($case['classification_label'] ?? '') ?>
    </div>
</div>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">01.00</span> Déverrouiller</div>
    </div>
    <div class="panel-body">
        <form method="post" action="<?= $h(url('atak/sse/dossiers/' . (int) ($case['id'] ?? 0) . '/deverrouiller')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <label for="unlock_code">Code du dossier</label>
            <input id="unlock_code" name="unlock_code" type="text" required maxlength="32" autocomplete="off"
                   class="gate-code-input" autofocus placeholder="Code secret">
            <button class="btn" type="submit">Ouvrir le dossier</button>
        </form>
        <p class="sse-note" style="margin-top:1rem">
            Le code d’accès au portail ne déverrouille pas automatiquement un dossier protégé.
        </p>
    </div>
</section>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
