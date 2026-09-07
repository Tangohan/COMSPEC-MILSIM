<?php
declare(strict_types=1);

require base_path('views/admin/effectifs_workspace/partials/rh_ui_helpers.php');

$hr = is_array($hrWorkspaceSettings ?? null) ? $hrWorkspaceSettings : [];
$rp = is_array($roleplayConfig ?? null) ? $roleplayConfig : [];
$dup = is_array($duplicateSettings ?? null) ? $duplicateSettings : [];
$preview = is_array($advancementPreview ?? null) ? $advancementPreview : [];
$csrf = htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8');
$vis = (($hr['default_visibility'] ?? 'STAFF') === 'MEMBER') ? 'MEMBER' : 'STAFF';
$mode = (($hr['advancement_mode'] ?? 'propose') === 'apply') ? 'apply' : 'propose';
?>
<section class="eff-rh-hero">
    <p class="eff-page-kicker">Dossier RH</p>
    <h2 class="eff-page-title">Réglages du bureau effectifs</h2>
    <p class="eff-page-lead">
        Personnalisez le coffre, les alertes, l’intégration, le roleplay et les avancements.
        Chaque communauté choisit ce qui s’applique chez elle. Rien n’est forcé.
    </p>
</section>

<form method="post" action="<?= $h(effectifs_workspace_url('reglages')) ?>" class="bo-eff-settings">
    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">

    <section class="bo-eff-settings__block" aria-labelledby="hr-set-docs">
        <h2 id="hr-set-docs">Coffre et pièces</h2>
        <p>Choisissez qui voit une nouvelle pièce par défaut, et si une décision de mobilité, d’élévation ou de fin d’intégration établit automatiquement une pièce dans le dossier.</p>
        <div class="eff-rh-form__grid">
            <div class="eff-rh-field">
                <span class="eff-rh-field__label">Visibilité par défaut</span>
                <select name="default_visibility" aria-label="Visibilité par défaut des pièces">
                    <option value="STAFF" <?= $vis === 'STAFF' ? 'selected' : '' ?>>État-major uniquement</option>
                    <option value="MEMBER" <?= $vis === 'MEMBER' ? 'selected' : '' ?>>Visible du membre</option>
                </select>
            </div>
        </div>
        <div class="bo-eff-settings__checks" style="margin-top:0.85rem">
            <label><input type="checkbox" name="auto_pdf_mobility" value="1" <?= !empty($hr['auto_pdf_mobility']) ? 'checked' : '' ?>> Établir une décision d’affectation quand une mobilité est appliquée</label>
            <label><input type="checkbox" name="auto_pdf_elevation" value="1" <?= !empty($hr['auto_pdf_elevation']) ? 'checked' : '' ?>> Établir une pièce quand une élévation de grade est acceptée</label>
            <label><input type="checkbox" name="auto_pdf_integration" value="1" <?= !empty($hr['auto_pdf_integration']) ? 'checked' : '' ?>> Établir une attestation quand l’intégration est terminée</label>
        </div>
    </section>

    <section class="bo-eff-settings__block" aria-labelledby="hr-set-alerts">
        <h2 id="hr-set-alerts">Alertes de suivi</h2>
        <p>Seuils utilisés sur la page Alertes. Ils ne sanctionnent personne : ils signalent un dossier à relire.</p>
        <div class="eff-rh-form__grid">
            <div class="eff-rh-field">
                <span class="eff-rh-field__label">Jours sans connexion</span>
                <input type="number" name="inactivity_days" min="14" max="180" value="<?= (int) ($hr['inactivity_days'] ?? 45) ?>" aria-label="Jours sans connexion">
            </div>
            <div class="eff-rh-field">
                <span class="eff-rh-field__label">Jours d’absence encore ouverte</span>
                <input type="number" name="absence_days" min="7" max="90" value="<?= (int) ($hr['absence_days'] ?? 14) ?>" aria-label="Jours d’absence ouverte">
            </div>
        </div>
    </section>

    <section class="bo-eff-settings__block" aria-labelledby="hr-set-adv">
        <h2 id="hr-set-adv">Avancements de grade</h2>
        <p>
            D’après l’ancienneté réelle dans la communauté, le bureau peut proposer le grade suivant (même famille de grades).
            Par défaut, une demande est créée : un responsable la valide. Appliquer tout seul reste un choix explicite.
        </p>
        <div class="bo-eff-settings__checks">
            <label><input type="checkbox" name="advancement_enabled" value="1" <?= !empty($hr['advancement_enabled']) ? 'checked' : '' ?>> Activer la revue automatique des avancements</label>
        </div>
        <div class="eff-rh-form__grid" style="margin-top:0.85rem">
            <div class="eff-rh-field">
                <span class="eff-rh-field__label">Ancienneté minimale (mois)</span>
                <input type="number" name="advancement_months" min="3" max="60" value="<?= (int) ($hr['advancement_months'] ?? 12) ?>" aria-label="Ancienneté minimale en mois">
            </div>
            <div class="eff-rh-field">
                <span class="eff-rh-field__label">Quand le dossier est éligible</span>
                <select name="advancement_mode" aria-label="Mode d’avancement">
                    <option value="propose" <?= $mode === 'propose' ? 'selected' : '' ?>>Créer une demande d’élévation à valider</option>
                    <option value="apply" <?= $mode === 'apply' ? 'selected' : '' ?>>Appliquer le grade suivant tout de suite</option>
                </select>
            </div>
        </div>
        <?php if ($preview !== []): ?>
            <div class="bo-eff-settings__preview">
                <strong><?= count($preview) ?> dossier<?= count($preview) > 1 ? 's' : '' ?> actuellement éligible<?= count($preview) > 1 ? 's' : '' ?></strong>
                <ul>
                    <?php foreach (array_slice($preview, 0, 12) as $row): ?>
                        <li><?= $h((string) ($row['name'] ?? 'Membre')) ?> — <?= $h((string) ($row['from'] ?? '')) ?> → <?= $h((string) ($row['to'] ?? '')) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </section>

    <section class="bo-eff-settings__block" aria-labelledby="hr-set-int">
        <h2 id="hr-set-int">Intégration</h2>
        <p>À l’arrivée d’un membre, un parcours d’accueil peut s’ouvrir tout seul. Les étapes avancent ensuite d’elles-mêmes quand le dossier ou le rendez-vous est en règle.</p>
        <div class="bo-eff-settings__checks">
            <label><input type="checkbox" name="auto_start_integration" value="1" <?= !empty($hr['auto_start_integration']) ? 'checked' : '' ?>> Ouvrir un parcours d’accueil à la création du compte</label>
            <label><input type="checkbox" name="auto_start_on_assignment" value="1" <?= !empty($hr['auto_start_on_assignment']) ? 'checked' : '' ?>> Ouvrir un parcours si l’affectation change et qu’un modèle s’applique</label>
        </div>
        <p class="bo-eff-jump" style="margin:0.9rem 0 0;border:0;padding:0;background:transparent">
            <a href="<?= $h(url('back-office/integration-membres/modeles')) ?>">Préparer les modèles d’accueil</a>
        </p>
    </section>

    <section class="bo-eff-settings__block" aria-labelledby="hr-set-rp">
        <h2 id="hr-set-rp">Roleplay</h2>
        <p>Le suivi d’immersion (entretiens, visites, rotations) peut vivre dans ce bureau. Les étapes et les filières se règlent dans l’écran d’immersion.</p>
        <div class="bo-eff-settings__checks">
            <label><input type="checkbox" name="rp_followup_enabled" value="1" <?= !empty($rp['enabled']) ? 'checked' : '' ?>> Activer le suivi roleplay</label>
            <label><input type="checkbox" name="rp_followup_optional" value="1" <?= !empty($rp['optional']) ? 'checked' : '' ?>> Le suivi reste facultatif pour les membres</label>
        </div>
        <p class="bo-eff-jump" style="margin:0.9rem 0 0;border:0;padding:0;background:transparent">
            <a href="<?= $h(url('back-office/roleplay/immersion')) ?>">Personnaliser les étapes et les filières</a>
        </p>
    </section>

    <section class="bo-eff-settings__block" aria-labelledby="hr-set-chain">
        <h2 id="hr-set-chain">Chaîne de commandement</h2>
        <p>Désignez le chef de chaque unité pour voir qui relève de qui. L’organigramme (quelle unité est au-dessus de quelle autre) se règle dans la structure.</p>
        <p class="bo-eff-jump" style="margin:0.6rem 0 0;border:0;padding:0;background:transparent">
            <a href="<?= $h(effectifs_workspace_url('chaine')) ?>">Désigner les chefs d’unité</a>
        </p>
    </section>

    <section class="bo-eff-settings__block" aria-labelledby="hr-set-dup">
        <h2 id="hr-set-dup">Fiches jumelles</h2>
        <p>La détection des dossiers qui partagent une même valeur (matricule, indicatif…) se règle sur sa propre page.</p>
        <p>État actuel : <?= !empty($dup['enabled']) ? 'détection active' : 'détection en pause' ?>.</p>
        <p class="bo-eff-jump" style="margin:0.6rem 0 0;border:0;padding:0;background:transparent">
            <a href="<?= $h(effectifs_workspace_url('doublons')) ?>">Choisir les critères de détection</a>
        </p>
    </section>

    <div class="eff-rh-form__actions" style="display:flex;gap:0.6rem;flex-wrap:wrap;justify-content:flex-start">
        <button type="submit" class="eff-rh-btn eff-rh-btn--primary">Enregistrer les réglages</button>
    </div>
</form>

<?php if (!empty($canManageAdvancement)): ?>
<form method="post" action="<?= $h(effectifs_workspace_url('reglages/avancements')) ?>" style="margin-top:0.75rem">
    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
    <button type="submit" class="eff-rh-btn">Passer les dossiers en revue maintenant</button>
</form>
<?php endif; ?>
