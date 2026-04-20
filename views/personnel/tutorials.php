<div class="min-h-screen bg-gradient-to-b from-slate-50 to-slate-100/80 pb-20">
  <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
    <header class="mb-10 border-b border-slate-200 pb-8">
      <p class="text-[10px] font-black uppercase tracking-[0.35em] text-emerald-700">Aide Athena</p>
      <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-900">Tutoriels — dossier personnel &amp; ORBAT</h1>
      <p class="mt-3 text-sm leading-relaxed text-slate-600">Comment remplir votre dossier, vous affecter à une unité, utiliser les presets et régler la visibilité forum.</p>
      <div class="mt-6 flex flex-wrap gap-3">
        <a href="<?= htmlspecialchars(url('personnel/me/edit')) ?>" class="inline-flex rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">Éditer mon dossier</a>
        <a href="<?= htmlspecialchars(url('orbat')) ?>" class="inline-flex rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">Ouvrir l’ORBAT</a>
        <a href="<?= htmlspecialchars(url('personnel/me')) ?>" class="inline-flex rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">Ma fiche</a>
        <a href="<?= htmlspecialchars(url('personnel/mon-espace-rh')) ?>" class="inline-flex rounded-xl border border-emerald-200 bg-emerald-50/80 px-4 py-2.5 text-sm font-semibold text-emerald-900 hover:bg-emerald-100">Espace RH et formations</a>
      </div>
    </header>

    <article class="prose prose-slate max-w-none space-y-10 text-sm leading-relaxed">
      <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mt-0 text-lg font-black uppercase tracking-wide text-slate-900">1. À quoi sert le dossier ?</h2>
        <p>Le <strong>dossier personnel</strong> distingue votre <em>compte</em> (connexion, identité civile optionnelle) de votre <em>personnage / profil opérationnel</em> (RP, unité, équipement). Il alimente la <strong>fiche</strong>, l’<strong>ORBAT</strong>, le <strong>forum</strong> (carte auteur) et les <strong>listes admin</strong> (ex. effectifs sans unité).</p>
      </section>

      <section class="rounded-2xl border border-cyan-200 bg-cyan-50/40 p-6 shadow-sm">
        <h2 class="mt-0 text-lg font-black uppercase tracking-wide text-cyan-950">2. Unité &amp; affectation (ORBAT)</h2>
        <ol class="list-decimal space-y-2 pl-5">
          <li>Créez ou vérifiez les <strong>unités</strong> dans l’<a class="font-semibold text-cyan-900 underline" href="<?= htmlspecialchars(url('orbat')) ?>">ORBAT</a> (structure de la communauté).</li>
          <li>Dans <strong>Éditer le dossier</strong>, section <em>Unité &amp; affectation (ORBAT)</em>, choisissez l’<strong>unité principale</strong> puis renseignez le champ <strong>Rôle dans l’unité</strong> (ex. Officier opérations, Fusilier).</li>
          <li>À l’enregistrement, ce couple unité + rôle crée ou met à jour la ligne d’affectation (fiche, statistiques « sans unité »).</li>
          <li>Sans unité dans la liste, créez d’abord l’unité côté ORBAT / administration.</li>
        </ol>
      </section>

      <section class="rounded-2xl border border-indigo-200 bg-white p-6 shadow-sm">
        <h2 class="mt-0 text-lg font-black uppercase tracking-wide text-slate-900">3. Presets de rôle (ex. Officier opérations)</h2>
        <p>Sur la page d’édition, dans la section ORBAT, les boutons <strong>presets</strong> remplissent le <em>rôle dans l’unité</em> et des <em>suggestions d’équipement</em> (classe, radio, armement…). Ce sont des <strong>modèles</strong> : vous choisissez toujours l’unité, puis vous adaptez le texte si besoin avant <strong>Enregistrer</strong>.</p>
        <p class="mb-0 text-slate-600">Les presets ne choisissent pas l’unité à votre place : vous devez toujours sélectionner l’unité ORBAT correspondante.</p>
      </section>

      <section class="rounded-2xl border border-violet-200 bg-violet-50/30 p-6 shadow-sm">
        <h2 class="mt-0 text-lg font-black uppercase tracking-wide text-violet-950">4. Forum global vs espace communauté</h2>
        <ul class="list-disc space-y-2 pl-5">
          <li><strong>Forum global (plateforme)</strong> : la carte auteur est <em>allégée</em> pour tout le monde (pas de détail ORBAT sur la carte). Les modérateurs voient l’identité réelle dans le panneau dédié.</li>
          <li><strong>Espace dédié</strong> à votre communauté : la carte peut afficher grade, unité, etc. selon vos cases <em>Forum &amp; visibilité</em> dans le dossier.</li>
        </ul>
        <p class="mb-0">Depuis un sujet, le bouton <strong>Compte &amp; forum</strong> renvoie vers les préférences compte et le bloc forum du dossier.</p>
      </section>

      <section class="rounded-2xl border border-amber-200 bg-amber-50/40 p-6 shadow-sm">
        <h2 class="mt-0 text-lg font-black uppercase tracking-wide text-amber-950">5. Complétude du dossier</h2>
        <p>Le score indique les champs encore vides (identité, affectation, clearance, disponibilité, etc.). Remplir le <strong>rôle principal</strong> + une <strong>unité</strong> + les champs de sécurité demandés fait monter le score. Une <strong>formation certifiante</strong> peut parfois compenser la disponibilité numérique.</p>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mt-0 text-lg font-black uppercase tracking-wide text-slate-900">6. Raccourcis utiles</h2>
        <ul class="mb-0 list-none space-y-2 pl-0">
          <li><a class="font-semibold text-emerald-800 underline" href="<?= htmlspecialchars(url('account/preferences')) ?>">Préférences compte</a> — nom affiché, indicatif, langue.</li>
          <li><a class="font-semibold text-emerald-800 underline" href="<?= htmlspecialchars(url('account/portrait')) ?>">Portrait / médias</a> — avatar forum.</li>
          <li><a class="font-semibold text-emerald-800 underline" href="<?= htmlspecialchars(url('personnel/me/edit')) ?>">Éditer le dossier</a> — bloc Forum &amp; visibilité.</li>
        </ul>
      </section>

      <section class="rounded-2xl border border-emerald-200 bg-emerald-50/40 p-6 shadow-sm">
        <h2 class="mt-0 text-lg font-black uppercase tracking-wide text-emerald-950">7. Check-list de validation (rapide)</h2>
        <ol class="mb-0 list-decimal space-y-2 pl-5">
          <li>Je renseigne mon identité RP (nom personnage) et mon matricule.</li>
          <li>Je sélectionne mon unité ORBAT et je complète mon rôle principal.</li>
          <li>Je vérifie mes options <strong>Forum &amp; visibilité</strong> selon ce que je veux afficher.</li>
          <li>Je termine les champs de sécurité (clearance, disponibilité, statut) pour éviter les alertes de complétude.</li>
          <li>Je valide puis je relis ma fiche publique dans <a class="font-semibold text-emerald-900 underline" href="<?= htmlspecialchars(url('personnel/me')) ?>">Ma fiche</a>.</li>
        </ol>
      </section>
    </article>
  </div>
</div>
