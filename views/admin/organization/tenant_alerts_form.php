<?php
/** @var array<string, mixed>|null $tenantAlert */
/** @var string $formAction */
/** @var string $formMethod */
$row = $tenantAlert;
$isEdit = $row !== null;
$dt = static function (?string $sqlDt): string {
    if ($sqlDt === null || $sqlDt === '') {
        return '';
    }
    $t = strtotime($sqlDt);

    return $t ? date('Y-m-d\TH:i', $t) : '';
};
?>
<div class="max-w-2xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900"><?= $isEdit ? 'Modifier l’alerte' : 'Nouvelle alerte communauté' ?></h1>
        <a href="<?= url('back-office/alerts') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">Retour</a>
    </div>

    <form method="<?= htmlspecialchars($formMethod) ?>" action="<?= htmlspecialchars($formAction) ?>" class="space-y-5">
        <?= \App\Core\Csrf::field() ?>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Type</label>
            <select name="kind" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                <?php foreach (['info' => 'Info', 'novelty' => 'Nouveauté', 'discount' => 'Promo / remise', 'urgent' => 'Urgent'] as $v => $lab): ?>
                    <option value="<?= $v ?>" <?= (($row['kind'] ?? 'info') === $v) ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Titre</label>
            <input type="text" name="title" required value="<?= htmlspecialchars((string) ($row['title'] ?? '')) ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" maxlength="255">
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Texte (optionnel)</label>
            <textarea name="body" rows="4" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"><?= htmlspecialchars((string) ($row['body'] ?? '')) ?></textarea>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Libellé du lien</label>
                <input type="text" name="cta_label" value="<?= htmlspecialchars((string) ($row['cta_label'] ?? '')) ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">URL du lien</label>
                <input type="text" name="cta_url" value="<?= htmlspecialchars((string) ($row['cta_url'] ?? '')) ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" placeholder="/forum ou https://…">
            </div>
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Code promo (optionnel)</label>
            <input type="text" name="coupon_code" value="<?= htmlspecialchars((string) ($row['coupon_code'] ?? '')) ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm font-mono" maxlength="64">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Début (optionnel)</label>
                <input type="datetime-local" name="starts_at" value="<?= htmlspecialchars($dt(isset($row['starts_at']) ? (string) $row['starts_at'] : null)) ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Fin (optionnel)</label>
                <input type="datetime-local" name="ends_at" value="<?= htmlspecialchars($dt(isset($row['ends_at']) ? (string) $row['ends_at'] : null)) ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
            </div>
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Ordre d’affichage</label>
            <input type="number" name="sort_order" value="<?= (int) ($row['sort_order'] ?? 0) ?>" class="w-32 border border-slate-300 rounded-lg px-3 py-2 text-sm">
        </div>

        <div class="flex items-center gap-2">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" id="ta_is_active" <?= ($row === null || ! empty($row['is_active'])) ? 'checked' : '' ?>>
            <label for="ta_is_active" class="text-sm font-semibold text-slate-700">Alerte active</label>
        </div>

        <button type="submit" class="inline-flex items-center px-5 py-2.5 rounded-lg bg-slate-900 text-white text-sm font-black uppercase tracking-wide hover:bg-emerald-600 transition-colors"><?= $isEdit ? 'Enregistrer' : 'Créer' ?></button>
    </form>
</div>
