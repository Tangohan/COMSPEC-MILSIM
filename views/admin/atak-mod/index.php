<?php
declare(strict_types=1);

$hasMod = !empty($hasMod);
$modMeta = is_array($modMeta ?? null) ? $modMeta : [];
$success = $success ?? null;
$error = $error ?? null;
$errors = is_array($errors ?? null) ? $errors : [];
$baseUrl = url('');
$memberDownloadUrl = (string) ($memberDownloadUrl ?? url('atak/mod'));
$sizeLabel = (string) ($modMeta['size_label'] ?? '—');
$updatedAt = (string) ($modMeta['updated_at'] ?? '—');
$version = (string) ($modMeta['version'] ?? '');
?>
<link href="<?= htmlspecialchars(asset_url('assets/css/back-office-atak-mod.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">

<div class="bo-atak-mod">
    <header class="bo-atak-mod__hero">
        <p class="bo-atak-mod__eyebrow">Ressources tactiques</p>
        <h1>Pack Overwatch</h1>
        <p class="bo-atak-mod__lead">
            Déposez ici l’archive du pack destiné aux joueurs. Une fois validée, elle devient téléchargeable
            depuis la page membre, et une annonce peut prévenir la communauté.
        </p>
    </header>

    <div class="bo-atak-mod__deck">
        <?php if ($success): ?>
            <div class="bo-atak-mod__flash bo-atak-mod__flash--ok" role="status"><?= htmlspecialchars((string) $success, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bo-atak-mod__flash bo-atak-mod__flash--err" role="alert"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($errors !== []): ?>
            <div class="bo-atak-mod__flash bo-atak-mod__flash--err" role="alert">
                <strong>Points à corriger</strong>
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="bo-atak-mod__kpis">
            <div class="bo-atak-mod__kpi">
                <span class="bo-atak-mod__kpi-label">Statut</span>
                <span class="bo-atak-mod__kpi-value <?= $hasMod ? 'bo-atak-mod__status-ok' : 'bo-atak-mod__status-off' ?>">
                    <?= $hasMod ? 'Disponible pour les membres' : 'Aucun pack publié' ?>
                </span>
            </div>
            <div class="bo-atak-mod__kpi">
                <span class="bo-atak-mod__kpi-label">Taille</span>
                <span class="bo-atak-mod__kpi-value"><?= htmlspecialchars($hasMod ? $sizeLabel : '—', ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="bo-atak-mod__kpi">
                <span class="bo-atak-mod__kpi-label"><?= $version !== '' ? 'Version / mise à jour' : 'Dernière mise à jour' ?></span>
                <span class="bo-atak-mod__kpi-value">
                    <?php if ($hasMod && $version !== ''): ?>
                        <?= htmlspecialchars($version, ENT_QUOTES, 'UTF-8') ?>
                        <span style="display:block;margin-top:0.25rem;font-size:0.8rem;font-weight:600;color:#64748b;">
                            <?= htmlspecialchars($updatedAt, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    <?php else: ?>
                        <?= htmlspecialchars($hasMod ? $updatedAt : '—', ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <?php if ($hasMod): ?>
            <section class="bo-atak-mod__panel">
                <h2>Pack actuellement proposé</h2>
                <p>
                    Les membres peuvent le récupérer depuis la page de téléchargement.
                    Déposer une nouvelle archive remplace automatiquement la version précédente.
                </p>
                <div class="bo-atak-mod__actions">
                    <a class="bo-atak-mod__btn bo-atak-mod__btn--ghost" href="<?= htmlspecialchars($memberDownloadUrl, ENT_QUOTES, 'UTF-8') ?>">
                        Voir la page membre
                    </a>
                    <form action="<?= htmlspecialchars($baseUrl . '/admin/atak-mod/delete', ENT_QUOTES, 'UTF-8') ?>" method="post" onsubmit="return confirm('Retirer le pack actuel ? Les membres ne pourront plus le télécharger.');">
                        <?= \App\Core\Csrf::field() ?>
                        <button type="submit" class="bo-atak-mod__btn bo-atak-mod__btn--danger">Retirer le pack</button>
                    </form>
                </div>
            </section>
        <?php endif; ?>

        <section class="bo-atak-mod__panel">
            <h2><?= $hasMod ? 'Remplacer le pack' : 'Déposer le pack' ?></h2>
            <p>
                Archive ZIP du pack Overwatch (50&nbsp;Mo max). Glissez le fichier dans la zone ci-dessous
                ou cliquez pour le sélectionner.
            </p>
            <form action="<?= htmlspecialchars($baseUrl . '/admin/atak-mod/upload', ENT_QUOTES, 'UTF-8') ?>" method="post" enctype="multipart/form-data" id="bo-atak-mod-upload-form">
                <?= \App\Core\Csrf::field() ?>
                <div class="bo-atak-mod__drop" id="bo-atak-mod-drop">
                    <input type="file" name="mod_zip" id="bo-atak-mod-file" accept=".zip,application/zip" required aria-label="Archive ZIP du pack Overwatch" />
                    <span class="bo-atak-mod__drop-title">Glisser-déposer l’archive ZIP</span>
                    <span class="bo-atak-mod__drop-hint">ou cliquer pour parcourir — format ZIP uniquement</span>
                </div>
                <p class="bo-atak-mod__file-name" id="bo-atak-mod-file-name" hidden></p>
                <div class="bo-atak-mod__actions">
                    <button type="submit" class="bo-atak-mod__btn bo-atak-mod__btn--primary">Vérifier et publier</button>
                    <a class="bo-atak-mod__btn bo-atak-mod__btn--ghost" href="<?= htmlspecialchars($baseUrl . '/admin/atak-config', ENT_QUOTES, 'UTF-8') ?>">Réglages ATAK</a>
                    <a class="bo-atak-mod__btn bo-atak-mod__btn--ghost" href="<?= htmlspecialchars($baseUrl . '/admin/atak-mod-blocks', ENT_QUOTES, 'UTF-8') ?>">Restrictions mod</a>
                    <a class="bo-atak-mod__btn bo-atak-mod__btn--ghost" href="<?= htmlspecialchars($baseUrl . '/atak', ENT_QUOTES, 'UTF-8') ?>">Ouvrir ATAK</a>
                    <a class="bo-atak-mod__btn bo-atak-mod__btn--ghost" href="<?= htmlspecialchars($baseUrl . '/admin', ENT_QUOTES, 'UTF-8') ?>">Retour admin</a>
                </div>
            </form>
        </section>
    </div>
</div>

<script>
(function () {
  var drop = document.getElementById('bo-atak-mod-drop');
  var input = document.getElementById('bo-atak-mod-file');
  var nameEl = document.getElementById('bo-atak-mod-file-name');
  if (!drop || !input || !nameEl) return;

  function showName() {
    var f = input.files && input.files[0];
    if (!f) {
      nameEl.hidden = true;
      nameEl.textContent = '';
      return;
    }
    nameEl.hidden = false;
    nameEl.textContent = 'Fichier sélectionné : ' + f.name;
  }

  input.addEventListener('change', showName);
  ['dragenter', 'dragover'].forEach(function (ev) {
    drop.addEventListener(ev, function (e) {
      e.preventDefault();
      drop.classList.add('is-dragover');
    });
  });
  ['dragleave', 'drop'].forEach(function (ev) {
    drop.addEventListener(ev, function (e) {
      e.preventDefault();
      drop.classList.remove('is-dragover');
    });
  });
  drop.addEventListener('drop', function (e) {
    var files = e.dataTransfer && e.dataTransfer.files;
    if (!files || !files.length) return;
    try {
      input.files = files;
    } catch (err) {}
    showName();
  });
})();
</script>
