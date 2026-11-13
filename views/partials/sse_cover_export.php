<?php
declare(strict_types=1);

/**
 * Générer une couverture sur Athena et la télécharger pour le jeu (SseCoverExportService::panel).
 *
 * @var array{history: list<array<string, mixed>>, remaining: int, limit: int, countries: list<array<string, mixed>>} $coverPanel
 * @var string $coverPostUrl      action du formulaire de génération
 * @var string $coverDownloadBase adresse de base des téléchargements (suivie de /{id}/telecharger)
 * @var string $coverCsrf         jeton CSRF
 * @var bool   $coverSelf         vrai : la couverture est pour la personne connectée
 * @var string $coverHeadingTag   (facultatif) h2 ou h3
 * @var bool   $coverHasPrimary   (facultatif) faux : pas d'identité principale, seule la personne fictive est proposée
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
// Niveau du titre : h2 en page seule, h3 dans une carte de la fiche membre.
$coverTag = in_array($coverHeadingTag ?? 'h2', ['h2', 'h3'], true) ? ($coverHeadingTag ?? 'h2') : 'h2';
?>
<section class="idm-cover" id="couverture" aria-labelledby="idm-cover-gen-title">
    <<?= $coverTag ?> id="idm-cover-gen-title" class="idm-cover__title"><?= $coverSelf ? 'Télécharger ma couverture pour le jeu' : 'Télécharger une couverture pour ce membre' ?></<?= $coverTag ?>>
    <p class="idm-sim"><strong>SIMULATION</strong> Le fichier contient l’identité Athena du membre (identité principale et ses papiers émis), ou une personne fictive tirée au hasard, jamais une personne réelle.</p>
    <p>Dans le jeu : téléphone › Ma couverture › Importer ma couverture › Synchroniser avec Athena. La couverture générée ici s’y récupère sans fichier ; le téléchargement ci-dessous reste possible pour jouer sans connexion.</p>
    <ol class="idm-cover__steps">
        <li>Choisissez le contenu, puis cliquez <b>Générer</b>. Avec l’identité Athena, le joueur a ses vrais papiers de jeu même si le serveur n’a pas reçu le paquet d’Athena.</li>
        <li>Cliquez <b>Télécharger</b> : vous obtenez un petit fichier .json.</li>
        <li>Déposez-le, sans changer son nom, dans le dossier <code>Documents\Arma 3 - COMSPEC</code> du PC qui lance Arma. Exemple : <code>C:\Users\Jake\Documents\Arma 3 - COMSPEC\comspec-couverture-CVR-1A2B3C4D.json</code></li>
        <li>En jeu, sur le téléphone ATAK : <b>Ma couverture › Importer ma couverture › Importer un fichier</b>. Aucune connexion à Athena n’est nécessaire en jeu ; Oublier l’import revient à l’identité d’avant.</li>
    </ol>
    <?php if ($coverPanel['countries'] === []): ?>
        <p>Aucun pays n’est actif dans cette communauté : les responsables doivent en activer un dans le registre des papiers.</p>
    <?php else: ?>
        <form method="post" action="<?= $h($coverPostUrl) ?>" enctype="multipart/form-data" class="idm-cover__form">
            <input type="hidden" name="_csrf_token" value="<?= $h($coverCsrf) ?>">
            <div class="idm-cover__grid">
                <label class="idm-cover__wide">Contenu
                    <select name="source">
                        <?php if ($coverHasPrimary ?? true): ?><option value="athena">Identité Athena (identité principale et ses papiers)</option><?php endif; ?>
                        <option value="random">Personne fictive tirée au hasard</option>
                    </select>
                </label>
                <label>Pays (personne fictive)
                    <select name="country">
                        <option value="">Au hasard</option>
                        <?php foreach ($coverPanel['countries'] as $c): ?>
                            <option value="<?= $h($c['code']) ?>"><?= $h($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Sexe
                    <select name="sex">
                        <option value="">Au hasard</option>
                        <option value="M">Masculin</option>
                        <option value="F">Féminin</option>
                    </select>
                </label>
                <label>Langue des textes
                    <select name="lang">
                        <option value="fr">Français</option>
                        <option value="en">English</option>
                    </select>
                </label>
            </div>
            <div class="idm-cover__photo">
                <span class="idm-cover__label">Photo d’identité fictive (personne fictive, facultative)</span>
                <label class="idm-drop" data-drop>
                    <input type="file" name="photo" accept="image/png,image/jpeg,image/webp" class="idm-drop__input" data-drop-input>
                    <span class="idm-drop__btn">Choisir une photo</span>
                    <span class="idm-drop__name" data-drop-name>ou glissez une image ici · PNG, JPG ou WebP, portrait de face</span>
                </label>
            </div>
            <div class="idm-cover__acts">
                <button type="submit" class="btn btn-primary idm-cover__go" <?= $coverPanel['remaining'] < 1 ? 'disabled' : '' ?>>Générer</button>
                <span class="idm-cover__quota"><?= $h((string) $coverPanel['remaining']) ?> / <?= $h((string) $coverPanel['limit']) ?> générations restantes sur 24 heures.</span>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($coverPanel['history'] !== []): ?>
        <?php $histTag = $coverTag === 'h3' ? 'h4' : 'h3'; ?><<?= $histTag ?> class="idm-cover__sub">Historique</<?= $histTag ?>>
        <ul class="idm-cover__history">
            <?php foreach ($coverPanel['history'] as $e): ?>
                <li>
                    <div>
                        <b translate="no"><?= $h($e['cover_name']) ?></b>
                        <span translate="no"><?= $h($e['ref']) ?> · <?= $h($e['country_code']) ?> · <?= $h(substr((string) $e['created_at'], 0, 16)) ?> UTC</span>
                        <?php if (!empty($e['downloaded_at'])): ?><span>· Téléchargée</span><?php endif; ?>
                    </div>
                    <a class="idm-cover__dl" href="<?= $h($coverDownloadBase . '/' . (int) $e['id'] . '/telecharger') ?>">Télécharger</a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<script src="<?= $h(asset_url('assets/js/member-papers.js')) ?>" defer></script>
