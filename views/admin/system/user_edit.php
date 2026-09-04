<?php
declare(strict_types=1);

use App\Services\Admin\PlatformUserProfileService;

$pack = is_array($platformEdit ?? null) ? $platformEdit : [];
$user = is_array($pack['user'] ?? null) ? $pack['user'] : [];
$profile = is_array($pack['profile'] ?? null) ? $pack['profile'] : [];
$legal = is_array($pack['legal'] ?? null) ? $pack['legal'] : [];
$personnel = is_array($pack['personnel'] ?? null) ? $pack['personnel'] : [];
$extras = is_array($pack['extras'] ?? null) ? $pack['extras'] : [];
$tenant = is_array($pack['tenant'] ?? null) ? $pack['tenant'] : [];
$grades = is_array($pack['grades'] ?? null) ? $pack['grades'] : [];
$gradeCategories = is_array($pack['grade_categories'] ?? null) ? $pack['grade_categories'] : [];
$units = is_array($pack['units'] ?? null) ? $pack['units'] : [];
$roles = is_array($pack['roles'] ?? null) ? $pack['roles'] : [];
$selectedRoleIds = is_array($pack['selected_role_ids'] ?? null) ? $pack['selected_role_ids'] : [];
$extraCallsigns = is_array($pack['extra_callsigns'] ?? null) ? $pack['extra_callsigns'] : [];
$clearanceOptions = is_array($pack['clearance_options'] ?? null) ? $pack['clearance_options'] : [];
$flagOptions = is_array($pack['flag_options'] ?? null) ? $pack['flag_options'] : [];
$statusOptions = is_array($pack['status_options'] ?? null) ? $pack['status_options'] : PlatformUserProfileService::accountStatusOptions();
$bloodOptions = is_array($pack['blood_options'] ?? null) ? $pack['blood_options'] : PlatformUserProfileService::bloodTypeOptions();
$sexOptions = is_array($pack['sex_options'] ?? null) ? $pack['sex_options'] : PlatformUserProfileService::sexOptions();
$familyOptions = is_array($pack['family_options'] ?? null) ? $pack['family_options'] : PlatformUserProfileService::familySituationOptions();
$languageOptions = is_array($pack['language_options'] ?? null) ? $pack['language_options'] : PlatformUserProfileService::interfaceLanguageOptions();
$timezoneOptions = is_array($pack['timezone_options'] ?? null) ? $pack['timezone_options'] : PlatformUserProfileService::timezoneOptions();
$doctrineOptions = is_array($pack['doctrine_options'] ?? null) ? $pack['doctrine_options'] : PlatformUserProfileService::doctrineOptions();
$gradeFormatOptions = is_array($pack['grade_format_options'] ?? null) ? $pack['grade_format_options'] : PlatformUserProfileService::gradeFormatOptions();

$uid = (int) ($user['id'] ?? ($platformEditUserId ?? 0));
$email = (string) ($user['email'] ?? '');
$tenantName = trim((string) ($tenant['name'] ?? ''));
$athenaId = trim((string) ($user['athena_identifier'] ?? ''));
$ust = (string) ($user['status'] ?? '');
$matricule = trim((string) ($personnel['matricule_internal'] ?? '')) ?: trim((string) ($extras['service_number'] ?? ''));
$enlistment = '';
if (!empty($personnel['enlistment_date']) && !str_starts_with((string) $personnel['enlistment_date'], '0000-00-00')) {
    $enlistment = substr((string) $personnel['enlistment_date'], 0, 10);
}
$clearanceReviewed = '';
if (!empty($personnel['clearance_reviewed_at'])) {
    $clearanceReviewed = substr((string) $personnel['clearance_reviewed_at'], 0, 10);
}
$birthDate = '';
$legalBirth = trim((string) ($legal['birth_date'] ?? ''));
$profileBirth = trim((string) ($profile['birth_date'] ?? ''));
$birthSrc = $legalBirth !== '' && !str_starts_with($legalBirth, '0000-00-00') ? $legalBirth : $profileBirth;
if ($birthSrc !== '' && !str_starts_with($birthSrc, '0000-00-00')) {
    $birthDate = substr($birthSrc, 0, 10);
}

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$keepSelect = static function (array $options, string $current): array {
    if ($current !== '' && !array_key_exists($current, $options)) {
        $options[$current] = $current;
    }

    return $options;
};

$personUrl = $email !== ''
    ? url('admin/users/person') . '?email=' . rawurlencode($email)
    : url('admin/users');
$updateUrl = url('admin/users/' . $uid . '/update');
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');

$fieldClass = 'mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm';
$labelClass = 'text-[10px] font-bold uppercase tracking-wider text-slate-500';

if ($uid < 1) {
    echo '<p class="p-6 text-sm text-slate-700">Fiche introuvable.</p>';

    return;
}
?>
<div class="min-h-0 flex-1 bg-slate-50">
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8 space-y-6">
        <nav class="text-sm text-slate-500">
            <a href="<?= $h(url('admin')) ?>" class="font-semibold text-emerald-800 hover:text-emerald-950">Administration du site</a>
            <span class="mx-2" aria-hidden="true">/</span>
            <a href="<?= $h(url('admin/users')) ?>" class="font-semibold text-emerald-800 hover:text-emerald-950">Comptes utilisateurs</a>
            <span class="mx-2" aria-hidden="true">/</span>
            <a href="<?= $h($personUrl) ?>" class="font-semibold text-emerald-800 hover:text-emerald-950">Dossier personne</a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-slate-800">Modifier la fiche</span>
        </nav>

        <header class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Administration du site</p>
            <h1 class="text-2xl font-black text-slate-900">Modifier la fiche complète</h1>
            <p class="mt-1 text-sm text-slate-600">
                Tous les champs du compte, du contact et du dossier personnel.
                <?php if ($tenantName !== ''): ?>
                    Communauté : <strong class="font-semibold text-slate-800"><?= $h($tenantName) ?></strong>.
                <?php endif; ?>
                L’identifiant plateforme n’est pas modifiable.
            </p>
        </header>

        <?php if ($flashOk): ?>
            <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-950" role="status"><?= $h((string) $flashOk) ?></p>
        <?php endif; ?>
        <?php if ($flashErr): ?>
            <p class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-950" role="alert"><?= $h((string) $flashErr) ?></p>
        <?php endif; ?>

        <form method="post" action="<?= $h($updateUrl) ?>" class="space-y-6">
            <?= \App\Core\Csrf::field() ?>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Compte et accès</h2>
                    <p class="text-sm text-slate-600">Adresse de connexion, mot de passe et état du compte — réservés à l’administration du site.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="email" class="<?= $labelClass ?>">Adresse e-mail</label>
                        <input type="email" id="email" name="email" required maxlength="190" autocomplete="email"
                               value="<?= $h($email) ?>" class="<?= $fieldClass ?>">
                    </div>
                    <div>
                        <label for="password" class="<?= $labelClass ?>">Nouveau mot de passe</label>
                        <input type="password" id="password" name="password" minlength="6" autocomplete="new-password"
                               class="<?= $fieldClass ?>" placeholder="Laisser vide pour ne pas changer">
                        <p class="mt-1 text-xs text-slate-500">Au moins 6 caractères si vous en définissez un nouveau.</p>
                    </div>
                    <div>
                        <label for="status" class="<?= $labelClass ?>">Statut du compte</label>
                        <select id="status" name="status" class="<?= $fieldClass ?>">
                            <?php foreach ($keepSelect($statusOptions, $ust) as $val => $lab): ?>
                                <option value="<?= $h((string) $val) ?>" <?= $ust === (string) $val ? 'selected' : '' ?>><?= $h($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <p class="<?= $labelClass ?>">Identifiant plateforme</p>
                        <p class="mt-1 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-sm text-slate-800"><?= $athenaId !== '' ? $h($athenaId) : '—' ?></p>
                        <p class="mt-1 text-xs text-slate-500">Attribué une fois pour toutes, non modifiable.</p>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Identité du personnage</h2>
                    <p class="text-sm text-slate-600">Prénom, nom, indicatif et présentation utilisés dans les listes, le dossier et le forum.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="first_name" class="<?= $labelClass ?>">Prénom</label>
                        <input type="text" id="first_name" name="first_name" required maxlength="100" autocomplete="off"
                               value="<?= $h(trim((string) ($profile['first_name'] ?? ''))) ?>" class="<?= $fieldClass ?>">
                    </div>
                    <div>
                        <label for="last_name" class="<?= $labelClass ?>">Nom</label>
                        <input type="text" id="last_name" name="last_name" required maxlength="100" autocomplete="off"
                               value="<?= $h(trim((string) ($profile['last_name'] ?? ''))) ?>" class="<?= $fieldClass ?>">
                    </div>
                    <div>
                        <label for="callsign" class="<?= $labelClass ?>">Indicatif principal</label>
                        <input type="text" id="callsign" name="callsign" maxlength="80" autocomplete="off"
                               value="<?= $h(trim((string) ($user['callsign'] ?? $personnel['callsign'] ?? ''))) ?>" class="<?= $fieldClass ?>">
                    </div>
                    <div>
                        <label for="nickname_primary" class="<?= $labelClass ?>">Surnom principal</label>
                        <input type="text" id="nickname_primary" name="nickname_primary" maxlength="120" autocomplete="off"
                               value="<?= $h(trim((string) ($personnel['nickname_primary'] ?? ''))) ?>" class="<?= $fieldClass ?>">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="bio" class="<?= $labelClass ?>">Présentation</label>
                        <textarea id="bio" name="bio" rows="3" class="<?= $fieldClass ?>" maxlength="2000"><?= $h((string) ($profile['bio'] ?? '')) ?></textarea>
                    </div>
                    <div>
                        <label for="motto" class="<?= $labelClass ?>">Devise</label>
                        <input type="text" id="motto" name="motto" maxlength="255"
                               value="<?= $h((string) ($personnel['motto'] ?? '')) ?>" class="<?= $fieldClass ?>">
                    </div>
                    <div>
                        <label for="nicknames_text" class="<?= $labelClass ?>">Autres surnoms</label>
                        <textarea id="nicknames_text" name="nicknames_text" rows="3" class="<?= $fieldClass ?>" placeholder="Un surnom par ligne"><?= $h((string) ($pack['nicknames_text'] ?? '')) ?></textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <p class="<?= $labelClass ?>">Indicatifs supplémentaires</p>
                        <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <?php foreach ($extraCallsigns as $i => $alias): ?>
                                <div>
                                    <label for="extra_callsign_<?= (int) $i ?>" class="text-[10px] font-semibold text-slate-500">Alias <?= (int) $i + 1 ?></label>
                                    <input type="text" id="extra_callsign_<?= (int) $i ?>" name="extra_callsigns[]" maxlength="100" autocomplete="off"
                                           value="<?= $h((string) $alias) ?>" class="<?= $fieldClass ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Identité civile et contact</h2>
                    <p class="text-sm text-slate-600">Coordonnées réelles et préférences d’interface. Distinctes du personnage.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="civil_first_name" class="<?= $labelClass ?>">Prénom civil</label>
                        <input type="text" id="civil_first_name" name="civil_first_name" maxlength="100" autocomplete="off"
                               value="<?= $h(trim((string) ($legal['first_name'] ?? ''))) ?>" class="<?= $fieldClass ?>">
                    </div>
                    <div>
                        <label for="civil_last_name" class="<?= $labelClass ?>">Nom civil</label>
                        <input type="text" id="civil_last_name" name="civil_last_name" maxlength="100" autocomplete="off"
                               value="<?= $h(trim((string) ($legal['last_name'] ?? ''))) ?>" class="<?= $fieldClass ?>">
                    </div>
                    <div>
                        <label for="phone" class="<?= $labelClass ?>">Téléphone</label>
                        <input type="tel" id="phone" name="phone" maxlength="40" autocomplete="off"
                               value="<?= $h(trim((string) ($legal['phone'] ?? $profile['phone'] ?? ''))) ?>" class="<?= $fieldClass ?>">
                    </div>
                    <div>
                        <label for="birth_date" class="<?= $labelClass ?>">Date de naissance</label>
                        <input type="date" id="birth_date" name="birth_date" value="<?= $h($birthDate) ?>" class="<?= $fieldClass ?>">
                    </div>
                    <div>
                        <label for="civil_nationality" class="<?= $labelClass ?>">Nationalité civile</label>
                        <input type="text" id="civil_nationality" name="civil_nationality" maxlength="80" autocomplete="off"
                               value="<?= $h(trim((string) ($legal['nationality'] ?? $profile['nationality'] ?? ''))) ?>" class="<?= $fieldClass ?>">
                    </div>
                    <div>
                        <label for="discord_handle" class="<?= $labelClass ?>">Identifiant Discord</label>
                        <input type="text" id="discord_handle" name="discord_handle" maxlength="80" autocomplete="off"
                               value="<?= $h(trim((string) ($profile['discord_handle'] ?? ''))) ?>" class="<?= $fieldClass ?>">
                    </div>
                    <div>
                        <label for="timezone" class="<?= $labelClass ?>">Fuseau horaire</label>
                        <select id="timezone" name="timezone" class="<?= $fieldClass ?>">
                            <?php
                            $tzCur = trim((string) ($profile['timezone'] ?? ''));
                            foreach ($keepSelect($timezoneOptions, $tzCur) as $val => $lab):
                            ?>
                                <option value="<?= $h((string) $val) ?>" <?= $tzCur === (string) $val ? 'selected' : '' ?>><?= $h($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="language" class="<?= $labelClass ?>">Langue de l’interface</label>
                        <select id="language" name="language" class="<?= $fieldClass ?>">
                            <?php
                            $langCur = trim((string) ($profile['language'] ?? ''));
                            foreach ($keepSelect($languageOptions, $langCur) as $val => $lab):
                            ?>
                                <option value="<?= $h((string) $val) ?>" <?= $langCur === (string) $val ? 'selected' : '' ?>><?= $h($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="country_of_residence" class="<?= $labelClass ?>">Pays de résidence</label>
                        <input type="text" id="country_of_residence" name="country_of_residence" maxlength="80" autocomplete="off"
                               value="<?= $h(trim((string) ($profile['country_of_residence'] ?? ''))) ?>" class="<?= $fieldClass ?>">
                    </div>
                    <div>
                        <label for="public_flag_country_code" class="<?= $labelClass ?>">Drapeau sur la fiche</label>
                        <select id="public_flag_country_code" name="public_flag_country_code" class="<?= $fieldClass ?>">
                            <?php
                            $flagCur = strtoupper(trim((string) ($profile['public_flag_country_code'] ?? '')));
                            foreach ($keepSelect($flagOptions, $flagCur) as $val => $lab):
                            ?>
                                <option value="<?= $h((string) $val) ?>" <?= $flagCur === strtoupper((string) $val) ? 'selected' : '' ?>><?= $h($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Dossier personnel</h2>
                    <p class="text-sm text-slate-600">Détails du personnage, ancienneté et matricule.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="blood_type" class="<?= $labelClass ?>">Groupe sanguin</label>
                        <select id="blood_type" name="blood_type" class="<?= $fieldClass ?>">
                            <?php
                            $bt = trim((string) ($personnel['blood_type'] ?? ''));
                            foreach ($keepSelect($bloodOptions, $bt) as $val => $lab):
                            ?>
                                <option value="<?= $h((string) $val) ?>" <?= $bt === (string) $val ? 'selected' : '' ?>><?= $h($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="sex" class="<?= $labelClass ?>">Sexe</label>
                        <select id="sex" name="sex" class="<?= $fieldClass ?>">
                            <?php
                            $sexCur = trim((string) ($personnel['sex'] ?? ''));
                            foreach ($keepSelect($sexOptions, $sexCur) as $val => $lab):
                            ?>
                                <option value="<?= $h((string) $val) ?>" <?= $sexCur === (string) $val ? 'selected' : '' ?>><?= $h($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="family_situation" class="<?= $labelClass ?>">Situation familiale</label>
                        <select id="family_situation" name="family_situation" class="<?= $fieldClass ?>">
                            <?php
                            $famCur = trim((string) ($personnel['family_situation'] ?? ''));
                            foreach ($keepSelect($familyOptions, $famCur) as $val => $lab):
                            ?>
                                <option value="<?= $h((string) $val) ?>" <?= $famCur === (string) $val ? 'selected' : '' ?>><?= $h($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="weight_kg" class="<?= $labelClass ?>">Poids (kg)</label>
                        <input type="number" id="weight_kg" name="weight_kg" min="20" max="300"
                               value="<?= $h((string) ($personnel['weight_kg'] ?? '')) ?>" class="<?= $fieldClass ?>">
                    </div>
                    <div>
                        <label for="birth_place" class="<?= $labelClass ?>">Lieu de naissance (personnage)</label>
                        <input type="text" id="birth_place" name="birth_place" maxlength="150" autocomplete="off"
                               value="<?= $h((string) ($personnel['birth_place'] ?? '')) ?>" class="<?= $fieldClass ?>">
                    </div>
                    <div>
                        <label for="nationality_rp" class="<?= $labelClass ?>">Nationalité du personnage</label>
                        <input type="text" id="nationality_rp" name="nationality_rp" maxlength="100" autocomplete="off"
                               value="<?= $h((string) ($personnel['nationality'] ?? '')) ?>" class="<?= $fieldClass ?>">
                    </div>
                    <div>
                        <label for="languages" class="<?= $labelClass ?>">Langues parlées par le personnage</label>
                        <input type="text" id="languages" name="languages" maxlength="255" autocomplete="off"
                               value="<?= $h((string) ($personnel['languages'] ?? '')) ?>" class="<?= $fieldClass ?>">
                    </div>
                    <div>
                        <label for="operator_status" class="<?= $labelClass ?>">Statut opérateur</label>
                        <input type="text" id="operator_status" name="operator_status" maxlength="160" autocomplete="off"
                               value="<?= $h((string) ($personnel['operator_status'] ?? '')) ?>" class="<?= $fieldClass ?>">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="operator_tags" class="<?= $labelClass ?>">Spécialités</label>
                        <input type="text" id="operator_tags" name="operator_tags" maxlength="255" autocomplete="off"
                               value="<?= $h((string) ($personnel['operator_tags'] ?? '')) ?>" class="<?= $fieldClass ?>">
                    </div>
                    <div>
                        <label for="enlistment_date" class="<?= $labelClass ?>">Arrivée dans la communauté</label>
                        <input type="date" id="enlistment_date" name="enlistment_date" value="<?= $h($enlistment) ?>" class="<?= $fieldClass ?>">
                    </div>
                    <div>
                        <label for="matricule_internal" class="<?= $labelClass ?>">Matricule</label>
                        <input type="text" id="matricule_internal" name="matricule_internal" maxlength="64" autocomplete="off"
                               value="<?= $h($matricule) ?>" class="<?= $fieldClass ?>">
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Grade, doctrine et affectation</h2>
                    <p class="text-sm text-slate-600">Grade de la communauté, unité d’affectation et habilitation.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="nationality_code" class="<?= $labelClass ?>">Doctrine</label>
                        <select id="nationality_code" name="nationality_code" class="<?= $fieldClass ?>">
                            <option value="">Non renseignée</option>
                            <?php
                            $docCur = trim((string) ($user['nationality_code'] ?? ''));
                            foreach ($doctrineOptions as $val => $lab):
                            ?>
                                <option value="<?= $h((string) $val) ?>" <?= $docCur === (string) $val ? 'selected' : '' ?>><?= $h($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="professional_category_code" class="<?= $labelClass ?>">Catégorie de personnel</label>
                        <select id="professional_category_code" name="professional_category_code" class="<?= $fieldClass ?>">
                            <option value="">Non renseignée</option>
                            <?php
                            $catCur = trim((string) ($user['professional_category_code'] ?? ''));
                            foreach ($gradeCategories as $c):
                                $code = (string) ($c['code'] ?? '');
                            ?>
                                <option value="<?= $h($code) ?>" <?= $catCur === $code ? 'selected' : '' ?>><?= $h((string) ($c['label'] ?? $code)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="grade_id" class="<?= $labelClass ?>">Grade</label>
                        <select id="grade_id" name="grade_id" class="<?= $fieldClass ?>">
                            <option value="">Aucun grade</option>
                            <?php foreach ($grades as $g): ?>
                                <option value="<?= (int) ($g['id'] ?? 0) ?>" <?= (int) ($user['grade_id'] ?? 0) === (int) ($g['id'] ?? 0) ? 'selected' : '' ?>>
                                    <?= $h((string) ($g['label_long'] ?? $g['name'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="preferred_grade_format" class="<?= $labelClass ?>">Format d’affichage du grade</label>
                        <select id="preferred_grade_format" name="preferred_grade_format" class="<?= $fieldClass ?>">
                            <?php
                            $fmtCur = trim((string) ($user['preferred_grade_format'] ?? 'classic'));
                            foreach ($gradeFormatOptions as $val => $lab):
                            ?>
                                <option value="<?= $h((string) $val) ?>" <?= $fmtCur === (string) $val ? 'selected' : '' ?>><?= $h($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="rank_display" class="<?= $labelClass ?>">Grade ou titre affiché</label>
                        <input type="text" id="rank_display" name="rank_display" maxlength="100"
                               value="<?= $h((string) ($personnel['rank_display'] ?? '')) ?>" class="<?= $fieldClass ?>">
                    </div>
                    <div>
                        <label for="rank_display_override" class="<?= $labelClass ?>">Libellé court personnalisé</label>
                        <input type="text" id="rank_display_override" name="rank_display_override" maxlength="100"
                               value="<?= $h((string) ($personnel['rank_display_override'] ?? '')) ?>" class="<?= $fieldClass ?>">
                    </div>
                    <div>
                        <label for="primary_unit_id" class="<?= $labelClass ?>">Unité d’affectation</label>
                        <select id="primary_unit_id" name="primary_unit_id" class="<?= $fieldClass ?>">
                            <option value="">Aucune unité</option>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?= (int) ($unit['id'] ?? 0) ?>" <?= (int) ($personnel['primary_unit_id'] ?? 0) === (int) ($unit['id'] ?? 0) ? 'selected' : '' ?>>
                                    <?= $h((string) ($unit['name'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="assignment_role" class="<?= $labelClass ?>">Rôle dans l’unité</label>
                        <input type="text" id="assignment_role" name="assignment_role" maxlength="120" autocomplete="off"
                               value="<?= $h((string) ($personnel['primary_role'] ?? '')) ?>" class="<?= $fieldClass ?>">
                    </div>
                    <div>
                        <label for="clearance_level" class="<?= $labelClass ?>">Habilitation</label>
                        <select id="clearance_level" name="clearance_level" class="<?= $fieldClass ?>">
                            <option value="">Non renseignée</option>
                            <?php
                            $clCur = trim((string) ($personnel['clearance_level'] ?? ''));
                            foreach ($clearanceOptions as $val => $lab):
                            ?>
                                <option value="<?= $h((string) $val) ?>" <?= $clCur === (string) $val ? 'selected' : '' ?>><?= $h($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="clearance_reviewed_at" class="<?= $labelClass ?>">Dernière revue d’habilitation</label>
                        <input type="date" id="clearance_reviewed_at" name="clearance_reviewed_at" value="<?= $h($clearanceReviewed) ?>" class="<?= $fieldClass ?>">
                    </div>
                    <div>
                        <label for="readiness_score" class="<?= $labelClass ?>">Indice de préparation (0 à 100)</label>
                        <input type="number" id="readiness_score" name="readiness_score" min="0" max="100"
                               value="<?= $h((string) ($personnel['readiness_score'] ?? '')) ?>" class="<?= $fieldClass ?>">
                    </div>
                    <div class="flex items-end">
                        <label class="flex items-start gap-2 text-sm text-slate-800">
                            <input type="checkbox" name="deployable" value="1" class="mt-1 rounded border-slate-300"
                                   <?= !isset($personnel['deployable']) || (int) $personnel['deployable'] === 1 ? 'checked' : '' ?>>
                            <span>Disponible pour un déploiement</span>
                        </label>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Liaison Steam</h2>
                    <p class="text-sm text-slate-600">Rattachez le compte Steam pour le retrouver sur la carte et parmi les opérateurs en liaison.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="steam_id" class="<?= $labelClass ?>">Identifiant Steam</label>
                        <input type="text" id="steam_id" name="steam_id" maxlength="512" autocomplete="off"
                               value="<?= $h(trim((string) ($user['steam_id'] ?? ''))) ?>" class="<?= $fieldClass ?>"
                               placeholder="Numéro Steam, format classique, ou adresse du profil">
                        <p class="mt-1 text-xs text-slate-500">Laissez vide pour retirer la liaison.</p>
                    </div>
                    <div>
                        <label class="flex items-start gap-2 text-sm text-slate-800">
                            <input type="checkbox" name="sync_steam_profile" value="1" class="mt-1 rounded border-slate-300"
                                   <?= !empty($pack['steam_configured']) ? '' : 'disabled' ?>>
                            <span>Synchroniser la photo du compte à l’enregistrement</span>
                        </label>
                        <?php if (empty($pack['steam_configured'])): ?>
                            <p class="mt-1 text-xs text-slate-500">La lecture du profil public Steam n’est pas configurée : l’identifiant peut tout de même être enregistré.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Équipement</h2>
                    <p class="text-sm text-slate-600">Classe, kit, radio, véhicule et spécialité d’armement du dossier.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="equipment_class" class="<?= $labelClass ?>">Classe d’équipement</label>
                        <input type="text" id="equipment_class" name="equipment_class" maxlength="120"
                               value="<?= $h((string) ($personnel['equipment_class'] ?? '')) ?>" class="<?= $fieldClass ?>">
                    </div>
                    <div>
                        <label for="kit_assigned" class="<?= $labelClass ?>">Kit attribué</label>
                        <input type="text" id="kit_assigned" name="kit_assigned" maxlength="120"
                               value="<?= $h((string) ($personnel['kit_assigned'] ?? '')) ?>" class="<?= $fieldClass ?>">
                    </div>
                    <div>
                        <label for="radio_assigned" class="<?= $labelClass ?>">Radio attribuée</label>
                        <input type="text" id="radio_assigned" name="radio_assigned" maxlength="120"
                               value="<?= $h((string) ($personnel['radio_assigned'] ?? '')) ?>" class="<?= $fieldClass ?>">
                    </div>
                    <div>
                        <label for="vehicle_authorized" class="<?= $labelClass ?>">Véhicule autorisé</label>
                        <input type="text" id="vehicle_authorized" name="vehicle_authorized" maxlength="120"
                               value="<?= $h((string) ($personnel['vehicle_authorized'] ?? '')) ?>" class="<?= $fieldClass ?>">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="weapon_specialty" class="<?= $labelClass ?>">Spécialité d’armement</label>
                        <input type="text" id="weapon_specialty" name="weapon_specialty" maxlength="120"
                               value="<?= $h((string) ($personnel['weapon_specialty'] ?? '')) ?>" class="<?= $fieldClass ?>">
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Rôles dans la communauté</h2>
                    <p class="text-sm text-slate-600">Cochez les rôles à attribuer. Les droits effectifs sont l’union de tous les rôles cochés.</p>
                </div>
                <?php if ($roles === []): ?>
                    <p class="text-sm text-slate-500">Aucun rôle n’est encore défini pour cette communauté.</p>
                <?php else: ?>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <?php foreach ($roles as $role):
                            $rid = (int) ($role['id'] ?? 0);
                            if ($rid < 1) {
                                continue;
                            }
                            $rname = trim((string) ($role['name'] ?? ''));
                            if ($rname === '') {
                                $rname = 'Rôle sans nom';
                            }
                            ?>
                            <label class="flex items-start gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800">
                                <input type="checkbox" name="org_role_ids[]" value="<?= $rid ?>" class="mt-0.5 rounded border-slate-300"
                                       <?= in_array($rid, $selectedRoleIds, true) ? 'checked' : '' ?>>
                                <span><?= $h($rname) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="org_role_ids_present" value="1">
                <?php endif; ?>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Notes de commandement</h2>
                    <p class="text-sm text-slate-600">Notes internes visibles des responsables, pas du membre.</p>
                </div>
                <div>
                    <label for="command_notes" class="<?= $labelClass ?>">Notes</label>
                    <textarea id="command_notes" name="command_notes" rows="4" class="<?= $fieldClass ?>"><?= $h((string) ($personnel['command_notes'] ?? $extras['admin_notes'] ?? '')) ?></textarea>
                </div>
            </section>

            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                <a href="<?= $h($personUrl) ?>" class="text-sm font-semibold text-slate-600 hover:underline">Annuler</a>
                <button type="submit" class="rounded-lg bg-emerald-800 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-900">
                    Enregistrer la fiche
                </button>
            </div>
        </form>
    </div>
</div>
