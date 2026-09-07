<?php
declare(strict_types=1);

/**
 * ORBAT dépliable du tableau de bord — vue RH avec portraits.
 *
 * @var array{root: array<string,mixed>, total_units: int, total_members: int}|null $dashboard_orbat
 * @var string|null $dashboard_tenant_label
 */

$pack = is_array($dashboard_orbat ?? null) ? $dashboard_orbat : null;
if ($pack === null || !is_array($pack['root'] ?? null)) {
    return;
}

$root = $pack['root'];
$totalUnits = (int) ($pack['total_units'] ?? 0);
$totalMembers = (int) ($pack['total_members'] ?? 0);
$unitLabel = trim((string) ($dashboard_tenant_label ?? ''));
if ($unitLabel === '') {
    $unitLabel = function_exists('i18n_phrase') ? i18n_phrase('nav', 'Communauté') : 'Communauté';
}
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

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

$renderNode = null;
$renderNode = static function (array $node, int $depth = 0) use (&$renderNode, $renderPerson, $h): void {
    $label = trim((string) ($node['label'] ?? 'Unité'));
    $code = trim((string) ($node['code'] ?? ''));
    $strength = (int) ($node['strength'] ?? 0);
    $mission = trim((string) ($node['mission'] ?? ''));
    if ($mission === '—') {
        $mission = '';
    }
    $commander = is_array($node['commander'] ?? null) ? $node['commander'] : null;
    $members = is_array($node['members'] ?? null) ? $node['members'] : [];
    $children = is_array($node['children'] ?? null) ? $node['children'] : [];
    $open = $depth < 2;
    $hasBody = $commander !== null || $members !== [] || $children !== [] || $mission !== '';
    $type = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($node['type'] ?? 'command'))) ?: 'command';
    ?>
    <details class="dash-orbat__node dash-orbat__node--<?= $h($type) ?>" data-depth="<?= $depth ?>"<?= $open ? ' open' : '' ?><?= !$hasBody ? ' data-leaf="1"' : '' ?>>
        <summary class="dash-orbat__summary">
            <span class="dash-orbat__chevron" aria-hidden="true"></span>
            <span class="dash-orbat__unit-mark" aria-hidden="true"><?= $h(mb_strtoupper(mb_substr($label, 0, 1))) ?></span>
            <span class="dash-orbat__unit-copy">
                <span class="dash-orbat__unit-name"><?= $h($label) ?></span>
                <span class="dash-orbat__unit-sub">
                    <?php if ($code !== '' && $code !== $label): ?>
                        <span><?= $h($code) ?></span>
                    <?php endif; ?>
                    <span><?= $strength ?> membre<?= $strength > 1 ? 's' : '' ?></span>
                    <?php if (count($children) > 0): ?>
                        <span><?= count($children) ?> sous-unité<?= count($children) > 1 ? 's' : '' ?></span>
                    <?php endif; ?>
                </span>
            </span>
            <?php if ($commander !== null): ?>
                <span class="dash-orbat__summary-lead">
                    <?php $renderPerson($commander, 'lead-mini'); ?>
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
<section class="dash-orbat" id="organisation" aria-labelledby="dash-orbat-heading">
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
                <button type="button" class="dash-orbat__btn dash-orbat__btn--ghost" data-dash-orbat-expand>Tout ouvrir</button>
                <button type="button" class="dash-orbat__btn dash-orbat__btn--ghost" data-dash-orbat-collapse>Tout fermer</button>
                <a class="dash-orbat__btn dash-orbat__btn--solid" href="<?= $h(url('orbat')) ?>">ORBAT complet</a>
            </div>
        </header>
        <div class="dash-orbat__tree" data-dash-orbat-tree>
            <?php $renderNode($root, 0); ?>
        </div>
    </div>
</section>
<script>
(function () {
  var root = document.querySelector('[data-dash-orbat-tree]');
  if (!root) return;
  var expand = document.querySelector('[data-dash-orbat-expand]');
  var collapse = document.querySelector('[data-dash-orbat-collapse]');
  function setAll(open) {
    root.querySelectorAll('details.dash-orbat__node').forEach(function (el) {
      if (el.getAttribute('data-leaf') === '1') return;
      el.open = open;
    });
  }
  if (expand) expand.addEventListener('click', function () { setAll(true); });
  if (collapse) collapse.addEventListener('click', function () { setAll(false); });
})();
</script>
