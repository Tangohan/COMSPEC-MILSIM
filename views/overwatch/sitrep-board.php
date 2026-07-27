<div id="panel-sitrep" class="panel-tab">
  <h2 class="text-lg font-black uppercase tracking-tight mb-4">Tableau de situation</h2>
  <p class="text-xs text-slate-500 mb-3">Signalements terrain fusionnés : plusieurs sources proches se confirment automatiquement. Cliquez un signalement pour le centrer sur la carte.</p>
  <div class="space-y-3">
    <div id="sitrep-list" class="space-y-2 max-h-64 overflow-y-auto text-sm">
      <p class="text-slate-500 text-xs">Chargement…</p>
    </div>
    <details class="text-xs text-slate-600 border border-slate-200 rounded-lg p-2 bg-slate-50">
      <summary class="cursor-pointer font-bold text-slate-700">Comment ça fonctionne</summary>
      <ul class="mt-2 space-y-1 list-disc list-inside">
        <li><strong>Origine :</strong> les signalements viennent du terrain (Arma) ou du poste de commandement.</li>
        <li><strong>Fusion :</strong> un même type de cible à proximité (moins de 100 m, dernières minutes) est regroupé.</li>
        <li><strong>Confiance :</strong> une seule source = provisoire ; plusieurs sources rapprochées = corroboré puis confirmé.</li>
      </ul>
    </details>
    <div class="border-t border-slate-200 pt-2">
      <p class="text-xs font-bold text-slate-600 mb-1">Nouveau signalement (poste de commandement)</p>
      <div class="flex flex-wrap gap-2 items-end">
        <label class="flex flex-col gap-0.5">
          <span class="text-xs text-slate-500">Type de cible</span>
          <select id="sitrep-ops-target" class="border border-slate-300 rounded px-2 py-1 text-sm w-36">
            <option value="INFANTRY">Infanterie</option>
            <option value="VEHICLE">Véhicule</option>
            <option value="ARMOR">Blindé</option>
            <option value="AIR_DEFENSE">Défense antiaérienne</option>
            <option value="UNKNOWN">Non identifié</option>
          </select>
        </label>
        <label class="flex flex-col gap-0.5">
          <span class="text-xs text-slate-500">Est</span>
          <input type="number" id="sitrep-ops-x" value="" placeholder="Position" class="border border-slate-300 rounded px-2 py-1 text-sm w-24" />
        </label>
        <label class="flex flex-col gap-0.5">
          <span class="text-xs text-slate-500">Nord</span>
          <input type="number" id="sitrep-ops-y" value="" placeholder="Position" class="border border-slate-300 rounded px-2 py-1 text-sm w-24" />
        </label>
        <label class="flex flex-col gap-0.5">
          <span class="text-xs text-slate-500">Indicatif source</span>
          <input type="text" id="sitrep-ops-source" value="PC" class="border border-slate-300 rounded px-2 py-1 text-sm w-24" placeholder="PC" />
        </label>
        <button type="button" id="sitrep-ops-submit" class="px-2 py-1 rounded bg-slate-800 text-white text-xs font-bold uppercase">Publier</button>
      </div>
      <p class="text-[11px] text-slate-500 mt-1">Indiquez les coordonnées terrain (Est / Nord). Les signalements issus du jeu alimentent ce tableau en priorité.</p>
    </div>
  </div>
</div>
