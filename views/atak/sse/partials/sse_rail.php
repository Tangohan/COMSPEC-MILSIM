<?php
declare(strict_types=1);
/**
 * Rail latéral SSE — session, arborescence dossiers, création, historique.
 * @var list<array{node:array<string,mixed>,children:list<array<string,mixed>>}> $sseFolderTree
 * @var list<array<string,mixed>> $sseRecentCases
 * @var list<array<string,mixed>> $sseFolderParents
 * @var bool $canManage
 * @var bool $isGuest
 * @var int $clearanceUntil
 * @var string $guestLabel
 * @var string $activeNav
 * @var array{total:int,active:int,archive:int}|null $indexCounts
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$sseFolderTree = is_array($sseFolderTree ?? null) ? $sseFolderTree : [];
$sseRecentCases = is_array($sseRecentCases ?? null) ? $sseRecentCases : [];
$sseFolderParents = is_array($sseFolderParents ?? null) ? $sseFolderParents : [];
$canManage = (bool) ($canManage ?? false);
$isGuest = (bool) ($isGuest ?? false);
$clearanceUntil = (int) ($clearanceUntil ?? 0);
$guestLabel = (string) ($guestLabel ?? '');
$activeNav = (string) ($activeNav ?? 'dossiers');
$indexCounts = is_array($indexCounts ?? null) ? $indexCounts : ['total' => 0, 'active' => 0, 'archive' => 0];
$nowTs = time();
$sessionRemainingLabel = '';
if ($clearanceUntil > $nowTs) {
    $remainSec = $clearanceUntil - $nowTs;
    $remainH = intdiv($remainSec, 3600);
    $remainM = intdiv($remainSec % 3600, 60);
    $sessionRemainingLabel = $remainH > 0
        ? sprintf('%dh %02d', $remainH, $remainM)
        : sprintf('%d min', max(1, $remainM));
} elseif ($clearanceUntil > 0) {
    $sessionRemainingLabel = 'expirée';
}
$sessionKindLabel = $isGuest ? 'Session invitée' : 'Session authentifiée';
$navClass = static function (string $id) use ($activeNav): string {
    return 'sse-rail-link' . ($id === $activeNav ? ' is-active' : '');
};
?>
<aside class="sse-rail" aria-label="Navigation SSE">
    <div class="sse-rail-compact"><span>Bureau SSE</span></div>
    <div class="sse-rail-panel">
        <div class="sse-rail-hero">
            <div>
                <p class="sse-rail-kicker">Pilotage renseignement</p>
                <p class="sse-rail-label">Dossiers suivis</p>
            </div>
            <strong class="sse-rail-count"><?= (int) ($indexCounts['total'] ?? 0) ?></strong>
        </div>
        <div class="sse-rail-meter" aria-hidden="true">
            <?php
            $tot = max(1, (int) ($indexCounts['total'] ?? 0));
            $act = (int) ($indexCounts['active'] ?? 0);
            ?>
            <i style="width:<?= (int) round(($act / $tot) * 100) ?>%"></i>
        </div>
        <p class="sse-rail-meter-caption"><?= $act ?> en exploitation</p>

        <p class="sse-rail-section">Session</p>
        <div class="sse-rail-session">
            <div><span>Type</span><strong><?= $h($sessionKindLabel) ?></strong></div>
            <?php if ($isGuest && $guestLabel !== ''): ?>
                <div><span>Indicatif</span><strong><?= $h($guestLabel) ?></strong></div>
            <?php endif; ?>
            <div><span>Durée restante</span><strong><?= $h($sessionRemainingLabel !== '' ? $sessionRemainingLabel : '—') ?></strong></div>
            <a class="sse-rail-quit" href="<?= $h(url('atak/sse/quitter')) ?>">Quitter la session</a>
        </div>

        <p class="sse-rail-section">Sections</p>
        <nav class="sse-rail-nav" aria-label="Sections du portail">
            <a href="<?= $h(url('atak/sse/dossiers')) ?>" class="<?= $h($navClass('dossiers')) ?>"><b>01</b><span>Dossiers<em>Registre et arborescence</em></span></a>
            <a href="<?= $h(url('atak/sse/personnes')) ?>" class="<?= $h($navClass('personnes')) ?>"><b>02</b><span>Personnes<em>Index des fiches</em></span></a>
            <a href="<?= $h(url('atak/sse/interet')) ?>" class="<?= $h($navClass('interet')) ?>"><b>03</b><span>Dossiers d’intérêt<em>Signalements terrain</em></span></a>
            <a href="<?= $h(url('atak/sse/sites')) ?>" class="<?= $h($navClass('sites')) ?>"><b>04</b><span>Sites<em>Exploitation terrain</em></span></a>
            <a href="<?= $h(url('atak/sse/exploitation-numerique')) ?>" class="<?= $h($navClass('labnum')) ?>"><b>05</b><span>Exploitation numérique<em>Laboratoire / supports</em></span></a>
            <a href="<?= $h(url('atak/sse/dev')) ?>" class="<?= $h($navClass('dev')) ?>"><b>09</b><span>Atelier de préparation<em>Modèles mission Arma</em></span></a>
            <a href="<?= $h(url('atak/sse/croisements')) ?>" class="<?= $h($navClass('croisements')) ?>"><b>06</b><span>Croisements<em>Rapprochements</em></span></a>
            <a href="<?= $h(url('atak/sse/toiles')) ?>" class="<?= $h($navClass('toiles')) ?>"><b>07</b><span>Toiles de données<em>Data mesh / graphes</em></span></a>
            <?php if (!empty($canGrant)): ?>
                <a href="<?= $h(url('atak/sse/acces')) ?>" class="<?= $h($navClass('acces')) ?>"><b>08</b><span>Codes d’accès<em>Habilitation temporaire</em></span></a>
            <?php endif; ?>
        </nav>

        <p class="sse-rail-section">Arborescence</p>
        <div class="sse-rail-tree" role="tree">
            <?php if ($sseFolderTree === []): ?>
                <p class="sse-rail-empty">Aucun dossier pour le moment.</p>
            <?php else: ?>
                <?php foreach ($sseFolderTree as $branch):
                    $node = $branch['node'];
                    $children = $branch['children'];
                    $nid = (int) ($node['id'] ?? 0);
                    $locked = !empty($node['has_unlock_code']);
                    $isFolder = !empty($node['is_folder']);
                    ?>
                    <details class="sse-tree-folder" <?= $children !== [] || $isFolder ? 'open' : '' ?>>
                        <summary>
                            <span class="sse-tree-ico" aria-hidden="true"><?= $isFolder ? '▣' : '▸' ?></span>
                            <a href="<?= $h(url('atak/sse/dossiers/' . $nid)) ?>"><?= $h($node['title'] ?? '') ?></a>
                            <?php if ($locked): ?><i class="sse-tree-lock" title="Protégé par mot de passe">∎</i><?php endif; ?>
                            <b><?= count($children) ?></b>
                        </summary>
                        <?php if ($children !== []): ?>
                            <ul>
                                <?php foreach ($children as $child):
                                    $cid = (int) ($child['id'] ?? 0);
                                    ?>
                                    <li>
                                        <a href="<?= $h(url('atak/sse/dossiers/' . $cid)) ?>">
                                            <?= $h($child['title'] ?? '') ?>
                                            <?php if (!empty($child['has_unlock_code'])): ?><i class="sse-tree-lock" title="Protégé">∎</i><?php endif; ?>
                                        </a>
                                        <span class="sse-tree-ref"><?= $h($child['reference_code'] ?? '') ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </details>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($canManage): ?>
            <p class="sse-rail-section">Créer</p>
            <form class="sse-rail-create" method="post" action="<?= $h(url('atak/sse/dossiers/dossier')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <label for="sse-folder-title">Nom du dossier</label>
                <input id="sse-folder-title" name="title" type="text" required maxlength="200" placeholder="Ex. Théâtre Nord">
                <label for="sse-folder-parent">Sous-dossier de</label>
                <select id="sse-folder-parent" name="parent_id">
                    <option value="">Racine (aucun parent)</option>
                    <?php foreach ($sseFolderParents as $fp): ?>
                        <option value="<?= (int) $fp['id'] ?>"><?= $h($fp['title'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="sse-folder-pass">Mot de passe (optionnel)</label>
                <input id="sse-folder-pass" name="unlock_code" type="password" maxlength="32" autocomplete="new-password" placeholder="Protège l’ouverture">
                <button class="btn" type="submit">+ Créer le dossier</button>
            </form>
            <a class="btn btn--ghost sse-rail-create-case" href="<?= $h(url('atak/sse/dossiers/nouveau')) ?>">+ Ouvrir une affaire</a>
        <?php endif; ?>

        <p class="sse-rail-section">Historique de session</p>
        <div class="sse-rail-history">
            <?php if ($sseRecentCases === []): ?>
                <p class="sse-rail-empty">Aucune consultation récente.</p>
            <?php else: ?>
                <?php foreach ($sseRecentCases as $rc): ?>
                    <a href="<?= $h(url('atak/sse/dossiers/' . (int) ($rc['id'] ?? 0))) ?>">
                        <strong><?= $h($rc['title'] ?? '') ?></strong>
                        <span><?= $h($rc['reference_code'] ?? '') ?> · <?= $h($rc['at'] ?? '') ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</aside>
