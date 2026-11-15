<?php

declare(strict_types=1);

use App\Support\SseAccessCatalog as C;

/**
 * Fiche effectif d'un membre : bloc « Accès SSE » (carte dans l'onglet Accès + fenêtre « Donner ou retirer un accès »).
 * Visible des seuls gestionnaires SSE : sans droit de gestion, $sseAccess vaut null et rien n'est affiché. Aucun surtitre.
 *
 * @var string $ssePart 'card' ou 'dialog'
 * @var array<string, mixed>|null $sseAccess SseAccessMemberRights::overview()
 * @var int $id identifiant du membre
 * @var string $name nom du membre
 * @var string $csrfToken
 * @var callable $h
 */
$sseAccess = is_array($sseAccess ?? null) ? $sseAccess : null;
if ($sseAccess === null) {
    return;
}
$sseBase = effectifs_workspace_url('membres/' . $id . '/acces-sse');
$sseAccessLabels = [];
foreach (C::SIMPLE_ACCESS as $key => $def) {
    $sseAccessLabels[$key] = $def['label'];
}
$sseState = ['scheduled' => 'À venir', 'expired' => 'Échue', 'active' => 'En cours'];
$sseDates = static fn (array $r): string => $r['ends_at'] !== null && $r['ends_at'] !== '' ? 'jusqu’au ' . substr((string) $r['ends_at'], 0, 10) : 'sans date de fin';
?>
<?php if ($ssePart === 'card'): ?>
<article class="eff-fm-card eff-sse-card" aria-labelledby="acces-sse-titre">
    <div class="eff-fm-card__head">
        <h3 id="acces-sse-titre">Accès SSE</h3>
        <div class="eff-sse-actions">
            <button type="button" class="eff-fm-btn is-sm" data-dialog-open="eff-dlg-acces-sse" data-sse-mode="allow">Donner un accès</button>
            <button type="button" class="eff-fm-btn is-sm" data-dialog-open="eff-dlg-acces-sse" data-sse-mode="deny">Retirer un accès</button>
        </div>
    </div>
    <?php if (empty($sseAccess['ready'])): ?>
        <p class="eff-fm-lead">Les droits d’accès SSE ne sont pas encore installés dans cette communauté : les droits d’origine s’appliquent seuls.</p>
    <?php else: ?>
        <?php if (empty($sseAccess['entry'])): ?>
            <p class="eff-fm-alert is-w"><span><b>Pas de droit d’entrer au bureau SSE.</b> Tous les droits ci-dessous restent fermés tant qu’il lui manque ce droit d’entrer.</span></p>
        <?php endif; ?>
        <p class="eff-fm-lead">Ce que <?= $h($name) ?> peut faire dans le bureau SSE, et d’où vient chaque droit.</p>
        <div class="eff-sse-tbw">
            <table class="eff-sse-tb">
                <caption class="eff-sr-only">Droits effectifs du membre dans le bureau SSE, par section</caption>
                <thead><tr><th scope="col">Section</th><?php foreach ($sseAccessLabels as $lab): ?><th scope="col"><?= $h($lab) ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                <?php foreach ($sseAccess['rows'] as $row): ?>
                    <tr>
                        <th scope="row"><?= $h($row['label']) ?></th>
                        <?php foreach ($sseAccessLabels as $access => $lab): $cell = $row['cells'][$access]; ?>
                            <td data-label="<?= $h($lab) ?>">
                                <?php if (!$cell['applies']): ?>
                                    <span class="eff-sse-na">Sans objet</span>
                                <?php else: ?>
                                    <span class="eff-sse-flag <?= $cell['allowed'] ? 'is-yes' : 'is-no' ?>"><?= $cell['allowed'] ? 'Oui' : 'Non' ?></span>
                                    <?php foreach ($cell['origins'] as $o): ?>
                                        <small class="eff-sse-origin<?= $o['effect'] === 'deny' ? ' is-deny' : '' ?>">
                                            <?php if ($o['kind'] === 'legacy'): ?>
                                                Droit d’origine
                                            <?php else: ?>
                                                <?= $o['effect'] === 'deny' ? 'Refus · ' : '' ?><?= $h($o['type_label']) ?> : <?= $h($o['label']) ?><?= $o['ends_at'] !== null ? ' (jusqu’au ' . $h(substr((string) $o['ends_at'], 0, 10)) . ')' : '' ?>
                                            <?php endif; ?>
                                        </small>
                                    <?php endforeach; ?>
                                    <?php foreach ($cell['own'] as $ruleId): ?>
                                        <form method="post" action="<?= $h($sseBase . '/' . (int) $ruleId . '/retirer') ?>" class="eff-sse-inline">
                                            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                            <button type="submit" class="eff-fm-btn is-sm" aria-label="<?= $h(str_replace(['%1', '%2'], [$lab, $row['label']], 'Retirer l’accès « %1 » à %2')) ?>">Retirer</button>
                                        </form>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="eff-fm-hint">
            <a href="<?= $h(url('atak/sse/droits')) ?>">Ouvrir « Qui peut quoi » dans le bureau SSE</a>
        </p>
    <?php endif; ?>
</article>
<?php else: ?>
<dialog class="eff-dialog eff-dialog--wide eff-sse-dialog" id="eff-dlg-acces-sse" aria-labelledby="eff-dlg-acces-sse-title" data-sse-preview-url="<?= $h($sseBase . '/apercu') ?>">
    <div class="eff-dialog__body">
        <header class="eff-dialog__head">
            <h2 id="eff-dlg-acces-sse-title">Accès SSE de <?= $h($name) ?></h2>
            <button type="button" class="eff-dialog__close" data-dialog-close aria-label="Fermer">×</button>
        </header>
        <?php if (empty($sseAccess['ready'])): ?>
            <p class="eff-card__hint">Les droits d’accès SSE ne sont pas encore installés dans cette communauté.</p>
        <?php else: ?>
        <form method="post" action="<?= $h($sseBase) ?>" class="eff-sse-form" data-sse-form>
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <fieldset class="eff-sse-fs">
                <legend>Que faire ?</legend>
                <label class="eff-sse-opt"><input type="radio" name="effect" value="allow" checked> <span>Donner un accès</span></label>
                <label class="eff-sse-opt"><input type="radio" name="effect" value="deny"> <span>Retirer l’accès (refus : il l’emporte sur tout le reste)</span></label>
            </fieldset>
            <fieldset class="eff-sse-fs">
                <legend>Niveau</legend>
                <?php foreach (C::SIMPLE_ACCESS as $key => $def): ?>
                    <label class="eff-sse-opt"><input type="radio" name="access" value="<?= $h($key) ?>"<?= $key === 'voir' ? ' checked' : '' ?>> <span><b><?= $h($def['label']) ?></b> <small><?= $h(['voir' => 'consulter', 'ecrire' => 'consulter et modifier', 'gerer' => 'tout, y compris régler la section'][$key]) ?></small></span></label>
                <?php endforeach; ?>
            </fieldset>
            <fieldset class="eff-sse-fs">
                <legend>Sections</legend>
                <label class="eff-sse-opt"><input type="checkbox" name="sections[]" value="*" data-sse-all> <span><b>Toutes les sections</b></span></label>
                <div class="eff-sse-sections">
                    <?php foreach ($sseAccess['rows'] as $row): ?>
                        <label class="eff-sse-opt"><input type="checkbox" name="sections[]" value="<?= $h($row['key']) ?>"> <span><?= $h($row['label']) ?></span></label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <div class="eff-sse-g">
                <label>Jusqu’au (facultatif)<input type="date" name="ends_at" min="<?= $h(date('Y-m-d', time() + 86400)) ?>"></label>
                <label>Motif (obligatoire, 5 caractères au moins)<input type="text" name="reason" maxlength="<?= (int) C::REASON_MAX ?>" required minlength="<?= (int) C::REASON_MIN ?>" autocomplete="off"></label>
            </div>
            <p class="eff-sse-effect" role="status" aria-live="polite" data-sse-effect>Choisissez un niveau et au moins une section : l’effet s’affiche ici avant l’enregistrement.</p>
            <footer class="eff-dialog__foot">
                <button type="button" class="eff-btn eff-btn--ghost" data-dialog-close>Annuler</button>
                <span class="eff-dialog__spacer"></span>
                <button type="submit" class="eff-btn eff-btn--primary" data-sse-submit>Enregistrer</button>
            </footer>
        </form>

        <section class="eff-sse-sec" aria-labelledby="eff-sse-own-t">
            <h3 id="eff-sse-own-t">Règles propres à ce membre</h3>
            <?php if ($sseAccess['own'] === []): ?>
                <p class="eff-card__hint">Aucune règle d’identité pour ce membre.</p>
            <?php else: ?>
                <ul class="eff-sse-list">
                    <?php foreach ($sseAccess['own'] as $r): ?>
                        <li>
                            <span><b><?= $r['effect'] === 'deny' ? 'Refus' : 'Accord' ?></b> · <?= $h($r['section_label']) ?> · <?= $h($r['level_label']) ?> · <?= $h($sseDates($r)) ?> · <?= $h($sseState[$r['state']] ?? $r['state']) ?><?php if ($r['reason'] !== ''): ?><small>Motif : <?= $h($r['reason']) ?></small><?php endif; ?></span>
                            <form method="post" action="<?= $h($sseBase . '/' . (int) $r['id'] . '/retirer') ?>">
                                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                <button type="submit" class="eff-btn eff-btn--ghost">Retirer</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="eff-sse-sec" aria-labelledby="eff-sse-hist-t">
            <h3 id="eff-sse-hist-t">Historique des changements</h3>
            <?php if ($sseAccess['history'] === []): ?>
                <p class="eff-card__hint">Aucun changement enregistré pour ce membre.</p>
            <?php else: ?>
                <ul class="eff-sse-list">
                    <?php foreach ($sseAccess['history'] as $hist): ?>
                        <li>
                            <span>
                                <b><?= $hist['action'] === 'revoke' ? 'Retrait' : ($hist['effect'] === 'deny' ? 'Refus' : 'Accord') ?></b> · <?= $h(C::sectionLabel((string) $hist['section'])) ?> · <?= $h(C::levelLabel((string) $hist['level'])) ?>
                                <small><?= $h(substr((string) $hist['created_at'], 0, 16)) ?> · <?= $h($hist['actor_label']) ?><?= $hist['reason'] !== '' ? ' · ' . $h($hist['reason']) : '' ?></small>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p class="eff-card__hint"><a href="<?= $h(url('atak/sse/droits/historique')) ?>">Historique complet de la communauté</a></p>
        </section>
        <?php endif; ?>
    </div>
</dialog>
<?php endif; ?>
