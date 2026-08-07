<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $case */
?>
<div class="breadcrumb">
    Athena / SSE /
    <a class="link" href="<?= $h(url('atak/sse/dossiers')) ?>">Dossiers</a> /
    <strong>Déverrouillage</strong>
</div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Protection // Mot de passe</div>
        <h1><?= $h($case['title'] ?? 'Dossier') ?></h1>
        <p>
            Ce dossier est protégé. Saisissez le mot de passe fourni par le détenteur
            pour l’ouvrir dans cette session.
        </p>
    </div>
    <div class="page-reference">
        <strong><?= $h($case['reference_code'] ?? '') ?></strong>
        Protégé
    </div>
</div>

<section class="panel sse-unlock-panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">00</span> Déverrouiller</div>
    </div>
    <div class="panel-body">
        <form method="post" action="<?= $h(url('atak/sse/dossiers/' . (int) $case['id'] . '/deverrouiller')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <label for="unlock_code">Mot de passe du dossier</label>
            <input id="unlock_code" name="unlock_code" type="password" required maxlength="32" autocomplete="off" autofocus class="gate-code-input">
            <button class="btn" type="submit">Ouvrir</button>
        </form>
    </div>
</section>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
