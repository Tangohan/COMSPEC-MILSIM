<?php
declare(strict_types=1);

/** @var list<array<string,mixed>> $presets */
$presets = $presets ?? [];
$presetNorm = new \App\Services\Profile\RecruitmentPresetPayloadService();
$success = $success ?? null;
$error = $error ?? null;

$accountNavKey = 'recruitment';
$accountTitle = 'Profils de candidature';
$accountLead = 'Enregistrez des préréglages (motivation, disponibilité, personnage…) réutilisables sur les formulaires d’enrôlement.';
require base_path('views/partials/account/shell_open.php');
?>

<div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1.25rem">
    <p class="account-hub__hint" style="margin:0;max-width:28rem">Ces profils accélèrent une candidature : ils ne remplacent pas votre fiche personnelle ni votre compte civil.</p>
    <a href="<?= htmlspecialchars(url('account/recruitment-presets/create'), ENT_QUOTES, 'UTF-8') ?>" class="account-hub__btn account-hub__btn--ink">Nouveau profil</a>
</div>

<?php if (empty($presets)): ?>
<div class="account-hub__empty">
    <p class="account-hub__empty-title">Aucun profil enregistré</p>
    <p class="account-hub__empty-desc">Créez un profil pour préremplir rapidement une candidature lors d’un prochain enrôlement.</p>
    <p style="margin:1.15rem 0 0">
        <a href="<?= htmlspecialchars(url('account/recruitment-presets/create'), ENT_QUOTES, 'UTF-8') ?>" class="account-hub__btn account-hub__btn--primary">Créer mon premier profil</a>
    </p>
</div>
<?php else: ?>
<section class="account-hub__panel">
    <div class="account-hub__panel-body" style="padding-top:.5rem;padding-bottom:.5rem">
        <ul class="account-hub__action-list">
            <?php foreach ($presets as $p): ?>
                <?php
                $pay = is_array($p['payload'] ?? null) ? $p['payload'] : [];
                $norm = $presetNorm->normalizeDecodedPayload($pay);
                $rp = is_array($norm['rp'] ?? null) ? $norm['rp'] : [];
                $rpLabel = \App\Services\Profile\RecruitmentPresetPayloadService::deriveOperatorDisplayName($rp);
                ?>
            <li>
                <div class="account-hub__action" style="cursor:default">
                    <span class="account-hub__action-icon" aria-hidden="true">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </span>
                    <span style="flex:1;min-width:0">
                        <p class="account-hub__action-title"><?= htmlspecialchars((string) ($p['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="account-hub__action-desc">
                            <?php if ($rpLabel !== ''): ?>Personnage : <?= htmlspecialchars($rpLabel, ENT_QUOTES, 'UTF-8') ?> · <?php endif; ?>
                            Modifié <?= !empty($p['updated_at']) ? htmlspecialchars((string) $p['updated_at'], ENT_QUOTES, 'UTF-8') : '—' ?>
                        </p>
                    </span>
                    <span style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center">
                        <a href="<?= htmlspecialchars(url('account/recruitment-presets/' . (int) ($p['id'] ?? 0) . '/edit'), ENT_QUOTES, 'UTF-8') ?>" class="account-hub__btn account-hub__btn--soft" style="padding:.45rem .75rem;font-size:.75rem">Modifier</a>
                        <form method="post" action="<?= htmlspecialchars(url('account/recruitment-presets/' . (int) ($p['id'] ?? 0) . '/delete'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Supprimer ce profil de candidature ?');">
                            <?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="account-hub__btn" style="padding:.45rem .75rem;font-size:.75rem;background:#fff;color:#be123c;border:1px solid #fecdd3">Supprimer</button>
                        </form>
                    </span>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<p class="account-hub__footer-note">
    <a href="<?= htmlspecialchars(url('personnel/me'), ENT_QUOTES, 'UTF-8') ?>">Voir ma fiche personnelle</a>
</p>

<?php require base_path('views/partials/account/shell_close.php'); ?>
