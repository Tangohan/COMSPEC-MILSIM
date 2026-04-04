<?php
$baseUrl = url('');
$nodeAtakUrl = $nodeAtakUrl ?? '';
$atakConfig = $atakConfig ?? null;
$atakModDownloadUrl = $atakModDownloadUrl ?? null;
?>
<div class="max-w-3xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-2">Assistant Mod Arma — COMSPEC Overwatch</h1>
    <p class="text-sm text-slate-600 mb-8">Installation, configuration Arma ↔ site et vérification du fonctionnement.</p>

    <nav class="mb-8 pb-4 border-b border-slate-200 flex flex-wrap gap-2">
        <a href="<?= $baseUrl ?>/atak" class="text-slate-600 hover:text-slate-900 text-sm font-medium">← Carte ATAK</a>
        <span class="text-slate-400">·</span>
        <a href="<?= $baseUrl ?>/atak/tuto" class="text-slate-600 hover:text-slate-900 text-sm font-medium">Tutoriel détaillé</a>
        <?php if (function_exists('can') && can('admin.access')): ?>
        <span class="text-slate-400">·</span>
        <a href="<?= $baseUrl ?>/admin/atak-config" class="text-slate-600 hover:text-slate-900 text-sm font-medium">Config ATAK (admin)</a>
        <?php endif; ?>
    </nav>

    <div class="space-y-10">
        <!-- 1. Installation -->
        <section class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
                <span class="flex items-center justify-center w-8 h-8 rounded-full bg-slate-900 text-white text-sm">1</span>
                Installation
            </h2>
            <ul class="space-y-3 text-sm text-slate-700">
                <li class="flex items-start gap-3">
                    <span class="text-slate-400 mt-0.5">□</span>
                    <span><strong>Prérequis :</strong> Arma 3 à jour et <strong>CBA A3</strong> (Community Base Addons).</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-slate-400 mt-0.5">□</span>
                    <span><strong>Télécharger le mod</strong>
                        <?php if ($atakModDownloadUrl): ?>
                            — <a href="<?= htmlspecialchars($atakModDownloadUrl) ?>" class="text-slate-900 underline font-medium" download>Télécharger COMSPEC Overwatch</a>
                        <?php else: ?>
                            — à fournir par votre administrateur (voir <a href="<?= $baseUrl ?>/atak/tuto" class="text-slate-900 underline">tutoriel</a>).
                        <?php endif; ?>
                    </span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-slate-400 mt-0.5">□</span>
                    <span><strong>Extraire</strong> l’archive dans le dossier Arma 3 (ou mods du launcher) pour obtenir le dossier <code class="bg-slate-100 px-1 rounded">@COMSPECOverwatch</code>.</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-slate-400 mt-0.5">□</span>
                    <span><strong>Activer</strong> CBA A3 puis COMSPEC Overwatch dans le launcher.</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-slate-400 mt-0.5">□</span>
                    <span><strong>Vérifier</strong> que <code class="bg-slate-100 px-1 rounded">COMSPECExtension_x64.dll</code> est bien à la racine de <code class="bg-slate-100 px-1 rounded">@COMSPECOverwatch</code> (fournie avec le mod).</span>
                </li>
            </ul>
        </section>

        <!-- 2. Configuration -->
        <section class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
                <span class="flex items-center justify-center w-8 h-8 rounded-full bg-slate-900 text-white text-sm">2</span>
                Configuration (Arma ↔ site)
            </h2>
            <p class="text-sm text-slate-600 mb-4">Dans Arma : <strong>ESC</strong> → <strong>Options</strong> → <strong>Jeu</strong> → <strong>Configurer les mods</strong> (Configure Addons) → <strong>COMSPEC Overwatch</strong> → <strong>Connexion</strong>.</p>

            <?php if ($nodeAtakUrl !== ''): ?>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">URL du nœud ATAK</label>
                    <div class="flex gap-2 items-center">
                        <pre class="flex-1 bg-slate-100 border border-slate-200 rounded px-3 py-2 text-sm overflow-x-auto" id="setup-node-url"><?= htmlspecialchars($nodeAtakUrl) ?></pre>
                        <button type="button" class="px-3 py-2 bg-slate-900 text-white text-sm font-medium rounded hover:bg-slate-800 whitespace-nowrap" data-copy-target="setup-node-url">Copier</button>
                    </div>
                </div>
                <?php if (!empty($atakConfig['arma_mod_credentials'])): ?>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Clé d’accès / identifiants (si fournis)</label>
                    <div class="flex gap-2 items-start">
                        <pre class="flex-1 bg-slate-100 border border-slate-200 rounded px-3 py-2 text-sm whitespace-pre-wrap" id="setup-credentials"><?= htmlspecialchars($atakConfig['arma_mod_credentials']) ?></pre>
                        <button type="button" class="px-3 py-2 bg-slate-900 text-white text-sm font-medium rounded hover:bg-slate-800 whitespace-nowrap" data-copy-target="setup-credentials">Copier</button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">L’URL du nœud n’est pas configurée. Un administrateur doit renseigner la <a href="<?= $baseUrl ?>/admin/atak-config" class="underline">Configuration ATAK</a>.</p>
            <?php endif; ?>
        </section>

        <!-- 3. Vérification -->
        <section class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
                <span class="flex items-center justify-center w-8 h-8 rounded-full bg-slate-900 text-white text-sm">3</span>
                Vérification
            </h2>
            <div class="space-y-4">
                <div>
                    <p class="text-sm text-slate-700 mb-2">Vérifier que le nœud ATAK (serveur de la carte) répond bien.</p>
                    <button type="button" id="setup-test-node" class="px-4 py-2 bg-emerald-600 text-white text-sm font-semibold rounded hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        Tester la connexion au nœud
                    </button>
                    <p id="setup-test-result" class="mt-2 text-sm hidden" role="status"></p>
                </div>
                <div class="border-t border-slate-200 pt-4">
                    <p class="text-sm text-slate-700 mb-2"><strong>Test en jeu :</strong></p>
                    <ol class="list-decimal list-inside text-sm text-slate-700 space-y-1">
                        <li>Ouvrez la <a href="<?= $baseUrl ?>/atak" class="text-slate-900 underline font-medium">carte ATAK</a> dans votre navigateur.</li>
                        <li>Lancez Arma 3 avec le mod COMSPEC Overwatch et rejoignez une mission.</li>
                        <li>Votre indicatif doit apparaître sur la carte après quelques secondes.</li>
                    </ol>
                    <p class="text-xs text-slate-500 mt-2">Assurez-vous que votre <strong>indicatif Arma</strong> est renseigné dans <a href="<?= $baseUrl ?>/account/preferences" class="underline">Préférences du compte</a> si le site l’utilise pour l’affichage.</p>
                </div>
            </div>
        </section>
    </div>

    <p class="mt-10 text-sm text-slate-500">
        <a href="<?= $baseUrl ?>/atak" class="text-slate-700 hover:underline font-medium">Ouvrir la carte ATAK</a>
        ·
        <a href="<?= $baseUrl ?>/atak/tuto" class="text-slate-700 hover:underline font-medium">Tutoriel complet</a>
        ·
        <a href="<?= $baseUrl ?>/dashboard" class="text-slate-700 hover:underline font-medium">Dashboard</a>
    </p>
</div>

<script>
(function () {
  var nodeUrl = <?= json_encode($nodeAtakUrl) ?>;

  document.querySelectorAll('[data-copy-target]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = this.getAttribute('data-copy-target');
      var el = document.getElementById(id);
      if (!el) return;
      var text = el.textContent || '';
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () {
          var label = btn.textContent;
          btn.textContent = 'Copié';
          setTimeout(function () { btn.textContent = label; }, 1500);
        });
      }
    });
  });

  var testBtn = document.getElementById('setup-test-node');
  var testResult = document.getElementById('setup-test-result');
  if (testBtn && testResult) {
    testBtn.addEventListener('click', function () {
      if (!nodeUrl) {
        testResult.textContent = 'Aucune URL de nœud configurée.';
        testResult.className = 'mt-2 text-sm text-amber-700';
        testResult.classList.remove('hidden');
        return;
      }
      testBtn.disabled = true;
      testResult.textContent = 'Test en cours…';
      testResult.className = 'mt-2 text-sm text-slate-600';
      testResult.classList.remove('hidden');
      var pingUrl = nodeUrl.replace(/\/$/, '') + '/api/atak/ping';
      fetch(pingUrl, { method: 'GET', mode: 'cors' })
        .then(function (r) {
          if (r.ok) return r.json();
          throw new Error('Réponse ' + r.status);
        })
        .then(function (data) {
          testResult.textContent = 'Connexion au nœud OK. Le serveur ATAK répond.';
          testResult.className = 'mt-2 text-sm text-emerald-700';
        })
        .catch(function (err) {
          testResult.textContent = 'Échec : ' + (err.message || 'impossible de joindre le nœud').replace(/^Échec : Échec : /, 'Échec : ');
          testResult.className = 'mt-2 text-sm text-red-700';
        })
        .then(function () {
          testBtn.disabled = false;
        });
    });
  }
})();
</script>
