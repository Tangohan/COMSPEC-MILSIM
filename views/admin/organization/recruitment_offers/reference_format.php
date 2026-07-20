<?php
declare(strict_types=1);
/** @var array<string,mixed> $format */
/** @var string $prospectionDocumentRef */
/** @var string $previewReference */
/** @var int $previewYear */
/** @var int $previewSeq */
/** @var int $previewLastSeq */
/** @var string $previewUnitLabel */
/** @var string $previewTenantName */
/** @var bool $previewHasUnits */
$format = is_array($format ?? null) ? $format : [];
$prospectionDocumentRef = $prospectionDocumentRef ?? '';
$previewReference = $previewReference ?? '';
$previewYear = (int) ($previewYear ?? (int) date('Y'));
$previewSeq = (int) ($previewSeq ?? 1);
$previewLastSeq = (int) ($previewLastSeq ?? 0);
$previewUnitLabel = trim((string) ($previewUnitLabel ?? ''));
$previewTenantName = trim((string) ($previewTenantName ?? ''));
$previewHasUnits = (bool) ($previewHasUnits ?? false);
$previewSeqPadded = str_pad((string) max(1, $previewSeq), 4, '0', STR_PAD_LEFT);
$fieldClass = 'ref-format-field w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/25';
$labelClass = 'mb-1.5 block text-sm font-semibold text-slate-800';
$hintClass = 'mt-1.5 text-xs leading-relaxed text-slate-500';

$segmentCards = [
    [
        'name' => 'include_organization_tag',
        'checked' => !empty($format['include_organization_tag']),
        'title' => 'Sigle de la communauté',
        'desc' => 'Ajoute un court sigle au début de la référence (ex. nom abrégé de votre organisation).',
        'extra' => 'organization_tag',
        'extra_label' => 'Sigle personnalisé (optionnel)',
        'extra_hint' => 'Si ce champ reste vide, Athena utilise le code communauté ou un abrégé du nom du portail.',
        'extra_value' => (string) ($format['organization_tag'] ?? ''),
        'extra_max' => 32,
        'extra_placeholder' => 'Ex. ORION, 1RG…',
        'extra_mono' => false,
    ],
    [
        'name' => 'include_ao_segment',
        'checked' => !empty($format['include_ao_segment']),
        'title' => 'Type d’avis',
        'desc' => 'Indique qu’il s’agit d’un appel à candidatures (souvent AO, AOC ou AVIS).',
        'extra' => 'ao_segment',
        'extra_label' => 'Texte du type d’avis',
        'extra_hint' => 'Court, en majuscules si possible.',
        'extra_value' => (string) ($format['ao_segment'] ?? 'AO'),
        'extra_max' => 12,
        'extra_placeholder' => 'AO',
        'extra_mono' => true,
    ],
    [
        'name' => 'include_unit_code',
        'checked' => !empty($format['include_unit_code']),
        'title' => 'Code de l’unité porteuse',
        'desc' => 'Reprend le code enregistré pour l’unité choisie sur chaque avis.',
        'extra' => null,
    ],
    [
        'name' => 'include_unit_name_abbr',
        'checked' => !empty($format['include_unit_name_abbr']),
        'title' => 'Abrégé du nom d’unité',
        'desc' => 'Construit un abrégé à partir du nom complet de l’unité (plusieurs mots).',
        'extra' => null,
    ],
    [
        'name' => 'include_arm_domain_abbr',
        'checked' => !empty($format['include_arm_domain_abbr']),
        'title' => 'Domaine d’armes',
        'desc' => 'Ajoute l’abrégé du domaine (ex. infanterie → INF, transmissions → TRS).',
        'extra' => null,
    ],
    [
        'name' => 'include_rec_segment',
        'checked' => !empty($format['include_rec_segment']),
        'title' => 'Mention recrutement',
        'desc' => 'Ajoute un libellé fixe pour marquer clairement qu’il s’agit du recrutement.',
        'extra' => 'rec_segment',
        'extra_label' => 'Texte de la mention',
        'extra_hint' => 'Souvent REC.',
        'extra_value' => (string) ($format['rec_segment'] ?? 'REC'),
        'extra_max' => 16,
        'extra_placeholder' => 'REC',
        'extra_mono' => true,
    ],
];
?>
<div class="reference-format-page recruitment-bureau max-w-5xl w-full space-y-8">
    <nav class="overflow-hidden rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm sm:px-5" aria-label="Fil d’Ariane">
        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-slate-600">
            <a href="<?= htmlspecialchars(url('back-office/recruitment/offers'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border border-transparent px-2 py-1.5 text-slate-700 transition hover:border-slate-200 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40 focus-visible:ring-offset-2">Offres publiées</a>
            <span class="text-slate-300" aria-hidden="true">/</span>
            <span class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 font-bold text-slate-900">Format des références</span>
        </div>
    </nav>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 bg-slate-50 px-5 py-6 sm:px-8 sm:py-7">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-emerald-700">Vitrine · Avis de vacance</p>
                    <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Format des références</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600">
                        Composez la référence affichée sur chaque avis au moment de la publication, ainsi que la mention courte en tête de la liste publique des offres.
                    </p>
                </div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    <a href="<?= htmlspecialchars(url('back-office/recruitment/offers/create'), ENT_QUOTES, 'UTF-8') ?>" class="recruitment-lms-submit-secondary inline-flex min-h-[2.5rem] items-center rounded-xl px-3 py-2 text-[10px] font-bold uppercase tracking-[0.18em] shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2">
                        Nouvelle offre
                    </a>
                    <a href="<?= htmlspecialchars(url('back-office/recruitment/offers'), ENT_QUOTES, 'UTF-8') ?>" class="recruitment-lms-nav-dark inline-flex min-h-[2.5rem] items-center rounded-xl px-3 py-2 text-[10px] font-bold uppercase tracking-[0.18em] shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40 focus-visible:ring-offset-2">
                        ← Offres publiées
                    </a>
                </div>
            </div>
        </div>

        <div class="border-b border-slate-200 bg-white px-5 py-6 sm:px-8">
            <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-slate-500">Aperçu enregistré</p>
            <div class="ref-format-preview mt-4 rounded-2xl border border-emerald-200/80 bg-emerald-50/40 p-5 sm:p-6">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-800/80">Référence type</p>
                <p class="ref-format-preview__value mt-2 font-mono text-lg font-bold tracking-tight text-slate-900 break-all sm:text-xl"><?= htmlspecialchars($previewReference, ENT_QUOTES, 'UTF-8') ?></p>
                <ul class="ref-format-preview__meta mt-5 grid gap-3 text-xs leading-relaxed text-slate-600 sm:grid-cols-2">
                    <li class="rounded-xl border border-white/80 bg-white/70 px-3.5 py-3">
                        <span class="block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-500">Communauté</span>
                        <span class="mt-1 block font-semibold text-slate-800"><?= htmlspecialchars($previewTenantName !== '' ? $previewTenantName : '—', ENT_QUOTES, 'UTF-8') ?></span>
                    </li>
                    <li class="rounded-xl border border-white/80 bg-white/70 px-3.5 py-3">
                        <span class="block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-500">Unité d’exemple</span>
                        <span class="mt-1 block font-semibold text-slate-800"><?= htmlspecialchars($previewUnitLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="mt-1 block text-slate-500">Première unité de l’administration. Chaque avis utilisera l’unité porteuse choisie.</span>
                    </li>
                    <li class="rounded-xl border border-white/80 bg-white/70 px-3.5 py-3">
                        <span class="block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-500">Domaine illustré</span>
                        <span class="mt-1 block font-semibold text-slate-800">Transmissions (TRS)</span>
                        <span class="mt-1 block text-slate-500">Uniquement pour montrer le domaine lorsqu’il est activé.</span>
                    </li>
                    <li class="rounded-xl border border-white/80 bg-white/70 px-3.5 py-3">
                        <span class="block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-500">Numéro de fin</span>
                        <span class="mt-1 block font-semibold text-slate-800 tabular-nums"><?= (int) $previewYear ?>-<?= htmlspecialchars($previewSeqPadded, ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="mt-1 block text-slate-500"><?= $previewLastSeq === 0
                            ? 'Prochain numéro prévu — aucune publication enregistrée cette année.'
                            : 'Prochain numéro prévu pour une nouvelle publication cette année.' ?></span>
                    </li>
                </ul>
                <?php if (!$previewHasUnits && !empty($format['include_unit_code'])): ?>
                    <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-3 text-xs leading-relaxed text-amber-950" role="status">
                        Aucune unité n’est encore créée : le code d’unité dans l’aperçu repose sur un libellé de secours tant qu’aucune unité réelle n’existe.
                    </p>
                <?php endif; ?>
                <p class="mt-4 text-xs text-slate-500">Cet aperçu reflète les réglages déjà enregistrés. Enregistrez le formulaire pour le mettre à jour.</p>
            </div>
        </div>

        <form action="<?= htmlspecialchars(url('back-office/recruitment/reference-format'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="px-5 py-8 sm:px-8 sm:py-10 space-y-8">
            <?= \App\Core\Csrf::field() ?>

            <section class="ref-format-section rounded-2xl border border-slate-200 bg-slate-50/40 p-5 sm:p-7" aria-labelledby="ref-format-vitrine-title">
                <div class="mb-6">
                    <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-slate-500">Liste publique</p>
                    <h2 id="ref-format-vitrine-title" class="mt-1 text-lg font-black tracking-tight text-slate-900">Mention en tête des offres</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">
                        Texte court affiché au-dessus du tableau des avis sur la vitrine (ex. service ou bureau en charge du recrutement).
                    </p>
                </div>
                <div>
                    <label for="prospection_document_ref" class="<?= htmlspecialchars($labelClass, ENT_QUOTES, 'UTF-8') ?>">Mention affichée aux visiteurs</label>
                    <input
                        type="text"
                        id="prospection_document_ref"
                        name="prospection_document_ref"
                        value="<?= htmlspecialchars($prospectionDocumentRef, ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Ex. DRH / Bureau recrutement"
                        class="<?= htmlspecialchars($fieldClass, ENT_QUOTES, 'UTF-8') ?> max-w-xl"
                        maxlength="120"
                    />
                    <p class="<?= htmlspecialchars($hintClass, ENT_QUOTES, 'UTF-8') ?>">Phrase courte et lisible. Évitez les codes internes inutiles pour le public.</p>
                </div>
            </section>

            <section class="ref-format-section rounded-2xl border border-slate-200 bg-white p-5 sm:p-7" aria-labelledby="ref-format-compose-title">
                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-slate-500">Composition</p>
                        <h2 id="ref-format-compose-title" class="mt-1 text-lg font-black tracking-tight text-slate-900">Parties de la référence</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">
                            Cochez les éléments à enchaîner, puis choisissez le caractère qui les sépare. L’année et le numéro d’ordre sont toujours ajoutés à la fin.
                        </p>
                    </div>
                    <div class="shrink-0">
                        <label for="separator" class="<?= htmlspecialchars($labelClass, ENT_QUOTES, 'UTF-8') ?>">Caractère entre les parties</label>
                        <input
                            type="text"
                            id="separator"
                            name="separator"
                            value="<?= htmlspecialchars((string) ($format['separator'] ?? '/'), ENT_QUOTES, 'UTF-8') ?>"
                            maxlength="4"
                            class="<?= htmlspecialchars($fieldClass, ENT_QUOTES, 'UTF-8') ?> ref-format-field--sep w-24 text-center font-mono text-base font-bold"
                            aria-describedby="separator-help"
                        />
                        <p id="separator-help" class="<?= htmlspecialchars($hintClass, ENT_QUOTES, 'UTF-8') ?>">Ex. / − ·</p>
                    </div>
                </div>

                <div class="ref-format-segments grid gap-3">
                    <?php foreach ($segmentCards as $card): ?>
                        <?php
                        $inputId = 'seg_' . $card['name'];
                        $hasExtra = !empty($card['extra']);
                        ?>
                        <div class="ref-format-segment rounded-xl border border-slate-200 bg-slate-50/70 transition focus-within:border-emerald-300 focus-within:bg-white focus-within:shadow-sm">
                            <label for="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>" class="flex cursor-pointer items-start gap-3 p-4 sm:p-5">
                                <input type="hidden" name="<?= htmlspecialchars($card['name'], ENT_QUOTES, 'UTF-8') ?>" value="0" />
                                <input
                                    type="checkbox"
                                    id="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>"
                                    name="<?= htmlspecialchars($card['name'], ENT_QUOTES, 'UTF-8') ?>"
                                    value="1"
                                    class="ref-format-segment__check mt-1 h-4 w-4 rounded border-slate-300 text-emerald-700 focus:ring-emerald-500/30"
                                    <?= !empty($card['checked']) ? 'checked' : '' ?>
                                    <?php if ($hasExtra): ?>aria-controls="<?= htmlspecialchars('extra_' . $card['extra'], ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>
                                />
                                <span class="min-w-0">
                                    <span class="block text-sm font-bold text-slate-900"><?= htmlspecialchars($card['title'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="mt-1 block text-xs leading-relaxed text-slate-600"><?= htmlspecialchars($card['desc'], ENT_QUOTES, 'UTF-8') ?></span>
                                </span>
                            </label>
                            <?php if ($hasExtra): ?>
                                <div id="<?= htmlspecialchars('extra_' . $card['extra'], ENT_QUOTES, 'UTF-8') ?>" class="ref-format-segment__extra border-t border-slate-200/80 px-4 pb-4 pt-0 sm:px-5 sm:pb-5">
                                    <div class="ml-7 sm:ml-8 pt-3">
                                        <label for="<?= htmlspecialchars((string) $card['extra'], ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($labelClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $card['extra_label'], ENT_QUOTES, 'UTF-8') ?></label>
                                        <input
                                            type="text"
                                            id="<?= htmlspecialchars((string) $card['extra'], ENT_QUOTES, 'UTF-8') ?>"
                                            name="<?= htmlspecialchars((string) $card['extra'], ENT_QUOTES, 'UTF-8') ?>"
                                            value="<?= htmlspecialchars((string) $card['extra_value'], ENT_QUOTES, 'UTF-8') ?>"
                                            maxlength="<?= (int) $card['extra_max'] ?>"
                                            placeholder="<?= htmlspecialchars((string) ($card['extra_placeholder'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            class="<?= htmlspecialchars($fieldClass, ENT_QUOTES, 'UTF-8') ?> max-w-xs<?= !empty($card['extra_mono']) ? ' font-mono uppercase' : '' ?>"
                                        />
                                        <p class="<?= htmlspecialchars($hintClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $card['extra_hint'], ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 pt-8">
                <button type="submit" class="recruitment-lms-submit-primary inline-flex min-h-[2.75rem] items-center justify-center rounded-xl px-8 py-2.5 text-sm font-bold shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40 focus-visible:ring-offset-2">
                    Enregistrer le format
                </button>
                <a href="<?= htmlspecialchars(url('back-office/recruitment/offers'), ENT_QUOTES, 'UTF-8') ?>" class="recruitment-lms-submit-secondary inline-flex min-h-[2.75rem] items-center justify-center rounded-xl px-6 py-2.5 text-sm font-semibold shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2">
                    Annuler
                </a>
            </div>
        </form>
    </div>
</div>
