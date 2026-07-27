<div id="panel-danger-zones" class="panel-tab">
  <h2 class="text-lg font-black uppercase tracking-tight mb-4">Zones de danger</h2>
  <div class="space-y-4">
    <p class="text-xs text-slate-500">Cliquez sur la carte pour placer le centre, puis saisissez le rayon (m) et créez.</p>
    <div>
      <label class="block text-xs font-bold uppercase text-slate-500 mb-1" for="dz-type">Type</label>
      <select id="dz-type" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
        <option value="NO_FIRE_AREA">Zone interdite de tir</option>
        <option value="NO_FLY_ZONE">Zone d’interdiction de vol</option>
        <option value="ARTY_DANGER_CLOSE">Danger artillerie (proximité)</option>
        <option value="MINEFIELD">Champ de mines</option>
        <option value="RESTRICTED_AREA">Zone réglementée</option>
        <option value="AA_THREAT_RING">Menace antiaérienne</option>
      </select>
    </div>
    <div>
      <label class="block text-xs font-bold uppercase text-slate-500 mb-1" for="dz-label">Nom de la zone</label>
      <input type="text" id="dz-label" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" placeholder="Ex. Zone nord du village" />
    </div>
    <div>
      <label class="block text-xs font-bold uppercase text-slate-500 mb-1" for="dz-radius">Rayon (m)</label>
      <input type="number" id="dz-radius" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" value="500" min="50" max="5000" />
    </div>
    <input type="hidden" id="dz-center-x" value="" />
    <input type="hidden" id="dz-center-y" value="" />
    <button type="button" id="dz-create" class="w-full px-3 py-2 rounded-lg bg-slate-800 text-white text-sm font-bold uppercase">Créer la zone</button>
    <div id="dz-list" class="space-y-2 max-h-48 overflow-y-auto"></div>
  </div>
</div>
