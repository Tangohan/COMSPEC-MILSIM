<?php
declare(strict_types=1);

$hasMod = !empty($hasMod);
$modSizeLabel = (string) ($modSizeLabel ?? '');
$modUpdatedAt = (string) ($modUpdatedAt ?? '');
$modVersion = (string) ($modVersion ?? '');
$modDownloadUrl = $modDownloadUrl ?? null;
$atakSetupUrl = (string) ($atakSetupUrl ?? url('atak/setup'));
$atakUrl = (string) ($atakUrl ?? url('atak'));
$docsUrl = (string) ($docsUrl ?? url('documentation'));
$canManageMod = !empty($canManageMod);
$adminModUrl = (string) ($adminModUrl ?? url('admin/atak-mod'));
?>
<link href="<?= htmlspecialchars(asset_url('assets/css/atak-mod-download.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">

<div class="atak-mod-dl">
    <header class="atak-mod-dl__hero">
        <p class="atak-mod-dl__eyebrow">ATAK · Overwatch</p>
        <h1>Télécharger le pack Overwatch</h1>
        <p class="atak-mod-dl__lead">
            Installez ce pack côté Arma pour relier votre session à Athena : positions, marqueurs,
            diapositives de briefing et outils tactiques de votre communauté.
        </p>
    </header>

    <section class="atak-mod-dl__card">
        <h2>Pack de votre communauté</h2>
        <?php if ($hasMod && $modDownloadUrl): ?>
            <div class="atak-mod-dl__meta">
                <div class="atak-mod-dl__meta-item">
                    <span class="atak-mod-dl__meta-label">Statut</span>
                    <span class="atak-mod-dl__meta-value">Disponible</span>
                </div>
                <div class="atak-mod-dl__meta-item">
                    <span class="atak-mod-dl__meta-label">Taille</span>
                    <span class="atak-mod-dl__meta-value"><?= htmlspecialchars($modSizeLabel !== '' ? $modSizeLabel : '—', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="atak-mod-dl__meta-item">
                    <span class="atak-mod-dl__meta-label"><?= $modVersion !== '' ? 'Version' : 'Mise à jour' ?></span>
                    <span class="atak-mod-dl__meta-value">
                        <?php if ($modVersion !== ''): ?>
                            <?= htmlspecialchars($modVersion, ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($modUpdatedAt !== ''): ?>
                                <span style="display:block;margin-top:0.2rem;font-size:0.78rem;font-weight:600;color:#64748b;">
                                    <?= htmlspecialchars($modUpdatedAt, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            <?php endif; ?>
                        <?php else: ?>
                            <?= htmlspecialchars($modUpdatedAt !== '' ? $modUpdatedAt : '—', ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            <div class="atak-mod-dl__actions">
                <a class="atak-mod-dl__cta" href="<?= htmlspecialchars((string) $modDownloadUrl, ENT_QUOTES, 'UTF-8') ?>">
                    Télécharger le pack
                </a>
                <a class="atak-mod-dl__cta atak-mod-dl__cta--ghost" href="<?= htmlspecialchars($atakSetupUrl, ENT_QUOTES, 'UTF-8') ?>">
                    Guide d’installation
                </a>
                <a class="atak-mod-dl__cta atak-mod-dl__cta--ghost" href="<?= htmlspecialchars($atakUrl, ENT_QUOTES, 'UTF-8') ?>">
                    Ouvrir ATAK
                </a>
            </div>
        <?php else: ?>
            <div class="atak-mod-dl__empty" role="status">
                Aucun pack n’est encore publié pour votre communauté. Un administrateur doit le déposer
                depuis l’espace d’administration des ressources tactiques.
            </div>
            <div class="atak-mod-dl__actions">
                <a class="atak-mod-dl__cta atak-mod-dl__cta--ghost" href="<?= htmlspecialchars($atakUrl, ENT_QUOTES, 'UTF-8') ?>">Retour à ATAK</a>
                <?php if ($canManageMod): ?>
                    <a class="atak-mod-dl__cta" href="<?= htmlspecialchars($adminModUrl, ENT_QUOTES, 'UTF-8') ?>">Publier un pack</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="atak-mod-dl__card">
        <h2>Avant de lancer Arma</h2>
        <ul>
            <li>Placez le pack dans votre dossier de mods Arma et activez-le au lancement.</li>
            <li>Liez votre compte Athena (Steam déjà renseigné sur le profil, ou code généré depuis ATAK).</li>
            <li>Pour les écrans de briefing en mission, suivez le guide des diapositives Eden.</li>
        </ul>
        <div class="atak-mod-dl__actions" style="margin-top:1rem;">
            <a class="atak-mod-dl__cta atak-mod-dl__cta--ghost" href="<?= htmlspecialchars($docsUrl, ENT_QUOTES, 'UTF-8') ?>">
                Diapositives de briefing (guide)
            </a>
            <?php if ($canManageMod): ?>
                <a class="atak-mod-dl__cta atak-mod-dl__cta--ghost" href="<?= htmlspecialchars($adminModUrl, ENT_QUOTES, 'UTF-8') ?>">
                    Administration du pack
                </a>
            <?php endif; ?>
        </div>
    </section>
</div>
