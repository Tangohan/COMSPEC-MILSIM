<?php
$atakToken = $atakToken ?? '';
$nodeAtakUrl = $nodeAtakUrl ?? '';
$baseUrl = url('');
?>
<script>
  window.ATAK_TOKEN = <?= json_encode($atakToken) ?>;
  window.NODE_ATAK_URL = <?= json_encode($nodeAtakUrl) ?>;
</script>
<div class="max-w-full px-4 py-4">
  <p class="text-slate-600 mb-2">Carte tactique ATAK — Token injecté pour le service Node. Connectez le service temps réel sur <code class="text-sm bg-slate-100 px-1 rounded"><?= htmlspecialchars($nodeAtakUrl) ?></code></p>
  <p class="text-sm text-slate-500 mb-4"><a href="<?= $baseUrl ?>/dashboard" class="underline">Retour dashboard</a></p>
  <p class="text-xs text-slate-400">Pour activer la carte complète : intégrer le contenu de <code>atak.html</code> ici et transmettre <code>ATAK_TOKEN</code> au Socket.IO / API Node.</p>
</div>
