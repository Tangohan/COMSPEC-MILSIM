<?php
declare(strict_types=1);

/**
 * Bloc admin « Visibilité » (dossier / édition).
 *
 * @var bool $canManageVisibility
 * @var array{visibility_level?: string, assignment_visibility?: string, anonymized_label?: string} $personnelVisibility
 * @var array<string, string> $visibilityLevelOptions
 * @var string|null $personnelVisibilityConsequence
 * @var string|null $personnelAssignmentVisibilityConsequence
 * @var string|null $visibilityPreviewBaseUrl
 * @var string|null $visibilityPreviewAs
 * @var int $visibilityTargetUserId
 * @var bool $visibilityStandaloneForm  Si true : formulaire POST dédié (AJAX). Sinon champs inclus dans le form parent.
 */
use App\Support\VisibilityLevel;

$canManageVisibility = !empty($canManageVisibility);
if (!$canManageVisibility) {
    return;
}

$personnelVisibility = is_array($personnelVisibility ?? null) ? $personnelVisibility : [];
$visibilityLevelOptions = is_array($visibilityLevelOptions ?? null) ? $visibilityLevelOptions : [
    VisibilityLevel::NORMAL => VisibilityLevel::label(VisibilityLevel::NORMAL),
    VisibilityLevel::ANONYMIZED => VisibilityLevel::label(VisibilityLevel::ANONYMIZED),
    VisibilityLevel::RESTRICTED => VisibilityLevel::label(VisibilityLevel::RESTRICTED),
    VisibilityLevel::HIDDEN => VisibilityLevel::label(VisibilityLevel::HIDDEN),
];
$curVis = VisibilityLevel::normalize((string) ($personnelVisibility['visibility_level'] ?? VisibilityLevel::NORMAL));
$curAssign = VisibilityLevel::normalize((string) ($personnelVisibility['assignment_visibility'] ?? VisibilityLevel::NORMAL));
$anonLabel = trim((string) ($personnelVisibility['anonymized_label'] ?? ''));
$personnelVisibilityConsequence = (string) ($personnelVisibilityConsequence ?? VisibilityLevel::consequence($curVis, 'personnel'));
$personnelAssignmentVisibilityConsequence = (string) ($personnelAssignmentVisibilityConsequence ?? VisibilityLevel::consequence($curAssign, 'personnel'));
$visibilityPreviewBaseUrl = trim((string) ($visibilityPreviewBaseUrl ?? ''));
$visibilityPreviewAs = isset($visibilityPreviewAs) ? (string) $visibilityPreviewAs : '';
$visibilityTargetUserId = (int) ($visibilityTargetUserId ?? 0);
$visibilityStandaloneForm = !empty($visibilityStandaloneForm);
$consequencesMap = [];
foreach (array_keys($visibilityLevelOptions) as $lvl) {
    $consequencesMap[$lvl] = VisibilityLevel::consequence((string) $lvl, 'personnel');
}
$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$ajaxUrl = $visibilityTargetUserId > 0 ? url('personnel/' . $visibilityTargetUserId . '/visibility') : '';
?>
<section
  id="edit-visibilite"
  class="scroll-mt-24 overflow-hidden rounded-2xl border border-slate-300/90 bg-white shadow-sm ring-1 ring-slate-900/[0.04]"
  data-personnel-visibility-admin
  data-consequences="<?= $h(json_encode($consequencesMap, JSON_UNESCAPED_UNICODE) ?: '{}') ?>"
  <?php if ($visibilityStandaloneForm && $ajaxUrl !== ''): ?>
  x-data="personnelVisibilityAdmin({
    url: <?= json_encode($ajaxUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) ?>,
    csrf: <?= json_encode(\App\Core\Csrf::token(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) ?>,
    visibility: <?= json_encode($curVis, JSON_UNESCAPED_UNICODE) ?>,
    assignment: <?= json_encode($curAssign, JSON_UNESCAPED_UNICODE) ?>,
    label: <?= json_encode($anonLabel, JSON_UNESCAPED_UNICODE) ?>,
    consequences: <?= json_encode($consequencesMap, JSON_UNESCAPED_UNICODE) ?>
  })"
  <?php endif; ?>
>
  <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-4 sm:px-6">
    <h2 class="text-base font-black tracking-tight text-slate-900">Visibilité</h2>
    <p class="mt-1 text-sm text-slate-600">Contrôle ce que les membres voient dans l’annuaire, l’ORBAT et la fiche publique.</p>
  </div>
  <div class="space-y-5 px-5 py-5 sm:px-6">
    <?php if ($visibilityStandaloneForm): ?>
    <form @submit.prevent="save()" class="space-y-5">
    <?php endif; ?>

    <div class="grid gap-4 md:grid-cols-2">
      <div>
        <label class="mb-1 block text-xs font-semibold text-slate-600" for="visibility_level">Visibilité du personnel</label>
        <select
          name="visibility_level"
          id="visibility_level"
          class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-400/20"
          <?php if ($visibilityStandaloneForm): ?>x-model="visibility" @change="syncConsequence()"<?php else: ?>data-vis-personnel-select<?php endif; ?>
        >
          <?php foreach ($visibilityLevelOptions as $value => $label): ?>
          <option value="<?= $h((string) $value) ?>" <?= $curVis === (string) $value ? 'selected' : '' ?>><?= $h((string) $label) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="mt-2 text-xs leading-relaxed text-slate-600" <?php if ($visibilityStandaloneForm): ?>x-text="personnelConsequence"<?php else: ?>data-vis-personnel-consequence<?php endif; ?>>
          <?= $h($personnelVisibilityConsequence) ?>
        </p>
      </div>
      <div>
        <label class="mb-1 block text-xs font-semibold text-slate-600" for="assignment_visibility">Visibilité de l’affectation</label>
        <select
          name="assignment_visibility"
          id="assignment_visibility"
          class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-400/20"
          <?php if ($visibilityStandaloneForm): ?>x-model="assignment" @change="syncConsequence()"<?php else: ?>data-vis-assignment-select<?php endif; ?>
        >
          <?php foreach ($visibilityLevelOptions as $value => $label): ?>
          <option value="<?= $h((string) $value) ?>" <?= $curAssign === (string) $value ? 'selected' : '' ?>><?= $h((string) $label) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="mt-2 text-xs leading-relaxed text-slate-600" <?php if ($visibilityStandaloneForm): ?>x-text="assignmentConsequence"<?php else: ?>data-vis-assignment-consequence<?php endif; ?>>
          <?= $h($personnelAssignmentVisibilityConsequence) ?>
        </p>
        <p class="mt-1 text-[11px] text-slate-500">Utile quand le personnel reste connu mais que l’unité d’affectation doit être masquée.</p>
      </div>
    </div>

    <div>
      <label class="mb-1 block text-xs font-semibold text-slate-600" for="anonymized_label">Libellé anonymisé (optionnel)</label>
      <input
        type="text"
        name="anonymized_label"
        id="anonymized_label"
        maxlength="120"
        value="<?= $h($anonLabel) ?>"
        placeholder="Ex. Opérateur section — poste occupé"
        class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-400/20"
        <?php if ($visibilityStandaloneForm): ?>x-model="label"<?php endif; ?>
      >
      <p class="mt-1 text-[11px] text-slate-500">Affiché à la place de l’identité lorsque le niveau est « Anonymisée ».</p>
    </div>

    <?php if ($visibilityStandaloneForm): ?>
    <div class="flex flex-wrap items-center gap-3">
      <button type="submit" class="inline-flex items-center rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800" :disabled="saving">
        <span x-show="!saving">Enregistrer la visibilité</span>
        <span x-cloak x-show="saving">Enregistrement…</span>
      </button>
      <p class="text-xs" :class="messageOk ? 'text-emerald-700' : 'text-rose-700'" x-text="message" x-show="message !== ''"></p>
    </div>
    </form>
    <?php else: ?>
    <div>
      <label class="mb-1 block text-xs font-semibold text-slate-600" for="visibility_change_reason">Motif du changement (optionnel)</label>
      <input type="text" name="visibility_change_reason" id="visibility_change_reason" maxlength="500" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" placeholder="Ex. Rotation confidentielle, besoin opérationnel…">
    </div>
    <?php endif; ?>

    <?php if ($visibilityPreviewBaseUrl !== ''): ?>
    <div class="rounded-xl border border-slate-200 bg-slate-50/70 px-4 py-3">
      <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Aperçu</p>
      <p class="mt-1 text-xs text-slate-600">Ouvre la fiche avec des droits réduits pour vérifier le rendu.</p>
      <div class="mt-3 flex flex-wrap gap-2">
        <?php
        $previewLinks = [
            'member' => 'Voir comme membre standard',
            'cadre' => 'Voir comme cadre',
            'command' => 'Voir comme commandement',
        ];
        foreach ($previewLinks as $key => $lab):
            $href = $visibilityPreviewBaseUrl . (str_contains($visibilityPreviewBaseUrl, '?') ? '&' : '?') . 'preview_as=' . rawurlencode($key);
        ?>
        <a href="<?= $h($href) ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 hover:bg-slate-100<?= $visibilityPreviewAs === $key ? ' ring-2 ring-slate-400' : '' ?>"><?= $h($lab) ?></a>
        <?php endforeach; ?>
        <?php if ($visibilityPreviewAs !== ''): ?>
        <a href="<?= $h($visibilityPreviewBaseUrl) ?>" class="inline-flex items-center rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-900 hover:bg-emerald-100">Quitter l’aperçu</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php if (!$visibilityStandaloneForm): ?>
<script>
(function () {
  var root = document.querySelector('[data-personnel-visibility-admin]');
  if (!root) return;
  var map = {};
  try { map = JSON.parse(root.getAttribute('data-consequences') || '{}') || {}; } catch (e) { map = {}; }
  function bind(selectSel, consSel) {
    var sel = root.querySelector(selectSel);
    var cons = root.querySelector(consSel);
    if (!sel || !cons) return;
    sel.addEventListener('change', function () {
      cons.textContent = map[sel.value] || '';
    });
  }
  bind('[data-vis-personnel-select]', '[data-vis-personnel-consequence]');
  bind('[data-vis-assignment-select]', '[data-vis-assignment-consequence]');
})();
</script>
<?php else: ?>
<script>
document.addEventListener('alpine:init', function () {
  if (window.Alpine && !window.__personnelVisibilityAdminRegistered) {
    window.__personnelVisibilityAdminRegistered = true;
    Alpine.data('personnelVisibilityAdmin', function (cfg) {
      return {
        url: cfg.url,
        csrf: cfg.csrf,
        visibility: cfg.visibility,
        assignment: cfg.assignment,
        label: cfg.label || '',
        consequences: cfg.consequences || {},
        personnelConsequence: (cfg.consequences && cfg.consequences[cfg.visibility]) || '',
        assignmentConsequence: (cfg.consequences && cfg.consequences[cfg.assignment]) || '',
        saving: false,
        message: '',
        messageOk: true,
        syncConsequence: function () {
          this.personnelConsequence = this.consequences[this.visibility] || '';
          this.assignmentConsequence = this.consequences[this.assignment] || '';
        },
        save: async function () {
          this.saving = true;
          this.message = '';
          try {
            var body = new FormData();
            body.append('_csrf_token', this.csrf);
            body.append('_ajax', '1');
            body.append('visibility_level', this.visibility);
            body.append('assignment_visibility', this.assignment);
            body.append('anonymized_label', this.label);
            var res = await fetch(this.url, {
              method: 'POST',
              headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
              body: body,
              credentials: 'same-origin'
            });
            var data = await res.json().catch(function () { return {}; });
            this.messageOk = !!(data && data.success);
            this.message = (data && data.message) ? data.message : (this.messageOk ? 'Enregistré.' : 'Échec de l’enregistrement.');
            if (data && data.consequence) this.personnelConsequence = data.consequence;
            if (data && data.assignment_consequence) this.assignmentConsequence = data.assignment_consequence;
          } catch (e) {
            this.messageOk = false;
            this.message = 'Erreur réseau.';
          } finally {
            this.saving = false;
          }
        }
      };
    });
  }
});
</script>
<?php endif; ?>
