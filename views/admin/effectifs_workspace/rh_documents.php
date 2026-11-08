<?php
declare(strict_types=1);

/*
 * Effectifs > Documents RH : coffre des dossiers individuels (maquette « Effectifs Documents - Athena »).
 * Registre filtrable (types, visibilité, recherche, membre), fiche de la pièce en volet latéral,
 * fenêtre « Ajouter au dossier » : dépôt d’un fichier (POST documents-rh) ou pièce A4 établie (POST documents-rh/etablir).
 */

require base_path('views/admin/effectifs_workspace/partials/rh_ui_helpers.php');

$docs = is_array($hrDocuments ?? null) ? $hrDocuments : [];
$typeLabels = is_array($hrDocTypeLabels ?? null) ? $hrDocTypeLabels : [];
$users = is_array($orgUsers ?? null) ? $orgUsers : [];
$schemaReady = !empty($hrSchemaReady);
$canManage = !empty($canManage);
$csrf = htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8');
$hrSettings = is_array($hrWorkspaceSettings ?? null) ? $hrWorkspaceSettings : [];
$defaultVisibility = (($hrSettings['default_visibility'] ?? 'STAFF') === 'MEMBER') ? 'MEMBER' : 'STAFF';
$pdfTypes = is_array($hrPdfTypeLabels ?? null) ? $hrPdfTypeLabels : [];
$unitNames = is_array($hrUnitNames ?? null) ? $hrUnitNames : [];
$scope = is_array($hrScopeUser ?? null) ? $hrScopeUser : null;
$scopeId = $scope !== null ? (int) ($scope['id'] ?? 0) : 0;
$tenantId = (int) ($hrTenantId ?? 0);
$community = trim((string) ($hrCommunityName ?? ''));
$summary = is_array($hrDocumentsSummary ?? null) ? $hrDocumentsSummary : [];
$total = (int) ($summary['total'] ?? ($hrDocumentsCount ?? count($docs)));
$byType = is_array($summary['by_type'] ?? null) ? $summary['by_type'] : [];
if ($summary === []) {
    foreach ($docs as $d) {
        $tk = (string) ($d['doc_type'] ?? 'autre');
        $byType[$tk] = ($byType[$tk] ?? 0) + 1;
    }
}
$monthKey = date('Y-m');
$monthLabel = \App\Support\LocalizedCalendar::month((int) date('n')) . ' ' . date('Y');

$memberName = static function (array $u): string {
    return trim((string) ($u['display_name'] ?? '')) ?: (trim((string) ($u['callsign'] ?? '')) ?: 'Membre');
};
$usersById = [];
foreach ($users as $u) {
    $usersById[(int) ($u['id'] ?? 0)] = $u;
}
$scopeName = $scope !== null ? $memberName($scope) : '';
$avatarColors = ['#059669', '#1d4ed8', '#b45309', '#7c3aed', '#be185d', '#0e7490', '#475569'];
$initials = static function (string $label): string {
    $parts = preg_split('/[\s.\-_]+/u', trim($label), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (count($parts) === 1) {
        return mb_strtoupper(mb_substr($parts[0], 0, 2));
    }
    $out = '';
    foreach (array_slice($parts, -2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }

    return $out !== '' ? $out : '?';
};
$edrDate = static function (?string $raw) use ($rhWhen): string {
    $ts = strtotime(trim((string) $raw));
    if (!$ts) {
        return $rhWhen($raw);
    }

    return (int) date('j', $ts) . ' ' . \App\Support\LocalizedCalendar::month((int) date('n', $ts), true) . ' ' . date('Y', $ts);
};
$memberOptions = static function (int $selected) use ($users, $memberName, $h): string {
    $out = '';
    foreach ($users as $u) {
        $id = (int) ($u['id'] ?? 0);
        $out .= '<option value="' . $id . '"' . ($id === $selected ? ' selected' : '') . '>' . $h($memberName($u)) . '</option>';
    }

    return $out;
};
$fileUrl = static fn (int $id): string => effectifs_workspace_url('documents-rh/' . $id . '/fichier');
$icon = static fn (string $id, string $cls = 'edr-i'): string => '<svg class="' . $cls . '" aria-hidden="true" focusable="false"><use href="#edr-' . $id . '"></use></svg>';
$visBadge = static function (bool $member) use ($icon): string {
    return $member
        ? '<span class="edr-vb edr-vb--me">' . $icon('eye') . 'Visible du membre</span>'
        : '<span class="edr-vb edr-vb--staff">' . $icon('lock') . 'État-major</span>';
};
$typeOrder = array_keys($typeLabels);
?>
<svg class="edr-sprite" aria-hidden="true" focusable="false">
    <symbol id="edr-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></symbol>
    <symbol id="edr-x" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></symbol>
    <symbol id="edr-dl" viewBox="0 0 24 24"><path d="M12 4v12M6 10l6 6 6-6M4 20h16"/></symbol>
    <symbol id="edr-up" viewBox="0 0 24 24"><path d="M12 20V8M6 14l6-6 6 6M4 4h16"/></symbol>
    <symbol id="edr-file" viewBox="0 0 24 24"><path d="M14 3H6v18h12V7zM14 3v4h4"/></symbol>
    <symbol id="edr-pen" viewBox="0 0 24 24"><path d="M4 20h4L19 9l-4-4L4 16z"/></symbol>
    <symbol id="edr-eye" viewBox="0 0 24 24"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></symbol>
    <symbol id="edr-lock" viewBox="0 0 24 24"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></symbol>
    <symbol id="edr-dots" viewBox="0 0 24 24"><circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/></symbol>
    <symbol id="edr-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4-6 8-6s7 2 8 6"/></symbol>
    <symbol id="edr-pin" viewBox="0 0 24 24"><path d="M12 21s-7-6.5-7-12a7 7 0 0 1 14 0c0 5.5-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/></symbol>
</svg>

<?php $rhDocsTab = 'coffre'; require base_path('views/admin/effectifs_workspace/partials/rh_documents_tabs.php'); ?>
<div class="edr" data-edr>
    <header class="edr-ph">
        <div class="edr-ph__copy">
            <span class="edr-k">Dossier RH</span>
            <?php if ($scope !== null): ?>
                <h2 class="edr-ph__title">Documents de <?= $h($scopeName) ?></h2>
                <p class="edr-ph__lead">Toutes les pièces versées au dossier individuel de ce membre, des plus récentes aux plus anciennes.</p>
                <p class="edr-ph__scope">
                    <a href="<?= $h(effectifs_workspace_url('membres/' . $scopeId)) ?>"><?= $icon('user') ?>Fiche du membre</a>
                    <a href="<?= $h(effectifs_workspace_url('documents-rh')) ?>">Toute la communauté</a>
                </p>
            <?php else: ?>
                <h2 class="edr-ph__title">Documents</h2>
                <p class="edr-ph__lead">Le coffre des dossiers individuels : chartes, certificats, décisions d’affectation et évaluations. Chaque pièce est classée, datée, et sa visibilité est choisie.</p>
            <?php endif; ?>
        </div>
        <?php if ($canManage && $schemaReady): ?>
        <div class="edr-ph__acts">
            <button type="button" class="edr-btn" data-edr-new="file"><?= $icon('up') ?>Déposer un fichier</button>
            <button type="button" class="edr-btn edr-btn--dark" data-edr-new="gen"><?= $icon('pen') ?>Établir une pièce</button>
        </div>
        <?php endif; ?>
    </header>

<?php if (!$schemaReady): ?>
    <div class="edr-panel">
        <div class="edr-empty">
            <div class="edr-empty__ill"><?= $icon('file', 'edr-i edr-i--lg') ?></div>
            <b>Documents RH indisponibles</b>
            <p><?= $h($rhUnavailable) ?></p>
        </div>
    </div>
<?php else: ?>
    <div class="edr-stats" role="group" aria-label="Aperçu des documents">
        <button type="button" class="edr-stat is-on" data-edr-stat="" aria-pressed="true">
            <span class="edr-k">Pièces au coffre</span>
            <b><?= $total ?></b>
            <small>toutes visibilités</small>
        </button>
        <button type="button" class="edr-stat" data-edr-stat="MEMBER" aria-pressed="false">
            <span class="edr-k"><i class="edr-dot edr-dot--me" aria-hidden="true"></i>Visibles du membre</span>
            <b><?= (int) ($summary['member'] ?? 0) ?></b>
            <small>dans sa fiche</small>
        </button>
        <button type="button" class="edr-stat" data-edr-stat="STAFF" aria-pressed="false">
            <span class="edr-k"><i class="edr-dot edr-dot--staff" aria-hidden="true"></i>État-major</span>
            <b><?= (int) ($summary['staff'] ?? 0) ?></b>
            <small>gestionnaires uniquement</small>
        </button>
        <button type="button" class="edr-stat" data-edr-stat="month" aria-pressed="false">
            <span class="edr-k"><i class="edr-dot edr-dot--month" aria-hidden="true"></i>Ajoutées ce mois</span>
            <b><?= (int) ($summary['month'] ?? 0) ?></b>
            <small><?= $h($monthLabel) ?></small>
        </button>
    </div>

    <div class="edr-vault">
        <aside class="edr-rail" aria-label="Types de pièce">
            <span class="edr-k edr-rail__k">Types de pièce</span>
            <button type="button" class="is-on" data-edr-type="" aria-pressed="true">
                <span class="edr-sw edr-sw--all" aria-hidden="true"></span><span class="edr-rail__l">Toutes</span><span class="edr-rail__c"><?= $total ?></span>
            </button>
            <?php foreach ($typeLabels as $tk => $lab): ?>
                <?php $n = (int) ($byType[$tk] ?? 0); ?>
                <button type="button" data-edr-type="<?= $h((string) $tk) ?>" aria-pressed="false"<?= $n === 0 ? ' class="is-zero"' : '' ?>>
                    <span class="edr-sw edr-t--<?= $h((string) $tk) ?>" aria-hidden="true"></span><span class="edr-rail__l"><?= $h((string) $lab) ?></span><span class="edr-rail__c"><?= $n ?></span>
                </button>
            <?php endforeach; ?>
            <?php if ($canManage): ?>
                <hr>
                <span class="edr-k edr-rail__k">Origine</span>
                <button type="button" class="edr-rail__new" data-edr-new="file"><?= $icon('up') ?><span class="edr-rail__l">Déposer un fichier</span></button>
                <button type="button" class="edr-rail__new" data-edr-new="gen"><?= $icon('pen') ?><span class="edr-rail__l">Établir une pièce</span></button>
            <?php endif; ?>
        </aside>

        <section class="edr-panel" aria-labelledby="edr-list-title">
            <h3 id="edr-list-title" class="edr-sr">Pièces enregistrées</h3>
            <div class="edr-bar">
                <label class="edr-search">
                    <?= $icon('search') ?>
                    <span class="edr-sr">Rechercher une pièce</span>
                    <input type="search" data-edr-q placeholder="Titre, membre, fichier…" autocomplete="off">
                </label>
                <form method="get" action="<?= $h(effectifs_workspace_url('documents-rh')) ?>" class="edr-bar__member" data-edr-member-form>
                    <label class="edr-sr" for="edr-member-filter">Membre</label>
                    <select class="edr-sel" id="edr-member-filter" name="user_id" data-edr-member-filter>
                        <option value="">Tous les membres</option>
                        <?= $memberOptions($scopeId) ?>
                    </select>
                    <noscript><button type="submit" class="edr-btn edr-btn--sm">Afficher</button></noscript>
                </form>
                <label class="edr-sr" for="edr-group">Regroupement</label>
                <select class="edr-sel" id="edr-group" data-edr-group>
                    <option value="type">Grouper par type</option>
                    <?php if ($scope === null): ?><option value="member">Grouper par membre</option><?php endif; ?>
                    <option value="none">Sans regroupement</option>
                </select>
            </div>

            <?php if ($scope === null && $total > count($docs)): ?>
                <p class="edr-note">Les <?= count($docs) ?> pièces les plus récentes sont listées sur <?= $total ?>. Choisissez un membre pour voir tout son dossier.</p>
            <?php endif; ?>

            <div class="edr-list" data-edr-list>
            <?php foreach ($docs as $d): ?>
                <?php
                $id = (int) ($d['id'] ?? 0);
                $uid = (int) ($d['user_id'] ?? 0);
                $u = $usersById[$uid] ?? null;
                $name = trim((string) ($d['user_display_name'] ?? '')) ?: 'Membre';
                $callsign = trim((string) ($u['callsign'] ?? ''));
                $unit = $u !== null ? (string) ($unitNames[(int) ($u['unit_id'] ?? 0)] ?? '') : '';
                $sub = $unit !== '' ? $unit : $callsign;
                $type = (string) ($d['doc_type'] ?? 'autre');
                $typeLabel = (string) ($typeLabels[$type] ?? $type);
                $path = trim((string) ($d['file_path'] ?? ''));
                $stored = \App\Support\PersonnelHrDocumentStorage::isStoredPath($path);
                $orig = trim((string) ($d['original_name'] ?? ''));
                $ext = $stored ? mb_strtoupper(mb_substr((string) pathinfo($orig !== '' ? $orig : $path, PATHINFO_EXTENSION), 0, 4)) : '';
                $fiHtml = $stored ? $h($ext !== '' ? $ext : 'DOC') : $icon('pin', 'edr-i edr-fi__pin');
                $visMember = ($d['visibility'] ?? '') === 'MEMBER';
                $created = (string) ($d['created_at'] ?? '');
                $title = (string) ($d['title'] ?? '');
                $desc = trim((string) ($d['description'] ?? ''));
                $avatar = $avatarColors[$uid % count($avatarColors)];
                ?>
                <article class="edr-doc" data-edr-doc="<?= $id ?>" data-type="<?= $h($type) ?>" data-vis="<?= $visMember ? 'MEMBER' : 'STAFF' ?>" data-month="<?= substr($created, 0, 7) === $monthKey ? '1' : '0' ?>" data-member="<?= $uid ?>" data-order="<?= (int) array_search($type, $typeOrder, true) ?>">
                    <span class="edr-fi edr-t--<?= $h($type) ?>" aria-hidden="true"><?= $fiHtml ?></span>
                    <div class="edr-doc__t">
                        <button type="button" class="edr-doc__open" data-edr-open aria-haspopup="dialog"><?= $h($title) ?></button>
                        <small>
                            <span class="edr-ty"><i class="edr-t--<?= $h($type) ?>" aria-hidden="true"></i><?= $h($typeLabel) ?></span>
                            <?php if ($stored && $orig !== ''): ?><span class="edr-doc__f"><?= $h($orig) ?></span>
                            <?php elseif (!$stored && $path !== ''): ?><span class="edr-doc__f">Emplacement : <?= $h($path) ?></span><?php endif; ?>
                        </small>
                    </div>
                    <div class="edr-who">
                        <span class="edr-av" style="background:<?= $h($avatar) ?>" aria-hidden="true"><?= $h($initials($callsign !== '' ? $callsign : $name)) ?></span>
                        <span class="edr-who__n"><b data-edr-who-name><?= $h($name) ?></b><?php if ($sub !== ''): ?><small data-edr-who-sub><?= $h($sub) ?></small><?php endif; ?></span>
                    </div>
                    <time class="edr-dt" datetime="<?= $h(substr($created, 0, 10)) ?>"><?= $h($edrDate($created)) ?></time>
                    <?= $visBadge($visMember) ?>
                    <div class="edr-more">
                        <button type="button" class="edr-more__btn" data-edr-menu aria-haspopup="true" aria-expanded="false" aria-label="Actions sur la pièce"><?= $icon('dots') ?></button>
                        <div class="edr-menu" hidden>
                            <button type="button" data-edr-open><?= $icon('eye') ?>Voir le détail</button>
                            <?php if ($stored): ?><a href="<?= $h($fileUrl($id)) ?>"><?= $icon('dl') ?>Ouvrir la pièce</a><?php endif; ?>
                            <?php if ($uid > 0): ?><a href="<?= $h(effectifs_workspace_url('membres/' . $uid)) ?>"><?= $icon('user') ?>Fiche du membre</a><?php endif; ?>
                        </div>
                    </div>
                    <div class="edr-doc__detail" hidden data-edr-detail>
                        <div class="edr-dr__h">
                            <span class="edr-fi edr-t--<?= $h($type) ?>" aria-hidden="true"><?= $fiHtml ?></span>
                            <div class="edr-dr__ht"><b><?= $h($title) ?></b><small><?= $h($typeLabel) ?></small></div>
                            <button type="button" class="edr-btn edr-btn--ic" data-edr-close aria-label="Fermer"><?= $icon('x') ?></button>
                        </div>
                        <div class="edr-dr__b">
                            <div class="edr-a4 edr-a4--file">
                                <div class="edr-filecard">
                                    <span class="edr-fi edr-fi--lg edr-t--<?= $h($type) ?>" aria-hidden="true"><?= $fiHtml ?></span>
                                    <?php if ($stored): ?>
                                        <b><?= $h($orig !== '' ? $orig : $title) ?></b>
                                        <small>Pièce jointe conservée dans le coffre</small>
                                    <?php else: ?>
                                        <b>Pièce conservée ailleurs</b>
                                        <small><?= $h($path !== '' ? $path : '—') ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <dl class="edr-dl">
                                <dt>Membre</dt><dd><?= $h($name) ?><?= $callsign !== '' && strcasecmp($callsign, $name) !== 0 ? ' · ' . $h($callsign) : '' ?></dd>
                                <?php if ($unit !== ''): ?><dt>Unité</dt><dd><?= $h($unit) ?></dd><?php endif; ?>
                                <dt>Type</dt><dd><?= $h($typeLabel) ?></dd>
                                <dt>Date</dt><dd><?= $h($edrDate($created)) ?></dd>
                                <dt>Visibilité</dt><dd><?= $visBadge($visMember) ?></dd>
                                <dt>Origine</dt><dd><?= $stored ? 'Fichier au coffre' : 'Emplacement externe' ?></dd>
                                <?php if ($desc !== ''): ?><dt>Description</dt><dd class="edr-dl__free"><?= $h($desc) ?></dd><?php endif; ?>
                            </dl>
                        </div>
                        <div class="edr-dr__f">
                            <?php if ($uid > 0): ?><a class="edr-btn" href="<?= $h(effectifs_workspace_url('membres/' . $uid)) ?>"><?= $icon('user') ?>Fiche du membre</a><?php endif; ?>
                            <?php if ($stored): ?><a class="edr-btn edr-btn--dark" href="<?= $h($fileUrl($id)) ?>"><?= $icon('dl') ?>Ouvrir la pièce</a><?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>

            <div class="edr-empty" data-edr-nomatch hidden>
                <div class="edr-empty__ill"><?= $icon('search', 'edr-i edr-i--lg') ?></div>
                <b>Aucune pièce ne correspond</b>
                <p>Essayez un autre type, une autre visibilité ou retirez la recherche.</p>
                <button type="button" class="edr-btn" data-edr-reset>Effacer les filtres</button>
            </div>
            <?php if ($docs === []): ?>
                <div class="edr-empty">
                    <div class="edr-empty__ill"><?= $icon('file', 'edr-i edr-i--lg') ?></div>
                    <b><?= $scope !== null ? 'Aucune pièce dans ce dossier' : 'Le coffre est vide' ?></b>
                    <p>Déposez une pièce déjà signée, ou établissez une pièce A4 datée directement depuis Athena.</p>
                    <?php if ($canManage): ?>
                    <div class="edr-empty__acts">
                        <button type="button" class="edr-btn" data-edr-new="file">Déposer un fichier</button>
                        <button type="button" class="edr-btn edr-btn--dark" data-edr-new="gen">Établir une pièce</button>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <dialog class="edr-dr" aria-labelledby="edr-dr-title" data-edr-drawer>
        <h2 id="edr-dr-title" class="edr-sr">Détail de la pièce</h2>
        <div class="edr-dr__in" data-edr-drawer-body></div>
    </dialog>

    <?php if ($canManage): ?>
    <dialog class="edr-ov" aria-labelledby="edr-cmp-title" data-edr-composer>
        <div class="edr-cmp">
            <div class="edr-cmp__l">
                <div class="edr-cmp__h">
                    <div><span class="edr-k">Nouvelle pièce</span><h2 id="edr-cmp-title">Ajouter au dossier</h2></div>
                    <button type="button" class="edr-btn edr-btn--ic" data-edr-cmp-close aria-label="Fermer"><?= $icon('x') ?></button>
                </div>
                <div class="edr-modes" role="group" aria-label="Origine de la pièce">
                    <button type="button" class="edr-mode" data-edr-mode="file" aria-pressed="false">
                        <span class="edr-mode__i"><?= $icon('up') ?></span>
                        <span><b>Déposer un fichier</b><small>Une pièce déjà signée ou scannée.</small></span>
                    </button>
                    <button type="button" class="edr-mode" data-edr-mode="gen" aria-pressed="false">
                        <span class="edr-mode__i"><?= $icon('pen') ?></span>
                        <span><b>Établir une pièce</b><small>Athena rédige un A4 daté, prêt à signer.</small></span>
                    </button>
                </div>

                <form method="post" action="<?= $h(effectifs_workspace_url('documents-rh')) ?>" enctype="multipart/form-data" class="edr-cmp__form" data-edr-form="file" hidden>
                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                    <?php if ($scopeId > 0): ?><input type="hidden" name="return_user" value="<?= $scopeId ?>"><?php endif; ?>
                    <div class="edr-cmp__b">
                        <div class="edr-row">
                            <div class="edr-fl">
                                <label for="edr-f-member">Membre</label>
                                <select class="edr-sel" id="edr-f-member" name="user_id" required data-edr-f-member>
                                    <option value="">Choisir un membre…</option>
                                    <?= $memberOptions($scopeId) ?>
                                </select>
                            </div>
                            <div class="edr-fl">
                                <label for="edr-f-type">Type de pièce</label>
                                <select class="edr-sel" id="edr-f-type" name="doc_type" data-edr-f-type>
                                    <?php foreach ($typeLabels as $k => $lab): ?>
                                        <option value="<?= $h((string) $k) ?>"><?= $h((string) $lab) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="edr-fl">
                            <label for="edr-f-title">Titre</label>
                            <input class="edr-in" type="text" id="edr-f-title" name="title" maxlength="200" placeholder="Ex. Charte signée 2026" data-edr-f-title>
                        </div>
                        <div class="edr-fl">
                            <span class="edr-fl__l">Pièce jointe
                                <?php $rhTip('tip-docs-file', 'À propos de la pièce jointe', 'Déposez la pièce ici (PDF, image ou document Word, 15 Mo maximum). Elle reste dans le coffre : seuls les gestionnaires, et le membre si vous le choisissez, peuvent l’ouvrir.'); ?>
                            </span>
                            <label class="edr-drop" data-edr-drop>
                                <input type="file" name="document" class="edr-drop__input" data-edr-file accept=".pdf,.jpg,.jpeg,.png,.webp,.txt,.doc,.docx,.odt,application/pdf,image/jpeg,image/png,image/webp,text/plain,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.oasis.opendocument.text">
                                <span class="edr-drop__i" aria-hidden="true"><?= $icon('up', 'edr-i edr-i--md') ?></span>
                                <span class="edr-drop__t">
                                    <b>Déposer le fichier</b>
                                    <small>Glissez le document ici, ou cliquez. PDF, image ou Word, 15 Mo maximum.</small>
                                    <span class="edr-drop__name" data-edr-file-name aria-live="polite">Aucun fichier choisi</span>
                                </span>
                            </label>
                        </div>
                        <div class="edr-fl">
                            <label for="edr-f-loc">Emplacement <small>si la pièce reste ailleurs</small></label>
                            <input class="edr-in" type="text" id="edr-f-loc" name="location_note" maxlength="500" placeholder="Dossier partagé, archives, lien interne…" data-edr-f-loc>
                        </div>
                        <fieldset class="edr-fl edr-vis">
                            <legend>Visibilité</legend>
                            <label class="edr-vo"><input type="radio" name="visibility" value="MEMBER"<?= $defaultVisibility === 'MEMBER' ? ' checked' : '' ?>><b><?= $icon('eye') ?>Visible du membre</b><small>Le membre la retrouve dans sa fiche.</small></label>
                            <label class="edr-vo"><input type="radio" name="visibility" value="STAFF"<?= $defaultVisibility === 'STAFF' ? ' checked' : '' ?>><b><?= $icon('lock') ?>État-major uniquement</b><small>Réservée aux gestionnaires du personnel.</small></label>
                        </fieldset>
                        <div class="edr-fl">
                            <label for="edr-f-desc">Description</label>
                            <textarea class="edr-in" id="edr-f-desc" name="description" maxlength="500" rows="3" placeholder="Contexte, période, décision associée…"></textarea>
                        </div>
                    </div>
                    <div class="edr-cmp__f">
                        <small data-edr-hint aria-live="polite"></small>
                        <div class="edr-cmp__fa">
                            <button type="button" class="edr-btn" data-edr-cmp-close>Annuler</button>
                            <button type="submit" class="edr-btn edr-btn--dark">Ajouter au dossier</button>
                        </div>
                    </div>
                </form>

                <form method="post" action="<?= $h(effectifs_workspace_url('documents-rh/etablir')) ?>" class="edr-cmp__form" data-edr-form="gen" hidden>
                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                    <?php if ($scopeId > 0): ?><input type="hidden" name="return_user" value="<?= $scopeId ?>"><?php endif; ?>
                    <div class="edr-cmp__b">
                        <div class="edr-row">
                            <div class="edr-fl">
                                <label for="edr-g-member">Membre</label>
                                <select class="edr-sel" id="edr-g-member" name="user_id" required data-edr-f-member>
                                    <option value="">Choisir un membre…</option>
                                    <?= $memberOptions($scopeId) ?>
                                </select>
                            </div>
                            <div class="edr-fl">
                                <label for="edr-g-type">Type de pièce</label>
                                <select class="edr-sel" id="edr-g-type" name="doc_type" data-edr-f-type>
                                    <?php foreach ($pdfTypes as $k => $lab): ?>
                                        <option value="<?= $h((string) $k) ?>"><?= $h((string) $lab) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="edr-fl">
                            <label for="edr-g-title">Titre <small>vide = titre du type</small></label>
                            <input class="edr-in" type="text" id="edr-g-title" name="title" maxlength="200" placeholder="Laisser vide pour le titre du type" data-edr-f-title>
                        </div>
                        <fieldset class="edr-fl edr-vis">
                            <legend>Visibilité</legend>
                            <label class="edr-vo"><input type="radio" name="visibility" value="MEMBER"<?= $defaultVisibility === 'MEMBER' ? ' checked' : '' ?>><b><?= $icon('eye') ?>Visible du membre</b><small>Le membre la retrouve dans sa fiche.</small></label>
                            <label class="edr-vo"><input type="radio" name="visibility" value="STAFF"<?= $defaultVisibility === 'STAFF' ? ' checked' : '' ?>><b><?= $icon('lock') ?>État-major uniquement</b><small>Réservée aux gestionnaires du personnel.</small></label>
                        </fieldset>
                        <div class="edr-fl">
                            <label for="edr-g-detail">Mention complémentaire</label>
                            <textarea class="edr-in" id="edr-g-detail" name="detail" maxlength="500" rows="3" placeholder="Unité, période, décision, objectifs…" data-edr-f-detail></textarea>
                        </div>
                    </div>
                    <div class="edr-cmp__f">
                        <small data-edr-hint aria-live="polite"></small>
                        <div class="edr-cmp__fa">
                            <button type="button" class="edr-btn" data-edr-cmp-close>Annuler</button>
                            <button type="submit" class="edr-btn edr-btn--dark">Établir et ranger</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="edr-cmp__r">
                <span class="edr-k edr-cmp__rk"><span>Aperçu</span><span data-edr-prev-kind="gen">A4 · PDF</span><span data-edr-prev-kind="file" hidden>Fichier</span></span>
                <div class="edr-sheet" data-edr-prev="gen" data-rh-issue-preview>
                    <div class="edr-a4">
                        <div class="edr-a4__hd">
                            <span class="edr-a4__lg" aria-hidden="true"><?= $h($community !== '' ? mb_strtoupper(mb_substr($community, 0, 1)) : 'A') ?></span>
                            <span><small>Dossier individuel · Ressources humaines</small><b><?= $h($community !== '' ? $community : 'Communauté') ?></b></span>
                            <span class="edr-a4__ref">Réf.<br><b data-edr-p="ref">RH-…</b></span>
                        </div>
                        <span class="edr-a4__badge" data-edr-p="badge"></span>
                        <h3 data-edr-p="title"></h3>
                        <div class="edr-a4__obj" data-edr-p="obj-box"><small>Objet</small><span data-edr-p="object"></span></div>
                        <table>
                            <tr><td>Intéressé(e)</td><td data-edr-p="member"></td></tr>
                            <tr><td>Unité</td><td data-edr-p="unit">—</td></tr>
                            <tr><td>Date</td><td><?= $h(date('d/m/Y')) ?></td></tr>
                        </table>
                        <div class="edr-a4__body"><p data-edr-p="body"></p><p data-edr-p="detail-wrap" hidden><strong>Mentions :</strong> <span data-edr-p="detail"></span></p></div>
                        <div class="edr-a4__visa">
                            <div><b>Intéressé(e) · pour le dossier</b>Visa / prise de connaissance</div>
                            <div><b>Le responsable du personnel</b>Autorité émettrice · Signature et cachet</div>
                        </div>
                        <div class="edr-a4__ft"><span>Athena · Dossier RH</span><span>Diffusion restreinte · dossier personnel</span></div>
                    </div>
                </div>
                <div class="edr-sheet" data-edr-prev="file" hidden>
                    <div class="edr-a4 edr-a4--file">
                        <div class="edr-filecard">
                            <span class="edr-fi edr-fi--lg" data-edr-p="ext" aria-hidden="true"><?= $icon('file', 'edr-i edr-i--md') ?></span>
                            <b data-edr-p="fname">Aucun fichier choisi</b>
                            <small data-edr-p="fsize"></small>
                        </div>
                    </div>
                </div>
                <p class="edr-cmp__note" data-edr-prev-kind="gen">Aperçu indicatif : le PDF final contient le texte complet, la référence et les blocs de signature.</p>
            </div>
        </div>
    </dialog>

    <div hidden data-edr-copy data-tenant="<?= $tenantId ?>">
        <span data-k="none">Membre à désigner</span>
        <span data-k="miss-member">Il manque un membre.</span>
        <span data-k="miss-file">Il manque un fichier ou un emplacement.</span>
        <span data-k="ready">Prêt à ranger dans le dossier.</span>
        <span data-k="too-big">Fichier trop lourd : 15 Mo maximum.</span>
        <span data-k="no-file">Aucun fichier choisi</span>
        <?php
        $copy = [
            'charte' => ['CHA', 'Prise de connaissance de la charte', 'Constate la prise de connaissance de la charte et l’engagement à en respecter les principes de conduite et de cohésion.'],
            'reglement' => ['REG', 'Prise de connaissance du règlement intérieur', 'Atteste la communication du règlement (service, sécurité, moyens collectifs) et sa prise de connaissance à la date de la pièce.'],
            'certificat' => ['CER', 'Attestation de parcours / appartenance', 'Certifie l’appartenance aux effectifs et le parcours du membre, pour mobilité, intégration ou dossier administratif.'],
            'affectation' => ['AFF', 'Décision d’affectation', 'Formalise l’affectation retenue, sa prise d’effet et les responsabilités attachées au poste.'],
            'evaluation' => ['EVA', 'Évaluation portée au dossier', 'Synthèse d’appréciation (tenue, implication, compétences) et mentions de progression éventuelles.'],
        ];
        foreach ($pdfTypes as $k => $lab):
            [$prefix, $obj, $body] = $copy[$k] ?? ['RH', 'Pièce du dossier individuel', ''];
            ?>
            <span data-type="<?= $h((string) $k) ?>" data-prefix="<?= $h($prefix) ?>" data-k="label"><?= $h((string) $lab) ?></span>
            <span data-type="<?= $h((string) $k) ?>" data-k="object"><?= $h($obj) ?></span>
            <span data-type="<?= $h((string) $k) ?>" data-k="body"><?= $h($body) ?></span>
        <?php endforeach; ?>
        <?php foreach ($users as $u): ?>
            <?php $un = (string) ($unitNames[(int) ($u['unit_id'] ?? 0)] ?? ''); ?>
            <?php if ($un !== ''): ?><span data-unit="<?= (int) ($u['id'] ?? 0) ?>"><?= $h($un) ?></span><?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>
</div>

<?php $rhShortcutCurrent = 'documents'; require base_path('views/admin/effectifs_workspace/partials/rh_shortcuts.php'); ?>
<script src="<?= $h(asset_url('assets/js/effectifs-documents-rh.js')) ?>" defer></script>
