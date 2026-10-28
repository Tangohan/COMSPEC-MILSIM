<?php
declare(strict_types=1);

use App\Services\Community\TenantTypeConfig;

/** @var string|null $docsTenantType profil de la communauté courante (null hors communauté) */

$current = isset($docsTenantType) && $docsTenantType !== null ? TenantTypeConfig::normalizeType((string) $docsTenantType) : null;
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$isManager = function_exists('can') && (can('admin.organization') || can('admin.access'));
$levels = [
    'member' => ['Membre', 'Le niveau de tout nouveau venu. Il utilise le portail sans rien administrer.'],
    'hr' => ['RH', 'Tient les fiches et accueille les nouveaux. N’a pas accès aux paramètres ni aux niveaux d’accès.'],
    'manager' => ['Gestionnaire', 'Responsable de la communauté : paramètres, profil, niveaux d’accès et tous les outils.'],
];
?>
<div class="dprof">
    <div class="dprof__frame">
        <header class="dprof__hero">
            <p class="dprof__kicker"><a href="<?= $h(url('documentation')) ?>">Guide du portail</a> · Profils et accès</p>
            <h1 class="dprof__title">Profils de communauté et niveaux d’accès</h1>
            <p class="dprof__lead">Deux réglages décident de ce que chacun voit sur Athena : le <strong>profil</strong> de la communauté (quels outils existent) et le <strong>niveau d’accès</strong> de chaque membre (ce qu’il peut en faire).</p>
            <?php if ($current !== null): ?>
                <p class="dprof__current">Votre communauté utilise le profil <strong><?= $h(TenantTypeConfig::label($current)) ?></strong>.
                    <?php if ($isManager): ?><a href="<?= $h(url('back-office/organisation/parametres') . '?onglet=profil#profil') ?>">Changer de profil</a><?php endif; ?>
                </p>
            <?php endif; ?>
            <nav class="dprof__toc" aria-label="Sur cette page">
                <a href="#comparatif">Comparatif</a>
                <a href="#profils">Les trois profils</a>
                <a href="#acces">Niveaux d’accès</a>
                <?php if ($current !== null): ?><a href="#demarrer">Bien démarrer</a><?php endif; ?>
                <a href="#questions">Questions fréquentes</a>
            </nav>
        </header>

        <section id="comparatif" class="dprof__section">
            <h2 class="dprof__h2">1. Ce que contient chaque profil</h2>
            <?php $tpcCurrentType = $current; require base_path('views/partials/tenant_profile_compare.php'); ?>
        </section>

        <section id="profils" class="dprof__section">
            <h2 class="dprof__h2">2. Les trois profils</h2>
            <div class="dprof__cards">
                <?php foreach ([TenantTypeConfig::TYPE_ATAK, TenantTypeConfig::TYPE_EFFECTIFS, TenantTypeConfig::TYPE_FULL] as $t): ?>
                    <?php $g = TenantTypeConfig::profileGuide($t); ?>
                    <article class="dprof__card<?= $t === $current ? ' is-current' : '' ?>">
                        <h3 class="dprof__h3"><?= $h($g['label']) ?><?php if ($t === $current): ?> <span class="dprof__badge">Le vôtre</span><?php endif; ?></h3>
                        <p class="dprof__tagline"><?= $h($g['tagline']) ?></p>
                        <p class="dprof__label">Idéal pour</p>
                        <ul class="dprof__list">
                            <?php foreach ($g['ideal_for'] as $line): ?><li><?= $h($line) ?></li><?php endforeach; ?>
                        </ul>
                        <p class="dprof__label">À savoir</p>
                        <p class="dprof__text"><?= $h($g['not_for']) ?></p>
                        <p class="dprof__label">Pour démarrer</p>
                        <ol class="dprof__mini-steps">
                            <?php foreach ($g['steps'] as $st): ?><li><?= $h($st['title']) ?></li><?php endforeach; ?>
                        </ol>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section id="acces" class="dprof__section">
            <h2 class="dprof__h2">3. Les niveaux d’accès</h2>
            <p class="dprof__text">Chaque membre a un niveau : <strong>Membre</strong>, <strong>RH</strong> ou <strong>Gestionnaire</strong> (le Gestionnaire peut aussi créer des niveaux personnalisés). Un niveau ne donne jamais accès à un outil absent du profil : un Gestionnaire d’une communauté « Carte ATAK » ne voit pas le recrutement.</p>
            <div class="dprof__wrap">
                <table class="dprof__table">
                    <thead>
                        <tr>
                            <th scope="col">Niveau</th>
                            <?php foreach ([TenantTypeConfig::TYPE_ATAK, TenantTypeConfig::TYPE_EFFECTIFS, TenantTypeConfig::TYPE_FULL] as $t): ?>
                                <th scope="col"<?= $t === $current ? ' class="is-current"' : '' ?>><?= $h(TenantTypeConfig::label($t)) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($levels as $lk => [$lname, $ldesc]): ?>
                            <tr>
                                <th scope="row"><strong><?= $h($lname) ?></strong><span><?= $h($ldesc) ?></span></th>
                                <?php foreach ([TenantTypeConfig::TYPE_ATAK, TenantTypeConfig::TYPE_EFFECTIFS, TenantTypeConfig::TYPE_FULL] as $t): ?>
                                    <td<?= $t === $current ? ' class="is-current"' : '' ?>><?= $h(TenantTypeConfig::profileGuide($t)['levels'][$lk] ?? '') ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="dprof__hint">Les <strong>fonctions</strong> d’une fiche (chef de groupe, infirmier, pilote…) décrivent le rôle en jeu : elles ne donnent aucun droit sur le site. Seul le niveau d’accès compte.</p>
            <?php if ($isManager): ?>
                <p class="dprof__hint"><a href="<?= $h(url('back-office/access-management')) ?>">Gérer les niveaux d’accès des membres</a></p>
            <?php endif; ?>
        </section>

        <?php if ($current !== null): ?>
            <section id="demarrer" class="dprof__section">
                <h2 class="dprof__h2">4. Bien démarrer avec votre profil</h2>
                <?php $tpgType = $current; $tpgCollapsible = false; require base_path('views/partials/tenant_profile_getting_started.php'); ?>
            </section>
        <?php endif; ?>

        <section id="questions" class="dprof__section">
            <h2 class="dprof__h2"><?= $current !== null ? '5' : '4' ?>. Questions fréquentes</h2>
            <div class="dprof__faq">
                <details>
                    <summary>Que se passe-t-il quand je change de profil ?</summary>
                    <p>Les menus, le tableau de bord et les adresses des modules hors profil sont masqués pour tout le monde. Aucune donnée n’est supprimée : fiches, candidatures ou formations réapparaissent si vous revenez au profil Complet. Chaque niveau d’accès (Membre, RH, Gestionnaire) retrouve ses droits de base ; l’administration, les invitations et la modération sont retirées du niveau Membre, et le niveau RH perd l’accès complet à l’administration.</p>
                </details>
                <details>
                    <summary>Un membre tombe sur « Cette fonctionnalité n’est pas incluse dans le profil »</summary>
                    <p>Le lien mène à un module que votre profil masque (par exemple les formations dans un profil Carte ATAK). Un Gestionnaire peut passer au profil qui l’inclut depuis Paramètres → Profil.</p>
                </details>
                <details>
                    <summary>Qui peut changer le profil ?</summary>
                    <p>Uniquement un Gestionnaire de la communauté. Le changement s’applique immédiatement à tous les membres.</p>
                </details>
                <details>
                    <summary>Profil et formule, quelle différence ?</summary>
                    <p>Le profil choisit les outils qui ont du sens pour votre communauté. La formule (abonnement) fixe les plafonds et certaines options payantes. Un module doit être à la fois dans le profil et dans la formule pour apparaître.</p>
                </details>
                <details>
                    <summary>Le mod et les téléphones ATAK fonctionnent-ils avec tous les profils ?</summary>
                    <p>Oui : la carte ATAK, les équipes de feu, le temps de jeu et l’arsenal sont inclus dans les trois profils.</p>
                </details>
            </div>
        </section>
    </div>
</div>
<style>
.dprof { background: var(--ds-color-bg, #f8fafc); color: #0f172a; flex: 1 1 auto; }
.dprof__frame { max-width: 72rem; margin: 0 auto; padding: 1.5rem 1rem 3rem; display: grid; gap: 1.5rem; }
@media (min-width: 768px) { .dprof__frame { padding: 2rem 2rem 3.5rem; } }
.dprof__hero { border: 1px solid #e2e8f0; border-radius: 1rem; padding: 1.4rem 1.25rem; background: linear-gradient(160deg, #fff, rgba(5, 150, 105, 0.05)); }
.dprof__kicker { margin: 0 0 0.35rem; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.18em; text-transform: uppercase; color: #64748b; }
.dprof__kicker a { color: inherit; text-decoration: none; }
.dprof__title { margin: 0; font-size: 1.6rem; font-weight: 800; letter-spacing: -0.01em; }
.dprof__lead { margin: 0.5rem 0 0; max-width: 48rem; font-size: 0.95rem; line-height: 1.55; color: #475569; }
.dprof__current { margin: 0.8rem 0 0; font-size: 0.88rem; }
.dprof__current a, .dprof__hint a { margin-left: 0.4rem; color: #047857; font-weight: 700; }
.dprof__toc { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 1rem; }
.dprof__toc a { border: 1px solid #e2e8f0; border-radius: 999px; padding: 0.3rem 0.8rem; font-size: 0.8rem; font-weight: 600; color: #0f172a; text-decoration: none; background: #fff; }
.dprof__toc a:hover { border-color: #059669; color: #047857; }
.dprof__section { display: grid; gap: 0.8rem; scroll-margin-top: 5rem; }
.dprof__h2 { margin: 0; font-size: 1.2rem; font-weight: 800; }
.dprof__cards { display: grid; gap: 0.9rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 19rem), 1fr)); }
.dprof__card { border: 1px solid #e2e8f0; border-radius: 0.9rem; background: #fff; padding: 1rem 1.1rem; }
.dprof__card.is-current { border-color: #059669; box-shadow: 0 0 0 1px #059669; }
.dprof__h3 { margin: 0; font-size: 1.05rem; font-weight: 800; }
.dprof__badge { margin-left: 0.3rem; border-radius: 999px; background: #059669; color: #fff; padding: 0.05rem 0.5rem; font-size: 0.65rem; vertical-align: middle; }
.dprof__tagline { margin: 0.35rem 0 0; font-size: 0.88rem; color: #334155; line-height: 1.5; }
.dprof__label { margin: 0.85rem 0 0.25rem; font-size: 0.66rem; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; color: #64748b; }
.dprof__list, .dprof__mini-steps { margin: 0; padding-left: 1.1rem; display: grid; gap: 0.2rem; font-size: 0.83rem; color: #334155; }
.dprof__text { margin: 0; font-size: 0.86rem; line-height: 1.55; color: #334155; }
.dprof__hint { margin: 0; font-size: 0.82rem; color: #64748b; }
.dprof__wrap { overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 0.9rem; background: #fff; }
.dprof__table { width: 100%; min-width: 46rem; border-collapse: collapse; font-size: 0.83rem; }
.dprof__table th, .dprof__table td { padding: 0.7rem 0.8rem; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
.dprof__table tbody tr:last-child th, .dprof__table tbody tr:last-child td { border-bottom: 0; }
.dprof__table thead th { background: #f8fafc; font-size: 0.8rem; }
.dprof__table tbody th { width: 24%; font-weight: 500; }
.dprof__table tbody th strong { display: block; font-size: 0.9rem; }
.dprof__table tbody th span { display: block; margin-top: 0.15rem; font-size: 0.75rem; color: #64748b; }
.dprof__table .is-current { background: rgba(5, 150, 105, 0.06); }
.dprof__faq { display: grid; gap: 0.5rem; }
.dprof__faq details { border: 1px solid #e2e8f0; border-radius: 0.75rem; background: #fff; padding: 0.7rem 0.9rem; }
.dprof__faq summary { cursor: pointer; font-weight: 700; font-size: 0.9rem; }
.dprof__faq p { margin: 0.5rem 0 0; font-size: 0.86rem; line-height: 1.55; color: #334155; }
</style>
