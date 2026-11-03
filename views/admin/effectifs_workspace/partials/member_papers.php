<?php
declare(strict_types=1);

use App\Services\Sse\MemberIdentityPapersService as P;
use App\Support\SseIdentityCatalog as C;

/**
 * Fiche membre › « Identité & papiers » (COMSPEC IDENTITY).
 *
 * @var array<string, mixed> $memberPapers MemberIdentityPapersController::sectionFor()
 * @var int $id membre
 * @var string $csrfToken
 */
$pp = $memberPapers;
$ph = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rights = $pp['rights'];
$manage = (bool) $rights['manage'];
$truth = (bool) $rights['truth'];
$base = (string) $pp['base'];
$countries = $pp['countries'];
$maps = $pp['maps'];
$countryNames = array_column($countries, 'name', 'code');
$countryLabel = static fn (?string $code): string => isset($countryNames[$code ?? '']) ? $countryNames[$code] . ' (' . $code . ')' : ($code ?: '—');
$primary = $pp['primary'];
$proposal = $pp['proposal'];
$docs = $pp['documents'];
$mapLabel = static function (?int $ctx) use ($maps): string {
    foreach ($maps as $m) {
        if ($m['id'] === $ctx) {
            return $m['label'];
        }
    }

    return $ctx !== null ? 'Carte ' . $ctx : 'Toutes les cartes';
};
$docById = [];
foreach ($docs as $d) {
    $docById[(int) $d['id']] = $d;
}
$wfChip = ['draft' => '', 'validated' => '', 'issued' => 'is-ok', 'revoked' => 'is-warn'];
$scopeLabels = ['default' => 'Par défaut', 'map' => 'Pour une carte', 'operation' => 'Pour une opération'];
$identityFields = static function (array $v, bool $withKind) use ($ph, $countries, $truth): void {
    ?>
    <div class="eff-dialog__grid">
        <?php if ($withKind): ?>
            <label>Nature
                <select name="kind">
                    <option value="alias">Alias</option>
                    <?php if ($truth): ?><option value="cover">Identité de couverture</option><?php endif; ?>
                </select>
            </label>
        <?php endif; ?>
        <label>Nom<input type="text" name="last_name" maxlength="120" value="<?= $ph($v['last_name'] ?? '') ?>"<?= $withKind ? '' : ' required' ?>></label>
        <label>Prénom<input type="text" name="first_name" maxlength="120" value="<?= $ph($v['first_name'] ?? '') ?>"<?= $withKind ? '' : ' required' ?>></label>
        <label>Sexe
            <select name="sex">
                <?php foreach (C::SEXES as $k => $label): ?>
                    <option value="<?= $ph($k) ?>" <?= ($v['sex'] ?? 'X') === $k ? 'selected' : '' ?>><?= $ph($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Date de naissance<input type="date" name="birth_date" value="<?= $ph($v['birth_date'] ?? '') ?>"></label>
        <label>Lieu de naissance<input type="text" name="birth_place" maxlength="120" value="<?= $ph($v['birth_place'] ?? '') ?>"></label>
        <label>Nationalité (pays de jeu)
            <select name="nationality">
                <option value="">Non précisée</option>
                <?php foreach ($countries as $c): ?>
                    <option value="<?= $ph($c['code']) ?>" <?= ($v['nationality'] ?? '') === $c['code'] ? 'selected' : '' ?>><?= $ph($c['name']) ?> (<?= $ph($c['code']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Profession<input type="text" name="profession" maxlength="120" value="<?= $ph($v['profession'] ?? '') ?>"></label>
    </div>
    <?php
};
?>
<section class="eff-fm-sec" id="papiers" aria-labelledby="papiers-titre">
    <header class="eff-fm-sec__head">
        <h2 id="papiers-titre">Identité &amp; papiers</h2>
        <p>Papiers d’identité fictifs du personnage (mention SIMULATION), kit porté en mission et contrôles subis en jeu.</p>
    </header>
    <div class="eff-fm-cards">

    <article class="eff-fm-card" id="papiers-identite">
        <div class="eff-fm-card__head">
            <h3>Identité principale</h3>
            <?php if ($primary !== null): ?><span class="eff-fm-chip"><?= $ph($primary['ref']) ?></span><?php endif; ?>
        </div>
        <?php if ($primary !== null): ?>
            <dl class="eff-fm-fields">
                <div class="eff-fm-f"><dt>Nom</dt><dd><?= $ph(P::displayName($primary)) ?></dd></div>
                <div class="eff-fm-f"><dt>Né(e) le</dt><dd><?= $ph(\App\Support\SseIdentityCatalog::birthDateLabel($primary['birth_date'] ?? null)) ?></dd></div>
                <div class="eff-fm-f"><dt>Lieu de naissance</dt><dd><?= $ph($primary['birth_place'] ?? '—') ?></dd></div>
                <div class="eff-fm-f"><dt>Nationalité</dt><dd><?= $ph($countryLabel($primary['nationality'] ?? null)) ?></dd></div>
            </dl>
        <?php else: ?>
            <p class="eff-fm-lead">Pas encore d’identité de jeu. Elle reprend la fiche du personnage (nom, prénom, naissance, nationalité).</p>
        <?php endif; ?>
        <?php if ($manage): ?>
            <details class="eff-card__edit"<?= $primary === null ? ' open' : '' ?>>
                <summary><?= $primary === null ? 'Créer l’identité principale' : 'Modifier l’identité principale' ?></summary>
                <form method="post" action="<?= $ph($base . '/identite-principale') ?>" class="eff-card__form">
                    <input type="hidden" name="_csrf_token" value="<?= $ph($csrfToken) ?>">
                    <?php $identityFields($primary ?? $proposal, false); ?>
                    <?php if ($primary === null && $proposal['nationality'] === '' && $proposal['nationality_text'] !== ''): ?>
                        <p class="eff-card__hint">Nationalité de la fiche : « <?= $ph($proposal['nationality_text']) ?> ». Choisissez le pays de jeu correspondant.</p>
                    <?php endif; ?>
                    <button type="submit" class="eff-btn eff-btn--primary">Enregistrer</button>
                </form>
                <?php if ($primary !== null): ?>
                    <form method="post" action="<?= $ph($base . '/identite-principale') ?>" class="eff-card__form">
                        <input type="hidden" name="_csrf_token" value="<?= $ph($csrfToken) ?>">
                        <?php foreach (['last_name', 'first_name', 'sex', 'birth_date', 'birth_place'] as $k): ?>
                            <input type="hidden" name="<?= $ph($k) ?>" value="<?= $ph($proposal[$k] !== '' ? $proposal[$k] : ($primary[$k] ?? '')) ?>">
                        <?php endforeach; ?>
                        <input type="hidden" name="nationality" value="<?= $ph($proposal['nationality'] !== '' ? $proposal['nationality'] : ($primary['nationality'] ?? '')) ?>">
                        <input type="hidden" name="profession" value="<?= $ph($primary['profession'] ?? '') ?>">
                        <button type="submit" class="eff-fm-btn is-sm">Reprendre la fiche du personnage</button>
                    </form>
                <?php endif; ?>
            </details>
        <?php endif; ?>
    </article>

    <article class="eff-fm-card" id="papiers-alias">
        <div class="eff-fm-card__head">
            <h3>Identités d’emprunt</h3>
            <span class="eff-fm-count"><?= count($pp['aliases']) ?></span>
        </div>
        <?php if ($pp['aliases'] === []): ?>
            <p class="eff-fm-lead"><?= $truth ? 'Aucun alias ni identité de couverture.' : 'Aucun alias.' ?></p>
        <?php else: ?>
            <ul class="eff-fm-list">
                <?php foreach ($pp['aliases'] as $a): ?>
                    <li>
                        <span class="eff-fm-list__dt"><?= $ph($a['ref']) ?></span>
                        <div><b><?= $ph(P::displayName($a)) ?></b>
                            <?php if ($truth): ?> <span class="eff-fm-chip<?= in_array($a['kind'], P::SECRET, true) ? ' is-warn' : '' ?>"><?= $ph($a['kind_label']) ?></span><?php endif; ?>
                            <p><?= $ph((\App\Support\SseIdentityCatalog::birthDateLabel($a['birth_date'] ?? null)) . ' · ' . $countryLabel($a['nationality'] ?? null)) ?> · <?= (int) $a['documents_count'] ?> papier(s)</p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if (!$truth): ?>
            <p class="eff-card__hint">Les identités de couverture ne sont visibles qu’avec l’habilitation Confidentiel.</p>
        <?php endif; ?>
        <?php if ($manage): ?>
            <details class="eff-card__edit">
                <summary>Nouvelle identité d’emprunt</summary>
                <form method="post" action="<?= $ph($base . '/identites') ?>" class="eff-card__form">
                    <input type="hidden" name="_csrf_token" value="<?= $ph($csrfToken) ?>">
                    <?php $identityFields([], true); ?>
                    <label class="eff-card__check"><input type="checkbox" name="generate" value="1" checked> Tirer au sort le nom, le prénom et la naissance s’ils sont laissés vides</label>
                    <button type="submit" class="eff-btn eff-btn--primary">Créer</button>
                </form>
            </details>
        <?php endif; ?>
    </article>

    <article class="eff-fm-card eff-fm-card--wide" id="papiers-documents">
        <div class="eff-fm-card__head">
            <h3>Papiers</h3>
            <span class="eff-fm-count"><?= count($docs) ?></span>
        </div>
        <?php if ($manage && $pp['identities'] !== []): ?>
            <form method="get" action="<?= $ph($base . '/documents/nouveau') ?>" class="idm-new">
                <label>Identité
                    <select name="identity_id">
                        <?php foreach ($pp['identities'] as $i): ?>
                            <option value="<?= (int) $i['id'] ?>"><?= $ph(P::displayName($i)) ?><?= !empty($i['is_primary']) ? ' (principale)' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Papier
                    <select name="doc_type">
                        <?php foreach (C::DOC_TYPES as $k => $label): ?>
                            <option value="<?= $ph($k) ?>"><?= $ph($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Pays
                    <select name="country" data-country-picker>
                        <?php foreach ($countries as $c): ?>
                            <option value="<?= $ph($c['code']) ?>" <?= ($primary['nationality'] ?? '') === $c['code'] ? 'selected' : '' ?>><?= $ph($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="eff-btn eff-btn--primary">Générer un papier</button>
            </form>
        <?php elseif ($manage): ?>
            <p class="eff-card__hint">Créez d’abord l’identité principale : les papiers en reprennent les données.</p>
        <?php endif; ?>
        <?php if ($docs === []): ?>
            <p class="eff-fm-lead">Aucun papier.</p>
        <?php else: ?>
            <div class="idm-docs">
                <?php foreach ($docs as $d): ?>
                    <?php $did = (int) $d['id']; $act = $base . '/documents/' . $did . '/action'; ?>
                    <div class="idm-doc">
                        <div class="idm-doc__head">
                            <b><?= $ph($d['doc_type_label']) ?> · <?= $ph($d['country_name']) ?></b>
                            <span class="idm-doc__num" translate="no"><?= $ph($d['doc_number']) ?></span>
                            <span class="eff-fm-chip <?= $wfChip[$d['workflow']] ?? '' ?>"><?= $ph($d['workflow_label']) ?></span>
                            <?php if ($d['st_validity'] !== 'valid' || $d['expired']): ?><span class="eff-fm-chip is-warn"><?= $ph($d['expired'] && $d['st_validity'] === 'valid' ? 'Expiré' : C::label(C::VALIDITIES, $d['st_validity'])) ?></span><?php endif; ?>
                            <?php if ($d['st_report'] !== 'none'): ?><span class="eff-fm-chip is-warn"><?= $ph(C::label(C::REPORTS, $d['st_report'])) ?></span><?php endif; ?>
                            <?php if ($truth && $d['secret']): ?><span class="eff-fm-chip is-warn"><?= $ph(C::label(C::ORIGINS, $d['st_origin'])) ?></span><?php endif; ?>
                        </div>
                        <p class="idm-doc__meta"><?= $ph($d['holder_name']) ?> · valable du <?= $ph($d['issued_on'] ?? '—') ?> au <?= $ph($d['expires_on'] ?? '—') ?> · <?= $ph($mapLabel($d['context_id'])) ?><?= $d['op_key'] !== null ? ' · opération ' . $ph($d['op_key']) : '' ?></p>
                        <details class="idm-doc__preview">
                            <summary>Aperçu du spécimen</summary>
                            <div class="idp-card">
                                <div class="idp-card__head"><span><?= $ph($d['country_name']) ?> · <?= $ph($d['doc_type_label']) ?></span><span>SIMULATION</span></div>
                                <div class="idp-card__name"><?= $ph(mb_strtoupper((string) $d['identity']['last_name'])) ?> <?= $ph($d['identity']['first_name']) ?></div>
                                <div class="idp-grid" style="margin-top:8px">
                                    <div><span class="muted">N°</span><br><strong translate="no"><?= $ph($d['doc_number']) ?></strong></div>
                                    <div><span class="muted">Né(e) le</span><br><strong><?= $ph(C::birthDateLabel($d['identity']['birth_date'] ?? null)) ?></strong></div>
                                    <div><span class="muted">Sexe</span><br><strong><?= $ph($d['identity']['sex']) ?></strong></div>
                                    <div><span class="muted">Autorité</span><br><strong><?= $ph($d['authority'] ?? '—') ?></strong></div>
                                </div>
                                <div class="idp-mrz" translate="no" aria-label="Bande de lecture automatique"><?= $ph(implode("\n", $d['mrz'])) ?></div>
                            </div>
                        </details>
                        <?php if ($manage): ?>
                            <div class="idm-doc__actions">
                                <?php if ($d['workflow'] !== 'revoked'): ?>
                                    <a class="eff-fm-btn is-sm" href="<?= $ph($base . '/documents/' . $did . '/modifier') ?>">Modifier</a>
                                <?php endif; ?>
                                <?php
                                $buttons = [];
                                if (in_array($d['workflow'], ['draft', 'validated'], true)) {
                                    $buttons['issue'] = 'Émettre';
                                }
                                if ($d['workflow'] === 'draft') {
                                    $buttons['validate'] = 'Valider';
                                }
                                if ($d['workflow'] === 'validated') {
                                    $buttons['draft'] = 'Repasser en brouillon';
                                }
                                if (in_array($d['workflow'], ['validated', 'issued'], true) && $d['st_report'] === 'none') {
                                    $buttons['stolen'] = 'Déclarer volé';
                                    $buttons['lost'] = 'Déclarer perdu';
                                }
                                if ($d['st_report'] !== 'none') {
                                    $buttons['clear_report'] = 'Lever le signalement';
                                }
                                $buttons['duplicate'] = 'Dupliquer';
                                if ($d['workflow'] === 'draft') {
                                    $buttons['delete'] = 'Supprimer';
                                }
                                ?>
                                <?php foreach ($buttons as $do => $label): ?>
                                    <form method="post" action="<?= $ph($act) ?>">
                                        <input type="hidden" name="_csrf_token" value="<?= $ph($csrfToken) ?>">
                                        <input type="hidden" name="do" value="<?= $ph($do) ?>">
                                        <button type="submit" class="eff-fm-btn is-sm<?= $do === 'delete' ? ' is-danger' : '' ?>"><?= $ph($label) ?></button>
                                    </form>
                                <?php endforeach; ?>
                                <?php if ($d['workflow'] === 'issued'): ?>
                                    <details class="idm-doc__revoke">
                                        <summary class="eff-fm-btn is-sm">Révoquer</summary>
                                        <form method="post" action="<?= $ph($act) ?>" class="eff-card__form">
                                            <input type="hidden" name="_csrf_token" value="<?= $ph($csrfToken) ?>">
                                            <input type="hidden" name="do" value="revoke">
                                            <label>Motif<input type="text" name="reason" maxlength="255" required placeholder="Ex. Identité grillée"></label>
                                            <button type="submit" class="eff-btn eff-btn--primary">Révoquer le papier</button>
                                        </form>
                                    </details>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>

    <article class="eff-fm-card eff-fm-card--wide" id="papiers-kit">
        <div class="eff-fm-card__head">
            <h3>Kit de mission</h3>
            <?php if (!$pp['steam_linked']): ?><span class="eff-fm-chip is-warn">Steam non lié</span><?php endif; ?>
        </div>
        <p class="eff-fm-lead">Les papiers émis que le joueur reçoit en arrivant sur le serveur. Le kit d’une opération l’emporte sur celui de la carte, qui l’emporte sur le kit par défaut.</p>
        <?php if (!$pp['steam_linked']): ?>
            <p class="eff-card__hint">Le compte de ce membre n’est pas lié à Steam : le serveur de jeu ne peut pas lui remettre son kit.</p>
        <?php endif; ?>
        <?php if ($pp['kits'] === []): ?>
            <p class="eff-fm-lead">Aucun kit : le joueur arrive sans papiers.</p>
        <?php else: ?>
            <ul class="eff-fm-list">
                <?php foreach ($pp['kits'] as $kit): ?>
                    <li>
                        <span class="eff-fm-list__dt"><?= $ph($scopeLabels[$kit['scope']] ?? '') ?></span>
                        <div>
                            <b><?= $ph($kit['op_key'] !== null ? 'Opération ' . $kit['op_key'] : $mapLabel($kit['context_id'])) ?><?= $kit['op_key'] !== null && $kit['context_id'] !== null ? ' · ' . $ph($mapLabel($kit['context_id'])) : '' ?></b>
                            <p><?php
                                $names = [];
                                foreach ($kit['document_ids'] as $kd) {
                                    if (isset($docById[$kd])) {
                                        $names[] = $docById[$kd]['doc_type_label'] . ' ' . $docById[$kd]['doc_number'] . ($docById[$kd]['workflow'] !== 'issued' ? ' (pas en jeu)' : '');
                                    }
                                }
                                echo $ph($names !== [] ? implode(' · ', $names) : '—');
                            ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($manage): ?>
            <?php $issuedDocs = array_values(array_filter($docs, static fn (array $d): bool => $d['workflow'] === 'issued')); ?>
            <details class="eff-card__edit">
                <summary>Composer un kit</summary>
                <form method="post" action="<?= $ph($base . '/kit') ?>" class="eff-card__form">
                    <input type="hidden" name="_csrf_token" value="<?= $ph($csrfToken) ?>">
                    <div class="eff-dialog__grid">
                        <label>Carte
                            <select name="context_id">
                                <option value="0">Toutes les cartes (par défaut)</option>
                                <?php foreach ($maps as $m): ?>
                                    <option value="<?= (int) $m['id'] ?>"><?= $ph($m['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Opération<input type="text" name="op_key" maxlength="64" placeholder="Toutes les opérations"></label>
                    </div>
                    <?php if ($issuedDocs === []): ?>
                        <p class="eff-card__hint">Aucun papier émis : émettez d’abord un papier pour l’ajouter au kit.</p>
                    <?php endif; ?>
                    <?php foreach ($issuedDocs as $d): ?>
                        <label class="eff-card__check"><input type="checkbox" name="document_ids[]" value="<?= (int) $d['id'] ?>"> <?= $ph($d['doc_type_label'] . ' · ' . $d['country_name'] . ' · ' . $d['doc_number'] . ' · ' . $d['holder_name']) ?></label>
                    <?php endforeach; ?>
                    <p class="eff-card__hint">Enregistrer sans rien cocher retire le kit de cette carte ou de cette opération.</p>
                    <button type="submit" class="eff-btn eff-btn--primary">Enregistrer le kit</button>
                </form>
            </details>
        <?php endif; ?>
    </article>

    <article class="eff-fm-card eff-fm-card--wide" id="papiers-controles">
        <div class="eff-fm-card__head">
            <h3>Contrôles subis</h3>
            <span class="eff-fm-count"><?= count($pp['checks']) ?></span>
        </div>
        <?php if ($pp['checks'] === []): ?>
            <p class="eff-fm-lead">Aucun contrôle d’identité remonté du terrain.</p>
        <?php else: ?>
            <ul class="eff-fm-list">
                <?php foreach ($pp['checks'] as $c): ?>
                    <li>
                        <span class="eff-fm-list__dt"><?= $ph(substr((string) $c['created_at'], 0, 16)) ?></span>
                        <div>
                            <b><?= $ph($c['ref']) ?> · <?= $ph($c['verdict_label']) ?></b>
                            <p><?= $ph(implode(' · ', array_filter([
                                $c['doc_number'] !== null ? 'papier ' . $c['doc_number'] : '',
                                $c['levels'] !== [] ? implode(', ', $c['levels']) : '',
                                $c['controller_callsign'] !== null ? 'par ' . $c['controller_callsign'] : '',
                                $c['grid'] !== null ? 'carroyage ' . $c['grid'] : '',
                            ]))) ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>
    </div>
</section>
