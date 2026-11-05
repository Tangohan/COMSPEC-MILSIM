<?php
declare(strict_types=1);

/**
 * Matricules d’organisation — charte ATHENA (en-tête rendu par la coque back-office).
 *
 * Contrôle du registre (doublons, membres sans matricule, hors format, numéros sautés), règles de format
 * avec aperçu en direct, registre des membres filtrable, attribution du prochain matricule, export / import
 * et historique des changements.
 *
 * @var bool $memberNumberSchemaReady
 * @var array<string, mixed> $memberNumberConfig
 * @var string|null $memberNumberPreview
 * @var list<string> $memberNumberModes
 * @var string $memberNumberCsrf
 * @var list<array{id: int, display_name: string, callsign: string, email: string, athena_identifier: string, tenant_member_number: string}> $memberNumberMembers
 * @var array<string, mixed>|null $memberNumberAnalysis
 * @var list<array<string, mixed>> $memberNumberAudit
 * @var string $memberNumberTenantToken
 * @var array<string, mixed>|null $memberNumberRenumber Aperçu de renumérotation (?renumeroter=1)
 * @var array<string, mixed>|null $memberNumberImportPreview Aperçu d’import en attente
 * @var array{unit: string, grade: string} $memberNumberContextSample Exemple d’unité / grade pour l’aperçu
 */

use App\Support\MemberNumberAnalysis;

$schemaReady = !empty($memberNumberSchemaReady);
$config = is_array($memberNumberConfig ?? null) ? $memberNumberConfig : [];
$preview = isset($memberNumberPreview) ? (string) $memberNumberPreview : '';
$modes = is_array($memberNumberModes ?? null) ? $memberNumberModes : ['free', 'automatic', 'assisted'];
$csrf = htmlspecialchars((string) ($memberNumberCsrf ?? ''), ENT_QUOTES, 'UTF-8');
$members = is_array($memberNumberMembers ?? null) ? $memberNumberMembers : [];
$an = is_array($memberNumberAnalysis ?? null) ? $memberNumberAnalysis : null;
$audit = is_array($memberNumberAudit ?? null) ? $memberNumberAudit : [];
$tenantToken = (string) ($memberNumberTenantToken ?? '');
$renumber = is_array($memberNumberRenumber ?? null) ? $memberNumberRenumber : null;
$importPreview = is_array($memberNumberImportPreview ?? null) ? $memberNumberImportPreview : null;
$ctxSample = is_array($memberNumberContextSample ?? null) ? $memberNumberContextSample : ['unit' => 'ALPHA', 'grade' => 'SGT'];
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$enabled = !empty($config['enabled']);
$label = (string) ($config['label'] ?? "Matricule d'organisation");
$mode = (string) ($config['mode'] ?? 'free');
$pattern = (string) ($config['pattern'] ?? '{PREFIX}-{NUMBER:4}');
$prefix = (string) ($config['prefix'] ?? '');
$nextSeq = max(1, (int) ($config['next_sequence'] ?? 1));
$unique = !array_key_exists('unique_required', $config) || !empty($config['unique_required']);
$required = !empty($config['required']);
$canGenerate = $schemaReady && $enabled && $mode !== 'free';

$modeInfo = [
    'free' => ['Saisie libre', 'Chaque matricule est tapé à la main sur la fiche du membre.'],
    'automatic' => ['Génération automatique', 'Chaque nouveau membre reçoit le prochain matricule, sans intervention.'],
    'assisted' => ['Saisie assistée', 'Le prochain matricule est proposé ; vous pouvez le remplacer avant de valider.'],
];
// Éléments du modèle : libellé lisible, valeur insérée dans le champ.
$tokens = [
    ['{PREFIX}', 'Préfixe'],
    ['{NUMBER:4}', 'Numéro sur 4 chiffres'],
    ['{NUMBER:3}', 'Numéro sur 3 chiffres'],
    ['{NUMBER}', 'Numéro sans zéros'],
    ['{YEAR}', 'Année (' . date('Y') . ')'],
    ['{YEAR:2}', 'Année (' . date('y') . ')'],
    ['{MONTH}', 'Mois'],
    ['{TENANT}', 'Nom court de la communauté'],
    ['{UNIT}', 'Code de l’unité du membre'],
    ['{GRADE}', 'Grade du membre'],
];
$presets = [
    '{PREFIX}-{NUMBER:4}' => 'Préfixe et numéro',
    '{PREFIX}-{YEAR}-{NUMBER:3}' => 'Préfixe, année et numéro',
    '{YEAR:2}-{NUMBER:4}' => 'Année courte et numéro',
    '{TENANT}/{NUMBER:4}' => 'Communauté et numéro',
    '{NUMBER:5}' => 'Numéro seul',
];

$nameOf = static function (array $m): string {
    foreach (['display_name', 'callsign', 'email'] as $k) {
        $v = trim((string) ($m[$k] ?? ''));
        if ($v !== '') {
            return $v;
        }
    }

    return 'Membre #' . (int) ($m['id'] ?? 0);
};
$byId = [];
foreach ($members as $m) {
    $byId[(int) $m['id']] = $m;
}
$dupIds = [];
foreach (($an['duplicates'] ?? []) as $ids) {
    foreach ($ids as $id) {
        $dupIds[(int) $id] = true;
    }
}
$formatChecked = !empty($an['format_checked']);
$offIds = $formatChecked ? array_fill_keys(array_map('intval', $an['off_format'] ?? []), true) : [];

$total = (int) ($an['total'] ?? count($members));
$assigned = (int) ($an['assigned'] ?? 0);
$missing = (int) ($an['missing'] ?? 0);
$dupGroups = count($an['duplicates'] ?? []);
$dupMembers = (int) ($an['duplicate_members'] ?? 0);
$offCount = count($offIds);
$pct = static fn (int $n): string => $total > 0 ? (string) (int) round($n / $total * 100) . '%' : '0%';
$sourceLabels = [
    'manual' => 'Saisie', 'manual_override' => 'Saisie', 'assisted' => 'Proposé', 'automatic' => 'Automatique',
    'import' => 'Import', 'bulk' => 'Attribution groupée', 'regenerate' => 'Régénéré',
    'restore' => 'Rétabli depuis l’historique', 'renumber' => 'Renumérotation',
];
?>
<?php if ($flashOk): ?>
<p class="ath-flash ath-flash--ok" role="status"><?= $h((string) $flashOk) ?></p>
<?php endif; ?>
<?php if ($flashErr): ?>
<p class="ath-flash ath-flash--err" role="alert"><?= $h((string) $flashErr) ?></p>
<?php endif; ?>

<?php
$orgCodes = is_array($memberNumberUnitCodes ?? null) ? $memberNumberUnitCodes : [];
$orgCommunity = $orgCodes['community'] ?? null;
$orgUnits = is_array($memberNumberUnits ?? null) ? $memberNumberUnits : [];
$canManageCodes = !empty($memberNumberCanManageCodes);
?>
<section class="bo-mn-panel" id="mn-organigramme" aria-labelledby="mn-organigramme-title">
    <header class="bo-mn-panel__head"><h2 class="bo-mn-panel__title" id="mn-organigramme-title">Préfixes de l’organigramme</h2></header>
    <div class="bo-mn-panel__body">
        <p class="bo-mn-text">Le préfixe de communauté ouvre chaque code hiérarchique, par exemple SOAR-B-A-1. Chaque unité ajoute son code à celui de son parent. Les identifiants administratifs restent permanents.</p>
        <?php if ($orgCommunity === null): ?>
            <p class="bo-mn-text" role="status">Les codes de l’organigramme sont temporairement indisponibles.</p>
        <?php else: ?>
            <form method="post" action="<?= $h(url('back-office/organisation/matricules/prefixes')) ?>" class="bo-mn-prefix-form">
                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                <div class="ath-field"><label class="ath-field__label" for="mn-community-code">Préfixe de communauté</label>
                <input class="ath-field__input bo-mn-mono" id="mn-community-code" name="community_code" value="<?= $h((string) $orgCommunity['code']) ?>" required minlength="2" maxlength="10" pattern="[A-Za-z0-9]{2,10}" aria-describedby="mn-community-help" <?= $canManageCodes ? '' : 'disabled' ?>></div>
                <?php if ($canManageCodes): ?><button class="ath-btn ath-btn--solid" type="submit">Enregistrer le préfixe</button><?php endif; ?>
            </form>
            <p class="bo-mn-text bo-mn-muted" id="mn-community-help">2 à 10 lettres ou chiffres. Modifier ce préfixe recalcule les codes de toutes les unités.</p>
            <?php foreach ($orgUnits as $orgUnit): $orgId = (int) $orgUnit['id']; $computed = $orgCodes['units'][$orgId] ?? []; ?>
                <form method="post" action="<?= $h(url('back-office/organisation/matricules/prefixes')) ?>" class="bo-mn-prefix-row">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="unit_id" value="<?= $orgId ?>">
                    <div><strong><?= $h((string) $orgUnit['name']) ?></strong><br><code><?= $h((string) ($computed['code'] ?? '—')) ?></code></div>
                    <div class="ath-field"><label class="ath-field__label" for="mn-segment-<?= $orgId ?>">Code de l’unité</label><input class="ath-field__input bo-mn-mono" id="mn-segment-<?= $orgId ?>" name="segment" value="<?= $h((string) ($orgUnit['code'] ?? '')) ?>" maxlength="8" pattern="[A-Za-z0-9]{0,8}" placeholder="Automatique" <?= $canManageCodes ? '' : 'disabled' ?>></div>
                    <?php if ($canManageCodes): ?><button class="ath-btn" type="submit">Enregistrer</button><?php endif; ?>
                </form>
            <?php endforeach; ?>
            <p class="bo-mn-text bo-mn-muted">Code d’unité : 1 à 8 lettres ou chiffres ; laissez vide pour le calculer depuis le nom. Les matricules des membres déjà attribués se gèrent séparément dans le registre ci-dessous.</p>
        <?php endif; ?>
    </div>
</section>

<?php if (!$schemaReady): ?>
<div class="ath-note">
    <div class="ath-note__title">Module en préparation</div>
    <div class="ath-note__text">Les matricules d’organisation seront disponibles après la prochaine mise à jour de la plateforme.</div>
</div>
<?php return; endif; ?>

<div class="bo-mn" data-bo-mn>
<?php
$athKpis = [
    ['label' => 'MEMBRES ACTIFS', 'value' => (string) $total, 'tone' => '#1e4f80', 'pct' => $total > 0 ? '100%' : '0%', 'note' => 'dans la communauté'],
    ['label' => 'AVEC MATRICULE', 'value' => (string) $assigned, 'tone' => '#0b8a5c', 'pct' => $pct($assigned), 'note' => $pct($assigned) . ' de l’effectif'],
    ['label' => 'SANS MATRICULE', 'value' => (string) $missing, 'tone' => $missing === 0 ? '#0b8a5c' : '#c98a12', 'pct' => $pct($missing), 'note' => $missing === 0 ? 'tout le monde est numéroté' : 'à attribuer'],
    ['label' => 'DOUBLONS', 'value' => (string) $dupGroups, 'tone' => $dupGroups === 0 ? '#0b8a5c' : '#c72e2e', 'pct' => $pct($dupMembers), 'note' => $dupGroups === 0 ? 'aucun matricule partagé' : $dupMembers . ' membres concernés'],
    ['label' => 'PROCHAIN MATRICULE', 'value' => $preview !== '' ? $preview : '—', 'tone' => '#1e4f80', 'pct' => $canGenerate ? '100%' : '0%', 'note' => $canGenerate ? 'séquence n° ' . $nextSeq : ($enabled ? 'saisie libre' : 'matricules désactivés')],
];
require base_path('views/partials/ath_kpis.php');

// Points à corriger : [ton, titre, texte HTML déjà échappé, filtre du registre, séquence proposée].
$issues = [];
if ($dupGroups > 0) {
    $list = [];
    foreach (array_slice($an['duplicates'], 0, 6, true) as $value => $ids) {
        $names = array_map(static fn (int $id): string => $nameOf($byId[$id] ?? ['id' => $id]), array_slice($ids, 0, 3));
        $list[] = '<b class="bo-mn-mono">' . $h((string) ($byId[$ids[0]]['tenant_member_number'] ?? $value)) . '</b> : ' . $h(implode(', ', $names)) . (count($ids) > 3 ? '…' : '');
    }
    $issues[] = ['bad', $dupGroups . ' matricule(s) attribué(s) à plusieurs membres', implode(' · ', $list) . ($dupGroups > 6 ? ' · …' : ''), 'dup', null];
}
if (!empty($an['sequence_behind'])) {
    $issues[] = ['bad', 'La prochaine séquence est en retard', $h('Le plus grand numéro déjà attribué est ' . (int) $an['max_sequence'] . ' alors que la séquence reprend à ' . $nextSeq . ' : les prochains matricules générés seraient refusés comme doublons.'), null, (int) $an['suggested_next']];
}
if ($offCount > 0) {
    $issues[] = ['warn', $offCount . ' matricule(s) ne suivent pas le modèle actuel', $h('Souvent des matricules saisis à la main ou importés avant un changement de modèle. Ils restent valides.'), 'off', null];
}
if ((int) ($an['gap_count'] ?? 0) > 0) {
    $ranges = MemberNumberAnalysis::ranges($an['gaps']);
    $more = (int) $an['gap_count'] > count($an['gaps']) || count($ranges) > 12;
    $issues[] = ['info', (int) $an['gap_count'] . ' numéro(s) libre(s) dans la séquence', $h('Numéros jamais attribués ou libérés : ' . implode(', ', array_slice($ranges, 0, 12)) . ($more ? '…' : '') . '. Un départ ou un matricule retiré laisse un trou : ce n’est pas une erreur.'), null, null];
}
if ($missing > 0 && $required) {
    $issues[] = ['warn', $missing . ' membre(s) sans matricule alors qu’il est obligatoire', $h($canGenerate ? 'Utilisez « Attribuer à tous » dans le registre ci-dessous.' : 'Saisissez-les dans le registre ci-dessous.'), 'sans', null];
}
?>
<section class="bo-mn-panel ath-rise" aria-labelledby="mn-check-title">
    <header class="bo-mn-panel__head">
        <h2 class="bo-mn-panel__title" id="mn-check-title">Contrôle du registre</h2>
        <span class="bo-mn-panel__hint"><?= $issues === [] ? 'Aucun point à corriger' : count($issues) . ' point(s) à regarder' ?></span>
    </header>
    <?php if ($issues === []): ?>
    <p class="bo-mn-ok"><span class="ath-tag ath-tag--ok">Registre propre</span> Pas de doublon, séquence à jour<?= $total > 0 && $missing === 0 ? ', tous les membres actifs sont numérotés' : '' ?>.</p>
    <?php else: ?>
    <ul class="bo-mn-issues">
        <?php foreach ($issues as [$tone, $title, $html, $filter, $realign]): ?>
        <li class="bo-mn-issue bo-mn-issue--<?= $h($tone) ?>">
            <div class="bo-mn-issue__body">
                <strong><?= $h($title) ?></strong>
                <span><?= $html ?></span>
            </div>
            <div class="bo-mn-issue__acts">
                <?php if ($filter !== null): ?>
                <button type="button" class="ath-btn" data-mn-show="<?= $h($filter) ?>">Voir dans le registre</button>
                <?php endif; ?>
                <?php if ($realign !== null): ?>
                <form method="post" action="<?= $h(url('back-office/organisation/matricules/recaler')) ?>">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                    <button type="submit" class="ath-btn ath-btn--solid">Reprendre à <?= (int) $realign ?></button>
                </form>
                <?php endif; ?>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>

<form method="post" action="<?= $h(url('back-office/organisation/matricules')) ?>" class="bo-mn-rules ath-rise" data-mn-form
      data-tenant="<?= $h($tenantToken) ?>" data-unit="<?= $h((string) $ctxSample['unit']) ?>" data-grade="<?= $h((string) $ctxSample['grade']) ?>" data-year="<?= $h(date('Y')) ?>" data-month="<?= $h(date('m')) ?>">
    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
    <section class="bo-mn-panel">
        <header class="bo-mn-panel__head">
            <h2 class="bo-mn-panel__title">Règles</h2>
            <span class="bo-mn-spacer" aria-hidden="true"></span>
            <label class="bo-mn-switch">
                <span class="ath-toggle <?= $enabled ? 'is-on' : 'is-off' ?>">
                    <input type="checkbox" class="ath-toggle__input" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?> data-mn-toggle>
                    <span class="ath-toggle__knob" aria-hidden="true"></span>
                </span>
                <span>Matricules d’organisation activés</span>
            </label>
        </header>
        <div class="bo-mn-panel__body">
            <div class="ath-form__grid">
                <div class="ath-field">
                    <label class="ath-field__label" for="mn-label">Nom affiché</label>
                    <input class="ath-field__input" id="mn-label" name="label" value="<?= $h($label) ?>" maxlength="80" placeholder="Matricule d'organisation">
                    <span class="ath-field__help">Ex. Matricule interne, Numéro d’incorporation, Service Number.</span>
                </div>
                <div class="ath-field">
                    <label class="ath-field__label" for="mn-prefix">Préfixe</label>
                    <input class="ath-field__input bo-mn-mono" id="mn-prefix" name="prefix" value="<?= $h($prefix) ?>" maxlength="40" placeholder="GEND" data-mn-in="prefix">
                    <span class="ath-field__help">Lettres placées devant le numéro, si le modèle les utilise.</span>
                </div>
            </div>

            <p class="bo-mn-sub">Comment les matricules sont attribués</p>
            <div class="ath-choice-grid">
                <?php foreach ($modes as $m): $m = (string) $m; [$mName, $mDesc] = $modeInfo[$m] ?? [$m, '']; ?>
                <label class="ath-choice">
                    <input type="radio" name="mode" value="<?= $h($m) ?>" <?= $mode === $m ? 'checked' : '' ?> data-mn-in="mode">
                    <span class="ath-choice__body">
                        <span class="ath-choice__name"><?= $h($mName) ?></span>
                        <span class="ath-choice__desc"><?= $h($mDesc) ?></span>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>

            <p class="bo-mn-sub">Modèle du matricule</p>
            <div class="bo-mn-pattern">
                <div class="ath-field">
                    <label class="ath-field__label" for="mn-pattern">Modèle</label>
                    <input class="ath-field__input bo-mn-mono" id="mn-pattern" name="pattern" value="<?= $h($pattern) ?>" maxlength="120" placeholder="{PREFIX}-{NUMBER:4}" data-mn-in="pattern" spellcheck="false" autocomplete="off">
                </div>
                <div class="ath-field">
                    <label class="ath-field__label" for="mn-preset">Modèles courants</label>
                    <select class="ath-field__select" id="mn-preset" data-mn-preset>
                        <option value="">Choisir…</option>
                        <?php foreach ($presets as $pv => $pl): ?>
                        <option value="<?= $h($pv) ?>"<?= $pv === $pattern ? ' selected' : '' ?>><?= $h($pl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="bo-mn-tokens" role="group" aria-label="Ajouter un élément au modèle">
                <?php foreach ($tokens as [$tv, $tl]): ?>
                <button type="button" class="bo-mn-token" data-mn-token="<?= $h($tv) ?>" title="Insère <?= $h($tv) ?> à l’emplacement du curseur">+ <?= $h($tl) ?></button>
                <?php endforeach; ?>
            </div>
            <p class="bo-mn-warn" data-mn-nonumber<?= $mode === 'free' || \App\Services\Personnel\TenantMemberNumberService::patternHasNumber($pattern) ? ' hidden' : '' ?>>Le modèle n’a pas de numéro : tous les matricules générés seraient identiques. Ajoutez un « Numéro ».</p>

            <div class="ath-form__grid bo-mn-gap">
                <div class="ath-field">
                    <label class="ath-field__label" for="mn-next">Prochain numéro de la séquence</label>
                    <input class="ath-field__input bo-mn-mono" type="number" id="mn-next" name="next_sequence" min="1" value="<?= $nextSeq ?>" data-mn-in="next">
                    <span class="ath-field__help">Augmente de 1 à chaque matricule généré.<?php if ((int) ($an['max_sequence'] ?? 0) > 0): ?> Plus grand numéro déjà attribué : <?= (int) $an['max_sequence'] ?>.<?php endif; ?></span>
                </div>
                <div class="bo-mn-checks">
                    <label class="bo-mn-check"><input type="checkbox" name="unique_required" value="1" <?= $unique ? 'checked' : '' ?>> Un matricule n’appartient qu’à un seul membre</label>
                    <label class="bo-mn-check"><input type="checkbox" name="required" value="1" <?= $required ? 'checked' : '' ?>> Obligatoire pour chaque membre</label>
                </div>
            </div>
        </div>
        <footer class="bo-mn-panel__foot">
            <button type="submit" class="ath-btn ath-btn--solid">Enregistrer les règles</button>
            <span class="bo-mn-panel__hint">L’identifiant plateforme (Athena) reste permanent et n’est jamais remplacé.</span>
        </footer>
    </section>
    <aside class="ath-panel-dark bo-mn-preview" aria-label="Aperçu">
        <p class="ath-panel-dark__kicker">Aperçu</p>
        <p class="bo-mn-preview__label" data-mn-pv-label><?= $h($label) ?></p>
        <p class="bo-mn-preview__value" data-mn-pv><?= $h($preview !== '' ? $preview : '—') ?></p>
        <p class="bo-mn-preview__then">puis</p>
        <ul class="bo-mn-preview__next" data-mn-pv-next></ul>
        <p class="bo-mn-preview__ctx" data-mn-pv-ctx<?= \App\Services\Personnel\TenantMemberNumberService::patternUsesContext($pattern) ? '' : ' hidden' ?>>Exemple pour un membre de l’unité <?= $h((string) $ctxSample['unit']) ?>, grade <?= $h((string) $ctxSample['grade']) ?>. Sans unité ou sans grade, l’élément est simplement omis.</p>
        <p class="bo-mn-preview__mode" data-mn-pv-mode><?= $h(($modeInfo[$mode] ?? [$mode])[0]) ?><?= $enabled ? '' : ' · désactivé' ?></p>
    </aside>
</form>

<section class="bo-mn-panel ath-rise" aria-labelledby="mn-reg-title">
    <header class="bo-mn-panel__head">
        <h2 class="bo-mn-panel__title" id="mn-reg-title">Registre des membres</h2>
        <span class="bo-mn-panel__hint" data-mn-count><?= $total ?> membre<?= $total > 1 ? 's' : '' ?></span>
        <span class="bo-mn-spacer" aria-hidden="true"></span>
        <a class="ath-btn" href="<?= $h(url('back-office/organisation/matricules/export')) ?>">Exporter CSV</a>
        <?php if ($canGenerate && $missing > 0): ?>
        <form method="post" action="<?= $h(url('back-office/organisation/matricules/attribuer')) ?>" data-mn-confirm="Attribuer le prochain matricule aux <?= $missing ?> membre(s) qui n’en ont pas ?">
            <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
            <button type="submit" class="ath-btn ath-btn--solid">Attribuer à tous les membres sans matricule (<?= $missing ?>)</button>
        </form>
        <?php endif; ?>
    </header>
    <div class="bo-mn-bar">
        <label class="ath-table-toolbar__search bo-mn-search">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#8c979b" stroke-width="2.2" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.2-3.2"></path></svg>
            <input type="search" placeholder="Nom, indicatif, e-mail ou matricule…" autocomplete="off" spellcheck="false" aria-label="Rechercher un membre" data-mn-q>
        </label>
        <div class="bo-mn-filters" role="group" aria-label="Filtrer le registre">
            <button type="button" class="bo-mn-chip" aria-pressed="true" data-mn-filter="all">Tous <span><?= $total ?></span></button>
            <button type="button" class="bo-mn-chip" aria-pressed="false" data-mn-filter="sans">Sans matricule <span><?= $missing ?></span></button>
            <button type="button" class="bo-mn-chip" aria-pressed="false" data-mn-filter="dup">Doublons <span><?= count($dupIds) ?></span></button>
            <?php if ($formatChecked): ?>
            <button type="button" class="bo-mn-chip" aria-pressed="false" data-mn-filter="off">Hors modèle <span><?= $offCount ?></span></button>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($members === []): ?>
    <p class="bo-mn-empty">Aucun membre actif dans la communauté pour l’instant.</p>
    <?php else: ?>
    <div class="ath-table-wrap">
        <table class="ath-table bo-mn-table">
            <thead>
                <tr><th>MEMBRE</th><th>E-MAIL</th><th>IDENTIFIANT PLATEFORME</th><th><?= $h(mb_strtoupper($label)) ?></th><th>ÉTAT</th><th>ACTION</th></tr>
            </thead>
            <tbody>
            <?php foreach ($members as $m):
                $id = (int) $m['id'];
                $num = (string) $m['tenant_member_number'];
                $state = $num === '' ? 'sans' : (isset($dupIds[$id]) ? 'dup' : (isset($offIds[$id]) ? 'off' : 'ok'));
                [$tagTone, $tagLabel] = ['ok' => ['ok', 'À jour'], 'sans' => ['warn', 'Sans matricule'], 'dup' => ['bad', 'Doublon'], 'off' => ['neut', 'Hors modèle']][$state];
                $name = $nameOf($m);
                $search = mb_strtolower($name . ' ' . $m['callsign'] . ' ' . $m['email'] . ' ' . $num . ' ' . $m['athena_identifier']);
            ?>
                <tr class="ath-row" data-mn-row data-state="<?= $state ?>" data-search="<?= $h($search) ?>">
                    <td>
                        <a class="bo-mn-name" href="<?= $h(url('back-office/users/' . $id)) ?>"><?= $h($name) ?></a>
                        <?php if ($m['callsign'] !== '' && $m['callsign'] !== $name): ?><span class="bo-mn-callsign"><?= $h($m['callsign']) ?></span><?php endif; ?>
                    </td>
                    <td class="bo-mn-muted"><?= $h($m['email'] !== '' ? $m['email'] : '—') ?></td>
                    <td class="bo-mn-mono bo-mn-muted"><?= $h($m['athena_identifier'] !== '' ? $m['athena_identifier'] : '—') ?></td>
                    <td class="bo-mn-mono"><b><?= $h($num !== '' ? $num : '—') ?></b></td>
                    <td><span class="ath-tag ath-tag--<?= $tagTone ?>"><?= $h($tagLabel) ?></span></td>
                    <td class="bo-mn-actions">
                        <button type="button" class="ath-row-action" data-mn-edit="<?= $id ?>" data-mn-name="<?= $h($name) ?>" data-mn-current="<?= $h($num) ?>">Modifier</button>
                        <?php if ($canGenerate && $num === ''): ?>
                        <form method="post" action="<?= $h(url('back-office/organisation/matricules/attribuer')) ?>">
                            <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="user_id" value="<?= $id ?>">
                            <button type="submit" class="ath-row-action">Attribuer le prochain</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="bo-mn-empty" data-mn-none hidden>Aucun membre ne correspond.</p>
    <?php endif; ?>
</section>

<?php
$renumScope = (string) ($renumber['scope'] ?? 'off');
$renumNumbering = (string) ($renumber['numbering'] ?? 'keep');
?>
<section class="bo-mn-panel ath-rise" aria-labelledby="mn-renum-title">
    <header class="bo-mn-panel__head">
        <h2 class="bo-mn-panel__title" id="mn-renum-title">Renuméroter selon le modèle actuel</h2>
        <span class="bo-mn-panel__hint">Utile après un changement de modèle. Rien n’est modifié avant votre validation.</span>
    </header>
    <div class="bo-mn-panel__body">
        <form method="get" action="<?= $h(url('back-office/organisation/matricules')) ?>" class="bo-mn-renum-form">
            <input type="hidden" name="renumeroter" value="1">
            <div class="ath-field">
                <label class="ath-field__label" for="mn-renum-scope">Matricules concernés</label>
                <select class="ath-field__select" id="mn-renum-scope" name="portee">
                    <option value="off"<?= $renumScope === 'off' ? ' selected' : '' ?>>Seulement ceux qui ne suivent pas le modèle<?= $offCount > 0 ? ' (' . $offCount . ')' : '' ?></option>
                    <option value="all"<?= $renumScope === 'all' ? ' selected' : '' ?>>Tous les matricules attribués (<?= $assigned ?>)</option>
                </select>
            </div>
            <div class="ath-field">
                <label class="ath-field__label" for="mn-renum-num">Numéro</label>
                <select class="ath-field__select" id="mn-renum-num" name="numero">
                    <option value="keep"<?= $renumNumbering === 'keep' ? ' selected' : '' ?>>Garder le numéro actuel (chiffres de fin)</option>
                    <option value="next"<?= $renumNumbering === 'next' ? ' selected' : '' ?>>Numéroter à la suite, à partir de <?= $nextSeq ?></option>
                </select>
            </div>
            <button type="submit" class="ath-btn">Voir l’aperçu</button>
        </form>
        <?php if ($renumber !== null): ?>
            <?php if (!empty($renumber['error'])): ?>
            <p class="ath-flash ath-flash--err bo-mn-gap"><?= $h((string) $renumber['error']) ?></p>
            <?php elseif (($renumber['rows'] ?? []) === []): ?>
            <p class="bo-mn-text bo-mn-gap">Aucun matricule à renuméroter avec ces choix.</p>
            <?php else:
                $renumRows = $renumber['rows'];
                $renumChanges = (int) $renumber['changes'];
                $renumConflicts = (int) $renumber['conflicts'];
            ?>
            <p class="bo-mn-text bo-mn-gap"><strong><?= $renumChanges ?> matricule(s) changeront</strong><?= $renumConflicts > 0 ? ', ' . $renumConflicts . ' resteront inchangés à cause d’un conflit' : '' ?>. La séquence reprendra ensuite à <?= (int) $renumber['next_after'] ?>. Chaque changement est inscrit dans l’historique et peut être annulé ligne par ligne.</p>
            <div class="ath-table-wrap bo-mn-scroll">
                <table class="ath-table bo-mn-table">
                    <thead><tr><th>MEMBRE</th><th>ACTUEL</th><th>NOUVEAU</th><th>ÉTAT</th></tr></thead>
                    <tbody>
                    <?php foreach ($renumRows as $r):
                        [$rt, $rl] = ['change' => ['ok', 'Changera'], 'same' => ['neut', 'Déjà conforme'], 'conflict' => ['bad', 'Conflit']][$r['status']] ?? ['neut', '—'];
                    ?>
                        <tr>
                            <td><?= $h($nameOf($byId[(int) $r['id']] ?? ['id' => (int) $r['id']])) ?></td>
                            <td class="bo-mn-mono"><?= $h((string) $r['old']) ?></td>
                            <td class="bo-mn-mono"><b><?= $h((string) $r['new']) ?></b></td>
                            <td><span class="ath-tag ath-tag--<?= $rt ?>"><?= $h($rl) ?></span><?php if ((string) $r['reason'] !== ''): ?> <span class="bo-mn-muted"><?= $h((string) $r['reason']) ?></span><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="ath-form__actions">
                <?php if ($renumChanges > 0): ?>
                <form method="post" action="<?= $h(url('back-office/organisation/matricules/renumeroter')) ?>" data-mn-confirm="Renuméroter <?= $renumChanges ?> matricule(s) ?">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="portee" value="<?= $h($renumScope) ?>">
                    <input type="hidden" name="numero" value="<?= $h($renumNumbering) ?>">
                    <input type="hidden" name="empreinte" value="<?= $h((string) $renumber['hash']) ?>">
                    <button type="submit" class="ath-btn ath-btn--solid">Appliquer la renumérotation (<?= $renumChanges ?>)</button>
                </form>
                <?php endif; ?>
                <a class="ath-btn" href="<?= $h(url('back-office/organisation/matricules')) ?>">Fermer l’aperçu</a>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<div class="bo-mn-split">
    <section class="bo-mn-panel ath-rise" aria-labelledby="mn-import-title">
        <header class="bo-mn-panel__head">
            <h2 class="bo-mn-panel__title" id="mn-import-title">Importer depuis un tableur</h2>
        </header>
        <div class="bo-mn-panel__body">
            <p class="bo-mn-text">Le plus simple : exportez le registre, complétez la colonne des matricules dans votre tableur, puis réimportez le fichier. Les membres sont reconnus par leur adresse e-mail ; doublons, caractères non autorisés et adresses inconnues sont signalés.</p>
            <?php if ($importPreview !== null):
                $impRows = (array) ($importPreview['rows'] ?? []);
                $impCount = ['ok' => 0, 'same' => 0, 'error' => 0];
                foreach ($impRows as $ir) {
                    $impCount[(string) ($ir['status'] ?? 'error')] = ($impCount[(string) ($ir['status'] ?? 'error')] ?? 0) + 1;
                }
            ?>
            <div class="bo-mn-imp">
                <p class="bo-mn-text"><strong>Vérification de « <?= $h((string) ($importPreview['file'] ?? '')) ?> »</strong> : <?= $impCount['ok'] ?> ligne(s) à appliquer, <?= $impCount['same'] ?> déjà à jour, <?= $impCount['error'] ?> refusée(s). Rien n’a encore été modifié.</p>
                <?php if ($impRows !== []): ?>
                <div class="ath-table-wrap bo-mn-scroll">
                    <table class="ath-table bo-mn-table bo-mn-table--compact">
                        <thead><tr><th>LIGNE</th><th>MEMBRE</th><th>ACTUEL</th><th>FICHIER</th><th>RÉSULTAT</th></tr></thead>
                        <tbody>
                        <?php foreach ($impRows as $ir):
                            $st = (string) ($ir['status'] ?? 'error');
                            [$it, $il] = ['ok' => ['ok', 'Sera appliqué'], 'same' => ['neut', 'Déjà à jour'], 'error' => ['bad', 'Refusé']][$st] ?? ['bad', 'Refusé'];
                        ?>
                            <tr>
                                <td class="bo-mn-mono"><?= (int) ($ir['line'] ?? 0) ?></td>
                                <td><?= $h((string) (($ir['name'] ?? '') !== '' ? $ir['name'] : ($ir['email'] ?? ''))) ?><?php if (($ir['name'] ?? '') !== ''): ?><span class="bo-mn-callsign"><?= $h((string) $ir['email']) ?></span><?php endif; ?></td>
                                <td class="bo-mn-mono"><?= $h((string) (($ir['current'] ?? '') !== '' ? $ir['current'] : '—')) ?></td>
                                <td class="bo-mn-mono"><b><?= $h((string) (($ir['number'] ?? '') !== '' ? $ir['number'] : '—')) ?></b></td>
                                <td><span class="ath-tag ath-tag--<?= $it ?>"><?= $h($il) ?></span><?php if ($st === 'error' && (string) ($ir['message'] ?? '') !== ''): ?> <span class="bo-mn-muted"><?= $h((string) $ir['message']) ?></span><?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                <div class="ath-form__actions">
                    <?php if ($impCount['ok'] > 0): ?>
                    <form method="post" action="<?= $h(url('back-office/organisation/matricules/import/appliquer')) ?>">
                        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                        <button type="submit" class="ath-btn ath-btn--solid">Appliquer <?= $impCount['ok'] ?> ligne(s)</button>
                    </form>
                    <?php endif; ?>
                    <form method="post" action="<?= $h(url('back-office/organisation/matricules/import/annuler')) ?>">
                        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                        <button type="submit" class="ath-btn">Abandonner</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            <form method="post" action="<?= $h(url('back-office/organisation/matricules/import')) ?>" enctype="multipart/form-data" class="bo-mn-import">
                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                <input type="file" name="csv_file" accept=".csv,text/csv" required aria-label="Fichier à importer">
                <button type="submit" class="ath-btn<?= $importPreview === null ? ' ath-btn--solid' : '' ?>">Vérifier le fichier</button>
            </form>
            <p class="bo-mn-text bo-mn-muted">Le fichier est d’abord vérifié ligne par ligne : vous voyez ce qui sera modifié avant de l’appliquer.</p>
            <p class="bo-mn-text bo-mn-muted">Séparateur point-virgule ou virgule. Les colonnes « email » et « tenant_member_number » du fichier exporté sont celles qui comptent ; les autres sont ignorées.</p>
            <a class="ath-btn" href="<?= $h(url('back-office/organisation/matricules/export?filtre=sans')) ?>">Télécharger les membres sans matricule</a>
        </div>
    </section>

    <section class="bo-mn-panel ath-rise" aria-labelledby="mn-audit-title">
        <header class="bo-mn-panel__head">
            <h2 class="bo-mn-panel__title" id="mn-audit-title">Derniers changements</h2>
        </header>
        <?php if ($audit === []): ?>
        <p class="bo-mn-empty">Aucun changement enregistré pour l’instant.</p>
        <?php else: ?>
        <ol class="bo-mn-audit">
            <?php foreach ($audit as $a):
                $who = trim((string) ($a['member_name'] ?? ''));
                if ($who === '') {
                    $who = trim((string) ($a['member_callsign'] ?? ''));
                }
                if ($who === '') {
                    $who = 'Membre #' . (int) ($a['user_id'] ?? 0);
                }
                $old = trim((string) ($a['old_value'] ?? ''));
                $new = trim((string) ($a['new_value'] ?? ''));
                $actor = trim((string) ($a['actor_name'] ?? ''));
                $ts = strtotime((string) ($a['created_at'] ?? '')) ?: null;
            ?>
            <li>
                <div class="bo-mn-audit__line">
                    <strong><?= $h($who) ?></strong>
                    <span class="bo-mn-mono"><?= $old !== '' ? $h($old) : '—' ?> → <?= $new !== '' ? $h($new) : 'retiré' ?></span>
                </div>
                <div class="bo-mn-audit__meta"><?= $h(($sourceLabels[(string) ($a['source'] ?? '')] ?? 'Modification') . ($actor !== '' ? ' · par ' . $actor : '') . ($ts !== null ? ' · ' . date('d/m/Y H:i', $ts) : '')) ?></div>
                <?php
                $auditUid = (int) ($a['user_id'] ?? 0);
                $canRestore = $old !== '' && isset($byId[$auditUid]) && mb_strtolower((string) $byId[$auditUid]['tenant_member_number']) !== mb_strtolower($old);
                if ($canRestore): ?>
                <form method="post" action="<?= $h(url('back-office/organisation/matricules/retablir')) ?>" class="bo-mn-audit__restore" data-mn-confirm="Redonner le matricule <?= $h($old) ?> à <?= $h($who) ?> ?">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="audit_id" value="<?= (int) ($a['id'] ?? 0) ?>">
                    <button type="submit" class="ath-row-action"><?= $new === '' ? 'Réattribuer' : 'Rétablir' ?> <?= $h($old) ?></button>
                </form>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ol>
        <?php endif; ?>
    </section>
</div>

<dialog class="bo-mn-dialog" data-mn-dialog aria-labelledby="mn-dialog-title">
    <form method="post" action="<?= $h(url('back-office/organisation/matricules/membre')) ?>">
        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="user_id" value="" data-mn-dialog-id>
        <h2 class="bo-mn-panel__title" id="mn-dialog-title">Matricule de <span data-mn-dialog-name></span></h2>
        <div class="ath-field">
            <label class="ath-field__label" for="mn-dialog-value"><?= $h($label) ?></label>
            <input class="ath-field__input bo-mn-mono" id="mn-dialog-value" name="value" maxlength="100" autocomplete="off" spellcheck="false" data-mn-dialog-value>
            <span class="ath-field__help">Lettres, chiffres, - / _ et point. Laissez vide pour retirer le matricule.<?= $preview !== '' ? ' Prochain disponible : ' . $h($preview) . '.' : '' ?></span>
        </div>
        <div class="ath-form__actions">
            <button type="button" class="ath-btn" data-mn-dialog-close>Annuler</button>
            <button type="submit" class="ath-btn ath-btn--solid">Enregistrer</button>
        </div>
    </form>
</dialog>
</div>
<script>
(function () {
    var root = document.querySelector('[data-bo-mn]');
    if (!root) { return; }

    root.querySelectorAll('[data-mn-confirm]').forEach(function (f) {
        f.addEventListener('submit', function (e) { if (!window.confirm(f.getAttribute('data-mn-confirm'))) { e.preventDefault(); } });
    });

    /* Aperçu du modèle : reproduit TenantMemberNumberService::format(). */
    function fmt(pattern, prefix, n, tenant, year, month, unit, grade) {
        var out = pattern.split('{PREFIX}').join(prefix).split('{prefix}').join(prefix)
            .replace(/\{YEAR:2\}/gi, String(year).slice(-2)).replace(/\{YEAR\}/gi, String(year))
            .replace(/\{MONTH\}/gi, month).replace(/\{TENANT\}/gi, tenant)
            .replace(/\{UNIT\}/gi, unit || '').replace(/\{GRADE\}/gi, grade || '');
        var m = out.match(/\{NUMBER:(\d+)\}/i);
        if (m) {
            var pad = Math.max(1, Math.min(8, parseInt(m[1], 10)));
            var s = String(n);
            while (s.length < pad) { s = '0' + s; }
            out = out.replace(/\{NUMBER:\d+\}/gi, s);
        } else {
            out = out.replace(/\{NUMBER\}/gi, String(n));
        }
        return out.replace(/-{2,}/g, '-').replace(/\/{2,}/g, '/').replace(/^[-\/]+|[-\/]+$/g, '');
    }
    var form = root.querySelector('[data-mn-form]');
    if (form) {
        var q = function (k) { return form.querySelector('[data-mn-in="' + k + '"]'); };
        var patternIn = q('pattern');
        var toggle = form.querySelector('[data-mn-toggle]');
        var modeNames = { free: 'Saisie libre', automatic: 'Génération automatique', assisted: 'Saisie assistée' };
        var render = function () {
            var checked = form.querySelector('[data-mn-in="mode"]:checked');
            var mode = checked ? checked.value : 'free';
            var next = Math.max(1, parseInt(q('next').value, 10) || 1);
            var args = [patternIn.value || '', q('prefix').value.trim(), next, form.dataset.tenant || '', form.dataset.year, form.dataset.month, form.dataset.unit || '', form.dataset.grade || ''];
            form.querySelector('[data-mn-pv-ctx]').hidden = !/\{UNIT\}|\{GRADE\}/i.test(patternIn.value);
            form.querySelector('[data-mn-pv]').textContent = fmt.apply(null, args) || '—';
            var list = form.querySelector('[data-mn-pv-next]');
            list.textContent = '';
            for (var i = 1; i <= 2; i++) {
                var li = document.createElement('li');
                args[2] = next + i;
                li.textContent = fmt.apply(null, args);
                list.appendChild(li);
            }
            var lbl = form.querySelector('[name="label"]').value.trim();
            form.querySelector('[data-mn-pv-label]').textContent = lbl || "Matricule d'organisation";
            form.querySelector('[data-mn-pv-mode]').textContent = (modeNames[mode] || mode) + (toggle.checked ? '' : ' · désactivé');
            form.querySelector('[data-mn-nonumber]').hidden = mode === 'free' || /\{NUMBER(:\d+)?\}/i.test(patternIn.value);
            toggle.closest('.ath-toggle').className = 'ath-toggle ' + (toggle.checked ? 'is-on' : 'is-off');
        };
        form.querySelectorAll('[data-mn-token]').forEach(function (b) {
            b.addEventListener('click', function () {
                var tok = b.getAttribute('data-mn-token');
                var v = patternIn.value;
                var s = typeof patternIn.selectionStart === 'number' ? patternIn.selectionStart : v.length;
                var e = typeof patternIn.selectionEnd === 'number' ? patternIn.selectionEnd : v.length;
                patternIn.value = v.slice(0, s) + tok + v.slice(e);
                patternIn.focus();
                patternIn.setSelectionRange(s + tok.length, s + tok.length);
                render();
            });
        });
        var preset = form.querySelector('[data-mn-preset]');
        if (preset) { preset.addEventListener('change', function () { if (preset.value) { patternIn.value = preset.value; render(); } }); }
        form.addEventListener('input', render);
        form.addEventListener('change', render);
        render();
    }

    /* Registre : recherche (sans accents) et filtres. */
    var rows = Array.prototype.slice.call(root.querySelectorAll('[data-mn-row]'));
    var input = root.querySelector('[data-mn-q]');
    var chips = root.querySelectorAll('[data-mn-filter]');
    var none = root.querySelector('[data-mn-none]');
    var count = root.querySelector('[data-mn-count]');
    var state = 'all';
    var norm = function (s) { return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
    rows.forEach(function (r) { r._s = norm(r.getAttribute('data-search')); });
    function apply() {
        var term = norm(input ? input.value.trim() : '');
        var shown = 0;
        rows.forEach(function (r) {
            var ok = (state === 'all' || r.getAttribute('data-state') === state) && (term === '' || r._s.indexOf(term) !== -1);
            r.hidden = !ok;
            if (ok) { shown++; }
        });
        if (none) { none.hidden = shown !== 0; }
        if (count) { count.textContent = shown + ' membre' + (shown > 1 ? 's' : '') + (shown !== rows.length ? ' sur ' + rows.length : ''); }
    }
    function setFilter(f) {
        state = f;
        chips.forEach(function (o) { o.setAttribute('aria-pressed', o.getAttribute('data-mn-filter') === f ? 'true' : 'false'); });
        apply();
    }
    chips.forEach(function (b) { b.addEventListener('click', function () { setFilter(b.getAttribute('data-mn-filter')); }); });
    if (input) { input.addEventListener('input', apply); }
    root.querySelectorAll('[data-mn-show]').forEach(function (b) {
        b.addEventListener('click', function () {
            setFilter(b.getAttribute('data-mn-show'));
            var reg = document.getElementById('mn-reg-title');
            if (reg) { reg.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
        });
    });

    /* Modifier le matricule d’un membre. */
    var dlg = root.querySelector('[data-mn-dialog]');
    root.querySelectorAll('[data-mn-edit]').forEach(function (b) {
        b.addEventListener('click', function () {
            if (!dlg || typeof dlg.showModal !== 'function') { return; }
            dlg.querySelector('[data-mn-dialog-id]').value = b.getAttribute('data-mn-edit');
            dlg.querySelector('[data-mn-dialog-name]').textContent = b.getAttribute('data-mn-name');
            var v = dlg.querySelector('[data-mn-dialog-value]');
            v.value = b.getAttribute('data-mn-current') || '';
            dlg.showModal();
            v.focus();
            v.select();
        });
    });
    if (dlg) { dlg.querySelector('[data-mn-dialog-close]').addEventListener('click', function () { dlg.close(); }); }
})();
</script>
