<?php
declare(strict_types=1);

/** @var array<string, mixed>|null $platformAlert */
/** @var string $formAction */
/** @var string $formMethod */
$isEdit = is_array($platformAlert);
$row = $isEdit ? $platformAlert : [];
$kindOptions = \App\Support\PlatformAlertPresentation::kindOptions();
$kindMeta = [
    'info' => ['hint' => "Message général pour le portail.", 'color' => '#6366f1'],
    'novelty' => ['hint' => "Nouveauté ou changement mis en avant.", 'color' => '#00a870'],
    'discount' => ['hint' => "Offre ou avantage temporaire.", 'color' => '#f59e0b'],
    'urgent' => ['hint' => "Priorité maximale, à lire immédiatement.", 'color' => '#ef4444'],
];
$displayStyleMeta = [
    'classic' => ['hint' => "Bandeau dans la zone d’annonces du portail.", 'swatch' => '#0063cb', 'tag' => 'Information'],
    'mini_info' => ['hint' => "Barre compacte sous le menu — ton information.", 'swatch' => '#0063cb', 'tag' => 'Info'],
    'mini_success' => ['hint' => "Barre compacte sous le menu — ton succès.", 'swatch' => '#18753c', 'tag' => 'Succès'],
    'mini_warning' => ['hint' => "Barre compacte sous le menu — ton attention.", 'swatch' => '#b34000', 'tag' => 'Alerte'],
    'mini_danger' => ['hint' => "Barre compacte sous le menu — ton critique.", 'swatch' => '#ce0500', 'tag' => 'Critique'],
    'breaking' => ['hint' => "Bandeau défilant pour mise à jour ou maintenance.", 'swatch' => '#7f1d1d', 'tag' => 'Attention'],
    'popup' => ['hint' => "Fenêtre à l’arrivée sur le tableau de bord.", 'swatch' => '#334155', 'tag' => 'Pop-up'],
];
$iconSvg = [
    'info' => "<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z\"/></svg>",
    'novelty' => "<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z\"/></svg>",
    'discount' => "<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z\"/></svg>",
    'urgent' => "<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z\"/></svg>",
];
$currentKind = (string) ($row['kind'] ?? 'info');
if (!isset($kindOptions[$currentKind])) {
    $currentKind = 'info';
}
$displayStyleOptions = \App\Support\AlertDisplayStyle::platformOptions();
$styleSource = \App\Support\AlertDisplayStyle::CLASSIC;
if (is_array($row)) {
    $styleSource = is_array($row['display_styles'] ?? null)
        ? $row['display_styles']
        : (string) ($row['display_style'] ?? \App\Support\AlertDisplayStyle::CLASSIC);
}
$currentDisplayStyles = \App\Support\AlertDisplayStyle::parsePlatformList($styleSource);
if ($currentDisplayStyles === []) {
    $currentDisplayStyles = [\App\Support\AlertDisplayStyle::CLASSIC];
}
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
$dismissible = !$isEdit || !isset($row['dismissible']) || (int) $row['dismissible'] === 1;
?>
<style>
.pa-kind-card:has(input:checked),
.pa-style-card:has(input:checked) {
    border-color: #059669 !important;
    background: #ecfdf5 !important;
    box-shadow: 0 0 0 1px rgba(5, 150, 105, 0.35);
}
.pa-kind-card svg { width: 1.25rem; height: 1.25rem; }
.pa-mock { pointer-events: none; margin-top: 0.7rem; border-radius: 0.4rem; overflow: hidden; text-align: left; }
.pa-mock__classic {
    background: #e8edff;
    box-shadow: inset 3px 0 0 #0063cb;
    padding: 0.45rem 0.55rem 0.5rem 0.7rem;
}
.pa-mock__classic strong { display: block; font-size: 0.7rem; font-weight: 800; color: #161616; }
.pa-mock__classic span { display: block; margin-top: 0.1rem; font-size: 0.62rem; color: #3a3a3a; }
.pa-mock__classic.tone-novelty { background: #e8f5e9; box-shadow: inset 3px 0 0 #18753c; }
.pa-mock__classic.tone-discount { background: #fee7a0; box-shadow: inset 3px 0 0 #b34000; }
.pa-mock__classic.tone-urgent { background: #ffe9e9; box-shadow: inset 3px 0 0 #ce0500; }
.pa-mock__mini {
    display: flex; align-items: flex-start; gap: 0.45rem;
    padding: 0.4rem 0.5rem 0.45rem 0.6rem;
    background: #e8edff;
    box-shadow: inset 3px 0 0 #0063cb;
}
.pa-mock__mini.tone-success { background: #b8fec9; box-shadow: inset 3px 0 0 #18753c; }
.pa-mock__mini.tone-warning { background: #fee7a0; box-shadow: inset 3px 0 0 #b34000; }
.pa-mock__mini.tone-danger { background: #ffe9e9; box-shadow: inset 3px 0 0 #ce0500; }
.pa-mock__tag { font-size: 0.58rem; font-weight: 800; flex-shrink: 0; }
.pa-mock__mini.tone-info .pa-mock__tag { color: #0063cb; }
.pa-mock__mini.tone-success .pa-mock__tag { color: #18753c; }
.pa-mock__mini.tone-warning .pa-mock__tag { color: #716043; }
.pa-mock__mini.tone-danger .pa-mock__tag { color: #ce0500; }
.pa-mock__mini p { margin: 0; font-size: 0.65rem; font-weight: 700; color: #161616; line-height: 1.25; }
.pa-mock__breaking {
    background: #ffe9e9;
    box-shadow: inset 3px 0 0 #ce0500;
    padding: 0.4rem 0.5rem;
    display: flex; align-items: center; gap: 0.45rem;
}
.pa-mock__breaking .pa-mock__tag { color: #ce0500; }
.pa-mock__breaking p { margin: 0; font-size: 0.62rem; font-weight: 600; color: #161616; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pa-mock__popup {
    background: rgba(15, 23, 42, 0.35);
    padding: 0.55rem 0.7rem 0.7rem;
}
.pa-mock__popup-card {
    background: #fff;
    border-radius: 0.45rem;
    padding: 0.55rem 0.65rem;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.18);
}
.pa-mock__popup-card strong { display: block; font-size: 0.7rem; color: #0f172a; }
.pa-mock__popup-card span { display: block; margin-top: 0.15rem; font-size: 0.6rem; color: #475569; }
.pa-live { display: flex; flex-direction: column; gap: 0.55rem; }
.pa-live[hidden] { display: none !important; }
</style>
<div class="min-h-0 flex-1 bg-slate-50">
    <div class="w-full px-4 sm:px-5 lg:px-6 py-4 sm:py-5 space-y-5">

        <header class="relative overflow-hidden rounded-xl border border-emerald-200/90 bg-gradient-to-br from-emerald-50/90 via-white to-slate-50 shadow-sm">
            <div class="absolute inset-y-0 left-0 w-1 bg-emerald-600" aria-hidden="true"></div>
            <div class="relative px-4 sm:px-6 py-5 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-[11px] font-black uppercase tracking-[0.22em] text-emerald-800/90">Administration · Plateforme</p>
                    <h1 class="mt-1.5 text-2xl font-black tracking-tight text-slate-900"><?= $isEdit ? 'Modifier l’annonce' : 'Nouvelle annonce' ?></h1>
                    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">
                        Message court sur le portail : bandeau, barre sous le menu, bandeau Attention ou fenêtre. Vous pouvez combiner plusieurs emplacements.
                    </p>
                </div>
                <a href="<?= url('admin/system/alerts') ?>" class="inline-flex shrink-0 items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Retour à la liste</a>
            </div>
        </header>

        <?php $e = \App\Core\Session::getFlash('error'); ?>
        <?php if ($e): ?>
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800" role="alert"><?= htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form id="pa-alert-form" method="<?= htmlspecialchars($formMethod, ENT_QUOTES, 'UTF-8') ?>" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>" class="space-y-5">
            <?= \App\Core\Csrf::field() ?>

            <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm space-y-6">
                <h2 class="text-sm font-black uppercase tracking-[0.12em] text-slate-800">Type &amp; emplacement</h2>

                <fieldset>
                    <legend class="mb-3 block text-sm font-semibold text-slate-800">Type d’annonce</legend>
                    <div class="grid gap-3 sm:grid-cols-2" role="radiogroup" aria-label="Type d’annonce">
                        <?php foreach ($kindOptions as $v => $lab):
                            $meta = $kindMeta[$v] ?? ['hint' => '', 'color' => '#64748b'];
                            $toneClass = match ((string) $v) {
                                'novelty' => 'tone-novelty',
                                'discount' => 'tone-discount',
                                'urgent' => 'tone-urgent',
                                default => '',
                            };
                            ?>
                            <label class="pa-kind-card flex cursor-pointer flex-col rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm transition hover:border-emerald-300">
                                <span class="flex items-start gap-3">
                                    <input type="radio" name="kind" value="<?= htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') ?>" class="mt-1 border-slate-300 text-emerald-600 focus:ring-emerald-500" <?= $currentKind === $v ? 'checked' : '' ?> data-pa-kind>
                                    <span class="min-w-0 flex-1">
                                        <span class="flex items-center gap-2" style="color:<?= htmlspecialchars($meta['color'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?= $iconSvg[$v] ?? $iconSvg['info'] ?>
                                            <span class="font-semibold text-slate-900"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></span>
                                        </span>
                                        <span class="mt-0.5 block text-xs leading-relaxed text-slate-500"><?= htmlspecialchars($meta['hint'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </span>
                                </span>
                                <span class="pa-mock pa-mock__classic <?= htmlspecialchars($toneClass, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true">
                                    <strong><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span>Aperçu du bandeau selon ce type.</span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="mb-3 block text-sm font-semibold text-slate-800">Emplacements d’affichage</legend>
                    <p class="mb-3 text-xs text-slate-500">Vous pouvez cocher plusieurs emplacements : la même annonce apparaîtra à chacun d’eux.</p>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" role="group" aria-label="Emplacements d’affichage">
                        <?php foreach ($displayStyleOptions as $v => $lab):
                            $meta = $displayStyleMeta[$v] ?? ['hint' => '', 'swatch' => '#64748b', 'tag' => ''];
                            $checked = in_array((string) $v, $currentDisplayStyles, true);
                            ?>
                            <label class="pa-style-card flex cursor-pointer flex-col rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm transition hover:border-emerald-300">
                                <span class="flex items-start gap-3">
                                    <input type="checkbox" name="display_styles[]" value="<?= htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') ?>" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" <?= $checked ? 'checked' : '' ?> data-pa-style>
                                    <span class="min-w-0">
                                        <span class="flex items-center gap-2">
                                            <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full" style="background:<?= htmlspecialchars($meta['swatch'], ENT_QUOTES, 'UTF-8') ?>"></span>
                                            <span class="font-semibold text-slate-900"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></span>
                                        </span>
                                        <span class="mt-0.5 block text-xs leading-relaxed text-slate-500"><?= htmlspecialchars($meta['hint'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </span>
                                </span>
                                <?php if ((string) $v === 'classic'): ?>
                                    <span class="pa-mock pa-mock__classic" aria-hidden="true">
                                        <strong>Titre de l’annonce</strong>
                                        <span>Zone d’annonces du portail</span>
                                    </span>
                                <?php elseif ((string) $v === 'breaking'): ?>
                                    <span class="pa-mock pa-mock__breaking" aria-hidden="true">
                                        <span class="pa-mock__tag"><?= htmlspecialchars((string) $meta['tag'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <p>Texte qui défile sous le menu</p>
                                    </span>
                                <?php elseif ((string) $v === 'popup'): ?>
                                    <span class="pa-mock pa-mock__popup" aria-hidden="true">
                                        <span class="pa-mock__popup-card">
                                            <strong>Titre de l’annonce</strong>
                                            <span>Fenêtre au tableau de bord</span>
                                        </span>
                                    </span>
                                <?php else:
                                    $miniTone = match ((string) $v) {
                                        'mini_success' => 'tone-success',
                                        'mini_warning' => 'tone-warning',
                                        'mini_danger' => 'tone-danger',
                                        default => 'tone-info',
                                    };
                                    ?>
                                    <span class="pa-mock pa-mock__mini <?= htmlspecialchars($miniTone, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true">
                                        <span class="pa-mock__tag"><?= htmlspecialchars((string) $meta['tag'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <p>Titre de l’annonce</p>
                                    </span>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">Les barres sous le menu et le bandeau Attention s’affichent sur toute la largeur, juste sous la navigation.</p>
                </fieldset>

                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3.5">
                    <p class="text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">Aperçu avec votre texte</p>
                    <p class="mt-0.5 text-xs text-slate-500">Les emplacements cochés se mettent à jour au fil de la saisie.</p>
                    <div id="pa-live" class="pa-live mt-3" aria-live="polite"></div>
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
                    <input type="checkbox" name="is_active" value="1" id="pa-active" class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" <?= (!$isEdit || !empty($row['is_active'])) ? 'checked' : '' ?>>
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
<script>
(function () {
  var form = document.getElementById('pa-alert-form');
  var live = document.getElementById('pa-live');
  if (!form || !live) return;

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function selectedKind() {
    var el = form.querySelector('input[data-pa-kind]:checked');
    return el ? el.value : 'info';
  }

  function selectedStyles() {
    return Array.prototype.map.call(
      form.querySelectorAll('input[data-pa-style]:checked'),
      function (el) { return el.value; }
    );
  }

  function classicTone(kind) {
    if (kind === 'novelty') return ' tone-novelty';
    if (kind === 'discount') return ' tone-discount';
    if (kind === 'urgent') return ' tone-urgent';
    return '';
  }

  function miniTone(style) {
    if (style === 'mini_success') return ' tone-success';
    if (style === 'mini_warning') return ' tone-warning';
    if (style === 'mini_danger') return ' tone-danger';
    return ' tone-info';
  }

  function miniTag(style) {
    if (style === 'mini_success') return 'Succès';
    if (style === 'mini_warning') return 'Alerte';
    if (style === 'mini_danger') return 'Critique';
    if (style === 'breaking') return 'Attention';
    return 'Info';
  }

  function render() {
    var title = (document.getElementById('pa-title') || {}).value || 'Titre de l’annonce';
    var body = (document.getElementById('pa-body') || {}).value || '';
    var kind = selectedKind();
    var styles = selectedStyles();
    if (!styles.length) {
      live.innerHTML = '<p class="text-sm text-amber-800">Cochez au moins un emplacement pour voir l’aperçu.</p>';
      return;
    }
    live.innerHTML = styles.map(function (style) {
      var t = esc(title.trim() || 'Titre de l’annonce');
      var b = esc((body || '').trim());
      if (style === 'classic') {
        return '<div class="pa-mock pa-mock__classic' + classicTone(kind) + '"><strong>' + t + '</strong>' + (b ? '<span>' + b + '</span>' : '') + '</div>';
      }
      if (style === 'breaking') {
        return '<div class="pa-mock pa-mock__breaking"><span class="pa-mock__tag">' + esc(miniTag(style)) + '</span><p>' + t + (b ? ' · ' + b : '') + '</p></div>';
      }
      if (style === 'popup') {
        return '<div class="pa-mock pa-mock__popup"><div class="pa-mock__popup-card"><strong>' + t + '</strong>' + (b ? '<span>' + b + '</span>' : '<span>Fenêtre au tableau de bord</span>') + '</div></div>';
      }
      return '<div class="pa-mock pa-mock__mini' + miniTone(style) + '"><span class="pa-mock__tag">' + esc(miniTag(style)) + '</span><p>' + t + '</p></div>';
    }).join('');
  }

  form.addEventListener('input', render);
  form.addEventListener('change', render);
  form.addEventListener('submit', function (e) {
    if (!selectedStyles().length) {
      e.preventDefault();
      live.innerHTML = '<p class="text-sm text-amber-800">Cochez au moins un emplacement d’affichage.</p>';
      var first = form.querySelector('input[data-pa-style]');
      if (first) first.focus();
    }
  });
  render();
})();
</script>
