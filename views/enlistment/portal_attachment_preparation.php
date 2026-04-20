<?php
declare(strict_types=1);
$portalToken = (string) ($portalToken ?? '');
$attachmentId = (int) ($attachmentId ?? 0);
$originalName = trim((string) ($originalName ?? 'fichier'));
$kind = (string) ($kind ?? 'file');
$downloadDelaySeconds = max(3, min(120, (int) ($downloadDelaySeconds ?? 10)));
$waitRemainingSeconds = max(0, (int) ($waitRemainingSeconds ?? $downloadDelaySeconds));
$gateUnlocked = !empty($gateUnlocked);
$followUpUrl = (string) ($followUpUrl ?? url('enlistment'));
$downloadUrl = (string) ($downloadUrl ?? '');
$isAudio = $kind === 'audio';
$base = url('');
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Téléchargement — pièce jointe</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>body { font-family: Inter, system-ui, sans-serif; }</style>
</head>
<body class="min-h-screen bg-white text-slate-900 antialiased">
<nav class="sticky top-0 z-20 border-b border-red-100 bg-white/95 px-4 py-3 backdrop-blur">
    <div class="mx-auto flex max-w-3xl items-center justify-between gap-3">
        <a href="<?= htmlspecialchars($followUpUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-xs font-bold text-red-700 underline decoration-red-200 underline-offset-4 hover:text-red-900">← Retour au suivi</a>
        <span class="text-[10px] font-black uppercase tracking-[0.25em] text-red-600/90">Athena</span>
    </div>
</nav>

<main class="mx-auto max-w-3xl px-4 py-10 sm:py-14">
    <div class="overflow-hidden rounded-[1.75rem] border border-red-200 bg-white shadow-xl shadow-red-900/10 ring-1 ring-red-100">
        <div class="h-1.5 bg-red-600" aria-hidden="true"></div>
        <div class="border-b border-red-100 bg-gradient-to-b from-red-50/90 to-white px-6 py-5 sm:px-8">
            <p class="text-[10px] font-black uppercase tracking-[0.35em] text-red-700/90">Étape avant téléchargement</p>
            <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Pièce transmise sur votre dossier</h1>
            <p class="mt-2 truncate text-sm font-semibold text-red-950/90" title="<?= htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8') ?></p>
            <p class="mt-1 text-xs text-slate-600"><?= $isAudio ? 'Type : enregistrement audio' : 'Type : document' ?> · référence interne n°<?= $attachmentId ?></p>
        </div>
        <div class="space-y-6 px-6 py-8 sm:px-8">
            <div class="rounded-2xl border border-red-200 bg-red-50 p-5 text-sm leading-relaxed text-red-950">
                <p class="font-bold text-red-900">Sécurité et bonnes pratiques</p>
                <ul class="mt-3 list-disc space-y-2 pl-5 text-red-950/90">
                    <li>Les fichiers reçus via ce suivi <strong class="text-red-900">ne sont pas analysés automatiquement</strong> (antivirus ou filtre de contenu) au moment du téléchargement.</li>
                    <li>Ouvrez ce fichier uniquement si vous reconnaissez l’échange avec l’équipe recrutement et si le nom vous semble cohérent.</li>
                    <li>En cas de doute, contactez la communauté par un canal déjà utilisé plutôt que d’ouvrir la pièce.</li>
                </ul>
            </div>
            <div class="rounded-2xl border border-red-100 bg-white p-5 text-center shadow-inner shadow-red-50">
                <p class="text-xs font-semibold uppercase tracking-wider text-red-800/70">Délai de réflexion</p>
                <p id="prep-countdown" class="mt-2 text-4xl font-black tabular-nums text-red-600"><?= (int) $waitRemainingSeconds ?></p>
                <p class="mt-2 text-xs text-slate-600">Quelques secondes pour laisser le temps de lire l’avertissement ci-dessus.</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-center">
                <a id="prep-download-btn" href="<?= htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8') ?>"
                   class="<?= $gateUnlocked ? '' : 'pointer-events-none opacity-40 ' ?>inline-flex min-h-[3rem] items-center justify-center rounded-2xl bg-red-600 px-8 text-sm font-black uppercase tracking-wide text-white shadow-lg shadow-red-900/25 transition hover:bg-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white">
                    Télécharger le fichier
                </a>
                <a href="<?= htmlspecialchars($followUpUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex min-h-[3rem] items-center justify-center rounded-2xl border-2 border-red-200 bg-white px-6 text-sm font-bold text-red-800 transition hover:border-red-300 hover:bg-red-50">
                    Annuler
                </a>
            </div>
            <p class="text-center text-[11px] leading-relaxed text-slate-500">Un court délai est exigé avant la première récupération du fichier sur cet appareil ; les téléchargements suivants depuis cette session sont immédiats.</p>
        </div>
    </div>
</main>
<script>
(function () {
  var total = <?= json_encode($downloadDelaySeconds) ?>;
  var remaining = <?= json_encode($waitRemainingSeconds) ?>;
  var el = document.getElementById('prep-countdown');
  var btn = document.getElementById('prep-download-btn');
  if (!el || !btn) return;
  function tick() {
    el.textContent = String(Math.max(0, remaining));
    if (remaining <= 0) {
      btn.classList.remove('pointer-events-none', 'opacity-40');
      return;
    }
    remaining -= 1;
    setTimeout(tick, 1000);
  }
  tick();
})();
</script>
</body>
</html>
