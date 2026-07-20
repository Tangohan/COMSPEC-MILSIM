<div class="min-h-screen bg-gradient-to-b from-slate-50 to-slate-100/80 pb-20">
  <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
    <header class="mb-10 border-b border-slate-200 pb-8">
      <p class="text-[10px] font-black uppercase tracking-[0.35em] text-emerald-700">Aide Athena</p>
      <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-900">Guide du dossier personnel</h1>
      <p class="mt-4 max-w-2xl text-base leading-relaxed text-slate-600">
        Ce guide vous accompagne pour constituer votre dossier opérationnel&nbsp;: identité de personnage, unité, rôle, équipement suggéré et ce que les autres voient de vous sur le forum. Lisez les sections dans l’ordre si vous démarrez, ou sautez à celle qui correspond à votre question du moment.
      </p>
      <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-500">
        Les liens ci-dessous ouvrent les écrans concernés&nbsp;; revenez ici dès que vous avez besoin de remettre le fil.
      </p>
      <div class="mt-8 flex flex-wrap gap-3">
        <a href="<?= htmlspecialchars(url('personnel/me/edit')) ?>" class="inline-flex rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">Éditer mon dossier</a>
        <a href="<?= htmlspecialchars(url('orbat')) ?>" class="inline-flex rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">Ouvrir l’organigramme</a>
        <a href="<?= htmlspecialchars(url('personnel/me')) ?>" class="inline-flex rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">Ma fiche</a>
        <a href="<?= htmlspecialchars(url('personnel/mon-espace-rh')) ?>" class="inline-flex rounded-xl border border-emerald-200 bg-emerald-50/80 px-4 py-2.5 text-sm font-semibold text-emerald-900 hover:bg-emerald-100">Espace RH et formations</a>
      </div>
    </header>

    <article class="space-y-8 text-sm leading-relaxed text-slate-700">

      <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-slate-400">Avant de commencer</p>
        <h2 class="mt-2 text-lg font-black tracking-tight text-slate-900">À quoi sert le dossier&nbsp;?</h2>
        <p class="mt-4 text-base leading-relaxed text-slate-600">
          Votre <strong>compte</strong> permet de vous connecter. Votre <strong>dossier personnel</strong> décrit le personnage ou le profil opérationnel que la communauté voit&nbsp;: nom de mission, unité, fonction, équipement, et préférences d’affichage.
        </p>
        <p class="mt-4 leading-relaxed">
          Une fois renseigné, ce dossier alimente votre fiche, votre place dans l’organigramme (ORBAT), la carte d’auteur sur le forum de la communauté, et les listes utilisées par l’encadrement (par exemple pour repérer qui n’a pas encore d’unité).
        </p>
        <p class="mt-4 mb-0 rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3 text-slate-600">
          <span class="font-semibold text-slate-800">Quand s’en servir&nbsp;?</span>
          À l’arrivée dans la communauté, après un changement d’affectation, ou dès que votre fiche ou votre carte forum ne reflète plus votre situation réelle.
        </p>
      </section>

      <section class="rounded-2xl border border-cyan-200 bg-cyan-50/40 p-6 shadow-sm sm:p-8">
        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-cyan-700/80">Affectation</p>
        <h2 class="mt-2 text-lg font-black tracking-tight text-cyan-950">Choisir son unité et son rôle</h2>
        <p class="mt-4 text-base leading-relaxed text-cyan-950/80">
          L’organigramme (ORBAT) décrit la structure de la communauté. Votre dossier rattache votre personnage à une unité de cet organigramme et précise la fonction que vous y occupez.
        </p>
        <p class="mt-4 rounded-xl border border-cyan-100/80 bg-white/70 px-4 py-3 text-cyan-950/85">
          <span class="font-semibold text-cyan-950">Quand s’en servir&nbsp;?</span>
          Lorsque vous rejoignez une unité, que vous changez de poste, ou que votre fiche indique encore «&nbsp;sans unité&nbsp;».
        </p>
        <ol class="mt-6 list-decimal space-y-4 pl-5 text-cyan-950/90">
          <li>
            <span class="font-semibold text-cyan-950">Vérifiez que l’unité existe.</span>
            Ouvrez l’<a class="font-semibold text-cyan-900 underline underline-offset-2" href="<?= htmlspecialchars(url('orbat')) ?>">organigramme</a>. Si votre unité n’apparaît pas dans la liste du dossier, elle doit d’abord être créée côté organigramme ou administration.
          </li>
          <li>
            <span class="font-semibold text-cyan-950">Renseignez l’affectation dans le dossier.</span>
            Dans <a class="font-semibold text-cyan-900 underline underline-offset-2" href="<?= htmlspecialchars(url('personnel/me/edit')) ?>">Éditer mon dossier</a>, section <em>Unité &amp; rôle</em>, choisissez l’<strong>unité principale</strong>, puis indiquez votre <strong>rôle dans l’unité</strong> (par exemple Officier opérations, Fusilier). Si votre communauté propose un <strong>rôle métier</strong> dans le référentiel, sélectionnez-le également.
          </li>
          <li>
            <span class="font-semibold text-cyan-950">Enregistrez pour publier l’affectation.</span>
            L’enregistrement met à jour votre fiche et votre place visible dans l’organigramme. Sans enregistrement, le choix reste un brouillon sur l’écran d’édition.
          </li>
        </ol>
      </section>

      <section class="rounded-2xl border border-indigo-200 bg-white p-6 shadow-sm sm:p-8">
        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-indigo-500">Gain de temps</p>
        <h2 class="mt-2 text-lg font-black tracking-tight text-slate-900">Modèles de rôle (presets)</h2>
        <p class="mt-4 text-base leading-relaxed text-slate-600">
          Les modèles proposés dans la section <em>Unité &amp; rôle</em> préremplissent un rôle type et des suggestions d’équipement (classe, radio, armement…). Ils accélèrent la saisie&nbsp;; ils ne remplacent pas votre jugement.
        </p>
        <p class="mt-4 rounded-xl border border-indigo-100 bg-indigo-50/50 px-4 py-3 text-slate-700">
          <span class="font-semibold text-slate-900">Quand s’en servir&nbsp;?</span>
          Quand vous créez ou refondez un profil opérationnel proche d’un métier connu (officier, fusilier, radio…), avant d’ajuster les détails à votre situation.
        </p>
        <div class="mt-6 space-y-3 text-slate-700">
          <p class="mb-0">
            Choisissez d’abord votre <strong>unité</strong>, puis appliquez un modèle si besoin. Relisez le rôle et les suggestions d’équipement, corrigez ce qui ne correspond pas, puis <strong>enregistrez</strong>.
          </p>
          <p class="mb-0 rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3 text-slate-600">
            Un modèle ne choisit jamais l’unité à votre place&nbsp;: l’affectation reste toujours une décision explicite de votre part.
          </p>
        </div>
      </section>

      <section class="rounded-2xl border border-violet-200 bg-violet-50/30 p-6 shadow-sm sm:p-8">
        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-violet-600/80">Visibilité</p>
        <h2 class="mt-2 text-lg font-black tracking-tight text-violet-950">Ce que les autres voient de vous</h2>
        <p class="mt-4 text-base leading-relaxed text-violet-950/80">
          Selon l’endroit où vous écrivez, la carte d’auteur n’affiche pas les mêmes informations. Votre dossier contrôle surtout ce qui apparaît dans l’espace de votre communauté.
        </p>
        <p class="mt-4 rounded-xl border border-violet-100/80 bg-white/70 px-4 py-3 text-violet-950/85">
          <span class="font-semibold text-violet-950">Quand s’en servir&nbsp;?</span>
          Avant de publier sur le forum, ou si vous souhaitez limiter (ou enrichir) ce que vos camarades voient à côté de vos messages.
        </p>
        <ul class="mt-6 list-disc space-y-4 pl-5 text-violet-950/90">
          <li>
            <span class="font-semibold text-violet-950">Forum de la plateforme (global).</span>
            La carte d’auteur reste volontairement allégée pour tout le monde&nbsp;: pas de détail d’organigramme sur la carte. Les modérateurs disposent d’un panneau dédié pour l’identité réelle si besoin.
          </li>
          <li>
            <span class="font-semibold text-violet-950">Espace de votre communauté.</span>
            La carte peut afficher grade, unité et autres éléments selon les cases de la section <em>Forum &amp; fiche</em> de votre dossier. Cochez uniquement ce que vous acceptez de montrer.
          </li>
        </ul>
        <p class="mt-6 mb-0 text-violet-950/80">
          Depuis un sujet de forum, le raccourci vers le compte et le forum vous ramène aux préférences du compte et au bloc de visibilité du dossier.
        </p>
      </section>

      <section class="rounded-2xl border border-amber-200 bg-amber-50/40 p-6 shadow-sm sm:p-8">
        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-amber-800/80">Suivi</p>
        <h2 class="mt-2 text-lg font-black tracking-tight text-amber-950">Comprendre le score de complétude</h2>
        <p class="mt-4 text-base leading-relaxed text-amber-950/80">
          En haut de la page d’édition, un score indique dans quelle mesure le dossier est prêt pour la vie de la communauté. Il ne juge pas votre qualité de joueur&nbsp;: il signale les informations encore manquantes.
        </p>
        <p class="mt-4 rounded-xl border border-amber-100/80 bg-white/70 px-4 py-3 text-amber-950/85">
          <span class="font-semibold text-amber-950">Quand s’en servir&nbsp;?</span>
          Quand le bandeau «&nbsp;À compléter&nbsp;» apparaît, ou avant une validation d’encadrement / une campagne où un dossier complet est demandé.
        </p>
        <p class="mt-6 mb-0 leading-relaxed text-amber-950/90">
          Pour faire progresser le score, priorisez l’identité de personnage, une <strong>unité</strong>, un <strong>rôle</strong>, puis les champs de sécurité demandés (niveau d’habilitation, disponibilité, statut). Une <strong>formation certifiante</strong> peut parfois compenser une disponibilité numérique encore vide&nbsp;: le bandeau précise ce qui reste à faire.
        </p>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-slate-400">Aller plus loin</p>
        <h2 class="mt-2 text-lg font-black tracking-tight text-slate-900">Pages complémentaires</h2>
        <p class="mt-4 text-base leading-relaxed text-slate-600">
          Le dossier ne couvre pas tout. Ces écrans gèrent l’identité de connexion, le portrait et le détail opérationnel.
        </p>
        <ul class="mt-6 mb-0 list-none space-y-4 pl-0">
          <li class="rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3">
            <a class="font-semibold text-emerald-800 underline underline-offset-2" href="<?= htmlspecialchars(url('account/preferences')) ?>">Préférences du compte</a>
            <p class="mt-1 mb-0 text-slate-600">Nom affiché, indicatif et langue de l’interface.</p>
          </li>
          <li class="rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3">
            <a class="font-semibold text-emerald-800 underline underline-offset-2" href="<?= htmlspecialchars(url('account/portrait')) ?>">Portrait et médias</a>
            <p class="mt-1 mb-0 text-slate-600">Image utilisée comme avatar sur le forum.</p>
          </li>
          <li class="rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3">
            <a class="font-semibold text-emerald-800 underline underline-offset-2" href="<?= htmlspecialchars(url('personnel/me/edit')) ?>">Éditer le dossier</a>
            <p class="mt-1 mb-0 text-slate-600">Section <em>Forum &amp; fiche</em> pour choisir ce qui apparaît à côté de vos messages.</p>
          </li>
        </ul>
      </section>

      <section class="rounded-2xl border border-emerald-200 bg-emerald-50/40 p-6 shadow-sm sm:p-8">
        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-700/80">Récapitulatif</p>
        <h2 class="mt-2 text-lg font-black tracking-tight text-emerald-950">Parcours recommandé (première fois)</h2>
        <p class="mt-4 text-base leading-relaxed text-emerald-950/80">
          Suivez cet ordre pour éviter les allers-retours&nbsp;: identité d’abord, affectation ensuite, visibilité en dernier.
        </p>
        <ol class="mt-6 mb-0 list-decimal space-y-4 pl-5 text-emerald-950/90">
          <li>Renseignez l’identité de personnage (nom en mission) et le matricule.</li>
          <li>Sélectionnez votre unité dans l’organigramme et complétez votre rôle principal (éventuellement via un modèle).</li>
          <li>Ajustez les options <em>Forum &amp; fiche</em> selon ce que vous voulez montrer à la communauté.</li>
          <li>Complétez les champs de sécurité demandés pour lever les alertes de complétude.</li>
          <li>Enregistrez, puis relisez le résultat sur <a class="font-semibold text-emerald-900 underline underline-offset-2" href="<?= htmlspecialchars(url('personnel/me')) ?>">Ma fiche</a>.</li>
        </ol>
      </section>

    </article>
  </div>
</div>
