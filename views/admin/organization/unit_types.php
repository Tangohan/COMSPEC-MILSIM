<?php
declare(strict_types=1);

/** @var list<array{id: string, label: string, builtin: bool, used: int}> $typeRows */

$rows = is_array($typeRows ?? null) ? $typeRows : [];
$ready = !empty($typesReady);
$flashOk = trim((string) ($flashOk ?? ''));
$flashErr = trim((string) ($flashErr ?? ''));
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$own = array_values(array_filter($rows, static fn (array $r): bool => !$r['builtin']));
$base = array_values(array_filter($rows, static fn (array $r): bool => $r['builtin']));
$usage = static fn (int $n): string => $n === 0 ? __('unit_types.unused') : ($n === 1 ? __('unit_types.used_one') : __('unit_types.used_many', ['n' => $n]));
$action = url('back-office/organisation/types-entite/enregistrer');
$js = static fn (string $v): string => (string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
?>
<div class="bo-catalog bo-unit-types">
    <header class="bo-catalog__hero">
        <p class="bo-catalog__kicker">Organisation</p>
        <h1 class="bo-catalog__title"><?= $h(__('unit_types.title')) ?></h1>
        <p class="bo-catalog__lead"><?= $h(__('unit_types.subtitle')) ?></p>
        <div class="bo-catalog__hero-actions">
            <button type="button" class="ath-btn ath-btn--solid" data-ut-add <?= $ready ? '' : 'disabled' ?>><?= $h(__('unit_types.add')) ?></button>
            <a class="ath-btn" href="<?= $h(url('back-office/organisation/catalogue')) ?>"><?= $h(__('unit_types.back')) ?></a>
            <a class="ath-btn" href="<?= $h(url('orbat')) ?>"><?= $h(__('unit_types.open_orbat')) ?></a>
        </div>
    </header>

    <?php if ($flashOk !== ''): ?>
        <p class="ath-flash ath-flash--ok" role="status"><?= $h($flashOk) ?></p>
    <?php endif; ?>
    <?php if ($flashErr !== ''): ?>
        <p class="ath-flash ath-flash--err" role="alert"><?= $h($flashErr) ?></p>
    <?php endif; ?>
    <?php if (!$ready): ?>
        <p class="ath-flash ath-flash--err" role="alert"><?= $h(__('unit_types.not_ready')) ?></p>
    <?php endif; ?>

    <div role="tablist" class="bo-ut-tabs">
        <button type="button" role="tab" class="ath-btn ath-btn--solid" id="ut-tab-own" aria-selected="true" aria-controls="ut-own" data-ut-tab="own"><?= $h(__('unit_types.tab_own')) ?> (<?= (int) count($own) ?>)</button>
        <button type="button" role="tab" class="ath-btn" id="ut-tab-base" aria-selected="false" aria-controls="ut-base" data-ut-tab="base"><?= $h(__('unit_types.tab_base')) ?> (<?= (int) count($base) ?>)</button>
    </div>

    <section id="ut-own" role="tabpanel" aria-labelledby="ut-tab-own" class="bo-catalog__section">
        <?php if ($own === []): ?>
            <p class="bo-catalog__section-lead"><?= $h(__('unit_types.empty_own')) ?></p>
        <?php else: ?>
            <ul class="bo-ut-list">
                <?php foreach ($own as $r): ?>
                    <li class="bo-ut-row">
                        <strong><?= $h($r['label']) ?></strong>
                        <span class="bo-ut-use"<?= $r['used'] > 0 ? ' title="' . $h(__('unit_types.in_use_hint')) . '"' : '' ?>><?= $h($usage((int) $r['used'])) ?></span>
                        <button type="button" class="ath-btn" data-ut-rename="<?= $h($r['id']) ?>" data-label="<?= $h($r['label']) ?>"><?= $h(__('unit_types.rename')) ?></button>
                        <form method="post" action="<?= $h($action) ?>" data-ut-delete>
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="slug" value="<?= $h($r['id']) ?>">
                            <button type="submit" class="ath-btn" <?= $r['used'] > 0 ? 'disabled' : '' ?>><?= $h(__('unit_types.delete')) ?></button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section id="ut-base" role="tabpanel" aria-labelledby="ut-tab-base" class="bo-catalog__section" hidden>
        <p class="bo-catalog__section-lead"><?= $h(__('unit_types.base_help')) ?></p>
        <ul class="bo-ut-list">
            <?php foreach ($base as $r): ?>
                <li class="bo-ut-row"><strong><?= $h($r['label']) ?></strong><span class="bo-ut-use"><?= $h($usage((int) $r['used'])) ?></span></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <dialog id="ut-modal" class="bo-ut-modal">
        <form method="post" action="<?= $h($action) ?>">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="action" value="create" id="ut-action">
            <input type="hidden" name="slug" value="" id="ut-slug">
            <h2 id="ut-modal-title"><?= $h(__('unit_types.modal_add')) ?></h2>
            <div class="bo-catalog__field">
                <label for="ut-label"><?= $h(__('unit_types.name')) ?></label>
                <input type="text" name="label" id="ut-label" maxlength="120" required autocomplete="off">
                <small><?= $h(__('unit_types.name_help')) ?></small>
            </div>
            <div class="bo-ut-actions">
                <button type="button" class="ath-btn" data-ut-close><?= $h(__('unit_types.cancel')) ?></button>
                <button type="submit" class="ath-btn ath-btn--solid"><?= $h(__('unit_types.save')) ?></button>
            </div>
        </form>
    </dialog>
</div>
<style>
.bo-ut-tabs{display:flex;gap:.5rem;margin:1rem 0}
.bo-ut-list{list-style:none;margin:0;padding:0;display:grid;gap:.5rem}
.bo-ut-row{display:flex;align-items:center;gap:.75rem;flex-wrap:wrap}
.bo-ut-row strong{flex:1 1 12rem}
.bo-ut-use{opacity:.7;font-size:.9em}
.bo-ut-modal{max-width:28rem;width:calc(100% - 2rem)}
.bo-ut-actions{display:flex;justify-content:flex-end;gap:.5rem;margin-top:1rem}
</style>
<script>
(function () {
  var modal = document.getElementById('ut-modal');
  if (!modal) return;
  var T = {add: <?= $js(__('unit_types.modal_add')) ?>, ren: <?= $js(__('unit_types.modal_rename')) ?>, del: <?= $js(__('unit_types.confirm_delete')) ?>};
  function open(action, slug, label) {
    document.getElementById('ut-action').value = action;
    document.getElementById('ut-slug').value = slug;
    document.getElementById('ut-label').value = label;
    document.getElementById('ut-modal-title').textContent = action === 'create' ? T.add : T.ren;
    if (typeof modal.showModal === 'function') modal.showModal(); else modal.setAttribute('open', '');
    document.getElementById('ut-label').focus();
  }
  document.querySelectorAll('[data-ut-add]').forEach(function (b) { b.addEventListener('click', function () { open('create', '', ''); }); });
  document.querySelectorAll('[data-ut-rename]').forEach(function (b) {
    b.addEventListener('click', function () { open('rename', b.getAttribute('data-ut-rename'), b.getAttribute('data-label') || ''); });
  });
  document.querySelectorAll('[data-ut-close]').forEach(function (b) { b.addEventListener('click', function () { modal.close(); }); });
  document.querySelectorAll('form[data-ut-delete]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!window.confirm(T.del)) e.preventDefault(); });
  });
  document.querySelectorAll('[data-ut-tab]').forEach(function (tab) {
    tab.addEventListener('click', function () {
      var which = tab.getAttribute('data-ut-tab');
      ['own', 'base'].forEach(function (k) {
        var on = k === which;
        document.getElementById('ut-' + k).hidden = !on;
        var t = document.getElementById('ut-tab-' + k);
        t.setAttribute('aria-selected', on ? 'true' : 'false');
        t.classList.toggle('ath-btn--solid', on);
      });
    });
  });
})();
</script>
