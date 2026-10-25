<?php
declare(strict_types=1);
/** Bureau des effectifs : fenêtre « Importer un tableur » (CSV → fiches sans compte). */
?>
    <dialog class="bo-eff-import" id="bo-eff-import" aria-labelledby="bo-eff-import-title">
        <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('importer'), ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data">
            <?= \App\Core\Csrf::field() ?>
            <h2 id="bo-eff-import-title">Importer un tableur</h2>
            <p>Une ligne par membre, la première ligne donne les colonnes. Chaque ligne devient une fiche sans compte.</p>
            <ul>
                <li>Colonnes reconnues : Prénom, Nom (ou Nom affiché), Indicatif, Grade, Affectation, Steam ID.</li>
                <li>Le fichier « Exporter » se réimporte tel quel : retouchez-le dans Excel, ajoutez des lignes, enregistrez en CSV.</li>
                <li>Un membre déjà présent (même nom ou même indicatif) n’est pas créé deux fois.</li>
            </ul>
            <label class="bo-eff-import__file">
                <span>Fichier CSV</span>
                <input type="file" name="roster_file" accept=".csv,text/csv" required>
            </label>
            <div class="bo-eff-import__actions">
                <a class="ath-btn" href="<?= htmlspecialchars(effectifs_workspace_url('modele-tableur'), ENT_QUOTES, 'UTF-8') ?>">Télécharger le modèle</a>
                <span class="bo-eff-import__spacer"></span>
                <button type="button" class="ath-btn" data-eff-import-close>Annuler</button>
                <button type="submit" class="ath-btn ath-btn--solid">Importer</button>
            </div>
        </form>
    </dialog>
    <style>
    .bo-eff-bureau{margin:0 0 14px;padding:10px 14px;border:1px solid color-mix(in srgb, var(--ath-accent, #0d9f6e) 35%, transparent);border-radius:10px;background:color-mix(in srgb, var(--ath-accent, #0d9f6e) 7%, transparent);font-size:13px;line-height:1.5}
    .bo-eff-bureau p{margin:0}
    .bo-eff-import{max-width:560px;width:calc(100% - 32px);border:1px solid var(--ath-line, #d8ded9);border-radius:14px;padding:22px;background:var(--ath-surface, #fff);color:inherit}
    .bo-eff-import::backdrop{background:rgba(8,12,10,.55)}
    .bo-eff-import h2{margin:0 0 8px;font-size:18px}
    .bo-eff-import p,.bo-eff-import li{font-size:13px;line-height:1.5}
    .bo-eff-import ul{margin:8px 0 14px;padding-left:18px}
    .bo-eff-import__file{display:grid;gap:6px;font-size:13px;font-weight:600;margin-bottom:16px}
    .bo-eff-import__actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
    .bo-eff-import__spacer{flex:1}
    </style>
    <script>
    (function () {
        var dlg = document.getElementById('bo-eff-import');
        if (!dlg || typeof dlg.showModal !== 'function') { return; }
        document.querySelectorAll('[data-eff-import-open]').forEach(function (b) { b.addEventListener('click', function () { dlg.showModal(); }); });
        dlg.querySelectorAll('[data-eff-import-close]').forEach(function (b) { b.addEventListener('click', function () { dlg.close(); }); });
    })();
    </script>
