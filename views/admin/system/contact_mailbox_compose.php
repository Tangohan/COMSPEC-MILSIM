<?php
declare(strict_types=1);

use App\Core\Session;

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$state = (string) ($mbState ?? 'unconfigured');
$base = url('admin/system/boite-contact');
$ok = Session::getFlash('success');
$err = Session::getFlash('error');
$failedForm = Session::getFlash('mb_form');
$values = is_array($failedForm)
    ? $failedForm
    : ['to' => (string) ($mbTo ?? ''), 'cc' => '', 'subject' => '', 'body' => (string) ($mbBody ?? '')];
?>
<div class="pa">
    <div class="pa__frame">
        <header class="pa-hero">
            <h1 class="pa-hero__title">Nouveau message</h1>
            <p class="pa-hero__lead">Écrire à quelqu’un depuis l’adresse de contact du site. Le message est rangé dans un nouveau fil, et les réponses y arrivent.</p>
            <div class="pa-hero__actions">
                <a class="pa-btn pa-btn--ghost" href="<?= $h($base) ?>">Retour à la boîte</a>
            </div>
        </header>

        <div class="pa-panel">
            <?php if ($ok): ?>
                <p class="pa-flash pa-flash--ok"><?= $h((string) $ok) ?></p>
            <?php endif; ?>
            <?php if ($err): ?>
                <p class="pa-flash pa-flash--err"><?= $h((string) $err) ?></p>
            <?php endif; ?>

            <?php if ($state !== 'ready'): ?>
                <section class="pa-card">
                    <h2 class="pa-card__title">Boîte non disponible</h2>
                    <p class="pa-card__help">La boîte de contact n’est pas configurée ou sa base n’est pas prête. Ouvrez la page principale de la boîte pour le détail.</p>
                    <div class="pa-actions"><a class="pa-btn pa-btn--line" href="<?= $h($base) ?>">Boîte de contact</a></div>
                </section>
            <?php else: ?>
                <?php
                $cmBase = $base;
                $cmThreadId = 0;
                $cmReplyTo = 0;
                $cmDraftId = 0;
                $cmValues = $values;
                $cmLimits = $mbUploadLimits ?? [];
                $cmFormId = 'cm-new';
                require __DIR__ . '/_contact_mailbox_form.php';
                ?>
            <?php endif; ?>
        </div>
    </div>
</div>
