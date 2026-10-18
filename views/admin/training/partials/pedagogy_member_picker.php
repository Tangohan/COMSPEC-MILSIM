<?php
declare(strict_types=1);
/**
 * Choix d’un membre par identité (nom, indicatif, e-mail) avec filtre de saisie.
 *
 * @var list<array<string, mixed>> $pickerUsers
 * @var string $pickerId
 * @var list<int>|null $pickerExcludeIds
 */
$pickerUsers = is_array($pickerUsers ?? null) ? $pickerUsers : [];
$pickerId = (string) ($pickerId ?? 'pedagogy-picker');
$pickerExclude = array_flip(array_map('intval', is_array($pickerExcludeIds ?? null) ? $pickerExcludeIds : []));
?>
<div class="min-w-[220px] flex-1 space-y-1.5" data-pedagogy-picker>
    <label class="block text-xs font-bold text-slate-600" for="<?= htmlspecialchars($pickerId, ENT_QUOTES, 'UTF-8') ?>">Membre à nommer</label>
    <input type="search" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white" placeholder="Rechercher un nom, un indicatif ou un e-mail…" autocomplete="off" data-pedagogy-picker-filter>
    <select id="<?= htmlspecialchars($pickerId, ENT_QUOTES, 'UTF-8') ?>" name="target_user_id" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white" data-pedagogy-picker-select>
        <option value="">— Choisir un membre —</option>
        <?php foreach ($pickerUsers as $u):
            $uid = (int) ($u['id'] ?? 0);
            if ($uid < 1 || isset($pickerExclude[$uid])) {
                continue;
            }
            $label = trim((string) ($u['display_name'] ?? ''));
            $mail = trim((string) ($u['email'] ?? ''));
            if ($label === '') {
                $label = $mail !== '' ? $mail : ('Membre #' . $uid);
            }
            ?>
        <option value="<?= $uid ?>" data-search="<?= htmlspecialchars(mb_strtolower($label . ' ' . (string) ($u['callsign'] ?? '') . ' ' . $mail), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
    </select>
</div>
<?php if (empty($GLOBALS['__pedagogyPickerScript'])): $GLOBALS['__pedagogyPickerScript'] = true; ?>
<script>
document.addEventListener('input', function (e) {
    var f = e.target.closest ? e.target.closest('[data-pedagogy-picker-filter]') : null;
    if (!f) return;
    var sel = f.parentElement.querySelector('[data-pedagogy-picker-select]');
    if (!sel) return;
    var q = f.value.trim().toLowerCase();
    var first = null;
    Array.prototype.forEach.call(sel.options, function (o) {
        if (!o.value) return;
        var show = q === '' || (o.getAttribute('data-search') || '').indexOf(q) !== -1;
        o.hidden = !show;
        if (show && !first) first = o;
    });
    if (q !== '' && first && (sel.selectedIndex < 1 || sel.options[sel.selectedIndex].hidden)) {
        sel.value = first.value;
    }
});
</script>
<?php endif; ?>
