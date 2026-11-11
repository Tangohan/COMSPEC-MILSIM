<?php
declare(strict_types=1);
/**
 * Back-office › Renseignement › Fichiers SSE : onglets Fiches (recherche, doublons, fusion), Extractions de téléphones
 * (modération), Journal des consultations, Réglages (pays par défaut, conservation, droits). Clair et sombre comme le
 * reste du back-office ; aucun petit libellé au-dessus des titres.
 *
 * @var string $tab
 * @var string $q
 */
use App\Controllers\Admin\AdminSseFilesController as C;
use App\Support\SseRecordsCountryPacks as Packs;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$base = url(C::BASE);
$card = 'rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900';
$muted = 'text-slate-500 dark:text-slate-400';
$input = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';
$btn = 'inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-800 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700';
$pri = 'inline-flex items-center justify-center rounded-lg bg-emerald-600 px-3 py-2 text-sm font-bold text-white hover:bg-emerald-500';
$th = 'px-4 py-2 text-left text-xs font-bold ' . $muted;
$td = 'px-4 py-2 align-top text-sm text-slate-800 dark:text-slate-200';
?>
<div class="mx-auto max-w-6xl space-y-5 px-4 py-6">
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black text-slate-900 dark:text-white">Fichiers SSE</h1>
            <p class="mt-1 max-w-3xl text-sm <?= $muted ?>">Gestion des fichiers du Bureau SSE : fiches des sujets, doublons et fusion, extractions de téléphones du jeu, journal complet des consultations et réglages du module. Données fictives du jeu uniquement.</p>
        </div>
        <a class="<?= $btn ?>" href="<?= $h(url('atak/sse/fichiers')) ?>">Ouvrir le passage aux fichiers</a>
    </header>

    <nav class="flex flex-wrap gap-2" aria-label="Sections">
        <?php foreach (C::TABS as $k => $label): ?>
            <a href="<?= $h($base . ($k === 'fiches' ? '' : '/' . $k)) ?>" class="rounded-lg px-3 py-2 text-sm font-bold <?= $tab === $k ? 'bg-emerald-600 text-white' : 'border border-slate-300 text-slate-700 dark:border-slate-600 dark:text-slate-200' ?>"<?= $tab === $k ? ' aria-current="page"' : '' ?>><?= $h($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($tab === 'fiches'): ?>
        <?php $subjects = $subjects ?? ['rows' => [], 'total' => 0]; ?>
        <section class="<?= $card ?> overflow-hidden">
            <form method="get" action="<?= $h($base) ?>" class="flex flex-wrap items-end gap-3 border-b border-slate-100 p-4 dark:border-slate-800">
                <label class="flex-1 text-xs font-bold <?= $muted ?>">Chercher une fiche
                    <input class="<?= $input ?> mt-1" name="q" value="<?= $h($q) ?>" placeholder="Nom, prénom, alias, identifiant d’IA">
                </label>
                <button class="<?= $btn ?>" type="submit">Chercher</button>
                <span class="text-sm <?= $muted ?>"><?= (int) $subjects['total'] ?> fiche(s)</span>
            </form>
            <div class="overflow-x-auto"><table class="min-w-full">
                <thead><tr><th class="<?= $th ?>">Fiche</th><th class="<?= $th ?>">Nom</th><th class="<?= $th ?>">Naissance</th><th class="<?= $th ?>">Alias</th><th class="<?= $th ?>">Liens</th><th class="<?= $th ?>">Photos</th><th class="<?= $th ?>">Consultations</th><th class="<?= $th ?>"></th></tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($subjects['rows'] as $s): ?>
                    <tr>
                        <td class="<?= $td ?> font-mono"><?= $h($s['ref']) ?></td>
                        <td class="<?= $td ?> font-bold" translate="no"><?= $h($s['display_name']) ?><?= $s['merged_into_id'] > 0 ? ' <span class="ml-1 rounded bg-amber-100 px-1.5 text-xs text-amber-800">fusionnée dans IDT-' . (int) $s['merged_into_id'] . '</span>' : '' ?></td>
                        <td class="<?= $td ?> font-mono"><?= $h($s['birth_date'] ?? '—') ?></td>
                        <td class="<?= $td ?>"><?= (int) $s['aliases_count'] ?></td>
                        <td class="<?= $td ?>"><?= (int) $s['links_count'] ?></td>
                        <td class="<?= $td ?>"><?= (int) $s['photos_count'] ?></td>
                        <td class="<?= $td ?>"><?= (int) $s['queries_count'] ?></td>
                        <td class="<?= $td ?>"><a class="font-bold text-emerald-700 dark:text-emerald-400" href="<?= $h(url('atak/sse/fichiers/sujet/' . (int) $s['id'])) ?>">Ouvrir</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($subjects['rows'] === []): ?><tr><td class="<?= $td ?>" colspan="8">Aucune fiche<?= $q !== '' ? ' pour cette recherche' : '' ?>.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
            <?php if ($subjects['total'] > 40): ?>
                <div class="flex gap-2 p-4">
                    <?php if (($page ?? 1) > 1): ?><a class="<?= $btn ?>" href="<?= $h($base . '?' . http_build_query(['q' => $q, 'page' => $page - 1])) ?>">Précédente</a><?php endif; ?>
                    <?php if (($page ?? 1) * 40 < $subjects['total']): ?><a class="<?= $btn ?>" href="<?= $h($base . '?' . http_build_query(['q' => $q, 'page' => $page + 1])) ?>">Suivante</a><?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="<?= $card ?> p-5">
            <h2 class="text-base font-black text-slate-900 dark:text-white">Doublons probables et fusion</h2>
            <p class="mt-1 text-sm <?= $muted ?>">Même nom à la prononciation et même date de naissance. Fusionner déplace alias, liens, photos, annotations, mentions, contacts et extractions vers la fiche gardée ; l’autre fiche sort des fichiers sans être supprimée (ses papiers restent consultables).</p>
            <?php if (($duplicates ?? []) === []): ?><p class="mt-3 text-sm <?= $muted ?>">Aucun doublon probable.</p><?php endif; ?>
            <ul class="mt-3 space-y-2">
                <?php foreach (($duplicates ?? []) as $d): ?>
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                        <span class="text-sm"><b translate="no"><?= $h($d['a']['display_name']) ?></b> (<?= $h($d['a']['ref']) ?>) et <b translate="no"><?= $h($d['b']['display_name']) ?></b> (<?= $h($d['b']['ref']) ?>) · <span class="<?= $muted ?>"><?= $h($d['reason']) ?></span></span>
                        <span class="flex gap-2">
                            <a class="<?= $btn ?>" href="<?= $h(url('atak/sse/fichiers/comparer') . '?a=' . (int) $d['a']['id'] . '&b=' . (int) $d['b']['id']) ?>">Comparer</a>
                            <form method="post" action="<?= $h($base . '/fusion') ?>"><?= \App\Core\Csrf::field() ?><input type="hidden" name="from" value="<?= (int) $d['b']['id'] ?>"><input type="hidden" name="into" value="<?= (int) $d['a']['id'] ?>"><button class="<?= $btn ?>" type="submit">Fusionner dans <?= $h($d['a']['ref']) ?></button></form>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <form method="post" action="<?= $h($base . '/fusion') ?>" class="mt-4 grid gap-3" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))"><?= \App\Core\Csrf::field() ?>
                <label class="text-xs font-bold <?= $muted ?>">Fiche à fusionner<input class="<?= $input ?> mt-1 font-mono" name="from" required placeholder="IDT-42"></label>
                <label class="text-xs font-bold <?= $muted ?>">Dans la fiche gardée<input class="<?= $input ?> mt-1 font-mono" name="into" required placeholder="IDT-17"></label>
                <div class="flex items-end"><button class="<?= $pri ?>" type="submit">Fusionner</button></div>
            </form>
        </section>
    <?php elseif ($tab === 'extractions'): ?>
        <?php $ex = $extractions ?? ['rows' => [], 'total' => 0]; ?>
        <section class="<?= $card ?> overflow-hidden">
            <form method="get" action="<?= $h($base . '/extractions') ?>" class="flex flex-wrap items-end gap-3 border-b border-slate-100 p-4 dark:border-slate-800">
                <label class="flex-1 text-xs font-bold <?= $muted ?>">Chercher<input class="<?= $input ?> mt-1" name="q" value="<?= $h($q) ?>" placeholder="EXT-…, appareil, propriétaire"></label>
                <label class="text-xs font-bold <?= $muted ?>">État<select class="<?= $input ?> mt-1" name="etat"><option value="">Tous</option><option value="visible"<?= ($status ?? '') === 'visible' ? ' selected' : '' ?>>Visibles</option><option value="review"<?= ($status ?? '') === 'review' ? ' selected' : '' ?>>À valider</option><option value="hidden"<?= ($status ?? '') === 'hidden' ? ' selected' : '' ?>>Masquées</option></select></label>
                <button class="<?= $btn ?>" type="submit">Filtrer</button>
                <span class="text-sm <?= $muted ?>"><?= (int) $ex['total'] ?> extraction(s)</span>
            </form>
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($ex['rows'] as $e): ?>
                    <li class="flex flex-wrap items-center justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <a class="font-mono font-bold text-emerald-700 dark:text-emerald-400" href="<?= $h(url('atak/sse/fichiers/telephones/' . (int) $e['id'])) ?>"><?= $h($e['ref']) ?></a>
                            <span class="ml-2 text-sm text-slate-800 dark:text-slate-200" translate="no"><?= $h($e['device_label'] ?? $e['device_uid']) ?> · <?= $h($e['owner_label'] ?? 'propriétaire inconnu') ?></span>
                            <p class="text-xs <?= $muted ?>"><?= $h(substr((string) $e['created_at'], 0, 16)) ?>Z · <?= $h(['visible' => 'visible', 'hidden' => 'masquée', 'review' => 'à valider'][$e['status']] ?? $e['status']) ?><?= ($e['moderation_note'] ?? '') !== '' ? ' · ' . $h($e['moderation_note']) : '' ?></p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <form method="post" action="<?= $h($base . '/extractions/' . (int) $e['id']) ?>" class="flex gap-2"><?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="action" value="<?= $e['status'] === 'visible' ? 'hide' : 'show' ?>">
                                <input class="<?= $input ?> w-48" name="note" maxlength="255" placeholder="Motif (facultatif)" aria-label="Motif">
                                <button class="<?= $btn ?>" type="submit"><?= $e['status'] === 'visible' ? 'Masquer' : 'Rendre visible' ?></button>
                            </form>
                            <details class="text-sm"><summary class="cursor-pointer font-bold text-rose-700 dark:text-rose-400">Supprimer</summary>
                                <form method="post" action="<?= $h($base . '/extractions/' . (int) $e['id']) ?>" class="mt-2 flex gap-2"><?= \App\Core\Csrf::field() ?><input type="hidden" name="action" value="purge">
                                    <input class="<?= $input ?> w-40" name="confirm" placeholder="Tapez SUPPRIMER" aria-label="Confirmation">
                                    <button class="<?= $btn ?>" type="submit">Supprimer définitivement</button>
                                </form>
                                <p class="mt-1 text-xs <?= $muted ?>">Sans retour possible : l’extraction et ses éléments sont effacés.</p>
                            </details>
                        </div>
                    </li>
                <?php endforeach; ?>
                <?php if ($ex['rows'] === []): ?><li class="p-4 text-sm <?= $muted ?>">Aucune extraction.</li><?php endif; ?>
            </ul>
        </section>
    <?php elseif ($tab === 'journal'): ?>
        <section class="<?= $card ?> overflow-hidden">
            <form method="get" action="<?= $h($base . '/journal') ?>" class="flex flex-wrap items-end gap-3 border-b border-slate-100 p-4 dark:border-slate-800">
                <label class="flex-1 text-xs font-bold <?= $muted ?>">Chercher<input class="<?= $input ?> mt-1" name="q" value="<?= $h($q) ?>" placeholder="QRN, nom, numéro, opérateur"></label>
                <button class="<?= $btn ?>" type="submit">Chercher</button>
            </form>
            <div class="overflow-x-auto"><table class="min-w-full">
                <thead><tr><th class="<?= $th ?>">Requête</th><th class="<?= $th ?>">Heure</th><th class="<?= $th ?>">Type</th><th class="<?= $th ?>">Source</th><th class="<?= $th ?>">Opérateur</th><th class="<?= $th ?>">Critères</th><th class="<?= $th ?>">Réponse</th></tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach (($rows ?? []) as $r): ?>
                    <tr>
                        <td class="<?= $td ?> font-mono"><?= $h($r['qrn']) ?></td>
                        <td class="<?= $td ?> font-mono"><?= $h(substr((string) $r['created_at'], 0, 16)) ?></td>
                        <td class="<?= $td ?>"><?= $h($r['query_type']) ?></td>
                        <td class="<?= $td ?>"><?= $h($r['source_label']) ?></td>
                        <td class="<?= $td ?>" translate="no"><?= $h($r['operator_label'] ?? ($r['operator_user_id'] ? '#' . $r['operator_user_id'] : '—')) ?></td>
                        <td class="<?= $td ?> font-mono text-xs" translate="no"><?= $h(implode(' · ', array_map('strval', array_filter($r['criteria'], 'is_scalar')))) ?></td>
                        <td class="<?= $td ?> font-mono"><?= $r['hit'] ? 'HIT' : 'NO HIT' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (($rows ?? []) === []): ?><tr><td class="<?= $td ?>" colspan="7">Aucune consultation.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </section>
    <?php else: ?>
        <?php $st = $settings ?? []; ?>
        <form method="post" action="<?= $h($base . '/reglages') ?>" class="<?= $card ?> space-y-5 p-5"><?= \App\Core\Csrf::field() ?>
            <div>
                <label class="block text-sm font-bold text-slate-900 dark:text-white" for="fx-country">Pays par défaut du terminal</label>
                <select id="fx-country" class="<?= $input ?> mt-1 max-w-sm" name="default_country"><?php foreach (($countries ?? []) as $code): ?><option value="<?= $h($code) ?>"<?= ($st['default_country'] ?? '') === $code ? ' selected' : '' ?>><?= $h(Packs::label($code)) ?></option><?php endforeach; ?></select>
                <p class="mt-1 text-xs <?= $muted ?>">Vocabulaire du terminal et formats par défaut. Ex. États-Unis : système NCIC, réponse « NO RECORD », dates 03/14/1991 ; France : FPR, « NÉANT », 14/03/1991. Le pays de chaque sujet règle ensuite l’affichage de sa fiche.</p>
            </div>
            <div class="grid gap-4" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr))">
                <div><label class="block text-sm font-bold text-slate-900 dark:text-white" for="fx-qr">Conservation du journal (jours)</label><input id="fx-qr" class="<?= $input ?> mt-1" type="number" min="0" max="3650" name="query_retention_days" value="<?= (int) ($st['query_retention_days'] ?? 0) ?>"><p class="mt-1 text-xs <?= $muted ?>">0 : garder tout. Ex. 365 efface les consultations de plus d’un an à l’enregistrement des réglages.</p></div>
                <div><label class="block text-sm font-bold text-slate-900 dark:text-white" for="fx-er">Conservation des extractions (jours)</label><input id="fx-er" class="<?= $input ?> mt-1" type="number" min="0" max="3650" name="extraction_retention_days" value="<?= (int) ($st['extraction_retention_days'] ?? 0) ?>"><p class="mt-1 text-xs <?= $muted ?>">0 : garder tout. Ex. 90 pour ne garder que la campagne en cours.</p></div>
            </div>
            <?php foreach ([
                'extractions_enabled' => ['Synchronisation des téléphones du jeu', 'Coupée : le site refuse les extractions envoyées par les téléphones (le joueur le voit dans son téléphone).'],
                'member_photo_upload' => ['Les membres peuvent envoyer des photos', 'Ex. une capture prise en jeu, rangée dans la photothèque puis rattachée à une fiche. Aucune reconnaissance faciale.'],
                'xref_enabled' => ['Recoupements automatiques', 'Les comptes rendus, fiches, contrôles et extractions sont indexés pour proposer « ce nom est cité dans 2 CR ».'],
                'purpose_required' => ['Motif obligatoire au terminal', 'Comme sur un vrai terminal : chaque requête porte un code motif inscrit au journal. Ex. « CS » contrôle routier.'],
            ] as $k => [$label, $help]): ?>
                <div class="flex items-start gap-3">
                    <input type="hidden" name="<?= $h($k) ?>" value="0">
                    <input id="fx-<?= $h($k) ?>" type="checkbox" class="mt-1 h-4 w-4" name="<?= $h($k) ?>" value="1"<?= !empty($st[$k]) ? ' checked' : '' ?>>
                    <label for="fx-<?= $h($k) ?>"><span class="block text-sm font-bold text-slate-900 dark:text-white"><?= $h($label) ?></span><span class="block text-xs <?= $muted ?>"><?= $h($help) ?></span></label>
                </div>
            <?php endforeach; ?>
            <div>
                <label class="block text-sm font-bold text-slate-900 dark:text-white" for="fx-ev">Qui voit les extractions de téléphones</label>
                <select id="fx-ev" class="<?= $input ?> mt-1 max-w-sm" name="extractions_visibility"><option value="portal"<?= ($st['extractions_visibility'] ?? '') === 'portal' ? ' selected' : '' ?>>Tous les membres du Bureau SSE</option><option value="managers"<?= ($st['extractions_visibility'] ?? '') === 'managers' ? ' selected' : '' ?>>Après validation au back-office</option></select>
                <p class="mt-1 text-xs <?= $muted ?>">« Après validation » : une nouvelle extraction reste « à valider » ici avant d’être lue par les membres.</p>
            </div>
            <div><button class="<?= $pri ?>" type="submit">Enregistrer les réglages</button></div>
        </form>
    <?php endif; ?>
</div>
