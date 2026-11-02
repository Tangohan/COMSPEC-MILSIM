<?php
declare(strict_types=1);

/**
 * Fiche d'un émetteur de qualifications : chiffres, qualifications rattachées ou délivrées, derniers brevets,
 * habilités (instructeurs, signataires), modification guidée et archivage.
 *
 * @var array<string, mixed> $issuer
 * @var list<array<string, mixed>> $issuers
 * @var array{total: int, recent: int, definitions: int, last_at: string} $stat
 * @var array<int, array<string, mixed>> $linkedDefinitions
 * @var list<array<string, mixed>> $authorizations
 * @var list<array<string, mixed>> $recentAwards
 * @var list<array<string, mixed>> $definitions
 * @var list<array{id: int, name: string}> $units
 * @var list<array{id: int, label: string}> $members
 * @var list<array<string, mixed>> $jobs
 */
require_once base_path('views/admin/effectifs_workspace/partials/effk.php');

$issuer = is_array($issuer ?? null) ? $issuer : [];
$issuers = is_array($issuers ?? null) ? $issuers : [];
$stat = is_array($stat ?? null) ? $stat : ['total' => 0, 'recent' => 0, 'definitions' => 0, 'last_at' => ''];
$linkedDefinitions = is_array($linkedDefinitions ?? null) ? $linkedDefinitions : [];
$authorizations = is_array($authorizations ?? null) ? $authorizations : [];
$recentAwards = is_array($recentAwards ?? null) ? $recentAwards : [];
$definitions = is_array($definitions ?? null) ? $definitions : [];
$units = is_array($units ?? null) ? $units : [];
$members = is_array($members ?? null) ? $members : [];
$jobs = is_array($jobs ?? null) ? $jobs : [];
$enriched = !empty($enriched);
$linkReady = !empty($linkReady);
$authorizationsReady = !empty($authorizationsReady);
$logoUrl = (string) ($logoUrl ?? '');
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
$iid = (int) ($issuer['id'] ?? 0);
$base = 'back-office/referentiels/qualifications/emetteurs/' . $iid;
$isArch = !empty($issuer['archived_at']);
$kind = \App\Repositories\QualificationIssuerRepository::normalizeKind((string) ($issuer['issuer_kind'] ?? 'unit'));
$kindLabels = [
    'school' => 'École',
    'training_center' => 'Centre de formation',
    'unit' => 'Unité',
    'instructor' => 'Instructeur',
    'external' => 'Organisme externe',
];
$roleLabels = ['instructor' => 'Instructeur', 'signatory' => 'Signataire du brevet'];
$short = trim((string) ($issuer['short_name'] ?? ''));
$who = static function (array $a): string {
    $cs = trim((string) ($a['callsign'] ?? ''));
    $dn = trim((string) ($a['display_name'] ?? ''));

    return $cs !== '' && $dn !== '' && $cs !== $dn ? $cs . ' · ' . $dn : ($cs !== '' ? $cs : ($dn !== '' ? $dn : '#' . (int) ($a['user_id'] ?? 0)));
};
$fullDate = static function (string $iso): string {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $iso, $m)) {
        return '—';
    }

    return (int) $m[3] . ' ' . \App\Support\LocalizedCalendar::month((int) $m[2], true) . ' ' . $m[1];
};
$linkAction = $linkReady ? '<p class="qis-empty-act"><a class="effk-btn is-sm" href="#modifier">' . effk_e('Rattacher des qualifications') . '</a></p>' : '';
$attachedCount = count(array_filter($linkedDefinitions, static fn (array $d): bool => !empty($d['attached'])));
?>
<div class="bo-eff-workspace qis-page">
<div class="effk">
    <a class="qis-back" href="<?= effk_e(url('back-office/referentiels/qualifications/emetteurs')) ?>">← Émetteurs</a>

    <header class="effk-ph">
        <div class="effk-ph__copy qis-id">
            <span class="qis-logo qis-logo--lg" style="--c:<?= effk_e(effk_unit_color($iid)) ?>">
                <?php if ($logoUrl !== ''): ?><img src="<?= effk_e($logoUrl) ?>" alt=""><?php else: ?><span translate="no"><?= effk_e(effk_initials($short !== '' ? $short : (string) ($issuer['name'] ?? ''))) ?></span><?php endif; ?>
            </span>
            <div>
                <span class="effk-k">Émetteur · <?= effk_e($kindLabels[$kind]) ?></span>
                <h2 translate="no"><?= effk_e((string) ($issuer['name'] ?? '')) ?></h2>
                <div class="qis-card__meta">
                    <?php if ($short !== ''): ?><span class="effk-tag" translate="no"><?= effk_e($short) ?></span><?php endif; ?>
                    <?php if (trim((string) ($issuer['unit_name'] ?? '')) !== ''): ?><span class="effk-tag">Unité : <span translate="no"><?= effk_e((string) $issuer['unit_name']) ?></span></span><?php endif; ?>
                    <?php if (trim((string) ($issuer['parent_name'] ?? '')) !== ''): ?><span class="effk-tag">Dépend de <span translate="no"><?= effk_e((string) $issuer['parent_name']) ?></span></span><?php endif; ?>
                    <?php if ($isArch): ?><?= effk_pill('Archivé', 'warn') ?><?php else: ?><?= effk_pill('Actif', 'ok') ?><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="effk-ph__acts">
            <?php if ($linkedDefinitions !== []): ?><a class="effk-btn" href="<?= effk_e(url('back-office/referentiels/qualifications') . '?emetteur=' . $iid) ?>">Ses qualifications</a><?php endif; ?>
            <?php if (!$isArch): ?><a class="effk-btn is-dark" href="<?= effk_e(url('back-office/referentiels/qualifications/attribuer') . '?issuer_id=' . $iid) ?>"><?= effk_icon('plus') ?><span>Délivrer en son nom</span></a><?php endif; ?>
        </div>
    </header>

    <?php if ($flashSuccess): ?><p class="effk-note is-info" role="status"><?= effk_e((string) $flashSuccess) ?></p><?php endif; ?>
    <?php if ($flashError): ?><p class="effk-note is-warn" role="alert"><?= effk_e((string) $flashError) ?></p><?php endif; ?>
    <?php if ($isArch): ?>
        <form class="effk-note is-warn qis-archived" method="post" action="<?= effk_e(url($base . '/reactiver')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <span>Archivé : il n’est plus proposé pour les nouvelles délivrances. Les brevets déjà délivrés restent à son nom.</span>
            <button type="submit" class="effk-btn is-sm">Réactiver</button>
        </form>
    <?php endif; ?>
    <?php if (trim((string) ($issuer['description'] ?? '')) !== ''): ?>
        <p class="qis-lead"><?= nl2br(effk_e((string) $issuer['description'])) ?></p>
    <?php endif; ?>

    <?= effk_stats([
        ['label' => 'Délivrances', 'value' => (int) $stat['total'], 'hint' => 'Depuis le début'],
        ['label' => 'Sur 90 jours', 'value' => (int) $stat['recent'], 'hint' => $stat['last_at'] !== '' ? 'Dernière le ' . $fullDate((string) $stat['last_at']) : 'Aucune délivrance', 'tone' => (int) $stat['recent'] > 0 ? 'ok' : ''],
        ['label' => 'Qualifications', 'value' => count($linkedDefinitions), 'hint' => $attachedCount . ' rattachée(s)'],
        ['label' => 'Habilités', 'value' => count($authorizations), 'hint' => 'Instructeurs et signataires'],
    ], 'Chiffres de l’émetteur') ?>

    <?php if ($enriched): ?>
    <p class="qis-sign"><?= effk_icon('check') ?><span><?php if (trim((string) ($issuer['signatory_name'] ?? '')) !== ''): ?>Brevets signés par <b translate="no"><?= effk_e((string) $issuer['signatory_name']) ?></b><?= trim((string) ($issuer['signatory_title'] ?? '')) !== '' ? ', ' . effk_e((string) $issuer['signatory_title']) : '' ?>.<?php else: ?>Aucun signataire renseigné : le brevet est signé « Pour l’autorité émettrice ». <a href="#modifier">Indiquer un signataire</a><?php endif; ?></span></p>
    <?php endif; ?>

    <div class="qis-cols">
        <section class="effk-panel qis-sec" aria-labelledby="qis-defs">
            <h3 id="qis-defs">Qualifications</h3>
            <?php if ($linkedDefinitions === []): ?>
                <?= effk_empty('Aucune qualification liée.', $linkReady ? 'Cochez ses qualifications dans « Modifier l’émetteur » : il deviendra leur émetteur habituel.' : 'Choisissez cet émetteur lors d’une délivrance pour qu’il apparaisse ici.', $linkAction) ?>
            <?php else: ?>
            <div class="effk-table-wrap">
                <table class="effk-table">
                    <thead><tr><th scope="col">Qualification</th><th scope="col">Lien</th><th scope="col" class="effk-num">Délivrances</th></tr></thead>
                    <tbody>
                    <?php foreach ($linkedDefinitions as $d): ?>
                        <tr>
                            <td><a href="<?= effk_e(url('back-office/referentiels/qualifications/' . (int) $d['id'])) ?>" translate="no"><?= effk_e((string) $d['name']) ?></a><?= !empty($d['archived']) ? ' <span class="effk-tag">Archivée</span>' : '' ?></td>
                            <td><?= !empty($d['attached']) ? '<span class="effk-tag">Émetteur habituel</span>' : '<span class="effk-muted">Délivrée en son nom</span>' ?></td>
                            <td class="effk-num"><?= (int) $d['delivered'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>

        <section class="effk-panel qis-sec" id="habilites" aria-labelledby="qis-auth">
            <h3 id="qis-auth">Habilités</h3>
            <p class="qis-help">Instructeurs qui forment et valident, signataires des brevets. Information affichée sur la fiche : la délivrance reste réservée aux responsables de la communauté.</p>
            <?php if (!$authorizationsReady): ?>
                <p class="effk-note is-warn">Les habilités arrivent avec la prochaine mise à jour du site.</p>
            <?php else: ?>
                <?php if ($authorizations === []): ?>
                    <p class="effk-muted">Personne de désigné pour l’instant.</p>
                <?php else: ?>
                <ul class="qis-auths" role="list">
                    <?php foreach ($authorizations as $a):
                        $isUser = (int) ($a['user_id'] ?? 0) > 0; ?>
                    <li>
                        <?php if ($isUser): ?>
                            <?= effk_avatar((int) $a['user_id'], trim((string) ($a['display_name'] ?? '')) ?: $who($a)) ?>
                            <span class="qis-auths__who"><a href="<?= effk_e(url('back-office/ressources/effectifs/membres/' . (int) $a['user_id'])) ?>" translate="no"><?= effk_e($who($a)) ?></a><small><?= effk_e($roleLabels[(string) ($a['role'] ?? 'instructor')] ?? 'Instructeur') ?></small></span>
                        <?php else: ?>
                            <span class="effk-av" style="--av:#475569" aria-hidden="true"><?= effk_icon('user') ?></span>
                            <span class="qis-auths__who"><b translate="no"><?= effk_e((string) ($a['job_role_name'] ?? '')) ?></b><small>Fonction · <?= effk_e($roleLabels[(string) ($a['role'] ?? 'instructor')] ?? 'Instructeur') ?></small></span>
                        <?php endif; ?>
                        <form method="post" action="<?= effk_e(url($base . '/habilites/' . (int) $a['id'] . '/retirer')) ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="effk-btn is-sm is-icon" aria-label="Retirer l’habilitation" title="Retirer l’habilitation"><?= effk_icon('x') ?></button>
                        </form>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <form class="qis-auth-add" method="post" action="<?= effk_e(url($base . '/habilites')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <label class="qis-f" for="qis-who">
                        <span>Membre ou fonction</span>
                        <select id="qis-who" name="who" required>
                            <option value="">Choisir…</option>
                            <?php if ($members !== []): ?>
                            <optgroup label="Membres">
                                <?php foreach ($members as $m): ?><option value="u:<?= (int) $m['id'] ?>" translate="no"><?= effk_e($m['label']) ?></option><?php endforeach; ?>
                            </optgroup>
                            <?php endif; ?>
                            <?php if ($jobs !== []): ?>
                            <optgroup label="Fonctions">
                                <?php foreach ($jobs as $j): ?><option value="j:<?= (int) $j['id'] ?>"><?= effk_e((string) ($j['label'] ?? '')) ?></option><?php endforeach; ?>
                            </optgroup>
                            <?php endif; ?>
                        </select>
                    </label>
                    <label class="qis-f" for="qis-role">
                        <span>Rôle</span>
                        <select id="qis-role" name="role">
                            <?php foreach ($roleLabels as $rk => $rl): ?><option value="<?= effk_e($rk) ?>"><?= effk_e($rl) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" class="effk-btn is-dark"><?= effk_icon('plus') ?><span>Habiliter</span></button>
                </form>
            <?php endif; ?>
        </section>
    </div>

    <section class="effk-panel qis-sec" aria-labelledby="qis-awards">
        <h3 id="qis-awards">Derniers brevets délivrés</h3>
        <?php if ($recentAwards === []): ?>
            <?= effk_empty('Aucune délivrance à son nom.', 'Choisissez cet émetteur en délivrant une qualification, ou rattachez-lui des qualifications : les délivrances automatiques et Academy seront signées à son nom.') ?>
        <?php else: ?>
        <div class="effk-table-wrap">
            <table class="effk-table">
                <thead><tr><th scope="col">Date</th><th scope="col">Membre</th><th scope="col">Qualification</th><th scope="col">Brevet</th><th scope="col">Statut</th></tr></thead>
                <tbody>
                <?php foreach ($recentAwards as $r):
                    $aid = (int) $r['id'];
                    $number = trim((string) ($r['certificate_number'] ?? '')); ?>
                    <tr>
                        <td><?= effk_e($fullDate((string) ($r['obtained_at'] ?: $r['created_at']))) ?></td>
                        <td><a href="<?= effk_e(url('back-office/ressources/effectifs/membres/' . (int) $r['user_id'])) ?>#qualifications" translate="no"><?= effk_e($who($r)) ?></a></td>
                        <td translate="no"><?= effk_e((string) ($r['definition_name'] ?? '')) ?></td>
                        <td><?php if (trim((string) ($r['certificate_document_path'] ?? '')) !== ''): ?><a href="<?= effk_e(url('back-office/referentiels/qualifications/brevets/' . $aid . '/telecharger')) ?>"><span translate="no"><?= effk_e($number !== '' ? $number : '#' . $aid) ?></span> · PDF</a><?php elseif ($number !== ''): ?><span translate="no"><?= effk_e($number) ?></span><?php else: ?><span class="effk-muted">Pas encore généré</span><?php endif; ?></td>
                        <td><?= effk_e(\App\Support\QualificationAdminStatus::label((string) ($r['status_code'] ?? ''))) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <details class="effk-panel effk-fold" id="modifier">
        <summary>
            <span><b>Modifier l’émetteur</b><small>Identité, rattachement, signature du brevet, qualifications délivrées.</small></span>
            <?= effk_icon('down') ?>
        </summary>
        <form class="effk-fold__body qis-form" method="post" action="<?= effk_e(url($base . '/modifier')) ?>" enctype="multipart/form-data">
            <?= \App\Core\Csrf::field() ?>
            <?php
            $iss = $issuer;
            $fid = 'qi-edit';
            $linked = $linkedDefinitions;
            require base_path('views/admin/organization/qualifications/_issuer_fields.php');
            ?>
            <div class="effk-fold__foot">
                <span>Les brevets déjà délivrés gardent leur émetteur.</span>
                <button type="submit" class="effk-btn is-dark"><?= effk_icon('check') ?><span>Enregistrer</span></button>
            </div>
        </form>
    </details>

    <?php if ($enriched && !$isArch): ?>
    <form class="qis-archive" method="post" action="<?= effk_e(url($base . '/archiver')) ?>">
        <?= \App\Core\Csrf::field() ?>
        <div>
            <b>Archiver l’émetteur</b>
            <p>Il ne sera plus proposé pour les nouvelles délivrances. Rien n’est supprimé : les brevets déjà délivrés restent à son nom et vous pourrez le réactiver.</p>
        </div>
        <button type="submit" class="effk-btn">Archiver</button>
    </form>
    <?php endif; ?>
</div>
</div>
<script src="<?= effk_e(asset_url('assets/js/qualification-issuers.js')) ?>" defer></script>
