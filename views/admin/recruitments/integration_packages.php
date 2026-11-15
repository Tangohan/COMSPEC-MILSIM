<?php
declare(strict_types=1);

/**
 * Paquets d'intégration : ce que reçoit un nouveau membre quand le recrutement l'intègre.
 *
 * @var bool $packagesReady
 * @var list<array<string,mixed>> $packages
 * @var array<string,mixed>|null $editing
 * @var bool $creating
 * @var list<array<string,mixed>> $packageItems
 * @var list<array<string,mixed>> $accessRoles
 * @var list<array{label:string,grades:list<array<string,mixed>>}> $gradeGroups
 * @var list<array{label:string,hint:string,items:list<array{value:string,label:string,detail:string,checked:bool}>}> $jobRoleGroups
 * @var list<array{id:int,label:string,depth:int}> $units
 * @var list<array<string,mixed>> $openings
 * @var array<int,string> $qualificationOptions
 * @var array<int,string> $courseOptions
 */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$pk = is_array($editing ?? null) ? $editing : [];
$pkId = (int) ($pk['id'] ?? 0);
$base = url('back-office/recruitments/paquets-integration');
$selQ = [];
$selC = [];
foreach (($packageItems ?? []) as $it) {
    if ($it['item_type'] === 'qualification') {
        $selQ[(int) $it['ref_id']] = $it;
    } else {
        $selC[(int) $it['ref_id']] = $it;
    }
}
$packages = is_array($packages ?? null) ? $packages : [];
$unitLabel = static function (int $id) use ($units): string {
    foreach ($units as $u) {
        if ((int) $u['id'] === $id) {
            return (string) $u['label'];
        }
    }

    return '';
};
$field = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/25';
$label = 'mb-1.5 block text-sm font-semibold text-slate-800';
$hint = 'mt-1.5 text-xs leading-relaxed text-slate-500';
$card = 'rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6';
$btn = 'inline-flex items-center justify-center rounded-xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700';
$btnGhost = 'inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-800 transition hover:bg-slate-50';
?>
<div class="mx-auto w-full max-w-4xl space-y-6" data-integration-packages>
    <nav class="overflow-hidden rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm sm:px-5" aria-label="Fil d’Ariane">
        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-slate-600">
            <a href="<?= $h(url('back-office/recruitments')) ?>" class="rounded-lg px-2 py-1.5 text-slate-700 transition hover:bg-slate-50">Dossiers de candidature</a>
            <span class="text-slate-300" aria-hidden="true">/</span>
            <span class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 font-bold text-slate-900">Paquets d’intégration</span>
        </div>
    </nav>

    <header class="<?= $card ?>">
        <h2 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Paquets d’intégration</h2>
        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600">
            Définissez ce que reçoit un nouveau membre quand vous l’intégrez : grade, unité, emploi, niveau d’accès, qualifications,
            formations à suivre et mot d’accueil. Le recruteur voit le paquet pendant l’intégration, peut l’ajuster, puis tout est appliqué d’un coup.
            Sans paquet, l’intégration se passe comme avant.
        </p>
    </header>

    <?php if (!$packagesReady): ?>
    <div class="rounded-2xl border border-amber-300 bg-amber-50 px-5 py-4 text-sm text-amber-900" role="alert">
        Cette fonction n’est pas encore disponible sur cet environnement : la mise à jour de la base doit d’abord être exécutée.
    </div>
    <?php elseif ($packages === [] && empty($creating)): ?>
    <section class="<?= $card ?> text-center" aria-labelledby="pk-empty">
        <h2 id="pk-empty" class="text-lg font-black text-slate-900">Définissez ce que reçoit un nouveau membre</h2>
        <p class="mx-auto mt-2 max-w-xl text-sm leading-relaxed text-slate-600">
            Aucun paquet n’est défini. Créez-en un pour que chaque nouvelle recrue reçoive d’un coup son grade, son affectation,
            ses qualifications d’entrée et ses premières formations, sans rien ressaisir à la main.
        </p>
        <a href="<?= $h($base . '?nouveau=1') ?>" class="<?= $btn ?> mt-5">Créer le premier paquet</a>
    </section>
    <?php else: ?>
    <section class="<?= $card ?>" aria-labelledby="pk-list">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="pk-list" class="text-lg font-black text-slate-900">Vos paquets</h2>
            <a href="<?= $h($base . '?nouveau=1') ?>" class="<?= $btnGhost ?>">Nouveau paquet</a>
        </div>
        <ul class="mt-4 divide-y divide-slate-100">
            <?php foreach ($packages as $p): ?>
            <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold text-slate-900"><?= $h((string) $p['name']) ?></p>
                    <p class="text-xs text-slate-500">
                        <?php
                        $when = [];
                        if ((int) $p['opening_id'] > 0) {
                            $when[] = 'pour une offre précise';
                        }
                        if ((int) $p['match_unit_id'] > 0) {
                            $when[] = 'unité ' . $unitLabel((int) $p['match_unit_id']);
                        }
                        if ((int) $p['is_default'] === 1) {
                            $when[] = 'par défaut';
                        }
                        if ((int) $p['is_active'] !== 1) {
                            $when[] = 'désactivé';
                        }
                        echo $h($when === [] ? 'Proposé à personne tant qu’il n’est ni par défaut ni lié à une offre ou une unité' : implode(' · ', $when));
                        ?>
                    </p>
                </div>
                <a href="<?= $h($base . '?paquet=' . (int) $p['id']) ?>" class="<?= $btnGhost ?>">Modifier</a>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <?php if ($packagesReady && !empty($creating)): ?>
    <form method="post" action="<?= $h($base) ?>" class="space-y-6" id="pk-form">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="id" value="<?= $pkId ?>">

        <section class="<?= $card ?> space-y-4" aria-labelledby="pk-general">
            <h2 id="pk-general" class="text-lg font-black text-slate-900"><?= $pkId > 0 ? 'Modifier le paquet' : 'Nouveau paquet' ?></h2>
            <div>
                <label class="<?= $label ?>" for="pk-name">Nom du paquet</label>
                <input id="pk-name" type="text" name="name" maxlength="160" required value="<?= $h((string) ($pk['name'] ?? '')) ?>" placeholder="Recrue, Pilote, Médic…" class="<?= $field ?>">
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="<?= $label ?>" for="pk-opening">Proposé pour l’offre</label>
                    <select id="pk-opening" name="opening_id" class="<?= $field ?>">
                        <option value="0">Toutes les offres</option>
                        <?php foreach ($openings as $o): ?>
                        <option value="<?= (int) $o['id'] ?>"<?= (int) ($pk['opening_id'] ?? 0) === (int) $o['id'] ? ' selected' : '' ?>><?= $h((string) ($o['title'] ?? 'Offre #' . (int) $o['id'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="<?= $hint ?>">Un candidat pilote reçoit le paquet pilote : liez le paquet à l’offre pilote.</p>
                </div>
                <div>
                    <label class="<?= $label ?>" for="pk-match-unit">Proposé pour l’unité</label>
                    <select id="pk-match-unit" name="match_unit_id" class="<?= $field ?>">
                        <option value="0">Toutes les unités</option>
                        <?php foreach ($units as $u): ?>
                        <option value="<?= (int) $u['id'] ?>"<?= (int) ($pk['match_unit_id'] ?? 0) === (int) $u['id'] ? ' selected' : '' ?>><?= str_repeat('&nbsp;&nbsp;', min(6, (int) $u['depth'])) ?><?= $h((string) $u['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="<?= $hint ?>">Sert quand l’offre n’a pas son propre paquet.</p>
                </div>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:gap-6">
                <label class="flex items-center gap-2 py-3 text-sm text-slate-800"><input type="checkbox" name="is_default" value="1"<?= !empty($pk['is_default']) ? ' checked' : '' ?>> Paquet par défaut (quand rien d’autre ne correspond)</label>
                <label class="flex items-center gap-2 py-3 text-sm text-slate-800"><input type="checkbox" name="is_active" value="1"<?= !isset($pk['is_active']) || !empty($pk['is_active']) ? ' checked' : '' ?>> Actif</label>
            </div>
        </section>

        <section class="<?= $card ?> space-y-4" aria-labelledby="pk-orbat">
            <h2 id="pk-orbat" class="text-lg font-black text-slate-900">Affectation</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="<?= $label ?>" for="pk-grade">Grade initial</label>
                    <select id="pk-grade" name="grade_id" class="<?= $field ?>">
                        <option value="0">Aucun</option>
                        <?php foreach ($gradeGroups as $group): ?>
                        <optgroup label="<?= $h((string) $group['label']) ?>">
                            <?php foreach ($group['grades'] as $g): ?>
                            <option value="<?= (int) ($g['id'] ?? 0) ?>"<?= (int) ($pk['grade_id'] ?? 0) === (int) ($g['id'] ?? 0) ? ' selected' : '' ?>><?= $h(\App\Support\GradeSelectOptions::label($g)) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="<?= $label ?>" for="pk-unit">Unité d’affectation</label>
                    <select id="pk-unit" name="unit_id" class="<?= $field ?>">
                        <option value="0">Choisie à l’intégration</option>
                        <?php foreach ($units as $u): ?>
                        <option value="<?= (int) $u['id'] ?>"<?= (int) ($pk['unit_id'] ?? 0) === (int) $u['id'] ? ' selected' : '' ?>><?= str_repeat('&nbsp;&nbsp;', min(6, (int) $u['depth'])) ?><?= $h((string) $u['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="<?= $label ?>" for="pk-job">Emploi</label>
                    <select id="pk-job" name="personnel_job_role_id" class="<?= $field ?>">
                        <option value="">Choisi à l’intégration</option>
                        <?php foreach ($jobRoleGroups as $group): ?>
                        <optgroup label="<?= $h((string) $group['label']) ?>">
                            <?php foreach ($group['items'] as $item): ?>
                            <option value="<?= $h((string) $item['value']) ?>"<?= (int) ($pk['personnel_job_role_id'] ?? 0) > 0 && (string) (int) $pk['personnel_job_role_id'] === (string) $item['value'] ? ' selected' : '' ?>><?= $h((string) $item['label']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <fieldset>
                <legend class="<?= $label ?>">Niveau d’accès</legend>
                <div class="grid gap-1 sm:grid-cols-2">
                    <?php foreach ($accessRoles as $r): ?>
                    <label class="flex items-center gap-2 py-3 text-sm text-slate-800"><input type="checkbox" name="role_ids[]" value="<?= (int) $r['id'] ?>"<?= in_array((int) $r['id'], (array) ($pk['role_ids'] ?? []), true) ? ' checked' : '' ?>> <?= $h((string) ($r['name'] ?? '')) ?></label>
                    <?php endforeach; ?>
                </div>
                <p class="<?= $hint ?>">Sans choix, le membre reçoit le niveau « Membre » comme aujourd’hui. Le recruteur ne peut appliquer un niveau que s’il a le droit de les attribuer.</p>
            </fieldset>
        </section>

        <section class="<?= $card ?> space-y-4" aria-labelledby="pk-quals">
            <h2 id="pk-quals" class="text-lg font-black text-slate-900">Qualifications d’office</h2>
            <?php if ($qualificationOptions === []): ?>
            <p class="text-sm text-slate-600">Aucune qualification dans votre référentiel. <a class="font-semibold text-indigo-700 underline" href="<?= $h(url('back-office/referentiels/qualifications')) ?>">Créer ou importer des qualifications</a>.</p>
            <?php else: ?>
            <ul class="space-y-2">
                <?php foreach ($qualificationOptions as $qid => $qname): $cur = $selQ[(int) $qid] ?? null; ?>
                <li class="flex flex-col gap-2 rounded-xl border border-slate-200 p-3 sm:flex-row sm:items-center sm:justify-between">
                    <label class="flex items-center gap-2 py-3 text-sm font-semibold text-slate-900"><input type="checkbox" name="qualification_ids[]" value="<?= (int) $qid ?>"<?= $cur ? ' checked' : '' ?>> <?= $h($qname) ?></label>
                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-600">
                        <select name="q_status[<?= (int) $qid ?>]" class="rounded-lg border border-slate-300 bg-white px-2 py-2 text-xs" aria-label="Statut de <?= $h($qname) ?>">
                            <option value="obtained"<?= !$cur || $cur['status'] === 'obtained' ? ' selected' : '' ?>>Délivrée</option>
                            <option value="in_training"<?= $cur && $cur['status'] === 'in_training' ? ' selected' : '' ?>>En formation</option>
                        </select>
                        <label class="flex items-center gap-1">Validité
                            <input type="number" min="0" max="600" name="q_months[<?= (int) $qid ?>]" value="<?= $cur && (int) $cur['valid_months'] > 0 ? (int) $cur['valid_months'] : '' ?>" placeholder="par défaut" class="w-24 rounded-lg border border-slate-300 bg-white px-2 py-2 text-xs"> mois
                        </label>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <p class="<?= $hint ?>">Délivrées avec les règles de chaque qualification (prérequis, délivrance à la main). Sans durée, la validité de la qualification s’applique.</p>
            <?php endif; ?>
        </section>

        <section class="<?= $card ?> space-y-4" aria-labelledby="pk-courses">
            <h2 id="pk-courses" class="text-lg font-black text-slate-900">Formations à suivre</h2>
            <?php if ($courseOptions === []): ?>
            <p class="text-sm text-slate-600">Aucune formation dans votre Academy. <a class="font-semibold text-indigo-700 underline" href="<?= $h(url('academy')) ?>">Ouvrir l’Academy</a>.</p>
            <?php else: ?>
            <div class="grid gap-1 sm:grid-cols-2">
                <?php foreach ($courseOptions as $cid => $cname): ?>
                <label class="flex items-center gap-2 py-3 text-sm text-slate-800"><input type="checkbox" name="course_ids[]" value="<?= (int) $cid ?>"<?= isset($selC[(int) $cid]) ? ' checked' : '' ?>> <?= $h($cname) ?></label>
                <?php endforeach; ?>
            </div>
            <p class="<?= $hint ?>">Le membre est inscrit d’office ; il reçoit un seul récapitulatif au lieu d’un e-mail par formation.</p>
            <?php endif; ?>
        </section>

        <section class="<?= $card ?> space-y-3" aria-labelledby="pk-welcome">
            <h2 id="pk-welcome" class="text-lg font-black text-slate-900">Consignes de bienvenue</h2>
            <label class="sr-only" for="pk-welcome-text">Consignes de bienvenue</label>
            <textarea id="pk-welcome-text" name="welcome_message" rows="5" maxlength="4000" class="<?= $field ?>" placeholder="Premiers pas, canal Discord à rejoindre, document à lire, personne à contacter…"><?= $h((string) ($pk['welcome_message'] ?? '')) ?></textarea>
            <p class="<?= $hint ?>">Ce texte figure dans le récapitulatif de bienvenue envoyé au nouveau membre.</p>
        </section>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="<?= $btn ?>">Enregistrer le paquet</button>
            <a href="<?= $h($base) ?>" class="<?= $btnGhost ?>">Annuler</a>
        </div>
    </form>
    <?php if ($pkId > 0): ?>
    <form method="post" action="<?= $h($base . '/' . $pkId . '/supprimer') ?>" data-ui-confirm="1" data-ui-confirm-title="Supprimer ce paquet ?" data-ui-confirm-body="Les membres déjà intégrés gardent ce qu’ils ont reçu."">
        <?= \App\Core\Csrf::field() ?>
        <button type="submit" class="inline-flex items-center rounded-xl border border-rose-300 bg-white px-5 py-3 text-sm font-semibold text-rose-700 hover:bg-rose-50">Supprimer ce paquet</button>
    </form>
    <?php endif; ?>
    <?php endif; ?>
</div>
