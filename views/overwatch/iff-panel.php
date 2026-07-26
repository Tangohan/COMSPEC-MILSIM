<div id="panel-iff" class="panel-tab">
  <h2 class="text-lg font-black uppercase tracking-tight mb-4">Identification (IFF)</h2>
  <p class="text-xs text-slate-500 mb-3">Défi / réponse pour confirmer qu’une unité est amie. Le poste de commandement publie un défi ; les unités répondent avec le code convenu.</p>
  <div class="space-y-4">
    <div id="iff-current" class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm">
      <p class="text-xs font-bold uppercase text-slate-500">Défi courant</p>
      <p id="iff-challenge-code" class="font-mono font-bold text-lg">—</p>
      <p id="iff-valid-until" class="text-xs text-slate-500">Aucun défi actif.</p>
    </div>
    <div class="border border-slate-200 rounded-lg p-2 bg-white space-y-2">
      <p class="text-xs font-bold text-slate-600">Publier un défi</p>
      <div class="flex flex-wrap gap-2 items-end">
        <label class="flex flex-col gap-0.5">
          <span class="text-xs text-slate-500">Code (facultatif)</span>
          <input type="text" id="iff-new-code" maxlength="32" class="border border-slate-300 rounded px-2 py-1 text-sm w-28" placeholder="Auto" autocomplete="off" />
        </label>
        <label class="flex flex-col gap-0.5">
          <span class="text-xs text-slate-500">Durée</span>
          <select id="iff-valid-minutes" class="border border-slate-300 rounded px-2 py-1 text-sm">
            <option value="15">15 min</option>
            <option value="30" selected>30 min</option>
            <option value="60">1 h</option>
            <option value="120">2 h</option>
          </select>
        </label>
        <button type="button" id="iff-generate" class="px-2 py-1 rounded bg-slate-800 text-white text-xs font-bold uppercase">Publier</button>
      </div>
      <p id="iff-feedback" class="text-xs text-slate-500 min-h-[1rem]" role="status"></p>
    </div>
    <div id="iff-assets-list" class="space-y-2 max-h-48 overflow-y-auto text-sm">
      <p class="text-slate-500 text-xs">Chargement…</p>
    </div>
  </div>
</div>
