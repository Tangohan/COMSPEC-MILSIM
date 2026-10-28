<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
use App\Services\Sse\SseCampaignService as Cmp;

/** @var array<string,mixed> $row */
/** @var array<string,mixed> $detail */
$types = Cmp::TYPES;
$stats = $detail['stats'] ?? [];
$orgs = $detail['orgs'] ?? [];
$people = $detail['people'] ?? [];
$tab = in_array($tab ?? '', ['organisations', 'personnages', 'scelles', 'journal'], true) ? $tab : 'organisations';
$knownTone = static fn (int $k): string => ['badge--gray', 'badge--amber', ''][$k] ?? 'badge--gray';
$knownLabel = static fn (int $k): string => Cmp::KNOWN[$k] ?? Cmp::KNOWN[0];
$isArchived = ($row['status'] ?? '') === 'archived';
$base = url('atak/sse/campagnes/' . (int) $row['id']);
$csrf = \App\Core\Csrf::token();

$renderOrg = static function (string $id, int $depth) use (&$renderOrg, $orgs, $h, $knownTone, $knownLabel): string {
    $o = $orgs[$id] ?? null;
    if ($o === null || $depth > 8) {
        return '';
    }
    $html = '<li class="cmp-org">'
        . '<div class="cmp-org-head">'
        . '<strong>' . $h($o['name']) . '</strong>'
        . ' <span class="cmp-sub">' . $h($o['kind_label']) . ($o['threat_label'] !== '' ? ' · menace ' . $h(mb_strtolower($o['threat_label'])) : '') . '</span>'
        . ' <span class="badge ' . $h($knownTone((int) $o['known'])) . '">' . $h($knownLabel((int) $o['known'])) . '</span>'
        . '</div>';
    if ($o['desc'] !== '') {
        $html .= '<p class="cmp-org-desc">' . $h($o['desc']) . '</p>';
    }
    if ($o['members'] !== []) {
        $html .= '<ul class="cmp-members">';
        foreach ($o['members'] as $m) {
            $html .= '<li><span class="cmp-role">' . $h($m['role_label'] ?: 'Membre') . '</span> '
                . '<strong>' . $h($m['name'] ?: $m['key']) . '</strong>'
                . ($m['alias'] !== '' ? ' <em>« ' . $h($m['alias']) . ' »</em>' : '')
                . ' <span class="badge ' . $h($knownTone((int) $m['known'])) . '">' . $h($knownLabel((int) $m['known'])) . '</span>'
                . ($m['how'] !== '' ? ' <span class="cmp-sub">' . $h($m['how']) . '</span>' : '')
                . '</li>';
        }
        $html .= '</ul>';
    }
    if ($o['children'] !== []) {
        $html .= '<ul class="cmp-orbat">';
        foreach ($o['children'] as $child) {
            $html .= $renderOrg((string) $child, $depth + 1);
        }
        $html .= '</ul>';
    }

    return $html . '</li>';
};
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse_campaigns.css')) ?>">
<div class="page-heading">
    <div>
        <div class="page-heading-overline"><a href="<?= $h(url('atak/sse/campagnes')) ?>">Campagnes</a> // <?= $h($row['campaign_key']) ?></div>
        <h1><?= $h($row['name'] ?: $row['campaign_key']) ?></h1>
        <p>
            <span class="badge <?= $row['type'] === 'temporaire' ? 'badge--amber' : ($row['type'] === 'recurrente' ? 'badge--gray' : '') ?>"><?= $h($types[$row['type']] ?? $row['type']) ?></span>
            <?php if ($isArchived): ?><span class="badge badge--gray">Archivée</span><?php endif; ?>
            <?php if (!empty($row['shared'])): ?><span class="badge">Partagée</span><?php endif; ?>
            <?= $h(Cmp::TYPE_HINTS[$row['type']] ?? '') ?>
        </p>
    </div>
    <div class="cmp-heading-actions">
        <a class="btn btn--ghost" href="<?= $h($base . '/texte') ?>">Télécharger le texte</a>
    </div>
</div>

<?php if (!empty($detail['error'])): ?>
    <div class="flash flash--error">Campagne illisible : <?= $h($detail['error']) ?></div>
<?php endif; ?>
<?php if (!empty($collisions)): ?>
    <div class="flash flash--error">
        Graine biométrique reprise par une autre campagne qui n’en est pas une copie
        (<?= $h(implode(', ', array_map(static fn (array $c): string => ($c['tenant_name'] ?? '?') . ' / ' . $c['campaign_key'], $collisions))) ?>).
        Deux personnages différents pourraient avoir la même biométrie : recréez l’une des deux campagnes en jeu.
    </div>
<?php endif; ?>
<?php if (!empty($sourceNewer)): ?>
    <div class="flash flash--ok">
        La campagne d’origine (« <?= $h($source['name'] ?? '') ?> ») a changé depuis votre copie.
        <?php if ($canManage): ?>
            <form method="post" action="<?= $h($base) ?>" class="cmp-inline">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <input type="hidden" name="do" value="pull_source">
                <button class="btn btn--sm" type="submit">Mettre ma copie à jour</button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="metrics-grid cmp-metrics">
    <div class="metric"><div class="metric-label">Missions jouées</div><div class="metric-value"><?= (int) $row['missions'] ?></div><div class="metric-detail">Sauvegarde du jeu : <?= $h($detail['updated'] ?? '') ?: '—' ?></div></div>
    <div class="metric"><div class="metric-label">Personnages</div><div class="metric-value"><?= (int) ($stats['people'] ?? 0) ?></div><div class="metric-detail"><?= (int) ($stats['recurring'] ?? 0) ?> récurrent(s), <?= (int) ($stats['known'] ?? 0) ?> connu(s) des joueurs</div></div>
    <div class="metric"><div class="metric-label">Organisations</div><div class="metric-value"><?= (int) ($stats['orgs'] ?? 0) ?></div><div class="metric-detail"><?= (int) ($stats['orgs_known'] ?? 0) ?> connue(s) des joueurs</div></div>
    <div class="metric"><div class="metric-label">Pièces sous scellé</div><div class="metric-value"><?= (int) ($stats['pieces'] ?? 0) ?></div><div class="metric-detail"><?= (int) ($stats['tampered'] ?? 0) ?> journal(aux) altéré(s)</div></div>
</div>

<div class="cmp-ids">
    <div><span>Identifiant global</span><code><?= $h($globalId) ?></code></div>
    <div><span>Graine biométrique</span><code><?= $h($detail['seed'] ?? $row['seed']) ?></code></div>
    <?php if (($detail['copy_from'] ?? '') !== ''): ?><div><span>Structure copiée de</span><code><?= $h($detail['copy_from']) ?></code></div><?php endif; ?>
    <?php if (!empty($row['shared'])): ?><div><span>Copies par d’autres communautés</span><code><?= (int) $copies ?></code></div><?php endif; ?>
</div>

<nav class="cmp-tabs">
    <?php foreach (['organisations' => 'Organisations', 'personnages' => 'Personnages', 'scelles' => 'Registre des scellés', 'journal' => 'Journal'] as $k => $label): ?>
        <a href="<?= $h($base . '?vue=' . $k) ?>" class="<?= $tab === $k ? 'is-active' : '' ?>"><?= $h($label) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($tab === 'organisations'): ?>
<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">O.01</span> ORBAT des organisations</div>
        <div class="panel-meta">Vue d’ensemble (Zeus) : tout est affiché, avec ce que les joueurs en savent</div>
    </div>
    <div class="panel-body">
        <?php if ($orgs === []): ?>
            <p class="muted">Aucune organisation dans cette campagne.</p>
        <?php else: ?>
            <ul class="cmp-orbat cmp-orbat--root">
                <?php foreach ($detail['org_roots'] as $rootId): ?>
                    <?= $renderOrg((string) $rootId, 0) ?>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
<?php elseif ($tab === 'personnages'): ?>
<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">P.01</span> Personnages</div>
        <div class="panel-meta">L’identifiant global est le même dans toutes les communautés qui jouent cette campagne</div>
    </div>
    <?php if ($people === []): ?>
        <div class="panel-body"><p class="muted">Aucun personnage enregistré.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Identité</th><th>Identifiant global</th><th>Organisation</th><th>Rôle</th><th>Connu</th><th>Dernière apparition</th><th>Rencontres bio.</th></tr></thead>
                <tbody>
                <?php foreach ($people as $p): ?>
                    <tr>
                        <td>
                            <strong><?= $h($p['name'] ?: $p['key']) ?></strong><?php if ($p['alias'] !== ''): ?> <em>« <?= $h($p['alias']) ?> »</em><?php endif; ?>
                            <div class="cmp-sub"><?= $h($p['nationality']) ?><?= $p['status'] !== '' ? ' · ' . $h($p['status']) : '' ?><?= $p['recurring'] ? ' · récurrent' : '' ?></div>
                        </td>
                        <td><code><?= $h($p['global_id']) ?></code></td>
                        <td><?= $h($orgs[$p['org']]['name'] ?? $p['org']) ?></td>
                        <td><?= $h($p['role_label']) ?></td>
                        <td><span class="badge <?= $h($knownTone((int) $p['known'])) ?>"><?= $h($knownLabel((int) $p['known'])) ?></span><?php if ($p['how'] !== ''): ?><div class="cmp-sub"><?= $h($p['how']) ?></div><?php endif; ?></td>
                        <td><?= $h($p['last_seen']) ?></td>
                        <td><?= (int) $p['encounters'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php elseif ($tab === 'scelles'): ?>
<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">S.01</span> Registre des scellés</div>
        <div class="panel-meta">Gardé seulement par les campagnes permanentes</div>
    </div>
    <div class="panel-body">
        <?php if (($detail['pieces'] ?? []) === []): ?>
            <p class="muted">Aucune pièce enregistrée.</p>
        <?php else: ?>
            <?php foreach ($detail['pieces'] as $p): ?>
                <details class="cmp-piece<?= $p['tampered'] ? ' is-tampered' : '' ?>">
                    <summary>
                        <strong><?= $h($p['id']) ?></strong>
                        <?php if ($p['seal'] !== ''): ?><code><?= $h($p['seal']) ?></code><?php endif; ?>
                        <?= $h($p['desc'] ?: $p['label']) ?>
                        <span class="badge badge--gray"><?= $h($p['state']) ?></span>
                        <?php if ($p['archived']): ?><span class="badge badge--gray">Mission précédente</span><?php endif; ?>
                        <?php if ($p['tampered']): ?><span class="badge badge--red">Journal altéré</span><?php endif; ?>
                        <?php if ($p['holder'] !== ''): ?><span class="cmp-sub">détenteur : <?= $h($p['holder']) ?></span><?php endif; ?>
                    </summary>
                    <ol class="cmp-events">
                        <?php foreach ($p['events'] as $e): ?>
                            <li><code><?= $h($e['dtg'] ?? '') ?></code> <strong><?= $h($e['action'] ?? '') ?></strong> <?= $h($e['who'] ?? '') ?><?= ($e['grid'] ?? '') !== '' ? ' · ' . $h($e['grid']) : '' ?><?= ($e['remark'] ?? '') !== '' ? ' · ' . $h($e['remark']) : '' ?></li>
                        <?php endforeach; ?>
                    </ol>
                </details>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
<?php else: ?>
<div class="grid-2">
    <section class="panel">
        <div class="panel-header"><div class="panel-title"><span class="panel-index">J.01</span> Journal de campagne (jeu)</div></div>
        <div class="panel-body">
            <?php if (($detail['log'] ?? []) === []): ?>
                <p class="muted">Pas encore de mission sauvegardée.</p>
            <?php else: ?>
                <ol class="cmp-events">
                    <?php foreach ($detail['log'] as $e): ?>
                        <li><code><?= $h($e['date']) ?></code> <strong><?= $h($e['mission']) ?></strong> (<?= $h($e['world']) ?>) · <?= $h($e['why']) ?><div class="cmp-sub"><?= $h($e['summary']) ?></div></li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>
    </section>
    <section class="panel">
        <div class="panel-header"><div class="panel-title"><span class="panel-index">J.02</span> Gestes dans Athena</div></div>
        <div class="panel-body">
            <ol class="cmp-events">
                <?php foreach ($history as $e): ?>
                    <li><code><?= $h($e['created_at']) ?></code> <strong><?= $h($e['action']) ?></strong> <?= $h($e['actor']) ?><?= $e['detail'] !== '' ? ' · ' . $h($e['detail']) : '' ?></li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>
</div>
<?php endif; ?>

<?php if ($canManage): ?>
<section class="panel" style="margin-top:14px">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">G.01</span> Gérer la campagne</div>
        <div class="panel-meta">Les changements repartent en jeu avec « Campagne : récupérer depuis Athena »</div>
    </div>
    <div class="panel-body cmp-manage">
        <form method="post" action="<?= $h($base) ?>">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
            <input type="hidden" name="do" value="edit">
            <h3>Nom et type</h3>
            <label>Nom</label>
            <input type="text" name="name" maxlength="160" value="<?= $h($row['name']) ?>">
            <label>Type</label>
            <select name="type">
                <?php foreach ($types as $k => $label): ?><option value="<?= $h($k) ?>"<?= $row['type'] === $k ? ' selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?>
            </select>
            <button class="btn" type="submit">Enregistrer</button>
        </form>

        <form method="post" action="<?= $h($base) ?>">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
            <input type="hidden" name="do" value="duplicate">
            <h3>Dupliquer pour un drill</h3>
            <p class="muted">Mêmes personnages et même biométrie, rien de découvert, aucune pièce.</p>
            <label>Nouvel identifiant</label>
            <input type="text" name="new_key" maxlength="64" required value="<?= $h(mb_substr($row['campaign_key'] . '-DRILL', 0, 64)) ?>">
            <label>Nom</label>
            <input type="text" name="new_name" maxlength="160" placeholder="<?= $h(($row['name'] ?: $row['campaign_key']) . ' (copie)') ?>">
            <label>Type</label>
            <select name="new_type">
                <option value="temporaire">Temporaire (drill SOT)</option>
                <option value="recurrente">Récurrente (bootcamp)</option>
                <option value="permanente">Permanente</option>
            </select>
            <button class="btn" type="submit">Créer la copie</button>
        </form>

        <form method="post" action="<?= $h($base) ?>">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
            <input type="hidden" name="do" value="share">
            <h3>Partager avec les autres communautés</h3>
            <label>Partage</label>
            <select name="shared">
                <option value="0"<?= empty($row['shared']) ? ' selected' : '' ?>>Non partagée</option>
                <option value="1"<?= !empty($row['shared']) ? ' selected' : '' ?>>Publiée dans le catalogue</option>
            </select>
            <label>Contenu partagé</label>
            <select name="share_scope">
                <?php foreach (Cmp::SHARE_SCOPES as $k => $label): ?><option value="<?= $h($k) ?>"<?= ($row['share_scope'] ?? 'structure') === $k ? ' selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?>
            </select>
            <label>Présentation</label>
            <textarea name="share_note" rows="3" maxlength="600" placeholder="Contexte, carte, niveau conseillé…"><?= $h($row['share_note'] ?? '') ?></textarea>
            <button class="btn" type="submit"<?= $isArchived ? ' disabled' : '' ?>>Enregistrer le partage</button>
        </form>

        <div class="cmp-danger">
            <h3>Remise à zéro, archivage, suppression</h3>
            <form method="post" action="<?= $h($base) ?>" onsubmit="return confirm('Tout ce que les joueurs ont découvert et le registre des scellés seront effacés. Continuer ?')">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <input type="hidden" name="do" value="reset">
                <button class="btn btn--ghost" type="submit">Remettre à zéro les découvertes</button>
            </form>
            <form method="post" action="<?= $h($base) ?>">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <input type="hidden" name="do" value="archive">
                <button class="btn btn--ghost" type="submit"><?= $isArchived ? 'Réactiver' : 'Archiver' ?></button>
            </form>
            <form method="post" action="<?= $h($base) ?>">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <input type="hidden" name="do" value="delete">
                <label>Identifiant à taper pour supprimer</label>
                <input type="text" name="confirm" placeholder="<?= $h($row['campaign_key']) ?>">
                <button class="btn btn--danger" type="submit">Supprimer d’Athena</button>
            </form>
        </div>
    </div>
</section>
<?php endif; ?>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
