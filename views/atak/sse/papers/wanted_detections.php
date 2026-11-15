<?php
declare(strict_types=1);
/**
 * Liste des détections système : une identité sous avis de recherche actif est réapparue dans le jeu ou sur le site.
 * Lecture : accès au portail ; prise en compte ou rejet : gestion des fichiers SSE.
 *
 * @var list<array<string,mixed>> $detections
 * @var array{new: int, acknowledged: int, dismissed: int} $counts
 * @var string $status
 */
use App\Core\Csrf;
use App\Services\Sse\SseWantedAccess;
use App\Support\SseWantedCatalog as W;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$lang = (string) ($lang ?? 'fr') === 'en' ? 'en' : 'fr';
$canHandle = ($rights ?? null) instanceof SseWantedAccess && $rights->canManage();
$detections = is_array($detections ?? null) ? $detections : [];
$status = (string) ($status ?? 'new');
$fmt = static function (?string $v) use ($lang): string {
    $v = trim((string) $v);
    $ts = $v !== '' ? strtotime($v . ' UTC') : false;

    return $ts === false ? '—' : gmdate($lang === 'en' ? 'Y-m-d H:i' : 'd/m/Y H:i', $ts);
};
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-papers.css')) . '"><link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-identity-dossier.css')) . '">';
$tabs = ['new' => 'Nouvelles', 'acknowledged' => 'Prises en compte', 'dismissed' => 'Écartées', '' => 'Toutes'];
?>
<div class="page-heading">
    <div>
        <h1>Détections</h1>
        <p>Le système repère les identités sous avis de recherche actif quand elles réapparaissent dans le jeu ou sur le site. Une détection ne modifie jamais l’identité.</p>
    </div>
    <div class="page-reference"><strong><?= (int) $counts['new'] ?> nouvelle(s)</strong><div><a class="link" href="<?= $h(url('atak/sse/avis')) ?>">Avis de recherche</a></div></div>
</div>

<?php if (empty($wantedReady)): ?>
    <div class="dos-note dos-note--info">Les détections seront disponibles dès que l’étape de base de données « avis de recherche » aura été passée par l’équipe technique.</div>
<?php else: ?>
    <nav class="dos-filters" aria-label="Filtrer par statut">
        <?php foreach ($tabs as $key => $label): ?>
            <a href="<?= $h(url('atak/sse/avis/detections') . '?statut=' . $key) ?>" <?= $status === (string) $key ? 'aria-current="true"' : '' ?>><?= $h($label) ?><?= $key !== '' ? ' (' . (int) ($counts[$key] ?? 0) . ')' : '' ?></a>
        <?php endforeach; ?>
    </nav>
    <section class="panel">
        <?php if ($detections === []): ?>
            <div class="empty-state"><div class="empty-state-inner"><strong>Aucune détection</strong><p>Rien à signaler pour ce filtre. Les détections apparaissent quand une identité sous avis de recherche est contrôlée, scannée ou consultée.</p></div></div>
        <?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><th>Date</th><th>Identité</th><th>Réf. de l’avis</th><th>Type</th><th>Source</th><th>Lieu</th><th>Opérateur</th><th>État</th><?php if ($canHandle): ?><th></th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach ($detections as $d): ?>
                    <tr>
                        <td data-label="Date"><?= $h($fmt($d['created_at'])) ?></td>
                        <td data-label="Identité" translate="no"><a class="link" href="<?= $h(url('atak/sse/papiers/identites/' . (int) $d['identity_id'] . '#detections')) ?>"><?= $h($d['identity_name'] !== '' ? $d['identity_name'] : 'Identité #' . (int) $d['identity_id']) ?></a></td>
                        <td data-label="Avis" translate="no"><?= $h($d['notice_ref']) ?></td>
                        <td data-label="Type"><?= $h(W::label(W::DETECTION_TYPES, (string) $d['type'], $lang)) ?><?php if ((int) $d['repeat_count'] > 1): ?> <span class="muted">× <?= (int) $d['repeat_count'] ?></span><?php endif; ?></td>
                        <td data-label="Source"><?= $h(W::label(W::DETECTION_SOURCES, (string) $d['source'], $lang)) ?></td>
                        <td data-label="Lieu" translate="no"><?= $h($d['place'] ?? '—') ?></td>
                        <td data-label="Opérateur" translate="no"><?= $h($d['operator_label'] ?? '—') ?></td>
                        <td data-label="État"><span class="dos-status"><?= $h(W::label(W::DETECTION_STATUSES, (string) $d['status'], $lang)) ?></span><?php if (($d['comment'] ?? null) !== null): ?><br><small class="muted" translate="no"><?= $h($d['comment']) ?></small><?php endif; ?></td>
                        <?php if ($canHandle): ?>
                            <td>
                                <?php if ($d['status'] === 'new'): ?>
                                    <form method="post" action="<?= $h(url('atak/sse/avis/detections/' . (int) $d['id'] . '/statut')) ?>" class="idp-actions">
                                        <?= Csrf::field() ?>
                                        <input type="text" name="comment" maxlength="500" placeholder="Commentaire (facultatif)" aria-label="Commentaire sur la détection">
                                        <button class="btn" type="submit" name="status" value="acknowledged">Prendre en compte</button>
                                        <button class="btn btn--ghost" type="submit" name="status" value="dismissed">Écarter</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
