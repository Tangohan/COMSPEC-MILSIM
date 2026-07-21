<?php
declare(strict_types=1);
$title = (string) ($title ?? 'Briefing tactique');
$atakTenantName = trim((string) ($atakTenantName ?? 'Communauté'));
$atakSlides = is_array($atakSlides ?? null) ? $atakSlides : [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=3.0">
    <meta http-equiv="refresh" content="30">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: #0f172a; font-family: -apple-system, system-ui, sans-serif; color: #e2e8f0; }
        header { position: sticky; top: 0; z-index: 5; background: #0f172a; border-bottom: 1px solid rgba(255,255,255,.08); padding: .9rem 1rem; }
        header p.eyebrow { margin: 0 0 .15rem; font-size: .6rem; font-weight: 800; letter-spacing: .25em; text-transform: uppercase; color: #34d399; }
        header h1 { margin: 0; font-size: 1rem; font-weight: 900; }
        main { padding: 1rem; display: flex; flex-direction: column; gap: 1rem; }
        .empty { text-align: center; padding: 3rem 1rem; color: #94a3b8; font-size: .875rem; }
        .slide { background: #fff; border-radius: 1rem; overflow: hidden; box-shadow: 0 12px 30px -10px rgba(0,0,0,.5); }
        .slide img { width: 100%; display: block; }
        .slide .cap { padding: .6rem .9rem; font-size: .8rem; font-weight: 700; color: #0f172a; }
        footer { text-align: center; padding: 1rem; font-size: .65rem; color: #64748b; }
    </style>
</head>
<body>
    <header>
        <p class="eyebrow">Briefing tactique</p>
        <h1><?= htmlspecialchars($atakTenantName, ENT_QUOTES, 'UTF-8') ?></h1>
    </header>
    <main>
        <?php if ($atakSlides === []): ?>
        <div class="empty">Aucune diapositive de briefing active pour l’instant.</div>
        <?php else: ?>
        <?php foreach ($atakSlides as $slide):
            $imagePath = trim((string) ($slide['image_path'] ?? ''));
            if ($imagePath === '') {
                continue;
            }
            $slideTitle = trim((string) ($slide['title'] ?? ''));
        ?>
        <div class="slide">
            <img src="<?= htmlspecialchars(url($imagePath), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($slideTitle !== '' ? $slideTitle : 'Diapositive de briefing', ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
            <?php if ($slideTitle !== ''): ?><div class="cap"><?= htmlspecialchars($slideTitle, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </main>
    <footer>Actualisation automatique toutes les 30 secondes.</footer>
</body>
</html>
