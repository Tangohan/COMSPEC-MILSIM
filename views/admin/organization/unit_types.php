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
<div class="bo-org bo-unit-types">
    <?php if ($flashOk !== ''): ?>
        <p class="ath-flash ath-flash--ok" role="status"><?= $h($flashOk) ?></p>
    <?php endif; ?>
    <?php if ($flashErr !== ''): ?>
        <p class="ath-flash ath-flash--err" role="alert"><?= $h($flashErr) ?></p>
    <?php endif; ?>
    <?php if (!$ready): ?>
        <p class="ath-flash ath-flash--err" role="alert"><?= $h(__('unit_types.not_ready')) ?></p>
    <?php endif; ?>

    <div class="bo-org-stats">
        <div class="bo-org-stat bo-org-stat--ok"><span class="bo-org-stat__label"><?= $h(__('unit_types.tab_own')) ?></span><span class="bo-org-stat__value"><?= (int) count($own) ?></span></div>
        <div class="bo-org-stat"><span class="bo-org-stat__label"><?= $h(__('unit_types.tab_base')) ?></span><span class="bo-org-stat__value"><?= (int) count($base) ?></span></div>
        <div class="bo-org-stat"><span class="bo-org-stat__label"><?= $h(__('unit_types.stat_units')) ?></span><span class="bo-org-stat__value"><?= (int) array_sum(array_column($rows, 'used')) ?></span></div>
    </div>

    <section class="bo-org-panel">
        <div role="tablist" class="bo-org-tabs">
            <button type="button" role="tab" class="bo-org-tab" id="ut-tab-own" aria-selected="true" aria-controls="ut-own" data-ut-tab="own"><?= $h(__('unit_types.tab_own')) ?><span><?= (int) count($own) ?></span></button>
            <button type="button" role="tab" class="bo-org-tab" id="ut-tab-base" aria-selected="false" aria-controls="ut-base" data-ut-tab="base"><?= $h(__('unit_types.tab_base')) ?><span><?= (int) count($base) ?></span></button>
        </div>

        <div id="ut-own" role="tabpanel" aria-labelledby="ut-tab-own">
            <div class="bo-org-panel__head">
                <div class="bo-org-panel__titles">
                    <h2 class="bo-org-panel__title"><?= $h(__('unit_types.tab_own')) ?></h2>
                    <p class="bo-org-panel__hint"><?= $h(__('unit_types.subtitle')) ?></p>
                </div>
                <?php if ($own !== []): ?>
                <div class="bo-org-panel__acts">
                    <button type="button" class="ath-btn ath-btn--solid" data-ut-add <?= $ready ? '' : 'disabled' ?>>+ <?= $h(__('unit_types.add')) ?></button>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($own === []): ?>
                <div class="bo-org-empty">
                    <p class="bo-org-empty__title"><?= $h(__('unit_types.empty_title')) ?></p>
                    <p class="bo-org-empty__text"><?= $h(__('unit_types.empty_own')) ?></p>
                    <button type="button" class="ath-btn ath-btn--solid" data-ut-add <?= $ready ? '' : 'disabled' ?>>+ <?= $h(__('unit_types.add')) ?></button>
                </div>
            <?php else: ?>
                <ul class="bo-org-list">
                    <?php foreach ($own as $r): ?>
                        <li class="bo-org-item">
                            <span class="bo-org-item__name"><?= $h($r['label']) ?></span>
                            <span class="ath-tag <?= $r['used'] > 0 ? 'ath-tag--info' : 'ath-tag--neut' ?>"<?= $r['used'] > 0 ? ' title="' . $h(__('unit_types.in_use_hint')) . '"' : '' ?>><?= $h($usage((int) $r['used'])) ?></span>
                            <div class="bo-org-item__acts">
                                <button type="button" class="ath-btn" data-ut-rename="<?= $h($r['id']) ?>" data-label="<?= $h($r['label']) ?>"><?= $h(__('unit_types.rename')) ?></button>
                                <form method="post" action="<?= $h($action) ?>" data-ut-delete>
                                    <?= \App\Core\Csrf::field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="slug" value="<?= $h($r['id']) ?>">
                                    <button type="submit" class="ath-btn ath-btn--danger" <?= $r['used'] > 0 ? 'disabled' : '' ?>><?= $h(__('unit_types.delete')) ?></button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div id="ut-base" role="tabpanel" aria-labelledby="ut-tab-base" hidden>
            <div class="bo-org-panel__head">
                <div class="bo-org-panel__titles">
                    <h2 class="bo-org-panel__title"><?= $h(__('unit_types.tab_base')) ?></h2>
                    <p class="bo-org-panel__hint"><?= $h(__('unit_types.base_help')) ?></p>
                </div>
            </div>
            <ul class="bo-org-list">
                <?php foreach ($base as $r): ?>
                    <li class="bo-org-item">
                        <span class="bo-org-item__name"><?= $h($r['label']) ?></span>
                        <span class="ath-tag <?= $r['used'] > 0 ? 'ath-tag--info' : 'ath-tag--neut' ?>"><?= $h($usage((int) $r['used'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>

    <dialog id="ut-modal" class="bo-org-dialog">
        <form method="post" action="<?= $h($action) ?>">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="action" value="create" id="ut-action">
            <input type="hidden" name="slug" value="" id="ut-slug">
            <div class="bo-org-dialog__head"><h2 id="ut-modal-title"><?= $h(__('unit_types.modal_add')) ?></h2></div>
            <div class="bo-org-dialog__body">
                <label for="ut-label"><?= $h(__('unit_types.name')) ?></label>
                <input type="text" class="ath-field__input" name="label" id="ut-label" maxlength="120" required autocomplete="off">
                <small><?= $h(__('unit_types.name_help')) ?></small>
            </div>
            <div class="bo-org-panel__foot">
                <button type="button" class="ath-btn" data-ut-close><?= $h(__('unit_types.cancel')) ?></button>
                <button type="submit" class="ath-btn ath-btn--solid"><?= $h(__('unit_types.save')) ?></button>
            </div>
        </form>
    </dialog>
</div>
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
              });
    });
  });
})();
</script>
