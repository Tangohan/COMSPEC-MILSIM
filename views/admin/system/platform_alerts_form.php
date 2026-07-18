<?php
declare(strict_types=1);

/** @var array<string, mixed>|null $platformAlert */
/** @var string $formAction */
/** @var string $formMethod */
$row = $platformAlert;
$isEdit = $row !== null;
$kindOptions = \App\Support\PlatformAlertPresentation::kindOptions();
$aud = ['guest' => true, 'authenticated' => true, 'free' => true, 'paid' => true];
if ($row && !empty($row['audience_json'])) {
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
$dismissible = $row === null || !isset($row['dismissible']) || (int) $row['dismissible'] === 1;
?>
<div class="min-h-0 flex-1 bg-slate-50">
    <div class="w-full px-4 sm:px-5 lg:px-6 py-4 sm:py-5 space-y-5">

        <header class="relative overflow-hidden rounded-xl border border-emerald-200/90 bg-gradient-to-br from-emerald-50/90 via-white to-slate-50 shadow-sm">
            <div class="absolute inset-y-0 left-0 w-1 bg-emerald-600" aria-hidden="true"></div>
            <div class="relative px-4 sm:px-6 py-5 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-[11px] font-black uppercase tracking-[0.22em] text-emerald-800/90">Administration · Plateforme</p>
                    <h1 class="mt-1.5 text-2xl font-black tracking-tight text-slate-900"><?= $isEdit ? 'Modifier l’annonce' : 'Nouvelle annonce' ?></h1>
                    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">
                        Message court sur le portail : bandeau classique, barre sous le menu, ou bandeau Breaking pour les mises à jour majeures et maintenances.
                    </p>
                </div>
                <a href="<?= url('admin/system/alerts') ?>" class="inline-flex shrink-0 items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Retour à la liste</a>
            </div>
        </header>

        <?php $e = \App\Core\Session::getFlash('error'); ?>
        <?php if ($e): ?>
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800" role="alert"><?= htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="<?= htmlspecialchars($formMethod, ENT_QUOTES, 'UTF-8') ?>" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>" class="space-y-5">
            <?= \App\Core\Csrf::field() ?>

            <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm space-y-5">
                <h2 class="text-sm font-black uppercase tracking-[0.12em] text-slate-800">Contenu</h2>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-slate-700" for="pa-kind">Type</label>
                    <select id="pa-kind" name="kind" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                        <?php foreach ($kindOptions as $v => $lab): ?>
                            <option value="<?= htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') ?>" <?= (($row['kind'] ?? 'info') === $v) ? 'selected' : '' ?>><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-slate-700" for="pa-display-style">Emplacement d’affichage</label>
                    <?php
                    $displayStyleOptions = \App\Support\AlertDisplayStyle::platformOptions();
                    $currentDisplayStyle = \App\Support\AlertDisplayStyle::sanitizePlatform(
                        isset($row['display_style']) ? (string) $row['display_style'] : null
                    );
                    ?>
                    <select id="pa-display-style" name="display_style" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                        <?php foreach ($displayStyleOptions as $v => $lab): ?>
                            <option value="<?= htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') ?>" <?= $currentDisplayStyle === $v ? 'selected' : '' ?>><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Les barres sous le menu et le bandeau Breaking s’affichent sur toute la largeur, juste sous la navigation.</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-slate-700" for="pa-title">Titre</label>
                    <input id="pa-title" type="text" name="title" required value="<?= htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" maxlength="255">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-slate-700" for="pa-body">Texte (optionnel)</label>
                    <textarea id="pa-body" name="body" rows="4" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"><?= htmlspecialchars((string) ($row['body'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-slate-700" for="pa-cta-label">Libellé du lien</label>
                        <input id="pa-cta-label" type="text" name="cta_label" value="<?= htmlspecialchars((string) ($row['cta_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" placeholder="Voir les détails">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-slate-700" for="pa-cta-url">Lien cible</label>
                        <input id="pa-cta-url" type="text" name="cta_url" value="<?= htmlspecialchars((string) ($row['cta_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" placeholder="https://… ou /chemin-interne">
                        <p class="mt-1 text-xs text-slate-500">Adresse complète en https, ou chemin interne commençant par /.</p>
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-slate-700" for="pa-coupon">Code promo (optionnel)</label>
                    <input id="pa-coupon" type="text" name="coupon_code" value="<?= htmlspecialchars((string) ($row['coupon_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-mono" maxlength="64">
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm space-y-5">
                <h2 class="text-sm font-black uppercase tracking-[0.12em] text-slate-800">Période et ordre</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-slate-700" for="pa-starts">Début (optionnel)</label>
                        <input id="pa-starts" type="datetime-local" name="starts_at" value="<?= htmlspecialchars($dt(isset($row['starts_at']) ? (string) $row['starts_at'] : null), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-slate-700" for="pa-ends">Fin (optionnel)</label>
                        <input id="pa-ends" type="datetime-local" name="ends_at" value="<?= htmlspecialchars($dt(isset($row['ends_at']) ? (string) $row['ends_at'] : null), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-slate-700" for="pa-order">Ordre d’affichage</label>
                    <input id="pa-order" type="number" name="sort_order" value="<?= (int) ($row['sort_order'] ?? 0) ?>" class="w-32 rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                    <p class="mt-1 text-xs text-slate-500">Les valeurs les plus basses apparaissent en premier.</p>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm space-y-4">
                <h2 class="text-sm font-black uppercase tracking-[0.12em] text-slate-800">Audience</h2>
                <p class="text-sm text-slate-600">Indiquez qui peut voir cette annonce sur le portail.</p>
                <div class="space-y-2.5">
                    <label class="flex items-start gap-2.5 text-sm text-slate-700 cursor-pointer">
                        <input type="checkbox" name="aud_guest" value="1" class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" <?= !empty($aud['guest']) ? 'checked' : '' ?>>
                        <span>Visiteurs non connectés</span>
                    </label>
                    <label class="flex items-start gap-2.5 text-sm text-slate-700 cursor-pointer">
                        <input type="checkbox" name="aud_auth" value="1" class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" <?= !empty($aud['authenticated']) ? 'checked' : '' ?>>
                        <span>Utilisateurs connectés</span>
                    </label>
                    <label class="flex items-start gap-2.5 text-sm text-slate-700 cursor-pointer">
                        <input type="checkbox" name="aud_free" value="1" class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" <?= !empty($aud['free']) ? 'checked' : '' ?>>
                        <span>Communautés sans abonnement payant actif</span>
                    </label>
                    <label class="flex items-start gap-2.5 text-sm text-slate-700 cursor-pointer">
                        <input type="checkbox" name="aud_paid" value="1" class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" <?= !empty($aud['paid']) ? 'checked' : '' ?>>
                        <span>Communautés avec abonnement payant actif ou période d’essai</span>
                    </label>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm space-y-4">
                <h2 class="text-sm font-black uppercase tracking-[0.12em] text-slate-800">Publication et options</h2>
                <label class="flex items-start gap-2.5 text-sm text-slate-700 cursor-pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" id="pa-active" class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" <?= ($row === null || !empty($row['is_active'])) ? 'checked' : '' ?>>
                    <span>
                        <span class="font-semibold text-slate-900">Publication activée</span>
                        <span class="mt-0.5 block text-xs text-slate-500">Si la case est décochée, l’annonce reste enregistrée mais n’apparaît pas sur le portail.</span>
                    </span>
                </label>
                <label class="flex items-start gap-2.5 text-sm text-slate-700 cursor-pointer">
                    <input type="hidden" name="dismissible" value="0">
                    <input type="checkbox" name="dismissible" value="1" id="pa-dismiss" class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" <?= $dismissible ? 'checked' : '' ?>>
                    <span>
                        <span class="font-semibold text-slate-900">Autoriser le masquage</span>
                        <span class="mt-0.5 block text-xs text-slate-500">Décoché : les membres ne peuvent pas fermer le bandeau (annonce obligatoire jusqu’à la fin de période).</span>
                    </span>
                </label>
                <?php if (!$isEdit): ?>
                <label class="flex items-start gap-2.5 text-sm text-slate-700 cursor-pointer rounded-lg border border-emerald-200 bg-emerald-50/60 px-3 py-3">
                    <input type="checkbox" name="send_email_now" value="1" id="pa-mail" class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    <span>
                        <span class="font-semibold text-emerald-950">Envoyer aussi par e-mail aux comptes actifs</span>
                        <span class="mt-0.5 block text-xs text-emerald-900/80">Diffusion immédiate après création (tous les comptes actifs du portail). Les visiteurs non connectés ne sont pas contactés.</span>
                    </span>
                </label>
                <?php endif; ?>
            </section>

            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="inline-flex items-center rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-emerald-700"><?= $isEdit ? 'Enregistrer' : 'Créer l’annonce' ?></button>
                <a href="<?= url('admin/system/alerts') ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Annuler</a>
            </div>
        </form>
    </div>
</div>
