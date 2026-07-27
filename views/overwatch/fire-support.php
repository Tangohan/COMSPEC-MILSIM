<div id="panel-fire-support" class="panel-tab active">
  <h2 class="text-lg font-black uppercase tracking-tight mb-4">Calculateur d’appui-feu</h2>
  <div class="space-y-4">
    <div>
      <label class="block text-xs font-bold uppercase text-slate-500 mb-1" for="fire-support-unit">Batterie</label>
      <select id="fire-support-unit" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
        <option value="">— Saisie manuelle (position) —</option>
      </select>
    </div>
    <div>
      <label class="block text-xs font-bold uppercase text-slate-500 mb-1" for="fire-support-ammo">Munition</label>
      <select id="fire-support-ammo" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
        <option value="HE">Explosive</option>
        <option value="SMOKE">Fumigène</option>
        <option value="ILLUM">Éclairante</option>
      </select>
    </div>
    <p class="text-xs text-slate-500">Cliquez sur la carte pour définir la cible.</p>
    <div id="fire-support-solution" class="hidden rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-2">
      <p class="text-xs font-bold uppercase text-slate-500">Solution de tir</p>
      <div class="grid grid-cols-2 gap-2 text-sm">
        <span class="text-slate-600">Portée</span><span id="fs-distance" class="font-mono font-bold">—</span>
        <span class="text-slate-600">Azimut °</span><span id="fs-azimuth-deg" class="font-mono font-bold">—</span>
        <span class="text-slate-600">Azimut mils</span><span id="fs-azimuth-mils" class="font-mono font-bold">—</span>
        <span class="text-slate-600">Charge</span><span id="fs-charge" class="font-mono font-bold">—</span>
        <span class="text-slate-600">Élévation mils</span><span id="fs-elevation" class="font-mono font-bold">—</span>
        <span class="text-slate-600">Temps de vol</span><span id="fs-tof" class="font-mono font-bold">—</span>
      </div>
      <div class="flex flex-wrap gap-2 mt-3">
        <button type="button" id="fire-support-transmit" class="px-2 py-1.5 rounded-lg bg-slate-800 text-white text-xs font-bold uppercase">Émettre</button>
        <button type="button" id="fire-support-save" class="px-3 py-2 rounded-lg border border-slate-300 text-slate-700 text-xs font-bold uppercase">Enregistrer la mission de feu</button>
        <button type="button" id="fire-support-marker" class="px-3 py-2 rounded-lg border border-slate-300 text-slate-700 text-xs font-bold uppercase">Placer un marqueur de cible</button>
      </div>
    </div>
    <div id="fire-support-error" class="hidden text-sm text-red-600"></div>
  </div>
</div>
