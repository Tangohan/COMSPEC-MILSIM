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
    <<?= $coverTag ?> id="idm-cover-gen-title"><?= $coverSelf ? 'Télécharger ma couverture pour le jeu' : 'Télécharger une couverture pour ce membre' ?></<?= $coverTag ?>>
    <p class="idm-sim"><strong>SIMULATION</strong> Le fichier contient l’identité Athena du membre (identité principale et ses papiers émis), ou une personne fictive tirée au hasard, jamais une personne réelle.</p>
    <ol class="idm-cover__steps">
        <li>Choisissez le contenu, puis cliquez Générer. Avec l’identité Athena, le joueur a ses vrais papiers de jeu même si le serveur n’a pas reçu le paquet d’Athena.</li>
        <li>Cliquez Télécharger : vous obtenez un petit fichier .json.</li>
        <li>Déposez-le dans le dossier Documents\Arma 3 - COMSPEC du PC qui lance Arma. Exemple : C:\Users\Jake\Documents\Arma 3 - COMSPEC\comspec-couverture-CVR-1A2B3C4D.json (gardez le nom du fichier tel quel).</li>
        <li>En jeu, sur le téléphone ATAK : Ma couverture › Importer ma couverture. Aucune connexion à Athena n’est nécessaire en jeu ; Oublier l’import revient à l’identité d’avant.</li>
    </ol>
    <?php if ($coverPanel['countries'] === []): ?>
        <p>Aucun pays n’est actif dans cette communauté : les responsables doivent en activer un dans le registre des papiers.</p>
    <?php else: ?>
        <form method="post" action="<?= $h($coverPostUrl) ?>" enctype="multipart/form-data" class="idm-cover__form">
            <input type="hidden" name="_csrf_token" value="<?= $h($coverCsrf) ?>">
            <label>Contenu
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
            <label>Photo d’identité fictive (personne fictive, facultative)
                <input type="file" name="photo" accept="image/png,image/jpeg,image/webp">
            </label>
            <button type="submit" class="btn btn-primary" <?= $coverPanel['remaining'] < 1 ? 'disabled' : '' ?>>Générer</button>
            <span class="muted"><?= $h((string) $coverPanel['remaining']) ?> / <?= $h((string) $coverPanel['limit']) ?> générations restantes sur 24 heures.</span>
        </form>
    <?php endif; ?>

    <?php if ($coverPanel['history'] !== []): ?>
        <?php $histTag = $coverTag === 'h3' ? 'h4' : 'h3'; ?><<?= $histTag ?>>Historique</<?= $histTag ?>>
        <ul class="idm-cover__history">
            <?php foreach ($coverPanel['history'] as $e): ?>
                <li>
                    <b translate="no"><?= $h($e['cover_name']) ?></b>
                    <span translate="no"><?= $h($e['ref']) ?> · <?= $h($e['country_code']) ?> · <?= $h(substr((string) $e['created_at'], 0, 16)) ?> UTC</span>
                    <?php if (!empty($e['downloaded_at'])): ?><span>Téléchargée</span><?php endif; ?>
                    <a class="link" href="<?= $h($coverDownloadBase . '/' . (int) $e['id'] . '/telecharger') ?>">Télécharger</a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
