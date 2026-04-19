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
<body class="min-h-screen bg-slate-950 text-slate-100">
<nav class="sticky top-0 z-20 border-b border-white/10 bg-slate-950/90 px-4 py-3 backdrop-blur">
    <div class="mx-auto flex max-w-3xl items-center justify-between gap-3">
        <a href="<?= htmlspecialchars($followUpUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-xs font-bold text-emerald-400 underline decoration-emerald-400/40 underline-offset-4 hover:text-emerald-300">← Retour au suivi</a>
        <span class="text-[10px] font-black uppercase tracking-[0.25em] text-white/50">Athena</span>
    </div>
</nav>

<main class="mx-auto max-w-3xl px-4 py-10 sm:py-14">
    <div class="overflow-hidden rounded-[1.75rem] border border-amber-500/30 bg-gradient-to-b from-amber-950/80 to-slate-950 shadow-2xl shadow-amber-900/20">
        <div class="border-b border-amber-500/25 bg-amber-500/10 px-6 py-5 sm:px-8">
            <p class="text-[10px] font-black uppercase tracking-[0.35em] text-amber-200/90">Étape avant téléchargement</p>
            <h1 class="mt-2 text-2xl font-black tracking-tight text-white sm:text-3xl">Pièce transmise sur votre dossier</h1>
            <p class="mt-2 truncate text-sm font-semibold text-amber-50/95" title="<?= htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8') ?></p>
            <p class="mt-1 text-xs text-amber-100/80"><?= $isAudio ? 'Type : enregistrement audio' : 'Type : document' ?> · référence interne n°<?= $attachmentId ?></p>
        </div>
        <div class="space-y-6 px-6 py-8 sm:px-8">
            <div class="rounded-2xl border border-amber-400/25 bg-amber-950/40 p-5 text-sm leading-relaxed text-amber-50/95">
                <p class="font-bold text-amber-100">Sécurité et bonnes pratiques</p>
                <ul class="mt-3 list-disc space-y-2 pl-5 text-amber-50/90">
                    <li>Les fichiers reçus via ce suivi <strong class="text-amber-100">ne sont pas analysés automatiquement</strong> (antivirus ou filtre de contenu) au moment du téléchargement.</li>
                    <li>Ouvrez ce fichier uniquement si vous reconnaissez l’échange avec l’équipe recrutement et si le nom vous semble cohérent.</li>
                    <li>En cas de doute, contactez la communauté par un canal déjà utilisé plutôt que d’ouvrir la pièce.</li>
                </ul>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-5 text-center">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Délai de réflexion</p>
                <p id="prep-countdown" class="mt-2 text-4xl font-black tabular-nums text-white"><?= (int) $waitRemainingSeconds ?></p>
                <p class="mt-2 text-xs text-slate-400">Quelques secondes pour laisser le temps de lire l’avertissement ci-dessus.</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-center">
                <a id="prep-download-btn" href="<?= htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8') ?>"
                   class="<?= $gateUnlocked ? '' : 'pointer-events-none opacity-40 ' ?>inline-flex min-h-[3rem] items-center justify-center rounded-2xl bg-emerald-500 px-8 text-sm font-black uppercase tracking-wide text-slate-950 shadow-lg shadow-emerald-900/30 transition hover:bg-emerald-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950">
                    Télécharger le fichier
                </a>
                <a href="<?= htmlspecialchars($followUpUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex min-h-[3rem] items-center justify-center rounded-2xl border border-white/20 px-6 text-sm font-bold text-white/90 transition hover:bg-white/10">
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
