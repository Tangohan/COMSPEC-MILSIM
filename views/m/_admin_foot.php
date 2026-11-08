<?php

declare(strict_types=1);

/**
 * Pied de l'espace d'administration : fermer la session, script des formulaires.
 *
 * @var array<string, mixed>|null $adminLink
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$ver = rawurlencode(class_exists(\App\Support\BuildVersion::class) ? \App\Support\BuildVersion::id() : '1');
?>
  </main>
<?php if (!empty($adminLink)): ?>
  <footer class="mm-foot">
    <button type="button" class="mm-link" data-admin-logout>Fermer l’espace d’administration</button>
    <small>Chaque action est enregistrée dans le journal de la communauté, à votre nom. Vos droits sont revérifiés à chaque action.</small>
  </footer>
<?php endif; ?>
  <div class="mm-toast" id="mm-toast" role="status" aria-live="polite" hidden></div>
  <script src="<?= $h(url('assets/js/atak-m-admin.js')) ?>?v=<?= $h($ver) ?>"></script>
</body>
</html>
