<?php
declare(strict_types=1);

use App\Services\Sse\SseRedactionService as Red;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $case */
/** @var string $level */
/** @var array<string,string> $levels */
/** @var array<string,array<string,mixed>> $categories */
/** @var array{visible: list<string>, hidden: list<string>} $summary */
/** @var list<array<string,mixed>> $people */
/** @var list<array<string,mixed>> $sites */
/** @var list<array<string,mixed>> $manual */
/** @var string $flash */
/** @var string $initial */
/** @var string $maxLevel */
/** @var string $clearanceOrigin */
/** @var bool $clearanceRefused */
/** @var string $requestedLevel */
/** @var bool $caseAboveClearance */
/** @var bool $canExport */

$caseId = (int) ($case['id'] ?? 0);
$base = url('atak/sse/dossiers/' . $caseId . '/declassification');
$caseUrl = url('atak/sse/dossiers/' . $caseId);
$pdfUrl = url('atak/sse/dossiers/' . $caseId . '/pdf?niveau=' . rawurlencode($level));
$levelLabel = Red::levelLabel($level);

$personFields = [
    'display_name' => ['Identité affichée', 'identite'],
    'last_name' => ['Nom', 'identite'],
    'first_name' => ['Prénom', 'identite'],
    'alias' => ['Alias connu', 'identite'],
    'birth_date' => ['Date de naissance', 'identite'],
    'birth_place' => ['Lieu de naissance', 'identite'],
    'nationality' => ['Nationalité déclarée', 'identite'],
    'grid_reference' => ['Lieu du contrôle', 'lieu'],
    'biometric_samples' => ['Références de relevés', 'biometrie'],
    'identity_query' => ['Référence de dossier antérieur', 'biometrie'],
    'signature' => ['Signature ATAK', 'source'],
    'submitter_callsign' => ['Opérateur ayant recueilli', 'source'],
    'created_at' => ['Heure de recueil', 'horodatage'],
];
$siteFields = [
    'name' => ['Désignation du site', 'lieu'],
    'grid_reference' => ['Référence de grille', 'lieu'],
    'rooms' => ['Désignation des pièces', 'lieu'],
    'team_label' => ['Équipe d’exploitation', 'source'],
    'submitter_callsign' => ['Opérateur ayant ouvert', 'source'],
];

$isRedactedValue = static function (mixed $v): bool {
    $s = trim((string) $v);
    return $s === '' || $s === '—' || $s === '00' || preg_match('/^█+$/u', $s) === 1 || preg_match('/^■+$/u', $s) === 1;
};
?>
<div class="breadcrumb">
    Athena / SSE /
    <a class="link" href="<?= $h(url('atak/sse/dossiers')) ?>">Dossiers</a> /
    <a class="link" href="<?= $h($caseUrl) ?>"><?= $h($case['reference_code'] ?? '') ?></a> /
    <strong>Version expurgée</strong>
</div>

<section class="sse-decl-hero" aria-labelledby="sse-decl-title">
    <div class="sse-decl-hero__main">
        <p class="sse-decl-hero__kicker">Diffusion // Version expurgée</p>
        <h1 id="sse-decl-title">Déclassification et caviardage</h1>
        <p class="sse-decl-hero__lead">
            Choisissez le niveau de diffusion : tout ce qui est au-dessus part au noir avant affichage.
            Vous pouvez aussi noircir une zone à la main, quel que soit le niveau.
        </p>
        <p class="sse-decl-hero__meta">
            <span class="badge"><?= $h($case['classification_label'] ?? '') ?></span>
            <span class="badge badge--gray"><?= $h($case['status_label'] ?? '') ?></span>
            <span class="muted"><?= $h($case['reference_code'] ?? '') ?> — <?= $h($case['title'] ?? '') ?></span>
        </p>
    </div>
    <div class="sse-decl-hero__actions">
        <a class="btn btn--ghost" href="<?= $h($caseUrl) ?>">Retour au dossier</a>
        <a class="btn btn--ghost" href="<?= $h($caseUrl . '/compte-rendu') ?>">Compte rendu</a>
        <?php if (!empty($canExport)): ?>
            <a class="btn" href="<?= $h($pdfUrl) ?>">PDF — version <?= $h(mb_strtolower($levelLabel)) ?></a>
        <?php endif; ?>
    </div>
</section>

<div class="sse-decl-clearance <?= !empty($clearanceRefused) || !empty($caseAboveClearance) ? 'is-alert' : '' ?>">
    <strong>Habilitation de lecture : <?= $h(Red::levelLabel($maxLevel)) ?></strong>
    <span><?= $h($clearanceOrigin) ?>. Les niveaux au-dessus sont refusés — le paramètre de l’adresse exprime une demande, il n’accorde rien.</span>
    <?php if (!empty($clearanceRefused)): ?>
        <span class="sse-decl-clearance__warn">
            Demande « <?= $h(Red::levelLabel($requestedLevel)) ?> » rabattue en « <?= $h(Red::levelLabel($maxLevel)) ?> ».
        </span>
    <?php endif; ?>
    <?php if (!empty($caseAboveClearance)): ?>
        <span class="sse-decl-clearance__warn">
            Ce dossier est tenu au-dessus de votre habilitation : les catégories trop élevées restent noircies.
        </span>
    <?php endif; ?>
    <span class="sse-decl-clearance__note">Le texte noirci n’est pas envoyé au navigateur : la substitution est faite avant affichage.</span>
</div>

<section class="panel sse-decl-panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">01.11</span> Niveau de diffusion</div>
        <div class="panel-meta">Le plus large caviarde le plus</div>
    </div>
    <div class="panel-body">
        <div class="sse-level-picker sse-level-picker--scale" role="list">
            <?php foreach ($levels as $key => $label): ?>
                <?php $allowed = Red::levelRank((string) $key) <= Red::levelRank($maxLevel); ?>
                <?php if ($allowed): ?>
                    <a class="sse-level sse-level--<?= $h((string) $key) ?> <?= $key === $level ? 'is-active' : '' ?>"
                       role="listitem"
                       href="<?= $h($base . '?niveau=' . rawurlencode((string) $key)) ?>">
                        <span class="sse-level-rank"><?= (int) Red::levelRank((string) $key) ?></span>
                        <span class="sse-level-label"><?= $h($label) ?></span>
                    </a>
                <?php else: ?>
                    <span class="sse-level sse-level--<?= $h((string) $key) ?> is-locked" title="Au-dessus de votre habilitation de lecture.">
                        <span class="sse-level-rank"><?= (int) Red::levelRank((string) $key) ?></span>
                        <span class="sse-level-label"><?= $h($label) ?></span>
                        <span class="sse-level-lock">Non habilité</span>
                    </span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <div class="sse-release-summary sse-release-summary--cards">
            <div class="sse-release-card sse-release-card--clear">
                <div class="sse-block-title">Lisible en clair</div>
                <?php if ($summary['visible'] === []): ?>
                    <p>Rien — tout est caviardé à ce niveau.</p>
                <?php else: ?>
                    <ul class="sse-decl-chips">
                        <?php foreach ($summary['visible'] as $lab): ?>
                            <li><?= $h($lab) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="sse-release-card sse-release-card--black">
                <div class="sse-block-title">Caviardé</div>
                <?php if ($summary['hidden'] === []): ?>
                    <p>Rien — le document est intégral.</p>
                <?php else: ?>
                    <ul class="sse-decl-chips">
                        <?php foreach ($summary['hidden'] as $lab): ?>
                            <li><?= $h($lab) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <table class="sse-category-table">
            <thead>
            <tr>
                <th>Catégorie</th>
                <th>Lisible à partir de</th>
                <th>Ce qu’elle couvre</th>
                <th>À ce niveau</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($categories as $key => $meta): ?>
                <?php $visible = Red::visibleAt((string) $key, $level); ?>
                <tr class="<?= $visible ? '' : 'is-blacked' ?>">
                    <td><strong><?= $h($meta['label']) ?></strong></td>
                    <td><?= $h(Red::levelLabel((string) $meta['level'])) ?></td>
                    <td class="sse-muted"><?= $h($meta['help']) ?></td>
                    <td>
                        <span class="badge <?= $visible ? 'is-confirmed' : 'is-conflicting' ?>">
                            <?= $visible ? 'En clair' : 'Au noir' ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php
$renderReport = static function (string $title, string $body, string $domId) use ($h, $levelLabel, $level): void {
    ?>
    <section class="panel sse-decl-panel">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">01.12</span> <?= $h($title) ?> — <?= $h(mb_strtolower($levelLabel)) ?></div>
            <div class="panel-meta">
                <button class="btn btn--ghost btn--sm" type="button" data-copy="#<?= $h($domId) ?>">Copier</button>
            </div>
        </div>
        <div class="panel-body">
            <div class="sse-decl-paper sse-decl-paper--<?= $h($level) ?>" aria-label="<?= $h($title) ?>">
                <div class="sse-decl-paper__banner">
                    <span>Version de diffusion</span>
                    <strong><?= $h(mb_strtoupper($levelLabel, 'UTF-8')) ?></strong>
                    <span class="sse-decl-paper__stamp">Version expurgée</span>
                </div>
                <pre class="sse-decl-paper__body" id="<?= $h($domId) ?>"><?= $h($body) ?></pre>
            </div>
        </div>
    </section>
    <?php
};
$renderReport('Flash', $flash, 'sse-flash-red');
$renderReport('Compte rendu initial', $initial, 'sse-initial-red');
?>

<section class="panel sse-decl-panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">01.14</span> Personnes — aperçu expurgé</div>
        <div class="panel-meta"><?= count($people) ?> fiche<?= count($people) > 1 ? 's' : '' ?></div>
    </div>
    <?php if ($people === []): ?>
        <div class="panel-body"><p class="muted">Aucune personne rattachée au dossier.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Réf.</th>
                    <th>Identité</th>
                    <th>Lieu du contrôle</th>
                    <th>Recueilli par</th>
                    <th>Zones noircies</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($people as $i => $p): ?>
                    <?php $red = is_array($p['_redacted'] ?? null) ? $p['_redacted'] : []; ?>
                    <tr>
                        <td class="record-id"><?= $h(sprintf('P%02d', $i + 1)) ?></td>
                        <td>
                            <?php if ($isRedactedValue($p['display_name'] ?? '')): ?>
                                <span class="sse-decl-bar" title="Zone caviardée"></span>
                            <?php else: ?>
                                <span class="record-name"><?= $h($p['display_name'] ?? '') ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isRedactedValue($p['grid_reference'] ?? '—')): ?>
                                <span class="sse-decl-bar" title="Zone caviardée"></span>
                            <?php else: ?>
                                <?= $h($p['grid_reference']) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isRedactedValue($p['submitter_callsign'] ?? '—')): ?>
                                <span class="sse-decl-bar" title="Zone caviardée"></span>
                            <?php else: ?>
                                <?= $h($p['submitter_callsign']) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $labels = array_values(array_unique(array_map(
                                static fn (string $c): string => Red::categoryLabel($c),
                                $red
                            )));
                            ?>
                            <?php if ($labels === []): ?>
                                <span class="muted">aucune</span>
                            <?php else: ?>
                                <ul class="sse-decl-chips sse-decl-chips--inline">
                                    <?php foreach ($labels as $lab): ?>
                                        <li><?= $h($lab) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ($sites !== []): ?>
<section class="panel sse-decl-panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">01.15</span> Sites — aperçu expurgé</div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Référence</th>
                <th>Désignation</th>
                <th>Grille</th>
                <th>Équipe</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($sites as $s): ?>
                <tr>
                    <td class="record-id"><?= $h($s['reference_code'] ?? '') ?></td>
                    <td>
                        <?php if ($isRedactedValue($s['name'] ?? '')): ?>
                            <span class="sse-decl-bar"></span>
                        <?php else: ?>
                            <?= $h($s['name'] ?? '') ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($isRedactedValue($s['grid_reference'] ?? '—')): ?>
                            <span class="sse-decl-bar"></span>
                        <?php else: ?>
                            <?= $h($s['grid_reference']) ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($isRedactedValue($s['team_label'] ?? '—')): ?>
                            <span class="sse-decl-bar"></span>
                        <?php else: ?>
                            <?= $h($s['team_label']) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($canManage)): ?>
<section class="panel sse-decl-panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">01.16</span> Noircir une zone à la main</div>
        <div class="panel-meta">Vaut à tous les niveaux</div>
    </div>
    <div class="panel-body">
        <p class="sse-note">
            Un caviardage manuel s’applique quel que soit le niveau de diffusion.
            Motivez-le : c’est ce motif qu’on relira pour décider de le lever.
        </p>
        <?php if ($people === [] && $sites === []): ?>
            <p class="muted">Rien à caviarder : le dossier ne porte encore ni personne ni site.</p>
        <?php else: ?>
            <form method="post" action="<?= $h(url('atak/sse/dossiers/' . $caseId . '/caviardage')) ?>" class="sse-relation-form sse-decl-manual" id="sse-decl-manual">
                <?= \App\Core\Csrf::field() ?>
                <div class="sse-decl-manual__grid">
                    <div class="field">
                        <label for="target">Fiche</label>
                        <select id="target" name="target" required>
                            <?php foreach ($people as $i => $p): ?>
                                <option value="person:<?= (int) ($p['id'] ?? 0) ?>" data-kind="person">
                                    <?= $h(sprintf('P%02d — %s', $i + 1, (string) ($p['display_name'] ?? ''))) ?>
                                </option>
                            <?php endforeach; ?>
                            <?php foreach ($sites as $s): ?>
                                <option value="site:<?= (int) ($s['id'] ?? 0) ?>" data-kind="site">
                                    <?= $h(trim(($s['reference_code'] ?? '') . ' — ' . ($s['name'] ?? ''))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="field_pair">Zone à noircir</label>
                        <select id="field_pair" name="field_pair" required>
                            <optgroup label="Sur une personne" data-kind="person">
                                <?php foreach ($personFields as $key => [$label, $cat]): ?>
                                    <option value="<?= $h($key . '|' . $cat) ?>"><?= $h($label) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Sur un site" data-kind="site">
                                <?php foreach ($siteFields as $key => [$label, $cat]): ?>
                                    <option value="<?= $h($key . '|' . $cat) ?>"><?= $h($label) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                </div>
                <div class="field field--wide">
                    <label for="reason">Motif</label>
                    <input type="text" id="reason" name="reason" maxlength="255" required
                           placeholder="Protection de source, mineur, tiers non impliqué…">
                </div>
                <button class="btn" type="submit">Noircir cette zone</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php if ($manual !== []): ?>
<section class="panel sse-decl-panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">01.17</span> Zones noircies à la main</div>
        <div class="panel-meta"><?= count($manual) ?></div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Fiche</th>
                <th>Zone</th>
                <th>Catégorie</th>
                <th>Motif</th>
                <th>Posé par</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($manual as $m): ?>
                <?php
                $type = (string) ($m['target_type'] ?? 'person');
                $fkey = (string) ($m['field'] ?? '');
                $known = $type === 'site' ? $siteFields : $personFields;
                $targetId = (int) ($m['target_id'] ?? 0);
                $ficheLabel = $type === 'site' ? 'Site ' . $targetId : 'Personne ' . $targetId;
                if ($type === 'person') {
                    foreach ($people as $i => $p) {
                        if ((int) ($p['id'] ?? 0) === $targetId) {
                            $ficheLabel = sprintf('P%02d', $i + 1);
                            break;
                        }
                    }
                } else {
                    foreach ($sites as $s) {
                        if ((int) ($s['id'] ?? 0) === $targetId) {
                            $ficheLabel = (string) ($s['reference_code'] ?? ('Site ' . $targetId));
                            break;
                        }
                    }
                }
                ?>
                <tr>
                    <td class="record-id"><?= $h($ficheLabel) ?></td>
                    <td><?= $h($known[$fkey][0] ?? $fkey) ?></td>
                    <td><span class="badge"><?= $h(Red::categoryLabel((string) ($m['category'] ?? ''))) ?></span></td>
                    <td class="sse-muted"><?= $h($m['reason'] ?? '—') ?></td>
                    <td class="record-id"><?= $h($m['author_label'] ?? '—') ?></td>
                    <td>
                        <form method="post"
                              action="<?= $h(url('atak/sse/dossiers/' . $caseId . '/caviardage/' . (int) ($m['id'] ?? 0) . '/supprimer')) ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <button class="btn btn--ghost btn--sm" type="submit">Lever</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>
<?php endif; ?>

<script>
document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var el = document.querySelector(btn.getAttribute('data-copy'));
        if (!el) { return; }
        var done = function () {
            var old = btn.textContent;
            btn.textContent = 'Copié';
            setTimeout(function () { btn.textContent = old; }, 1600);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(el.textContent).then(done);
            return;
        }
        var ta = document.createElement('textarea');
        ta.value = el.textContent;
        ta.setAttribute('readonly', '');
        ta.style.position = 'absolute';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(); } catch (e) { /* ignoré */ }
        document.body.removeChild(ta);
    });
});
(function () {
    var form = document.getElementById('sse-decl-manual');
    if (!form) return;
    var target = document.getElementById('target');
    var field = document.getElementById('field_pair');
    function sync() {
        var opt = target.options[target.selectedIndex];
        var kind = opt ? (opt.getAttribute('data-kind') || 'person') : 'person';
        Array.prototype.forEach.call(field.querySelectorAll('optgroup'), function (g) {
            var show = g.getAttribute('data-kind') === kind;
            g.disabled = !show;
            g.hidden = !show;
        });
        var first = field.querySelector('optgroup:not([hidden]) option');
        if (first) field.value = first.value;
    }
    target.addEventListener('change', sync);
    sync();
})();
</script>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
