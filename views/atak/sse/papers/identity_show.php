<?php
declare(strict_types=1);
use App\Support\SseIdentityCatalog as C;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $identity */
/** @var array<string,mixed>|null $person */
/** @var list<array<string,mixed>> $documents */
/** @var list<array<string,mixed>> $countries */
$canSeeTruth = !empty($canSeeTruth);
$secret = in_array($identity['kind'], ['cover', 'forged'], true);
$showPerson = is_array($person ?? null) && ($canSeeTruth || !$secret);
require __DIR__ . '/_subnav.php';
?>
<div class="breadcrumb">Athena / SSE / <a class="link" href="<?= $h(url('atak/sse/papiers/identites')) ?>">Identités</a> / <strong><?= $h($identity['ref']) ?></strong></div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline"><?= $h($identity['ref']) ?> // <?= $canSeeTruth ? $h($identity['kind_label']) : 'Nature réservée' ?></div>
        <h1><?= $h($identity['display_name']) ?></h1>
        <p><?= $h(C::label(C::SEXES, $identity['sex'])) ?> · <?= $h(\App\Support\SseIdentityCatalog::birthDateLabel($identity['birth_date'] ?? null)) ?> · <?= $h($identity['birth_place'] ?? '—') ?> · <?= $h($identity['nationality'] ?? '—') ?></p>
    </div>
    <div class="page-reference">
        <strong><?= count($documents) ?> papier(s)</strong>
        <?php if ($showPerson): ?><div><a class="link" href="<?= $h(url('atak/sse/identites/' . (int) $person['id'])) ?>">Fiche personne</a></div><?php endif; ?>
    </div>
</div>

<?php
$aiLinks = [];
try {
    $aiLinks = (new \App\Repositories\SseAiProfileRepository())->forIdentity((int) $identity['tenant_id'], (int) $identity['id']);
} catch (\Throwable) {
}
if ($aiLinks !== []): ?>
    <p class="idp-ai-links">Profils IA reliés : <?php foreach ($aiLinks as $i => $aiP): ?><?= $i > 0 ? ', ' : '' ?><a class="link" href="<?= $h(url('atak/sse/papiers/profils-ia/' . (int) $aiP['id'])) ?>"><?= $h($aiP['ref'] . ' · ' . $aiP['label']) ?></a><?php endforeach; ?></p>
<?php endif; ?>

<section class="panel">
    <div class="panel-header"><div class="panel-title"><span class="panel-index">01</span> Identité</div></div>
    <div class="panel-body idp-grid">
        <div><span class="muted">Nom</span><br><strong><?= $h(mb_strtoupper((string) $identity['last_name'])) ?></strong></div>
        <div><span class="muted">Prénom</span><br><strong><?= $h($identity['first_name']) ?></strong></div>
        <div><span class="muted">Profession</span><br><strong><?= $h($identity['profession'] ?? '—') ?></strong></div>
        <div><span class="muted">Nature</span><br><strong><?= $canSeeTruth ? $h($identity['kind_label']) : '<span class="idp-hidden">Réservée à l’habilitation Confidentiel</span>' ?></strong></div>
        <div><span class="muted">Fiche personne</span><br><strong><?= $showPerson ? $h($person['display_name'] ?? ('IDN-' . (int) $person['id'])) : '—' ?></strong></div>
        <div><span class="muted">Personnage en jeu</span><br><strong><?= $h($identity['arma_key'] ?? '—') ?></strong></div>
        <div><span class="muted">Carte</span><br><strong><?= $identity['context_id'] !== null ? '#' . (int) $identity['context_id'] : 'Toutes les cartes' ?></strong></div>
        <div><span class="muted">Opération</span><br><strong><?= $h($identity['op_key'] ?? 'Toutes les opérations') ?></strong></div>
        <?php if ($identity['seed'] !== null): ?>
            <div><span class="muted">Graine du tirage</span><br><strong><?= (int) $identity['seed'] ?></strong></div>
        <?php endif; ?>
    </div>
    <?php if (!empty($identity['notes'])): ?>
        <div class="panel-body"><p><?= nl2br($h($identity['notes'])) ?></p></div>
    <?php endif; ?>
</section>

<?php if ($canSeeTruth || !$secret): ?>
    <?php $imagesDocId = 0; $personPhotos = $showPerson ? ($personPhotos ?? []) : []; require __DIR__ . '/_holder_images.php'; ?>
<?php endif; ?>

<section class="panel" style="margin-top:10px">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">02</span> Papiers</div>
        <div class="panel-meta"><?= count($documents) ?> papier(s)</div>
    </div>
    <?php if ($documents === []): ?>
        <div class="empty-state"><div class="empty-state-inner"><strong>Aucun papier</strong><p>Ajoutez une carte, un passeport ou un permis : le numéro et la bande de lecture sont calculés pour vous.</p></div></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Référence</th><th>Type</th><th>Pays</th><th>Numéro</th><th>Expire le</th><th>État</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($documents as $d): ?>
                    <tr>
                        <td><?= $h($d['ref']) ?></td>
                        <td><?= $h($d['doc_type_label']) ?></td>
                        <td><?= $h($d['country_code']) ?></td>
                        <td><code><?= $h($d['doc_number']) ?></code></td>
                        <td><?= $h($d['expires_on'] ?? '—') ?></td>
                        <td><?= $h($d['workflow_label']) ?></td>
                        <td><a class="btn-open" href="<?= $h(url('atak/sse/papiers/documents/' . (int) $d['id'])) ?>">Ouvrir</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?php if (!empty($canManage)): ?>
        <form class="panel-body idp-actions" method="get" action="<?= $h(url('atak/sse/papiers/documents/nouveau')) ?>">
            <input type="hidden" name="identity_id" value="<?= (int) $identity['id'] ?>">
            <label for="new_doc_type" class="muted">Nouveau papier</label>
            <select id="new_doc_type" name="doc_type">
                <?php foreach (C::DOC_TYPES as $k => $label): ?>
                    <option value="<?= $h($k) ?>"><?= $h($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="country" aria-label="Pays émetteur">
                <?php foreach ($countries as $c): ?>
                    <option value="<?= $h($c['code']) ?>" <?= ($identity['nationality'] ?? '') === $c['code'] ? 'selected' : '' ?>><?= $h($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn" type="submit">Préparer le papier</button>
        </form>
    <?php endif; ?>
</section>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
