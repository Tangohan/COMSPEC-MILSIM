<?php
$categories = $categories ?? [];
$trainings = $trainings ?? [];
$equipmentClasses = $equipmentClasses ?? [];
$units = $units ?? [];
$users = $users ?? [];
$tenantRoles = $tenantRoles ?? [];
$permissionAccessLevels = $permissionAccessLevels ?? ['read', 'comment', 'edit', 'approve', 'manage'];
$documentTypes = [
    'manuel' => 'Manuel',
    'procedure' => 'Procédure',
    'note' => 'Note',
    'compte_rendu' => 'Compte rendu',
    'directive' => 'Directive',
    'annexe' => 'Annexe',
    'support_formation' => 'Support de formation',
    'fiche_equipement' => 'Fiche équipement',
    'document_operationnel' => 'Document opérationnel',
    'piece_jointe' => 'Pièce jointe',
];
$classificationLevels = [
    'public' => 'Public',
    'interne' => 'Interne service',
    'restreint' => 'Diffusion restreinte',
    'sensible' => 'Donnée sensible',
    'confidentiel' => 'Confidentiel opérationnel',
    'operationnel' => 'Accès commandement',
];
/** Ordre : du plus courant au plus expert (évite « publication contrôlée » en premier regard). */
$visibilityScopes = [
    'private' => 'Privé — vous & les collaborateurs du document',
    'organization' => 'Toute la communauté (selon classification)',
    'role' => 'Certains rôles seulement (cases ci-dessous)',
    'unit' => 'Une unité (choisie dans « Liaisons métier »)',
    'collaborators' => 'Collaborateurs listés sur la fiche',
    'controlled' => 'Règles sur mesure — expert',
];
$visibilityHelp = [
    'private' => 'Réservé au propriétaire et aux collaborateurs explicitement ajoutés sur ce document (une fois publié).',
    'collaborators' => 'Seuls les collaborateurs listés sur la fiche document pourront lire après publication.',
    'unit' => 'Les membres de l’unité sélectionnée dans « Liaisons métier » pourront lire ; l’unité est obligatoire.',
    'role' => 'Seuls les rôles cochés (intra-tenant). État-major / fondateur / modération voient tout (sous réserve de la classification).',
    'organization' => 'Tout membre de la communauté peut lire si son profil autorise le niveau de classification du document.',
    'controlled' => 'Vous remplissez le tableau ligne par ligne : chaque règle cible un rôle, une unité, un utilisateur ou un groupe.',
];
$visibilityHelpLong = [
    'private' => '<p><strong>Privé</strong> limite la diffusion aux personnes qui travaillent sur le document : le <em>propriétaire</em>, l’<em>auteur</em> et les <em>collaborateurs</em> que vous ajouterez ensuite (éditeur, lecteur, etc.). Tant que le statut reste « Brouillon » ou « En relecture », les autres utilisateurs ne voient pas le fichier même s’ils ont d’autres droits.</p>',
    'collaborators' => '<p>Identique au mode privé sur le principe, mais l’accent est mis sur la <strong>liste de collaborateurs</strong> : seuls ces comptes auront accès une fois le document <em>publié</em>. Pensez à compléter les collaborateurs après création si besoin.</p>',
    'unit' => '<p>Le document est rattaché à <strong>une unité</strong> (liste « Unité » dans la section Liaisons métier). Les utilisateurs qui appartiennent à cette unité dans l’ORBAT / annuaire pourront le consulter lorsqu’il est publié. <strong>Obligatoire :</strong> choisir une unité, sinon la création est refusée.</p>',
    'role' => '<p>Vous cochez un ou plusieurs <strong>rôles communauté</strong> (ex. cadre, opérateur). Seuls ces profils pourront lire une fois publié, si leur <strong>niveau de classification</strong> le permet. <strong>État-major</strong>, <strong>fondateur</strong> et profils d’administration équivalents ne sont pas limités par cette liste. Les <strong>modérateurs du forum</strong> voient tout en lecture et commentaires. Le menu « Niveau d’accès » s’applique aux rôles cochés.</p>',
    'organization' => '<p>Diffusion large au sein de la <strong>communauté courante</strong> : tout compte actif peut lire le document publié, sauf s’il est bloqué par le <strong>niveau de classification</strong> (un utilisateur ne voit pas un document classé au-dessus de son plafond). À utiliser pour les notices générales non sensibles.</p>',
    'controlled' => '<p>Mode le plus fin : chaque ligne du tableau est une <strong>règle d’accès</strong>. Colonne « Type » : rôle (référence telle qu’en administration), unité, utilisateur ou groupe. Colonne « Valeur » : l’identifiant attendu pour ce type (souvent un numéro interne pour unité ou personne). Colonne « Accès » : jusqu’où va le droit (lecture, modification…). Les lignes vides sont ignorées. Combinez plusieurs règles si des profils différents doivent coexister.</p>',
];
$classificationHelpLong = '<p>Le <strong>niveau de classification</strong> protège le contenu : plus il est élevé, moins de profils peuvent le consulter. <strong>Public</strong> : peu restrictif. <strong>Interne service</strong> : usage courant au sein de l’organisation. <strong>Restreint / Sensible / Confidentiel / Opérationnel</strong> : réservé aux profils autorisés (souvent commandement ou rôles dédiés). Ce niveau est combiné à la <em>visibilité</em> : les deux conditions doivent être remplies pour qu’un utilisateur lise le document.</p>';
$statuses = ['draft' => 'Brouillon', 'review' => 'En relecture', 'approval' => 'À valider', 'published' => 'Publié', 'suspended' => 'Suspendu', 'archived' => 'Archivé', 'obsolete' => 'Obsolète'];
$relationTypes = ['annexe' => 'Annexe', 'piece_jointe' => 'Pièce jointe', 'reference' => 'Référence', 'support_formation' => 'Support formation', 'procedure_associee' => 'Procédure associée', 'document_lie' => 'Document lié'];
$currentUserId = (int) ($currentUserId ?? 0);

$accessLevelLabels = [
    'read' => 'Lecture',
    'comment' => 'Commenter',
    'edit' => 'Modifier',
    'approve' => 'Valider',
    'manage' => 'Gérer',
];
$permTypeLabels = [
    'role' => 'Rôle',
    'unit' => 'Unité',
    'user' => 'Utilisateur',
    'group' => 'Groupe',
];
?>
<style>
  .doc-help-details > summary { list-style: none; }
  .doc-help-details > summary::-webkit-details-marker { display: none; }
</style>
<div class="min-h-screen bg-gradient-to-b from-slate-50 to-slate-100/90">
  <div class="border-b border-slate-200/80 bg-white/90 backdrop-blur-sm">
    <div class="mx-auto flex max-w-6xl flex-col gap-4 px-4 py-8 sm:px-6 lg:px-8">
      <nav class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-500">
        <a href="<?= url('documents/gestion') ?>" class="text-emerald-700 hover:text-emerald-600">Documents</a>
        <span class="mx-2 text-slate-300">/</span>
        <span class="text-slate-800">Nouveau document</span>
      </nav>
      <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p class="text-[10px] font-black uppercase tracking-[0.35em] text-emerald-700">Gestion documentaire</p>
          <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-900">Créer un document</h1>
          <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">Métadonnées, classification, visibilité réelle (rôles, unité, règles fines) et cycle de vie.</p>
        </div>
      </div>
    </div>
  </div>

  <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
    <?php if (\App\Core\Session::get('error')): ?>
    <div class="mb-8 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 shadow-sm" role="alert">
      <?= htmlspecialchars(\App\Core\Session::get('error')) ?>
    </div>
    <?php \App\Core\Session::forget('error'); endif; ?>

    <div class="mb-8 overflow-hidden rounded-2xl border border-indigo-200/80 bg-gradient-to-br from-indigo-50/90 to-white shadow-sm">
      <details class="doc-help-details group">
        <summary class="flex cursor-pointer items-center gap-3 px-4 py-4 text-left sm:px-5">
          <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-sm font-black text-white shadow-md" aria-hidden="true">?</span>
          <span>
            <span class="block text-sm font-black uppercase tracking-wide text-indigo-950">Guide du formulaire</span>
            <span class="mt-0.5 block text-[13px] font-medium text-indigo-900/85">Cliquez pour comprendre l’ordre des sections et l’impact de chaque choix (classification, visibilité, cycle de vie).</span>
          </span>
          <span class="ml-auto shrink-0 text-indigo-400 transition group-open:rotate-180" aria-hidden="true">▼</span>
        </summary>
        <div class="space-y-4 border-t border-indigo-100/80 px-4 pb-5 pt-2 text-[13px] leading-relaxed text-slate-700 sm:px-5">
          <p><strong class="text-slate-900">Ordre recommandé.</strong> (1) Identité et fichier — (2) Classification &amp; visibilité — (3) Liaisons métier si besoin — (4) Hiérarchie / propriétaire — (5) Statut et dates. Rien n’est définitif : vous pourrez modifier la fiche après création.</p>
          <ul class="list-inside list-disc space-y-2 pl-1 text-slate-700">
            <li><strong>Classification</strong> : sensibilité du contenu (qui a le « droit » de voir ce niveau de secret).</li>
            <li><strong>Visibilité</strong> : à qui s’adresse le document une fois publié (privé, unité, rôles, toute l’org, règles fines).</li>
            <li><strong>Liaisons métier</strong> : rattachements optionnels (formation, équipement, unité, opérateur) pour retrouver le document par contexte.</li>
            <li><strong>Statut</strong> : brouillon = pas encore diffusé ; publié = les règles de visibilité s’appliquent aux lecteurs.</li>
          </ul>
          <p class="rounded-lg bg-white/80 px-3 py-2 text-[12px] text-slate-600 ring-1 ring-indigo-100">Les pastilles <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-black text-indigo-800 align-middle">?</span> sous chaque section ouvrent une aide détaillée sans quitter la page.</p>
        </div>
      </details>
    </div>

    <form action="<?= url('documents/gestion/ajout') ?>" method="post" enctype="multipart/form-data" id="document-upload-form" class="space-y-8">
        <?= \App\Core\Csrf::field() ?>
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
            <div class="space-y-6">
                <section class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm">
                    <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-4">
                        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Identité du document</h2>
                        <p class="mt-1 text-xs text-slate-500">Titre, type et classement documentaire.</p>
                    </div>
                    <div class="space-y-4 p-5">
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-600">Titre *</label>
                            <input type="text" name="title" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-inner shadow-slate-100/50 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" id="doc-title" placeholder="Intitulé officiel" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-600">Adresse courte du document</label>
                            <input type="text" name="slug" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" placeholder="Généré depuis le titre si vide" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-600">Résumé court</label>
                            <input type="text" name="short_description" maxlength="500" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-600">Description</label>
                            <textarea name="description" rows="3" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"></textarea>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-600">Type</label>
                                <select name="document_type" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                                    <option value="">—</option>
                                    <?php foreach ($documentTypes as $k => $v): ?>
                                    <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-600">Catégorie</label>
                                <select name="category" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                                    <option value="">—</option>
                                    <?php foreach ($categories as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-600">Tags</label>
                            <input type="text" name="tags_text" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" placeholder="tag1, tag2" />
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm">
                    <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                          <div>
                            <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Fichier</h2>
                            <p class="mt-1 text-xs text-slate-500">Pièce jointe ou fiche sans fichier.</p>
                          </div>
                          <details class="doc-help-details max-w-full sm:max-w-md">
                            <summary class="flex cursor-pointer items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-black uppercase tracking-wider text-slate-600 shadow-sm hover:border-emerald-300">
                              <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-800 text-[11px] text-white">?</span> Aide
                            </summary>
                            <div class="mt-3 rounded-xl border border-slate-200 bg-white p-3 text-[12px] leading-relaxed text-slate-600 shadow-sm">
                              <p>Cochez <strong>Document sans fichier</strong> pour créer uniquement la fiche (métadonnées, liaisons) — utile pour un document « logique » ou en attente de version.</p>
                              <p class="mt-2">Sinon, joignez un PDF, une image ou une vidéo selon les formats acceptés par le serveur. Le fichier devient la <strong>version courante</strong> du document à l’enregistrement.</p>
                            </div>
                          </details>
                        </div>
                    </div>
                    <div class="space-y-4 p-5">
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3 text-sm text-slate-700 transition hover:border-emerald-300">
                            <input type="checkbox" name="document_without_file" value="1" id="doc-without-file" class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                            <span>Document sans fichier (fiche métier uniquement)</span>
                        </label>
                        <div id="file-zone">
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-600">Fichier</label>
                            <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.mp4,application/pdf,image/jpeg,image/png,image/webp,video/mp4" class="w-full rounded-xl border-2 border-dashed border-slate-200 bg-slate-50/50 px-4 py-8 text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-xs file:font-bold file:text-white hover:border-emerald-400" />
                            <p class="mt-2 text-[11px] text-slate-500">PDF, images, vidéo — selon limites serveur.</p>
                        </div>
                    </div>
                </section>
            </div>

            <div class="space-y-6">
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_4px_24px_-4px_rgba(15,23,42,0.08)] ring-1 ring-slate-900/[0.04]">
                    <div class="relative border-b border-emerald-100 bg-gradient-to-r from-emerald-50 via-white to-slate-50 px-5 py-4">
                        <div class="absolute left-0 top-0 h-full w-1 bg-gradient-to-b from-emerald-500 to-teal-600" aria-hidden="true"></div>
                        <div class="flex flex-wrap items-start justify-between gap-3 pl-2">
                          <div>
                            <h2 class="text-sm font-black uppercase tracking-wider text-slate-900">Classification &amp; visibilité</h2>
                            <p class="mt-1 text-xs leading-relaxed text-slate-600">Sensibilité du contenu + qui peut lire une fois publié.</p>
                          </div>
                          <details class="doc-help-details max-w-full sm:max-w-lg">
                            <summary class="flex cursor-pointer items-center gap-2 rounded-full border border-emerald-200 bg-white px-3 py-1.5 text-[11px] font-black uppercase tracking-wider text-emerald-900 shadow-sm hover:bg-emerald-50">
                              <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">?</span> Aide globale
                            </summary>
                            <div class="mt-3 rounded-xl border border-emerald-100 bg-white p-3 text-[12px] leading-relaxed text-slate-700 shadow-sm">
                              <p><strong>Deux leviers distincts.</strong> La <em>classification</em> limite selon le profil (ex. confidentiel = peu de monde). La <em>visibilité</em> limite selon le périmètre (unité, rôle, etc.). Les deux s’appliquent ensemble.</p>
                              <p class="mt-2">Tant que le document n’est pas <strong>publié</strong>, la plupart des lecteurs ne le voient pas : seuls propriétaire et collaborateurs travaillent dessus.</p>
                            </div>
                          </details>
                        </div>
                    </div>
                    <div class="space-y-5 p-5">
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-600" for="classification-level">Niveau de classification</label>
                            <select name="classification_level" id="classification-level" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                                <?php foreach ($classificationLevels as $k => $v): ?>
                                <option value="<?= htmlspecialchars($k) ?>" <?= $k === 'interne' ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <details class="doc-help-details mt-2">
                              <summary class="cursor-pointer text-[11px] font-semibold text-emerald-800 hover:underline">Détail des niveaux (liste)</summary>
                              <ul class="mt-2 space-y-1.5 rounded-lg border border-slate-100 bg-slate-50/50 p-3 text-[11px] leading-snug text-slate-600">
                                <li><strong class="text-slate-800">Public</strong> — diffusion large possible si le reste du formulaire le permet.</li>
                                <li><strong class="text-slate-800">Interne service</strong> — usage interne courant.</li>
                                <li><strong class="text-slate-800">Restreint / Sensible / Confidentiel / Opérationnel</strong> — plafonds de plus en plus stricts ; réservé aux rôles habilités.</li>
                              </ul>
                            </details>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-600" for="visibility-scope">Qui peut lire une fois le document publié ?</label>
                            <p class="mb-2 text-[11px] leading-snug text-slate-500">Astuce : en brouillon, seuls vous et les collaborateurs voyez le document — choisissez surtout <strong>Privé</strong> ou <strong>Toute la communauté</strong>.</p>
                            <div class="mb-3 flex flex-wrap gap-2">
                                <button type="button" class="doc-vis-preset rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-800 shadow-sm transition hover:border-emerald-400 hover:bg-emerald-50" data-vis="private">Privé</button>
                                <button type="button" class="doc-vis-preset rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-800 shadow-sm transition hover:border-emerald-400 hover:bg-emerald-50" data-vis="organization">Toute la communauté</button>
                                <button type="button" class="doc-vis-preset rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-800 shadow-sm transition hover:border-emerald-400 hover:bg-emerald-50" data-vis="role">Par rôles</button>
                                <button type="button" class="doc-vis-preset rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-800 shadow-sm transition hover:border-emerald-400 hover:bg-emerald-50" data-vis="unit">Par unité</button>
                                <button type="button" class="doc-vis-preset rounded-lg border border-dashed border-amber-300 bg-amber-50/80 px-3 py-1.5 text-[11px] font-semibold text-amber-950 transition hover:bg-amber-100" data-vis="controlled">Mode expert</button>
                            </div>
                            <select name="visibility_scope" id="visibility-scope" class="w-full rounded-xl border-2 border-slate-300 bg-white px-3 py-2.5 text-sm font-medium text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/25">
                                <?php foreach ($visibilityScopes as $k => $v): ?>
                                <option value="<?= htmlspecialchars($k) ?>" <?= $k === 'private' ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p id="visibility-help" class="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-[11px] leading-relaxed text-slate-600"><?= htmlspecialchars($visibilityHelp['private']) ?></p>
                            <details class="doc-help-details mt-2 rounded-lg border border-slate-200 bg-white">
                                <summary class="cursor-pointer px-3 py-2 text-[11px] font-semibold text-slate-600">Aide détaillée sur ce mode</summary>
                                <div id="visibility-help-long" class="doc-visibility-long border-t border-slate-100 px-3 py-2.5 text-[12px] leading-relaxed text-slate-700"><?= $visibilityHelpLong['private'] ?></div>
                            </details>
                        </div>

                        <div id="panel-visibility-role" class="hidden rounded-xl border border-violet-200 bg-violet-50/50 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                              <p class="text-xs font-bold uppercase tracking-wide text-violet-900">Rôles autorisés à la lecture</p>
                              <details class="doc-help-details text-right">
                                <summary class="inline-flex cursor-pointer list-none items-center gap-1 rounded-full border border-violet-300 bg-white px-2 py-0.5 text-[10px] font-black uppercase text-violet-900"><span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-violet-700 text-[10px] text-white">?</span> Aide</summary>
                                <div class="mt-2 rounded-lg border border-violet-100 bg-white p-3 text-left text-[11px] leading-relaxed text-violet-950/90">
                                  Cochez les <strong>rôles</strong> proposés pour votre communauté (officier, membre…). Le <strong>niveau d’accès</strong> s’applique à toutes les cases cochées : en général « Lecture » suffit pour une diffusion lecture seule ; montez seulement si ces rôles doivent aussi commenter ou modifier via les permissions documentaires.
                                </div>
                              </details>
                            </div>
                            <p class="mt-1 text-[11px] leading-relaxed text-violet-900/85">Ces cases s’appliquent aux <strong>membres de cette communauté</strong> uniquement. <strong>État-major</strong> et <strong>fondateur</strong> : accès complet. <strong>Modérateurs forum</strong> : lecture (et commentaires) sur tout document publié. La classification s’applique toujours.</p>
                            <?php if (empty($tenantRoles)): ?>
                                <p class="mt-3 text-sm text-amber-800">Aucun rôle communauté trouvé. Créez des rôles dans l’admin organisation.</p>
                            <?php else: ?>
                            <div class="mt-3 max-h-48 space-y-2 overflow-y-auto rounded-lg border border-violet-100 bg-white p-3">
                                <?php foreach ($tenantRoles as $role): ?>
                                <label class="flex cursor-pointer items-start gap-3 rounded-lg px-2 py-1.5 text-sm hover:bg-violet-50">
                                    <input type="checkbox" name="visibility_role_slugs[]" value="<?= htmlspecialchars((string) ($role['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500" />
                                    <span>
                                        <span class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($role['name'] ?? '')) ?></span>
                                        <span class="ml-2 font-mono text-[11px] text-slate-500"><?= htmlspecialchars((string) ($role['slug'] ?? '')) ?></span>
                                        <?php if (!empty($role['description'])): ?>
                                        <span class="mt-0.5 block text-[11px] text-slate-500"><?= htmlspecialchars((string) $role['description']) ?></span>
                                        <?php endif; ?>
                                    </span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-4">
                                <label class="mb-1 block text-[11px] font-bold uppercase text-slate-600">Niveau d’accès pour ces rôles</label>
                                <select name="visibility_role_access_level" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                    <?php foreach ($permissionAccessLevels as $lvl): ?>
                                    <option value="<?= htmlspecialchars($lvl) ?>" <?= $lvl === 'read' ? 'selected' : '' ?>><?= htmlspecialchars($accessLevelLabels[$lvl] ?? $lvl) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div id="panel-visibility-unit" class="hidden rounded-xl border border-sky-200 bg-sky-50/60 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                              <p class="text-xs font-bold uppercase tracking-wide text-sky-900">Unité de diffusion</p>
                              <details class="doc-help-details text-right">
                                <summary class="inline-flex cursor-pointer list-none items-center gap-1 rounded-full border border-sky-300 bg-white px-2 py-0.5 text-[10px] font-black uppercase text-sky-900"><span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-sky-700 text-[10px] text-white">?</span> Aide</summary>
                                <div class="mt-2 rounded-lg border border-sky-100 bg-white p-3 text-left text-[11px] leading-relaxed text-sky-950/90">
                                  L’<strong>unité</strong> sert à deux choses : rattachement métier (ORBAT) et, avec ce mode de visibilité, <strong>liste des personnes</strong> qui partagent cette unité et pourront lire le document publié. Le champ est le même que dans « Liaisons métier » : une seule unité par document pour ce mode.
                                </div>
                              </details>
                            </div>
                            <p class="mt-2 text-[11px] leading-relaxed text-sky-900/90">
                                Sélectionnez l’unité dans la section <a href="#liaisons-metier" class="font-bold text-sky-800 underline decoration-sky-400 underline-offset-2">Liaisons métier</a> (liste « Unité »). Les membres rattachés à cette unité pourront lire le document une fois publié.
                            </p>
                        </div>

                        <div id="panel-visibility-controlled" class="hidden rounded-xl border border-amber-200 bg-amber-50/50 p-4">
                            <p class="text-[12px] font-semibold text-amber-950">Mode expert — à n’utiliser que si les autres modes ne suffisent pas.</p>
                            <p class="mt-1 text-[11px] leading-relaxed text-amber-900/90">Pour la plupart des documents, <strong>« Par rôles »</strong> ou <strong>« Toute la communauté »</strong> est plus simple que ce tableau.</p>
                            <details class="doc-help-details mt-2 rounded-lg border border-amber-200 bg-white">
                              <summary class="cursor-pointer px-3 py-2 text-[11px] font-semibold text-amber-900">Comment remplir le tableau ?</summary>
                              <div class="border-t border-amber-100 px-3 py-2 text-[11px] leading-relaxed text-amber-950/95">
                                <strong>Rôle</strong> : référence telle qu’affichée dans l’administration des rôles. <strong>Unité / Utilisateur</strong> : numéro interne attribué par le système. <strong>Groupe</strong> : identifiant métier si utilisé. Colonne <em>Accès</em> : droit maximal pour la ligne. Les lignes vides sont ignorées.
                              </div>
                            </details>
                            <details id="controlled-fine-rules-details" class="mt-3 overflow-x-auto rounded-lg border border-amber-100 bg-white shadow-sm">
                              <summary class="cursor-pointer px-3 py-2.5 text-[12px] font-bold text-amber-950">Afficher le tableau des règles fines</summary>
                              <div class="border-t border-amber-100 p-2">
                                <table class="min-w-full divide-y divide-amber-100 text-left text-[12px]">
                                    <thead class="bg-amber-50/80 text-[10px] font-black uppercase tracking-wider text-amber-900/80">
                                        <tr>
                                            <th class="px-3 py-2">Type</th>
                                            <th class="px-3 py-2">Valeur</th>
                                            <th class="px-3 py-2">Accès</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-amber-50">
                                        <?php for ($pi = 0; $pi < 6; $pi++): ?>
                                        <tr>
                                            <td class="px-2 py-2 align-top">
                                                <select name="permissions[<?= $pi ?>][permission_type]" class="w-full min-w-[7rem] rounded border border-slate-200 px-2 py-1.5 text-[12px]">
                                                    <option value="">—</option>
                                                    <?php foreach ($permTypeLabels as $pk => $pl): ?>
                                                    <option value="<?= htmlspecialchars($pk) ?>"><?= htmlspecialchars($pl) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td class="px-2 py-2 align-top">
                                                <input type="text" name="permissions[<?= $pi ?>][permission_value]" class="w-full min-w-[8rem] rounded border border-slate-200 px-2 py-1.5 font-mono text-[12px]" placeholder="Référence rôle ou n° interne" title="Référence du rôle ou numéro interne unité ou personne" />
                                            </td>
                                            <td class="px-2 py-2 align-top">
                                                <select name="permissions[<?= $pi ?>][access_level]" class="w-full rounded border border-slate-200 px-2 py-1.5 text-[12px]">
                                                    <?php foreach ($permissionAccessLevels as $lvl): ?>
                                                    <option value="<?= htmlspecialchars($lvl) ?>" <?= $lvl === 'read' ? 'selected' : '' ?>><?= htmlspecialchars($accessLevelLabels[$lvl] ?? $lvl) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <?php endfor; ?>
                                    </tbody>
                                </table>
                              </div>
                            </details>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50/90 p-4">
                          <p class="mb-3 text-[11px] font-bold uppercase tracking-wide text-slate-600">Options courantes</p>
                          <div class="grid gap-3 sm:grid-cols-2">
                            <label class="flex cursor-pointer items-start gap-2 text-sm text-slate-800">
                                <input type="checkbox" name="download_allowed" value="1" checked class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                <span><strong>Téléchargement</strong> <span class="text-[11px] text-slate-500">(décocher pour lecture seule à l’écran)</span></span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-2 text-sm text-slate-800">
                                <input type="checkbox" name="print_allowed" value="1" checked class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                <span><strong>Impression</strong></span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-2 text-sm text-slate-800">
                                <input type="checkbox" name="locked" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                <span><strong>Verrouillé</strong> <span class="text-[11px] text-slate-500">(modifs limitées)</span></span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-2 text-sm text-slate-800">
                                <input type="checkbox" name="inherit_parent_security" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                <span><strong>Hériter sécurité du parent</strong></span>
                            </label>
                          </div>
                        </div>
                    </div>
                </section>

                <section id="liaisons-metier" class="scroll-mt-24 overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm">
                    <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                          <div>
                            <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Liaisons métier</h2>
                            <p class="mt-1 text-xs text-slate-500">Rattachements pour retrouver le document par contexte.</p>
                          </div>
                          <details class="doc-help-details max-w-full sm:max-w-md">
                            <summary class="flex cursor-pointer items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-black uppercase tracking-wider text-slate-600 shadow-sm">
                              <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-800 text-[11px] text-white">?</span> Aide
                            </summary>
                            <div class="mt-3 rounded-xl border border-slate-200 bg-white p-3 text-[12px] leading-relaxed text-slate-600 shadow-sm">
                              <p>Ces champs sont <strong>facultatifs</strong> mais utiles pour classer le document : formation associée, matériel, <strong>unité propriétaire</strong> (obligatoire si visibilité « Unité »), opérateur référent, code mission libre.</p>
                              <p class="mt-2">Ils alimentent les liens et recherches ; ils ne remplacent pas la visibilité générale sauf pour le mode « Unité » où l’unité choisie définit aussi le public lecteur.</p>
                            </div>
                          </details>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-[11px] font-bold uppercase text-slate-600">Formation</label>
                            <select name="link_training" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                <option value="">—</option>
                                <?php foreach ($trainings as $t): ?>
                                <option value="<?= htmlspecialchars($t['value'] ?? '') ?>"><?= htmlspecialchars($t['label'] ?? ($t['title'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[11px] font-bold uppercase text-slate-600">Classe d’équipement</label>
                            <select name="link_equipment_class" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                <option value="">—</option>
                                <?php foreach ($equipmentClasses as $e): ?>
                                <option value="<?= (int) $e['id'] ?>"><?= htmlspecialchars($e['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[11px] font-bold uppercase text-slate-600">Unité <span class="text-rose-600">*</span> si visibilité « Unité »</label>
                            <select name="link_unit" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20">
                                <option value="">—</option>
                                <?php foreach ($units as $u): ?>
                                <option value="<?= (int) $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[11px] font-bold uppercase text-slate-600">Opérateur</label>
                            <select name="link_user" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                <option value="">—</option>
                                <?php foreach ($users as $u): ?>
                                <option value="<?= (int) $u['id'] ?>"><?= htmlspecialchars($u['display_name'] ?? $u['email']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1.5 block text-[11px] font-bold uppercase text-slate-600">Mission (réf.)</label>
                            <input type="text" name="mission_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="ex. op_tanoa_07" />
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                          <div>
                            <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Hiérarchie &amp; collaboration</h2>
                            <p class="mt-1 text-xs text-slate-500">Document parent, version, propriétaire et auteur.</p>
                          </div>
                          <details class="doc-help-details max-w-full sm:max-w-md">
                            <summary class="flex cursor-pointer items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-black uppercase tracking-wider text-slate-600 shadow-sm">
                              <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-800 text-[11px] text-white">?</span> Aide
                            </summary>
                            <div class="mt-3 rounded-xl border border-slate-200 bg-white p-3 text-[12px] leading-relaxed text-slate-600 shadow-sm">
                              <p><strong>Parent</strong> : relie ce document à un autre (annexe, pièce jointe logique). <strong>Type de liaison</strong> précise la nature du lien.</p>
                              <p class="mt-2"><strong>Propriétaire</strong> : responsable du contenu (souvent vous). <strong>Auteur principal</strong> : rédacteur de référence. Vous pourrez ajouter d’autres collaborateurs après création depuis la fiche document.</p>
                            </div>
                          </details>
                        </div>
                    </div>
                    <div class="space-y-4 p-5">
                        <div>
                            <label class="mb-1.5 block text-xs font-bold text-slate-600">Document parent</label>
                            <select name="parent_document_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                <option value="">— Aucun</option>
                                <?php foreach ($allDocuments ?? [] as $d): ?>
                                <option value="<?= (int) $d['id'] ?>"><?= htmlspecialchars($d['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-slate-600">Type de liaison</label>
                                <select name="relation_type" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                    <?php foreach ($relationTypes as $k => $v): ?>
                                    <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-slate-600">Ordre</label>
                                <input type="number" name="sort_order" value="0" min="0" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                            </div>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-bold text-slate-600">Version / libellé</label>
                            <input type="text" name="version_label" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="ex. 1.0" />
                        </div>
                        <div class="grid gap-4 border-t border-slate-100 pt-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-slate-600">Propriétaire</label>
                                <select name="owner_user_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                    <?php foreach ($users as $u): ?>
                                    <option value="<?= (int) $u['id'] ?>" <?= (int) $u['id'] === $currentUserId ? 'selected' : '' ?>><?= htmlspecialchars($u['display_name'] ?? $u['email']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-slate-600">Auteur principal</label>
                                <select name="author_user_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                    <?php foreach ($users as $u): ?>
                                    <option value="<?= (int) $u['id'] ?>" <?= (int) $u['id'] === $currentUserId ? 'selected' : '' ?>><?= htmlspecialchars($u['display_name'] ?? $u['email']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                          <div>
                            <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Cycle de vie</h2>
                            <p class="mt-1 text-xs text-slate-500">Statut de workflow et jalons temporels.</p>
                          </div>
                          <details class="doc-help-details max-w-full sm:max-w-md">
                            <summary class="flex cursor-pointer items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-black uppercase tracking-wider text-slate-600 shadow-sm">
                              <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-800 text-[11px] text-white">?</span> Aide
                            </summary>
                            <div class="mt-3 rounded-xl border border-slate-200 bg-white p-3 text-[12px] leading-relaxed text-slate-600 shadow-sm">
                              <p><strong>Brouillon / relecture / validation</strong> : le document n’est pas encore diffusé comme ressource « publique » aux lecteurs selon les règles de visibilité.</p>
                              <p class="mt-2"><strong>Publié</strong> : les règles de classification + visibilité s’appliquent aux membres autorisés.</p>
                              <p class="mt-2"><strong>Dates</strong> : effet (entrée en vigueur), révision (contrôle périodique), expiration (fin de validité du contenu).</p>
                            </div>
                          </details>
                        </div>
                    </div>
                    <div class="space-y-4 p-5">
                        <div>
                            <div class="mb-1.5 flex flex-wrap items-center gap-2">
                              <label class="block text-xs font-bold text-slate-600" for="doc-status">Statut</label>
                              <details class="doc-help-details">
                                <summary class="inline-flex cursor-pointer list-none items-center gap-1 rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-[10px] font-black uppercase text-slate-700"><span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-slate-700 text-[10px] text-white">?</span></summary>
                                <div class="mt-2 max-w-lg rounded-lg border border-slate-200 bg-white p-3 text-[11px] leading-relaxed text-slate-600">
                                  <strong>Brouillon</strong> — travail en cours, invisible pour les lecteurs cibles.<br />
                                  <strong>En relecture / À valider</strong> — workflow interne avant publication.<br />
                                  <strong>Publié</strong> — la visibilité et la classification s’appliquent aux utilisateurs autorisés.<br />
                                  <strong>Suspendu</strong> — accès retiré temporairement sans archiver.<br />
                                  <strong>Archivé / Obsolète</strong> — conservé pour l’historique, hors circuit courant.
                                </div>
                              </details>
                            </div>
                            <select name="status" id="doc-status" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                <?php foreach ($statuses as $k => $v): ?>
                                <option value="<?= htmlspecialchars($k) ?>" <?= $k === 'draft' ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label class="mb-1.5 block text-[11px] font-bold uppercase text-slate-600">Effet</label>
                                <input type="datetime-local" name="effective_at" class="w-full rounded-xl border border-slate-200 px-2 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-[11px] font-bold uppercase text-slate-600">Révision</label>
                                <input type="datetime-local" name="review_due_at" class="w-full rounded-xl border border-slate-200 px-2 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-[11px] font-bold uppercase text-slate-600">Expiration</label>
                                <input type="datetime-local" name="expires_at" class="w-full rounded-xl border border-slate-200 px-2 py-2 text-sm" />
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 pt-8">
            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-8 py-3 text-sm font-black uppercase tracking-wider text-white shadow-lg shadow-slate-900/15 transition hover:bg-emerald-600">Créer le document</button>
            <a href="<?= url('documents/gestion') ?>" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">Annuler</a>
        </div>
    </form>
  </div>
</div>
<script>
(function() {
  var help = <?= json_encode($visibilityHelp, JSON_UNESCAPED_UNICODE) ?>;
  var helpLong = <?= json_encode($visibilityHelpLong, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;
  var sel = document.getElementById('visibility-scope');
  var helpEl = document.getElementById('visibility-help');
  var helpLongEl = document.getElementById('visibility-help-long');
  var panelRole = document.getElementById('panel-visibility-role');
  var panelUnit = document.getElementById('panel-visibility-unit');
  var panelCtrl = document.getElementById('panel-visibility-controlled');
  function refresh() {
    var v = sel.value;
    if (helpEl && help[v]) helpEl.textContent = help[v];
    if (helpLongEl && helpLong[v]) helpLongEl.innerHTML = helpLong[v];
    panelRole.classList.toggle('hidden', v !== 'role');
    panelUnit.classList.toggle('hidden', v !== 'unit');
    panelCtrl.classList.toggle('hidden', v !== 'controlled');
  }
  sel.addEventListener('change', refresh);
  document.querySelectorAll('.doc-vis-preset').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var vis = btn.getAttribute('data-vis');
      if (vis && sel.querySelector('option[value="' + vis + '"]')) {
        sel.value = vis;
        refresh();
      }
    });
  });
  refresh();
})();
document.getElementById('doc-without-file').addEventListener('change', function() {
  var zone = document.getElementById('file-zone');
  zone.style.display = this.checked ? 'none' : 'block';
});
</script>
