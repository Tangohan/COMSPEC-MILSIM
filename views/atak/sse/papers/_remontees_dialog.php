<?php
declare(strict_types=1);
/**
 * Modale « Consigner depuis les contrôles » de la fiche d'un sujet contrôlé : liste filtrable des papiers remontés par l'ATAK,
 * cases à cocher, champs préremplis par Cerbère et modifiables avant validation. Inclus par identity_show.php.
 *
 * @var array<string,mixed> $identity
 * @var list<array<string,mixed>> $remontees SseCheckPaperSelectionService::candidates
 * @var callable $h
 * @var callable $dt
 * @var string $self
 */
use App\Support\SseIdentityCatalog as C;

$remStates = ['new' => 'À consigner', 'consigned' => 'Consigné', 'exists' => 'Déjà au registre', 'incomplete' => 'Incomplet'];
$remPreselect = (int) ($_GET['consigner'] ?? 0);
$remTypes = [];
foreach ($remontees as $r) {
    $remTypes[$r['fields']['doc_type']] = C::label(C::DOC_TYPES, $r['fields']['doc_type']);
}
?>
<dialog class="rem-dialog" id="rem-dialog" aria-labelledby="rem-title" data-rem-dialog <?= $remPreselect > 0 ? 'data-rem-preselect="' . $remPreselect . '"' : '' ?>>
    <form method="post" action="<?= $h(url($self . '/constates/remontees')) ?>" data-rem-form>
        <?= \App\Core\Csrf::field() ?>
        <span hidden data-rem-t="none">Aucun papier sélectionné</span><span hidden data-rem-t="one">1 papier sélectionné</span><span hidden data-rem-t="many">%d papiers sélectionnés</span><span hidden data-rem-t="show">Modifier</span><span hidden data-rem-t="hide">Replier</span>
        <header class="rem-head">
            <div>
                <h2 id="rem-title">Consigner depuis les contrôles</h2>
                <p class="rem-lead">Papiers relevés par l’ATAK lors des contrôles de ce sujet et des contrôles sans rattachement. Cochez ceux à consigner : Cerbère préremplit les champs depuis le contrôle, vous pouvez les modifier avant de valider.</p>
            </div>
            <button type="button" class="rem-close" data-rem-close aria-label="Fermer">×</button>
        </header>

        <div class="rem-filters" role="group" aria-label="Filtrer les remontées">
            <div class="rem-field rem-field--search">
                <label for="rem-q">Recherche</label>
                <input id="rem-q" type="search" data-rem-q placeholder="Numéro, nom présenté, contrôleur, lieu…" autocomplete="off">
            </div>
            <div class="rem-field">
                <label for="rem-state">État</label>
                <select id="rem-state" data-rem-state>
                    <option value="new">À consigner</option>
                    <option value="done">Déjà consignés ou au registre</option>
                    <option value="">Tous</option>
                </select>
            </div>
            <div class="rem-field">
                <label for="rem-type">Type de papier</label>
                <select id="rem-type" data-rem-type>
                    <option value="">Tous les types</option>
                    <?php foreach ($remTypes as $k => $label): ?><option value="<?= $h($k) ?>"><?= $h($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="rem-field">
                <label for="rem-scope">Rattachement</label>
                <select id="rem-scope" data-rem-scope>
                    <option value="">Tous</option>
                    <option value="mine">Rattachés à ce sujet</option>
                    <option value="free">Sans rattachement</option>
                </select>
            </div>
            <div class="rem-field">
                <label for="rem-since">Période</label>
                <select id="rem-since" data-rem-since>
                    <option value="">Toute la période</option>
                    <option value="1">Dernières 24 h</option>
                    <option value="7">7 derniers jours</option>
                    <option value="30">30 derniers jours</option>
                </select>
            </div>
        </div>

        <div class="rem-body">
            <?php if ($remontees === []): ?>
                <div class="rem-empty"><strong>Aucune remontée</strong><p>Aucun contrôle de l’ATAK n’a relevé de papier pour ce sujet. Dès qu’un contrôle est transmis avec un numéro de papier, il apparaît ici.</p></div>
            <?php else: ?>
                <ul class="rem-list" data-rem-list>
                    <?php foreach ($remontees as $r): $f = $r['fields']; $id = (int) $r['id']; $ts = strtotime(substr($r['created_at'], 0, 19) . ' UTC') ?: 0; ?>
                        <li class="rem-row<?= $r['selectable'] ? '' : ' is-locked' ?>" data-rem-row data-id="<?= $id ?>" data-state="<?= $h($r['state'] === 'new' || $r['state'] === 'incomplete' ? 'new' : 'done') ?>" data-type="<?= $h($f['doc_type']) ?>" data-scope="<?= $h($r['scope']) ?>" data-ts="<?= $ts ?>" data-key="<?= $h($f['country_code'] . '|' . $f['doc_type'] . '|' . $f['doc_number']) ?>" data-text="<?= $h(mb_strtolower($f['doc_number'] . ' ' . $r['presented_name'] . ' ' . $r['controller'] . ' ' . $r['grid'] . ' ' . $r['ref'] . ' ' . $r['op_key'])) ?>">
                            <div class="rem-main">
                                <label class="rem-pick">
                                    <input type="checkbox" name="pick[<?= $id ?>]" value="1" data-rem-pick <?= $r['selectable'] ? '' : 'disabled' ?> aria-label="Consigner le papier <?= $h($f['doc_number']) ?> du contrôle <?= $h($r['ref']) ?>">
                                </label>
                                <div class="rem-sum">
                                    <p class="rem-title">
                                        <strong><?= $h(C::label(C::DOC_TYPES, $f['doc_type'])) ?></strong>
                                        <span class="rem-num" translate="no"><?= $h(trim($f['country_code'] . ' ' . $f['doc_number'])) ?></span>
                                        <span class="rem-badge rem-badge--<?= $h($r['state']) ?>"><?= $h($remStates[$r['state']] ?? $r['state']) ?></span>
                                        <?php if ($r['doc_ref'] !== ''): ?><a class="rem-link" href="<?= $h(url('atak/sse/papiers/documents/' . (int) $r['doc_id'])) ?>"><?= $h($r['doc_ref']) ?></a><?php endif; ?>
                                    </p>
                                    <p class="rem-meta">
                                        <a class="rem-link" href="<?= $h(url('atak/sse/papiers/controles/' . $id)) ?>"><?= $h($r['ref']) ?></a>
                                        · <?= $h($dt($r['created_at'])) ?>
                                        <?php if ($r['controller'] !== ''): ?> · <?= $h($r['controller']) ?><?php endif; ?>
                                        <?php if ($f['place'] !== ''): ?> · <?= $h($f['place']) ?><?php endif; ?>
                                    </p>
                                    <p class="rem-meta">
                                        <?php if ($r['presented_name'] !== ''): ?>Nom présenté : <span translate="no"><?= $h($r['presented_name']) ?></span><?php if ($r['presented_dob'] !== ''): ?> · Naissance : <?= $h($r['presented_dob']) ?><?php endif; ?><?php else: ?>Aucun nom présenté<?php endif; ?>
                                        <?php if ($f['expires_on'] !== ''): ?> · Expire le <?= $h($f['expires_on']) ?><?php endif; ?>
                                        <?php if ($r['verdict_label'] !== ''): ?> · Verdict : <?= $h($r['verdict_label']) ?><?php endif; ?>
                                    </p>
                                    <p class="rem-dup" data-rem-dup hidden>Même papier sélectionné deux fois : un seul papier sera créé, rattaché aux deux contrôles.</p>
                                </div>
                                <?php if (is_array($r['paper_image'] ?? null)): ?>
                                    <a class="rem-shot" href="<?= $h($r['paper_image']['url']) ?>" target="_blank" rel="noopener"><img src="<?= $h($r['paper_image']['thumb_url']) ?>" alt="Photo du papier <?= $h($f['doc_number']) ?> prise au contrôle <?= $h($r['ref']) ?>" loading="lazy"></a>
                                <?php endif; ?>
                                <?php if ($r['selectable']): ?>
                                    <button type="button" class="rem-edit" data-rem-edit aria-expanded="false" aria-controls="rem-f-<?= $id ?>">Modifier</button>
                                <?php endif; ?>
                            </div>
                            <?php if ($r['selectable']): ?>
                                <div class="rem-fields" id="rem-f-<?= $id ?>" hidden>
                                    <p class="rem-cerbere">Cerbère a prérempli ces champs depuis le contrôle. Modifiez ce qui doit l’être avant de consigner.</p>
                                    <div class="rem-grid">
                                        <div class="rem-field"><label for="rem-t-<?= $id ?>">Type de papier</label>
                                            <select id="rem-t-<?= $id ?>" name="row[<?= $id ?>][doc_type]">
                                                <?php foreach (C::DOC_TYPES as $k => $label): ?><option value="<?= $h($k) ?>" <?= $f['doc_type'] === $k ? 'selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?>
                                            </select></div>
                                        <div class="rem-field"><label for="rem-c-<?= $id ?>">Pays (code)</label>
                                            <input id="rem-c-<?= $id ?>" name="row[<?= $id ?>][country_code]" value="<?= $h($f['country_code']) ?>" maxlength="3" autocomplete="off"></div>
                                        <div class="rem-field"><label for="rem-n-<?= $id ?>">Numéro</label>
                                            <input id="rem-n-<?= $id ?>" name="row[<?= $id ?>][doc_number]" value="<?= $h($f['doc_number']) ?>" maxlength="20" autocomplete="off"></div>
                                        <div class="rem-field"><label for="rem-i-<?= $id ?>">Délivré le</label>
                                            <input id="rem-i-<?= $id ?>" type="date" name="row[<?= $id ?>][issued_on]" value="<?= $h($f['issued_on']) ?>"></div>
                                        <div class="rem-field"><label for="rem-e-<?= $id ?>">Expire le</label>
                                            <input id="rem-e-<?= $id ?>" type="date" name="row[<?= $id ?>][expires_on]" value="<?= $h($f['expires_on']) ?>"></div>
                                        <div class="rem-field"><label for="rem-a-<?= $id ?>">Authenticité constatée</label>
                                            <select id="rem-a-<?= $id ?>" name="row[<?= $id ?>][authenticity]">
                                                <?php foreach (C::AUTHENTICITY as $k => $label): ?><option value="<?= $h($k) ?>" <?= $f['authenticity'] === $k ? 'selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?>
                                            </select></div>
                                        <div class="rem-field"><label for="rem-s-<?= $id ?>">Date du constat</label>
                                            <input id="rem-s-<?= $id ?>" type="date" name="row[<?= $id ?>][seen_on]" value="<?= $h($f['seen_on']) ?>"></div>
                                        <div class="rem-field"><label for="rem-p-<?= $id ?>">Lieu du constat</label>
                                            <input id="rem-p-<?= $id ?>" name="row[<?= $id ?>][place]" value="<?= $h($f['place']) ?>" maxlength="120"></div>
                                        <div class="rem-field"><label for="rem-u-<?= $id ?>">Autorité émettrice</label>
                                            <input id="rem-u-<?= $id ?>" name="row[<?= $id ?>][authority]" value="<?= $h($f['authority']) ?>" maxlength="160"></div>
                                        <div class="rem-field rem-field--wide"><label for="rem-r-<?= $id ?>">Remarque</label>
                                            <input id="rem-r-<?= $id ?>" name="row[<?= $id ?>][remark]" value="" maxlength="1500" placeholder="Facultatif"></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="rem-none" data-rem-none hidden>Aucune remontée ne correspond à ces filtres.</p>
            <?php endif; ?>
        </div>

        <footer class="rem-foot">
            <p class="rem-count" data-rem-count aria-live="polite">0 papier sélectionné</p>
            <button type="button" class="rem-btn rem-btn--ghost" data-rem-close>Annuler</button>
            <button type="submit" class="rem-btn rem-btn--primary" data-rem-submit disabled>Consigner la sélection</button>
        </footer>
    </form>
</dialog>
