<?php
declare(strict_types=1);

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** Bureau SSE › Papiers d'un membre › création ou modification d'un papier : même formulaire que la fiche membre. */
require __DIR__ . '/_subnav.php';
require __DIR__ . '/_member_css.php';
?>
<?php require dirname(__DIR__, 3) . '/admin/effectifs_workspace/member_paper_form.php'; ?>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
