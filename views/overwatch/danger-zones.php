<div id="panel-danger-zones" class="panel-tab">
  <h2 class="text-lg font-black uppercase tracking-tight mb-4">Danger Zones</h2>
  <div class="space-y-4">
    <p class="text-xs text-slate-500">Cliquez sur la carte pour placer le centre, puis saisir le rayon (m) et créer.</p>
    <div>
      <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Type</label>
      <select id="dz-type" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
        <option value="NO_FIRE_AREA">NO FIRE AREA</option>
        <option value="NO_FLY_ZONE">NO FLY ZONE</option>
        <option value="ARTY_DANGER_CLOSE">ARTY DANGER CLOSE</option>
        <option value="MINEFIELD">MINEFIELD</option>
        <option value="RESTRICTED_AREA">RESTRICTED AREA</option>
        <option value="AA_THREAT_RING">AA THREAT RING</option>
      </select>
    </div>
    <div>
      <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Label</label>
      <input type="text" id="dz-label" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" placeholder="Nom de la zone" />
    </div>
    <div>
      <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Rayon (m)</label>
      <input type="number" id="dz-radius" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" value="500" min="50" max="5000" />
    </div>
    <input type="hidden" id="dz-center-x" value="" />
    <input type="hidden" id="dz-center-y" value="" />
    <button type="button" id="dz-create" class="w-full px-3 py-2 rounded-lg bg-slate-800 text-white text-sm font-bold uppercase">Créer la zone</button>
    <div id="dz-list" class="space-y-2 max-h-48 overflow-y-auto"></div>
  </div>
</div>
