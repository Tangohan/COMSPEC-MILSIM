<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array{treatments: list<array<string,mixed>>, recent: list<array<string,mixed>>, provenance: bool, queue: array<string,int>, detections: list<array<string,mixed>>} $cerbere */
$cerbere = is_array($cerbere ?? null) ? $cerbere : ['treatments' => [], 'recent' => [], 'provenance' => false, 'queue' => [], 'detections' => []];
$when = static function (?string $d): string {
    $t = $d !== null && $d !== '' ? strtotime($d) : false;

    return $t !== false ? date('d/m/Y H:i', $t) : '—';
};
/** « notice:1 » devient « Avis de recherche 1 » : le type en clair, jamais le code technique. */
$what = static function (string $ref) use ($h): string {
    [$t, $i] = explode(':', $ref, 2) + ['', ''];

    return $h(\App\Support\SseLinkCatalog::typeLabel($t) . ' ' . $i);
};
$detectionTypes = ['id_check' => 'Contrôle d’identité', 'seek' => 'Fiche SEEK II', 'file_query' => 'Passage aux fichiers', 'photo' => 'Photo versée', 'link' => 'Lien', 'phone' => 'Téléphone', 'cross_match' => 'Croisement'];
?>
<div class="page-heading">
    <div>
        <h1>Cerbères</h1>
        <p>Tout ce que le système fait tout seul dans le bureau : relier, classer, corréler, détecter, ouvrir un dossier. Rien ici ne se règle : c’est la liste de ce qui tourne et de ce qu’il a fait.</p>
    </div>
    <a class="btn btn--ghost" href="<?= $h(url('atak/sse/rapprochements')) ?>">Propositions de Cerbère</a>
</div>

<section class="panel" aria-labelledby="cb-widget">
    <div class="panel-header"><div class="panel-title" id="cb-widget">Bouton Cerbère</div></div>
    <div class="panel-body">
        <p class="muted">Le bouton rond en bas à droite de chaque page ouvre Cerbère : ce qu’il a fait et ce qui attend votre décision sur la page où vous êtes.</p>
        <p><button type="button" class="btn btn--ghost" data-cw-restore>Afficher le bouton Cerbère</button> <span class="muted" data-cw-restored hidden>Le bouton est de retour sur vos pages.</span></p>
    </div>
</section>

<?php if (!$cerbere['provenance']): ?>
    <div class="sse-note" role="note">La trace du « pourquoi » de chaque lien n’est pas encore activée sur ce site (étape « sse_entity_links »). Les traitements tournent déjà ; leurs liens seront expliqués dès que l’étape sera passée.</div>
<?php endif; ?>

<section class="panel" aria-labelledby="cb-treatments">
    <div class="panel-header"><div class="panel-title" id="cb-treatments">Les traitements</div></div>
    <div class="panel-body">
        <p class="muted">Un nom par métier : chaque lien posé par un traitement porte son nom et le code de la règle, dans le widget « Liaisons » de chaque fiche.</p>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Traitement</th><th>Ce qu’il fait</th><th>État</th><th>Liens posés</th><th>Dernier</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($cerbere['treatments'] as $t): ?>
                        <tr>
                            <td data-label="Traitement"><strong><?= $h($t['label']) ?></strong></td>
                            <td data-label="Ce qu’il fait"><?= $h($t['does']) ?>
                                <?php if (!empty($t['rules'])): ?>
                                    <ul class="muted" style="margin:6px 0 0;padding-left:18px">
                                        <?php foreach ($t['rules'] as $r): ?>
                                            <li><?= $h($r['label']) ?> — <code><?= $h($r['code']) ?></code> : <?= (int) $r['n'] ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </td>
                            <td data-label="État">
                                <?php if ($t['active']): ?>
                                    <span class="badge badge--green">Actif</span>
                                <?php else: ?>
                                    <span class="badge badge--amber">À activer</span>
                                    <?php if ($t['why'] !== ''): ?><div class="muted"><?= $h($t['why']) ?></div><?php endif; ?>
                                <?php endif; ?>
                                <?php if ($t['key'] === 'validation' && !empty($t['extra']['ready'])): ?>
                                    <div class="muted"><?= (int) $t['extra']['pending'] ?> à examiner · <?= (int) $t['extra']['accepted'] ?> acceptée(s) · <?= (int) $t['extra']['rejected'] ?> rejetée(s)</div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Liens posés"><?= $cerbere['provenance'] ? (int) $t['total'] : '—' ?></td>
                            <td data-label="Dernier"><?= $h($when($t['last'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="panel" aria-labelledby="cb-recent">
    <div class="panel-header"><div class="panel-title" id="cb-recent">Derniers événements</div></div>
    <div class="panel-body">
        <p class="muted">Les derniers liens posés par un traitement et les dernières détections d’avis de recherche. Lecture seule.</p>
        <?php if ($cerbere['recent'] === [] && $cerbere['detections'] === []): ?>
            <div class="empty-state"><div class="empty-state-inner"><strong>Rien à signaler</strong><p>Aucun traitement n’a encore laissé de trace sur ce site.</p></div></div>
        <?php else: ?>
            <ul class="sse-timeline-list" style="list-style:none;margin:0;padding:0">
                <?php foreach ($cerbere['recent'] as $e): ?>
                    <li style="padding:8px 0;border-bottom:1px solid var(--ui-line)">
                        <strong><?= $h($e['treatment']) ?></strong> <span class="muted">(<?= $h($e['rule_label']) ?> · <code><?= $h($e['rule']) ?></code>)</span><br>
                        <?= $h($e['text']) ?> <span class="muted"><?= $what((string) $e['from']) ?> → <?= $what((string) $e['to']) ?> · <?= $h($when($e['at'])) ?></span>
                    </li>
                <?php endforeach; ?>
                <?php foreach ($cerbere['detections'] as $d): ?>
                    <li style="padding:8px 0;border-bottom:1px solid var(--ui-line)">
                        <strong>Cerbère-Détection</strong> <span class="muted">(avis de recherche)</span><br>
                        <?= $h($detectionTypes[$d['type']] ?? $d['type']) ?><?= (int) $d['repeat'] > 1 ? ' ×' . (int) $d['repeat'] : '' ?> <span class="muted"><?= $h($d['status']) ?> · <?= $h($when($d['at'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>

<section class="panel" aria-labelledby="cb-names">
    <div class="panel-header"><div class="panel-title" id="cb-names">Anciens noms</div></div>
    <div class="panel-body">
        <p class="muted">Les noms ont changé à l’affichage seulement : les codes, colonnes, adresses et droits sont les mêmes qu’avant.</p>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Avant</th><th>Maintenant</th></tr></thead>
                <tbody>
                    <?php foreach (\App\Support\SseCerbere::LEGACY_LABELS as $old => $new): ?>
                        <tr><td data-label="Avant"><?= $h($old) ?></td><td data-label="Maintenant"><?= $h($new) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
