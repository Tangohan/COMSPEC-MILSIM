<?php
declare(strict_types=1);
$base = rtrim(url(''), '/');
$courses = $courses ?? [];
$legacyModules = $legacyModules ?? [];
$totalModules = count($courses) + count($legacyModules);
$heroImage = 'https://www.mil.be/media/ixoe4s5w/2026-04-02-jtac-0027.jpg?v=1dccc0cbcdee3b0&rmode=crop&width=1600&height=1000&format=webp&quality=90';
$courseCover = static function (array $course) use ($heroImage): string {
    foreach (['cover_url', 'image_url', 'thumbnail_url'] as $key) {
        $value = trim((string) ($course[$key] ?? ''));
        if ($value !== '') return $value;
    }
    return $heroImage;
};
$progress = 0;
foreach ($catalogueSidebarEnrollments ?? [] as $enrollment) {
    $progress = max($progress, (int) ($enrollment['progress_percent'] ?? 0));
}
?>
<!doctype html>
<html lang="fr" data-lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#07110f">
  <title>Athena Academy — JTAC Training</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('assets/css/athena-academy.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<div class="academy" data-academy>
  <div class="academy-release" role="status">
    <span>PRE-RELEASE / ACCÈS ANTICIPÉ</span>
    <p data-fr="Cette version de l’Academy est en cours de validation. Les contenus et résultats peuvent évoluer." data-en="This Academy release is being validated. Content and results may change.">Cette version de l’Academy est en cours de validation. Les contenus et résultats peuvent évoluer.</p>
  </div>
  <header class="academy-header">
    <a class="academy-brand" href="<?= htmlspecialchars($base) ?>/formations" aria-label="Athena Academy">
      <span class="academy-brand__mark">A</span><span>ATHENA <b>ACADEMY</b></span>
    </a>
    <nav class="academy-header__nav" aria-label="Navigation principale">
      <a href="#programmes" data-fr="Cursus" data-en="Curricula">Cursus</a>
      <a href="#method" data-fr="Méthode" data-en="Method">Méthode</a>
      <a href="<?= htmlspecialchars($base) ?>/formations/mes-formations" data-fr="Mon dossier" data-en="My record">Mon dossier</a>
    </nav>
    <div class="academy-header__actions">
      <button class="lang-switch" type="button" data-language aria-label="Switch to English"><span>FR</span><i></i><span>EN</span></button>
      <a class="academy-avatar" href="<?= htmlspecialchars($base) ?>/account" aria-label="Compte">OP</a>
    </div>
  </header>

  <aside class="academy-rail" aria-label="Espaces Academy">
    <a class="is-active" href="#top"><span>01</span><b data-fr="Accueil" data-en="Home">Accueil</b></a>
    <a href="#programmes"><span>02</span><b data-fr="Catalogue" data-en="Catalogue">Catalogue</b></a>
    <a href="<?= htmlspecialchars($base) ?>/formations/mes-formations"><span>03</span><b data-fr="Mes formations" data-en="My training">Mes formations</b></a>
    <a href="#classroom"><span>04</span><b data-fr="Salle de cours" data-en="Classroom">Salle de cours</b></a>
    <a href="#laboratory"><span>05</span><b data-fr="Laboratoire" data-en="Laboratory">Laboratoire</b></a>
    <a href="#simulators"><span>06</span><b data-fr="Simulateurs" data-en="Simulators">Simulateurs</b></a>
    <a href="#exams"><span>07</span><b data-fr="Examens" data-en="Exams">Examens</b></a>
    <a href="<?= htmlspecialchars($base) ?>/documents"><span>08</span><b data-fr="Bibliothèque" data-en="Library">Bibliothèque</b></a>
    <a href="<?= htmlspecialchars($base) ?>/formations/competences"><span>09</span><b data-fr="Dossier stagiaire" data-en="Trainee record">Dossier stagiaire</b></a>
    <a href="<?= htmlspecialchars($base) ?>/formation"><span>10</span><b data-fr="Espace instructeur" data-en="Instructor area">Espace instructeur</b></a>
  </aside>

  <main id="top">
    <section class="academy-hero" style="--hero:url('<?= htmlspecialchars($heroImage, ENT_QUOTES, 'UTF-8') ?>')">
      <div class="academy-hero__content">
        <p class="eyebrow"><span></span><span data-fr="ACADÉMIE NUMÉRIQUE · JTAC" data-en="DIGITAL ACADEMY · JTAC">ACADÉMIE NUMÉRIQUE · JTAC</span></p>
        <h1><span data-fr="DIRIGER" data-en="CONTROL">DIRIGER</span><br><em data-fr="DEPUIS LE SOL." data-en="FROM THE GROUND.">DEPUIS LE SOL.</em></h1>
        <p class="academy-hero__lead" data-fr="De la théorie à la validation opérationnelle : progressez par compétences, entraînez vos communications et prenez des décisions sûres sous pression." data-en="From theory to operational validation: build skills, train your communications and make safe decisions under pressure.">De la théorie à la validation opérationnelle : progressez par compétences, entraînez vos communications et prenez des décisions sûres sous pression.</p>
        <div class="academy-hero__buttons">
          <a class="btn btn--signal" href="#programmes" data-fr="Explorer les cursus" data-en="Explore curricula">Explorer les cursus</a>
          <a class="btn btn--glass" href="<?= htmlspecialchars($base) ?>/formations/mes-formations" data-fr="Reprendre ma formation" data-en="Resume training">Reprendre ma formation</a>
        </div>
      </div>
      <div class="academy-hero__quote"><span>COMMS / 01</span><blockquote>“NO COMMS,<br>NO BOMBS.”</blockquote><small data-fr="Principe fondamental JTAC" data-en="Core JTAC principle">Principe fondamental JTAC</small></div>
      <div class="academy-hero__stats">
        <div><strong>13</strong><span data-fr="semaines intensives" data-en="intensive weeks">semaines intensives</span></div>
        <div><strong><?= (int) $totalModules ?></strong><span data-fr="cursus disponibles" data-en="available curricula">cursus disponibles</span></div>
        <div><strong>24/7</strong><span data-fr="accès au campus" data-en="campus access">accès au campus</span></div>
      </div>
    </section>

    <section class="academy-dashboard" aria-labelledby="dashboard-title">
      <div class="section-heading"><p class="eyebrow"><span></span><span data-fr="VOTRE SITUATION" data-en="YOUR STATUS">VOTRE SITUATION</span></p><h2 id="dashboard-title" data-fr="Tableau de bord" data-en="Dashboard">Tableau de bord</h2></div>
      <div class="dashboard-grid">
        <article class="progress-card"><div class="progress-ring" style="--progress:<?= $progress ?>"><b><?= $progress ?>%</b></div><div><span class="card-label" data-fr="PROGRESSION GÉNÉRALE" data-en="OVERALL PROGRESS">PROGRESSION GÉNÉRALE</span><h3 data-fr="Votre parcours opérationnel" data-en="Your operational path">Votre parcours opérationnel</h3><p data-fr="Reprenez exactement là où vous vous êtes arrêté." data-en="Resume exactly where you left off.">Reprenez exactement là où vous vous êtes arrêté.</p><a href="<?= htmlspecialchars($base) ?>/formations/mes-formations" data-fr="Ouvrir mon dossier →" data-en="Open my record →">Ouvrir mon dossier →</a></div></article>
        <article class="status-card"><span class="card-label" data-fr="PROCHAINE ÉTAPE" data-en="NEXT STEP">PROCHAINE ÉTAPE</span><b>CAS / 9-LINE</b><p data-fr="Communications et transmission structurée" data-en="Communications and structured transmission">Communications et transmission structurée</p><span class="status-chip" data-fr="À commencer" data-en="Ready to start">À commencer</span></article>
        <article class="status-card status-card--dark"><span class="card-label" data-fr="DERNIER SCORE" data-en="LATEST SCORE">DERNIER SCORE</span><b>— / 100</b><p data-fr="Aucune évaluation enregistrée" data-en="No assessment recorded">Aucune évaluation enregistrée</p><a href="#exams" data-fr="Voir les évaluations" data-en="View assessments">Voir les évaluations</a></article>
      </div>
    </section>

    <section class="academy-method" id="method">
      <div class="section-heading section-heading--light"><p class="eyebrow"><span></span><span data-fr="DOCTRINE PÉDAGOGIQUE" data-en="LEARNING DOCTRINE">DOCTRINE PÉDAGOGIQUE</span></p><h2 data-fr="Savoir. Faire. Décider." data-en="Know. Act. Decide.">Savoir. Faire. Décider.</h2><p data-fr="Une progression continue, de l’acquisition des fondamentaux au débriefing instructeur." data-en="A continuous progression from fundamentals to instructor-led debriefing.">Une progression continue, de l’acquisition des fondamentaux au débriefing instructeur.</p></div>
      <ol class="method-flow">
        <?php $steps = [['01','Cours théorique','Theory'],['02','Démonstration','Demonstration'],['03','Exercice guidé','Guided exercise'],['04','Simulation autonome','Independent simulation'],['05','Évaluation','Assessment'],['06','Débriefing','Debrief'],['07','Validation des acquis','Skills validation']]; foreach ($steps as [$n,$fr,$en]): ?>
        <li><span><?= $n ?></span><b data-fr="<?= $fr ?>" data-en="<?= $en ?>"><?= $fr ?></b></li>
        <?php endforeach; ?>
      </ol>
    </section>

    <section class="academy-programmes" id="programmes">
      <div class="section-heading"><p class="eyebrow"><span></span><span data-fr="CATALOGUE" data-en="CATALOGUE">CATALOGUE</span></p><h2 data-fr="Cursus disponibles" data-en="Available curricula">Cursus disponibles</h2><p data-fr="Des parcours structurés en chapitres, ressources, exercices et évaluations." data-en="Curricula structured into chapters, resources, exercises and assessments.">Des parcours structurés en chapitres, ressources, exercices et évaluations.</p></div>
      <div class="course-grid">
        <?php if ($courses === []): ?>
        <article class="course-card course-card--featured"><img src="<?= htmlspecialchars($heroImage, ENT_QUOTES, 'UTF-8') ?>" alt="JTAC en entraînement"><div class="course-card__body"><span>JTAC · FOUNDATION</span><h3 data-fr="Fondamentaux du contrôle aérien avancé" data-en="Joint terminal attack control fundamentals">Fondamentaux du contrôle aérien avancé</h3><p data-fr="Cursus de prévisualisation — les contenus seront publiés prochainement par vos instructeurs." data-en="Preview curriculum — content will be published soon by your instructors.">Cursus de prévisualisation — les contenus seront publiés prochainement par vos instructeurs.</p><small>PRE-RELEASE</small></div></article>
        <?php else: foreach (array_slice($courses, 0, 6) as $i => $course): $slug=(string)($course['slug'] ?? ''); ?>
        <a class="course-card <?= $i === 0 ? 'course-card--featured' : '' ?>" href="<?= htmlspecialchars($base . '/formations/' . rawurlencode($slug), ENT_QUOTES, 'UTF-8') ?>">
          <img src="<?= htmlspecialchars($courseCover($course), ENT_QUOTES, 'UTF-8') ?>" alt="">
          <div class="course-card__body"><span><?= htmlspecialchars(strtoupper((string)($course['category'] ?? 'CURRICULUM'))) ?></span><h3><?= htmlspecialchars((string)($course['title'] ?? 'Formation')) ?></h3><p><?= htmlspecialchars(mb_strimwidth(strip_tags((string)($course['description'] ?? '')), 0, 150, '…')) ?></p><small><?= max(0,(int)($course['estimated_minutes'] ?? 0)) ?> MIN · <?= htmlspecialchars(strtoupper((string)($course['level'] ?? 'TOUS NIVEAUX'))) ?></small></div>
        </a>
        <?php endforeach; endif; ?>
      </div>
    </section>

    <section class="academy-spaces">
      <?php $spaces=[['classroom','Salle de cours','Classroom','Diapositives, doctrine, schémas interactifs et audio.','Slides, doctrine, interactive diagrams and audio.'],['laboratory','Laboratoire','Laboratory','Exercices guidés avec objectifs et feedback immédiat.','Guided exercises with objectives and immediate feedback.'],['simulators','Simulateurs','Simulators','Scénarios fictifs indépendants, sans impact opérationnel.','Independent fictional scenarios with no operational impact.'],['exams','Examens','Exams','Évaluations chronométrées, notation et débriefing.','Timed assessments, scoring and debriefing.']]; foreach($spaces as [$id,$fr,$en,$dfr,$den]): ?>
      <article id="<?= $id ?>"><span>+</span><h3 data-fr="<?= $fr ?>" data-en="<?= $en ?>"><?= $fr ?></h3><p data-fr="<?= $dfr ?>" data-en="<?= $den ?>"><?= $dfr ?></p><button type="button" disabled data-fr="Bientôt disponible" data-en="Coming soon">Bientôt disponible</button></article>
      <?php endforeach; ?>
    </section>

    <section class="academy-story">
      <div><p class="eyebrow"><span></span><span data-fr="RINGO STRIKE · ELSENBORN" data-en="RINGO STRIKE · ELSENBORN">RINGO STRIKE · ELSENBORN</span></p><h2 data-fr="Treize semaines pour relier l’air et la terre." data-en="Thirteen weeks to connect air and ground.">Treize semaines pour relier l’air et la terre.</h2><p data-fr="Les JTAC dirigent avions de combat, hélicoptères et UAV selon des procédures communes. Communication, analyse tactique, gestion du stress et sécurité sont évaluées en permanence." data-en="JTACs control combat aircraft, helicopters and UAVs under shared procedures. Communication, tactical analysis, stress management and safety are continuously assessed.">Les JTAC dirigent avions de combat, hélicoptères et UAV selon des procédures communes. Communication, analyse tactique, gestion du stress et sécurité sont évaluées en permanence.</p><a href="https://www.mil.be/fr/news/guider-le-ciel-depuis-le-sol-les-jtac-en-action-lors-d-un-entrainement-conjoint-et-multinational/" target="_blank" rel="noopener" data-fr="Lire le reportage de la Défense ↗" data-en="Read the Defence story ↗">Lire le reportage de la Défense ↗</a></div>
      <img src="<?= htmlspecialchars($heroImage, ENT_QUOTES, 'UTF-8') ?>" alt="Stagiaire JTAC durant un exercice">
    </section>
  </main>
  <footer><span>ATHENA ACADEMY · PRE-RELEASE</span><p data-fr="Plateforme d’entraînement numérique — usage pédagogique uniquement." data-en="Digital training platform — educational use only.">Plateforme d’entraînement numérique — usage pédagogique uniquement.</p><a href="<?= htmlspecialchars($base) ?>/dashboard" data-fr="Retour au portail" data-en="Back to portal">Retour au portail</a></footer>
</div>
<script src="<?= htmlspecialchars(asset_url('assets/js/athena-academy.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
</body></html>
