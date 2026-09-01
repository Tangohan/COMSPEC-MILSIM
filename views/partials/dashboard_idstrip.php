<?php
declare(strict_types=1);

/**
 * Barre d’identité opérationnelle — chrome sous la navbar Dashboard / Hub / Forum.
 * Inclus depuis dashboard_command_center.php, immédiatement après header_dashboard.
 *
 * Attend les variables calculées par dashboard_command_center (unité, grade, matricule, etc.).
 */
?>
        <section class="dash-idstrip" aria-label="Identité opérationnelle">
            <div class="dash-idstrip__shell">
                <div class="dash-idstrip__facts">
                    <div class="dash-idstrip__fact">
                        <span class="dash-idstrip__label">Communauté</span>
                        <span class="dash-idstrip__value"><?= htmlspecialchars($unitLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="dash-idstrip__fact">
                        <span class="dash-idstrip__label">Grade</span>
                        <span class="dash-idstrip__value"><?= htmlspecialchars($roleHint, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="dash-idstrip__fact">
                        <span class="dash-idstrip__label">Matricule</span>
                        <span class="dash-idstrip__value"><?= htmlspecialchars($matricule ? (string) $matricule : 'Non attribué', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <?php if ($platformRole !== ''): ?>
                    <div class="dash-idstrip__fact">
                        <span class="dash-idstrip__label">Rôle</span>
                        <span class="dash-idstrip__value"><?= htmlspecialchars($platformRole, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="dash-idstrip__fact">
                        <span class="dash-idstrip__label">Statut</span>
                        <span class="dash-idstrip__value dash-idstrip__value--status"><?= htmlspecialchars($statutLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="dash-idstrip__fact">
                        <span class="dash-idstrip__label">Date</span>
                        <span class="dash-idstrip__value"><?= htmlspecialchars($todayLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
                <div class="dash-idstrip__actions" role="group" aria-label="Raccourcis opérationnels">
                    <button type="button" class="dash-idstrip__icon-btn" @click="tacticalOpen = true" aria-haspopup="dialog" aria-label="Ouvrir la situation tactique">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-4.5 7-11a7 7 0 10-14 0c0 6.5 7 11 7 11z"/>
                            <circle cx="12" cy="10" r="2.5"/>
                        </svg>
                    </button>
                    <button type="button" class="dash-idstrip__icon-btn" @click="calendarOpen = true" aria-haspopup="dialog" aria-label="Ouvrir le calendrier des manœuvres">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <rect x="3" y="5" width="18" height="16" rx="2"/>
                            <path stroke-linecap="round" d="M3 10h18M8 3v4M16 3v4"/>
                        </svg>
                    </button>
                    <a href="<?= url('personnel/me') ?>" class="dash-idstrip__icon-btn" aria-label="Ma fiche" title="Ma fiche">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </a>
                    <a href="<?= url('publier') ?>" class="dash-idstrip__icon-btn" aria-label="Publier" title="Publier">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/>
                        </svg>
                    </a>
                    <a href="<?= url('evenements') ?>" class="dash-idstrip__icon-btn" aria-label="Nouvelle manœuvre" title="Nouvelle manœuvre">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 22V15"/>
                        </svg>
                    </a>
                    <a href="<?= url('messages') ?>" class="dash-idstrip__text-btn">
                        <svg class="dash-idstrip__btn-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h8M8 14h5"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/>
                        </svg>
                        <span>Demande à l’encadrement</span>
                    </a>
                    <button type="button" class="dash-idstrip__text-btn" data-dash-rail-open-external="org-anomaly" aria-controls="dash-rail-nested-org-anomaly">
                        <svg class="dash-idstrip__btn-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3.8 21 19.5H3L12 3.8Z"/>
                            <path stroke-linecap="round" d="M12 9.5v4.4M12 16.6h.01"/>
                        </svg>
                        <span>Signaler une anomalie</span>
                    </button>
                    <?php if ($canViewAtakOperators): ?>
                    <a href="<?= url('back-office/atak/operateurs') ?>" class="dash-idstrip__text-btn dash-idstrip__text-btn--accent">
                        <svg class="dash-idstrip__btn-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
                        </svg>
                        <span>Effectifs en liaison<?php if ($atakOperatorsLinkedCount !== null): ?> (<?= (int) $atakOperatorsLinkedCount ?>)<?php endif; ?></span>
                    </a>
                    <?php endif; ?>
                </div>
                <?php
                $dashShowCommunitySwitch = $dashCtxCommunity && count($communityMemberships ?? []) > 1;
                if ($dashShowCommunitySwitch || $dashCtxTrial):
                ?>
                <div class="dash-idstrip__aside">
                    <?php if ($dashShowCommunitySwitch): ?>
                    <div class="dash-idstrip__switch">
                        <span class="dash-idstrip__label dash-idstrip__label--with-icon">
                            <svg class="dash-idstrip__btn-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12M8 12h12M8 17h12"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h.01M4 12h.01M4 17h.01"/>
                            </svg>
                            Vous êtes sur
                        </span>
                        <span class="dash-idstrip__chip dash-idstrip__chip--current" title="Communauté dont le poste affiche les positions"><?= htmlspecialchars($unitLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="dash-idstrip__label dash-idstrip__label--with-icon">Autres</span>
                        <div class="dash-idstrip__chips">
                            <?php foreach ($communityMemberships as $m): ?>
                                <?php if ((int) ($m['tenant_id'] ?? 0) === $currentTid) {
                                    continue;
                                } ?>
                                <form method="post" action="<?= url('community/switch') ?>" class="inline" onsubmit="var b=this.querySelector('button[type=submit]');if(b){b.disabled=true;b.setAttribute('aria-busy','true');}">
                                    <?= \App\Core\Csrf::field() ?>
                                    <input type="hidden" name="tenant_id" value="<?= (int) $m['tenant_id'] ?>">
                                    <input type="hidden" name="return_to" value="atak">
                                    <button type="submit" class="dash-idstrip__chip">
                                        <svg class="dash-idstrip__btn-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h3a2 2 0 012 2v9a2 2 0 01-2 2H8a2 2 0 01-2-2V9a2 2 0 012-2z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h3a2 2 0 012 2v9a2 2 0 01-2 2h-3"/>
                                        </svg>
                                        <?= htmlspecialchars(community_display_name($m), ENT_QUOTES, 'UTF-8') ?>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($dashCtxTrial): ?>
                        <a href="<?= url('platform/upgrade') ?>" class="dash-idstrip__trial">
                            Essai fondateur jusqu’au <?= htmlspecialchars(date('d/m/Y', strtotime($founderTrialEndsAt)), ENT_QUOTES, 'UTF-8') ?> →
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </section>
