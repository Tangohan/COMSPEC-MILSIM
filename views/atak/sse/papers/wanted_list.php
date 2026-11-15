<?php
declare(strict_types=1);
/**
 * Liste des avis de recherche de la communauté (jeu de rôle) : filtres, états, notice PDF, accès aux détections et aux
 * réglages de la notice.
 *
 * @var list<array<string,mixed>> $notices
 * @var array<string,string> $filters
 * @var array{active: int, lifted: int, expired: int, captured: int} $counts
 * @var array<string, array{0:string,1:string}> $wantedTypes
 * @var array<int,string> $authors
 */
use App\Services\Sse\SseWantedAccess;
use App\Support\SseWantedCatalog as W;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$lang = (string) ($lang ?? 'fr') === 'en' ? 'en' : 'fr';
$rights = ($rights ?? null) instanceof SseWantedAccess ? $rights : null;
$canPdf = $rights !== null && $rights->canGeneratePdf();
$canManage = $rights !== null && $rights->canManage();
$wantedConfig = is_array($wantedConfig ?? null) ? $wantedConfig : [];
$wantedTypes = is_array($wantedTypes ?? null) ? $wantedTypes : W::types();
$authors = is_array($authors ?? null) ? $authors : [];
$noticeExtras = is_array($noticeExtras ?? null) ? $noticeExtras : [];
$attachCounts = is_array($attachCounts ?? null) ? $attachCounts : [];
$codeChoices = is_array($codeChoices ?? null) ? $codeChoices : [];
$fmt = static function (?string $v) use ($lang): string {
    $v = trim((string) $v);
    $ts = $v !== '' ? strtotime($v . ' UTC') : false;

    return $ts === false ? '—' : gmdate($lang === 'en' ? 'Y-m-d H:i' : 'd/m/Y H:i', $ts);
};
$tone = static fn (string $level): string => match ($level) {
    'critical', 'high' => 'badge--red',
    'medium' => 'badge--amber',
    default => 'badge--gray',
};
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-papers.css')) . '"><link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-identity-dossier.css')) . '"><link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-wanted-codes.css')) . '">';
$noticeMeta = is_array($noticeMeta ?? null) ? $noticeMeta : [];
$pendingCount = (int) ($pendingCount ?? 0);
$statusTabs = ['active' => 'Actifs', 'a_valider' => 'À valider', 'lifted' => 'Levés', 'captured' => 'Capturés', 'expired' => 'Expirés', '' => 'Tous'];
$counts['a_valider'] = $pendingCount;
?>
<div class="page-heading">
    <div>
        <h1>Avis de recherche</h1>
        <p>Avis posés depuis les fiches des identités fictives. Un seul avis actif par identité ; chaque apparition d’une identité sous avis crée une détection.</p>
    </div>
    <div class="page-reference">
        <strong><?= (int) $counts['active'] ?> actif(s)</strong>
        <div><a class="link" href="<?= $h(url('atak/sse/avis/detections')) ?>">Détections</a><?php if ((int) ($newDetections ?? 0) > 0): ?> <span class="badge badge--red"><?= (int) $newDetections ?> nouvelle(s)</span><?php endif; ?></div>
        <?php if ($canManage): ?><div><a class="link" href="<?= $h(url('atak/sse/avis/reglages')) ?>">Réglages de la notice</a></div><?php endif; ?>
    </div>
</div>

<?php if (empty($wantedReady)): ?>
    <div class="dos-note dos-note--info">Les avis de recherche seront disponibles dès que l’étape de base de données « avis de recherche » aura été passée par l’équipe technique.</div>
<?php else: ?>
    <nav class="dos-filters" aria-label="Filtrer par statut">
        <?php foreach ($statusTabs as $key => $label): ?>
            <a href="<?= $h(url('atak/sse/avis') . '?statut=' . $key) ?>" <?= $filters['status'] === (string) $key ? 'aria-current="true"' : '' ?>><?= $h($label) ?><?= $key !== '' ? ' (' . (int) ($counts[$key] ?? 0) . ')' : '' ?></a>
        <?php endforeach; ?>
    </nav>
    <form class="dos-filters-bar" method="get" action="<?= $h(url('atak/sse/avis')) ?>">
        <input type="hidden" name="statut" value="<?= $h($filters['status']) ?>">
        <div><label for="f-q">Recherche</label><input id="f-q" type="search" name="q" value="<?= $h($filters['q']) ?>" placeholder="Nom, référence, code ou mot"></div>
        <div><label for="f-code">Code</label>
            <input id="f-code" class="wc-filter-code" type="text" name="code" list="f-code-list" value="<?= $h($filters['code'] ?? '') ?>" placeholder="M-TER-01, A-OBS" autocomplete="off" translate="no">
            <datalist id="f-code-list">
                <?php foreach ($codeChoices as $kindList): foreach ($kindList as $code => $c): ?><option value="<?= $h($code) ?>"><?= $h($lang === 'en' ? $c['en'] : $c['fr']) ?></option><?php endforeach; endforeach; ?>
            </datalist></div>
        <div><label for="f-type">Type</label>
            <select id="f-type" name="type"><option value="">Tous</option>
                <?php foreach ($wantedTypes as $k => $labels): ?><option value="<?= $h($k) ?>" <?= $filters['type'] === $k ? 'selected' : '' ?>><?= $h($lang === 'en' ? $labels[1] : $labels[0]) ?></option><?php endforeach; ?>
            </select></div>
        <div><label for="f-threat">Menace</label>
            <select id="f-threat" name="menace"><option value="">Toutes</option>
                <?php foreach (W::THREATS as $k => $labels): ?><option value="<?= $h($k) ?>" <?= $filters['threat'] === $k ? 'selected' : '' ?>><?= $h($lang === 'en' ? $labels[1] : $labels[0]) ?></option><?php endforeach; ?>
            </select></div>
        <button class="btn" type="submit">Filtrer</button>
    </form>

    <section class="panel">
        <?php if ($notices === [] && $filters['status'] === 'a_valider'): ?>
            <div class="empty-state"><div class="empty-state-inner"><strong>Rien à valider</strong><p>Aucun avis n’attend de décision. Quand le circuit de validation est actif, les avis proposés par un analyste arrivent ici.</p></div></div>
        <?php elseif ($notices === []): ?>
            <div class="empty-state"><div class="empty-state-inner"><strong>Aucun avis</strong><p>Aucun avis ne correspond à ces filtres. Un avis se place depuis la fiche d’une identité fictive.</p><p><a class="btn" href="<?= $h(url('atak/sse/papiers/identites')) ?>">Ouvrir les identités fictives</a></p></div></div>
        <?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><th>Référence</th><th>Identité</th><th>Type</th><th>Menace</th><th>Statut</th><th>Émis le</th><th>Expire le</th><th>Auteur</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($notices as $n): ?>
                    <tr>
                        <td data-label="Référence" translate="no"><strong><?= $h($n['ref']) ?></strong></td>
                        <td data-label="Identité" translate="no"><a class="link" href="<?= $h(url('atak/sse/papiers/identites/' . (int) $n['identity_id'] . '#avis')) ?>"><?= $h($n['identity_name'] !== '' ? $n['identity_name'] : 'Identité #' . (int) $n['identity_id']) ?></a></td>
                        <td data-label="Type"><?= $h(W::typeLabel((string) $n['notice_type'], $wantedConfig, $lang)) ?>
                            <?php $cx = $noticeExtras[(int) $n['id']]['codes'] ?? ['reasons' => [], 'measures' => []]; $cl = array_merge($cx['reasons'], $cx['measures']); ?>
                            <?php if ($cl !== [] || !empty($attachCounts[(int) $n['id']])): ?>
                                <div class="wc-listcodes">
                                    <?php foreach (array_slice($cl, 0, 6) as $c): ?><a class="wc-codelink" translate="no" href="<?= $h(url('atak/sse/avis') . '?statut=' . rawurlencode((string) $filters['status']) . '&code=' . rawurlencode((string) $c['code'])) ?>" title="<?= $h($lang === 'en' ? $c['en'] : $c['fr']) ?>"><b><?= $h($c['code']) ?></b></a><?php endforeach; ?>
                                    <?php if (count($cl) > 6): ?><span class="wc-mini">+<?= count($cl) - 6 ?></span><?php endif; ?>
                                    <?php if (!empty($attachCounts[(int) $n['id']])): ?><span class="wc-mini"><?= (int) $attachCounts[(int) $n['id']] ?> image(s)</span><?php endif; ?>
                                </div>
                            <?php endif; ?></td>
                        <td data-label="Menace"><span class="badge <?= $h($tone((string) $n['threat_level'])) ?>"><?= $h(W::label(W::THREATS, (string) $n['threat_level'], $lang)) ?></span><?= $n['armed'] === 'yes' ? ' <span class="badge badge--red">' . $h(W::label(W::ARMED, 'yes', $lang)) . '</span>' : '' ?></td>
                        <td data-label="Statut"><span class="dos-status"><?= $h(W::label(W::STATUSES, (string) $n['status'], $lang)) ?></span><?php if (($noticeMeta[(int) $n['id']]['state'] ?? 'live') === 'pending'): ?> <span class="badge badge--amber">À valider</span><?php endif; ?></td>
                        <td data-label="Émis le"><?= $h($fmt($n['issued_at'])) ?></td>
                        <td data-label="Expire le"><?= $h($n['expires_at'] !== null ? $fmt($n['expires_at']) : '—') ?></td>
                        <td data-label="Auteur" translate="no"><?= $h($authors[(int) $n['id']] ?? '—') ?><?php $svcAc = (string) ($noticeMeta[(int) $n['id']]['service']['ac'] ?? ''); if ($svcAc !== ''): ?><div class="wc-mini" title="<?= $h($noticeMeta[(int) $n['id']]['service']['fr'] ?? '') ?>"><?= $h($svcAc) ?></div><?php endif; ?></td>
                        <td>
                            <a class="btn-open" href="<?= $h(url('atak/sse/papiers/identites/' . (int) $n['identity_id'] . '#avis')) ?>">Ouvrir</a>
                            <?php if ($canPdf): ?>
                                <a class="btn-open" href="<?= $h(url('atak/sse/avis/' . (int) $n['id'] . '/pdf')) ?>">PDF</a>
                                <a class="btn-open" href="<?= $h(url('atak/sse/avis/' . (int) $n['id'] . '/pdf?mode=impression')) ?>" target="_blank" rel="noopener">Imprimer</a>
                            <?php endif; ?>
                        </td>
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
