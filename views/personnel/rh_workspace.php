<?php
declare(strict_types=1);
/**
 * Mes démarches — espace opérateur du back-office (ex-/personnel/mon-espace-rh).
 * Trois démarches (absence, évolution, mobilité) à gauche ; à faire, documents et
 * ancienneté à droite. Les blocs « programmes de préqualification » et
 * « évolutions liées à vos programmes » (informations de déploiement de la
 * plateforme, sans rapport avec les démarches RH) ont été retirés.
 */
$trainingAllowed = !empty($rhTrainingAllowed);
$charterReady = !empty($rhCharterReady);
$charterAccepted = !empty($rhCharterAccepted);
$seniorityLines = is_array($rhSeniorityLines ?? null) ? $rhSeniorityLines : [];
$dossierCompleteness = is_array($rhDossierCompleteness ?? null) ? $rhDossierCompleteness : ['score' => 0, 'filled' => 0, 'total' => 0, 'missing' => []];
$dossierScore = (int) ($dossierCompleteness['score'] ?? 0);
$dossierMissing = is_array($dossierCompleteness['missing'] ?? null) ? $dossierCompleteness['missing'] : [];
$greetingName = trim((string) ($rhGreetingName ?? ''));
$rhWorkspaceCsrf = htmlspecialchars((string) ($rhWorkspaceCsrf ?? ''), ENT_QUOTES, 'UTF-8');
$absencesSchemaReady = !empty($rhAbsencesSchemaReady);
$personnelAbsences = is_array($rhPersonnelAbsences ?? null) ? $rhPersonnelAbsences : [];
$activeAbsences = is_array($rhActiveAbsences ?? null) ? $rhActiveAbsences : [];
$absenceReasonLabels = is_array($rhAbsenceReasonLabels ?? null) ? $rhAbsenceReasonLabels : [];
$mobilitySchemaReady = !empty($rhMobilitySchemaReady);
$myMobility = is_array($rhMyMobility ?? null) ? $rhMyMobility : [];
$mobilityTypeLabels = is_array($rhMobilityTypeLabels ?? null) ? $rhMobilityTypeLabels : [];
$mobilityStatusLabels = is_array($rhMobilityStatusLabels ?? null) ? $rhMobilityStatusLabels : [];
$hrDocsSchemaReady = !empty($rhHrDocsSchemaReady);
$myHrDocs = is_array($rhMyHrDocs ?? null) ? $rhMyHrDocs : [];
$hrDocTypeLabels = is_array($rhHrDocTypeLabels ?? null) ? $rhHrDocTypeLabels : [];
$elevationCatalog = is_array($rhElevationCatalog ?? null) ? $rhElevationCatalog : [];
$elevationCooldown = (int) ($rhElevationCooldownSeconds ?? 0);
$elevationHasRecipients = !empty($rhElevationHasRecipients);

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$formatAbsenceDate = static function (?string $ymd): string {
    if ($ymd === null || $ymd === '') {
        return '';
    }
    $ts = strtotime($ymd);

    return $ts !== false ? date('d/m/Y', $ts) : $ymd;
};

$absencePeriodLabel = static function (array $row) use ($formatAbsenceDate): string {
    $start = $formatAbsenceDate((string) ($row['starts_on'] ?? ''));
    $endRaw = $row['ends_on'] ?? null;
    if ($endRaw === null || $endRaw === '') {
        return $start !== '' ? ('Depuis le ' . $start . ', sans date de retour') : 'Sans date de retour';
    }
    $end = $formatAbsenceDate((string) $endRaw);
    if ($start !== '' && $end !== '' && $start === $end) {
        return 'Le ' . $start;
    }

    return 'Du ' . ($start !== '' ? $start : '…') . ' au ' . ($end !== '' ? $end : '…');
};

$mobilityTone = static function (string $status): string {
    return match ($status) {
        'approved', 'accepted', 'done', 'completed' => 'ok',
        'rejected', 'refused', 'declined', 'cancelled' => 'muted',
        default => 'warn',
    };
};

// « À faire » : ce qui attend une action du membre, en tête de colonne.
$todos = [];
if ($trainingAllowed && $charterReady && !$charterAccepted) {
    $todos[] = [
        'title' => 'Accepter la charte des formations',
        'text' => 'Nécessaire avant de suivre certains parcours de formation.',
        'href' => url('account/charte-formations'),
        'cta' => 'Lire la charte',
    ];
}
if ($dossierMissing !== []) {
    $todos[] = [
        'title' => 'Compléter votre fiche (' . $dossierScore . ' %)',
        'text' => 'Il manque ' . count($dossierMissing) . ' élément' . (count($dossierMissing) > 1 ? 's' : '') . ' : ' . implode(', ', array_map('strval', $dossierMissing)) . '.',
        'href' => url('personnel/me/edit'),
        'cta' => 'Compléter ma fiche',
    ];
}

$elevationOpen = $elevationCooldown <= 0 && $elevationHasRecipients;
$cooldownHours = $elevationCooldown > 0 ? max(1, (int) ceil($elevationCooldown / 3600)) : 0;
$pendingMobility = array_values(array_filter($myMobility, static fn (array $m): bool => $mobilityTone((string) ($m['status'] ?? '')) === 'warn'));

$rhFlashSuccess = \App\Core\Session::getFlash('success');
$rhFlashError = \App\Core\Session::getFlash('error');
?>
<div class="dm" id="contenu-mes-demarches">

    <?php if ($rhFlashSuccess || $rhFlashError): ?>
    <div class="dm-flashes">
        <?php if ($rhFlashSuccess): ?><div class="dm-flash is-ok" role="status"><?= $e($rhFlashSuccess) ?></div><?php endif; ?>
        <?php if ($rhFlashError): ?><div class="dm-flash is-error" role="alert"><?= $e($rhFlashError) ?></div><?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Accès directs : l’état de chaque démarche en un coup d’œil -->
    <nav class="dm-quick" aria-label="Démarches">
        <?php if ($absencesSchemaReady): ?>
        <a class="dm-quick__item<?= $activeAbsences !== [] ? ' is-warn' : '' ?>" href="#absences">
            <span class="dm-quick__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4M9 15l6 0"/></svg></span>
            <span class="dm-quick__text">
                <b>Absence</b>
                <small><?= $activeAbsences !== [] ? 'Une absence est en cours' : 'Prévenir l’encadrement' ?></small>
            </span>
        </a>
        <?php endif; ?>
        <a class="dm-quick__item<?= !$elevationOpen ? ' is-muted' : '' ?>" href="#elevation">
            <span class="dm-quick__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m6 15 6-6 6 6"/><path d="m6 20 6-6 6 6"/></svg></span>
            <span class="dm-quick__text">
                <b>Évolution</b>
                <small><?= $elevationCooldown > 0 ? 'Demande déjà envoyée' : 'Grade, rôle, fonction, affectation' ?></small>
            </span>
        </a>
        <?php if ($mobilitySchemaReady): ?>
        <a class="dm-quick__item<?= $pendingMobility !== [] ? ' is-info' : '' ?>" href="#mobilite">
            <span class="dm-quick__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h13l-3-3M20 17H7l3 3"/></svg></span>
            <span class="dm-quick__text">
                <b>Mobilité</b>
                <small><?= $pendingMobility !== [] ? count($pendingMobility) . ' demande' . (count($pendingMobility) > 1 ? 's' : '') . ' en attente' : 'Changer de poste ou d’unité' ?></small>
            </span>
        </a>
        <?php endif; ?>
    </nav>

    <div class="dm-grid">
        <div class="dm-main">

            <?php if ($absencesSchemaReady): ?>
            <section id="absences" class="dm-card" aria-labelledby="dm-absences-title">
                <header class="dm-card__head">
                    <div>
                        <h2 id="dm-absences-title" class="dm-card__title">Déclarer une absence</h2>
                        <p class="dm-card__sub">L’encadrement est prévenu et l’absence apparaît sur votre fiche.</p>
                    </div>
                    <?php if ($activeAbsences !== []): ?><span class="dm-pill is-warn">Absence en cours</span><?php endif; ?>
                </header>

                <?php foreach ($activeAbsences as $active):
                    $aReason = (string) ($active['reason'] ?? 'autre');
                    $aNote = trim((string) ($active['note'] ?? ''));
                    ?>
                <div class="dm-current" role="status">
                    <div>
                        <p class="dm-current__title"><?= $e($absencePeriodLabel($active)) ?></p>
                        <p class="dm-current__text"><?= $e($absenceReasonLabels[$aReason] ?? 'Autre') ?><?= $aNote !== '' ? ' — ' . $e($aNote) : '' ?></p>
                    </div>
                    <form method="post" action="<?= $e(url('personnel/mon-espace-rh/absences/annuler')) ?>">
                        <input type="hidden" name="_csrf_token" value="<?= $rhWorkspaceCsrf ?>">
                        <input type="hidden" name="absence_id" value="<?= (int) ($active['id'] ?? 0) ?>">
                        <button type="submit" class="dm-btn">Je suis de retour</button>
                    </form>
                </div>
                <?php endforeach; ?>

                <form method="post" action="<?= $e(url('personnel/mon-espace-rh/absences')) ?>" class="dm-form" id="rh-absence-form">
                    <input type="hidden" name="_csrf_token" value="<?= $rhWorkspaceCsrf ?>">
                    <div class="dm-row">
                        <div class="dm-field">
                            <label for="absence_starts_on">À partir du</label>
                            <input type="date" id="absence_starts_on" name="starts_on" required value="<?= $e(date('Y-m-d')) ?>">
                        </div>
                        <div class="dm-field">
                            <label for="absence_reason">Motif</label>
                            <select id="absence_reason" name="reason">
                                <?php foreach ($absenceReasonLabels as $reasonValue => $reasonLabel): ?>
                                <option value="<?= $e($reasonValue) ?>"<?= (string) $reasonValue === 'personnel' ? ' selected' : '' ?>><?= $e($reasonLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <fieldset class="dm-choice">
                        <legend>Date de retour</legend>
                        <label class="dm-choice__opt">
                            <input type="radio" name="has_duration" value="1" checked data-absence-duration="dated">
                            <span><b>Je la connais</b><small>L’absence se termine toute seule.</small></span>
                        </label>
                        <label class="dm-choice__opt">
                            <input type="radio" name="has_duration" value="0" data-absence-duration="open">
                            <span><b>Je ne sais pas encore</b><small>Vous indiquerez votre retour ici.</small></span>
                        </label>
                    </fieldset>
                    <div class="dm-field dm-field--narrow" id="absence-ends-wrap">
                        <label for="absence_ends_on">Retour prévu le</label>
                        <input type="date" id="absence_ends_on" name="ends_on">
                    </div>
                    <div class="dm-field">
                        <label for="absence_note">Précision <span class="dm-optional">(facultatif)</span></label>
                        <textarea id="absence_note" name="note" rows="2" maxlength="500" placeholder="Ex. : indisponible les soirs de semaine jusqu’à nouvel ordre"></textarea>
                    </div>
                    <div class="dm-actions">
                        <button type="submit" class="dm-btn dm-btn--primary">Prévenir l’encadrement</button>
                    </div>
                </form>

                <?php if ($personnelAbsences !== []): ?>
                <details class="dm-history">
                    <summary>Historique des absences <span class="dm-count"><?= count($personnelAbsences) ?></span></summary>
                    <ul>
                        <?php foreach ($personnelAbsences as $row):
                            $st = (string) ($row['status'] ?? 'active');
                            $today = date('Y-m-d');
                            $starts = (string) ($row['starts_on'] ?? '');
                            $ends = $row['ends_on'] ?? null;
                            $isActiveRow = $st === 'active';
                            $coversToday = $isActiveRow && $starts <= $today && ($ends === null || $ends === '' || (string) $ends >= $today);
                            [$statusLabel, $tone] = !$isActiveRow ? ['Annulée', 'muted'] : ($coversToday ? ['En cours', 'warn'] : ($starts > $today ? ['À venir', 'info'] : ['Terminée', 'ok']));
                            ?>
                        <li>
                            <div>
                                <p class="dm-history__title"><?= $e($absencePeriodLabel($row)) ?></p>
                                <p class="dm-history__text"><?= $e($absenceReasonLabels[(string) ($row['reason'] ?? 'autre')] ?? 'Autre') ?></p>
                            </div>
                            <span class="dm-pill is-<?= $tone ?>"><?= $e($statusLabel) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
                <?php endif; ?>
            </section>
            <script>
            (function () {
                var form = document.getElementById('rh-absence-form');
                if (!form) return;
                var wrap = document.getElementById('absence-ends-wrap');
                var ends = document.getElementById('absence_ends_on');
                function sync() {
                    var dated = form.querySelector('input[name="has_duration"][value="1"]');
                    var on = dated && dated.checked;
                    if (wrap) wrap.hidden = !on;
                    if (ends) {
                        ends.required = !!on;
                        if (!on) ends.value = '';
                    }
                }
                form.querySelectorAll('input[name="has_duration"]').forEach(function (el) { el.addEventListener('change', sync); });
                sync();
            })();
            </script>
            <?php endif; ?>

            <section id="elevation" class="dm-card" aria-labelledby="dm-elevation-title">
                <header class="dm-card__head">
                    <div>
                        <h2 id="dm-elevation-title" class="dm-card__title">Demander une évolution</h2>
                        <p class="dm-card__sub">Un changement de grade, de rôle, de fonction ou d’affectation. L’encadrement décide ; rien ne change avant sa confirmation.</p>
                    </div>
                </header>
                <?php if ($elevationCooldown > 0): ?>
                <p class="dm-notice">Votre demande a bien été envoyée. Vous pourrez en faire une autre dans environ <?= $cooldownHours ?> heure<?= $cooldownHours > 1 ? 's' : '' ?>.</p>
                <?php elseif (!$elevationHasRecipients): ?>
                <p class="dm-notice">Personne n’est encore habilité à traiter ces demandes dans votre communauté. Adressez-vous directement à l’encadrement.</p>
                <?php else: ?>
                <form method="post" action="<?= $e(url('personnel/mon-espace-rh/elevation')) ?>" class="dm-form rh-elev-form">
                    <input type="hidden" name="_csrf_token" value="<?= $rhWorkspaceCsrf ?>">
                    <?php
                    $fieldIdPrefix = 'rh-elev';
                    $selectedKind = 'grade';
                    $includeUnit = true;
                    require base_path('views/admin/effectifs_workspace/partials/elevation_request_fields.php');
                    ?>
                    <div class="dm-actions">
                        <button type="submit" class="dm-btn dm-btn--primary">Envoyer la demande</button>
                    </div>
                </form>
                <?php endif; ?>
            </section>

            <?php if ($mobilitySchemaReady): ?>
            <section id="mobilite" class="dm-card" aria-labelledby="dm-mobility-title">
                <header class="dm-card__head">
                    <div>
                        <h2 id="dm-mobility-title" class="dm-card__title">Souhait de mobilité</h2>
                        <p class="dm-card__sub">Un poste ou une unité qui vous intéresse ? Dites-le à l’encadrement, avec vos raisons.</p>
                    </div>
                </header>
                <form method="post" action="<?= $e(url('personnel/mon-espace-rh/mobilite')) ?>" class="dm-form">
                    <input type="hidden" name="_csrf_token" value="<?= $rhWorkspaceCsrf ?>">
                    <div class="dm-row">
                        <div class="dm-field">
                            <label for="dm-mobility-type">Type de demande</label>
                            <select id="dm-mobility-type" name="request_type">
                                <?php foreach ($mobilityTypeLabels as $k => $lab): ?>
                                <option value="<?= $e($k) ?>"<?= $k === 'career_wish' ? ' selected' : '' ?>><?= $e($lab) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="dm-field">
                            <label for="dm-mobility-target">Poste ou unité visé</label>
                            <input type="text" id="dm-mobility-target" name="target_label" maxlength="200" placeholder="Ex. : chef d’équipe, opérateur radio…">
                        </div>
                    </div>
                    <div class="dm-field">
                        <label for="dm-mobility-motivation">Pourquoi ce changement ?</label>
                        <textarea id="dm-mobility-motivation" name="motivation" rows="3" maxlength="2000" placeholder="Vos motivations, vos disponibilités, ce que vous apporteriez…"></textarea>
                    </div>
                    <div class="dm-actions">
                        <button type="submit" class="dm-btn dm-btn--primary">Envoyer à l’encadrement</button>
                    </div>
                </form>

                <?php if ($myMobility !== []): ?>
                <div class="dm-requests">
                    <h3 class="dm-subtitle">Mes demandes</h3>
                    <ul>
                        <?php foreach ($myMobility as $m):
                            $mStatus = (string) ($m['status'] ?? '');
                            $tl = trim((string) ($m['target_label'] ?? ''));
                            $created = !empty($m['created_at']) ? date('d/m/Y', strtotime((string) $m['created_at'])) : '';
                            ?>
                        <li>
                            <div>
                                <p class="dm-history__title"><?= $e($mobilityTypeLabels[$m['request_type'] ?? ''] ?? ($m['request_type'] ?? '')) ?><?= $tl !== '' ? ' — ' . $e($tl) : '' ?></p>
                                <?php if ($created !== ''): ?><p class="dm-history__text">Envoyée le <?= $e($created) ?></p><?php endif; ?>
                            </div>
                            <span class="dm-pill is-<?= $mobilityTone($mStatus) ?>"><?= $e($mobilityStatusLabels[$mStatus] ?? $mStatus) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>
        </div>

        <aside class="dm-side" aria-label="À faire et documents">
            <section class="dm-card<?= $todos !== [] ? ' dm-card--todo' : '' ?>" aria-labelledby="dm-todo-title">
                <h2 id="dm-todo-title" class="dm-card__title">À faire</h2>
                <?php if ($todos === []): ?>
                <p class="dm-empty-ok">Rien à faire pour le moment. Votre dossier est à jour.</p>
                <?php else: ?>
                <ul class="dm-todos">
                    <?php foreach ($todos as $todo): ?>
                    <li>
                        <p class="dm-todos__title"><?= $e($todo['title']) ?></p>
                        <p class="dm-todos__text"><?= $e($todo['text']) ?></p>
                        <a class="dm-btn dm-btn--small" href="<?= $e($todo['href']) ?>"><?= $e($todo['cta']) ?></a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </section>

            <?php if ($hrDocsSchemaReady): ?>
            <section class="dm-card" aria-labelledby="dm-docs-title">
                <h2 id="dm-docs-title" class="dm-card__title">Documents partagés avec vous</h2>
                <?php if ($myHrDocs === []): ?>
                <p class="dm-muted">Aucun document pour l’instant. Ceux que l’encadrement vous transmet apparaîtront ici.</p>
                <?php else: ?>
                <ul class="dm-docs">
                    <?php foreach ($myHrDocs as $doc):
                        $docId = (int) ($doc['id'] ?? 0);
                        $stored = \App\Support\PersonnelHrDocumentStorage::isStoredPath((string) ($doc['file_path'] ?? ''));
                        ?>
                    <li>
                        <span class="dm-docs__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 3h9l4 4v14H6z"/><path d="M9 12h7M9 16h5"/></svg></span>
                        <div>
                            <p class="dm-docs__title"><?= $e($doc['title'] ?? '') ?></p>
                            <p class="dm-docs__type"><?= $e($hrDocTypeLabels[$doc['doc_type'] ?? ''] ?? ($doc['doc_type'] ?? '')) ?></p>
                        </div>
                        <?php if ($stored && $docId > 0): ?>
                        <a class="dm-btn dm-btn--small" href="<?= $e(url('personnel/mon-espace-rh/documents/' . $docId . '/fichier')) ?>">Ouvrir</a>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <section class="dm-card" aria-labelledby="dm-seniority-title">
                <h2 id="dm-seniority-title" class="dm-card__title">Ancienneté</h2>
                <?php if ($seniorityLines === []): ?>
                <p class="dm-muted">Pas encore d’indicateur d’ancienneté pour votre compte.</p>
                <?php else: ?>
                <dl class="dm-facts">
                    <?php foreach ($seniorityLines as $line): ?>
                    <div><dt><?= $e($line['label'] ?? '') ?></dt><dd><?= $e($line['formatted'] ?? '—') ?></dd></div>
                    <?php endforeach; ?>
                </dl>
                <?php endif; ?>
                <form method="post" action="<?= $e(url('personnel/mon-espace-rh/actualiser')) ?>" class="dm-inline">
                    <input type="hidden" name="_csrf_token" value="<?= $rhWorkspaceCsrf ?>">
                    <button type="submit" class="dm-link-btn" title="Utile après un changement d’affectation ou de rôle">Recalculer depuis ma fiche</button>
                </form>
            </section>

            <nav class="dm-card dm-links" aria-label="Voir aussi">
                <h2 class="dm-card__title">Voir aussi</h2>
                <a href="<?= $e(url('back-office/ma-situation/ma-fiche')) ?>">Ma fiche <span aria-hidden="true">→</span></a>
                <?php if ($trainingAllowed): ?>
                <a href="<?= $e(url('formations/mes-formations')) ?>">Mes formations <span aria-hidden="true">→</span></a>
                <?php endif; ?>
                <a href="<?= $e(url('back-office/ma-situation/coffre')) ?>">Mon coffre <span aria-hidden="true">→</span></a>
            </nav>
        </aside>
    </div>
</div>
