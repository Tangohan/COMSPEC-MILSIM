<?php
declare(strict_types=1);
/** @var bool $valid */
/** @var string $message */
/** @var array<string,mixed>|null $case */
/** @var array<string,mixed>|null $workstation */
/** @var bool $match */
/** @var bool $canOpen */
/** @var string|null $caseUrl */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$ws = is_array($workstation ?? null) ? $workstation : [];
$caseRow = is_array($case ?? null) ? $case : null;
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $h($title ?? 'Sceau poste de travail') ?></title>
    <link rel="stylesheet" href="<?= $h(url('assets/css/sse_portal.css')) ?>">
    <style>
        body.sse-seal-page {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 1.5rem;
            background: #0b1220;
            color: #e2e8f0;
            font-family: "Segoe UI", system-ui, sans-serif;
        }
        .sse-seal-card {
            width: min(32rem, 100%);
            padding: 1.4rem 1.5rem 1.55rem;
            border: 1px solid rgba(148, 163, 184, 0.35);
            border-radius: 0.75rem;
            background: linear-gradient(180deg, #152033, #0f172a);
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.35);
        }
        .sse-seal-card h1 { margin: 0 0 0.35rem; font-size: 1.15rem; }
        .sse-seal-card .lead { margin: 0 0 1rem; color: #94a3b8; font-size: 0.9rem; line-height: 1.45; }
        .sse-seal-meta { display: grid; gap: 0.45rem; margin: 0 0 1.1rem; font-size: 0.86rem; }
        .sse-seal-meta div { display: flex; justify-content: space-between; gap: 1rem; border-bottom: 1px solid rgba(148,163,184,.2); padding-bottom: 0.35rem; }
        .sse-seal-meta span { color: #94a3b8; }
        .sse-seal-actions { display: flex; flex-wrap: wrap; gap: 0.55rem; }
        .badge-ok { color: #86efac; }
        .badge-warn { color: #fcd34d; }
        .badge-bad { color: #fca5a5; }
    </style>
</head>
<body class="sse-seal-page">
    <main class="sse-seal-card">
        <p class="muted" style="margin:0 0 .4rem;letter-spacing:.14em;text-transform:uppercase;font-size:.68rem;font-weight:800;color:#94a3b8">Athena · Bureau SSE</p>
        <h1>Sceau poste de travail</h1>
        <p class="lead <?= !empty($valid) ? ($match ? 'badge-ok' : 'badge-warn') : 'badge-bad' ?>">
            <?= $h($message ?? '') ?>
        </p>
        <?php if (!empty($valid) && $caseRow): ?>
            <div class="sse-seal-meta">
                <div><span>Dossier</span><strong><?= $h($caseRow['reference_code'] ?? '') ?></strong></div>
                <div><span>Intitulé</span><strong><?= $h($caseRow['title'] ?? '') ?></strong></div>
                <div><span>Identifiant QR</span><strong><?= $h($ws['id'] ?? '—') ?></strong></div>
                <div><span>Poste</span><strong><?= $h(($ws['host'] ?? '—') . ' · ' . ($ws['ip'] ?? '')) ?></strong></div>
            </div>
        <?php endif; ?>
        <div class="sse-seal-actions">
            <?php if (!empty($canOpen) && !empty($caseUrl)): ?>
                <a class="btn" href="<?= $h($caseUrl) ?>">Ouvrir le dossier</a>
            <?php elseif (!empty($valid)): ?>
                <a class="btn" href="<?= $h(url('atak/sse')) ?>">Entrer dans le bureau SSE</a>
            <?php endif; ?>
            <a class="btn btn--ghost" href="<?= $h(url('')) ?>">Retour Athena</a>
        </div>
    </main>
</body>
</html>
