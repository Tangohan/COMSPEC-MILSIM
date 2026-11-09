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
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<section class="idm-cover" id="couverture" aria-labelledby="idm-cover-gen-title">
    <h2 id="idm-cover-gen-title"><?= $coverSelf ? 'Générer ma couverture pour le jeu' : 'Générer une couverture pour ce membre' ?></h2>
    <p class="idm-sim"><strong>SIMULATION</strong> Personne fictive : nom, famille, métier, adresse, téléphone et papier sont tirés au hasard, jamais d’une personne réelle.</p>
    <ol class="idm-cover__steps">
        <li>Choisissez le pays, puis cliquez Générer. Une photo d’identité fictive est facultative.</li>
        <li>Cliquez Télécharger : vous obtenez un petit fichier .json.</li>
        <li>Déposez-le dans le dossier Documents\Arma 3 - COMSPEC du PC qui lance Arma.</li>
        <li>Dans le téléphone ATAK, ouvrez Ma couverture et cliquez Importer ma couverture. Aucune connexion à Athena n’est nécessaire en jeu.</li>
    </ol>
    <?php if ($coverPanel['countries'] === []): ?>
        <p>Aucun pays n’est actif dans cette communauté : les responsables doivent en activer un dans le registre des papiers.</p>
    <?php else: ?>
        <form method="post" action="<?= $h($coverPostUrl) ?>" enctype="multipart/form-data" class="idm-cover__form">
            <input type="hidden" name="_csrf_token" value="<?= $h($coverCsrf) ?>">
            <label>Pays
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
            <label>Photo d’identité fictive (facultative)
                <input type="file" name="photo" accept="image/png,image/jpeg,image/webp">
            </label>
            <button type="submit" class="btn btn-primary" <?= $coverPanel['remaining'] < 1 ? 'disabled' : '' ?>>Générer</button>
            <span class="muted"><?= $h((string) $coverPanel['remaining']) ?> / <?= $h((string) $coverPanel['limit']) ?> générations restantes sur 24 heures.</span>
        </form>
    <?php endif; ?>

    <?php if ($coverPanel['history'] !== []): ?>
        <h3>Historique</h3>
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
