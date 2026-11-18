<?php

use App\Core\Csrf;
use App\Services\Modpack\ModpackVersion;

$e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$number = static fn ($value): string => number_format((int) $value, 0, ',', ' ');
$hasArchive = !empty($pack['archive_key']);
$published = $hasArchive && !empty($pack['published']);
$paused = (int) ($pack['max_downloads'] ?? 0) === 0;
$expected = $pack['expected_version'] ?? null;
$outdated = $published && $expected !== null && ModpackVersion::isOlder($pack['version'], $expected);
$status = !$published ? 'Non publié' : ($outdated ? 'Out of date' : ($expected === null ? 'Compatibilité à confirmer' : 'À jour'));
$recent = (int) ($activity['recent'] ?? 0);
$previous = (int) ($activity['previous'] ?? 0);
$delta = $recent - $previous;
$size = (int) ($pack['bytes'] ?? 0);
$date = $published ? new DateTimeImmutable($pack['published_at'], new DateTimeZone('UTC')) : null;
?>
<div class="mp" lang="fr" translate="no">
<div id="contenu" class="mp-wrap">
    <section class="mp-hero" aria-labelledby="pack-title">
        <img class="mp-cover" src="<?= $e(asset_url('assets/images/home/hero-2-1920.webp')) ?>" srcset="<?= $e(asset_url('assets/images/home/hero-2-960.webp')) ?> 960w, <?= $e(asset_url('assets/images/home/hero-2-1920.webp')) ?> 1817w" sizes="(max-width: 610px) 100vw, 1200px" alt="" fetchpriority="high">
        <div class="mp-hero-content">
            <p class="mp-kicker hi-kicker"><span></span> ARMA 3 / TÉLÉCHARGEMENT DIRECT</p>
            <h1 id="pack-title" class="hi-display">Modpack<br><em>COMSPEC</em></h1>
            <p>Le pack de mods COMSPEC pour Arma 3, en une seule archive à installer manuellement.</p>
            <div class="mp-actions">
                <?php if ($published && !$paused): ?>
                    <a class="mp-primary" href="<?= $e(url('modpacks/telecharger?archive=' . $pack['archive_key'])) ?>">
                        Télécharger le .RAR <span class="mp-arrow mp-arrow-down" aria-hidden="true"></span>
                    </a>
                <?php else: ?>
                    <span class="mp-disabled"><?= !$ready ? 'Service temporairement indisponible' : ($published ? 'Téléchargements en pause' : 'Archive à venir') ?></span>
                <?php endif; ?>
                <a class="mp-guide" href="#installation">Guide d’installation <span class="mp-arrow" aria-hidden="true"></span></a>
                <?php if ($admin && $ready): ?><a class="mp-guide" href="#administration">Administration <span class="mp-arrow" aria-hidden="true"></span></a><?php endif; ?>
            </div>
        </div>
    </section>

    <section class="mp-release" aria-label="Version et statistiques">
        <div class="mp-release-info">
            <p class="mp-label">Version distribuée</p>
            <p class="mp-version"><?= $published ? 'v' . $e($pack['version']) : '—' ?></p>
            <p class="mp-status<?= $outdated ? ' mp-warning' : ($published && $expected !== null ? ' mp-current' : '') ?>"><span aria-hidden="true"></span><?= $e($status) ?></p>
        </div>
        <div class="mp-stat"><p class="mp-label">Vues</p><strong><?= $ready ? $number($activity['views']) : '—' ?></strong><small>Total de la page</small></div>
        <div class="mp-stat"><p class="mp-label">Téléchargements</p><strong><?= $ready ? $number($activity['downloads']) : '—' ?></strong><small>Transferts lancés</small></div>
        <div class="mp-stat"><p class="mp-label">Tendance</p><strong><?= $ready ? $number($recent) : '—' ?></strong><small><?= !$ready || $delta === 0 ? 'Sur les 7 derniers jours' : ($delta > 0 ? '+' : '−') . $number(abs($delta)) . ' / 7 jours précédents' ?></small></div>
    </section>

    <?php if ($outdated): ?><p class="mp-alert">Cette archive fournit la version <?= $e($pack['version']) ?>. La version de référence est la <?= $e($expected) ?>.</p><?php endif; ?>

    <div class="mp-details">
        <section id="installation" class="mp-install">
            <h2>Installer le pack</h2>
            <ol>
                <li><div><strong>Extraire l’archive</strong><p>Décompressez le .RAR avec 7-Zip ou WinRAR dans un dossier dédié.</p></div></li>
                <li><div><strong>Ajouter les mods locaux</strong><p>Dans le lanceur Arma 3, ouvrez <b>Mods, puis Mod local</b> et sélectionnez chaque dossier de mod extrait.</p></div></li>
                <li><div><strong>Activer le pack</strong><p>Cochez les mods ajoutés et vérifiez la version demandée par votre serveur avant de rejoindre une partie.</p></div></li>
            </ol>
        </section>
        <section class="mp-file">
            <h2>Détails du fichier</h2>
            <dl>
                <div><dt>Format</dt><dd>RAR</dd></div>
                <div><dt>Taille</dt><dd><?= $published ? ($size >= 1073741824 ? number_format($size / 1073741824, 2, ',', ' ') . ' Gio' : ($size >= 1048576 ? number_format($size / 1048576, 1, ',', ' ') . ' Mio' : $number($size) . ' octets')) : '—' ?></dd></div>
                <div><dt>Publication</dt><dd><?= $date ? $e($date->setTimezone(new DateTimeZone('Europe/Paris'))->format('d/m/Y')) : '—' ?></dd></div>
                <div><dt>Version de référence</dt><dd><?= $e($expected ?? '—') ?></dd></div>
            </dl>
            <?php if ($published): ?><details><summary>Empreinte SHA-256</summary><code><?= $e($pack['sha256']) ?></code></details><?php endif; ?>
            <?php if ($published && !empty($pack['release_notes'])): ?><details open><summary>Notes de version</summary><p><?= nl2br($e($pack['release_notes'])) ?></p></details><?php endif; ?>
        </section>
    </div>

    <?php if ($admin && $ready): ?>
    <section id="administration" class="mp-admin">
        <h2>Administration</h2>
        <?php if (is_array($notice)): ?><p class="mp-alert" role="<?= $notice['error'] ? 'alert' : 'status' ?>"><?= $e($notice['text']) ?></p><?php endif; ?>
        <div class="mp-admin-grid">
            <form method="post" action="<?= $e(url('modpacks/publier')) ?>" data-modpack-upload
                  data-block-url="<?= $e(url('modpacks/bloc')) ?>"
                  data-block-size="<?= \App\Services\Modpack\ModpackUpload::MAX_BLOCK_BYTES ?>"
                  data-max-size="<?= \App\Services\Modpack\ModpackUpload::MAX_ARCHIVE_BYTES ?>">
                <input type="hidden" name="upload_token">
                <input type="hidden" name="archive_size">
                <?= Csrf::field() ?>
                <h3>Publier une version</h3>
                <label>Version<input name="version" placeholder="<?= $e(ModpackVersion::EXAMPLE) ?>" pattern="<?= $e(ModpackVersion::PATTERN) ?>"
                    maxlength="<?= ModpackVersion::MAX_LENGTH ?>" title="Exemple : <?= $e(ModpackVersion::EXAMPLE) ?>" required></label>
                <label>Archive .RAR<input type="file" data-archive accept=".rar,application/vnd.rar" required></label>
                <label>Notes de version<textarea name="notes" rows="4" maxlength="3000"></textarea></label>
                <?php if ($hasArchive): ?><p>La nouvelle archive remplace la précédente.</p><?php endif; ?>
                <progress class="mp-progress" max="100" value="0" aria-label="Progression de l’envoi" hidden></progress>
                <p data-upload-status role="status"></p>
                <noscript><p>Activez JavaScript pour envoyer l’archive par blocs.</p></noscript>
                <button class="mp-primary" type="submit" disabled>Publier le pack <span class="mp-arrow" aria-hidden="true"></span></button>
            </form>
            <form method="post" action="<?= $e(url('modpacks/reglages')) ?>">
                <?= Csrf::field() ?>
                <h3>Régler la diffusion</h3>
                <label>Version de référence<input name="expected_version" value="<?= $e($expected ?? '') ?>" placeholder="<?= $e(ModpackVersion::EXAMPLE) ?>" pattern="<?= $e(ModpackVersion::PATTERN) ?>"
                    maxlength="<?= ModpackVersion::MAX_LENGTH ?>" title="Exemple : <?= $e(ModpackVersion::EXAMPLE) ?>"></label>
                <p>Une archive plus ancienne que la version de référence est signalée « Out of date ».</p>
                <label>Transferts simultanés (0 pour mettre en pause)<input type="number" name="max_downloads" min="0" max="16" value="<?= $e($pack['max_downloads']) ?>" required></label>
                <label>Brider à partir de ce nombre de transferts<input type="number" name="throttle_at" min="1" max="16" value="<?= $e($pack['throttle_at']) ?>" required></label>
                <label>Débit maximal des nouveaux transferts bridés (Mio/s)<input type="number" name="throttle_mib" min="1" max="64" value="<?= $e($pack['throttle_mib']) ?>" required></label>
                <label class="mp-check"><input type="checkbox" name="published" value="1" <?= $published ? 'checked' : '' ?> <?= $hasArchive ? '' : 'disabled' ?>> Archive visible et téléchargeable</label>
                <button class="mp-primary" type="submit">Enregistrer les réglages</button>
            </form>
        </div>
    </section>
    <?php endif; ?>
</div>
</div>
<?php if ($admin && $ready): ?>
<script src="<?= $e(asset_url('assets/js/public-modpack-upload.js')) ?>" defer></script>
<?php endif; ?>
