<?php
declare(strict_types=1);

$title = $title ?? 'Accès indisponible';
$baseUrl = url('');
$brand = email_brand_name();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<?php
    $tailwindBaseUrl = $baseUrl;
    require base_path('views/partials/tailwind_cdn_or_build.php');
?>
    <style>
        body.nda-denied {
            margin: 0;
            min-height: 100vh;
            font-family: Manrope, system-ui, sans-serif;
            color: #e8eef4;
            background:
                radial-gradient(900px 480px at 50% -20%, rgba(180, 70, 70, 0.16), transparent 55%),
                linear-gradient(165deg, #050a10 0%, #121018 100%);
        }
        .nda-brand { font-family: "Space Grotesk", Manrope, sans-serif; letter-spacing: 0.08em; }
    </style>
</head>
<body class="nda-denied">
    <div class="mx-auto flex min-h-screen max-w-lg flex-col items-center justify-center px-4 py-16 text-center">
        <p class="nda-brand text-xs font-semibold uppercase text-amber-400/90">TTRD.FR</p>
        <h1 class="mt-4 text-3xl font-extrabold tracking-tight text-white">Accès indisponible</h1>
        <p class="mt-5 text-sm leading-relaxed text-slate-400">
            Cette démonstration n’est plus accessible depuis votre connexion.
            La fenêtre d’entrée ou la durée d’accès autorisée est écoulée.
        </p>
        <p class="mt-4 text-sm text-slate-500">
            Pour toute demande, contactez directement TTRD.FR.
        </p>
    </div>
</body>
</html>
