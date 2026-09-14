<?php
declare(strict_types=1);

/** @var list<array<string, mixed>> $detectionRules */
/** @var bool $detectionReady */
/** @var array<string, string> $detectionModes */
/** @var array<string, string> $detectionTypes */
/** @var list<int> $detectionRadii */
/** @var string $detectionFormAction */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rules = is_array($detectionRules ?? null) ? $detectionRules : [];
$ready = !empty($detectionReady);
$modes = is_array($detectionModes ?? null) ? $detectionModes : [];
$types = is_array($detectionTypes ?? null) ? $detectionTypes : [];
$radii = is_array($detectionRadii ?? null) ? $detectionRadii : [10, 20, 50, 100, 200, 500];
$formAction = (string) ($detectionFormAction ?? url('back-office/atak/detection-marqueurs'));
$overwatchUrl = url('-ATAK-OVERWATCH-Beta');

$sentence = static function (array $rule) use ($modes, $types): string {
    $mode = (string) ($rule['match_mode'] ?? '');
    $value = (string) ($rule['match_value'] ?? '');
    $how = $modes[$mode] ?? 'Le libellé commence par';
    if ($mode === 'marker_type') {
        $value = $types[$value] ?? $value;
    }
    $bits = [$how . ' « ' . $value . ' »'];
    $bits[] = 'rayon ' . (int) ($rule['radius_m'] ?? 20) . ' m';
    if (!empty($rule['confirm_arrival'])) {
        $bits[] = 'confirmation à l’arrivée d’un téléphone';
    }
    if (!empty($rule['notify_atak'])) {
        $bits[] = 'alerte sur les téléphones';
    }

    return implode(' · ', $bits);
};
?>
<div class="min-h-0 flex-1 bg-slate-50">
  <div class="max-w-[1040px] mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10 space-y-8">
    <header class="relative overflow-hidden rounded-2xl border border-blue-200/80 bg-gradient-to-br from-blue-50/90 via-white to-slate-50 shadow-sm">
      <div class="relative px-5 sm:px-8 py-7">
        <p class="inline-flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.2em] text-blue-900/80 mb-2">
          <span class="h-px w-6 bg-blue-400" aria-hidden="true"></span>
          Poste · Marqueurs en jeu
        </p>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Détection des marqueurs</h1>
        <p class="mt-2 text-sm text-slate-600 max-w-3xl leading-relaxed">
          Vous décrivez quels marqueurs posés dans Arma 3 doivent être suivis. Dès qu’un opérateur les pose, ils apparaissent au poste.
          Si vous le demandez, le point est confirmé lorsqu’un téléphone ATAK entre dans le rayon, et les opérateurs sont prévenus sur leur écran.
        </p>
        <div class="mt-5 flex flex-wrap gap-2">
          <a href="<?= $h(url('back-office/atak')) ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50">Poste de situation</a>
          <a href="<?= $h($overwatchUrl) ?>" class="inline-flex items-center rounded-lg border border-blue-200 bg-white px-4 py-2 text-sm font-semibold text-blue-950 shadow-sm hover:bg-blue-50/80">Overwatch Beta</a>
        </div>
      </div>
    </header>

    <?php if (!$ready): ?>
      <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">La détection des marqueurs n’est pas encore disponible sur ce serveur. Les points d’objectif PO continuent de fonctionner comme avant.</p>
    <?php else: ?>
      <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/80">
          <h2 class="text-sm font-black text-slate-900 tracking-tight">Nouvelle règle</h2>
          <p class="mt-1 text-xs text-slate-600">Choisissez comment reconnaître le marqueur, le rayon, puis ce qui doit se passer. Les listes évitent de tout saisir à la main.</p>
        </div>
        <form method="post" action="<?= $h($formAction) ?>" class="px-5 py-5 space-y-4">
          <input type="hidden" name="detection_action" value="create">
          <?= \App\Core\Csrf::field() ?>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <label class="block text-sm font-semibold text-slate-700">Nom de la règle
              <input name="label" required maxlength="80" placeholder="Extraction, danger, ralliement…" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
            </label>
            <label class="block text-sm font-semibold text-slate-700">Comment le reconnaître
              <select name="match_mode" id="ow-detect-mode" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                <?php foreach ($modes as $key => $title): ?>
                  <option value="<?= $h($key) ?>"><?= $h($title) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="block text-sm font-semibold text-slate-700" id="ow-detect-value-wrap">Texte du libellé
              <input name="match_value" maxlength="64" placeholder="EXFIL, LZ, DANGER…" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
            </label>
            <label class="block text-sm font-semibold text-slate-700 hidden" id="ow-detect-type-wrap">Symbole
              <select name="match_type" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                <?php foreach ($types as $key => $title): ?>
                  <option value="<?= $h($key) ?>"><?= $h($title) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="block text-sm font-semibold text-slate-700">Rayon
              <select name="radius_m" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                <?php foreach ($radii as $m): ?>
                  <option value="<?= (int) $m ?>" <?= (int) $m === 20 ? 'selected' : '' ?>><?= (int) $m ?> mètres</option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <div class="space-y-2 text-sm text-slate-800">
            <label class="flex items-start gap-2"><input type="checkbox" name="confirm_arrival" value="1" checked class="mt-1"> Confirmer le point lorsqu’un téléphone ATAK entre dans le rayon</label>
            <label class="flex items-start gap-2"><input type="checkbox" name="notify_web" value="1" checked class="mt-1"> Inscrire le point au journal du poste</label>
            <label class="flex items-start gap-2"><input type="checkbox" name="notify_atak" value="1" class="mt-1"> Prévenir les opérateurs sur leur téléphone</label>
          </div>
          <button type="submit" class="inline-flex items-center rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Créer la règle</button>
        </form>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/80">
          <h2 class="text-sm font-black text-slate-900 tracking-tight">Règles de la communauté</h2>
        </div>
        <?php if ($rules === []): ?>
          <p class="px-5 py-6 text-sm text-slate-600">Aucune règle pour le moment. Les points d’objectif libellés PO restent suivis automatiquement, comme aujourd’hui.</p>
        <?php else: ?>
          <ul class="divide-y divide-slate-100">
            <?php foreach ($rules as $rule): ?>
              <li class="px-5 py-4">
                <form method="post" action="<?= $h($formAction) ?>" class="space-y-3">
                  <?= \App\Core\Csrf::field() ?>
                  <input type="hidden" name="rule_id" value="<?= (int) ($rule['id'] ?? 0) ?>">
                  <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <p class="text-sm font-black text-slate-900"><?= $h((string) ($rule['label'] ?? 'Règle')) ?> <?= empty($rule['is_active']) ? '<span class="text-slate-500 font-semibold">— en pause</span>' : '' ?></p>
                      <p class="mt-1 text-xs text-slate-600"><?= $h($sentence($rule)) ?></p>
                    </div>
                    <label class="text-xs font-semibold text-slate-700 flex items-center gap-2"><input type="checkbox" name="is_active" value="1" <?= !empty($rule['is_active']) ? 'checked' : '' ?>> Règle active</label>
                  </div>
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <label class="block text-xs font-semibold text-slate-700">Nom
                      <input name="label" required maxlength="80" value="<?= $h((string) ($rule['label'] ?? '')) ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </label>
                    <label class="block text-xs font-semibold text-slate-700">Reconnaissance
                      <select name="match_mode" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <?php foreach ($modes as $key => $title): ?>
                          <option value="<?= $h($key) ?>" <?= ($rule['match_mode'] ?? '') === $key ? 'selected' : '' ?>><?= $h($title) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <?php if (($rule['match_mode'] ?? '') === 'marker_type'): ?>
                      <label class="block text-xs font-semibold text-slate-700">Symbole
                        <select name="match_type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                          <?php foreach ($types as $key => $title): ?>
                            <option value="<?= $h($key) ?>" <?= ($rule['match_value'] ?? '') === $key ? 'selected' : '' ?>><?= $h($title) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </label>
                    <?php else: ?>
                      <label class="block text-xs font-semibold text-slate-700">Texte
                        <input name="match_value" maxlength="64" value="<?= $h((string) ($rule['match_value'] ?? '')) ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                      </label>
                    <?php endif; ?>
                    <label class="block text-xs font-semibold text-slate-700">Rayon
                      <select name="radius_m" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <?php foreach ($radii as $m): ?>
                          <option value="<?= (int) $m ?>" <?= (int) ($rule['radius_m'] ?? 20) === (int) $m ? 'selected' : '' ?>><?= (int) $m ?> mètres</option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                  </div>
                  <div class="flex flex-wrap gap-4 text-sm text-slate-800">
                    <label class="flex items-center gap-2"><input type="checkbox" name="confirm_arrival" value="1" <?= !empty($rule['confirm_arrival']) ? 'checked' : '' ?>> Confirmer à l’arrivée</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="notify_web" value="1" <?= !empty($rule['notify_web']) ? 'checked' : '' ?>> Journal du poste</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="notify_atak" value="1" <?= !empty($rule['notify_atak']) ? 'checked' : '' ?>> Prévenir les téléphones</label>
                  </div>
                  <div class="flex flex-wrap gap-2">
                    <button type="submit" name="detection_action" value="update" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 hover:bg-slate-50">Enregistrer</button>
                    <button type="submit" name="detection_action" value="delete" class="inline-flex items-center rounded-lg border border-rose-200 bg-white px-3 py-1.5 text-xs font-semibold text-rose-800 hover:bg-rose-50" onclick="return confirm('Retirer cette règle ? Les marqueurs déjà posés restent sur la carte.');">Retirer</button>
                  </div>
                </form>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </div>
</div>
<script>
(function () {
  var mode = document.getElementById('ow-detect-mode');
  var valueWrap = document.getElementById('ow-detect-value-wrap');
  var typeWrap = document.getElementById('ow-detect-type-wrap');
  if (!mode || !valueWrap || !typeWrap) return;
  function sync() {
    var type = mode.value === 'marker_type';
    typeWrap.classList.toggle('hidden', !type);
    valueWrap.classList.toggle('hidden', type);
  }
  mode.addEventListener('change', sync);
  sync();
})();
</script>
