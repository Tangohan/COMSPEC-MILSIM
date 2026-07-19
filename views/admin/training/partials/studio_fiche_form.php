<?php
/**
 * Formulaire fiche formation (Studio) — données & inscription.
 * Variables attendues depuis studio_edit.php.
 */
$curVis = (string) ($course['visibility'] ?? 'draft');
$langCode = strtolower(trim((string) ($course['language_code'] ?? 'fr')));
if ($langCode === '') {
    $langCode = 'fr';
}
$validityDaysField = $course['validity_days'] ?? null;
?>
<form method="post" action="<?= training_studio_url($cid) ?>" class="space-y-0 mb-8" id="studio-fiche-form">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="_action" value="save_course">
    <input type="hidden" name="_studio_section" value="fiche">

    <section id="studio-fiche" class="ts-fiche-block scroll-mt-28">
        <div class="ts-fiche-block__head">
            <div>
                <h2>Identité</h2>
                <p>Titre, adresse publique et repères affichés dans le catalogue.</p>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="ts-field md:col-span-2">
                <label for="fiche-title">Titre</label>
                <input id="fiche-title" type="text" name="title" required value="<?= htmlspecialchars((string) ($course['title'] ?? '')) ?>">
            </div>
            <div class="ts-field">
                <label for="fiche-slug">Adresse courte dans le lien du parcours</label>
                <input id="fiche-slug" type="text" name="slug" required value="<?= htmlspecialchars($slug) ?>" title="Segment utilisé dans l’adresse web du parcours">
                <p class="ts-field__hint">Ex. : « premiers-secours » — apparaît dans le lien partagé avec les apprenants.</p>
            </div>
            <div class="ts-field">
                <label for="fiche-course-code">Code d’affichage</label>
                <input id="fiche-course-code" type="text" name="course_code" maxlength="32" value="<?= htmlspecialchars((string) ($course['course_code'] ?? '')) ?>" placeholder="Ex. A-03 (optionnel)">
            </div>
            <div class="ts-field">
                <label for="fiche-category">Catégorie</label>
                <input id="fiche-category" type="text" name="category" value="<?= htmlspecialchars((string) ($course['category'] ?? '')) ?>" placeholder="Optionnel">
            </div>
            <div class="ts-field">
                <label for="fiche-language">Langue</label>
                <select id="fiche-language" name="language_code">
                    <option value="fr" <?= $langCode === 'fr' ? 'selected' : '' ?>>Français</option>
                    <option value="en" <?= $langCode === 'en' ? 'selected' : '' ?>>English</option>
                    <?php if (!in_array($langCode, ['fr', 'en'], true)): ?>
                    <option value="<?= htmlspecialchars($langCode) ?>" selected><?= htmlspecialchars(strtoupper($langCode)) ?></option>
                    <?php endif; ?>
                </select>
            </div>
        </div>
    </section>

    <section class="ts-fiche-block">
        <div class="ts-fiche-block__head">
            <div>
                <h2>Publication</h2>
                <p>Qui peut voir le parcours et dans quel périmètre du catalogue.</p>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="ts-field">
                <label for="fiche-visibility">Visibilité</label>
                <select id="fiche-visibility" name="visibility">
                    <?php foreach ($visibilityOptions as $v):
                        $pubLocked = ($v === 'published' && !$canPublish && $curVis !== 'published');
                    ?>
                    <option value="<?= htmlspecialchars($v) ?>" <?= ($curVis === $v) ? 'selected' : '' ?> <?= $pubLocked ? 'disabled' : '' ?>><?= htmlspecialchars($visLabels[$v] ?? $v) ?><?= $pubLocked ? ' (permission requise)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="ts-field__hint">Les parcours publiés apparaissent dans le catalogue apprenant. Les brouillons restent réservés au Studio.</p>
            </div>
            <div class="ts-field md:col-span-2">
                <span>Portée du catalogue</span>
                <?php if ($studioCanSetPlatformScope): ?>
                <select name="lms_scope">
                    <option value="tenant" <?= $curLmsScope === 'tenant' ? 'selected' : '' ?>>Parcours de la communauté — proposé aux membres de cette organisation</option>
                    <option value="platform" <?= $curLmsScope === 'platform' ? 'selected' : '' ?>>Proposé sur toute la plateforme — visible par toutes les organisations éligibles</option>
                </select>
                <p class="ts-field__hint">Les parcours proposés sur toute la plateforme partagent les mêmes adresses courtes : choisissez un segment unique à l’échelle du site.</p>
                <?php else: ?>
                <p class="text-sm text-slate-800 font-medium m-0"><?= htmlspecialchars(function_exists('training_lms_course_scope_label_fr') ? training_lms_course_scope_label_fr($curLmsScope) : '') ?></p>
                <?php if ($curLmsScope === 'platform'): ?>
                <p class="ts-field__hint">Seuls les administrateurs de la plateforme peuvent modifier cette portée.</p>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php if (!empty($pedagogyColumnsReady)): ?>
            <div class="md:col-span-2 rounded-xl border border-emerald-100 bg-emerald-50/60 p-4 space-y-3">
                <p class="text-xs font-bold text-emerald-900 uppercase tracking-wide m-0">Responsabilités pédagogiques</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="ts-field">
                        <label for="fiche-ped-owner">Responsable pédagogique</label>
                        <select id="fiche-ped-owner" name="pedagogical_owner_user_id">
                            <option value="">— Non renseigné —</option>
                            <?php foreach ($studioStaffPickUsers ?? [] as $su):
                                $sid = (int) ($su['id'] ?? 0);
                                $slab = trim((string) ($su['display_name'] ?? '')) !== '' ? (string) $su['display_name'] : ('#' . $sid);
                                $curOwner = (int) ($course['pedagogical_owner_user_id'] ?? 0);
                            ?>
                            <option value="<?= $sid ?>" <?= $curOwner === $sid ? 'selected' : '' ?>><?= htmlspecialchars($slab) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="ts-field__hint">Obligatoire pour une mise en ligne publique.</p>
                    </div>
                    <div class="ts-field">
                        <label for="fiche-final-validator">Validateur final (optionnel)</label>
                        <select id="fiche-final-validator" name="final_validator_user_id">
                            <option value="">— Aucun —</option>
                            <?php foreach ($studioStaffPickUsers ?? [] as $su):
                                $sid = (int) ($su['id'] ?? 0);
                                $slab = trim((string) ($su['display_name'] ?? '')) !== '' ? (string) $su['display_name'] : ('#' . $sid);
                                $curVal = (int) ($course['final_validator_user_id'] ?? 0);
                            ?>
                            <option value="<?= $sid ?>" <?= $curVal === $sid ? 'selected' : '' ?>><?= htmlspecialchars($slab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!$canPublish): ?>
            <div class="md:col-span-2 rounded-xl border border-amber-200 bg-amber-50/70 p-4 space-y-3">
                <p class="text-xs font-bold text-amber-950 uppercase tracking-wide m-0">Publication — élévation de droits</p>
                <p class="text-sm text-amber-950/90 m-0">Vous ne pouvez pas mettre cette formation en « Publié ». Ce droit est réservé aux administrateurs de la communauté et aux personnes autorisées à publier les formations. Le responsable pédagogique ou le validateur final n’accordent ce droit que s’ils disposent eux-mêmes de cette habilitation.</p>
                <?php if ($publishElevationRecipients !== []): ?>
                <div>
                    <p class="text-xs font-semibold text-amber-950 m-0 mb-1">Personnes qui peuvent publier ou vous attribuer ce droit :</p>
                    <ul class="m-0 pl-5 text-sm text-amber-950/90 space-y-0.5">
                        <?php foreach ($publishElevationRecipients as $elevRec): ?>
                        <li><?= htmlspecialchars((string) ($elevRec['name'] ?? 'Membre')) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php if ($publishElevationCooldownSec !== null): ?>
                <p class="text-sm text-amber-900 m-0">Une demande est déjà en cours pour cette fiche. Vous pourrez renvoyer un rappel dans environ <?= max(1, (int) ceil((int) $publishElevationCooldownSec / 3600)) ?> heure<?= ((int) ceil((int) $publishElevationCooldownSec / 3600)) > 1 ? 's' : '' ?>.</p>
                <?php else: ?>
                <?php
                    $elevNames = array_map(static fn ($r) => (string) ($r['name'] ?? ''), $publishElevationRecipients);
                    $elevConfirm = 'Envoyer une demande à ' . implode(', ', $elevNames)
                        . ' ? Elles recevront une alerte et un e-mail avec un lien vers cette fiche.';
                ?>
                <form method="post" action="<?= training_studio_url($cid) ?>" class="inline-flex flex-wrap items-center gap-3"
                      onsubmit="return confirm(<?= htmlspecialchars(json_encode($elevConfirm, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>);">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="_action" value="request_publish_elevation">
                    <button type="submit" class="px-4 py-2.5 bg-amber-700 text-white text-xs font-black uppercase tracking-wide rounded-xl hover:bg-amber-800 shadow-sm">Demander une élévation de droits</button>
                </form>
                <?php endif; ?>
                <?php else: ?>
                <p class="text-sm text-amber-900 m-0">Aucune personne habilitée à publier n’est joignable dans cette communauté. Contactez un administrateur autrement.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="ts-fiche-block">
        <div class="ts-fiche-block__head">
            <div>
                <h2>Paramètres pédagogiques</h2>
                <p>Niveau, durée, réussite et options de certification.</p>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="ts-field">
                <label for="fiche-level">Niveau</label>
                <select id="fiche-level" name="level">
                    <?php foreach ($levelOptions as $lv): ?>
                    <option value="<?= htmlspecialchars($lv) ?>" <?= (($course['level'] ?? 'initiation') === $lv) ? 'selected' : '' ?>><?= htmlspecialchars($levelLabels[$lv] ?? $lv) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ts-field">
                <label for="fiche-minutes">Durée estimée (minutes)</label>
                <input id="fiche-minutes" type="number" name="estimated_minutes" min="0" step="1" value="<?= (int) ($course['estimated_minutes'] ?? 0) ?>">
            </div>
            <div class="ts-field">
                <label for="fiche-passing">Score de réussite (%)</label>
                <input id="fiche-passing" type="number" name="passing_score" min="0" max="100" step="0.01" value="<?= htmlspecialchars((string) ($course['passing_score'] ?? '80')) ?>">
            </div>
            <div class="ts-field">
                <label for="fiche-validity">Validité (jours)</label>
                <input id="fiche-validity" type="number" name="validity_days" min="0" step="1" value="<?= $validityDaysField !== null && $validityDaysField !== '' ? (int) $validityDaysField : '' ?>" placeholder="Vide = illimité">
                <p class="ts-field__hint">Laissez vide pour une validité sans limite de durée.</p>
            </div>
            <div class="md:col-span-2">
                <div class="ts-check-row">
                    <label class="ts-check">
                        <input type="checkbox" name="is_mandatory" value="1" <?= !empty($course['is_mandatory']) ? 'checked' : '' ?>>
                        <span>Formation obligatoire</span>
                    </label>
                    <label class="ts-check">
                        <input type="checkbox" name="is_certifying" value="1" <?= !empty($course['is_certifying']) ? 'checked' : '' ?>>
                        <span>Formation certifiante</span>
                    </label>
                </div>
            </div>
        </div>
    </section>

    <section class="ts-fiche-block">
        <div class="ts-fiche-block__head">
            <div>
                <h2>Textes &amp; objectifs</h2>
                <p>Accroche catalogue, description et objectifs pédagogiques.</p>
            </div>
        </div>
        <div class="grid grid-cols-1 gap-4">
            <div class="ts-field">
                <label for="fiche-short-desc">Accroche courte</label>
                <input id="fiche-short-desc" type="text" name="short_description" maxlength="500" value="<?= htmlspecialchars((string) ($course['short_description'] ?? '')) ?>">
            </div>
            <div class="ts-field">
                <label for="fiche-description">Description</label>
                <textarea id="fiche-description" name="description" rows="5"><?= htmlspecialchars((string) ($course['description'] ?? '')) ?></textarea>
            </div>
            <div class="ts-fiche-callout">
                <p class="m-0"><strong>Apparence &amp; médias de couverture</strong> — thème couleur, typographie, miniature, bannière et consignes audio se règlent dans l’onglet <a href="<?= htmlspecialchars($studioU('presentation')) ?>">Présentation</a>.</p>
            </div>
            <div data-lms-objectives-scope>
                <div class="ts-field">
                    <span>Objectifs pédagogiques</span>
                </div>
                <div class="space-y-2 mt-2" data-lms-objectives-list>
                    <?php foreach ($courseObjectiveLines as $objLine): ?>
                    <div class="flex gap-2 items-center" data-lms-objective-row>
                        <input type="text" name="course_learning_objectives[]" value="<?= htmlspecialchars($objLine) ?>" class="flex-1 min-w-0 border border-slate-200 rounded-xl px-3 py-2 text-sm" placeholder="Ex. Savoir appliquer la consigne de sécurité">
                        <button type="button" class="shrink-0 px-2 py-1.5 text-xs font-bold text-rose-600 hover:bg-rose-50 rounded-lg border border-transparent hover:border-rose-100" data-lms-objective-remove title="Retirer cette ligne">Retirer</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="mt-2 px-3 py-1.5 text-xs font-black uppercase tracking-wide text-emerald-800 border border-dashed border-emerald-300 rounded-lg hover:bg-emerald-50" data-lms-objective-add>+ Ajouter un objectif</button>
            </div>
            <details class="rounded-xl border border-sky-200 bg-sky-50/90 p-4 text-sm text-sky-950 shadow-sm">
                <summary class="cursor-pointer font-bold text-sky-950">Aide : pièces jointes et liens par leçon</summary>
                <p class="mt-2 text-xs leading-relaxed text-sky-900/90 m-0">Pour ajouter des <strong class="font-semibold">liens web</strong>, des <strong class="font-semibold">fichiers</strong> ou des <strong class="font-semibold">documents du centre documentaire</strong> visibles sous chaque leçon, ouvrez l’onglet <a href="<?= htmlspecialchars($studioU('structure')) ?>#studio-ressources-aide" class="font-bold text-sky-900 underline decoration-sky-400 hover:decoration-sky-700">Modules</a>.</p>
            </details>
        </div>
    </section>

    <section id="studio-engagement" class="ts-fiche-block scroll-mt-28">
        <div class="ts-fiche-block__head">
            <div>
                <h2>Inscription &amp; consignes</h2>
                <p>Définissez qui peut s’inscrire librement depuis le catalogue, sous quelles conditions, et comment les formateurs sont prévenus. Les inscriptions manuelles par le staff restent toujours possibles, même si l’inscription libre est fermée.</p>
            </div>
        </div>
        <div class="space-y-5">
            <div>
                <p class="text-xs font-bold text-slate-600 mb-2 m-0">Accès à l’inscription</p>
                <div class="ts-check-row">
                    <label class="ts-check">
                        <input type="checkbox" name="policy_enrollments_blocked" value="1" <?= !empty($policy['enrollments_blocked']) ? 'checked' : '' ?>>
                        <span>Bloquer toutes les nouvelles inscriptions</span>
                    </label>
                    <label class="ts-check">
                        <input type="checkbox" name="policy_self_enroll_disabled" value="1" <?= isset($policy['self_enroll_allowed']) && $policy['self_enroll_allowed'] === false ? 'checked' : '' ?>>
                        <span>Désactiver l’inscription libre</span>
                    </label>
                </div>
                <p class="ts-field__hint mt-2 mb-0">« Bloquer toutes les nouvelles inscriptions » ferme complètement l’entrée (libre ou nouvelle demande) : utile pour une session pleine ou une formation archivée côté catalogue. « Désactiver l’inscription libre » laisse le parcours visible, mais l’apprenant doit être inscrit par un formateur ou un gestionnaire. Si aucune case n’est cochée, l’inscription libre reste ouverte dans le respect des règles ci-dessous.</p>
            </div>

            <div>
                <input type="hidden" name="policy_self_enroll_requires_approval" value="0">
                <label class="ts-check">
                    <input type="checkbox" name="policy_self_enroll_requires_approval" value="1" <?= !empty($policy['self_enroll_requires_approval']) ? 'checked' : '' ?>>
                    <span>Exiger une validation par un formateur après chaque inscription libre</span>
                </label>
                <p class="ts-field__hint mt-2 mb-0">Quand cette option est active, la demande reste en attente jusqu’à acceptation ou refus. Sans effet si l’inscription libre est désactivée ou si toutes les inscriptions sont bloquées. Les personnes choisies ci-dessous (et l’auteur de la fiche) reçoivent une alerte par e-mail à chaque demande.</p>
            </div>

            <div>
                <input type="hidden" name="policy_comments_enabled" value="0">
                <label class="ts-check">
                    <input type="checkbox" name="policy_comments_enabled" value="1" <?= function_exists('training_lms_policy_comments_enabled') ? (training_lms_policy_comments_enabled($policy) ? 'checked' : '') : 'checked' ?>>
                    <span>Autoriser les commentaires sur la page « Avis &amp; échanges »</span>
                </label>
                <p class="ts-field__hint mt-2 mb-0">Cochez pour laisser les inscrits publier un avis ou un commentaire sur la page d’échanges du parcours. Décochez pour ne garder que la consultation, sans nouveau message public.</p>
            </div>

            <div class="ts-field">
                <label for="fiche-approvers">Formateurs notifiés pour valider les inscriptions</label>
                <select id="fiche-approvers" name="policy_enrollment_approver_user_ids[]" multiple size="6">
                    <?php foreach ($studioStaffPickUsers as $su):
                        $suid = (int) ($su['id'] ?? 0);
                        if ($suid < 1) {
                            continue;
                        }
                        $slab = trim((string) ($su['display_name'] ?? ''));
                        if ($slab === '') {
                            $slab = (string) ($su['email'] ?? ('#' . $suid));
                        }
                    ?>
                    <option value="<?= $suid ?>" <?= in_array($suid, $policyApproverIds, true) ? 'selected' : '' ?>><?= htmlspecialchars($slab) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="ts-field__hint">Choisissez qui reçoit l’alerte lorsqu’une inscription libre attend une validation. Si la liste reste vide, seul l’auteur de la fiche est prévenu (en plus des personnes qui ont déjà le droit d’assigner des apprenants). Vous pouvez en sélectionner plusieurs.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="ts-field">
                    <label for="fiche-prereq">Prérequis (formations validées avant)</label>
                    <select id="fiche-prereq" name="policy_prerequisite_course_ids[]" multiple size="6">
                        <?php foreach ($studioOtherCourses as $oc):
                            $oid = (int) ($oc['id'] ?? 0);
                            $ocVis = (string) ($oc['visibility'] ?? '');
                            $ocVisLab = $visLabels[$ocVis] ?? $ocVis;
                        ?>
                        <option value="<?= $oid ?>" <?= in_array($oid, $policyPrereq, true) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($oc['title'] ?? '')) ?><?= $ocVisLab !== '' ? ' (' . htmlspecialchars($ocVisLab) . ')' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="ts-field__hint">Listez les parcours que l’apprenant doit avoir terminés avec succès avant de s’inscrire librement ici. Liste vide = aucun prérequis de formation. Plusieurs choix possibles ; chacun est vérifié au moment de l’inscription.</p>
                </div>
                <div class="ts-field">
                    <label for="fiche-certs">Attestations requises (autres formations)</label>
                    <select id="fiche-certs" name="policy_certificate_course_ids[]" multiple size="6">
                        <?php foreach ($studioOtherCourses as $oc):
                            $oid = (int) ($oc['id'] ?? 0);
                        ?>
                        <option value="<?= $oid ?>" <?= in_array($oid, $policyCerts, true) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($oc['title'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="ts-field__hint">Exigez qu’une autre formation ait déjà été validée (attestation ou réussite enregistrée) avant l’inscription libre. Liste vide = aucune attestation demandée. Utile pour enchaîner un module avancé après un socle commun.</p>
                </div>
                <div class="ts-field" id="fiche-roles-field">
                    <span>Rôles autorisés</span>
                    <?php $policyRolesAll = $policyRoles === []; ?>
                    <div class="ts-check-row flex-col items-stretch gap-2 mb-2">
                        <label class="ts-check">
                            <input type="radio" name="policy_role_audience" value="all" <?= $policyRolesAll ? 'checked' : '' ?> data-fiche-roles-audience>
                            <span>Tout le monde de la communauté</span>
                        </label>
                        <label class="ts-check">
                            <input type="radio" name="policy_role_audience" value="roles" <?= !$policyRolesAll ? 'checked' : '' ?> data-fiche-roles-audience>
                            <span>Uniquement certains rôles (au moins un)</span>
                        </label>
                    </div>
                    <label for="fiche-roles" class="sr-only">Liste des rôles autorisés</label>
                    <select id="fiche-roles" name="policy_required_role_ids[]" multiple size="6">
                        <?php foreach ($studioRoles as $r):
                            $rid = (int) ($r['id'] ?? 0);
                        ?>
                        <option value="<?= $rid ?>" <?= in_array($rid, $policyRoles, true) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($r['name'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="ts-field__hint">« Tout le monde de la communauté » n’applique aucun filtre sur le rôle : tout membre éligible par ailleurs peut s’inscrire librement. Si vous choisissez « Uniquement certains rôles », sélectionnez au moins un rôle dans la liste : l’apprenant doit en posséder au moins un. Sans rôle sélectionné dans ce mode, aucune contrainte de rôle n’est enregistrée.</p>
                    <script>
                    (function () {
                        var root = document.getElementById('fiche-roles-field');
                        if (!root) return;
                        var select = root.querySelector('#fiche-roles');
                        var radios = root.querySelectorAll('[data-fiche-roles-audience]');
                        if (!select || !radios.length) return;
                        function sync() {
                            var all = false;
                            radios.forEach(function (r) {
                                if (r.checked && r.value === 'all') all = true;
                            });
                            select.disabled = all;
                            select.setAttribute('aria-disabled', all ? 'true' : 'false');
                        }
                        radios.forEach(function (r) { r.addEventListener('change', sync); });
                        sync();
                    })();
                    </script>
                </div>
                <div class="ts-field">
                    <label for="fiche-grades">Grades autorisés</label>
                    <select id="fiche-grades" name="policy_required_grade_ids[]" multiple size="6">
                        <?php foreach ($studioGrades as $g):
                            $gid = (int) ($g['id'] ?? 0);
                        ?>
                        <option value="<?= $gid ?>" <?= in_array($gid, $policyGrades, true) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($g['label_short'] ?? $g['code'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="ts-field__hint">Restreignez l’inscription libre aux grades sélectionnés. Liste vide = tous les grades sont acceptés. Si des grades sont choisis, le grade du compte doit figurer parmi eux.</p>
                </div>
            </div>

            <div>
                <p class="text-xs font-bold text-slate-600 mb-2 m-0">Statuts de compte autorisés pour l’inscription libre</p>
                <p class="ts-field__hint mb-2">Filtrez selon l’état du compte membre (par exemple compte déjà actif). Laissez toutes les cases décochées pour n’imposer aucune contrainte de statut. Si au moins une case est cochée, l’apprenant doit correspondre à <strong>au moins une</strong> d’entre elles pour s’inscrire librement.</p>
                <div class="ts-check-row">
                    <?php foreach ($policyUserStatusLabels as $stVal => $stLabel): ?>
                    <label class="ts-check">
                        <input type="checkbox" name="policy_user_status[]" value="<?= htmlspecialchars($stVal) ?>" <?= in_array($stVal, $policyStatusesSelected, true) ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($stLabel) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="border-t border-slate-100 pt-5" id="studio-engagement-share">
                <h3 class="text-xs font-black uppercase tracking-[0.14em] text-slate-500 mb-2">Code de repérage / page apprenant</h3>
                <p class="ts-field__hint mb-3 max-w-3xl">Ce code court permet aux membres connectés à <strong>cette</strong> communauté d’ouvrir directement la fiche depuis la page de saisie dédiée (pratique pour un affichage en salle, un message d’annonce ou un document papier). Enregistrez la fiche une première fois pour générer le code s’il n’apparaît pas encore. Si quelqu’un saisit un code d’une autre communauté, le portail l’indique clairement sans mélanger les espaces. Pour invalider un code déjà diffusé, utilisez « Régénérer le code » plus bas.</p>
                <div class="ts-share-panel">
                    <div class="ts-field">
                        <label for="fiche-share-code">Code actuel</label>
                        <input id="fiche-share-code" type="text" readonly class="ts-share-code" value="<?= $shareCodeDisplay !== '' ? htmlspecialchars($shareCodeDisplay) : '— (enregistrez la fiche pour en générer un)' ?>">
                        <p class="ts-field__hint">Affichage seul : le code se crée à l’enregistrement et se renouvelle uniquement via le bouton de régénération.</p>
                    </div>
                    <p class="text-xs text-slate-600 pb-2 m-0">Page apprenant : <a href="<?= url('formations/code-acces') ?>" class="font-semibold text-emerald-800 underline decoration-emerald-200 hover:text-emerald-950" target="_blank" rel="noopener">Ouvrir la saisie du code</a> — à partager avec les membres qui doivent retrouver ce parcours rapidement.</p>
                </div>
            </div>
        </div>
    </section>

    <div class="ts-fiche-savebar">
        <p>Enregistrez pour appliquer titre, publication et règles d’inscription.</p>
        <button type="submit">Enregistrer la fiche</button>
    </div>
</form>

<div class="ts-fiche-block mb-10 border-dashed">
    <div class="ts-fiche-block__head">
        <div>
            <h2>Régénérer le code de repérage</h2>
            <p>Utilisez cette action seulement si l’ancien code a circulé trop largement. Un nouveau code remplace immédiatement l’ancien : les membres devront utiliser le nouveau pour ouvrir la fiche depuis la page de saisie.</p>
        </div>
    </div>
    <form method="post" action="<?= training_studio_url($cid) ?>" class="inline-flex">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="_action" value="regenerate_enrollment_share_code">
        <button type="submit" class="px-4 py-2.5 bg-amber-600 text-white text-xs font-black uppercase tracking-wide rounded-xl hover:bg-amber-700 shadow-sm">Régénérer le code</button>
    </form>
</div>
