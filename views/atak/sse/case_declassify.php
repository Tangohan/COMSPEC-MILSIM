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

$caseId = (int) ($case['id'] ?? 0);
$base = url('atak/sse/dossiers/' . $caseId . '/declassification');

// Champs caviardables proposés à la main, par nature de fiche. Libellés métier :
// l'analyste désigne « Nom d'usage », pas une colonne.
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

?>
<div class="breadcrumb">
    Athena / SSE /
    <a class="link" href="<?= $h(url('atak/sse/dossiers')) ?>">Dossiers</a> /
    <a class="link" href="<?= $h(url('atak/sse/dossiers/' . $caseId)) ?>"><?= $h($case['reference_code'] ?? '') ?></a> /
    <strong>Déclassification</strong>
</div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Diffusion // Version expurgée</div>
        <h1>Déclassification et caviardage</h1>
        <p>
            Produisez la version du dossier diffusable à un niveau donné. Tout ce qui
            est au-dessus du niveau choisi part au noir automatiquement. Vous pouvez
            en plus noircir à la main une zone précise, quel que soit le niveau.
        </p>
    </div>
    <div class="page-reference">
        <strong><?= $h($case['reference_code'] ?? '') ?></strong>
        <?= $h($case['title'] ?? '') ?>
    </div>
</div>

<div class="security-notice">
    <div class="security-notice-code">SEC-11</div>
    <div>
        <strong>Le texte noirci n’est pas envoyé au navigateur</strong>
        <span>
            La substitution est faite avant l’affichage : la chaîne d’origine ne quitte
            pas le dossier. Un trait noir posé en habillage laisserait le texte lisible
            au copier-coller et dans le code source de la page.
        </span>
    </div>
</div>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.11</span>
            Niveau de diffusion
        </div>
        <div class="panel-meta">Le plus large caviarde le plus</div>
    </div>
    <div class="panel-body">
        <div class="sse-level-picker">
            <?php foreach ($levels as $key => $label): ?>
                <a class="sse-level <?= $key === $level ? 'is-active' : '' ?>"
                   href="<?= $h($base . '?niveau=' . rawurlencode((string) $key)) ?>">
                    <span class="sse-level-rank"><?= (int) Red::levelRank((string) $key) ?></span>
                    <span class="sse-level-label"><?= $h($label) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="sse-release-summary">
            <div>
                <div class="sse-block-title">Lisible en clair</div>
                <p><?= $summary['visible'] === [] ? 'Rien — tout est caviardé à ce niveau.' : $h(implode(' · ', $summary['visible'])) ?></p>
            </div>
            <div>
                <div class="sse-block-title">Caviardé</div>
                <p><?= $summary['hidden'] === [] ? 'Rien — le document est intégral.' : $h(implode(' · ', $summary['hidden'])) ?></p>
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
                <tr>
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

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.12</span>
            Flash — version <?= $h(mb_strtolower(Red::levelLabel($level))) ?>
        </div>
    </div>
    <div class="panel-body">
        <pre class="sse-report is-redacted" id="sse-flash-red"><?= $h($flash) ?></pre>
        <button class="btn btn--ghost btn--sm" type="button" data-copy="#sse-flash-red">Copier</button>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.13</span>
            Compte rendu initial — version <?= $h(mb_strtolower(Red::levelLabel($level))) ?>
        </div>
    </div>
    <div class="panel-body">
        <pre class="sse-report is-redacted" id="sse-initial-red"><?= $h($initial) ?></pre>
        <button class="btn btn--ghost btn--sm" type="button" data-copy="#sse-initial-red">Copier</button>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.14</span>
            Personnes — aperçu expurgé
        </div>
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
                        <td><span class="record-name"><?= $h($p['display_name'] ?? '') ?></span></td>
                        <td><?= $h(($p['grid_reference'] ?? '') !== '' ? $p['grid_reference'] : '—') ?></td>
                        <td><?= $h(($p['submitter_callsign'] ?? '') !== '' ? $p['submitter_callsign'] : '—') ?></td>
                        <td class="sse-muted">
                            <?php
                            $labels = array_values(array_unique(array_map(
                                static fn (string $c): string => Red::categoryLabel($c),
                                $red
                            )));
                            echo $labels === [] ? 'aucune' : $h(implode(', ', $labels));
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ($sites !== []): ?>
<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.15</span>
            Sites — aperçu expurgé
        </div>
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
                    <td><?= $h($s['name'] ?? '') ?></td>
                    <td><?= $h(($s['grid_reference'] ?? '') !== '' ? $s['grid_reference'] : '—') ?></td>
                    <td><?= $h(($s['team_label'] ?? '') !== '' ? $s['team_label'] : '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($canManage)): ?>
<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.16</span>
            Noircir une zone à la main
        </div>
        <div class="panel-meta">Vaut à tous les niveaux</div>
    </div>
    <div class="panel-body">
        <p class="sse-note">
            Un caviardage manuel s’applique quel que soit le niveau de diffusion, y
            compris le plus restreint. Motivez-le : c’est ce motif qu’on relira pour
            décider de le lever.
        </p>

        <?php if ($people === [] && $sites === []): ?>
            <p class="muted">Rien à caviarder : le dossier ne porte encore ni personne ni site.</p>
        <?php else: ?>
            <form method="post" action="<?= $h(url('atak/sse/dossiers/' . $caseId . '/caviardage')) ?>" class="sse-relation-form">
                <?= \App\Core\Csrf::field() ?>

                <div class="field">
                    <label for="target">Fiche</label>
                    <select id="target" name="target" required>
                        <?php foreach ($people as $i => $p): ?>
                            <option value="person:<?= (int) ($p['id'] ?? 0) ?>">
                                <?= $h(sprintf('P%02d — %s', $i + 1, (string) ($p['display_name'] ?? ''))) ?>
                            </option>
                        <?php endforeach; ?>
                        <?php foreach ($sites as $s): ?>
                            <option value="site:<?= (int) ($s['id'] ?? 0) ?>">
                                <?= $h(trim(($s['reference_code'] ?? '') . ' — ' . ($s['name'] ?? ''))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="field_pair">Zone à noircir</label>
                    <select id="field_pair" name="field_pair" required>
                        <optgroup label="Sur une personne">
                            <?php foreach ($personFields as $key => [$label, $cat]): ?>
                                <option value="<?= $h($key . '|' . $cat) ?>"><?= $h($label) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Sur un site">
                            <?php foreach ($siteFields as $key => [$label, $cat]): ?>
                                <option value="<?= $h($key . '|' . $cat) ?>"><?= $h($label) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
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
<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.17</span>
            Zones noircies à la main
        </div>
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
                ?>
                <tr>
                    <td class="record-id">
                        <?= $h(($type === 'site' ? 'Site ' : 'Personne ') . (int) ($m['target_id'] ?? 0)) ?>
                    </td>
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
// Copie sans dépendance : le portail ne charge aucune bibliothèque front.
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
</script>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
