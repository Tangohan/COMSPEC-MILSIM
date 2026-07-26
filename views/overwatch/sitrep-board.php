<div id="panel-sitrep" class="panel-tab">
  <h2 class="text-lg font-black uppercase tracking-tight mb-4">Tableau de situation</h2>
  <p class="text-xs text-slate-500 mb-3">Signalements fusionnés : plusieurs sources proches se confirment automatiquement.</p>
  <div class="space-y-3">
    <div id="sitrep-list" class="space-y-2 max-h-64 overflow-y-auto text-sm">
      <p class="text-slate-500 text-xs">Chargement…</p>
    </div>
    <details class="text-xs text-slate-600 border border-slate-200 rounded-lg p-2 bg-slate-50">
      <summary class="cursor-pointer font-bold text-slate-700">Qui génère ? Comment ?</summary>
      <ul class="mt-2 space-y-1 list-disc list-inside">
        <li><strong>Génération :</strong> les rapports sont créés ou fusionnés lorsqu’un client de jeu ou le poste de commandement envoie une situation (type de cible, position, indicatif source).</li>
        <li><strong>Depuis Arma :</strong> le mod peut transmettre ces éléments via la liaison prévue.</li>
        <li><strong>Fusion :</strong> si un rapport du même type existe déjà à proximité (moins de 100 m dans les dernières minutes), le nouveau message est fusionné.</li>
        <li><strong>Niveau de confiance :</strong> une seule source donne un avis provisoire ; plusieurs sources rapprochées le renforcent jusqu’à un niveau confirmé.</li>
      </ul>
    </details>
    <div class="border-t border-slate-200 pt-2">
      <p class="text-xs font-bold text-slate-600 mb-1">Nouveau signalement</p>
      <div class="flex flex-wrap gap-2 items-end">
        <label class="flex flex-col gap-0.5">
          <span class="text-xs text-slate-500">Type de cible</span>
          <select id="sitrep-test-target" class="border border-slate-300 rounded px-2 py-1 text-sm w-36">
            <option value="INFANTRY">Infanterie</option>
            <option value="VEHICLE">Véhicule</option>
            <option value="ARMOR">Blindé</option>
            <option value="AIR_DEFENSE">Défense antiaérienne</option>
            <option value="UNKNOWN">Non identifié</option>
          </select>
        </label>
        <label class="flex flex-col gap-0.5">
          <span class="text-xs text-slate-500">Est (X)</span>
          <input type="number" id="sitrep-test-x" value="15000" class="border border-slate-300 rounded px-2 py-1 text-sm w-20" />
        </label>
        <label class="flex flex-col gap-0.5">
          <span class="text-xs text-slate-500">Nord (Y)</span>
          <input type="number" id="sitrep-test-y" value="15000" class="border border-slate-300 rounded px-2 py-1 text-sm w-20" />
        </label>
        <label class="flex flex-col gap-0.5">
          <span class="text-xs text-slate-500">Indicatif source</span>
          <input type="text" id="sitrep-test-source" value="TOC" class="border border-slate-300 rounded px-2 py-1 text-sm w-24" placeholder="TOC" />
        </label>
        <button type="button" id="sitrep-test-submit" class="px-2 py-1 rounded bg-slate-800 text-white text-xs font-bold uppercase">Publier</button>
      </div>
    </div>
  </div>
</div>
