<?php
declare(strict_types=1);
/**
 * CSS utilitaire : préfère public/assets/css/tailwind.css (build prod) au script CDN de développement.
 * Variables optionnelles : $tailwindBaseUrl (défaut url('')).
 */
$tailwindBaseUrl = $tailwindBaseUrl ?? url('');
$tailwindBuilt = is_file(base_path('public/assets/css/tailwind.css'));
$twBase = rtrim((string) $tailwindBaseUrl, '/');
if ($tailwindBuilt): ?>
    <link href="<?= htmlspecialchars($twBase) ?>/assets/css/tailwind.css" rel="stylesheet">
<?php else: ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            fontFamily: {
              sans: ['Inter', 'system-ui', 'sans-serif'],
              serif: ['"Source Serif 4"', 'Georgia', 'serif'],
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
