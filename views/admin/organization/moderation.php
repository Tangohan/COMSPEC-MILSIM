<?php
declare(strict_types=1);

/**
 * Restrictions membres (niveau organisation) — charte ATHENA.
 *
 * L’en-tête de page est rendu par la coque back-office ; cette vue produit le bandeau
 * de périmètre, le formulaire de mesure et l’historique.
 *
 * @var list<array<string, mixed>> $actions
 * @var list<array<string, mixed>> $memberUsers
 * @var array<string, string> $moduleLabels
 */

$actions = is_array($actions ?? null) ? $actions : [];
$memberUsers = is_array($memberUsers ?? null) ? $memberUsers : [];
$moduleLabels = is_array($moduleLabels ?? null) ? $moduleLabels : [];

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$actionTypeLabel = static function (string $t): string {
    return match ($t) {
        'warn' => 'Avertissement',
        'mute' => 'Restriction d’activité',
        'suspend' => 'Suspension',
        'ban' => 'Exclusion',
        default => 'Autre',
    };
};

$modulesSummary = static function (?string $json): string {
    if ($json === null || $json === '') {
        return '—';
    }
    $d = json_decode($json, true);
    if (!is_array($d)) {
        return '—';
    }
    $mods = $d['modules_blocked'] ?? [];
    if (!is_array($mods) || $mods === []) {
        return '—';
    }
    $labels = \App\Services\Moderation\ModerationRestrictionsCatalog::moduleLabels();
    $parts = [];
    foreach ($mods as $k) {
        $k = (string) $k;
        $parts[] = $labels[$k] ?? $k;
    }

    return implode(', ', $parts);
};

$fmtDt = static function (mixed $raw): string {
    $s = trim((string) ($raw ?? ''));
    if ($s === '') {
        return '—';
    }
    $t = strtotime($s);

    return $t ? date('d/m/Y H:i', $t) : $s;
};

$revocable = ['mute', 'suspend', 'ban'];
$activeCount = 0;
$warnCount = 0;
$revokedCount = 0;
foreach ($actions as $a) {
    $type = (string) ($a['action_type'] ?? '');
    if (!empty($a['revoked_at'])) {
        $revokedCount++;
        continue;
    }
    if ($type === 'warn') {
        $warnCount++;
    } elseif (in_array($type, $revocable, true)) {
        $activeCount++;
    }
}
$total = count($actions);
$pctOf = static fn (int $n): string => $total > 0 ? (string) (int) round($n / $total * 100) . '%' : '0%';

$flashError = \App\Core\Session::getFlash('error');
$flashSuccess = \App\Core\Session::getFlash('success');
$flashWarning = \App\Core\Session::getFlash('warning');
?>
<div class="ath-note">
    <p class="ath-note__title">Périmètre : votre communauté</p>
    <p class="ath-note__text">
        Vous limitez ici l’accès du membre à certains <strong>domaines du portail</strong> de votre communauté
        (formations, documents, candidatures…). Les <strong>blocages e-mail et réseau</strong> déclenchés par la
        modération automatique du portail recrutement se lèvent dans
        <a href="<?= $h(url('back-office/security-indicators')) ?>">Blocages portail &amp; sécurité</a>.
        Les mesures sur le compte, le forum, la messagerie ou les listes globales du site restent du ressort
        de l’administration de la plateforme.
    </p>
</div>

<?php if ($flashError): ?>
<p class="ath-flash ath-flash--err" role="alert"><?= $h((string) $flashError) ?></p>
<?php endif; ?>
<?php if ($flashSuccess): ?>
<p class="ath-flash ath-flash--ok" role="status"><?= $h((string) $flashSuccess) ?></p>
<?php endif; ?>
<?php if ($flashWarning): ?>
<p class="ath-flash" role="status" style="background:#fdf3e2;border-color:#f2ddb4;color:#8a5a06;"><?= $h((string) $flashWarning) ?></p>
<?php endif; ?>

<?php
$athKpis = [
    [
        'label' => 'RESTRICTIONS EN COURS',
        'value' => (string) $activeCount,
        'delta' => '',
        'tone' => $activeCount === 0 ? '#0b8a5c' : '#c72e2e',
        'pct' => $pctOf($activeCount),
        'note' => 'accès limité en ce moment',
    ],
    [
        'label' => 'AVERTISSEMENTS',
        'value' => (string) $warnCount,
        'delta' => '',
        'tone' => $warnCount === 0 ? '#0b8a5c' : '#c98a12',
        'pct' => $pctOf($warnCount),
        'note' => 'conservés au dossier',
    ],
    [
        'label' => 'MESURES LEVÉES',
        'value' => (string) $revokedCount,
        'delta' => '',
        'tone' => '#64748b',
        'pct' => $pctOf($revokedCount),
        'note' => 'historique conservé',
    ],
    [
        'label' => 'MEMBRES CONCERNABLES',
        'value' => (string) count($memberUsers),
        'delta' => '',
        'tone' => '#1e4f80',
        'pct' => '100%',
        'note' => 'comptes de la communauté',
    ],
];
require base_path('views/partials/ath_kpis.php');
?>

<form method="post" action="<?= $h(url('back-office/moderation/apply')) ?>" class="ath-form ath-rise">
    <div class="ath-form__head">
        <span class="ath-form__title">Nouvelle mesure</span>
        <span class="ath-form__hint">Un avertissement reste au dossier sans limiter l’accès ; une restriction ferme les domaines cochés.</span>
    </div>
    <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
    <div class="ath-form__grid">
        <label class="ath-field">
            <span class="ath-field__label">Membre concerné</span>
            <select name="target_user_id" required class="ath-field__select">
                <option value="">— Choisir —</option>
                <?php foreach ($memberUsers as $u): ?>
                <option value="<?= (int) ($u['id'] ?? 0) ?>"><?= $h((string) ($u['email'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="ath-field">
            <span class="ath-field__label">Type de mesure</span>
            <select name="action_type" id="mod_action_type" class="ath-field__select">
                <option value="warn">Avertissement (conservé au dossier)</option>
                <option value="restriction">Restriction d’accès à des domaines</option>
            </select>
        </label>
    </div>

    <div id="mod_duration_wrap" hidden style="margin-top:14px;">
        <span class="ath-field__label">Durée de la restriction</span>
        <div class="ath-check-grid" style="margin-top:7px;">
            <label class="ath-check">
                <input type="radio" name="duration_mode" value="permanent" checked>
                <span>Sans date de fin</span>
            </label>
            <label class="ath-check">
                <input type="radio" name="duration_mode" value="temporary">
                <span>Temporaire</span>
            </label>
            <label class="ath-field">
                <span class="ath-field__label">Nombre de jours</span>
                <input type="number" name="duration_days" value="7" min="1" max="3650" class="ath-field__input">
                <span class="ath-field__help">Utilisé uniquement si la mesure est temporaire.</span>
            </label>
        </div>
    </div>

    <div id="mod_scope_wrap" hidden style="margin-top:14px;">
        <span class="ath-field__label">Domaines concernés</span>
        <p class="ath-field__help" style="margin:5px 0 8px;">Cochez les parties du portail auxquelles le membre ne doit plus accéder dans votre communauté.</p>
        <div class="ath-check-grid">
            <?php foreach ($moduleLabels as $key => $label): ?>
            <label class="ath-check">
                <input type="checkbox" name="modules_blocked[]" value="<?= $h((string) $key) ?>">
                <span><?= $h((string) $label) ?></span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="ath-form__grid ath-form__grid--wide" style="margin-top:14px;">
        <label class="ath-field">
            <span class="ath-field__label">Motif</span>
            <textarea name="reason" rows="3" class="ath-field__textarea"></textarea>
            <span class="ath-field__help">Visible par les personnes habilitées, sur la fiche du membre.</span>
        </label>
    </div>

    <div class="ath-form__actions">
        <button type="submit" class="ath-btn ath-btn--solid">Enregistrer la mesure</button>
    </div>
</form>

<?php
$csrf = \App\Core\Csrf::token();
$revokeUrl = url('back-office/moderation/revoke');

$athTableTitle = 'Historique récent';
$athTableCount = $total;
$athTableCols = ['DATE|m', 'MEMBRE|m', 'MESURE', 'DOMAINES', 'DÉCIDÉE PAR|m', 'ÉTAT|b'];
$athTableRows = [];
$athTableRowActions = [];
foreach ($actions as $a) {
    $type = (string) ($a['action_type'] ?? '');
    $isRevoked = !empty($a['revoked_at']);
    $restrictions = isset($a['restrictions_json']) && is_string($a['restrictions_json']) ? $a['restrictions_json'] : null;

    if ($isRevoked) {
        $state = 'Levée';
    } elseif (in_array($type, $revocable, true)) {
        $state = 'Actif';
    } else {
        $state = 'En attente';
    }

    $athTableRows[] = [
        $fmtDt($a['created_at'] ?? null),
        (string) ($a['target_email'] ?? '—'),
        $actionTypeLabel($type),
        $modulesSummary($restrictions),
        (string) ($a['actor_email'] ?? '—'),
        $state,
    ];

    // Balisage d’action construit ici, échappements compris (cf. contrat de ath_table.php).
    if (!$isRevoked && in_array($type, $revocable, true)) {
        $athTableRowActions[] = '<form method="post" action="' . $h($revokeUrl) . '"'
            . ' onsubmit="return confirm(\'Lever cette mesure ? Le membre retrouve immédiatement l\\\'accès aux domaines concernés.\');">'
            . '<input type="hidden" name="_csrf_token" value="' . $h($csrf) . '">'
            . '<input type="hidden" name="action_id" value="' . (int) ($a['id'] ?? 0) . '">'
            . '<button type="submit" class="ath-row-action ath-row-action--accent">Lever</button>'
            . '</form>';
    } elseif ($isRevoked) {
        $athTableRowActions[] = '<button type="button" class="ath-row-action" disabled>Levée</button>';
    } else {
        $athTableRowActions[] = null;
    }
}
$athTableActionsLabel = 'LEVÉE';
$athTableFilters = [];
$athTableMinWidth = '1240px';
$athTableShowCheckbox = false;
$athTableExportUrl = null;
$athTablePager = null;
$athTableRowHrefs = null;
$athTableFoot = $actions === []
    ? 'Aucune mesure enregistrée pour cette communauté.'
    : 'Les mesures levées restent visibles : l’historique n’est jamais effacé.';
require base_path('views/partials/ath_table.php');
?>

<script>
/* Le type de mesure commande l’affichage de la durée et des domaines : un avertissement
   n’en a pas besoin. `hidden` plutôt qu’un style inline, pour rester accessible. */
(function () {
  var select = document.getElementById('mod_action_type');
  var duration = document.getElementById('mod_duration_wrap');
  var scope = document.getElementById('mod_scope_wrap');
  if (!select || !duration || !scope) return;
  var sync = function () {
    var isWarning = select.value === 'warn';
    duration.hidden = isWarning;
    scope.hidden = isWarning;
  };
  select.addEventListener('change', sync);
  sync();
})();
</script>
