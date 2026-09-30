<?php
declare(strict_types=1);

/**
 * ORBAT dépliable du tableau de bord — vue RH avec portraits.
 *
 * @var array{root: array<string,mixed>, total_units: int, total_members: int, viewer_unit_ids?: list<int>, focus_unit_id?: int}|null $dashboard_orbat
 * @var string|null $dashboard_tenant_label
 * @var bool|null $can_view_orbat
 */

$pack = is_array($dashboard_orbat ?? null) ? $dashboard_orbat : null;
$canViewOrbat = !empty($can_view_orbat) || $pack !== null;
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$unitLabel = trim((string) ($dashboard_tenant_label ?? ''));
if ($unitLabel === '') {
    $unitLabel = function_exists('i18n_phrase') ? i18n_phrase('nav', 'Communauté') : 'Communauté';
}

if ($pack === null || !is_array($pack['root'] ?? null)) {
    if (!$canViewOrbat) {
        return;
    }
    ?>
<section class="dash-orbat dash-orbat--empty" id="organisation" aria-labelledby="dash-orbat-heading">
    <div class="dash-orbat__frame">
        <header class="dash-orbat__head">
            <div>
                <p class="dash-orbat__kicker">Organisation RH</p>
                <h2 id="dash-orbat-heading" class="dash-orbat__title">Chaîne de commandement</h2>
                <p class="dash-orbat__lead">
                    Aucune structure ORBAT n’est encore publiée pour <?= $h($unitLabel) ?>.
                </p>
            </div>
            <div class="dash-orbat__actions">
                <a class="dash-orbat__btn dash-orbat__btn--solid" href="<?= $h(url('orbat')) ?>">Ouvrir l’ORBAT</a>
            </div>
        </header>
        <div class="dash-orbat__empty">
            <p class="dash-orbat__empty-title">Structure en attente</p>
            <p class="dash-orbat__empty-text">
                Dès que les unités seront posées, la chaîne de commandement et les effectifs apparaîtront ici.
            </p>
        </div>
    </div>
</section>
    <?php
    return;
}

$root = $pack['root'];
$totalUnits = (int) ($pack['total_units'] ?? 0);
$totalMembers = (int) ($pack['total_members'] ?? 0);
$focusUnitId = (int) ($pack['focus_unit_id'] ?? 0);
$viewerUnitIds = is_array($pack['viewer_unit_ids'] ?? null) ? $pack['viewer_unit_ids'] : [];
$hasMine = $focusUnitId > 0 || $viewerUnitIds !== [];

$resolveMediaUrl = static function (?string $path): string {
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^(https?:)?//#i', $path) === 1 || str_starts_with($path, 'data:')) {
        return $path;
    }
    if (function_exists('asset_url')) {
        return asset_url(ltrim($path, '/'));
    }

    return url(ltrim($path, '/'));
};

$renderPerson = static function (?array $person, string $tone = 'member') use ($h): void {
    if ($person === null) {
        return;
    }
    $name = trim((string) ($person['label'] ?? ''));
    if ($name === '') {
        return;
    }
    $photo = trim((string) ($person['photo_url'] ?? ''));
    $initials = trim((string) ($person['initials'] ?? ''));
    if ($initials === '') {
        $initials = mb_strtoupper(mb_substr($name, 0, 2));
    }
    $role = trim((string) ($person['role'] ?? ''));
    $href = trim((string) ($person['href'] ?? ''));
    $tag = $href !== '' ? 'a' : 'div';
    $attr = $href !== '' ? ' href="' . $h($href) . '"' : '';
    ?>
    <<?= $tag ?> class="dash-orbat__person dash-orbat__person--<?= $h($tone) ?>"<?= $attr ?>>
        <span class="dash-orbat__avatar" aria-hidden="true">
            <?php if ($photo !== ''): ?>
                <img src="<?= $h($photo) ?>" alt="" loading="lazy" decoding="async">
            <?php else: ?>
                <span class="dash-orbat__initials"><?= $h($initials) ?></span>
            <?php endif; ?>
        </span>
        <span class="dash-orbat__person-meta">
            <span class="dash-orbat__person-name"><?= $h($name) ?></span>
            <?php if ($role !== ''): ?>
                <span class="dash-orbat__person-role"><?= $h($role) ?></span>
            <?php endif; ?>
        </span>
    </<?= $tag ?>>
    <?php
};

$renderVacant = static function () use ($h): void {
    ?>
    <div class="dash-orbat__person dash-orbat__person--vacant" aria-label="Poste de responsable vacant">
        <span class="dash-orbat__avatar dash-orbat__avatar--vacant" aria-hidden="true">
            <span class="dash-orbat__initials">—</span>
        </span>
        <span class="dash-orbat__person-meta">
            <span class="dash-orbat__person-name">Poste vacant</span>
            <span class="dash-orbat__person-role">Responsable non désigné</span>
        </span>
    </div>
    <?php
};

$renderNode = null;
$renderNode = static function (array $node, int $depth = 0) use (&$renderNode, $renderPerson, $renderVacant, $resolveMediaUrl, $h): void {
    $label = trim((string) ($node['label'] ?? 'Unité'));
    $code = trim((string) ($node['code'] ?? ''));
    $strength = (int) ($node['strength'] ?? 0);
    $mission = trim((string) ($node['mission'] ?? ''));
    if ($mission === '—') {
        $mission = '';
    }
    $commander = is_array($node['commander'] ?? null) ? $node['commander'] : null;
    $commanderVacant = !empty($node['commander_vacant']) && $commander === null;
    $isMine = !empty($node['is_mine']);
    $unitId = (int) ($node['unit_id'] ?? 0);
    $members = is_array($node['members'] ?? null) ? $node['members'] : [];
    $children = is_array($node['children'] ?? null) ? $node['children'] : [];
    $childCount = count($children);
    $iconUrl = $resolveMediaUrl(
        trim((string) ($node['icon_url'] ?? '')) !== ''
            ? (string) $node['icon_url']
            : (string) ($node['image_url'] ?? '')
    );
    $open = $depth < 2 || $isMine;
    $hasBody = $commander !== null || $commanderVacant || $members !== [] || $children !== [] || $mission !== '';
    $type = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($node['type'] ?? 'command'))) ?: 'command';
    $markLetter = mb_strtoupper(mb_substr($label, 0, 1));
    $searchBlob = mb_strtolower(trim($label . ' ' . $code . ' ' . (string) ($commander['label'] ?? '') . ' ' . $mission));
    foreach ($members as $mem) {
        if (is_array($mem)) {
            $searchBlob .= ' ' . mb_strtolower(trim((string) ($mem['label'] ?? '')));
        }
    }
    $nodeClass = 'dash-orbat__node dash-orbat__node--' . $type;
    if ($isMine) {
        $nodeClass .= ' is-mine';
    }
    if ($commanderVacant) {
        $nodeClass .= ' is-vacant-cmd';
    }
    ?>
    <details
        class="<?= $h($nodeClass) ?>"
        data-depth="<?= $depth ?>"
        data-unit-id="<?= $unitId ?>"
        data-search="<?= $h($searchBlob) ?>"
        <?= $isMine ? ' data-mine="1"' : '' ?>
        <?= $open ? ' open' : '' ?>
        <?= !$hasBody ? ' data-leaf="1"' : '' ?>
    >
        <summary class="dash-orbat__summary">
            <span class="dash-orbat__chevron" aria-hidden="true"></span>
            <span class="dash-orbat__unit-mark<?= $iconUrl !== '' ? ' dash-orbat__unit-mark--icon' : '' ?>" aria-hidden="true">
                <?php if ($iconUrl !== ''): ?>
                    <img src="<?= $h($iconUrl) ?>" alt="" loading="lazy" decoding="async" class="dash-orbat__unit-icon">
                <?php else: ?>
                    <?= $h($markLetter) ?>
                <?php endif; ?>
            </span>
            <span class="dash-orbat__unit-copy">
                <span class="dash-orbat__unit-name">
                    <?= $h($label) ?>
                    <?php if ($isMine): ?>
                        <span class="dash-orbat__mine-badge">Ma position</span>
                    <?php endif; ?>
                </span>
                <span class="dash-orbat__unit-sub" aria-label="Indicateurs d’unité">
                    <?php if ($code !== '' && $code !== $label): ?>
                        <span class="dash-orbat__chip dash-orbat__chip--code"><?= $h($code) ?></span>
                    <?php endif; ?>
                    <span class="dash-orbat__chip dash-orbat__chip--members<?= $strength === 0 ? ' is-empty' : '' ?>">
                        <svg class="dash-orbat__chip-ico" viewBox="0 0 16 16" aria-hidden="true" focusable="false">
                            <path fill="currentColor" d="M8 8a3 3 0 1 0-3-3 3 3 0 0 0 3 3Zm-5.5 6a4.5 4.5 0 0 1 9 0v.5H2.5Zm8.2-6.2a2.4 2.4 0 1 0-1.7-4.1 4.1 4.1 0 0 1 0 4.1 4 4 0 0 1 3.2 2.2h1.6V10a3.2 3.2 0 0 0-3.1-2.2Z"/>
                        </svg>
                        <span><?= $strength ?> membre<?= $strength > 1 ? 's' : '' ?></span>
                    </span>
                    <?php if ($childCount > 0): ?>
                        <span class="dash-orbat__chip dash-orbat__chip--subs">
                            <svg class="dash-orbat__chip-ico" viewBox="0 0 16 16" aria-hidden="true" focusable="false">
                                <path fill="currentColor" d="M7 2h2v3H7zm-4 5h2v3H3zm8 0h2v3h-2zM6.5 6.5h3v1h-3zM2 10.5h4V12H2zm8 0h4V12h-4zM7.5 8.5h1V11h-1z"/>
                            </svg>
                            <span><?= $childCount ?> sous-unité<?= $childCount > 1 ? 's' : '' ?></span>
                        </span>
                    <?php endif; ?>
                    <?php if ($commanderVacant): ?>
                        <span class="dash-orbat__chip dash-orbat__chip--vacant">Vacant</span>
                    <?php endif; ?>
                </span>
            </span>
            <?php if ($commander !== null): ?>
                <span class="dash-orbat__summary-lead">
                    <?php $renderPerson($commander, 'lead-mini'); ?>
                </span>
            <?php elseif ($commanderVacant): ?>
                <span class="dash-orbat__summary-lead dash-orbat__summary-lead--vacant">
                    <span class="dash-orbat__vacant-mini">Vacant</span>
                </span>
            <?php endif; ?>
        </summary>
        <?php if ($hasBody): ?>
            <div class="dash-orbat__body">
                <?php if ($mission !== ''): ?>
                    <p class="dash-orbat__mission"><?= $h($mission) ?></p>
                <?php endif; ?>

                <?php if ($commander !== null): ?>
                    <div class="dash-orbat__lead-block">
                        <p class="dash-orbat__eyebrow">Responsable</p>
                        <?php $renderPerson($commander, 'lead'); ?>
                    </div>
                <?php elseif ($commanderVacant): ?>
                    <div class="dash-orbat__lead-block">
                        <p class="dash-orbat__eyebrow">Responsable</p>
                        <?php $renderVacant(); ?>
                    </div>
                <?php endif; ?>

                <?php if ($members !== []): ?>
                    <div class="dash-orbat__roster">
                        <p class="dash-orbat__eyebrow">Effectif</p>
                        <div class="dash-orbat__people">
                            <?php foreach ($members as $mem): ?>
                                <?php if (is_array($mem)) { $renderPerson($mem, 'member'); } ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($children !== []): ?>
                    <div class="dash-orbat__children">
                        <?php foreach ($children as $child): ?>
                            <?php if (is_array($child)) { $renderNode($child, $depth + 1); } ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </details>
    <?php
};
?>
<section class="dash-orbat" id="organisation" aria-labelledby="dash-orbat-heading" data-focus-unit="<?= (int) $focusUnitId ?>">
    <div class="dash-orbat__frame">
        <header class="dash-orbat__head">
            <div>
                <p class="dash-orbat__kicker">Organisation RH</p>
                <h2 id="dash-orbat-heading" class="dash-orbat__title">Chaîne de commandement</h2>
                <p class="dash-orbat__lead">
                    Structure de <?= $h($unitLabel) ?> — <?= (int) $totalUnits ?> unité<?= $totalUnits > 1 ? 's' : '' ?>,
                    <?= (int) $totalMembers ?> personne<?= $totalMembers > 1 ? 's' : '' ?> placée<?= $totalMembers > 1 ? 's' : '' ?>.
                </p>
            </div>
            <div class="dash-orbat__actions">
                <?php if ($hasMine): ?>
                    <button type="button" class="dash-orbat__btn dash-orbat__btn--accent" data-dash-orbat-mine>Ma position</button>
                <?php endif; ?>
                <button type="button" class="dash-orbat__btn dash-orbat__btn--ghost" data-dash-orbat-expand>Tout ouvrir</button>
                <button type="button" class="dash-orbat__btn dash-orbat__btn--ghost" data-dash-orbat-collapse>Tout fermer</button>
                <a class="dash-orbat__btn dash-orbat__btn--ghost" href="<?= $h(url('orbat/export')) ?>">Exporter PDF</a>
                <a class="dash-orbat__btn dash-orbat__btn--ghost" href="<?= $h(url('orbat/export')) ?>">Exporter PDF</a>
                <a class="dash-orbat__btn dash-orbat__btn--solid" href="<?= $h(url('orbat') . ($focusUnitId > 0 ? '?unit=' . $focusUnitId : '')) ?>">ORBAT complet</a>
            </div>
        </header>
        <div class="dash-orbat__toolbar">
            <label class="dash-orbat__search">
                <span class="sr-only">Filtrer l’ORBAT</span>
                <svg class="dash-orbat__search-ico" viewBox="0 0 16 16" aria-hidden="true" focusable="false">
                    <path fill="currentColor" d="M11.5 10.4 14.7 13.6l-1.1 1.1-3.2-3.2a5.5 5.5 0 1 1 1.1-1.1ZM7 11.2A4.2 4.2 0 1 0 7 2.8a4.2 4.2 0 0 0 0 8.4Z"/>
                </svg>
                <input type="search" data-dash-orbat-search placeholder="Rechercher une unité, un code ou un nom…" autocomplete="off">
            </label>
            <p class="dash-orbat__filter-meta" data-dash-orbat-filter-meta hidden></p>
        </div>
        <div class="dash-orbat__tree" data-dash-orbat-tree>
            <?php $renderNode($root, 0); ?>
        </div>
        <p class="dash-orbat__no-match" data-dash-orbat-empty hidden>Aucune unité ne correspond à cette recherche.</p>
    </div>
</section>
<script>
(function () {
  var section = document.getElementById('organisation');
  var root = document.querySelector('[data-dash-orbat-tree]');
  if (!root || !section) return;
  var expand = document.querySelector('[data-dash-orbat-expand]');
  var collapse = document.querySelector('[data-dash-orbat-collapse]');
  var mineBtn = document.querySelector('[data-dash-orbat-mine]');
  var search = document.querySelector('[data-dash-orbat-search]');
  var filterMeta = document.querySelector('[data-dash-orbat-filter-meta]');
  var emptyMsg = document.querySelector('[data-dash-orbat-empty]');
  var focusUnit = parseInt(section.getAttribute('data-focus-unit') || '0', 10) || 0;

  function nodes() {
    return root.querySelectorAll('details.dash-orbat__node');
  }

  function setAll(open) {
    nodes().forEach(function (el) {
      if (el.getAttribute('data-leaf') === '1') return;
      el.open = open;
    });
  }

  function clearPulse() {
    root.querySelectorAll('.is-pulse').forEach(function (el) { el.classList.remove('is-pulse'); });
  }

  function focusUnitId(unitId) {
    if (!unitId) return false;
    var target = root.querySelector('details.dash-orbat__node[data-unit-id="' + unitId + '"]');
    if (!target) return false;
    var p = target.parentElement;
    while (p) {
      if (p.matches && p.matches('details.dash-orbat__node')) p.open = true;
      p = p.parentElement;
    }
    target.open = true;
    clearPulse();
    target.classList.add('is-pulse');
    try {
      target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } catch (e) {
      target.scrollIntoView(true);
    }
    window.setTimeout(function () { target.classList.remove('is-pulse'); }, 2200);
    return true;
  }

  function applySearch(raw) {
    var term = (raw || '').trim().toLowerCase();
    var all = nodes();
    var visible = 0;
    all.forEach(function (el) {
      el.classList.remove('is-filtered-out');
      el.hidden = false;
    });
    if (term === '') {
      if (filterMeta) { filterMeta.hidden = true; filterMeta.textContent = ''; }
      if (emptyMsg) emptyMsg.hidden = true;
      return;
    }
    all.forEach(function (el) {
      var hay = (el.getAttribute('data-search') || '');
      var match = hay.indexOf(term) !== -1;
      el.dataset.match = match ? '1' : '0';
    });
    // Remonter les parents des matches
    all.forEach(function (el) {
      if (el.dataset.match !== '1') return;
      var p = el.parentElement;
      while (p) {
        if (p.matches && p.matches('details.dash-orbat__node')) {
          p.dataset.match = '1';
          p.open = true;
        }
        p = p.parentElement;
      }
    });
    all.forEach(function (el) {
      var keep = el.dataset.match === '1';
      el.hidden = !keep;
      if (keep) {
        visible += 1;
        el.open = true;
      } else {
        el.classList.add('is-filtered-out');
      }
    });
    if (filterMeta) {
      filterMeta.hidden = false;
      filterMeta.textContent = visible + ' résultat' + (visible > 1 ? 's' : '');
    }
    if (emptyMsg) emptyMsg.hidden = visible > 0;
  }

  if (expand) expand.addEventListener('click', function () { setAll(true); });
  if (collapse) collapse.addEventListener('click', function () { setAll(false); });
  if (mineBtn) mineBtn.addEventListener('click', function () {
    if (!focusUnitId(focusUnit)) {
      var firstMine = root.querySelector('details.dash-orbat__node[data-mine="1"]');
      if (firstMine) {
        var id = parseInt(firstMine.getAttribute('data-unit-id') || '0', 10);
        focusUnitId(id);
      }
    }
  });
  if (search) {
    search.addEventListener('input', function () { applySearch(search.value); });
  }

  // Deep-link dashboard #organisation? ou ?unit=
  try {
    var params = new URLSearchParams(window.location.search || '');
    var qUnit = parseInt(params.get('unit') || params.get('unite') || '0', 10) || 0;
    if (qUnit > 0) {
      window.setTimeout(function () { focusUnitId(qUnit); }, 80);
    }
  } catch (e) {}
})();
</script>
