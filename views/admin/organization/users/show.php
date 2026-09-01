<?php
declare(strict_types=1);

$user = $user ?? null;
$userProfile = is_array($userProfile ?? null) ? $userProfile : [];
$userRoleIds = $userRoleIds ?? [];
$roles = $roles ?? [];
$completenessAccount = $completenessAccount ?? ($completeness ?? ['score' => 0, 'missing' => [], 'sections_critiques' => []]);
$completenessPersonnel = $completenessPersonnel ?? null;
$isServiceAccount = $isServiceAccount ?? false;
$showPlatformDiagnostics = $showPlatformDiagnostics ?? false;
$isAnonymizedAccount = (bool) ($isAnonymizedAccount ?? false);
$pendingPurgeRequest = is_array($pendingPurgeRequest ?? null) ? $pendingPurgeRequest : null;

if (!$user) {
    echo '<p>Utilisateur introuvable.</p>';
    return;
}

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$uid = (int) $user['id'];
$personnelEditUrl = url('personnel/' . $uid . '/edit');
$displayName = trim((string) ($user['display_name'] ?? ''));
$email = (string) ($user['email'] ?? '');
$callsign = trim((string) ($user['callsign'] ?? ''));
$avatarSrc = function_exists('user_media_public_url')
    ? user_media_public_url($user['avatar_url'] ?? null)
    : null;
$initialsSource = $displayName !== '' ? $displayName : $email;
$initials = function_exists('user_display_initials')
    ? user_display_initials($initialsSource, 2)
    : mb_strtoupper(mb_substr($initialsSource, 0, 2, 'UTF-8'), 'UTF-8');
$ust = (string) ($user['status'] ?? '');
if ($isAnonymizedAccount) {
    $statusLabel = 'Compte supprimé';
    $statusTagClass = 'ath-tag--neut';
} else {
    $statusLabel = match ($ust) {
        'active' => 'Compte actif',
        'inactive' => 'Compte inactif',
        'pending_verification' => 'En attente de vérification de l’e-mail',
        default => $ust !== '' ? 'Statut à clarifier' : 'Statut inconnu',
    };
    $statusTagClass = match ($ust) {
        'active' => 'ath-tag--ok',
        'inactive' => 'ath-tag--neut',
        default => 'ath-tag--warn',
    };
}

$displayValue = static function (?string $value, string $emptyLabel = 'Non renseigné') use ($h): string {
    $trimmed = trim((string) $value);

    return $trimmed !== '' ? $h($trimmed) : '<span class="ath-member-show__empty">' . $h($emptyLabel) . '</span>';
};

$levelBadge = static function (string $level): array {
    return match ($level) {
        \App\Services\Admin\ProfileCompletenessService::LEVEL_BLOCKING => [
            'text' => 'Indispensable',
            'class' => 'ath-tag--bad',
        ],
        \App\Services\Admin\ProfileCompletenessService::LEVEL_RECOMMENDED => [
            'text' => 'À prévoir',
            'class' => 'ath-tag--warn',
        ],
        \App\Services\Admin\ProfileCompletenessService::LEVEL_ADMINISTRATIVE => [
            'text' => 'Ergonomie du compte',
            'class' => 'ath-tag--neut',
        ],
        default => [
            'text' => 'À compléter',
            'class' => 'ath-tag--neut',
        ],
    };
};

$listUrl = url('back-office/users');
$editUrl = url('back-office/users/' . $uid . '/edit');

$roleNames = [];
foreach ($roles as $rr) {
    if (in_array((int) ($rr['id'] ?? 0), $userRoleIds, true)) {
        $roleNames[] = (string) ($rr['name'] ?? '');
    }
}
$roleNames = array_values(array_filter($roleNames, static fn (string $n): bool => trim($n) !== ''));

$accScore = (int) ($completenessAccount['score'] ?? 100);
$pScore = $completenessPersonnel !== null ? (int) ($completenessPersonnel['score'] ?? 100) : null;
$profileIncomplete = !$isServiceAccount && ($accScore < 100 || ($pScore !== null && $pScore < 100));

$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
$flashWarn = \App\Core\Session::getFlash('warning');
?>
<div class="ath-member-show ath-rise">
    <?php if ($flashOk): ?>
    <div class="ath-banner-warn ath-member-show__flash" style="background:#e6f8f0;border-color:#bfe9d8;" role="status">
        <div class="ath-banner-warn__text" style="color:#0b6b47;"><?= $h((string) $flashOk) ?></div>
    </div>
    <?php endif; ?>
    <?php if ($flashWarn): ?>
    <div class="ath-banner-warn ath-member-show__flash" role="status">
        <div class="ath-banner-warn__text"><?= $h((string) $flashWarn) ?></div>
    </div>
    <?php endif; ?>
    <?php if ($flashErr): ?>
    <div class="ath-banner-warn ath-member-show__flash ath-member-show__flash--err" role="alert">
        <div class="ath-banner-warn__text"><?= $h((string) $flashErr) ?></div>
    </div>
    <?php endif; ?>

    <div class="ath-member-show__meta ath-card">
        <div class="ath-member-show__avatar" aria-hidden="true">
            <?php if ($avatarSrc): ?>
            <img src="<?= $h($avatarSrc) ?>" alt="" data-img-fallback="avatar" data-img-initials="<?= $h($initials) ?>">
            <?php else: ?>
            <?= $h($initials) ?>
            <?php endif; ?>
        </div>
        <div class="ath-member-show__identity">
            <p class="ath-member-show__kicker">Fiche membre</p>
            <p class="ath-member-show__name"><?= $h($displayName !== '' ? $displayName : 'Membre sans nom affiché') ?></p>
            <p class="ath-member-show__email"><?= $h($email) ?></p>
            <div class="ath-member-show__tags">
                <span class="ath-tag <?= $h($statusTagClass) ?>"><?= $h($statusLabel) ?></span>
                <?php if ($callsign !== ''): ?>
                <span class="ath-tag ath-tag--neut">Indicatif <?= $h($callsign) ?></span>
                <?php endif; ?>
                <?php if ($isServiceAccount): ?>
                <span class="ath-tag ath-tag--info">Compte technique</span>
                <?php endif; ?>
                <?php if ($profileIncomplete): ?>
                <span class="ath-tag ath-tag--warn">Profil à compléter</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="ath-member-show__meta-actions">
            <a href="<?= $h($listUrl) ?>" class="ath-btn">← Liste des membres</a>
            <a href="<?= $h(effectifs_workspace_url('membres/' . $uid)) ?>" class="ath-btn">Fiche Effectifs</a>
            <a href="<?= $h($personnelEditUrl) ?>" class="ath-btn">Dossier personnel</a>
            <a href="<?= $h($editUrl) ?>" class="ath-btn ath-btn--solid">Compte</a>
        </div>
    </div>

    <?php if ($showPlatformDiagnostics): ?>
    <div class="ath-banner-warn ath-member-show__callout ath-member-show__callout--info" role="note">
        <p class="ath-banner-warn__kicker">Vue approfondie</p>
        <p class="ath-banner-warn__text">Vous consultez une vue enrichie avec des critères supplémentaires (photo de profil, identité civile) et l’ensemble des contrôles dossier. Les administrateurs sans habilitation plateforme voient une version simplifiée.</p>
    </div>
    <?php endif; ?>

    <?php if ($isServiceAccount): ?>
    <div class="ath-card ath-member-show__callout-card">
        <p class="ath-member-show__callout-title">Compte technique</p>
        <p class="ath-body">Utilisé pour la modération automatique et les traitements internes. Il n’a pas de fiche personnage jouable.</p>
    </div>
    <?php endif; ?>

    <?php if ($ust === 'pending_verification' && !$isServiceAccount): ?>
    <div class="ath-banner-warn ath-member-show__callout">
        <p class="ath-banner-warn__kicker">Confirmation en attente</p>
        <p class="ath-banner-warn__text">Le membre doit ouvrir le lien reçu par e-mail pour activer son accès. Vous pouvez renvoyer un nouveau lien s’il a expiré ou n’a pas été reçu.</p>
        <form method="post" action="<?= $h(url('back-office/users/' . $uid . '/resend-verification')) ?>" class="ath-member-show__inline-form">
            <?= \App\Core\Csrf::field() ?>
            <button type="submit" class="ath-btn ath-btn--solid">Renvoyer le lien de confirmation</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($profileIncomplete): ?>
    <div class="ath-banner-warn ath-member-show__callout">
        <p class="ath-banner-warn__kicker">Rappel par courriel</p>
        <p class="ath-banner-warn__text">Envoyez un message à l’adresse du compte avec un lien direct vers la fiche personnelle pour l’aider à finaliser son profil.</p>
        <form method="post" action="<?= $h(url('back-office/users/' . $uid . '/notify-profile')) ?>" class="ath-member-show__inline-form">
            <?= \App\Core\Csrf::field() ?>
            <button type="submit" class="ath-btn ath-btn--accent">Envoyer un rappel par e-mail</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($isAnonymizedAccount && !$isServiceAccount): ?>
    <section class="ath-card ath-member-show__danger-card" aria-labelledby="member-purge-request">
        <div class="ath-member-show__section-head">
            <h2 id="member-purge-request">Suppression définitive</h2>
            <p>Ce membre est déjà anonymisé (« Compte supprimé »). Vous pouvez demander à la plateforme d’effacer définitivement cette fiche de votre communauté.</p>
        </div>
        <?php if ($pendingPurgeRequest !== null): ?>
        <div class="ath-banner-warn ath-member-show__callout" role="status">
            <p class="ath-banner-warn__kicker">Demande en cours</p>
            <p class="ath-banner-warn__text">
                Une demande de suppression définitive a été transmise
                <?php
                $reqAt = trim((string) ($pendingPurgeRequest['created_at'] ?? ''));
                echo $reqAt !== '' ? ' le ' . $h($reqAt) : '';
                ?>. Un opérateur plateforme la traitera prochainement.
            </p>
        </div>
        <?php else: ?>
        <form method="post" action="<?= $h(url('back-office/users/' . $uid . '/request-purge')) ?>" class="ath-member-show__danger-form" onsubmit="return confirm('Demander la suppression définitive de cette fiche « Compte supprimé » ?\n\nUn opérateur plateforme devra valider la demande.');">
            <?= \App\Core\Csrf::field() ?>
            <label class="ath-member-show__check" style="display:block;margin-bottom:0.75rem;">
                <span style="display:block;font-weight:600;margin-bottom:0.35rem;">Motif (optionnel)</span>
                <textarea name="note" rows="3" maxlength="2000" class="ath-input" style="width:100%;" placeholder="Contexte pour l’opérateur plateforme…"></textarea>
            </label>
            <button type="submit" class="ath-btn ath-member-show__danger-btn">Demander la suppression définitive</button>
        </form>
        <?php endif; ?>
    </section>
    <?php elseif (($user['status'] ?? '') !== 'inactive' && !$isServiceAccount): ?>
    <section class="ath-card ath-member-show__danger-card" aria-labelledby="member-deactivate">
        <div class="ath-member-show__section-head">
            <h2 id="member-deactivate">Désactiver l’accès</h2>
            <p>Le membre ne pourra plus se connecter à cette communauté.</p>
        </div>
        <form method="post" action="<?= $h(url('back-office/users/' . $uid . '/deactivate')) ?>" class="ath-member-show__danger-form" onsubmit="return confirm('Désactiver l’accès de ce membre ?');">
            <?= \App\Core\Csrf::field() ?>
            <label class="ath-member-show__check">
                <input type="checkbox" name="block_email_rejoin" value="1">
                <span>Empêcher aussi toute nouvelle inscription ou candidature avec la même adresse e-mail dans cette communauté.</span>
            </label>
            <button type="submit" class="ath-btn ath-member-show__danger-btn">Désactiver l’accès</button>
        </form>
    </section>
    <?php endif; ?>

    <section class="ath-card ath-member-show__legend" aria-labelledby="legend-comp">
        <div class="ath-member-show__section-head ath-member-show__section-head--compact">
            <h2 id="legend-comp">Légende des niveaux</h2>
            <p>Priorité des éléments à compléter sur le compte et le dossier.</p>
        </div>
        <ul class="ath-member-show__legend-list">
            <li>
                <span class="ath-tag ath-tag--bad">Indispensable</span>
                <span class="ath-member-show__legend-copy">Bloque les vérifications essentielles tant que ce n’est pas renseigné.</span>
            </li>
            <li>
                <span class="ath-tag ath-tag--warn">À prévoir</span>
                <span class="ath-member-show__legend-copy">Recommandé pour une fiche exploitable par l’état-major.</span>
            </li>
            <?php if ($showPlatformDiagnostics): ?>
            <li>
                <span class="ath-tag ath-tag--neut">Ergonomie du compte</span>
                <span class="ath-member-show__legend-copy">Critère d’affichage sur le portail, sans impact sur les habilitations.</span>
            </li>
            <?php endif; ?>
        </ul>
    </section>

    <div class="ath-member-show__grid">
        <section class="ath-card ath-member-show__panel">
            <div class="ath-member-show__section-head">
                <h2>Compte</h2>
                <p>Connexion, identité affichée sur le portail et rôle dans l’unité.</p>
            </div>
            <div class="ath-member-show__section-body">
                <?php if ($accScore < 100): ?>
                <div class="ath-member-show__progress">
                    <div class="ath-kpi__label">Complétude du compte</div>
                    <div class="ath-kpi__row">
                        <span class="ath-kpi__value"><?= $accScore ?>%</span>
                    </div>
                    <div class="ath-kpi__bar"><span class="ath-barA" style="width:<?= min(100, max(0, $accScore)) ?>%;background:var(--ath-accent);"></span></div>
                    <?php if (!empty($completenessAccount['missing'])): ?>
                    <ul class="ath-member-show__missing">
                        <?php foreach ($completenessAccount['missing'] as $m):
                            $lv = (string) ($m['level'] ?? '');
                            if ($lv === \App\Services\Admin\ProfileCompletenessService::LEVEL_ADMINISTRATIVE && !$showPlatformDiagnostics) {
                                continue;
                            }
                            $b = $levelBadge($lv);
                            ?>
                        <li>
                            <span class="ath-tag <?= $h($b['class']) ?>"><?= $h($b['text']) ?></span>
                            <span><?= $h((string) ($m['label'] ?? '')) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <p class="ath-member-show__complete">
                    <span class="ath-member-show__complete-dot" aria-hidden="true"></span>
                    Informations minimales du compte présentes.
                </p>
                <?php endif; ?>

                <dl class="ath-member-show__data">
                    <div class="ath-member-show__data-row">
                        <dt>Adresse e-mail</dt>
                        <dd><?= $displayValue($email, 'Aucune adresse') ?></dd>
                    </div>
                    <div class="ath-member-show__data-row">
                        <dt>Prénom</dt>
                        <dd><?= $displayValue($userProfile['first_name'] ?? null) ?></dd>
                    </div>
                    <div class="ath-member-show__data-row">
                        <dt>Nom</dt>
                        <dd><?= $displayValue($userProfile['last_name'] ?? null) ?></dd>
                    </div>
                    <div class="ath-member-show__data-row">
                        <dt>Indicatif du compte</dt>
                        <dd><?= $displayValue($callsign) ?></dd>
                    </div>
                    <div class="ath-member-show__data-row">
                        <dt>Identifiant plateforme</dt>
                        <dd><span class="font-mono"><?= $displayValue($user['athena_identifier'] ?? null) ?></span></dd>
                    </div>
                    <div class="ath-member-show__data-row">
                        <dt>Steam ID</dt>
                        <dd><span class="font-mono"><?= $displayValue($user['steam_id'] ?? null) ?></span></dd>
                    </div>
                    <div class="ath-member-show__data-row">
                        <dt>Téléphone</dt>
                        <dd><?= $displayValue($userProfile['phone'] ?? null) ?></dd>
                    </div>
                    <div class="ath-member-show__data-row">
                        <dt>Rôles dans l’unité</dt>
                        <dd>
                            <?php if ($roleNames !== []): ?>
                                <?php foreach ($roleNames as $rn): ?>
                                <span class="ath-tag ath-tag--neut ath-member-show__role-tag"><?= $h($rn) ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="ath-member-show__empty">Aucun rôle attribué</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div class="ath-member-show__data-row">
                        <dt>Statut du compte</dt>
                        <dd><span class="ath-tag <?= $h($statusTagClass) ?>"><?= $h($statusLabel) ?></span></dd>
                    </div>
                </dl>
            </div>
        </section>

        <section class="ath-card ath-member-show__panel">
            <div class="ath-member-show__section-head">
                <h2>Dossier opérationnel</h2>
                <p>Personnage, affectation, clearance et qualifications — distinct du compte de connexion.</p>
            </div>
            <div class="ath-member-show__section-body ath-member-show__section-body--stack">
                <?php
                $ppShow = is_array($personnelProfile ?? null) ? $personnelProfile : [];
                $pexShow = is_array($personnelExtras ?? null) ? $personnelExtras : [];
                $gradeShow = is_array($memberGrade ?? null) ? $memberGrade : null;
                $assignShow = is_array($primaryAssignment ?? null) ? $primaryAssignment : null;
                $matriculeShow = trim((string) ($ppShow['matricule_internal'] ?? '')) ?: trim((string) ($pexShow['service_number'] ?? ''));
                $characterShow = trim((string) ($ppShow['character_name'] ?? ''));
                $gradeLabelShow = $gradeShow
                    ? trim((string) ($gradeShow['short_name'] ?? $gradeShow['name'] ?? $gradeShow['label_long'] ?? ''))
                    : '';
                $unitShow = trim((string) ($assignShow['unit_name'] ?? ''));
                ?>
                <?php if (!$isServiceAccount): ?>
                <dl class="ath-member-show__data">
                    <div class="ath-member-show__data-row">
                        <dt>Personnage</dt>
                        <dd><?= $displayValue($characterShow) ?></dd>
                    </div>
                    <div class="ath-member-show__data-row">
                        <dt>Matricule dossier</dt>
                        <dd><span class="font-mono"><?= $displayValue($matriculeShow) ?></span></dd>
                    </div>
                    <div class="ath-member-show__data-row">
                        <dt>Grade</dt>
                        <dd><?= $displayValue($gradeLabelShow) ?></dd>
                    </div>
                    <div class="ath-member-show__data-row">
                        <dt>Affectation</dt>
                        <dd><?= $displayValue($unitShow) ?></dd>
                    </div>
                </dl>
                <?php endif; ?>
                <?php if ($isServiceAccount): ?>
                <p class="ath-body">Non applicable pour un compte technique.</p>
                <?php elseif ($completenessPersonnel !== null): ?>
                    <?php if ($pScore !== null && $pScore < 100): ?>
                <div class="ath-member-show__progress">
                    <div class="ath-kpi__label">Complétude du dossier</div>
                    <div class="ath-kpi__row">
                        <span class="ath-kpi__value"><?= $pScore ?>%</span>
                    </div>
                    <div class="ath-kpi__bar"><span class="ath-barA" style="width:<?= min(100, max(0, $pScore)) ?>%;background:var(--ath-info);"></span></div>
                    <?php if (!empty($completenessPersonnel['sections_critiques'])): ?>
                    <div class="ath-member-show__priority">
                        <p class="ath-member-show__priority-title">Points à traiter en priorité</p>
                        <ul class="ath-member-show__priority-list">
                            <?php foreach ($completenessPersonnel['sections_critiques'] as $c): ?>
                            <li><?= $h((string) $c) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($completenessPersonnel['missing_labels'])): ?>
                    <div class="ath-member-show__other">
                        <p class="ath-member-show__other-title">Autres éléments à compléter</p>
                        <ul class="ath-member-show__other-list">
                            <?php foreach ($completenessPersonnel['missing_labels'] as $lbl): ?>
                            <li><?= $h((string) $lbl) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
                    <?php else: ?>
                <p class="ath-member-show__complete">
                    <span class="ath-member-show__complete-dot" aria-hidden="true"></span>
                    Éléments principaux du dossier renseignés.
                </p>
                    <?php endif; ?>
                <div class="ath-member-show__panel-actions">
                    <a href="<?= $h($personnelEditUrl) ?>" class="ath-btn ath-btn--solid">Ouvrir la fiche personnelle</a>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <?php
    $siblings = is_array($siblingMemberships ?? null) ? $siblingMemberships : [];
    if ($showPlatformDiagnostics && $siblings !== []):
    ?>
    <section class="ath-card ath-member-show__panel" style="margin-top:1.25rem;">
        <div class="ath-member-show__section-head">
            <h2>Appartenances multi-communautés</h2>
            <p>Toutes les fiches liées à la même adresse e-mail (vue plateforme).</p>
        </div>
        <div class="ath-member-show__section-body">
            <ul class="ath-member-show__other-list">
                <?php foreach ($siblings as $sib): ?>
                    <?php
                    $sibTid = (int) ($sib['tenant_id'] ?? 0);
                    $sibUid = (int) ($sib['id'] ?? 0);
                    $sibName = (string) ($sib['tenant_name'] ?? '');
                    $sibStatus = (string) ($sib['status'] ?? '');
                    $sibDeleted = !empty($sib['deleted_at']);
                    ?>
                    <li>
                        <strong><?= $h($sibName) ?></strong>
                        — #<?= $sibUid ?> · <?= $h($sibStatus) ?><?= $sibDeleted ? ' · anonymisé' : '' ?>
                        <?php if ($sibTid === (int) ($user['tenant_id'] ?? 0)): ?>
                            <span class="ath-tag ath-tag--info">Communauté courante</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($email !== ''): ?>
            <div class="ath-member-show__panel-actions" style="margin-top:0.75rem;">
                <a href="<?= $h(url('admin/users/person') . '?email=' . rawurlencode($email)) ?>" class="ath-btn ath-btn--solid">Dossier plateforme complet</a>
            </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>
</div>
