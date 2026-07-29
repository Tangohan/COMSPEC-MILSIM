<?php
declare(strict_types=1);
$owFormationRevision = (int) ($owFormationRevision ?? 1);
$owPackVersion = (string) ($owPackVersion ?? '1.3.0');
$atakModUrl = (string) ($atakModUrl ?? url('atak/mod'));
$atakGuideUrl = (string) ($atakGuideUrl ?? url('atak/mod/guide'));
$atakSetupUrl = (string) ($atakSetupUrl ?? url('atak/setup'));
$atakUrl = (string) ($atakUrl ?? url('atak'));

$modules = [
    [
        'id' => 'm1',
        'title' => 'Installation & première liaison',
        'duration' => '25 min',
        'level' => 'Débutant',
        'objectives' => [
            'Télécharger et installer le pack Overwatch',
            'Activer CBA et Overwatch dans le bon ordre',
            'Lier votre compte Athena',
            'Confirmer le badge « En liaison »',
        ],
    ],
    [
        'id' => 'm2',
        'title' => 'Hub, messagerie & navigation',
        'duration' => '30 min',
        'level' => 'Débutant',
        'objectives' => [
            'Ouvrir le hub sans chercher le raccourci',
            'Lire l’état liaison et la version du pack',
            'Envoyer et recevoir un message test',
            'Consulter ordres et briefing',
        ],
    ],
    [
        'id' => 'm3',
        'title' => 'Carte, marqueurs & Tacmap',
        'duration' => '35 min',
        'level' => 'Intermédiaire',
        'objectives' => [
            'Poser un marqueur lisible pour le TOC',
            'Vérifier la remontée sur Tacmap',
            'Utiliser un POI ACE (LZ ou objectif)',
            'Comprendre les délais de synchronisation',
        ],
    ],
    [
        'id' => 'm4',
        'title' => 'Rapports tactiques & demandes',
        'duration' => '40 min',
        'level' => 'Intermédiaire',
        'objectives' => [
            'Rédiger un SPOTREP complet',
            'Envoyer un CONTACT sous pression simulée',
            'Demander MEDEVAC ou renfort',
            'Adapter le rapport si liaison dégradée',
        ],
    ],
    [
        'id' => 'm5',
        'title' => 'Réalisme liaison (1.3.0)',
        'duration' => '45 min',
        'level' => 'Avancé',
        'objectives' => [
            'Identifier chaque overlay du hub',
            'Réagir à une zone sans couverture',
            'Rallumer ou réparer le terminal via ACE',
            'Comprendre la reprise après crash',
        ],
    ],
    [
        'id' => 'm6',
        'title' => 'Chef de mission & zones Zeus',
        'duration' => '50 min',
        'level' => 'Encadrement',
        'objectives' => [
            'Valider la checklist serveur',
            'Placer modules Eden roleplay',
            'Synchroniser zones portail si activé',
            'Rédiger un briefing OP Overwatch',
        ],
    ],
    [
        'id' => 'm7',
        'title' => 'Renseignement interpersonnel & portail classifié',
        'duration' => '25 min',
        'level' => 'Opérateur',
        'objectives' => [
            'Enregistrer une personne depuis le terminal terrain',
            'Consulter la fiche au TOC et ouvrir le portail classifié',
            'Comprendre codes d’accès et classification',
        ],
    ],
];
?>
<div class="site-docs ow-mod-docs ow-formation">
    <div class="site-docs__shell ow-formation__shell">
        <header class="site-docs__hero">
            <p class="ow-mod-docs__eyebrow">Formation · pack <?= htmlspecialchars($owPackVersion, ENT_QUOTES, 'UTF-8') ?> · révision <?= $owFormationRevision ?></p>
            <h1>Parcours opérateur Overwatch</h1>
            <p>
                Sept modules progressifs pour maîtriser le terminal tactique en conditions réelles.
                Cochez chaque étape au fur et à mesure : votre progression est enregistrée localement sur cet appareil.
            </p>
            <div class="ow-mod-docs__hero-links">
                <a href="<?= htmlspecialchars($atakGuideUrl, ENT_QUOTES, 'UTF-8') ?>">Guide complet</a>
                <a href="<?= htmlspecialchars($atakModUrl, ENT_QUOTES, 'UTF-8') ?>">Télécharger le pack</a>
                <a href="<?= htmlspecialchars($atakSetupUrl, ENT_QUOTES, 'UTF-8') ?>">Installation pas à pas</a>
            </div>
            <div class="ow-formation__progress" aria-live="polite">
                <div class="ow-formation__progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                    <span class="ow-formation__progress-fill" id="ow-formation-progress-fill"></span>
                </div>
                <p class="ow-formation__progress-label" id="ow-formation-progress-label">0 % — aucune étape validée</p>
            </div>
        </header>

        <aside class="site-docs__sidebar ow-formation__sidebar" aria-label="Modules">
            <p class="site-docs__sidebar-title">Modules</p>
            <ul class="site-docs__toc ow-formation__module-nav">
                <?php foreach ($modules as $i => $mod): ?>
                <li>
                    <a href="#module-<?= htmlspecialchars($mod['id'], ENT_QUOTES, 'UTF-8') ?>" data-module-nav="<?= htmlspecialchars($mod['id'], ENT_QUOTES, 'UTF-8') ?>">
                        <span class="ow-formation__module-num"><?= $i + 1 ?></span>
                        <?= htmlspecialchars($mod['title'], ENT_QUOTES, 'UTF-8') ?>
                        <span class="ow-formation__module-badge" data-module-badge="<?= htmlspecialchars($mod['id'], ENT_QUOTES, 'UTF-8') ?>" hidden>Validé</span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="ow-formation__reset" id="ow-formation-reset">Réinitialiser ma progression</button>
        </aside>

        <div class="site-docs__main ow-formation__main">
            <?php require __DIR__ . '/_modules.php'; ?>

            <section class="site-docs__section ow-formation__final" id="formation-final">
                <h2>Validation du parcours</h2>
                <p class="site-docs__lead">
                    Lorsque les sept modules sont cochés, vous disposez des réflexes minimums pour une OP Overwatch.
                    Faites valider votre parcours par un référent ATAK de votre unité si votre organisation l’exige.
                </p>
                <div class="site-docs__callout site-docs__callout--tip" id="ow-formation-complete-msg" hidden>
                    <strong>Parcours terminé.</strong> Tous les modules sont validés. Consultez le
                    <a href="<?= htmlspecialchars($atakGuideUrl, ENT_QUOTES, 'UTF-8') ?>">guide complet</a>
                    pour approfondir un chapitre ou préparer une mission en Zeus.
                </div>
            </section>
        </div>
    </div>
</div>
<script src="<?= htmlspecialchars(asset_url('assets/js/overwatch-mod-formation.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
