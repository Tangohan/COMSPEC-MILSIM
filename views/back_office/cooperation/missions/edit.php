<?php
declare(strict_types=1);

$m = $interteamMission ?? [];
$csrf = $csrfToken ?? \App\Core\Csrf::token();
$sid = (int) ($m['id'] ?? 0);
$choicesTypo = $cooperationTypologyChoices ?? [];
$choicesPrio = $cooperationPriorityChoices ?? [];
$fieldsOn = !empty($interteamProposalFieldsEnabled);
$curTypo = (string) ($m['cooperation_typology'] ?? '');
$curPrio = (string) ($m['cooperation_priority'] ?? 'routine');
$deadlineVal = '';
$rawDl = $m['proposal_deadline_at'] ?? null;
if ($rawDl) {
    $deadlineVal = date('Y-m-d\TH:i', strtotime((string) $rawDl));
}
$suspLines = [];
$rawSusp = $m['suspensive_conditions_json'] ?? null;
if (is_string($rawSusp) && trim($rawSusp) !== '') {
    $dec = json_decode($rawSusp, true);
    if (is_array($dec)) {
        foreach ($dec as $ln) {
            if (is_string($ln) && trim($ln) !== '') {
                $suspLines[] = $ln;
            }
        }
    }
}
$suspText = implode("\n", $suspLines);
?>
<div class="max-w-4xl mx-auto px-6 py-10 space-y-8">
    <div>
        <a href="<?= htmlspecialchars(cooperation_mission_show_url($sid), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">← Synthèse</a>
        <?php require base_path('views/back_office/cooperation/missions/_nav.php'); ?>
        <h1 class="mt-4 text-2xl font-black text-slate-900">Proposition</h1>
        <p class="mt-2 text-sm text-slate-600">Définissez l’objet et le cadrage de la coopération. Les unités partenaires voient ces éléments sur la synthèse.</p>
    </div>

    <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/proposal'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <div>
            <label for="coop_title" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Titre de la coopération</label>
            <input type="text" id="coop_title" name="title" required minlength="3" maxlength="255" value="<?= htmlspecialchars((string) ($m['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </div>
        <?php if ($fieldsOn): ?>
        <div>
            <label for="coop_typology" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Typologie</label>
            <select id="coop_typology" name="cooperation_typology" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">— Non précisé —</option>
                <?php foreach ($choicesTypo as $val => $label): ?>
                <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>"<?= $curTypo === $val ? ' selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="coop_priority" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Priorité</label>
            <select id="coop_priority" name="cooperation_priority" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <?php foreach ($choicesPrio as $val => $label): ?>
                <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>"<?= $curPrio === $val ? ' selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="coop_deadline" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Date limite de réponse souhaitée (facultatif)</label>
            <input type="datetime-local" id="coop_deadline" name="proposal_deadline_at" value="<?= htmlspecialchars($deadlineVal, ENT_QUOTES, 'UTF-8') ?>" class="w-full max-w-md rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </div>
        <div>
            <label for="coop_suspensive" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Conditions suspensives (une par ligne)</label>
            <textarea id="coop_suspensive" name="suspensive_conditions" rows="4" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Ex. : activation après validation commandement…"><?= htmlspecialchars($suspText, ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
        <?php else: ?>
        <p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">Les champs typologie, priorité et échéance seront disponibles après la prochaine mise à jour de la base (migration coopération).</p>
        <?php endif; ?>
        <button type="submit" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Enregistrer</button>
    </form>
</div>
