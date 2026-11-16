<?php
declare(strict_types=1);

use App\Services\Intel\IntelIntakePresenter as P;
use App\Services\Intel\IntelIntakeService;

/**
 * Bureau SSE — suivi d'une remontée (compte rendu ou fiche), préparé par IntelIntakeDesk comme la page du back-office.
 * Inclus deux fois par la page hôte : $intakePart = 'panel' (état, cycle, pastilles, actions, fil) dans la colonne,
 * puis $intakePart = 'dialogs' (fenêtres) hors des panneaux masqués. Classes .fnr-* (sse-fiche-rens.css).
 *
 * @var array<string, mixed> $intakeDesk
 * @var list<array{id: int, label: string}> $intakeMembers
 * @var array<string, string> $intakeTypes
 * Depuis la page d'une fiche, chaque formulaire porte retour=fiche : l'action revient sur la fiche (#suivi). Le
 * traitement complet (données, caviardage, suppression, Steam, liens, historique) est sur la page de la remontée.
 * @var string $intakePart
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$isd = $intakeDesk;
$isIt = $isd['item'];
$isSrc = (string) $isIt['source'];
$isRights = $isd['rights'];
$isState = (string) $isIt['state'];
$isOpen = !empty($isd['open']);
$isTreat = $isRights['treat'] && empty($isIt['deleted']);
$isBase = url('atak/sse/remontees/' . $isSrc . '/' . (int) $isIt['id']);
$isCsrf = '<input type="hidden" name="_csrf_token" value="' . $h(\App\Core\Csrf::token()) . '">' . ($isSrc === 'fiche' ? '<input type="hidden" name="retour" value="fiche">' : '');
$isTone = ['a_traiter' => 'new', 'prise_en_compte' => 'busy', 'en_cours' => 'busy', 'transmise' => 'busy', 'exploitee' => 'done'][$isState] ?? 'idle';
$isChipTone = ['danger' => 'critical', 'warn' => 'warning', 'caution' => 'caution'];
$isUrl = static fn (string $s, int $i): string => $s === 'fiche' ? url('atak/sse/fiches/' . $i) : url('atak/sse/remontees/cr/' . $i);
$isClose = '<button type="button" class="fnr-dialog__close" data-fnr-close aria-label="Fermer la fenêtre"><svg class="fnr-i" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg></button>';
$isCerbere = '<span class="fnr-cerbere">Proposé par Cerbère</span>';
$isDelay = $isd['delay'];
$isMembers = is_array($intakeMembers ?? null) ? $intakeMembers : [];
$isInfo = is_array($isIt['state_info'] ?? null) ? $isIt['state_info'] : [];
?>
<?php if (($intakePart ?? 'panel') === 'panel'): ?>
    <div class="fnr-state fnr-state--<?= $h($isTone) ?>">
        <p class="fnr-state__label"><span class="fnr-dot" aria-hidden="true"></span><?= $h($isIt['state_label']) ?></p>
        <p class="fnr-state__hint"><?= $h(IntelIntakeService::STATE_HINTS[$isState] ?? '') ?></p>
        <?php if (!empty($isInfo['forwarded_to'])): ?><p class="fnr-state__hint">Transmise à <?= $h((string) $isInfo['forwarded_to']) ?>.</p><?php endif; ?>
        <?php if (!empty($isInfo['duplicate_of']['ref'])): ?><p class="fnr-state__hint">Doublon de <a href="<?= $h($isUrl((string) $isInfo['duplicate_of']['source'], (int) $isInfo['duplicate_of']['id'])) ?>"><?= $h((string) $isInfo['duplicate_of']['ref']) ?></a>.</p><?php endif; ?>
    </div>

    <ol class="fnr-cycle<?= $isDelay['late'] ? ' is-late' : '' ?>" aria-label="Cycle de prise en compte">
        <?php foreach ($isd['cycle'] as $step): ?>
            <li class="is-<?= $h($step['status']) ?>"<?= $step['status'] === 'current' ? ' aria-current="step"' : '' ?>>
                <strong><?= $h($step['label']) ?></strong>
                <?php if ($step['at'] !== '' && $step['status'] !== 'todo'): ?><span><?= $h(P::when($step['at'])) ?></span><?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
    <p class="fnr-delay<?= $isDelay['late'] ? ' is-late' : '' ?>"><strong><?= $h($isDelay['label']) ?></strong> · <?= $h($isDelay['target_label']) ?><?php if ($isIt['assignee'] !== null): ?> · traitant : <span translate="no"><?= $h($isIt['assignee']['label']) ?></span><?php endif; ?></p>

    <ul class="fnr-chips fnr-chips--suivi" aria-label="Pastilles de la remontée">
        <?php foreach ($isd['badges'] as $b): if ($b['key'] === 'etat') { continue; } ?>
            <li class="fnr-chip<?= isset($isChipTone[$b['tone']]) ? ' fnr-chip--' . $h($isChipTone[$b['tone']]) : '' ?>"<?= $b['title'] !== '' ? ' title="' . $h($b['title']) . '"' : '' ?>><?= $h($b['label']) ?></li>
        <?php endforeach; ?>
    </ul>

    <?php if (!$isTreat): ?>
        <p class="fnr-dim">Le traitement des remontées est réservé à qui gère les fiches de renseignement.</p>
    <?php else: ?>
        <div class="fnr-suivi-actions">
            <?php if ($isState === 'a_traiter'): ?>
                <form method="post" action="<?= $h($isBase . '/take') ?>"><?= $isCsrf ?><button type="submit" class="btn fnr-wide" title="Elle vous est attribuée et l’heure est notée.">Prendre en compte</button></form>
            <?php elseif ($isState === 'prise_en_compte'): ?>
                <form method="post" action="<?= $h($isBase . '/state') ?>"><?= $isCsrf ?><input type="hidden" name="state" value="en_cours"><button type="submit" class="btn fnr-wide">Mettre en exploitation</button></form>
            <?php endif; ?>
            <?php if ($isState !== 'exploitee'): ?>
                <form method="post" action="<?= $h($isBase . '/state') ?>"><?= $isCsrf ?><input type="hidden" name="state" value="exploitee"><button type="submit" class="btn <?= in_array($isState, ['en_cours', 'transmise'], true) ? '' : 'btn--ghost' ?> fnr-wide">Marquer exploitée</button></form>
            <?php endif; ?>
            <button type="button" class="btn btn--ghost" data-fnr-open="fnr-dlg-rm-transmettre" aria-haspopup="dialog">Transmettre</button>
            <button type="button" class="btn btn--ghost" data-fnr-open="fnr-dlg-rm-doublon" aria-haspopup="dialog">Doublon</button>
            <?php if ($isOpen): ?>
                <button type="button" class="btn btn--ghost" data-fnr-open="fnr-dlg-rm-clore" aria-haspopup="dialog">Classer sans suite</button>
            <?php else: ?>
                <form method="post" action="<?= $h($isBase . '/state') ?>"><?= $isCsrf ?><input type="hidden" name="state" value="a_traiter"><button type="submit" class="btn btn--ghost fnr-wide">Rouvrir</button></form>
            <?php endif; ?>
            <button type="button" class="btn btn--ghost" data-fnr-open="fnr-dlg-rm-attribuer" aria-haspopup="dialog"><?= $isIt['assignee'] !== null ? 'Réattribuer' : 'Attribuer' ?></button>
            <?php if ($isRights['convert']): ?><button type="button" class="btn btn--ghost" data-fnr-open="fnr-dlg-rm-convertir" aria-haspopup="dialog">Convertir</button><?php endif; ?>
            <?php if ($isRights['discord']): ?><button type="button" class="btn btn--ghost" data-fnr-open="fnr-dlg-rm-discord" aria-haspopup="dialog">Notifier sur Discord</button><?php endif; ?>
        </div>

        <?php // Une proposition qui corrige les données (urgence) s'applique depuis le back-office : elle n'est pas offerte ici. ?>
        <?php foreach ($isd['proposals'] as $p): if ($p['action'] === 'edit') { continue; } ?>
            <form method="post" action="<?= $h($isBase . '/' . $p['action']) ?>" class="fnr-proposal">
                <?= $isCsrf ?>
                <?php foreach ($p['fields'] as $name => $value): ?><input type="hidden" name="<?= $h($name) ?>" value="<?= $h($value) ?>"><?php endforeach; ?>
                <p><?= $isCerbere ?> <?= $h($p['label']) ?> : <strong><?= $h($p['value_label']) ?></strong></p>
                <p class="fnr-dim"><?= $h($p['reason']) ?></p>
                <button type="submit" class="btn btn--ghost fnr-mini">Appliquer</button>
            </form>
        <?php endforeach; ?>

        <form method="post" action="<?= $h($isBase . '/comment') ?>" class="fnr-comment">
            <?= $isCsrf ?>
            <label class="fnr-field" for="fnr-rm-comment">Ajouter un commentaire
                <textarea id="fnr-rm-comment" name="body" rows="2" maxlength="8000" required placeholder="Analyse, recoupement, consigne…"></textarea>
            </label>
            <button type="submit" class="btn btn--ghost fnr-wide">Commenter</button>
        </form>

        <ol class="fnr-history">
            <?php foreach (array_reverse($isd['timeline']) as $entry): ?>
                <?php if ($entry['type'] === 'read'): $r = $entry['read']; ?>
                    <li class="fnr-history__read">
                        <p class="fnr-history__what"><strong translate="no"><?= $h($r['label']) ?></strong> <span>a lu la remontée</span> <span>(<?= $h(P::CHANNELS[$r['channel']] ?? $r['channel']) ?><?= $r['count'] > 1 ? ', ' . (int) $r['count'] . ' fois' : '' ?>)</span></p>
                        <p class="fnr-history__when"><?= $h(P::when((string) $r['first'])) ?></p>
                    </li>
                <?php else: $e = $entry['event']; if (in_array($e['kind'], ['steam'], true)) { continue; } ?>
                    <li>
                        <p class="fnr-history__what"><strong translate="no"><?= $h($e['actor']) ?></strong> <?= $e['kind'] === 'comment' ? 'a commenté' : P::eventLine($e, $isUrl) ?></p>
                        <?php if ($e['body'] !== ''): ?><blockquote class="fnr-quote" translate="no"><?= nl2br($h($e['body']), false) ?></blockquote><?php endif; ?>
                        <p class="fnr-history__when"><?= $h(P::when((string) $e['at'])) ?></p>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
            <li>
                <p class="fnr-history__what"><strong translate="no"><?= $h($isIt['author'] !== '' ? $isIt['author'] : 'Terrain') ?></strong> a remonté <?= $isSrc === 'cr' ? 'ce compte rendu' : 'cette fiche' ?></p>
                <p class="fnr-history__when"><?= $h(P::when((string) $isIt['at'])) ?></p>
            </li>
        </ol>
    <?php endif; ?>
    <?php if ($isRights['treat']): ?>
        <a class="btn btn--ghost fnr-wide" href="<?= $h($isBase) ?>">Traitement complet de la remontée</a>
    <?php endif; ?>
<?php elseif ($isTreat): ?>
    <dialog class="fnr-dialog" id="fnr-dlg-rm-transmettre" aria-labelledby="fnr-dlg-rm-transmettre-title">
        <form method="post" action="<?= $h($isBase . '/state') ?>" class="fnr-dialog__card">
            <?= $isCsrf ?><input type="hidden" name="state" value="transmise">
            <header class="fnr-dialog__head"><div><h2 id="fnr-dlg-rm-transmettre-title">Transmettre</h2><p class="fnr-desc">Choisissez l’échelon, la cellule ou le dossier qui reçoit la remontée.</p></div><?= $isClose ?></header>
            <div class="fnr-dialog__body">
                <label class="fnr-field" for="fnr-rm-fwd">Destinataire
                    <select id="fnr-rm-fwd" name="target" required autofocus>
                        <option value="">Choisir…</option>
                        <?php $group = ''; foreach ($isd['forward_targets'] as $t): if ($t['kind'] !== $group) { echo ($group !== '' ? '</optgroup>' : '') . '<optgroup label="' . $h($t['kind']) . '">'; $group = $t['kind']; } ?>
                            <option value="<?= $h($t['value']) ?>" translate="no"><?= $h($t['label']) ?></option>
                        <?php endforeach; echo $group !== '' ? '</optgroup>' : ''; ?>
                    </select>
                </label>
                <label class="fnr-field" for="fnr-rm-fwd-note">Motif (facultatif)
                    <input id="fnr-rm-fwd-note" type="text" name="note" maxlength="400" list="fnr-rm-fwd-reasons" placeholder="Visible dans le fil">
                </label>
                <datalist id="fnr-rm-fwd-reasons"><?php foreach ($isd['reasons']['transmise'] as $r): ?><option value="<?= $h($r) ?>"></option><?php endforeach; ?></datalist>
            </div>
            <footer class="fnr-dialog__foot"><button type="button" class="btn btn--ghost" data-fnr-close>Annuler</button><button type="submit" class="btn">Transmettre</button></footer>
        </form>
    </dialog>

    <dialog class="fnr-dialog" id="fnr-dlg-rm-doublon" aria-labelledby="fnr-dlg-rm-doublon-title">
        <form method="post" action="<?= $h($isBase . '/state') ?>" class="fnr-dialog__card">
            <?= $isCsrf ?><input type="hidden" name="state" value="doublon">
            <header class="fnr-dialog__head"><div><h2 id="fnr-dlg-rm-doublon-title">Marquer comme doublon</h2><p class="fnr-desc">Indiquez la remontée d’origine : celle-ci sera close et notée dans son fil.</p></div><?= $isClose ?></header>
            <div class="fnr-dialog__body">
                <?php if ($isd['duplicates'] !== []): ?>
                    <label class="fnr-field" for="fnr-rm-dup"><span><?= $isCerbere ?> Remontée d’origine</span>
                        <select id="fnr-rm-dup" name="target" required autofocus>
                            <option value="">Choisir…</option>
                            <?php foreach ($isd['duplicates'] as $dup): ?>
                                <option value="<?= $h($dup['source'] . '/' . $dup['id']) ?>"><?= $h($dup['ref'] . ' · ' . P::when($dup['at']) . ' · ' . $dup['reason']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php else: ?>
                    <label class="fnr-field" for="fnr-rm-dup">Référence de la remontée d’origine
                        <input id="fnr-rm-dup" type="text" name="target" maxlength="80" required autofocus placeholder="FR-2026-000041">
                    </label>
                    <p class="fnr-dim">Aucune remontée proche n’a été trouvée : la référence est vérifiée à l’enregistrement.</p>
                <?php endif; ?>
                <label class="fnr-field" for="fnr-rm-dup-note">Motif (facultatif)
                    <input id="fnr-rm-dup-note" type="text" name="note" maxlength="400" list="fnr-rm-dup-reasons" placeholder="Visible dans le fil">
                </label>
                <datalist id="fnr-rm-dup-reasons"><?php foreach ($isd['reasons']['doublon'] as $r): ?><option value="<?= $h($r) ?>"></option><?php endforeach; ?></datalist>
            </div>
            <footer class="fnr-dialog__foot"><button type="button" class="btn btn--ghost" data-fnr-close>Annuler</button><button type="submit" class="btn">Marquer doublon</button></footer>
        </form>
    </dialog>

    <dialog class="fnr-dialog fnr-dialog--small" id="fnr-dlg-rm-clore" aria-labelledby="fnr-dlg-rm-clore-title">
        <form method="post" action="<?= $h($isBase . '/state') ?>" class="fnr-dialog__card">
            <?= $isCsrf ?>
            <header class="fnr-dialog__head"><div><h2 id="fnr-dlg-rm-clore-title">Classer sans suite</h2><p class="fnr-desc">La remontée sort de la file ; elle peut être rouverte.</p></div><?= $isClose ?></header>
            <div class="fnr-dialog__body">
                <label class="fnr-field" for="fnr-rm-clo">Issue
                    <select id="fnr-rm-clo" name="state" autofocus>
                        <option value="close">Close</option>
                        <option value="non_exploitable">Non exploitable</option>
                    </select>
                </label>
                <label class="fnr-field" for="fnr-rm-clo-note">Motif (facultatif)
                    <input id="fnr-rm-clo-note" type="text" name="note" maxlength="400" list="fnr-rm-clo-reasons" placeholder="Visible dans le fil">
                </label>
                <datalist id="fnr-rm-clo-reasons"><?php foreach (array_merge($isd['reasons']['close'], $isd['reasons']['non_exploitable']) as $r): ?><option value="<?= $h($r) ?>"></option><?php endforeach; ?></datalist>
            </div>
            <footer class="fnr-dialog__foot"><button type="button" class="btn btn--ghost" data-fnr-close>Annuler</button><button type="submit" class="btn">Classer</button></footer>
        </form>
    </dialog>

    <dialog class="fnr-dialog fnr-dialog--small" id="fnr-dlg-rm-attribuer" aria-labelledby="fnr-dlg-rm-attribuer-title">
        <form method="post" action="<?= $h($isBase . '/assign') ?>" class="fnr-dialog__card">
            <?= $isCsrf ?>
            <header class="fnr-dialog__head"><div><h2 id="fnr-dlg-rm-attribuer-title">Traitant</h2><p class="fnr-desc">La personne qui répond de cette remontée.</p></div><?= $isClose ?></header>
            <div class="fnr-dialog__body">
                <?php $ap = $isd['assignee_proposal']; ?>
                <label class="fnr-field" for="fnr-rm-assign">Attribuer à
                    <select id="fnr-rm-assign" name="user_id" autofocus>
                        <option value="0">Personne</option>
                        <option value="<?= (int) $isd['viewer']['id'] ?>">Moi</option>
                        <?php foreach ($isMembers as $m): ?><option value="<?= (int) $m['id'] ?>"<?= ($isIt['assignee']['id'] ?? ($ap['user_id'] ?? 0)) === $m['id'] ? ' selected' : '' ?> translate="no"><?= $h($m['label']) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <?php if ($ap !== null): ?><p class="fnr-dim"><?= $isCerbere ?> <span translate="no"><?= $h($ap['label']) ?></span> : <?= $h($ap['reason']) ?></p><?php endif; ?>
            </div>
            <footer class="fnr-dialog__foot"><button type="button" class="btn btn--ghost" data-fnr-close>Annuler</button><button type="submit" class="btn">Attribuer</button></footer>
        </form>
    </dialog>

    <?php if ($isRights['convert']): ?>
        <dialog class="fnr-dialog fnr-dialog--small" id="fnr-dlg-rm-convertir" aria-labelledby="fnr-dlg-rm-convertir-title">
            <form method="post" action="<?= $h($isBase . '/convert') ?>" class="fnr-dialog__card">
                <?= $isCsrf ?>
                <header class="fnr-dialog__head"><div><h2 id="fnr-dlg-rm-convertir-title">Convertir</h2><p class="fnr-desc">Crée un élément du bureau à partir de cette remontée, qui reste intacte.</p></div><?= $isClose ?></header>
                <div class="fnr-dialog__body">
                    <?php $todo = array_values(array_filter($isd['conversions'], static fn (array $c): bool => $c['existing'] === null)); ?>
                    <?php if ($todo !== []): ?>
                        <label class="fnr-field" for="fnr-rm-conv">Convertir en
                            <select id="fnr-rm-conv" name="kind" autofocus>
                                <?php foreach ($todo as $c): ?><option value="<?= $h($c['kind']) ?>"><?= $h($c['label']) ?></option><?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>
                    <?php foreach ($isd['conversions'] as $c): if ($c['existing'] === null) { continue; } ?>
                        <p class="fnr-dim"><?= $h($c['label']) ?> déjà créé : <a href="<?= $h(url($c['existing']['url'])) ?>"><?= $h($c['existing']['ref']) ?></a></p>
                    <?php endforeach; ?>
                </div>
                <footer class="fnr-dialog__foot"><button type="button" class="btn btn--ghost" data-fnr-close>Annuler</button><button type="submit" class="btn"<?= $todo === [] ? ' disabled' : '' ?>>Convertir</button></footer>
            </form>
        </dialog>
    <?php endif; ?>

    <?php if ($isRights['discord']): $pv = $isd['discord']['preview']; ?>
        <dialog class="fnr-dialog fnr-dialog--small" id="fnr-dlg-rm-discord" aria-labelledby="fnr-dlg-rm-discord-title">
            <form method="post" action="<?= $h($isBase . '/discord') ?>" class="fnr-dialog__card">
                <?= $isCsrf ?>
                <header class="fnr-dialog__head"><div><h2 id="fnr-dlg-rm-discord-title">Notifier sur Discord</h2><p class="fnr-desc">Ce que le bot publiera : jamais le texte de la remontée.</p></div><?= $isClose ?></header>
                <div class="fnr-dialog__body">
                    <blockquote class="fnr-quote">
                        <strong>Remontée à regarder</strong><br>
                        <span translate="no"><?= $h($pv['ref']) ?> · <?= $h($pv['type']) ?></span><br>
                        Urgence : <?= $h(mb_strtolower($pv['urgency_label'])) ?>
                        <?php if ($pv['emitter'] !== ''): ?><br>Émetteur : <span translate="no"><?= $h($pv['emitter']) ?></span><?php endif; ?>
                        <?php if ($pv['title'] !== ''): ?><br><span translate="no"><?= $h($pv['title']) ?></span><?php endif; ?>
                    </blockquote>
                    <?php if ($isd['discord']['ready']): ?>
                        <p class="fnr-dim">Salon : <span translate="no"><?= $h($isd['discord']['channel']) ?></span>. Le bot l’envoie dans les cinq minutes.</p>
                    <?php else: ?>
                        <p class="fnr-dim"><?= $h($isd['discord']['reason']) ?></p>
                    <?php endif; ?>
                </div>
                <footer class="fnr-dialog__foot"><button type="button" class="btn btn--ghost" data-fnr-close>Annuler</button><button type="submit" class="btn"<?= $isd['discord']['ready'] ? '' : ' disabled' ?>>Envoyer la notification</button></footer>
            </form>
        </dialog>
    <?php endif; ?>
<?php endif; ?>
