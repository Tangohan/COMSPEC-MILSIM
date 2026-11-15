<?php
declare(strict_types=1);
/**
 * Tiroir d'aperçu d'une personne (fragment HTML chargé par le script de la page Personnes).
 *
 * @var array<string, mixed> $d détail (SsePersonWorkService::detail)
 * @var \App\Services\Sse\SsePersonViewer $viewer
 */

use App\Support\SsePersonUi as Ui;
use App\Support\SsePersonWork as W;

$h ??= static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$r = $d['row'];
$p = $d['person'];
$id = (int) $r['id'];
$now = time();
$ready = (bool) $d['ready'];
$canAct = $ready && $viewer->canWrite && $r['can_modify'];
$deny = !$ready ? 'Disponible après la mise à jour de la base de données (étape « SSE Personnes »).' : 'Droit manquant ou fiche classifiée au-dessus de votre habilitation.';
$med = is_array($p['medical_context'] ?? null) ? $p['medical_context'] : [];
$lesions = is_array($med['lesions'] ?? null) ? $med['lesions'] : [];
$sig = $r['sig'];
$wanted = $r['wanted'];
$bioNames = ['empreintes' => 'Empreintes', 'iris' => 'Iris', 'adn' => 'ADN'];
$bioState = ['present' => 'Présent', 'partial' => 'Partiel', 'absent' => 'Absent'];
$byKind = [];
foreach ($d['samples'] as $s) {
    $byKind[\App\Support\SsePersonDedupe::normalizeKind((string) $s['kind'])] = $s;
}
$auditLabels = [
    'person.classify' => 'Classification modifiée',
    'person.lock' => 'Fiche verrouillée',
    'person.unlock' => 'Fiche déverrouillée',
    'person.close' => 'Dossier clos',
    'person.reopen' => 'Dossier rouvert',
];
$fact = static function (string $label, ?string $value) use ($h): string {
    return $value === null || trim($value) === '' ? '' : '<div><dt>' . $h($label) . '</dt><dd>' . $h($value) . '</dd></div>';
};
?>
<div class="pe-dr" data-pe-dr data-id="<?= $id ?>" data-name="<?= $h($r['name']) ?>" data-wanted="<?= $wanted !== null ? '1' : '0' ?>" data-closed="<?= $r['closed'] ? '1' : '0' ?>" data-locked="<?= !empty($r['lock']['manual']) ? '1' : '0' ?>" data-class="<?= $h((string) $r['classification']) ?>">
    <header class="pe-dr__head">
        <?php if ($r['photo'] !== null): ?>
            <span class="pe-avatar pe-avatar--lg"><img src="<?= $h($r['photo']) ?>" alt="Photographie de <?= $h($r['name']) ?>"></span>
        <?php else: ?>
            <span class="pe-avatar pe-avatar--lg pe-avatar--initials" aria-hidden="true"><?= $h($r['initials']) ?></span>
        <?php endif; ?>
        <div class="pe-dr__id">
            <h2 id="pe-dr-title"><?= $h($r['name']) ?></h2>
            <p><?= $h(trim(($r['alias'] !== '' ? '« ' . $r['alias'] . ' » · ' : '') . $r['idn'])) ?></p>
        </div>
    </header>
    <div class="pe-dr__flags">
        <span class="pe-status"><?php if ($r['closed']): ?><span class="pe-dot pe-dot--closed" aria-hidden="true"></span>Clos<?php else: ?><span class="pe-dot pe-dot--<?= $h($r['status_tone']) ?>" aria-hidden="true"></span><?= $h($r['status_label']) ?><?php endif; ?></span>
        <?php if ($r['classification'] !== null): ?><span class="pe-class pe-class--<?= $h(Ui::classTone($r['classification'])) ?>" title="<?= $h(W::classificationLabel($r['classification'])) ?>"><?= $h(W::classificationBadge($r['classification'])) ?></span><?php endif; ?>
        <?php if ($r['lock']['locked']): ?><span class="pe-badge pe-badge--plain"><?= $r['lock']['auto'] && !$r['lock']['manual'] ? 'Verrouillée par la règle' : 'Verrouillée' ?></span><?php endif; ?>
    </div>

    <div class="pe-dr__state" id="pe-dr-sign">
        <div>
            <span class="pe-dr__k">Signature</span>
            <?php if ($sig !== null): ?>
                <strong class="pe-ok">Signé</strong>
                <small><?= $h(trim(($sig['callsign'] !== '' ? $sig['callsign'] : '—') . ($sig['terminal'] !== '' ? ' · terminal ' . $sig['terminal'] : '') . ($sig['at'] !== '' ? ' · ' . Ui::full($sig['at']) : ''))) ?></small>
                <?php if (!empty($p['signature']['image_path'])): ?>
                    <img class="pe-sigimg" src="<?= $h(\App\Support\SseImageStorage::inlineSrc((string) $p['signature']['image_path'])) ?>" alt="Signature tracée au terminal">
                <?php endif; ?>
            <?php else: ?>
                <strong class="pe-warn">Non signé</strong>
                <small>Aucune signature du terminal sur cette fiche.</small>
            <?php endif; ?>
        </div>
        <div>
            <span class="pe-dr__k">Avis de recherche</span>
            <?php if ($wanted !== null): ?>
                <strong class="pe-bad">Recherché · <?= $h(Ui::threat($wanted['threat'])) ?></strong>
                <small><?= $h($wanted['ref']) ?> · <a href="<?= $h(url('atak/sse/papiers/identites/' . $wanted['identity_id'])) ?>">Ouvrir l’avis</a></small>
            <?php else: ?>
                <strong>Aucun avis</strong>
                <small><?php if ($viewer->canWanted): ?><a href="<?= $h($r['registry_id'] !== null ? url('atak/sse/papiers/identites/' . $r['registry_id'] . '/avis/nouveau') : url('atak/sse/identites/' . $id) . '?fiche=personne') ?>">Placer un avis</a><?php else: ?>Droit de placer un avis requis.<?php endif; ?></small>
            <?php endif; ?>
        </div>
    </div>

    <div class="pe-dr__tabs" role="tablist" aria-label="Contenu de l’aperçu">
        <button type="button" role="tab" id="pe-dt-sum" aria-controls="pe-dp-sum" aria-selected="true" data-pe-tab="sum">Résumé</button>
        <button type="button" role="tab" id="pe-dt-bio" aria-controls="pe-dp-bio" aria-selected="false" tabindex="-1" data-pe-tab="bio">Biométrie</button>
        <button type="button" role="tab" id="pe-dt-log" aria-controls="pe-dp-log" aria-selected="false" tabindex="-1" data-pe-tab="log">Suivi</button>
    </div>

    <section class="pe-dr__panel" role="tabpanel" id="pe-dp-sum" aria-labelledby="pe-dt-sum">
        <h3>Identité et circonstances</h3>
        <p class="pe-dr__desc">Ce que le terrain a transmis sur cette personne.</p>
        <dl class="pe-facts">
            <?= $fact('Circonstances', (string) ($p['circumstances_label'] ?? '')) ?>
            <?= $fact('Nationalité', (string) ($p['nationality'] ?? '')) ?>
            <?= $fact('Affiliation', (string) ($p['affiliation'] ?? '')) ?>
            <?= $fact('Grille', (string) ($p['grid_reference'] ?? '')) ?>
            <?= $fact('Âge estimé', ($p['age_estimated'] ?? null) !== null ? (string) $p['age_estimated'] . ' ans' : '') ?>
            <?= $fact('Langue', (string) ($p['language_spoken'] ?? '')) ?>
            <?= $fact('Collecteur', (string) ($p['submitter_callsign'] ?? '')) ?>
            <?= $fact('Enregistrée', Ui::full((string) ($p['created_at'] ?? ''))) ?>
        </dl>
        <?php if ($med !== []): ?>
            <h3>Constat de terrain</h3>
            <p class="pe-dr__desc"><?= $h((string) ($med['etat_label'] ?? 'État inconnu')) ?><?= (int) ($med['pouls'] ?? -1) > 0 ? ' · pouls ' . (int) $med['pouls'] . '/min' : '' ?><?= $lesions !== [] ? ' · lésions : ' . $h(implode(', ', $lesions)) : '' ?></p>
            <p class="pe-note">Constat d’observation — ne remplace pas un bilan médical.</p>
        <?php endif; ?>
    </section>

    <section class="pe-dr__panel" role="tabpanel" id="pe-dp-bio" aria-labelledby="pe-dt-bio" hidden>
        <h3>Relevés biométriques</h3>
        <p class="pe-dr__desc"><?= $r['bio_complete'] ? 'Les trois relevés sont présents.' : 'Biométrie incomplète : un relevé manque ou est de qualité insuffisante.' ?></p>
        <ul class="pe-biolist">
            <?php foreach ($bioNames as $k => $label): ?>
                <?php $s = $byKind[$k] ?? null; $st = $r['bio'][$k] ?? 'absent'; ?>
                <li>
                    <span class="pe-pill pe-pill--<?= $h($st) ?>" aria-hidden="true"><?= $h(mb_substr($label, 0, 1)) ?></span>
                    <span class="pe-biolist__main"><strong><?= $h($label) ?></strong><small><?= $h($bioState[$st]) ?><?= $s !== null && $s['quality'] !== null ? ' · qualité ' . (int) $s['quality'] . ' %' : '' ?></small></span>
                    <?php if ($s !== null && !empty($s['lab_reference'])): ?><span class="pe-biolist__ref">réf. <?= $h($s['lab_reference']) ?></span><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="pe-dr__panel" role="tabpanel" id="pe-dp-log" aria-labelledby="pe-dt-log" hidden>
        <h3>Suivi du dossier</h3>
        <p class="pe-dr__desc">Ouvertures, chaîne de possession et décisions prises sur la fiche.</p>
        <dl class="pe-facts">
            <?= $fact('Modifiée', Ui::when($r['updated_at'], $now)) ?>
            <?= $fact('Ouverte par vous', $r['my_opened_at'] !== null ? Ui::when($r['my_opened_at'], $now) : 'Jamais') ?>
            <?= $fact('Dernière lecture', $r['last_read'] !== null ? trim(($r['last_read']['who'] !== null ? $r['last_read']['who'] . ' · ' : '') . Ui::when($r['last_read']['at'], $now)) : 'Par personne d’autre') ?>
            <?= $fact('Liens', (string) $r['links'] . ' (dossiers et identités du registre)') ?>
            <?php if ($r['lock']['manual']): ?><?= $fact('Verrouillage', trim((string) $r['lock']['reason'] . ($r['lock']['until'] !== null ? ' — jusqu’au ' . Ui::full($r['lock']['until']) : ''))) ?><?php endif; ?>
            <?php if ($r['closed']): ?><?= $fact('Clôture', W::closeReasonLabel($r['close_reason']) . ' · ' . Ui::when($r['closed_at'], $now) . ($d['close_by'] !== null ? ' · ' . $d['close_by'] : '')) ?><?php endif; ?>
        </dl>
        <?php if ($d['custody'] !== []): ?>
            <details class="pe-fold">
                <summary>Chaîne de possession (<?= count($d['custody']) ?>)</summary>
                <ol class="pe-timeline">
                    <?php foreach ($d['custody'] as $ev): ?>
                        <li><time><?= $h(Ui::when((string) ($ev['created_at'] ?? ''), $now)) ?></time><span><?= $h($ev['type_label'] ?? '') ?><?= !empty($ev['label']) ? ' — ' . $h($ev['label']) : '' ?></span><?php if (!empty($ev['actor'])): ?><small><?= $h($ev['actor']) ?></small><?php endif; ?></li>
                    <?php endforeach; ?>
                </ol>
            </details>
        <?php endif; ?>
        <?php if ($d['history'] !== []): ?>
            <details class="pe-fold" open>
                <summary>Décisions sur la fiche (<?= count($d['history']) ?>)</summary>
                <ol class="pe-timeline">
                    <?php foreach ($d['history'] as $ev): ?>
                        <li><time><?= $h(Ui::when((string) ($ev['created_at'] ?? ''), $now)) ?></time><span><?= $h($auditLabels[$ev['action']] ?? $ev['action']) ?><?= !empty($ev['reason']) ? ' — ' . $h($ev['reason']) : '' ?></span><?php if (!empty($ev['actor_label'])): ?><small><?= $h($ev['actor_label']) ?></small><?php endif; ?></li>
                    <?php endforeach; ?>
                </ol>
            </details>
        <?php endif; ?>
    </section>

    <footer class="pe-dr__foot">
        <a class="pe-btn pe-btn--solid" href="<?= $h(url('atak/sse/identites/' . $id)) ?>">Ouvrir la fiche</a>
        <button type="button" class="pe-btn" data-pe-act="classify" data-ids="<?= $id ?>"<?= $canAct ? '' : ' disabled title="' . $h($deny) . '"' ?>>Classifier</button>
        <?php if ($r['lock']['manual']): ?>
            <button type="button" class="pe-btn" data-pe-act="unlock" data-ids="<?= $id ?>"<?= $canAct ? '' : ' disabled title="' . $h($deny) . '"' ?>>Déverrouiller</button>
        <?php else: ?>
            <button type="button" class="pe-btn" data-pe-act="lock" data-ids="<?= $id ?>"<?= $canAct ? '' : ' disabled title="' . $h($deny) . '"' ?>>Verrouiller</button>
        <?php endif; ?>
        <?php if ($r['closed']): ?>
            <button type="button" class="pe-btn" data-pe-act="reopen" data-ids="<?= $id ?>"<?= $canAct ? '' : ' disabled title="' . $h($deny) . '"' ?>>Rouvrir</button>
        <?php else: ?>
            <button type="button" class="pe-btn" data-pe-act="close" data-ids="<?= $id ?>"<?= $canAct ? '' : ' disabled title="' . $h($deny) . '"' ?>>Clore</button>
        <?php endif; ?>
    </footer>
</div>
