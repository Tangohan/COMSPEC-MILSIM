<?php
declare(strict_types=1);
/**
 * Rédaction d’un message d’unité : ligne « À » en pastilles (saisie avec suggestions, annuaire en fenêtre), objet,
 * urgence, corps, barre d’envoi. Les destinataires restent des cases members[] et groups[] du formulaire (rangées dans
 * la fenêtre d’annuaire) : l’envoi poste exactement ce que JnetPortalController::mailSend attend. Les textes que le
 * script affiche sont écrits ici (éléments masqués) pour être traduits avec la page.
 *
 * @var list<array<string,mixed>> $mailBoxes
 * @var list<array{key:string,label:string,description:string,count:int,kind:string}> $mailGroups
 * @var array<string,list<int>> $mailGroupMembers membres de chaque groupe, expéditeur exclu
 * @var list<array<string,mixed>> $mailDirectory
 * @var array<string,array{label:string,hint:string}> $mailPrecedences
 * @var array{subject:string,body:string,precedence:string,groups:list<string>,members:list<int>} $mailDraft
 * @var int $mailEmailLimit
 * @var int $mailMaxRecipients
 * @var string $mailRecipientName destinataire posé par ?membre=, ?formation= ou ?groupe=
 * @var string $mailDraftContext clé du brouillon local (par destinataire)
 * @var bool $mailHasServerDraft saisie rendue par le serveur après un refus d’envoi
 * @var int $mailUserId
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$boxes = is_array($mailBoxes ?? null) ? $mailBoxes : [];
$groups = is_array($mailGroups ?? null) ? $mailGroups : [];
$groupMembers = is_array($mailGroupMembers ?? null) ? $mailGroupMembers : [];
$directory = is_array($mailDirectory ?? null) ? $mailDirectory : [];
$precedences = is_array($mailPrecedences ?? null) ? $mailPrecedences : [];
$draft = is_array($mailDraft ?? null) ? $mailDraft : [];
$emailLimit = (int) ($mailEmailLimit ?? 25);
$maxRecipients = (int) ($mailMaxRecipients ?? 250);
$recipientName = trim((string) ($mailRecipientName ?? ''));
$checkedGroups = array_flip(array_map('strval', (array) ($draft['groups'] ?? [])));
$checkedMembers = array_flip(array_map('intval', (array) ($draft['members'] ?? [])));
$currentPrecedence = (string) ($draft['precedence'] ?? 'routine');
if (!isset($precedences[$currentPrecedence])) {
    $currentPrecedence = (string) (array_key_first($precedences) ?? 'routine');
}
$subject = (string) ($draft['subject'] ?? '');
$body = (string) ($draft['body'] ?? '');
$hasDirectory = $groups !== [] || $directory !== [];

$plural = static fn (int $n): string => $n > 1 ? 's' : '';
$clean = static fn (mixed $v): string => in_array(trim((string) $v), ['', '—', '-'], true) ? '' : trim((string) $v);

// Onglets de l'annuaire : membres, formations, diffusion générale (toute l'unité, commandement, listes enregistrées).
$formations = [];
$general = [];
foreach ($groups as $g) {
    if ((string) ($g['kind'] ?? '') === 'orbat') {
        $formations[] = $g;
    } else {
        $general[] = $g;
    }
}
$groupId = static fn (string $key): string => 'grp-' . preg_replace('/[^a-z0-9]+/i', '-', $key);
$memberLine = static function (array $m) use ($clean): string {
    return implode(' · ', array_filter([$clean($m['callsign'] ?? ''), $clean($m['unit'] ?? ''), $clean($m['function'] ?? '')], static fn (string $s): bool => $s !== ''));
};

// Pastilles déjà posées (destinataire d'arrivée, saisie rendue après un refus) : le script les tient ensuite à jour.
$pills = [];
foreach ($groups as $g) {
    if (isset($checkedGroups[(string) $g['key']])) {
        $pills[] = ['for' => $groupId((string) $g['key']), 'name' => (string) $g['label'], 'count' => (int) $g['count'], 'group' => true, 'user' => (string) ($g['kind'] ?? '') !== 'unite'];
    }
}
foreach ($directory as $m) {
    if (isset($checkedMembers[(int) ($m['id'] ?? 0)])) {
        $pills[] = ['for' => 'mbr-' . (int) $m['id'], 'name' => (string) ($m['name'] ?? ''), 'group' => false, 'user' => true, 'photo' => (string) ($m['photo'] ?? ''), 'initials' => (string) ($m['initials'] ?? '??')];
    }
}
// Un nom de membre, de formation ou de liste n'est jamais traduit ; « Toute l'unité » l'est.
$recipientIsName = true;
foreach ($groups as $g) {
    if ((string) $g['label'] === $recipientName && (string) ($g['kind'] ?? '') === 'unite') {
        $recipientIsName = false;
    }
}
// Le curseur attend là où il manque quelque chose : destinataire, puis objet, puis corps (posé par le script pour le corps).
$focus = $pills === [] ? 'to' : ($subject === '' ? 'subject' : 'body');

// Numéro de la rubrique « Messagerie » dans la barre de l'extranet (posée par la coque), repris à côté du titre.
$rubric = '';
foreach ((array) ($tabs ?? []) as $tabIndex => $tabRow) {
    if (is_array($tabRow) && ($tabRow['id'] ?? '') === 'inbox') {
        $rubric = str_pad((string) $tabIndex, 2, '0', STR_PAD_LEFT);
    }
}
$groupIcon = '<svg viewBox="0 0 20 20" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="7.5" cy="7" r="2.7"/><path d="M2.5 16c.4-2.7 2.4-4.3 5-4.3s4.6 1.6 5 4.3"/><path d="M13 4.6a2.6 2.6 0 0 1 0 4.9M15 11.9c1.4.6 2.3 1.9 2.5 4.1"/></svg>';
?>
<noscript><style>.jmc-dir{display:block;position:static;max-height:none;margin:0 0 16px;width:auto}.jmc-combo,.jmc-to__browse,.jmc-dir__close,.jmc-dir__foot,.jmc-dir__tabs,.jmc-dir__bulk{display:none}.jmc-dir__panel[hidden]{display:block}</style></noscript>
<div class="jmc" data-jmc
     data-user="<?= (int) ($mailUserId ?? 0) ?>"
     data-context="<?= $h((string) ($mailDraftContext ?? 'nouveau')) ?>"
     data-email-limit="<?= $emailLimit ?>"
     data-max="<?= $maxRecipients ?>"
     data-server-draft="<?= !empty($mailHasServerDraft) ? '1' : '0' ?>"
     data-focus="<?= $h($focus) ?>">

    <header class="jmc-band">
        <span class="jmc-band__halo" aria-hidden="true"></span>
        <span class="jmc-band__veil" aria-hidden="true"></span>
        <div class="jmc-band__body">
            <div class="jmc-band__id">
                <div class="jmc-band__title">
                    <?php if ($recipientName !== ''): ?>
                        <h1 class="ja-disp ja-disp--sm">Message à <span<?= $recipientIsName ? ' translate="no"' : '' ?>><?= $h($recipientName) ?></span></h1>
                    <?php else: ?>
                        <h1 class="ja-disp ja-disp--sm">Nouveau message</h1>
                    <?php endif; ?>
                    <?php if ($rubric !== ''): ?><span class="jmc-band__index ja-mono" aria-hidden="true"><?= $h($rubric) ?></span><?php endif; ?>
                </div>
                <p class="jmc-band__lead">Écrivez à un membre, à une formation ou à toute l’unité. <span class="jmc-band__meta">Diffusion interne, consultations journalisées</span></p>
            </div>
            <div class="jmc-band__side">
                <nav class="jmc-band__nav" aria-label="Boîtes de messagerie">
                    <?php foreach ($boxes as $b): ?>
                        <?php $count = (int) ($b['count'] ?? 0); ?>
                        <a class="jmc-box" href="<?= $h((string) ($b['href'] ?? '#')) ?>"><?= $h((string) ($b['label'] ?? '')) ?><?php if ($count > 0): ?><em><?= min(99, $count) ?></em><?php endif; ?></a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>
    </header>

    <?php if (!$hasDirectory): ?>
        <p class="jnet-empty jmc-none">
            Aucun membre actif n’est encore rattaché à cette unité : la messagerie sera disponible dès que
            des comptes seront enregistrés et affectés.
        </p>
    <?php else: ?>
    <div class="jmc-draft" data-jmc-draft hidden role="status">
        <p><strong>Brouillon restauré</strong> <span data-jmc-draft-when></span></p>
        <button type="button" class="jmc-link" data-jmc-draft-clear>Effacer</button>
    </div>

    <form class="jmc-form" method="post" action="<?= $h(url('jnet/courrier/nouveau')) ?>" data-precedence="<?= $h($currentPrecedence) ?>">
        <?= \App\Core\Csrf::field() ?>

        <div class="jmc-row jmc-row--to">
            <label class="jmc-label" for="jmc-to">À</label>
            <div class="jmc-to" data-jmc-to>
                <ul class="jmc-pills" data-jmc-pills data-label="Retirer" aria-label="Destinataires choisis">
                    <?php foreach ($pills as $p): ?>
                        <li class="jmc-pill<?= $p['group'] ? ' jmc-pill--group' : '' ?>" data-for="<?= $h($p['for']) ?>">
                            <?php if ($p['group']): ?>
                                <span class="jmc-pill__av"><?= $groupIcon ?></span>
                            <?php elseif ($p['photo'] !== ''): ?>
                                <img class="jmc-pill__av" src="<?= $h($p['photo']) ?>" alt="">
                            <?php else: ?>
                                <span class="jmc-pill__av" translate="no"><?= $h($p['initials']) ?></span>
                            <?php endif; ?>
                            <span class="jmc-pill__name"<?= $p['user'] ? ' translate="no"' : '' ?>><?= $h($p['name']) ?></span>
                            <?php if ($p['group']): ?><span class="jmc-pill__count"><?= (int) $p['count'] ?></span><?php endif; ?>
                            <button type="button" class="jmc-pill__x" data-jmc-remove aria-label="Retirer <?= $h($p['name']) ?>">×</button>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="jmc-combo">
                    <input id="jmc-to" class="jmc-combo__input" type="text" role="combobox" autocomplete="off" spellcheck="false"
                           aria-expanded="false" aria-controls="jmc-suggest" aria-autocomplete="list"
                           placeholder="Nom, indicatif, formation…"<?= $focus === 'to' ? ' autofocus' : '' ?>>
                    <ul class="jmc-suggest" id="jmc-suggest" role="listbox" aria-label="Suggestions de destinataires" hidden></ul>
                </div>
            </div>
            <button type="button" class="jmc-btn jmc-to__browse" data-jmc-dir-open aria-haspopup="dialog" aria-controls="jmc-dir"><svg viewBox="0 0 20 20" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="2.5" width="12.5" height="15" rx="1.5"/><path d="M2.5 6h3M2.5 10h3M2.5 14h3"/><circle cx="10.5" cy="8" r="2"/><path d="M7.4 13.6c.5-1.6 1.6-2.4 3.1-2.4s2.6.8 3.1 2.4"/></svg><span>Choisir dans l’annuaire</span></button>
        </div>

        <div class="jmc-row">
            <label class="jmc-label" for="jnet-subject">Objet</label>
            <div class="jmc-field">
                <input id="jnet-subject" class="jmc-input" name="subject" type="text" maxlength="180"
                       placeholder="Ex. Consignes de préparation pour la sortie de dimanche"
                       value="<?= $h($subject) ?>"<?= $focus === 'subject' ? ' autofocus' : '' ?>>
                <span class="jmc-count ja-mono" data-jmc-count hidden aria-hidden="true"></span>
            </div>
        </div>

        <div class="jmc-row jmc-row--prec" role="radiogroup" aria-labelledby="jmc-prec-label">
            <span class="jmc-label" id="jmc-prec-label">Urgence</span>
            <div class="jmc-prec">
                <div class="jmc-seg">
                    <?php foreach ($precedences as $key => $meta): ?>
                        <label class="jmc-seg__opt jmc-seg__opt--<?= $h((string) $key) ?>">
                            <input type="radio" name="precedence" value="<?= $h((string) $key) ?>"<?= $currentPrecedence === (string) $key ? ' checked' : '' ?>>
                            <span><?= $h((string) $meta['label']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="jmc-prec__hint" aria-live="polite">
                    <?php foreach ($precedences as $key => $meta): ?>
                        <span data-jmc-hint="<?= $h((string) $key) ?>"><?= $h((string) $meta['hint']) ?></span>
                    <?php endforeach; ?>
                </p>
            </div>
        </div>

        <div class="jmc-row jmc-row--body">
            <label class="jmc-label" for="jnet-body">Message</label>
            <textarea id="jnet-body" class="jmc-body" name="body" rows="8" required
                      placeholder="Rédigez votre message : contexte, ce qui est attendu, échéance."><?= $h($body) ?></textarea>
        </div>

        <div class="jmc-send">
            <div class="jmc-send__recap" data-jmc-recap aria-live="polite">
                <p class="jmc-recap">
                    <span data-jmc-recap-none>Aucun destinataire pour l’instant.</span>
                    <span data-jmc-recap-some hidden>
                        <strong data-jmc-total>0</strong>
                        <span data-jmc-one hidden>destinataire</span><span data-jmc-many>destinataires</span>
                        <span data-jmc-via hidden>(dont <b data-jmc-via-n>0</b> par groupes, doublons écartés)</span>
                    </span>
                </p>
                <p class="jmc-rule" data-jmc-rule-mail hidden>Plus de <?= $emailLimit ?> destinataires : pas d’e-mail, le message reste dans la messagerie.</p>
                <p class="jmc-rule jmc-rule--bad" data-jmc-rule-max hidden>Plus de <?= $maxRecipients ?> destinataires : la diffusion sera refusée. Réduisez la sélection.</p>
                <p class="jmc-missing" data-jmc-missing hidden>
                    <span data-jmc-miss="to">Ajoutez au moins un destinataire.</span>
                    <span data-jmc-miss="subject">Indiquez l’objet du message.</span>
                    <span data-jmc-miss="body">Écrivez le message.</span>
                </p>
            </div>
            <div class="jmc-send__actions">
                <span class="jmc-kbd" aria-hidden="true" translate="no"><kbd>Ctrl</kbd> + <kbd>↵</kbd></span>
                <a class="jnet-btn jmc-cancel" href="<?= $h(url('jnet/courrier')) ?>">Annuler</a>
                <button class="jnet-btn jnet-btn--solid jmc-submit" type="submit" data-jmc-submit>Transmettre le message</button>
            </div>
        </div>

        <dialog class="jmc-dir" id="jmc-dir" aria-labelledby="jmc-dir-title" data-jmc-dir>
            <div class="jmc-dir__head">
                <div>
                    <h2 id="jmc-dir-title">Annuaire</h2>
                    <p>Cochez des membres, des formations ou une diffusion générale.</p>
                </div>
                <button type="button" class="jmc-dir__close" data-jmc-dir-close aria-label="Fermer l’annuaire">×</button>
            </div>
            <div class="jmc-dir__tools">
                <label class="ja-sr" for="jmc-dir-q">Rechercher dans l’annuaire</label>
                <input id="jmc-dir-q" class="jmc-input" type="search" autocomplete="off" data-jmc-dir-q
                       placeholder="Rechercher un nom, un indicatif, une formation, une fonction…">
                <div class="jmc-dir__tabs" role="tablist" aria-label="Types de destinataires">
                    <button type="button" role="tab" id="jmc-tab-members" aria-controls="jmc-panel-members" aria-selected="true" data-jmc-tab="members">Membres <em data-jmc-tab-n><?= count($directory) ?></em></button>
                    <button type="button" role="tab" id="jmc-tab-formations" aria-controls="jmc-panel-formations" aria-selected="false" tabindex="-1" data-jmc-tab="formations"><span translate="no">Formations</span> <em data-jmc-tab-n><?= count($formations) ?></em></button>
                    <button type="button" role="tab" id="jmc-tab-general" aria-controls="jmc-panel-general" aria-selected="false" tabindex="-1" data-jmc-tab="general">Diffusion générale <em data-jmc-tab-n><?= count($general) ?></em></button>
                </div>
            </div>

            <div class="jmc-dir__panel" id="jmc-panel-members" role="tabpanel" aria-labelledby="jmc-tab-members" data-jmc-panel="members">
                <div class="jmc-dir__bulk">
                    <button type="button" class="jmc-link" data-jmc-all>Tout sélectionner</button>
                    <button type="button" class="jmc-link" data-jmc-clear>Tout retirer</button>
                </div>
                <ul class="jmc-opts">
                    <?php foreach ($directory as $m): ?>
                        <?php
                        $mid = (int) ($m['id'] ?? 0);
                        $line = $memberLine($m);
                        $photo = (string) ($m['photo'] ?? '');
                        ?>
                        <li class="jmc-opt" data-jmc-opt data-search="<?= $h(trim((string) ($m['name'] ?? '') . ' ' . $line)) ?>">
                            <input type="checkbox" id="mbr-<?= $mid ?>" name="members[]" value="<?= $mid ?>"<?= isset($checkedMembers[$mid]) ? ' checked' : '' ?>>
                            <label for="mbr-<?= $mid ?>">
                                <?php if ($photo !== ''): ?>
                                    <img class="jmc-av" src="<?= $h($photo) ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <span class="jmc-av" translate="no"><?= $h((string) ($m['initials'] ?? '??')) ?></span>
                                <?php endif; ?>
                                <span class="jmc-opt__id" translate="no">
                                    <strong><?= $h((string) ($m['name'] ?? '')) ?></strong>
                                    <?php if ($line !== ''): ?><small><?= $h($line) ?></small><?php endif; ?>
                                </span>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="jmc-dir__empty" data-jmc-empty hidden>Aucun membre ne correspond à cette recherche.</p>
            </div>

            <div class="jmc-dir__panel" id="jmc-panel-formations" role="tabpanel" aria-labelledby="jmc-tab-formations" data-jmc-panel="formations" hidden>
                <div class="jmc-dir__bulk">
                    <p>Une formation comprend ses sous-ensembles. Les doublons sont écartés à l’envoi.</p>
                    <button type="button" class="jmc-link" data-jmc-clear>Tout retirer</button>
                </div>
                <ul class="jmc-opts">
                    <?php foreach ($formations as $g): ?>
                        <?php $key = (string) $g['key']; $n = (int) $g['count']; ?>
                        <li class="jmc-opt" data-jmc-opt data-search="<?= $h((string) $g['label']) ?>">
                            <input type="checkbox" id="<?= $h($groupId($key)) ?>" name="groups[]" value="<?= $h($key) ?>" data-count="<?= $n ?>"
                                   data-members="<?= $h(implode(',', array_map('intval', (array) ($groupMembers[$key] ?? [])))) ?>"<?= isset($checkedGroups[$key]) ? ' checked' : '' ?>>
                            <label for="<?= $h($groupId($key)) ?>">
                                <span class="jmc-av jmc-av--group"><?= $groupIcon ?></span>
                                <span class="jmc-opt__id">
                                    <strong translate="no"><?= $h((string) $g['label']) ?></strong>
                                    <small><?= $n ?> membre<?= $plural($n) ?></small>
                                </span>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="jmc-dir__empty" data-jmc-empty<?= $formations === [] ? '' : ' hidden' ?>>Aucune formation ne correspond à cette recherche.</p>
            </div>

            <div class="jmc-dir__panel" id="jmc-panel-general" role="tabpanel" aria-labelledby="jmc-tab-general" data-jmc-panel="general" hidden>
                <ul class="jmc-opts">
                    <?php foreach ($general as $g): ?>
                        <?php $key = (string) $g['key']; $n = (int) $g['count']; $isList = (string) ($g['kind'] ?? '') === 'liste'; ?>
                        <li class="jmc-opt" data-jmc-opt data-search="<?= $h((string) $g['label']) ?>">
                            <input type="checkbox" id="<?= $h($groupId($key)) ?>" name="groups[]" value="<?= $h($key) ?>" data-count="<?= $n ?>"<?= $key === 'all' ? ' data-jmc-everyone' : '' ?>
                                   data-members="<?= $h(implode(',', array_map('intval', (array) ($groupMembers[$key] ?? [])))) ?>"<?= isset($checkedGroups[$key]) ? ' checked' : '' ?>>
                            <label for="<?= $h($groupId($key)) ?>">
                                <span class="jmc-av jmc-av--group"><?= $groupIcon ?></span>
                                <span class="jmc-opt__id">
                                    <strong<?= $isList ? ' translate="no"' : '' ?>><?= $h((string) $g['label']) ?></strong>
                                    <small><?= $n ?> membre<?= $plural($n) ?></small>
                                    <span class="jmc-opt__desc"<?= $isList ? ' translate="no"' : '' ?>><?= $h((string) $g['description']) ?></span>
                                </span>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="jmc-dir__empty" data-jmc-empty hidden>Aucune diffusion ne correspond à cette recherche.</p>
            </div>

            <div class="jmc-dir__foot">
                <p class="jmc-dir__count" aria-live="polite"><strong data-jmc-dir-n>0</strong> <span data-jmc-dir-one hidden>sélectionné</span><span data-jmc-dir-many>sélectionnés</span></p>
                <button type="button" class="jnet-btn jnet-btn--solid" data-jmc-dir-close>Terminé</button>
            </div>
        </dialog>
    </form>

    <dialog class="jmc-confirm" data-jmc-confirm aria-labelledby="jmc-confirm-title">
        <span class="jmc-band__halo" aria-hidden="true"></span>
        <span class="jmc-band__veil" aria-hidden="true"></span>
        <div class="jmc-confirm__body">
            <h2 id="jmc-confirm-title" class="ja-disp ja-disp--sm">
                <span data-jmc-confirm-title="flash">Envoyer en Flash ?</span>
                <span data-jmc-confirm-title="everyone" hidden>Envoyer à toute l’unité ?</span>
            </h2>
            <p class="jmc-confirm__count"><strong data-jmc-confirm-n>0</strong> <span data-jmc-confirm-one hidden>destinataire va recevoir ce message.</span><span data-jmc-confirm-many>destinataires vont recevoir ce message.</span></p>
            <p class="jmc-confirm__why" data-jmc-confirm-why="flash">Flash est réservé à l’urgence opérationnelle : il interrompt le service courant de chaque destinataire.</p>
            <p class="jmc-confirm__why" data-jmc-confirm-why="everyone" hidden>Tous les membres actifs de l’unité sont concernés.</p>
            <p class="jmc-confirm__why" data-jmc-confirm-mail hidden>Aucun e-mail ne part au-delà de <?= $emailLimit ?> destinataires : le message reste dans la messagerie.</p>
            <div class="jmc-confirm__actions">
                <button type="button" class="jnet-btn" data-jmc-confirm-back>Revenir au message</button>
                <button type="button" class="jnet-btn jnet-btn--solid" data-jmc-confirm-go>Envoyer maintenant</button>
            </div>
        </div>
    </dialog>

    <p hidden data-jmc-noresult>Aucun résultat dans l’annuaire.</p>
    <?php endif; ?>
</div>
