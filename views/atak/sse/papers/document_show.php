<?php
declare(strict_types=1);
use App\Support\SseIdentityCatalog as C;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $doc */
/** @var array<string,mixed> $identity */
/** @var array<string,mixed>|null $country */
/** @var array<string,mixed>|null $holder */
/** @var list<string> $mrz */
/** @var list<array<string,mixed>> $checks */
/** @var list<array<string,mixed>> $instances */
/** @var list<string> $transitions */
$canSeeTruth = !empty($canSeeTruth);
$canManage = !empty($canManage);
$id = (int) $doc['id'];
$secret = in_array($identity['kind'] ?? '', ['cover', 'forged'], true) || in_array($doc['st_origin'], ['cover', 'forged'], true);
$transitionLabels = [
    'validated' => 'Valider',
    'issued' => 'Émettre',
    'draft' => 'Repasser en brouillon',
    'revoked' => 'Révoquer',
];
$statusFields = [
    'st_integrity' => ['Intégrité', C::INTEGRITIES],
    'st_validity' => ['Validité', C::VALIDITIES],
    'st_report' => ['Signalement', C::REPORTS],
    'st_attribution' => ['Attribution', C::ATTRIBUTIONS],
    'chip_status' => ['Puce', C::CHIPS],
];
require __DIR__ . '/_subnav.php';
?>

<div class="page-heading">
    <div>
        <h1><?= $h($doc['ref']) ?> — <?= $h($doc['doc_number']) ?></h1>
        <p><?= $h($country['name'] ?? $doc['country_code']) ?> · titulaire <a class="link" href="<?= $h(url('atak/sse/papiers/identites/' . (int) $doc['identity_id'])) ?>"><?= $h($identity['display_name'] ?? '') ?></a></p>
    </div>
    <div class="page-reference">
        <strong><?= $h($doc['workflow_label']) ?></strong>
        <div class="muted"><?= $doc['workflow'] === 'issued' ? 'Chargé par le serveur de jeu' : 'Pas encore en jeu' ?></div>
        <div><a class="link" href="<?= $h(\App\Controllers\Web\SseFileController::replayUrl('QA', ['number' => $doc['doc_number'], 'country' => $doc['country_code'], 'doc_type' => $doc['doc_type']])) ?>">Passer aux fichiers</a></div>
    </div>
</div>

<?php
$aiLinks = [];
try {
    $aiLinks = (new \App\Repositories\SseAiProfileRepository())->forIdentity((int) $doc['tenant_id'], (int) $doc['identity_id']);
} catch (\Throwable) {
}
if ($aiLinks !== []): ?>
    <p class="idp-ai-links">Profils IA de ce titulaire : <?php foreach ($aiLinks as $i => $aiP): ?><?= $i > 0 ? ', ' : '' ?><a class="link" href="<?= $h(url('atak/sse/papiers/profils-ia/' . (int) $aiP['id'])) ?>"><?= $h($aiP['ref'] . ' · ' . $aiP['label']) ?></a><?php endforeach; ?></p>
<?php endif; ?>

<section class="panel">
    <div class="panel-header"><div class="panel-title"><span class="panel-index">01</span> Spécimen</div><div class="panel-meta">Document fictif — mention SIMULATION</div></div>
    <div class="panel-body">
        <div class="idp-card">
            <div class="idp-card__head">
                <span><?= $h($country['name'] ?? $doc['country_code']) ?> · <?= $h($doc['doc_type_label']) ?></span>
                <span>SIMULATION</span>
            </div>
            <?php if (!empty($identity['photo_path'])): ?><img class="idp-card__photo" src="<?= $h(url((string) $identity['photo_path'])) ?>" alt="Photo du titulaire"><?php endif; ?>
            <div class="idp-card__name"><?= $h(mb_strtoupper((string) ($identity['last_name'] ?? ''))) ?> <?= $h($identity['first_name'] ?? '') ?></div>
            <div class="idp-grid" style="margin-top:8px">
                <div><span class="muted">N°</span><br><strong translate="no"><?= $h($doc['doc_number']) ?></strong></div>
                <div><span class="muted">Né(e) le</span><br><strong><?= $h(\App\Support\SseIdentityCatalog::birthDateLabel($identity['birth_date'] ?? null)) ?></strong></div>
                <div><span class="muted">Lieu de naissance</span><br><strong><?= $h($identity['birth_place'] ?? '—') ?></strong></div>
                <div><span class="muted">Sexe</span><br><strong><?= $h($identity['sex'] ?? 'X') ?></strong></div>
                <div><span class="muted">Nationalité</span><br><strong><?= $h($identity['nationality'] ?? '—') ?></strong></div>
                <div><span class="muted">Délivré le</span><br><strong><?= $h($doc['issued_on'] ?? '—') ?></strong></div>
                <div><span class="muted">Expire le</span><br><strong><?= $h($doc['expires_on'] ?? '—') ?></strong><?php if ($doc['expired']): ?> <span class="badge badge--amber">Expiré</span><?php endif; ?></div>
                <div><span class="muted">Autorité</span><br><strong><?= $h($doc['authority'] ?? '—') ?></strong></div>
            </div>
            <div class="idp-mrz" translate="no" aria-label="Bande de lecture automatique"><?= $h(implode("\n", $mrz)) ?></div>
        </div>
    </div>
</section>

<?php if (($identity['id'] ?? 0) > 0 && ($canSeeTruth || !$secret)): ?>
    <?php $imagesDocId = $id; $personPhotos = $personPhotos ?? []; require __DIR__ . '/_holder_images.php'; ?>
<?php endif; ?>

<section class="panel" style="margin-top:10px">
    <div class="panel-header"><div class="panel-title"><span class="panel-index">02</span> Statuts du papier</div></div>
    <?php if ($canManage): ?>
        <form class="panel-body" method="post" action="<?= $h(url('atak/sse/papiers/documents/' . $id . '/statuts')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <div class="idp-statuses">
                <?php if ($canSeeTruth): ?>
                    <div>
                        <label for="st_origin">Origine</label>
                        <select id="st_origin" name="st_origin">
                            <?php foreach (C::ORIGINS as $k => $label): ?>
                                <option value="<?= $h($k) ?>" <?= $doc['st_origin'] === $k ? 'selected' : '' ?>><?= $h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <?php foreach ($statusFields as $name => [$label, $options]): ?>
                    <div>
                        <label for="<?= $h($name) ?>"><?= $h($label) ?></label>
                        <select id="<?= $h($name) ?>" name="<?= $h($name) ?>">
                            <?php foreach ($options as $k => $optLabel): ?>
                                <option value="<?= $h($k) ?>" <?= (string) $doc[$name] === $k ? 'selected' : '' ?>><?= $h($optLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
                <?php if ($canSeeTruth): ?>
                    <div>
                        <label for="forgery_kind">Falsification</label>
                        <select id="forgery_kind" name="forgery_kind">
                            <option value="">Aucune</option>
                            <?php foreach (C::FORGERY_KINDS as $k => $label): ?>
                                <option value="<?= $h($k) ?>" <?= ($doc['forgery_kind'] ?? '') === $k ? 'selected' : '' ?>><?= $h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="forgery_quality">Qualité du faux (0 à 100)</label>
                        <input id="forgery_quality" name="forgery_quality" type="number" min="0" max="100" value="<?= (int) ($doc['forgery_quality'] ?? 50) ?>">
                    </div>
                <?php endif; ?>
                <div>
                    <label for="holder_person_id">Titulaire (numéro de fiche IDN)</label>
                    <input id="holder_person_id" name="holder_person_id" type="number" min="0" value="<?= ($doc['holder_person_id'] ?? 0) > 0 && ($canSeeTruth || !$secret) ? (int) $doc['holder_person_id'] : '' ?>" placeholder="Aucune fiche">
                </div>
            </div>
            <div class="idp-actions" style="margin-top:10px"><button class="btn" type="submit">Enregistrer les statuts</button></div>
        </form>
    <?php else: ?>
        <div class="panel-body idp-grid">
            <div><span class="muted">Origine</span><br><strong><?= $canSeeTruth ? $h(C::label(C::ORIGINS, $doc['st_origin'])) : '<span class="idp-hidden">Réservée</span>' ?></strong></div>
            <?php foreach ($statusFields as $name => [$label, $options]): ?>
                <div><span class="muted"><?= $h($label) ?></span><br><strong><?= $h(C::label($options, (string) $doc[$name])) ?></strong></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($holder !== null && ($canSeeTruth || !$secret)): ?>
        <div class="panel-body"><span class="muted">Fiche du titulaire :</span> <a class="link" href="<?= $h(url('atak/sse/identites/' . (int) $holder['id'])) ?>"><?= $h($holder['display_name'] ?? ('IDN-' . (int) $holder['id'])) ?></a></div>
    <?php endif; ?>
</section>

<section class="panel" style="margin-top:10px">
    <div class="panel-header"><div class="panel-title"><span class="panel-index">03</span> Cycle de vie</div><div class="panel-meta">Brouillon → Validé → Émis → Révoqué</div></div>
    <div class="panel-body">
        <div class="idp-grid">
            <div><span class="muted">Créé le</span><br><strong><?= $h($doc['created_at']) ?></strong></div>
            <div><span class="muted">Validé le</span><br><strong><?= $h($doc['validated_at'] ?? '—') ?></strong></div>
            <div><span class="muted">Émis le</span><br><strong><?= $h($doc['issued_at'] ?? '—') ?></strong></div>
            <div><span class="muted">Révoqué le</span><br><strong><?= $h($doc['revoked_at'] ?? '—') ?></strong><?php if (!empty($doc['revoked_reason'])): ?><br><span class="muted"><?= $h($doc['revoked_reason']) ?></span><?php endif; ?></div>
        </div>
        <?php if ($canManage && $transitions !== []): ?>
            <div class="idp-actions" style="margin-top:12px">
                <?php foreach ($transitions as $to): ?>
                    <form method="post" action="<?= $h(url('atak/sse/papiers/documents/' . $id . '/cycle')) ?>">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="to" value="<?= $h($to) ?>">
                        <?php if ($to === 'revoked'): ?>
                            <input type="text" name="reason" maxlength="255" required placeholder="Motif de la révocation" aria-label="Motif de la révocation">
                        <?php endif; ?>
                        <button class="btn<?= $to === 'revoked' ? ' btn--danger' : ($to === 'draft' ? ' btn--ghost' : '') ?>" type="submit"><?= $h($transitionLabels[$to] ?? $to) ?></button>
                    </form>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="panel" style="margin-top:10px">
    <div class="panel-header"><div class="panel-title"><span class="panel-index">04</span> Exemplaires en jeu</div><div class="panel-meta"><?= count($instances) ?> exemplaire(s)</div></div>
    <?php if ($instances === []): ?>
        <div class="panel-body"><p class="muted">Aucun exemplaire signalé par le jeu pour l’instant. Ils apparaîtront ici quand le serveur de jeu les aura distribués.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Exemplaire</th><th>État</th><th>Vu le</th></tr></thead>
                <tbody>
                <?php foreach ($instances as $in): ?>
                    <tr><td>#<?= (int) $in['id'] ?></td><td><?= $h($in['state'] === 'in_hand' ? 'En possession' : ($in['state'] === 'seized' ? 'Saisi' : 'Autre')) ?></td><td><?= $h($in['last_seen_at'] ?? '—') ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel" style="margin-top:10px">
    <div class="panel-header"><div class="panel-title"><span class="panel-index">05</span> Contrôles de ce papier</div><div class="panel-meta"><?= count($checks) ?> contrôle(s)</div></div>
    <?php if ($checks === []): ?>
        <div class="panel-body"><p class="muted">Aucun contrôle reçu du terrain pour ce papier.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Contrôle</th><th>Reçu le</th><th>Contrôleur</th><th>Niveaux</th><th>Verdict</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($checks as $c): ?>
                    <tr>
                        <td><?= $h($c['ref']) ?></td>
                        <td><?= $h($c['created_at']) ?></td>
                        <td><?= $h($c['controller_callsign'] ?? '—') ?></td>
                        <td><?= $h(implode(' ', $c['levels'])) ?></td>
                        <td><span class="badge idp-verdict--<?= $h($c['verdict'] !== '' ? $c['verdict'] : 'none') ?>"><?= $h($c['verdict_label']) ?></span></td>
                        <td><a class="btn-open" href="<?= $h(url('atak/sse/papiers/controles/' . (int) $c['id'])) ?>">Ouvrir</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
