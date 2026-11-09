<?php
declare(strict_types=1);

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/**
 * Bureau SSE › Papiers d'un membre : la vue partielle de l'onglet « Identité & papiers » de la fiche membre, seule
 * (aucune autre donnée de la fiche).
 *
 * @var array<string, mixed> $memberPapers MemberIdentityPapersController::sectionFor(..., true)
 * @var string $memberName
 * @var ?string $memberFileUrl lien vers la fiche membre (droit Effectifs seulement)
 * @var string $csrfToken
 */
require __DIR__ . '/_subnav.php';
require __DIR__ . '/_member_css.php';
?>
<div class="breadcrumb">Athena / SSE / <a class="link" href="<?= $h(url('atak/sse/papiers')) ?>">Identités &amp; documents</a> / <a class="link" href="<?= $h(url('atak/sse/papiers/membres')) ?>">Papiers des membres</a> / <strong><?= $h($memberName) ?></strong></div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Registre // Papiers d’un membre</div>
        <h1><?= $h($memberName) ?></h1>
        <?php if (!empty($memberFileUrl)): ?>
            <p><a class="link" href="<?= $h($memberFileUrl) ?>">Ouvrir la fiche membre</a></p>
        <?php endif; ?>
    </div>
    <div class="page-reference"><strong>Vue // Membre</strong> Réf. ATH-SSE-IDENT-MBR</div>
</div>

<?php if (!empty($coverPanel)): ?>
    <?php
    $coverSelf = false;
    $coverPostUrl = url('atak/sse/papiers/membres/' . (int) $memberId . '/couverture');
    $coverDownloadBase = $coverPostUrl;
    $coverCsrf = $csrfToken;
    require dirname(__DIR__, 3) . '/partials/sse_cover_export.php';
    ?>
<?php endif; ?>
<?php require dirname(__DIR__, 3) . '/admin/effectifs_workspace/partials/member_papers.php'; ?>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
