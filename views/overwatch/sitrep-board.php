<div id="panel-sitrep" class="panel-tab">
  <h2 class="text-lg font-black uppercase tracking-tight mb-4">SITREP Board</h2>
  <div class="space-y-3">
    <div id="sitrep-list" class="space-y-2 max-h-64 overflow-y-auto text-sm">
      <p class="text-slate-500 text-xs">Chargement…</p>
    </div>
    <details class="text-xs text-slate-600 border border-slate-200 rounded-lg p-2 bg-slate-50">
      <summary class="cursor-pointer font-bold text-slate-700">Qui génère ? Comment ?</summary>
      <ul class="mt-2 space-y-1 list-disc list-inside">
        <li><strong>Génération :</strong> les rapports sont créés ou fusionnés quand un client envoie <code class="bg-slate-200 px-1 rounded">POST /api/intel/report</code> avec <code>missionId</code>, <code>target_type</code>, <code>pos_x</code>, <code>pos_y</code>, <code>source_callsign</code> (optionnel).</li>
        <li><strong>Depuis Arma :</strong> le mod COMSPEC peut appeler l’extension avec <code>Intel.Report</code> et un JSON (à brancher dans vos scripts SQF / JTAC / contact). Aucun script du mod n’appelle encore cette fonction par défaut.</li>
        <li><strong>Fusion :</strong> si un rapport existant a le même <code>target_type</code> et une position à moins de 100 m dans les 5 dernières minutes, le nouveau rapport est fusionné (incrément <code>merged_count</code>).</li>
        <li><strong>Statuts :</strong> 1 source = <strong>TEMPORARY</strong>, 2 = <strong>CORROBORATED</strong>, 3+ = <strong>CONFIRMED</strong>.</li>
      </ul>
    </details>
    <div class="border-t border-slate-200 pt-2">
      <p class="text-xs font-bold text-slate-600 mb-1">Créer un report (test)</p>
      <div class="flex flex-wrap gap-2 items-end">
        <label class="flex flex-col gap-0.5">
          <span class="text-xs text-slate-500">Type cible</span>
          <input type="text" id="sitrep-test-target" value="INFANTRY" class="border border-slate-300 rounded px-2 py-1 text-sm w-28" placeholder="INFANTRY" />
        </label>
        <label class="flex flex-col gap-0.5">
          <span class="text-xs text-slate-500">X</span>
          <input type="number" id="sitrep-test-x" value="15000" class="border border-slate-300 rounded px-2 py-1 text-sm w-20" />
        </label>
        <label class="flex flex-col gap-0.5">
          <span class="text-xs text-slate-500">Y</span>
          <input type="number" id="sitrep-test-y" value="15000" class="border border-slate-300 rounded px-2 py-1 text-sm w-20" />
        </label>
        <label class="flex flex-col gap-0.5">
          <span class="text-xs text-slate-500">Source</span>
          <input type="text" id="sitrep-test-source" value="C2" class="border border-slate-300 rounded px-2 py-1 text-sm w-24" placeholder="C2" />
        </label>
        <button type="button" id="sitrep-test-submit" class="px-2 py-1 rounded bg-slate-800 text-white text-xs font-bold uppercase">Envoyer</button>
      </div>
    </div>
  </div>
</div>
