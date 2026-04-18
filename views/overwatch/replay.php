<div id="panel-replay" class="panel-tab">
  <h2 class="text-lg font-black uppercase tracking-tight mb-4">Time-Travel Replay</h2>
  <div class="space-y-4">
    <div class="flex gap-2 items-center">
      <button type="button" id="replay-play" class="px-3 py-2 rounded-lg bg-slate-800 text-white text-sm font-bold">Play</button>
      <button type="button" id="replay-pause" class="px-3 py-2 rounded-lg border border-slate-300 text-sm font-bold">Pause</button>
      <button type="button" id="replay-aar-refresh" class="px-3 py-2 rounded-lg border border-emerald-300 bg-emerald-50 text-emerald-800 text-sm font-bold">AAR</button>
      <button type="button" id="replay-aar-export" class="px-3 py-2 rounded-lg border border-indigo-300 bg-indigo-50 text-indigo-800 text-sm font-bold">Export PDF</button>
      <select id="replay-speed" class="border border-slate-300 rounded-lg px-2 py-1 text-sm">
        <option value="1">x1</option>
        <option value="2">x2</option>
        <option value="4">x4</option>
        <option value="8">x8</option>
      </select>
    </div>
    <div>
      <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Timeline</label>
      <input type="range" id="replay-slider" class="w-full" min="0" max="100" value="0" />
    </div>
    <div id="replay-info" class="text-xs text-slate-500">Charger la mission pour rejouer.</div>
    <div id="replay-aar" class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-700">
      Analyse AAR indisponible.
    </div>
  </div>
</div>
