<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $device */
/** @var list<array<string,mixed>> $acquisitions */
/** @var array<string,int> $contentCounts */
require __DIR__ . '/_subnav.php';
$id = (int) ($device['id'] ?? 0);
$byCategory = static fn (array $rows, array $cats): array => array_values(array_filter($rows, static fn (array $a): bool => in_array((string) ($a['category'] ?? ''), $cats, true)));
$systemArtifacts = $byCategory($artifacts, ['system']);
$docs = $byCategory($artifacts, ['document']);
$deleted = array_values(array_filter($artifacts, static fn (array $a): bool => !empty($a['is_deleted']) || ($a['category'] ?? '') === 'deleted'));
$browsing = $byCategory($artifacts, ['navigation']);
$mails = $byCategory($artifacts, ['email']);
$encrypted = array_values(array_filter($artifacts, static fn (array $a): bool => !empty($a['is_encrypted']) || ($a['category'] ?? '') === 'encrypted'));
$acquisitions = is_array($acquisitions ?? null) ? $acquisitions : [];
$reserves = '';
foreach ($acquisitions as $acq) {
    if (trim((string) ($acq['reserves'] ?? '')) !== '') {
        $reserves = (string) $acq['reserves'];
        break;
    }
}
$contentTotal = array_sum(array_map('intval', is_array($contentCounts ?? null) ? $contentCounts : []));
$howTo = 'Pour remplir cette rubrique : en jeu, exploitez l’ordinateur à fond (outil d’exploitation numérique du SSE, extraction complète), puis transmettez-le avec le terminal SEEK ou l’app SSE de l’ATAK. Le contenu relevé arrive ici ; rien n’est inventé.';
$empty = static fn (string $what): string => '<p class="muted">' . htmlspecialchars($what, ENT_QUOTES, 'UTF-8') . '</p>';
?>
<div class="page-heading">
    <div>
        <h1><?= $h($device['reference_code'] ?? '') ?></h1>
        <p>Utilisateurs, documents, réseaux, historiques et chronologie système.</p>
    </div>
</div>
<?php if ($contentTotal === 0): ?>
<section class="panel" id="computer-empty"><div class="panel-body">
    <strong>Aucun contenu relevé sur ce support</strong>
    <p class="muted"><?= $h($reserves !== '' ? $reserves : $howTo) ?></p>
    <?php if ($reserves !== ''): ?><p class="muted"><?= $h($howTo) ?></p><?php endif; ?>
</div></section>
<?php elseif ($reserves !== ''): ?>
<section class="panel"><div class="panel-body"><p class="muted"><?= $h($reserves) ?></p></div></section>
<?php endif; ?>
<div class="iw-tower-grid">
    <section class="panel">
        <div class="panel-header"><div class="panel-title">Utilisateurs locaux / système</div></div>
        <div class="panel-body"><?php if ($systemArtifacts === []): ?><?= $empty('Aucun utilisateur relevé.') ?><?php else: ?><ul><?php foreach ($systemArtifacts as $a): ?><li><?= $h($a['name'] ?? '') ?></li><?php endforeach; ?></ul><?php endif; ?></div>
    </section>
    <section class="panel">
        <div class="panel-header"><div class="panel-title">Comptes & applications</div></div>
        <div class="panel-body">
            <?php if ($accounts === [] && $applications === []): ?><?= $empty('Aucun compte ni application relevés.') ?><?php else: ?>
            <ul><?php foreach ($accounts as $a): ?><li><?= $h(($a['service_label'] ?? '') . ' — ' . ($a['username'] ?? '')) ?></li><?php endforeach; ?></ul>
            <ul><?php foreach ($applications as $a): ?><li><?= $h($a['app_name'] ?? '') ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </div>
    </section>
</div>
<section class="panel" style="margin-top:10px">
    <div class="panel-header"><div class="panel-title">Documents</div></div>
    <?php if ($docs === []): ?><div class="panel-body"><?= $empty('Aucun document relevé.') ?></div><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Nom</th><th>Chemin</th><th>Intérêt</th></tr></thead><tbody>
    <?php foreach ($docs as $a): ?><tr><td><?= $h($a['name'] ?? '') ?></td><td><?= $h($a['path'] ?? '') ?></td><td><?= $h($a['interest_level_label'] ?? '') ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<section class="panel" style="margin-top:10px">
    <div class="panel-header"><div class="panel-title">Navigation et courriels</div></div>
    <div class="panel-body">
        <?php if ($browsing === [] && $mails === []): ?><?= $empty('Aucun historique de navigation ni courriel relevé.') ?><?php else: ?>
        <ul><?php foreach ($browsing as $a): ?><li><?= $h($a['name'] ?? '') ?><?= ($a['path'] ?? '') !== '' ? ' — ' . $h($a['path']) : '' ?></li><?php endforeach; ?>
        <?php foreach ($mails as $a): ?><li><?= $h($a['name'] ?? '') ?><?= ($a['presumed_author'] ?? '') !== '' ? ' — ' . $h($a['presumed_author']) : '' ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
    </div>
</section>
<section class="panel" style="margin-top:10px">
    <div class="panel-header"><div class="panel-title">Fichiers supprimés / corbeille</div></div>
    <div class="panel-body"><?php if ($deleted === []): ?><?= $empty('Aucun fichier supprimé relevé.') ?><?php else: ?><ul><?php foreach ($deleted as $a): ?><li><?= $h($a['name'] ?? '') ?> — <?= $h($a['path'] ?? '') ?></li><?php endforeach; ?></ul><?php endif; ?></div>
</section>
<section class="panel" style="margin-top:10px">
    <div class="panel-header"><div class="panel-title">Fichiers chiffrés</div></div>
    <div class="panel-body"><?php if ($encrypted === []): ?><?= $empty('Aucun fichier chiffré relevé.') ?><?php else: ?><ul><?php foreach ($encrypted as $a): ?><li><?= $h($a['name'] ?? '') ?></li><?php endforeach; ?></ul><?php endif; ?></div>
</section>
<section class="panel" style="margin-top:10px">
    <div class="panel-header"><div class="panel-title">Réseaux connus</div></div>
    <div class="panel-body"><?php if ($networks === []): ?><?= $empty('Aucun réseau relevé.') ?><?php else: ?><ul><?php foreach ($networks as $n): ?><li><?= $h($n['ssid_or_name'] ?? '') ?></li><?php endforeach; ?></ul><?php endif; ?></div>
</section>
<section class="panel" style="margin-top:10px">
    <div class="panel-header"><div class="panel-title">Chronologie système</div></div>
    <div class="panel-body"><?php if ($timeline === []): ?><?= $empty('Aucun événement daté relevé : seuls les éléments portant une vraie date entrent dans la chronologie.') ?><?php else: ?><ul class="iw-feed"><?php foreach ($timeline as $e): ?>
        <li><time><?= $h(substr((string) ($e['event_at'] ?? ''), 0, 16)) ?></time><span><?= $h($e['title'] ?? '') ?></span></li>
    <?php endforeach; ?></ul><?php endif; ?></div>
</section>
<?php $sseContent = ob_get_clean(); require dirname(__DIR__) . '/_layout.php';
