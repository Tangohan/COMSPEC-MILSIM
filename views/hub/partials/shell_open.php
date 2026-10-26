<?php
declare(strict_types=1);

/**
 * Coque commune des pages du hub : registre sombre inspiré de « Mes formations » (rail de navigation,
 * panneaux et cartes tactiles). Variables : $hubShell (contrôleur), $hxTitle, $hxKicker, $hxLead, $hxActions (HTML).
 * Fermer avec views/hub/partials/shell_close.php.
 */
$hubShell = is_array($hubShell ?? null) ? $hubShell : ['active' => 'home', 'mine_slug' => '', 'mine_name' => '', 'training' => false, 'board_open' => null, 'reviews' => 0];
$hxH = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$hxIcons = [
    'home' => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"/>',
    'annuaire' => '<rect x="3.5" y="4" width="17" height="16" rx="1.5"/><path d="M8 9h8M8 13h8M8 17h5"/>',
    'comparer' => '<path d="M8 4v16M16 4v16"/><path d="M4 8h4M16 12h4M4 16h4"/>',
    'bourse' => '<path d="M7.5 12.5 4 16l4 4 3.5-3.5"/><path d="m16.5 11.5 3.5-3.5-4-4-3.5 3.5"/><path d="m9 15 6-6"/>',
    'fiche' => '<path d="M12 3.5 4.5 6.5v5c0 4.4 3.1 7.6 7.5 9 4.4-1.4 7.5-4.6 7.5-9v-5Z"/>',
    'avis' => '<path d="m12 4 2.4 4.9 5.4.8-3.9 3.8.9 5.4L12 16.4 7.2 18.9l.9-5.4L4.2 9.7l5.4-.8Z"/>',
    'formations' => '<path d="M2.5 9 12 4.5 21.5 9 12 13.5 2.5 9Z"/><path d="M6.5 11v4.5c0 1.4 2.5 3 5.5 3s5.5-1.6 5.5-3V11M21.5 9v5"/>',
    'dashboard' => '<rect x="3.5" y="3.5" width="7" height="7" rx="1"/><rect x="13.5" y="3.5" width="7" height="4.5" rx="1"/><rect x="13.5" y="11" width="7" height="9.5" rx="1"/><rect x="3.5" y="13.5" width="7" height="7" rx="1"/>',
    'actions' => '<path d="M12 3v3M12 18v3M3 12h3M18 12h3"/><circle cx="12" cy="12" r="5"/>',
    'inbox' => '<path d="M3.5 13.5 6 5h12l2.5 8.5"/><path d="M3.5 13.5V19h17v-5.5h-5l-1.5 2.5h-4l-1.5-2.5Z"/>',
    'demarches' => '<path d="M7 3.5h7.5L19 8v12.5H7Z"/><path d="M14 3.5V8h5M10 13h6M10 17h4"/>',
    'coop' => '<circle cx="8" cy="9" r="3"/><circle cx="16" cy="9" r="3"/><path d="M2.5 19c.8-3 3-4.5 5.5-4.5s4.7 1.5 5.5 4.5M10.5 19c.8-3 3-4.5 5.5-4.5s4.7 1.5 5.5 4.5"/>',
    'search' => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
    'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
    'heart' => '<path d="M12 20.5s-7.5-4.6-7.5-10.2A4.3 4.3 0 0 1 12 7.6a4.3 4.3 0 0 1 7.5 2.7c0 5.6-7.5 10.2-7.5 10.2Z"/>',
    'plus' => '<path d="M12 5v14M5 12h14"/>',
    'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="1.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
    'users' => '<circle cx="9" cy="8.5" r="3"/><path d="M3.5 19c.7-3.2 2.8-5 5.5-5s4.8 1.8 5.5 5"/><path d="M15.5 5.8a3 3 0 0 1 0 5.4M17.5 14.4c1.6.7 2.6 2.2 3 4.6"/>',
    'pulse' => '<path d="M3 12h4l2.5-6 5 12 2.5-6H21"/>',
    'tree' => '<rect x="9" y="3.5" width="6" height="4.5" rx="1"/><rect x="3.5" y="16" width="6" height="4.5" rx="1"/><rect x="14.5" y="16" width="6" height="4.5" rx="1"/><path d="M12 8v4M6.5 16v-2h11v2"/>',
    'intel' => '<path d="M4 5.5h16v11H8l-4 3Z"/><path d="M8 10h8M8 13h5"/>',
    'target' => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r=".8"/>',
];
$hxIcon = static fn (string $k, string $cls = 'hx-ico'): string => '<svg class="' . $cls . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($hxIcons[$k] ?? '') . '</svg>';

$hxNav = [
    ['home', 'Accueil du hub', 'Réseau, besoins, votre position', url('hub'), null],
    ['annuaire', 'Annuaire', 'Toutes les communautés', url('hub') . '#annuaire', null],
    ['comparer', 'Comparer', 'Deux communautés côte à côte', url('hub/comparer'), null],
    ['bourse', 'Bourse aux coopérations', 'Besoins et propositions', url('hub/bourse'), $hubShell['board_open'] ?? null],
];
if (($hubShell['mine_slug'] ?? '') !== '') {
    $hxNav[] = ['fiche', 'Notre fiche', 'Ce que le réseau voit de nous', url('hub/c/' . $hubShell['mine_slug']), null];
}
$hxSpace = [];
if (!empty($hubShell['training'])) {
    $hxSpace[] = ['formations', 'Mes formations', 'Parcours, progression, attestations', url('formations/mes-formations')];
}
$hxSpace[] = ['coop', 'Coopérations', 'Missions avec d’autres unités', cooperation_mission_index_url()];
$hxSpace[] = ['dashboard', 'Tableau de bord', 'Synthèse du jour', url('dashboard')];
$hxActive = (string) ($hubShell['active'] ?? 'home');
?>
<link rel="stylesheet" href="<?= $hxH(asset_url('assets/css/hub-ecosystem.css')) ?>">
<div class="hubx">
  <div class="hx-gridlines" aria-hidden="true"></div>
  <div class="hx-shell">
    <aside class="hx-rail" aria-label="Navigation du hub">
      <p class="hx-rail__brand">Athena · Hub</p>
      <p class="hx-section-label">Réseau</p>
      <nav class="hx-rail__group">
        <?php foreach ($hxNav as [$key, $label, $hint, $href, $count]): ?>
        <a class="hx-nav-btn<?= $hxActive === $key ? ' is-active' : '' ?>" href="<?= $hxH($href) ?>"<?= $hxActive === $key ? ' aria-current="page"' : '' ?>>
          <?= $hxIcon($key) ?><span><?= $hxH($label) ?><em><?= $hxH($hint) ?></em></span><?php if ($count): ?><i class="hx-count"><?= (int) $count ?></i><?php endif; ?>
        </a>
        <?php endforeach; ?>
        <?php if ((int) ($hubShell['reviews'] ?? 0) > 0): ?>
        <a class="hx-nav-btn hx-nav-btn--urgent" href="<?= $hxH(url('hub')) ?>#avis"><?= $hxIcon('avis') ?><span>Avis à donner<em>Partenaires à évaluer</em></span><i class="hx-count"><?= (int) $hubShell['reviews'] ?></i></a>
        <?php endif; ?>
      </nav>
      <p class="hx-section-label">Mon espace</p>
      <nav class="hx-rail__group">
        <?php foreach ($hxSpace as [$key, $label, $hint, $href]): ?>
        <a class="hx-nav-btn" href="<?= $hxH($href) ?>"<?= $key === 'formations' ? ' data-lms-module-entry="formation"' : '' ?>><?= $hxIcon($key) ?><span><?= $hxH($label) ?><em><?= $hxH($hint) ?></em></span></a>
        <?php endforeach; ?>
      </nav>
    </aside>

    <div class="hx-main">
      <nav class="hx-mobilebar" aria-label="Navigation du hub">
        <?php foreach ($hxNav as [$key, $label, , $href, $count]): ?>
        <a class="<?= $hxActive === $key ? 'is-active' : '' ?>" href="<?= $hxH($href) ?>"<?= $hxActive === $key ? ' aria-current="page"' : '' ?>><?= $hxIcon($key) ?><?= $hxH($label) ?><?= $count ? ' <i class="hx-count">' . (int) $count . '</i>' : '' ?></a>
        <?php endforeach; ?>
        <?php if (!empty($hubShell['training'])): ?><a href="<?= $hxH(url('formations/mes-formations')) ?>" data-lms-module-entry="formation"><?= $hxIcon('formations') ?>Mes formations</a><?php endif; ?>
      </nav>
      <header class="hx-head">
        <div class="hx-head__copy">
          <?php if (!empty($hxBack)): ?><a class="hx-back" href="<?= $hxH((string) $hxBack[0]) ?>"><?= $hxH((string) $hxBack[1]) ?></a><?php endif; ?>
          <p class="hx-kicker"><?= $hxH((string) ($hxKicker ?? 'Athena · Hub')) ?></p>
          <h1 class="hx-head__title"><?= $hxH((string) ($hxTitle ?? 'Hub des communautés')) ?></h1>
          <?php if (!empty($hxLead)): ?><p class="hx-head__lead"><?= $hxH((string) $hxLead) ?></p><?php endif; ?>
        </div>
        <?php if (!empty($hxActions)): ?><div class="hx-head__actions"><?= $hxActions ?></div><?php endif; ?>
      </header>
      <?php
      $hxFlashOk = \App\Core\Session::getFlash('success');
      $hxFlashErr = \App\Core\Session::getFlash('error');
      ?>
      <?php if ($hxFlashOk): ?><p class="hx-flash hx-flash--ok" role="status"><?= $hxH((string) $hxFlashOk) ?></p><?php endif; ?>
      <?php if ($hxFlashErr): ?><p class="hx-flash hx-flash--err" role="alert"><?= $hxH((string) $hxFlashErr) ?></p><?php endif; ?>
