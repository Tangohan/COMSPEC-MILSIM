<?php
declare(strict_types=1);

/** @var array<string, mixed>|null $platformAlert */
/** @var string $formAction */
/** @var string $formMethod */
$row = $platformAlert;
$isEdit = $row !== null;
$kindOptions = \App\Support\PlatformAlertPresentation::kindOptions();
$aud = ['guest' => true, 'authenticated' => true, 'free' => true, 'paid' => true];
if ($row && ! empty($row['audience_json'])) {
    $raw = $row['audience_json'];
    if (is_string($raw)) {
        $d = json_decode($raw, true);
        if (is_array($d)) {
            $aud = array_merge($aud, $d);
        }
    } elseif (is_array($raw)) {
        $aud = array_merge($aud, $raw);
    }
}
$dt = static function (?string $sqlDt): string {
    if ($sqlDt === null || $sqlDt === '') {
        return '';
    }
    $t = strtotime($sqlDt);

    return $t ? date('Y-m-d\TH:i', $t) : '';
};
?>
<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:py-12">
    <div class="mb-8">
        <a href="<?= url('admin/system/alerts') ?>" class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-700 hover:text-emerald-900">
            <span aria-hidden="true">←</span> Retour à la liste
        </a>
        <h1 class="mt-4 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl"><?= $isEdit ? 'Modifier l’alerte' : 'Nouvelle alerte plateforme' ?></h1>
        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600">
            Rédigez un message court. Il pourra apparaître en bandeau sur le portail et dans les annonces du bandeau supérieur pour les personnes concernées.
        </p>
    </div>

    <div class="mb-8 rounded-2xl border border-emerald-200 bg-emerald-50/80 p-5 text-sm text-emerald-950 shadow-sm">
        <p class="font-bold text-emerald-900">Avant de publier</p>
        <ul class="mt-2 list-inside list-disc space-y-1.5 text-emerald-900/90">
            <li><strong>Période</strong> : laissez les dates vides pour une diffusion sans limite de temps (sous réserve que la publication soit activée).</li>
            <li><strong>Audience</strong> : cochez qui doit voir le message (visiteurs, membres connectés, type de communauté).</li>
            <li><strong>Ordre d’affichage</strong> : les valeurs les plus basses sont affichées en premier lorsque plusieurs alertes coexistent.</li>
        </ul>
    </div>

    <form method="<?= htmlspecialchars($formMethod, ENT_QUOTES, 'UTF-8') ?>" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>" class="space-y-8">
        <?= \App\Core\Csrf::field() ?>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <h2 class="text-lg font-black text-slate-900">Contenu</h2>
            <div class="mt-6 space-y-5">
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Type</label>
                    <select name="kind" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <?php foreach ($kindOptions as $v => $lab): ?>
                            <option value="<?= htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') ?>" <?= (($row['kind'] ?? 'info') === $v) ? 'selected' : '' ?>><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Titre</label>
                    <input type="text" name="title" required value="<?= htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" maxlength="255">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Texte (optionnel)</label>
                    <textarea name="body" rows="4" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><?= htmlspecialchars((string) ($row['body'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Libellé du lien</label>
                        <input type="text" name="cta_label" value="<?= htmlspecialchars((string) ($row['cta_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Voir les offres">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Lien cible</label>
                        <input type="text" name="cta_url" value="<?= htmlspecialchars((string) ($row['cta_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Ex. page des tarifs ou adresse en https://…">
                        <p class="mt-1 text-xs text-slate-500">Adresse complète en https ou chemin interne commençant par /.</p>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Code promo (optionnel)</label>
                    <input type="text" name="coupon_code" value="<?= htmlspecialchars((string) ($row['coupon_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono" maxlength="64">
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <h2 class="text-lg font-black text-slate-900">Période et ordre</h2>
            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Début (optionnel)</label>
                    <input type="datetime-local" name="starts_at" value="<?= htmlspecialchars($dt(isset($row['starts_at']) ? (string) $row['starts_at'] : null), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Fin (optionnel)</label>
                    <input type="datetime-local" name="ends_at" value="<?= htmlspecialchars($dt(isset($row['ends_at']) ? (string) $row['ends_at'] : null), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div class="mt-5">
                <label class="mb-1 block text-sm font-semibold text-slate-700">Ordre d’affichage</label>
                <input type="number" name="sort_order" value="<?= (int) ($row['sort_order'] ?? 0) ?>" class="w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-slate-50 p-6 sm:p-8">
            <h2 class="text-lg font-black text-slate-900">Audience</h2>
            <p class="mt-1 text-sm text-slate-600">Indiquez qui peut voir cette alerte sur le portail.</p>
            <div class="mt-4 space-y-2">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="aud_guest" value="1" <?= ! empty($aud['guest']) ? 'checked' : '' ?>> Visiteurs non connectés
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="aud_auth" value="1" <?= ! empty($aud['authenticated']) ? 'checked' : '' ?>> Utilisateurs connectés
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="aud_free" value="1" <?= ! empty($aud['free']) ? 'checked' : '' ?>> Communautés sans abonnement payant actif
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="aud_paid" value="1" <?= ! empty($aud['paid']) ? 'checked' : '' ?>> Communautés avec abonnement payant actif ou période d’essai
                </label>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex items-center gap-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" id="is_active" <?= ($row === null || ! empty($row['is_active'])) ? 'checked' : '' ?>>
                <label for="is_active" class="text-sm font-semibold text-slate-700">Publication activée</label>
            </div>
            <p class="mt-2 text-xs text-slate-500">Si la case est décochée, l’alerte reste enregistrée mais ne s’affiche pas sur le portail.</p>
        </section>

        <button type="submit" class="inline-flex items-center rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-black uppercase tracking-wide text-white transition hover:bg-emerald-600"><?= $isEdit ? 'Enregistrer' : 'Créer' ?></button>
    </form>
</div>
