<?php
declare(strict_types=1);

/**
 * Back-office — Configuration ATAK / Tacmap (/back-office/ressources/atak-config).
 * Un onglet par thème (ancres conservées : #overwatch-game-experience, #photo-hud, #marker-icons, #bridge-modules).
 * Interactions : public/assets/js/back-office-atak-config.js · styles : back-office-atak-config.css.
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$ico = require __DIR__ . '/_icons.php';
$csrf = \App\Core\Csrf::field();
$base = url('back-office/ressources/atak-config');
$config = is_array($config ?? null) ? $config : [];
$atakMaps = is_array($atakMaps ?? null) ? $atakMaps : [];
$tenantId = (int) ($tenantId ?? 0);
$hasKey = !empty($hasTenantAccessKey);
$platformKey = !empty($platformKeyConfigured);
$keyPrefix = (string) ($accessKeyPrefix ?? '');
$keyAt = $accessKeyGeneratedAt ?? null;
$newKey = is_string($newAccessKeyPlain ?? null) ? $newAccessKeyPlain : null;
$authEvents = is_array($authEvents ?? null) ? $authEvents : [];
$dataSummary = is_array($dataSummary ?? null) ? $dataSummary : [];
$maintReady = !empty($maintenanceSchemaReady);
$maintOn = !empty($maintenanceEnabled);
$maintMsg = (string) ($maintenanceMessage ?? '');
$purgeWord = (string) ($purgeConfirmPhrase ?? 'EFFACER');
$modules = is_array($bridgeModules ?? null) ? $bridgeModules : [];
$expCatalog = is_array($experienceCatalog ?? null) ? $experienceCatalog : [];
$expReady = !empty($experienceSchemaReady);
$ge = is_array($gameExperience ?? null) ? $gameExperience : [];
$hostHint = (string) ($_SERVER['HTTP_HOST'] ?? 'votre-domaine.fr');
$fmt = static function (mixed $raw, string $f = 'd/m/Y H:i'): string {
    $t = is_string($raw) && $raw !== '' ? strtotime($raw) : false;

    return $t ? date($f, $t) : '';
};
$section = static function (string $icon, string $title, string $lead) use ($ico, $h): string {
    return '<header class="bo-ac-sec__head"><span class="bo-ac-sec__ico">' . $ico($icon, 22) . '</span><div><h2 class="bo-ac-sec__t">' . $h($title) . '</h2><p class="bo-ac-sec__lead">' . $h($lead) . '</p></div></header>';
};
$howto = static function (string $title, array $steps, string $open = '') use ($h, $ico): string {
    $out = '<details class="bo-ac-howto"' . $open . '><summary>' . $ico('tip', 16) . ' ' . $h($title) . '</summary><ol>';
    foreach ($steps as $s) {
        $out .= '<li>' . $s . '</li>';
    }

    return $out . '</ol></details>';
};
$switch = static function (string $name, bool $on, string $title, string $desc = '', string $icon = '', string $extra = '') use ($h, $ico): string {
    return '<label class="bo-ac-switch"' . $extra . '>'
        . '<input type="hidden" name="' . $h($name) . '" value="0"><input type="checkbox" name="' . $h($name) . '" value="1"' . ($on ? ' checked' : '') . '>'
        . '<span class="bo-ac-switch__track" aria-hidden="true"></span>'
        . ($icon !== '' ? '<span class="bo-ac-switch__ico">' . $ico($icon, 18) . '</span>' : '')
        . '<span class="bo-ac-switch__txt"><b>' . $h($title) . '</b>' . ($desc !== '' ? '<small>' . $h($desc) . '</small>' : '') . '</span></label>';
};
$on = static fn (string $k): bool => !array_key_exists($k, $ge) || !empty($ge[$k]);

// Journal de connexion
$events = array_values(array_filter($authEvents, static fn ($ev) => in_array((string) ($ev['type'] ?? ''), ['auth', 'phone', 'client_init'], true)));
$evOk = static function (array $ev): bool {
    if (($ev['type'] ?? '') !== 'auth') {
        return true;
    }
    if (isset($ev['meta']['ok'])) {
        return (bool) $ev['meta']['ok'];
    }

    return !isset($ev['meta']['reason']) || in_array((string) $ev['meta']['reason'], ['ok', 'key_regenerated'], true);
};
$failures = count(array_filter($events, static fn ($e) => !$evOk($e)));
$missionRows = array_sum(array_map('intval', array_diff_key($dataSummary, ['activity_events' => 1])));
$modsOn = count(array_filter($modules, static fn ($m) => !empty($m['enabled'])));
$methods = array_filter(['E-mail' => $on('auth_password'), 'Code' => $on('auth_otp'), 'Steam' => $on('auth_steam')]);

$tabs = [
    'demarrage' => ['start', 'Démarrage'],
    'acces' => ['key', 'Clé d’accès'],
    'overwatch-game-experience' => ['game', 'Connexion en jeu'],
    'experience' => ['mission', 'Expérience en mission'],
    'bridge-modules' => ['modules', 'Modules du pont'],
    'marker-icons' => ['map', 'Carte et symboles'],
    'photo-hud' => ['photo', 'Photos terrain'],
    'serveur' => ['server', 'Serveur et consignes'],
    'avance' => ['link', 'Liaison avancée'],
    'donnees' => ['data', 'Maintenance et données'],
];
?>
<div class="bo-ac" id="bo-ac" data-base="<?= $h(rtrim(url(''), '/')) ?>" data-map-id="<?= (int) ($config['default_map_id'] ?? 1) ?>">

  <?php if ($maintOn): ?>
    <div class="bo-ac-banner is-warn" role="status"><?= $ico('wrench') ?><div><b>Maintenance en cours.</b> La Tacmap et la connexion en jeu sont fermées aux opérateurs ; les administrateurs gardent l’accès. <a href="#donnees" data-ac-go="donnees">Lever la maintenance</a></div></div>
  <?php endif; ?>
  <?php if ($newKey): ?>
    <div class="bo-ac-banner is-key" role="alert">
      <?= $ico('key') ?>
      <div class="bo-ac-banner__body">
        <b>Nouvelle clé d’accès — copiez-la maintenant.</b> Elle ne sera plus affichée en entier. Les membres n’en ont pas besoin : « Appairer » la transmet seule.
        <div class="bo-ac-copy"><code id="bo-ac-newkey"><?= $h($newKey) ?></code><button type="button" class="ath-btn ath-btn--solid" data-ac-copy="#bo-ac-newkey">Copier</button></div>
      </div>
    </div>
  <?php endif; ?>

  <div class="ath-kpi-grid bo-ac-kpis">
    <article class="ath-card ath-kpi<?= $hasKey ? ' is-ok' : ($platformKey ? ' is-warn' : ' is-bad') ?>">
      <p class="ath-kpi__label">Clé d’accès</p>
      <p class="ath-kpi__value"><?= $hasKey ? 'Active' : ($platformKey ? 'Plateforme' : 'Aucune') ?></p>
      <p class="ath-kpi__note"><?= $hasKey ? $h($keyPrefix) . '… · ' . $h($fmt($keyAt, 'd/m/Y')) : ($platformKey ? 'clé commune, isolez la vôtre' : '<a href="#acces" data-ac-go="acces">générer une clé</a>') ?></p>
    </article>
    <article class="ath-card ath-kpi">
      <p class="ath-kpi__label">Opérateurs en liaison</p>
      <p class="ath-kpi__value" data-live="ops">—</p>
      <p class="ath-kpi__note">dernière remontée : <span data-live="ago">—</span></p>
    </article>
    <article class="ath-card ath-kpi">
      <p class="ath-kpi__label">Connexion en jeu</p>
      <p class="ath-kpi__value"><?= count($methods) ?> <small>/ 3</small></p>
      <p class="ath-kpi__note"><?= $h(implode(' · ', array_keys($methods))) ?> · pack ≥ <?= $h($ge['min_mod_version'] ?? '1.5.0') ?></p>
    </article>
    <article class="ath-card ath-kpi">
      <p class="ath-kpi__label">Modules du pont</p>
      <p class="ath-kpi__value"><?= $modsOn ?> <small>/ <?= count($modules) ?></small></p>
      <p class="ath-kpi__note">actifs pour la communauté</p>
    </article>
    <article class="ath-card ath-kpi<?= $failures > 0 ? ' is-warn' : '' ?>">
      <p class="ath-kpi__label">Connexions refusées</p>
      <p class="ath-kpi__value"><?= $failures ?></p>
      <p class="ath-kpi__note">sur les <?= count($events) ?> dernières tentatives</p>
    </article>
  </div>

  <div class="bo-ac-layout">
    <nav class="bo-ac-nav" aria-label="Sections">
      <?php foreach ($tabs as $id => [$icon, $label]): ?>
        <a href="#<?= $h($id) ?>" class="bo-ac-nav__a" data-ac-tab="<?= $h($id) ?>"><?= $ico($icon, 18) ?><span><?= $h($label) ?></span></a>
      <?php endforeach; ?>
      <p class="bo-ac-nav__sep">Pages liées</p>
      <a class="bo-ac-nav__a bo-ac-nav__a--ext" href="<?= $h(url('back-office/atak/controle-serveur')) ?>"><?= $ico('shield', 18) ?><span>Contrôle serveur</span></a>
      <a class="bo-ac-nav__a bo-ac-nav__a--ext" href="<?= $h(url('back-office/ressources/atak-mod')) ?>"><?= $ico('download', 18) ?><span>Fichier du mod</span></a>
      <a class="bo-ac-nav__a bo-ac-nav__a--ext" href="<?= $h(url('atak')) ?>"><?= $ico('external', 18) ?><span>Ouvrir la Tacmap</span></a>
    </nav>

    <div class="bo-ac-panels">

<!-- =========================================================== DÉMARRAGE -->
<section class="bo-ac-sec" id="demarrage" data-ac-panel>
  <?= $section('start', 'Mettre l’ATAK en service', 'La Tacmap (carte web) et le mod Arma 3 COMSPEC Overwatch partagent les mêmes positions, marqueurs, messages et comptes rendus. Quatre étapes suffisent pour une nouvelle communauté.') ?>
  <ol class="bo-ac-steps">
    <li class="<?= $hasKey ? 'is-done' : '' ?>">
      <span class="bo-ac-steps__n"><?= $hasKey ? $ico('check', 16) : '1' ?></span>
      <div><b>Générer la clé d’accès</b><p>Elle identifie votre communauté auprès du mod. Une seule fois ; à régénérer seulement en cas de fuite.</p><a href="#acces" data-ac-go="acces">Clé d’accès →</a></div>
    </li>
    <li>
      <span class="bo-ac-steps__n">2</span>
      <div><b>Personnaliser la connexion en jeu</b><p>Nom, image et message de la fenêtre Athena au lancement d’Arma, méthodes de connexion autorisées.</p><a href="#overwatch-game-experience" data-ac-go="overwatch-game-experience">Connexion en jeu →</a></div>
    </li>
    <li>
      <span class="bo-ac-steps__n">3</span>
      <div><b>Envoyer le parcours aux membres</b><p>Chaque joueur installe le mod puis suit « Première liaison » : carte → Appairer → code collé dans Athena. Aucune clé à recopier.</p>
        <div class="bo-ac-copy"><code id="bo-ac-firstlink"><?= $h(url('atak/premiere-liaison')) ?></code><button type="button" class="ath-btn" data-ac-copy="#bo-ac-firstlink">Copier le lien</button></div></div>
    </li>
    <li>
      <span class="bo-ac-steps__n">4</span>
      <div><b>Vérifier la liaison</b><p>Un opérateur connecté apparaît ici dans « Opérateurs en liaison » et sur la Tacmap. En cas d’échec, consultez les tentatives dans « Clé d’accès ».</p><a href="<?= $h(url('atak')) ?>">Ouvrir la Tacmap →</a></div>
    </li>
  </ol>
  <div class="bo-ac-grid bo-ac-grid--3">
    <a class="ath-panel bo-ac-tile" href="<?= $h(url('atak/setup')) ?>"><?= $ico('download') ?><b>Assistant d’installation</b><small>Pour les joueurs : télécharger et activer le mod.</small></a>
    <a class="ath-panel bo-ac-tile" href="<?= $h(url('atak/tuto')) ?>"><?= $ico('tip') ?><b>Guide de connexion</b><small>Le pas-à-pas détaillé, captures à l’appui.</small></a>
    <a class="ath-panel bo-ac-tile" href="<?= $h(url('back-office/atak/briefing-slides')) ?>"><?= $ico('photo') ?><b>Diapositives de briefing</b><small>Images publiées sur le tableau de briefing en jeu.</small></a>
    <a class="ath-panel bo-ac-tile" href="<?= $h(url('back-office/atak/controle-serveur')) ?>"><?= $ico('shield') ?><b>Contrôle serveur</b><small>Règles fines : factions visibles, réseau simulé, ATAK natif.</small></a>
    <a class="ath-panel bo-ac-tile" href="<?= $h(url('back-office/ressources/atak-mod-blocks')) ?>"><?= $ico('warn') ?><b>Restrictions d’accès</b><small>Bloquer un joueur, un poste ou un Steam.</small></a>
    <a class="ath-panel bo-ac-tile" href="<?= $h(url('back-office/renseignement/acces')) ?>"><?= $ico('intel') ?><b>Renseignement classifié</b><small>Codes d’accès temporaires au portail SSE.</small></a>
  </div>
</section>

<!-- =========================================================== CLÉ D’ACCÈS -->
<section class="bo-ac-sec" id="acces" data-ac-panel>
  <?= $section('key', 'Clé d’accès de la communauté', 'Le mod présente cette clé à chaque envoi : elle rattache les données à votre communauté. « Appairer » la transmet automatiquement aux joueurs.') ?>
  <div class="ath-panel bo-ac-card">
    <div class="bo-ac-keyrow">
      <div>
        <p class="bo-ac-label">État</p>
        <?php if ($hasKey): ?>
          <p class="bo-ac-big"><span class="ath-tag ath-tag--ok">Clé active</span> <code><?= $h($keyPrefix) ?>…</code></p>
          <p class="bo-ac-muted">Générée le <?= $h($fmt($keyAt)) ?>. Seul le début est affiché : la clé complète n’est visible qu’à sa création.</p>
        <?php elseif ($platformKey): ?>
          <p class="bo-ac-big"><span class="ath-tag ath-tag--warn">Clé plateforme</span></p>
          <p class="bo-ac-muted">Votre communauté utilise la clé commune de la plateforme. Générez la vôtre pour isoler vos accès.</p>
        <?php else: ?>
          <p class="bo-ac-big"><span class="ath-tag ath-tag--bad">Aucune clé</span></p>
          <p class="bo-ac-muted">Sans clé, ni « Appairer » ni la liaison jeu ne fonctionnent.</p>
        <?php endif; ?>
      </div>
      <form method="post" action="<?= $h($base . '/access-key') ?>" data-ac-confirm="<?= $hasKey ? 'Régénérer la clé ? L’ancienne cessera de fonctionner : chaque joueur devra refaire « Appairer ».' : 'Générer la clé d’accès de la communauté ?' ?>">
        <?= $csrf ?><button type="submit" class="ath-btn ath-btn--solid"><?= $ico('refresh', 16) ?> <?= $hasKey ? 'Régénérer la clé' : 'Générer une clé d’accès' ?></button>
      </form>
    </div>
    <div class="bo-ac-grid bo-ac-grid--2">
      <div>
        <p class="bo-ac-label">Identifiant de communauté</p>
        <div class="bo-ac-copy"><code id="bo-ac-tenant"><?= $tenantId ?></code><button type="button" class="ath-btn" data-ac-copy="#bo-ac-tenant">Copier</button></div>
        <p class="bo-ac-muted">Seulement pour le réglage manuel (Options → Extensions) quand plusieurs communautés partagent la même adresse.</p>
      </div>
      <div>
        <p class="bo-ac-label">Adresse à indiquer dans le mod</p>
        <div class="bo-ac-copy"><code id="bo-ac-url"><?= $h(rtrim((string) ($portalBaseUrl ?? url('')), '/')) ?></code><button type="button" class="ath-btn" data-ac-copy="#bo-ac-url">Copier</button></div>
        <p class="bo-ac-muted">Réglage manuel uniquement ; « Appairer » la renseigne seul.</p>
      </div>
    </div>
  </div>
  <?= $howto('Quand régénérer la clé ?', [
      '<b>En cas de fuite</b> : clé collée sur Discord, capture d’écran partagée, ancien membre qui la connaît.',
      '<b>Ce qui se passe</b> : l’ancienne clé est refusée immédiatement ; les joueurs voient « clé invalide » et refont « Appairer » (moins d’une minute).',
      '<b>À ne pas faire</b> : régénérer pour « réparer » un joueur qui n’arrive pas à se connecter. Regardez d’abord ses tentatives ci-dessous.',
  ]) ?>
  <div class="ath-table-panel">
    <div class="ath-table-toolbar">
      <span class="ath-table-toolbar__title">Tentatives de connexion récentes</span>
      <span class="ath-table-toolbar__count"><?= count($events) ?></span>
      <span class="ath-table-toolbar__spacer"></span>
      <span class="bo-ac-muted">Liaisons en jeu, refus de clé, connexions téléphone. Les secrets ne sont jamais affichés.</span>
    </div>
    <?php if ($events === []): ?>
      <p class="bo-ac-empty">Aucune tentative pour l’instant. Elles apparaissent dès qu’un joueur lance la liaison.</p>
    <?php else: ?>
    <div class="ath-table-wrap"><table class="ath-table bo-ac-table">
      <thead><tr><th>Quand</th><th>Résultat</th><th>Événement</th><th>Opérateur</th></tr></thead>
      <tbody>
      <?php foreach ($events as $ev): $ok = $evOk($ev); ?>
        <tr>
          <td class="bo-ac-mono"><?= $h($fmt($ev['at'] ?? null, 'd/m H:i') ?: '—') ?></td>
          <td><span class="ath-tag ath-tag--<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? 'Accepté' : 'Refusé' ?></span></td>
          <td><?= $h($ev['label'] ?? '') ?></td>
          <td><?= $h(($ev['actor'] ?? '') ?: '—') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
</section>

<!-- =========================================================== CONNEXION EN JEU -->
<?php require __DIR__ . '/_game_experience.php'; ?>

<!-- =========================================================== EXPÉRIENCE EN MISSION -->
<section class="bo-ac-sec" id="experience" data-ac-panel>
  <?= $section('mission', 'Expérience en mission', 'Le ton de la tablette Athena pour toute la communauté. Pour chaque réglage, vous pouvez l’imposer ou laisser chaque joueur choisir dans ses options du mod.') ?>
  <?php if (!$expReady): ?>
    <div class="bo-ac-banner is-bad"><?= $ico('warn') ?><div><b>Fonction indisponible.</b> La base n’est pas à jour pour l’expérience Overwatch : demandez au support plateforme d’appliquer la mise à jour.</div></div>
  <?php else:
      $byId = [];
      foreach ($expCatalog as $row) {
          $byId[(string) ($row['id'] ?? '')] = $row;
      }
      $mode = !empty($byId['realism']['value']) ? 'realism' : (!empty($byId['troll']['value']) ? 'troll' : 'standard');
      ?>
  <form method="post" action="<?= $h($base . '/experience') ?>" class="bo-ac-form" id="bo-ac-exp">
    <?= $csrf ?>
    <div class="ath-panel bo-ac-card">
      <p class="bo-ac-label">Ambiance générale</p>
      <div class="bo-ac-choice" role="radiogroup" aria-label="Ambiance">
        <?php foreach ([
            'standard' => ['mission', 'Standard', 'Tablette complète, aides visuelles et notifications selon les choix des joueurs.'],
            'realism' => ['shield', 'Réalisme MilSim', 'Interface sobre, sans aide superflue : pour les communautés exigeantes. Désactive le mode détente.'],
            'troll' => ['chat', 'Détente', 'Effets et messages légers pour les soirées décontractées. Incompatible avec le réalisme.'],
        ] as $val => [$i, $t, $d]): ?>
          <label class="bo-ac-opt"><input type="radio" name="_mode" value="<?= $h($val) ?>"<?= $mode === $val ? ' checked' : '' ?> data-ac-mode><span class="bo-ac-opt__ico"><?= $ico($i) ?></span><b><?= $h($t) ?></b><small><?= $h($d) ?></small></label>
        <?php endforeach; ?>
      </div>
      <input type="hidden" name="experience_realism" value="<?= $mode === 'realism' ? '1' : '0' ?>" data-ac-mode-realism>
      <input type="hidden" name="experience_troll" value="<?= $mode === 'troll' ? '1' : '0' ?>" data-ac-mode-troll>
    </div>

    <div class="ath-panel bo-ac-card">
      <p class="bo-ac-label">Réglages imposés ou laissés aux joueurs</p>
      <div class="bo-ac-tris">
      <?php foreach ($expCatalog as $row):
          $id = (string) ($row['id'] ?? '');
          if ($id === '' || ($row['type'] ?? '') === 'bool') {
              continue;
          }
          $val = (string) ($row['value'] ?? 'player');
          ?>
        <div class="bo-ac-tri">
          <div class="bo-ac-tri__txt"><b><?= $h($row['label'] ?? $id) ?></b><?php if (($row['description'] ?? '') !== ''): ?><small><?= $h($row['description']) ?></small><?php endif; ?></div>
          <div class="bo-ac-seg" role="radiogroup" aria-label="<?= $h($row['label'] ?? $id) ?>">
            <?php foreach (($row['choices'] ?? []) as $ch): $cv = (string) ($ch['value'] ?? ''); ?>
              <label><input type="radio" name="experience_<?= $h($id) ?>" value="<?= $h($cv) ?>"<?= $val === $cv ? ' checked' : '' ?>><span><?= $h(['player' => 'Choix du joueur', 'on' => 'Imposé : oui', 'off' => 'Imposé : non'][$cv] ?? ($ch['label'] ?? $cv)) ?></span></label>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
      <p class="bo-ac-muted">Factions indépendantes et civiles, réseau simulé, ATAK natif, roleplay : <a href="<?= $h(url('back-office/atak/controle-serveur/regles')) ?>">Contrôle serveur → Règles</a>.</p>
    </div>

    <div class="ath-panel bo-ac-card">
      <label class="bo-ac-field"><span>Consignes ajoutées au guide en jeu</span>
        <textarea name="experience_guide_custom" rows="4" maxlength="2000" placeholder="Ex. Fréquences : 1re section 41.0, PC 45.5 — Modpack « Opé 2026.10 » obligatoire — Toute demande d’appui passe par un 9-line."><?= $h($experienceGuideCustom ?? '') ?></textarea>
        <small>Affiché en mission (journal de bord et aide de la tablette), à la suite du guide standard.</small>
      </label>
      <?php if (($experienceGuide ?? '') !== ''): ?>
        <details class="bo-ac-howto"><summary><?= $ico('eye', 16) ?> Aperçu du guide reçu par les opérateurs</summary><pre class="bo-ac-pre"><?= $h($experienceGuide) ?></pre></details>
      <?php endif; ?>
    </div>
    <div class="bo-ac-save"><span class="bo-ac-muted">Appliqué aux opérateurs en liaison sous une minute.<?= ($experienceUpdatedAt ?? '') !== '' ? ' Dernière modification : ' . $h($fmt($experienceUpdatedAt)) . '.' : '' ?></span><button type="submit" class="ath-btn ath-btn--solid">Enregistrer l’expérience</button></div>
  </form>
  <?php endif; ?>
</section>

<!-- =========================================================== DONNÉES REMONTÉES -->
<?php $baseUrl = url(''); require __DIR__ . '/_data_flows.php'; ?>

<!-- =========================================================== MODULES DU PONT -->
<?php
$moduleGroups = [
    'Carte et navigation' => ['ctab_markers' => 'marker', 'route' => 'route', 'route_planning' => 'route', 'gps_navigation' => 'map', 'geo_places' => 'map', 'geo_roads' => 'route', 'jump' => 'jump'],
    'Capteurs et images' => ['drone' => 'drone', 'video_feeds' => 'camera', 'iceman_photo' => 'photo', 'ecoti_hud' => 'eye', 'weather' => 'weather'],
    'Messages et alertes' => ['iceman_alerts' => 'warn', 'iceman_bda' => 'fire', 'iceman_group' => 'chat', 'comspec_mirror' => 'refresh', 'wave_relay' => 'radio'],
    'Renseignement et rapports' => ['sse_person' => 'intel', 'report_routing' => 'mail', 'jnet_bridge' => 'link'],
];
$modById = [];
foreach ($modules as $m) {
    $modById[(string) ($m['id'] ?? '')] = $m;
}
$placed = array_merge(...array_map('array_keys', array_values($moduleGroups)));
$others = array_diff(array_keys($modById), $placed);
if ($others !== []) {
    $moduleGroups['Autres'] = array_fill_keys($others, 'modules');
}
?>
<section class="bo-ac-sec" id="bridge-modules" data-ac-panel>
  <?= $section('modules', 'Modules du pont ATAK Enhanced / cTab', 'Chaque fonction échangée entre Arma et la carte peut être coupée pour la communauté. Les joueurs en liaison récupèrent l’état sous une minute.') ?>
  <form method="post" action="<?= $h($base . '/modules') ?>" class="bo-ac-form">
    <?= $csrf ?>
    <div class="bo-ac-bulk"><button type="button" class="ath-btn" data-ac-all="1">Tout activer</button><button type="button" class="ath-btn" data-ac-all="0">Tout couper</button><span class="bo-ac-muted"><b data-ac-count><?= $modsOn ?></b> / <?= count($modules) ?> actifs</span></div>
    <?php foreach ($moduleGroups as $group => $ids): $present = array_filter(array_keys($ids), static fn ($id) => isset($modById[$id])); if ($present === []) { continue; } ?>
      <div class="ath-panel bo-ac-card">
        <p class="bo-ac-label"><?= $h($group) ?></p>
        <div class="bo-ac-grid bo-ac-grid--2">
          <?php foreach ($present as $id): $m = $modById[$id]; ?>
            <?= $switch('module_' . $id, !empty($m['enabled']), (string) ($m['label'] ?? $id), (string) ($m['description'] ?? ''), $ids[$id], ' data-ac-module') ?>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <div class="bo-ac-save"><span class="bo-ac-muted"><?= ($bridgeModulesUpdatedAt ?? '') !== '' ? 'Dernière modification : ' . $h($fmt($bridgeModulesUpdatedAt)) . '. ' : '' ?>Aussi réglable depuis <a href="<?= $h(url('back-office/atak/controle-serveur/fonctions')) ?>">Contrôle serveur → Fonctions</a>.</span><button type="submit" class="ath-btn ath-btn--solid">Enregistrer les modules</button></div>
  </form>
</section>

<!-- =========================================================== CARTE ET SYMBOLES -->
<?php
$markerIcons = is_array($markerIcons ?? null) ? $markerIcons : ['library' => [], 'assignments' => []];
$kinds = is_array($markerIconKinds ?? null) ? $markerIconKinds : [];
$lib = is_array($markerIcons['library'] ?? null) ? $markerIcons['library'] : [];
$assign = is_array($markerIcons['assignments'] ?? null) ? $markerIcons['assignments'] : [];
$cdn = function_exists('atak_marker_icons_cdn_base') ? rtrim(atak_marker_icons_cdn_base(), '/') : '';
$libJson = array_map(static fn ($r) => [
    'value' => 'upload:' . (string) ($r['id'] ?? ''),
    'label' => (string) ($r['label'] ?? 'Icône'),
    'url' => function_exists('user_media_public_url') ? (user_media_public_url((string) ($r['url'] ?? '')) ?? '') : (string) ($r['url'] ?? ''),
], $lib);
$kindIcons = ['player' => 'user', 'ai_friend' => 'shield', 'ai_hostile' => 'warn', 'phone' => 'radio', 'vehicle' => 'route', 'air' => 'jump'];
$defaultMap = (string) ($config['default_map_slug'] ?? 'altis');
?>
<section class="bo-ac-sec" id="marker-icons" data-ac-panel>
  <?= $section('map', 'Carte et symboles', 'La carte ouverte par défaut sur la Tacmap et l’apparence des symboles pour toute la communauté.') ?>
  <form method="post" action="<?= $h($base) ?>" class="ath-panel bo-ac-card">
    <?= $csrf ?><input type="hidden" name="_section" value="marker-icons">
    <p class="bo-ac-label">Carte affichée à l’ouverture</p>
    <div class="bo-ac-maps" role="radiogroup" aria-label="Carte par défaut">
      <?php foreach ($atakMaps as $m): $slug = (string) ($m['slug'] ?? ''); ?>
        <label class="bo-ac-map"><input type="radio" name="default_map_slug" value="<?= $h($slug) ?>"<?= $slug === $defaultMap ? ' checked' : '' ?>><span class="bo-ac-map__ico"><?= $ico('map') ?></span><b><?= $h($m['label'] ?? $slug) ?></b><small><?= $h($slug) ?></small></label>
      <?php endforeach; ?>
    </div>
    <p class="bo-ac-muted">Les opérateurs peuvent toujours changer de carte depuis l’en-tête de la Tacmap.</p>
    <div class="bo-ac-save bo-ac-save--inline"><button type="submit" class="ath-btn ath-btn--solid">Enregistrer la carte</button></div>
  </form>

  <form method="post" action="<?= $h($base . '/marker-icons') ?>" class="ath-panel bo-ac-card" id="bo-ac-markers">
    <?= $csrf ?>
    <p class="bo-ac-label">Symbole par type de contact</p>
    <ul class="bo-ac-kinds">
      <?php foreach ($kinds as $kind => $label): $cur = (string) ($assign[$kind] ?? 'nato'); ?>
        <li class="bo-ac-kind" data-ac-kind="<?= $h($kind) ?>">
          <span class="bo-ac-kind__ico"><?= $ico($kindIcons[$kind] ?? 'marker') ?></span>
          <span class="bo-ac-kind__txt"><b><?= $h($label) ?></b><small data-ac-kind-label><?= $cur === 'nato' ? 'Symbole OTAN prévu' : $h($cur) ?></small></span>
          <span class="bo-ac-kind__prev" data-ac-kind-prev><?= $cur === 'nato' ? $ico('shield', 26) : '' ?></span>
          <input type="hidden" name="marker_icon_<?= $h($kind) ?>" value="<?= $h($cur) ?>">
          <button type="button" class="ath-btn" data-ac-pick>Changer</button>
        </li>
      <?php endforeach; ?>
    </ul>
    <div class="bo-ac-save bo-ac-save--inline"><span class="bo-ac-muted">Visible après rechargement de la Tacmap.</span><button type="submit" class="ath-btn ath-btn--solid">Enregistrer les symboles</button></div>
  </form>

  <div class="ath-panel bo-ac-card">
    <p class="bo-ac-label">Vos icônes</p>
    <?php if ($lib === []): ?><p class="bo-ac-muted">Aucune icône envoyée. Exemple : l’écusson de votre section pour vos opérateurs.</p><?php else: ?>
      <ul class="bo-ac-lib">
        <?php foreach ($libJson as $i => $it): ?>
          <li><img src="<?= $h($it['url']) ?>" alt=""><span><?= $h($it['label']) ?></span>
            <form method="post" action="<?= $h($base . '/marker-icons/delete') ?>" data-ac-confirm="Retirer « <?= $h($it['label']) ?> » ? Les types qui l’utilisent reviennent au symbole OTAN."><?= $csrf ?><input type="hidden" name="icon_id" value="<?= $h((string) ($lib[$i]['id'] ?? '')) ?>"><button type="submit" class="bo-ac-x" aria-label="Retirer">×</button></form></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <form method="post" action="<?= $h($base . '/marker-icons/upload') ?>" enctype="multipart/form-data" class="bo-ac-upload">
      <?= $csrf ?>
      <label class="bo-ac-field"><span>Nom affiché</span><input type="text" name="marker_icon_label" maxlength="80" placeholder="Ex. Écusson 1re section" required></label>
      <label class="bo-ac-drop"><input type="file" name="marker_icon_file" accept="image/png,image/jpeg,image/webp" required data-ac-file-preview="#bo-ac-up-prev"><span><?= $ico('download') ?> Choisir une image</span><small>PNG, JPG ou WebP · 2 Mo max. · carré, fond transparent conseillé (64 × 64 px)</small><img id="bo-ac-up-prev" alt="" hidden></label>
      <button type="submit" class="ath-btn">Ajouter à la bibliothèque</button>
    </form>
  </div>

  <dialog class="bo-ac-dialog" id="bo-ac-picker" aria-label="Choisir un symbole">
    <form method="dialog" class="bo-ac-dialog__head"><b>Choisir un symbole</b><input type="search" placeholder="Rechercher : infanterie, médical, embuscade, b_air…" data-ac-pick-search><button class="bo-ac-x" aria-label="Fermer">×</button></form>
    <div class="bo-ac-dialog__body" data-ac-pick-grid></div>
  </dialog>
  <script type="application/json" id="bo-ac-marker-data"><?= json_encode(['cdn' => $cdn, 'library' => $libJson], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
  <script src="<?= $h(asset_url('assets/js/arma-marker-library-index.js')) ?>" defer></script>
</section>

<!-- =========================================================== PHOTOS TERRAIN -->
<?php
$hud = is_array($photoHud ?? null) ? $photoHud : [];
$hudOn = !array_key_exists('enabled', $hud) || !empty($hud['enabled']);
$hudFlags = [
    'show_datetime' => ['clock', 'Date et heure', 'Heure du jeu si disponible, sinon heure UTC du serveur.', !array_key_exists('show_datetime', $hud) || !empty($hud['show_datetime'])],
    'show_callsign' => ['user', 'Indicatif', 'L’indicatif de l’opérateur qui a pris la photo.', !array_key_exists('show_callsign', $hud) || !empty($hud['show_callsign'])],
    'show_device' => ['camera', 'Type de caméra', 'Casque, tablette, drone…', !array_key_exists('show_device', $hud) || !empty($hud['show_device'])],
    'show_grid' => ['map', 'Référence de grille', 'Position MGRS au moment de la capture.', !array_key_exists('show_grid', $hud) || !empty($hud['show_grid'])],
    'show_heading' => ['route', 'Cap', 'Direction de la prise de vue.', !empty($hud['show_heading'])],
    'show_altitude' => ['jump', 'Altitude', 'Utile pour les vues de drone.', !empty($hud['show_altitude'])],
    'show_logo' => ['shield', 'Logo en filigrane', 'Logo de l’unité avec « © unité ».', !array_key_exists('show_logo', $hud) || !empty($hud['show_logo'])],
];
?>
<section class="bo-ac-sec" id="photo-hud" data-ac-panel>
  <?= $section('photo', 'Bandeau des photos terrain', 'Un bandeau façon caméra-piéton est gravé sur les photos envoyées par les opérateurs (casque, tablette, drone) : unité, indicatif, grille, heure. Les photos déjà archivées ne changent pas.') ?>
  <form method="post" action="<?= $h($base . '/photo-hud') ?>" class="bo-ac-form bo-ac-hud" id="bo-ac-hud">
    <?= $csrf ?>
    <div class="bo-ac-hud__cfg">
      <div class="ath-panel bo-ac-card">
        <?= $switch('photo_hud_enabled', $hudOn, 'Graver le bandeau sur les prochaines photos', 'Désactivé : les captures restent brutes, sans identification.', 'photo', ' data-ac-hud="enabled"') ?>
        <div class="bo-ac-grid bo-ac-grid--2">
          <label class="bo-ac-field"><span>Unité ou agence</span><input name="photo_hud_agency" maxlength="80" value="<?= $h($hud['agency'] ?? '') ?>" placeholder="Ex. GIGN — Détachement Alpha" data-ac-hud="agency"><small>Vide : le nom de votre communauté.</small></label>
          <label class="bo-ac-field"><span>Ligne libre</span><input name="photo_hud_custom" maxlength="80" value="<?= $h($hud['custom_line'] ?? '') ?>" placeholder="Ex. OP FORTERESSE" data-ac-hud="custom"><small>Nom d’opération, théâtre, mention d’archivage.</small></label>
        </div>
        <p class="bo-ac-label">Position</p>
        <div class="bo-ac-seg bo-ac-seg--wide">
          <?php foreach (['top' => 'En haut', 'bottom' => 'En bas', 'both' => 'Haut et bas'] as $v => $l): ?><label><input type="radio" name="photo_hud_position" value="<?= $v ?>"<?= ($hud['position'] ?? 'top') === $v ? ' checked' : '' ?> data-ac-hud="position"><span><?= $h($l) ?></span></label><?php endforeach; ?>
        </div>
        <p class="bo-ac-label">Apparence</p>
        <div class="bo-ac-seg bo-ac-seg--wide">
          <?php foreach (['axon' => 'Caméra-piéton (barre sombre, voyant REC)', 'discreet' => 'Discret (barre fine)'] as $v => $l): ?><label><input type="radio" name="photo_hud_style" value="<?= $v ?>"<?= ($hud['style'] ?? 'axon') === $v ? ' checked' : '' ?> data-ac-hud="style"><span><?= $h($l) ?></span></label><?php endforeach; ?>
        </div>
      </div>
      <div class="ath-panel bo-ac-card">
        <p class="bo-ac-label">Informations incrustées</p>
        <div class="bo-ac-grid bo-ac-grid--2">
          <?php foreach ($hudFlags as $k => [$i, $t, $d, $v]): ?><?= $switch('photo_hud_' . $k, $v, $t, $d, $i, ' data-ac-hud="' . $k . '"') ?><?php endforeach; ?>
        </div>
      </div>
    </div>
    <div class="bo-ac-hud__prev">
      <p class="bo-ac-label">Aperçu en direct</p>
      <div class="bo-ac-shot" data-ac-shot>
        <div class="bo-ac-shot__bar bo-ac-shot__bar--top" data-ac-bar="top"><span class="bo-ac-shot__rec">● REC</span><span data-ac-line="agency"><?= $h(($hud['agency'] ?? '') ?: 'VOTRE UNITÉ') ?></span><span data-ac-line="meta"></span></div>
        <div class="bo-ac-shot__logo" data-ac-line="logo">© <?= $h(mb_strtoupper(($hud['agency'] ?? '') ?: 'UNITÉ')) ?></div>
        <div class="bo-ac-shot__bar bo-ac-shot__bar--bottom" data-ac-bar="bottom"><span data-ac-line="geo"></span><span data-ac-line="custom"></span></div>
      </div>
      <p class="bo-ac-muted">Simulation instantanée. Après enregistrement, le rendu réel ci-dessous est recalculé par le serveur.</p>
      <?php if (($photoHudPreview ?? '') !== ''): ?><img class="bo-ac-real" src="<?= $h($photoHudPreview) ?>" alt="Rendu réel du bandeau enregistré"><?php endif; ?>
    </div>
    <div class="bo-ac-save"><span class="bo-ac-muted">S’applique aux photos reçues après l’enregistrement (envois de photos terrain).</span><button type="submit" class="ath-btn ath-btn--solid">Enregistrer le bandeau</button></div>
  </form>
</section>

<!-- =========================================================== SERVEUR ET CONSIGNES -->
<section class="bo-ac-sec" id="serveur" data-ac-panel>
  <?= $section('server', 'Serveur Arma et consignes', 'Ce que vos membres lisent sur la Tacmap et dans l’assistant d’installation pour rejoindre vos sessions.') ?>
  <form method="post" action="<?= $h($base) ?>" class="bo-ac-form">
    <?= $csrf ?><input type="hidden" name="_section" value="serveur">
    <div class="ath-panel bo-ac-card">
      <p class="bo-ac-label">Serveur de jeu</p>
      <div class="bo-ac-grid bo-ac-grid--host">
        <label class="bo-ac-field"><span>Adresse</span><input name="arma_server_host" maxlength="190" value="<?= $h($config['arma_server_host'] ?? '') ?>" placeholder="Ex. arma.macommunaute.fr ou 51.38.12.40"></label>
        <label class="bo-ac-field"><span>Port</span><input name="arma_server_port" type="number" min="1" max="65535" value="<?= $h($config['arma_server_port'] ?? '') ?>" placeholder="2302"></label>
      </div>
      <p class="bo-ac-muted">Affiché sur la Tacmap et dans le panneau compte. Port par défaut d’Arma 3 : 2302.</p>
    </div>
    <div class="ath-panel bo-ac-card">
      <label class="bo-ac-field"><span>Consignes d’installation du mod</span>
        <textarea name="arma_mod_credentials" rows="5" placeholder="Ex.&#10;• Modpack : « Opé 2026.10 » (collection Steam épinglée sur Discord)&#10;• Mot de passe serveur : demandé en briefing&#10;• Overwatch : version 1.6 minimum"><?= $h($config['arma_mod_credentials'] ?? '') ?></textarea>
        <small>Montré sur la Tacmap (configuration jeu) et dans l’assistant d’installation. <b>Jamais de secret ici</b> : la clé d’accès se gère dans son onglet.</small>
      </label>
      <label class="bo-ac-field"><span>Instructions d’équipe</span>
        <textarea name="instructions" rows="5" placeholder="Ex.&#10;• Canaux : 1re section 41.0 — PC 45.5&#10;• Toute demande d’appui feu passe par un 9-line&#10;• Entraînement le mercredi 21 h"><?= $h($config['instructions'] ?? '') ?></textarea>
        <small>Visible sur la Tacmap par toute l’équipe : procédures, liens utiles, rappels.</small>
      </label>
    </div>
    <div class="bo-ac-save"><span class="bo-ac-muted">Visible immédiatement sur la Tacmap.</span><button type="submit" class="ath-btn ath-btn--solid">Enregistrer</button></div>
  </form>
</section>

<!-- =========================================================== LIAISON AVANCÉE -->
<?php $jwtSet = trim((string) ($config['jwt_secret'] ?? '')) !== ''; ?>
<section class="bo-ac-sec" id="avance" data-ac-panel>
  <?= $section('link', 'Liaison avancée', 'Santé de la synchronisation en direct, et deux réglages réservés aux hébergements particuliers. Dans 99 % des cas, ne touchez à rien ici.') ?>
  <div class="ath-panel bo-ac-card">
    <p class="bo-ac-label">Santé de la synchronisation <span class="bo-ac-live">en direct · 10 s</span></p>
    <div class="bo-ac-health">
      <div><small>Dernière remontée</small><b data-live="ago">—</b></div>
      <div><small>Débit</small><b data-live="kbps">—</b></div>
      <div><small>Volume 15 min</small><b data-live="vol">—</b></div>
      <div><small>Opérateurs</small><b data-live="ops">—</b></div>
      <div><small>Repères carte</small><b data-live="mk">—</b></div>
      <div><small>Photos 15 min</small><b data-live="photo">—</b></div>
    </div>
  </div>
  <form method="post" action="<?= $h($base) ?>" class="bo-ac-form">
    <?= $csrf ?><input type="hidden" name="_section" value="avance">
    <div class="ath-panel bo-ac-card">
      <label class="bo-ac-field"><span>Adresse du service de liaison</span>
        <input type="url" name="node_url" value="<?= $h($config['node_url'] ?? '') ?>" placeholder="Vide : ce site (https://<?= $h($hostHint) ?>)">
        <small>À renseigner seulement si votre hébergeur impose un domaine ou un port dédié au service temps réel. Exemple : <code>https://live.macommunaute.fr:8443</code>.</small>
      </label>
      <label class="bo-ac-field"><span>Secret de signature des jetons Tacmap <?= $jwtSet ? '<span class="ath-tag ath-tag--ok">défini</span>' : '<span class="ath-tag ath-tag--neut">valeur de la plateforme</span>' ?></span>
        <input type="password" name="jwt_secret" autocomplete="new-password" value="" placeholder="<?= $jwtSet ? 'Laisser vide pour conserver le secret actuel' : 'Vide : secret global de la plateforme' ?>">
        <small>Signe le jeton remis à la page Tacmap. Utile seulement avec un service de liaison auto-hébergé qui vérifie ce jeton ; le secret n’est jamais réaffiché.</small>
      </label>
      <?php if ($jwtSet): ?><label class="bo-ac-check"><input type="checkbox" name="jwt_secret_clear" value="1"> Effacer le secret et revenir à celui de la plateforme</label><?php endif; ?>
    </div>
    <div class="bo-ac-save"><span class="bo-ac-muted">Une adresse invalide est refusée à l’enregistrement.</span><button type="submit" class="ath-btn ath-btn--solid">Enregistrer</button></div>
  </form>
</section>

<!-- =========================================================== MAINTENANCE ET DONNÉES -->
<?php
$tableLabels = [
    'atak_markers' => 'Marqueurs', 'atak_units' => 'Unités', 'atak_chat_messages' => 'Messages', 'atak_orders' => 'Ordres',
    'atak_pings' => 'Pings', 'atak_nine_line' => '9-line', 'atak_map_shapes' => 'Tracés', 'atak_poi' => 'Points d’intérêt',
    'atak_intel_photos' => 'Photos renseignement', 'recon_images' => 'Photos terrain', 'atak_tactical_reports' => 'Comptes rendus tactiques',
    'atak_vehicle_tracking' => 'Véhicules suivis', 'atak_medevac_requests' => 'MEDEVAC', 'atak_waypoints' => 'Points de passage',
];
$rows = $dataSummary;
unset($rows['activity_events']);
arsort($rows);
$rows = array_filter($rows, static fn ($n) => (int) $n > 0);
?>
<section class="bo-ac-sec" id="donnees" data-ac-panel>
  <?= $section('data', 'Maintenance et données', 'Fermer temporairement la carte et la liaison jeu, exporter l’historique, ou repartir de zéro avant une nouvelle campagne.') ?>
  <div class="ath-panel bo-ac-card<?= $maintOn ? ' is-warn' : '' ?>">
    <?php if (!$maintReady): ?>
      <div class="bo-ac-banner is-bad"><?= $ico('warn') ?><div><b>Indisponible.</b> La mise à jour qui active la maintenance n’est pas appliquée sur ce serveur : demandez-la au support plateforme.</div></div>
    <?php else: ?>
    <form method="post" action="<?= $h($base . '/maintenance') ?>" class="bo-ac-form">
      <?= $csrf ?>
      <?= $switch('maintenance_enabled', $maintOn, 'Mode maintenance', 'Les opérateurs ne peuvent plus ouvrir la Tacmap, envoyer de données ni se connecter en jeu. Les administrateurs gardent l’accès pour tester.', 'wrench') ?>
      <label class="bo-ac-field"><span>Message affiché aux opérateurs</span>
        <textarea name="maintenance_message" rows="2" maxlength="500" placeholder="Ex. Mise à jour du serveur jusqu’à 21 h 00 Zulu. Rendez-vous sur Discord pour les nouvelles."><?= $h($maintMsg) ?></textarea></label>
      <div class="bo-ac-save bo-ac-save--inline"><button type="submit" class="ath-btn ath-btn--solid">Enregistrer la maintenance</button></div>
    </form>
    <?php endif; ?>
  </div>

  <div class="ath-panel bo-ac-card">
    <div class="bo-ac-keyrow">
      <div><p class="bo-ac-label">Données conservées</p><p class="bo-ac-big"><?= (int) ($dataSummary['activity_events'] ?? 0) ?> <small>entrées de journal</small> · <?= $missionRows ?> <small>enregistrements de mission</small></p></div>
      <a class="ath-btn" href="<?= $h($base . '/export') ?>"><?= $ico('download', 16) ?> Exporter tout (JSON)</a>
    </div>
    <?php if ($rows !== []): ?>
      <ul class="bo-ac-datalist">
        <?php foreach (array_slice($rows, 0, 12, true) as $t => $n): ?><li><span><?= $h($tableLabels[$t] ?? str_replace(['atak_', '_'], ['', ' '], $t)) ?></span><b><?= (int) $n ?></b></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <form method="post" action="<?= $h($base . '/purge') ?>" class="ath-panel bo-ac-card bo-ac-danger" data-ac-purge="<?= $h($purgeWord) ?>">
    <?= $csrf ?>
    <p class="bo-ac-label">Effacer définitivement</p>
    <p>Journal, unités, messages, ordres, pings, photos, tracés et comptes rendus tactiques de la communauté. <b>Conservés</b> : configuration, clé d’accès, indicatifs liés aux comptes.</p>
    <ol class="bo-ac-mini-steps"><li>Exportez d’abord (bouton ci-dessus).</li><li>Saisissez <code><?= $h($purgeWord) ?></code>.</li><li>Confirmez.</li></ol>
    <label class="bo-ac-field"><span>Mot de confirmation</span><input name="confirm_phrase" autocomplete="off" placeholder="<?= $h($purgeWord) ?>" data-ac-purge-input></label>
    <label class="bo-ac-check"><input type="checkbox" name="unlink_steam" value="1"> Désynchroniser aussi tous les comptes Steam (chaque opérateur devra refaire sa liaison)</label>
    <button type="submit" class="ath-btn bo-ac-btn-danger" disabled data-ac-purge-btn><?= $ico('trash', 16) ?> Tout effacer</button>
  </form>
</section>

    </div>
  </div>
</div>
<script src="<?= $h(asset_url('assets/js/back-office-atak-config.js')) ?>" defer></script>
