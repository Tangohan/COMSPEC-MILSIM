<?php
// Formulaires d'identité, de profil, de formule et de retrait (inchangés), inclus dans l'onglet Édition.
?>
                <form id="identite" class="pa-card scroll-mt-24" method="post" action="<?= $h($identityAction) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <p class="pa-card__kicker">01 · Identité</p>
                    <h2 class="pa-card__title">Nom et page publique</h2>
                    <p class="pa-card__help">Changer l’adresse courte casse les anciens liens vers cette communauté.</p>
                    <div class="pa-field">
                        <label for="tenant_name">Nom affiché</label>
                        <input id="tenant_name" name="tenant_name" type="text" required maxlength="255" value="<?= $h($name) ?>">
                    </div>
                    <div class="pa-field">
                        <label for="tenant_slug">Adresse courte de la page publique</label>
                        <p class="pa-hint">Lettres minuscules, chiffres et tirets, 50 caractères au plus.</p>
                        <input id="tenant_slug" name="tenant_slug" type="text" required maxlength="50" pattern="[a-z0-9]([-a-z0-9]*[a-z0-9])?" value="<?= $h($slug) ?>">
                    </div>
                    <div class="pa-actions">
                        <button type="submit" class="pa-btn pa-btn--ink">Enregistrer l’identité</button>
                    </div>
                </form>

                <form id="profil" class="pa-card scroll-mt-24" method="post" action="<?= $h($typeAction) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <p class="pa-card__kicker">02 · Profil</p>
                    <h2 class="pa-card__title">Profil d’outils</h2>
                    <p class="pa-card__help">Le profil détermine les outils visibles pour tous les membres. Changer ou réappliquer ajuste les menus, sans effacer les données.</p>
                    <fieldset style="border:0;margin:1rem 0 0;padding:0;">
                        <legend class="sr-only">Profil de la communauté</legend>
                        <?php foreach ($typeOptions as $typeKey => $typeMeta): ?>
                            <?php
                            $typeKey = (string) $typeKey;
                            $isCurrent = $typeKey === $currentType;
                            $inputId = 'tenant_type_' . preg_replace('/[^a-z0-9_-]/i', '', $typeKey);
                            ?>
                            <label class="pa-choice" for="<?= $h($inputId) ?>">
                                <input id="<?= $h($inputId) ?>" type="radio" name="tenant_type" value="<?= $h($typeKey) ?>" <?= $isCurrent ? 'checked' : '' ?>>
                                <span>
                                    <strong><?= $h((string) ($typeMeta['label'] ?? $typeKey)) ?><?= $isCurrent ? ' · actuel' : '' ?></strong>
                                    <em><?= $h((string) ($typeMeta['description'] ?? '')) ?></em>
                                    <?php if (!empty($typeMeta['consequences'])): ?>
                                        <em><?= $h((string) $typeMeta['consequences']) ?></em>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <label class="pa-choice" style="margin-top:0.85rem;">
                        <input type="checkbox" name="confirm_type_change" value="1" required>
                        <span>
                            <strong>Confirmation</strong>
                            <em>J’applique (ou réapplique) ce profil pour toute la communauté.</em>
                        </span>
                    </label>
                    <div class="pa-actions">
                        <button type="submit" class="pa-btn pa-btn--ink">Appliquer le profil</button>
                    </div>
                </form>

                <section id="formule" class="pa-card scroll-mt-24">
                    <p class="pa-card__kicker">03 · Formule</p>
                    <h2 class="pa-card__title">Formule d’accès</h2>
                    <p class="pa-card__help">Le palier détermine les modules et les plafonds. Le changement s’applique immédiatement.</p>

                    <?php if ($planGift !== null): ?>
                        <p class="pa-flash pa-flash--ok" style="margin-top:1rem;">
                            Formule offerte en cours : <?= $h($currentPlanLabel) ?>, <?= $giftEndLabel !== '' ? 'jusqu’au ' . $h($giftEndLabel) : 'sans limite' ?>.
                            Elle prime sur la formule de base ci-dessous (<?= $h($basePlanLabel) ?>), qui reprend à la fin de l’offre.
                            <a href="<?= $h(url('admin/formules-offertes')) ?>">Gérer les formules offertes</a>
                        </p>
                    <?php endif; ?>
                    <?php if ($plansError !== ''): ?>
                        <p class="pa-flash pa-flash--warn" style="margin-top:1rem;"><?= $h($plansError) ?></p>
                    <?php else: ?>
                        <?php if ($hasStripe): ?>
                            <p class="pa-flash pa-flash--warn" style="margin-top:1rem;">
                                Un abonnement est déjà lié au prestataire de paiement. Un changement manuel peut être écrasé au prochain renouvellement.
                            </p>
                        <?php endif; ?>

                        <form method="post" action="<?= $h($formAction) ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <div class="pa-field">
                                <label for="plan_slug"><?= $planGift !== null ? 'Formule de base' : 'Formule d’accès' ?></label>
                                <select id="plan_slug" name="plan_slug" required>
                                    <?php if ($plans === []): ?>
                                        <option value="">Aucune formule disponible</option>
                                    <?php else: ?>
                                        <?php foreach ($plans as $p): ?>
                                            <?php
                                            $planValue = (string) ($p['slug'] ?? '');
                                            $label = (string) ($p['name'] ?? $planValue);
                                            if ($planValue === '') {
                                                continue;
                                            }
                                            ?>
                                            <option value="<?= $h($planValue) ?>" <?= $planValue === $currentPlan ? 'selected' : '' ?>><?= $h($label) ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="pa-field">
                                <label for="subscription_status">Statut d’abonnement</label>
                                <p class="pa-hint">Pour une attribution de courtoisie, choisissez « Abonnement actif » ou « Sans abonnement payant ».</p>
                                <select id="subscription_status" name="subscription_status" required>
                                    <?php foreach ($statusLabels as $value => $label): ?>
                                        <option value="<?= $h((string) $value) ?>" <?= (string) $value === $currentStatus ? 'selected' : '' ?>><?= $h((string) $label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if ($trialActive): ?>
                                <label class="pa-choice" style="margin-top:1rem;">
                                    <input type="checkbox" name="end_founder_trial" value="1">
                                    <span>
                                        <strong>Clôturer l’essai fondateur</strong>
                                        <em>Seule la formule choisie ci-dessus comptera ensuite.</em>
                                    </span>
                                </label>
                            <?php endif; ?>
                            <div class="pa-actions">
                                <button type="submit" class="pa-btn pa-btn--ink">Enregistrer la formule</button>
                                <a class="pa-btn pa-btn--line" href="<?= $h(url('admin/tenants')) ?>">Retour à l’annuaire</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>
