<?php
declare(strict_types=1);

/**
 * Back-office — Configuration réalisme ATAK (/back-office/ressources/atak-realism/config).
 * Formulaire généré depuis config/realism-schema.json (un onglet par domaine), enregistré en une fois
 * (POST …/atak-realism/save, champs cfg[domaine][paramètre]). Chaque réglage affiche où il agit réellement
 * (App\Support\AtakRealismWiring). Interactions : back-office-atak-realism.js · styles : back-office-atak-realism.css.
 *
 * @var array<string, mixed> $schema
 * @var array<string, array<string, mixed>> $config  configuration active complétée par les défauts
 * @var array<string, mixed> $configMeta
 * @var list<array<string, mixed>> $history
 */
use App\Support\AtakRealismWiring as Wiring;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$schema = is_array($schema ?? null) ? $schema : ['domains' => [], 'profiles' => []];
$config = is_array($config ?? null) ? $config : [];
$meta = is_array($configMeta ?? null) ? $configMeta : [];
$history = is_array($history ?? null) ? $history : [];
$base = url('back-office/ressources/atak-realism');
$csrf = '<input type="hidden" name="_csrf_token" value="' . $h($csrfToken ?? '') . '">';
$fmt = static function (mixed $raw): string {
    $t = is_string($raw) && $raw !== '' ? strtotime($raw) : false;

    return $t ? date('d/m/Y H:i', $t) : '—';
};
$clean = static fn (string $label): string => trim((string) preg_replace('/^[^\p{L}\p{N}]+/u', '', $label));

$domains = $schema['domains'] ?? [];
uasort($domains, static fn ($a, $b) => ((int) ($a['order'] ?? 99)) <=> ((int) ($b['order'] ?? 99)));
$counts = Wiring::counts($schema);
$total = array_sum($counts);
$effective = $counts[Wiring::GAME] + $counts[Wiring::WEB] + $counts[Wiring::PARTIAL];

$icons = [
    'radio_relays' => '<path d="M12 12v9"/><circle cx="12" cy="10" r="2"/><path d="M7.5 5.5a6.5 6.5 0 0 0 0 9M16.5 5.5a6.5 6.5 0 0 1 0 9M4.5 3a10 10 0 0 0 0 14M19.5 3a10 10 0 0 1 0 14"/>',
    'zones_roleplay' => '<path d="M9 4 3 6.5v13.5L9 17.5l6 2.5 6-2.5V4l-6 2.5L9 4Z"/><circle cx="15" cy="11" r="2.5"/>',
    'network_simulation' => '<path d="M2.5 9a14 14 0 0 1 19 0M5.5 12.5a9.5 9.5 0 0 1 13 0M8.5 16a5 5 0 0 1 7 0"/><path d="m3 3 18 18"/>',
    'certificates' => '<path d="M12 3 4.5 6v5.5c0 4.5 3.2 8.3 7.5 9.5 4.3-1.2 7.5-5 7.5-9.5V6L12 3Z"/><path d="m9 12 2 2 4-4"/>',
    'terminal_damage' => '<rect x="6" y="2.5" width="12" height="19"/><path d="m9 8 3 3-2 3 3 3"/>',
    'waypoints' => '<circle cx="6" cy="18" r="2.5"/><circle cx="18" cy="6" r="2.5"/><path d="M8.5 18H15a3 3 0 0 0 0-6H9a3 3 0 0 1 0-6h6.5"/>',
    'symbology_map' => '<rect x="3" y="6" width="18" height="12"/><path d="m3 6 18 12M21 6 3 18"/>',
    'control_measures' => '<path d="M4 20V4l10 4-10 4"/><path d="M14 20h6M17 17v6"/>',
    'coverage_viewshed' => '<path d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="3"/>',
    'experience_ambiance' => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3.5"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/>',
    'other_settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 2.5v3M12 18.5v3M2.5 12h3M18.5 12h3M5.3 5.3l2.1 2.1M16.6 16.6l2.1 2.1M5.3 18.7l2.1-2.1M16.6 7.4l2.1-2.1"/>',
    'history' => '<path d="M3.5 12a8.5 8.5 0 1 0 2.5-6"/><path d="M3 3v4.5h4.5M12 7.5V12l3 2"/>',
];
$ico = static fn (string $k, int $s = 20): string => '<svg class="bo-rl-ico" viewBox="0 0 24 24" width="' . $s . '" height="' . $s . '" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="square" aria-hidden="true">' . ($icons[$k] ?? $icons['other_settings']) . '</svg>';

// Valeur lisible (défaut, sortie des curseurs).
$display = static function (mixed $v, array $p): string {
    $type = (string) ($p['type'] ?? 'text');
    $unit = (string) ($p['unit'] ?? '');
    if ($type === 'toggle') {
        return $v ? 'activé' : 'désactivé';
    }
    if ($type === 'dropdown') {
        foreach ($p['options'] ?? [] as $o) {
            if ((string) ($o['value'] ?? '') === (string) $v) {
                return (string) ($o['label'] ?? $v);
            }
        }

        return (string) $v;
    }
    if ($type === 'multi-select') {
        return is_array($v) && $v !== [] ? implode(', ', $v) : 'aucun';
    }
    if (is_numeric($v) && $unit === '%' && (float) ($p['max'] ?? 100) <= 1) {
        return round((float) $v * 100) . ' %';
    }
    if (is_numeric($v)) {
        return rtrim(rtrim(number_format((float) $v, 2, ',', ' '), '0'), ',') . ($unit !== '' ? ' ' . $unit : '');
    }

    return (string) $v;
};

$profiles = is_array($schema['profiles'] ?? null) ? $schema['profiles'] : [];
$profileTone = ['beginner' => 'ok', 'event' => 'warn', 'expert' => 'bad'];
$schemaJson = json_encode(['profiles' => $profiles], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
?>
<div class="bo-rl" id="bo-rl">

  <div class="ath-kpi-grid bo-rl-kpis">
    <article class="ath-card ath-kpi">
      <p class="ath-kpi__label">Version active</p>
      <p class="ath-kpi__value bo-rl-kpi-text"><?= $h($meta['name'] ?? '') !== '' ? $h(mb_strimwidth((string) $meta['name'], 0, 38, '…')) : 'Par défaut' ?></p>
      <p class="ath-kpi__note">modifiée le <?= $h($fmt($meta['updated_at'] ?? null)) ?></p>
    </article>
    <article class="ath-card ath-kpi is-ok">
      <p class="ath-kpi__label">Appliqués en jeu</p>
      <p class="ath-kpi__value"><?= (int) $counts[Wiring::GAME] ?> <small>/ <?= $total ?></small></p>
      <p class="ath-kpi__note">lus par le pack Overwatch</p>
    </article>
    <article class="ath-card ath-kpi">
      <p class="ath-kpi__label">Portail et partiels</p>
      <p class="ath-kpi__value"><?= (int) ($counts[Wiring::WEB] + $counts[Wiring::PARTIAL]) ?></p>
      <p class="ath-kpi__note"><?= (int) $counts[Wiring::WEB] ?> sur le portail · <?= (int) $counts[Wiring::PARTIAL] ?> partiels</p>
    </article>
    <article class="ath-card ath-kpi<?= $counts[Wiring::NONE] > 0 ? ' is-warn' : '' ?>">
      <p class="ath-kpi__label">Sans effet pour l’instant</p>
      <p class="ath-kpi__value"><?= (int) $counts[Wiring::NONE] ?></p>
      <p class="ath-kpi__note"><?= (int) $counts[Wiring::ELSEWHERE] ?> autres se règlent dans Roleplay</p>
    </article>
  </div>

  <details class="ath-panel bo-rl-howto">
    <summary><?= $ico('other_settings', 16) ?> Comment ça marche</summary>
    <ol>
      <li><b>Une seule configuration par communauté.</b> Chaque enregistrement crée une nouvelle version : l’onglet « Historique » permet de revenir en arrière.</li>
      <li><b>Le pack Overwatch la relit seul</b> (GET <code>/api/atak/realism/config</code>, mis en cache quelques minutes par le mod). Rien à redémarrer.</li>
      <li><b>Chaque réglage dit où il agit</b> : <span class="ath-tag ath-tag--ok">Appliqué en jeu</span> <span class="ath-tag ath-tag--info">Appliqué sur le portail</span> <span class="ath-tag ath-tag--warn">Partiellement appliqué</span> <span class="ath-tag ath-tag--neut">Réglé ailleurs</span> <span class="ath-tag ath-tag--neut">Pas encore pris en compte</span>. Cochez « Masquer les réglages sans effet » pour n’afficher que ce qui change quelque chose.</li>
      <li><b>Un profil remplit tout d’un coup</b> (Débutant, Événement, Expert) : vérifiez puis enregistrez. Exemple : une soirée d’initiation en « Débutant », une opération milsim en « Expert ».</li>
    </ol>
  </details>

  <?php if ($profiles !== []): ?>
  <section class="bo-rl-profiles" aria-label="Profils">
    <?php foreach ($profiles as $key => $pr): ?>
      <article class="ath-panel bo-rl-profile is-<?= $h($profileTone[$key] ?? 'neut') ?>">
        <p class="bo-rl-profile__t"><?= $h($clean((string) ($pr['label'] ?? $key))) ?></p>
        <p class="bo-rl-profile__d"><?= $h($pr['description'] ?? '') ?></p>
        <?php if (!empty($pr['recommended_for'])): ?><p class="bo-rl-profile__for">Pour : <?= $h($pr['recommended_for']) ?></p><?php endif; ?>
        <button type="button" class="ath-btn" data-rl-profile="<?= $h($key) ?>">Appliquer ce profil</button>
      </article>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <div class="bo-rl-layout">
    <nav class="bo-rl-nav" aria-label="Domaines">
      <?php foreach ($domains as $dk => $dom): $n = count($dom['parameters'] ?? []); $live = 0; foreach (array_keys($dom['parameters'] ?? []) as $pk) { $st = Wiring::of((string) $dk, (string) $pk)['status']; $live += in_array($st, [Wiring::GAME, Wiring::WEB, Wiring::PARTIAL], true) ? 1 : 0; } ?>
        <a href="#<?= $h($dk) ?>" class="bo-rl-nav__a" data-rl-tab="<?= $h($dk) ?>"><?= $ico((string) $dk, 18) ?><span><?= $h($clean((string) ($dom['label'] ?? $dk))) ?></span><small class="<?= $live > 0 ? 'is-live' : '' ?>" title="<?= $live ?> réglage(s) avec effet sur <?= $n ?>"><?= $live ?>/<?= $n ?></small></a>
      <?php endforeach; ?>
      <a href="#historique" class="bo-rl-nav__a" data-rl-tab="historique"><?= $ico('history', 18) ?><span>Historique</span><small><?= count($history) ?></small></a>
      <label class="bo-rl-nav__filter"><input type="checkbox" data-rl-hide-dead> Masquer les réglages sans effet</label>
    </nav>

    <div class="bo-rl-panels">
      <form method="post" action="<?= $h($base . '/save') ?>" id="bo-rl-form" class="bo-rl-form">
        <?= $csrf ?>
        <input type="hidden" name="_tab" value="" data-rl-current-tab>

        <?php foreach ($domains as $dk => $dom): $dk = (string) $dk; ?>
        <section class="bo-rl-sec" id="<?= $h($dk) ?>" data-rl-panel>
          <header class="bo-rl-sec__head">
            <span class="bo-rl-sec__ico"><?= $ico($dk, 22) ?></span>
            <div>
              <h2 class="bo-rl-sec__t"><?= $h($clean((string) ($dom['label'] ?? $dk))) ?></h2>
              <p class="bo-rl-sec__lead"><?= $h($dom['description'] ?? '') ?></p>
            </div>
          </header>
          <div class="ath-panel bo-rl-card">
          <?php foreach (($dom['parameters'] ?? []) as $pk => $p):
              $pk = (string) $pk;
              $type = (string) ($p['type'] ?? 'text');
              $id = 'rl-' . $dk . '-' . $pk;
              $name = 'cfg[' . $dk . '][' . $pk . ']';
              $val = $config[$dk][$pk] ?? ($p['default'] ?? null);
              $def = $p['default'] ?? null;
              $w = Wiring::of($dk, $pk);
              $dep = (string) ($p['depends_on'] ?? '');
              $unit = (string) ($p['unit'] ?? '');
              $pct = $unit === '%' && (float) ($p['max'] ?? 100) <= 1;
              $isDefault = $val === $def || (is_numeric($val) && is_numeric($def) && (float) $val === (float) $def);
              ?>
            <div class="bo-rl-param<?= $w['status'] === Wiring::NONE ? ' is-dead' : '' ?>" data-rl-param="<?= $h($dk . '.' . $pk) ?>" data-rl-type="<?= $h($type) ?>" data-rl-status="<?= $h($w['status']) ?>" data-rl-default="<?= $h((string) json_encode($def, JSON_UNESCAPED_UNICODE)) ?>"<?= $dep !== '' ? ' data-rl-depends="' . $h($dk . '.' . $dep) . '"' : '' ?>>
              <div class="bo-rl-param__txt">
                <label for="<?= $h($id) ?>" class="bo-rl-param__label"><?= $h($p['label'] ?? $pk) ?></label>
                <span class="ath-tag ath-tag--<?= $h($w['tone']) ?> bo-rl-wire" title="<?= $h($w['note']) ?>"><?= $h($w['label']) ?></span>
                <?php if (!empty($p['description'])): ?><p class="bo-rl-param__desc"><?= $h($p['description']) ?></p><?php endif; ?>
                <?php if (!empty($p['help'])): ?><p class="bo-rl-param__help"><?= $h($p['help']) ?></p><?php endif; ?>
                <?php if ($w['status'] !== Wiring::GAME): ?><p class="bo-rl-param__wire"><?= $h($w['note']) ?><?= $w['status'] === Wiring::ELSEWHERE ? ' <a href="' . $h(url('back-office/atak/roleplay')) . '">Ouvrir Roleplay</a>' : '' ?></p><?php endif; ?>
              </div>
              <div class="bo-rl-param__ctl">
                <?php if ($type === 'toggle'): ?>
                  <label class="bo-rl-switch"><input type="hidden" name="<?= $h($name) ?>" value="0"><input type="checkbox" id="<?= $h($id) ?>" name="<?= $h($name) ?>" value="1"<?= $val ? ' checked' : '' ?>><span class="bo-rl-switch__track" aria-hidden="true"></span><span class="bo-rl-switch__state" data-on="Activé" data-off="Désactivé"></span></label>
                <?php elseif ($type === 'slider'): ?>
                  <div class="bo-rl-range">
                    <input type="range" id="<?= $h($id) ?>" name="<?= $h($name) ?>" min="<?= $h($p['min'] ?? 0) ?>" max="<?= $h($p['max'] ?? 100) ?>" step="<?= $h($p['step'] ?? 1) ?>" value="<?= $h($val) ?>" data-rl-unit="<?= $h($unit) ?>"<?= $pct ? ' data-rl-pct' : '' ?>>
                    <output for="<?= $h($id) ?>" class="bo-rl-range__out"><?= $h($display($val, $p)) ?></output>
                  </div>
                  <p class="bo-rl-range__scale"><span><?= $h($display($p['min'] ?? 0, $p)) ?></span><span><?= $h($display($p['max'] ?? 100, $p)) ?></span></p>
                <?php elseif ($type === 'number'): ?>
                  <div class="bo-rl-num"><input type="number" id="<?= $h($id) ?>" name="<?= $h($name) ?>" value="<?= $h($val) ?>"<?= isset($p['validation']['min']) ? ' min="' . $h($p['validation']['min']) . '"' : '' ?><?= isset($p['validation']['max']) ? ' max="' . $h($p['validation']['max']) . '"' : '' ?> step="<?= $h($p['step'] ?? 1) ?>"><?php if ($unit !== ''): ?><span><?= $h($unit) ?></span><?php endif; ?></div>
                <?php elseif ($type === 'dropdown'): $opts = $p['options'] ?? []; ?>
                  <?php if (count($opts) <= 3): ?>
                    <div class="bo-rl-seg" role="radiogroup" aria-label="<?= $h($p['label'] ?? $pk) ?>">
                      <?php foreach ($opts as $i => $o): $ov = (string) ($o['value'] ?? ''); ?>
                        <label><input type="radio" <?= $i === 0 ? 'id="' . $h($id) . '" ' : '' ?>name="<?= $h($name) ?>" value="<?= $h($ov) ?>"<?= (string) $val === $ov ? ' checked' : '' ?>><span><?= $h($o['label'] ?? $ov) ?></span></label>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <select id="<?= $h($id) ?>" name="<?= $h($name) ?>">
                      <?php foreach ($opts as $o): $ov = (string) ($o['value'] ?? ''); ?><option value="<?= $h($ov) ?>"<?= (string) $val === $ov ? ' selected' : '' ?>><?= $h($o['label'] ?? $ov) ?></option><?php endforeach; ?>
                    </select>
                  <?php endif; ?>
                <?php elseif ($type === 'color'): ?>
                  <div class="bo-rl-color"><input type="color" id="<?= $h($id) ?>" name="<?= $h($name) ?>" value="<?= $h($val) ?>"><code data-rl-hex><?= $h(strtoupper((string) $val)) ?></code><span class="bo-rl-color__sample" style="--c: <?= $h($val) ?>" data-rl-sample>▲ ■ ●</span></div>
                <?php elseif ($type === 'multi-select'): $vals = is_array($val) ? array_map('strval', $val) : []; ?>
                  <div class="bo-rl-pills">
                    <input type="hidden" name="<?= $h($name) ?>[]" value="">
                    <?php foreach (($p['options'] ?? []) as $i => $o): $ov = (string) ($o['value'] ?? ''); ?>
                      <label><input type="checkbox" <?= $i === 0 ? 'id="' . $h($id) . '" ' : '' ?>name="<?= $h($name) ?>[]" value="<?= $h($ov) ?>"<?= in_array($ov, $vals, true) ? ' checked' : '' ?>><span><?= $h($o['label'] ?? $ov) ?></span></label>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <input type="text" id="<?= $h($id) ?>" name="<?= $h($name) ?>" value="<?= $h($val) ?>" placeholder="<?= $h($def) ?>" maxlength="<?= (int) ($p['validation']['maxLength'] ?? 80) ?>">
                <?php endif; ?>
                <div class="bo-rl-param__def">
                  <span>Défaut : <?= $h($display($def, $p)) ?></span>
                  <button type="button" class="bo-rl-reset" data-rl-reset<?= $isDefault ? ' hidden' : '' ?> title="Revenir à la valeur par défaut">↺ Défaut</button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
          </div>
        </section>
        <?php endforeach; ?>

        <div class="bo-rl-save">
          <span class="bo-rl-save__state" data-rl-dirty>Aucune modification</span>
          <label class="bo-rl-save__name"><span class="bo-rl-vh">Nom de la version</span><input type="text" name="name" maxlength="120" placeholder="Nom de la version (facultatif) — ex. Opération Neptune, relais 1,5 km"></label>
          <button type="submit" class="ath-btn ath-btn--solid">Enregistrer la configuration</button>
        </div>
      </form>

      <section class="bo-rl-sec" id="historique" data-rl-panel>
        <header class="bo-rl-sec__head">
          <span class="bo-rl-sec__ico"><?= $ico('history', 22) ?></span>
          <div><h2 class="bo-rl-sec__t">Historique des versions</h2><p class="bo-rl-sec__lead">Chaque enregistrement est conservé. Restaurer une version la recopie comme nouvelle version active ; rien n’est effacé.</p></div>
        </header>
        <div class="ath-table-panel">
          <div class="ath-table-toolbar"><span class="ath-table-toolbar__title">Versions</span><span class="ath-table-toolbar__count"><?= count($history) ?></span></div>
          <?php if ($history === []): ?>
            <p class="bo-rl-empty">Aucune version enregistrée pour l’instant.</p>
          <?php else: ?>
          <div class="ath-table-wrap"><table class="ath-table bo-rl-table">
            <thead><tr><th>Version</th><th>Enregistrée le</th><th>Par</th><th>État</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($history as $row): $active = !empty($row['is_active']); ?>
              <tr>
                <td><b><?= $h(($row['config_name'] ?? '') ?: 'Sans nom') ?></b> <span class="bo-rl-muted">#<?= (int) ($row['id'] ?? 0) ?></span></td>
                <td class="bo-rl-mono"><?= $h($fmt($row['updated_at'] ?? $row['created_at'] ?? null)) ?></td>
                <td><?= !empty($row['updated_by']) ? 'membre #' . (int) $row['updated_by'] : '—' ?></td>
                <td><span class="ath-tag ath-tag--<?= $active ? 'ok' : 'neut' ?>"><?= $active ? 'Active' : 'Archivée' ?></span></td>
                <td class="bo-rl-right"><?php if (!$active): ?>
                  <form method="post" action="<?= $h($base . '/version/' . (int) $row['id'] . '/restaurer') ?>" data-rl-confirm="Restaurer cette version ? Elle deviendra la configuration active."><?= $csrf ?><button type="submit" class="ath-btn">Restaurer</button></form>
                <?php endif; ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table></div>
          <?php endif; ?>
        </div>
      </section>
    </div>
  </div>
</div>
<script type="application/json" id="bo-rl-data"><?= $schemaJson !== false ? $schemaJson : '{}' ?></script>
<script src="<?= $h(asset_url('assets/js/back-office-atak-realism.js')) ?>" defer></script>
