<?php
declare(strict_types=1);

$baseUrl = rtrim(url(''), '/');
$firstLinkUrl = url('atak/premiere-liaison');
$atakUrl = url('atak');
$modUrl = url('atak/mod');
$guideUrl = url('atak/mod/guide');
$adminConfigUrl = url('admin/atak-config');
$canAdmin = function_exists('can') && (can('admin.system') || can('admin.organization') || can('admin.access'));
?>
<div class="max-w-3xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-2">Guide — Connexion ATAK / Overwatch</h1>
    <p class="text-sm text-slate-600 mb-6">
        Comment installer le pack, appairer votre compte et (pour l’équipe technique) générer la clé d’accès communauté.
    </p>

    <nav class="mb-8 pb-4 border-b border-slate-200 flex flex-wrap gap-x-3 gap-y-2 text-sm">
        <a href="<?= htmlspecialchars($firstLinkUrl, ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-800 hover:text-emerald-950">Première liaison (parcours guidé)</a>
        <span class="text-slate-300">·</span>
        <a href="<?= htmlspecialchars($atakUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-slate-600 hover:text-slate-900 font-medium">Carte ATAK</a>
        <span class="text-slate-300">·</span>
        <a href="<?= htmlspecialchars($modUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-slate-600 hover:text-slate-900 font-medium">Télécharger le pack</a>
        <span class="text-slate-300">·</span>
        <a href="<?= htmlspecialchars($guideUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-slate-600 hover:text-slate-900 font-medium">Guide du pack</a>
    </nav>

    <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 px-4 py-3 mb-8 text-sm text-emerald-950 leading-relaxed">
        <strong>Pour les membres :</strong> suivez d’abord
        <a class="underline font-semibold" href="<?= htmlspecialchars($firstLinkUrl, ENT_QUOTES, 'UTF-8') ?>">Première liaison</a>.
        Le chemin normal est <strong>compte + pack + code Appairer</strong>. Vous n’avez en général pas besoin de coller une clé technique dans Arma.
    </div>

    <div class="prose prose-slate max-w-none space-y-10">
        <section>
            <h2 class="text-lg font-bold text-slate-900 mb-3">1. Ce qu’il faut distinguer</h2>
            <ul class="list-disc pl-6 text-slate-700 space-y-2 text-sm">
                <li><strong>Code Appairer</strong> — généré sur le portail (carte → Appairer, ou Première liaison). À coller dans le téléphone Athena en jeu. Valable environ 30 minutes, usage unique. C’est le chemin recommandé pour les membres.</li>
                <li><strong>Clé d’accès communauté</strong> — générée une fois par un administrateur. Elle autorise la liaison jeu pour toute la communauté. Avec Appairer, elle est transmise automatiquement : les membres n’ont pas à la recopier.</li>
                <li><strong>Code terminal / téléphone</strong> — autre code, pour autoriser un appareil (menu Associer ce terminal). Ce n’est pas le code Appairer Overwatch.</li>
            </ul>
        </section>

        <section>
            <h2 class="text-lg font-bold text-slate-900 mb-3">2. Membres — se connecter en 4 étapes</h2>
            <ol class="list-decimal pl-6 text-slate-700 space-y-3 text-sm">
                <li>
                    <strong>Compte</strong> — Dans vos préférences : identifiant Steam + nom ou indicatif.
                </li>
                <li>
                    <strong>Pack</strong> —
                    <a href="<?= htmlspecialchars($modUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-slate-900 underline">Téléchargez Overwatch</a>,
                    activez-le après CBA, quittez Arma complètement après chaque mise à jour.
                </li>
                <li>
                    <strong>Appairer</strong> —
                    Sur la <a href="<?= htmlspecialchars($atakUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-slate-900 underline">carte ATAK</a>,
                    cliquez <strong>Appairer</strong> → <strong>Générer un code</strong> → Copier.
                    En jeu : téléphone → application <strong>Athena</strong> → coller uniquement le code (pas l’adresse du site) → Lier.
                    Si le compte est déjà reconnu, appuyez sur <strong>Entrer</strong>.
                </li>
                <li>
                    <strong>Contrôle</strong> — Revenez sur la carte : votre indicatif doit apparaître sous une minute (bougez un peu en jeu).
                </li>
            </ol>
            <p class="text-sm text-slate-600 mt-3">
                Variantes : bouton <strong>Steam</strong> ou connexion e-mail / mot de passe dans le même panneau Athena, si votre communauté les utilise.
            </p>
            <p class="mt-4">
                <a href="<?= htmlspecialchars($firstLinkUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg bg-emerald-800 text-white text-sm font-semibold px-4 py-2.5 hover:bg-emerald-700">
                    Ouvrir le parcours Première liaison
                </a>
            </p>
        </section>

        <section>
            <h2 class="text-lg font-bold text-slate-900 mb-3">3. Réglage avancé (si Appairer ne suffit pas)</h2>
            <p class="text-slate-700 text-sm mb-2">
                Dans le téléphone : <strong>Paramètres</strong> → rubrique <strong>Liaison au poste</strong> :
            </p>
            <ul class="list-disc pl-6 text-slate-700 space-y-1 text-sm">
                <li><strong>Adresse du portail</strong> — en général <code class="bg-slate-100 px-1 rounded text-xs"><?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?></code></li>
                <li><strong>Clé d’accès communauté</strong> — uniquement si un admin vous l’a communiquée (sinon laissez vide si déjà mémorisée)</li>
                <li><strong>Identifiant de communauté</strong> — utile si plusieurs communautés partagent la même adresse</li>
            </ul>
            <p class="text-slate-700 text-sm mt-2">
                Puis <strong>Enregistrer la liaison</strong>. Les mêmes valeurs existent aussi dans Options → Extensions (CBA).
            </p>
        </section>

        <section>
            <h2 class="text-lg font-bold text-slate-900 mb-3">4. Administrateurs — générer la clé d’accès</h2>
            <p class="text-slate-700 text-sm mb-2">
                Une clé communauté active est nécessaire pour que Appairer et la liaison jeu fonctionnent pleinement.
            </p>
            <ol class="list-decimal pl-6 text-slate-700 space-y-2 text-sm">
                <li>Ouvrez <strong>Configuration ATAK</strong> (back-office).</li>
                <li>Section <strong>Accès mod Overwatch</strong> → <strong>Générer une clé d’accès</strong> (ou Régénérer).</li>
                <li>Copiez immédiatement la clé affichée (elle ne sera plus montrée en entier ensuite).</li>
                <li>Publiez aussi le pack Overwatch pour les membres.</li>
                <li>Indiquez aux membres le parcours <a href="<?= htmlspecialchars($firstLinkUrl, ENT_QUOTES, 'UTF-8') ?>" class="underline">Première liaison</a> — pas de collage manuel de clé pour chacun.</li>
            </ol>
            <?php if ($canAdmin): ?>
            <p class="mt-4">
                <a href="<?= htmlspecialchars($adminConfigUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white text-slate-800 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50">
                    Ouvrir Configuration ATAK
                </a>
            </p>
            <?php endif; ?>
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                <strong>Attention :</strong> régénérer la clé invalide l’ancienne. Les opérateurs déjà liés via Appairer devront souvent générer un nouveau code et se reconnecter.
            </div>
        </section>

        <section>
            <h2 class="text-lg font-bold text-slate-900 mb-3">5. Dépannage rapide</h2>
            <ul class="list-disc pl-6 text-slate-700 space-y-2 text-sm">
                <li><strong>Code refusé / liaison impossible</strong> — générez un nouveau code, quittez Arma complètement, vérifiez Steam sur le compte.</li>
                <li><strong>Invisible sur la carte</strong> — canal poste ouvert (Athena prêt / Entrer), bougez un peu, attendez jusqu’à une minute.</li>
                <li><strong>Adresse collée dans le champ code</strong> — le champ code n’accepte que le code Appairer, jamais l’URL du site.</li>
                <li><strong>Pack ancien</strong> — rechargez le pack de la communauté, quittez Arma, relancez.</li>
            </ul>
        </section>
    </div>

    <p class="mt-10 text-sm text-slate-500">
        <a href="<?= htmlspecialchars($firstLinkUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-slate-700 hover:underline font-medium">Première liaison</a>
        ·
        <a href="<?= htmlspecialchars($atakUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-slate-700 hover:underline font-medium">Carte ATAK</a>
        ·
        <a href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>" class="text-slate-700 hover:underline font-medium">Tableau de bord</a>
    </p>
</div>
