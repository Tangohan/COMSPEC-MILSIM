<?php
declare(strict_types=1);
/**
 * CSS utilitaire : préfère public/assets/css/tailwind.css (build prod) au script CDN de développement.
 */
$tailwindBuilt = is_file(base_path('public/assets/css/tailwind.css'));
if ($tailwindBuilt): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/tailwind.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<?php else: ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            fontFamily: {
              sans: ['Inter', 'system-ui', 'sans-serif'],
              serif: ['Inter', 'Segoe UI', 'system-ui', 'sans-serif'],
            },
            letterSpacing: {
              architect: '0.3em',
              blueprint: '0.5em',
            },
          },
        },
      };
    </script>
<?php endif; ?>
