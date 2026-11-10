<?php
declare(strict_types=1);
use App\Support\SseIdentityCatalog as C;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $check */
/** @var array<string,mixed>|null $doc */
/** @var array<string,mixed>|null $person */
$results = is_array($check['results'] ?? null) ? $check['results'] : [];
/** Résultat d'un niveau en lignes lisibles « clé : valeur ». */
$describe = static function (mixed $res) use (&$describe): string {
    if (is_bool($res)) {
        return $res ? 'oui' : 'non';
    }
    if (!is_array($res)) {
        return (string) $res;
    }
    $lines = [];
    foreach ($res as $k => $v) {
        $lines[] = (is_int($k) ? '' : $k . ' : ') . (is_array($v) ? str_replace("\n", ' ; ', $describe($v)) : $describe($v));
    }

    return implode("\n", $lines);
};
require __DIR__ . '/_subnav.php';
?>

<div class="page-heading">
    <div>
        <h1><?= $h($check['ref']) ?></h1>
        <p>Verdict sur place : <span class="badge idp-verdict--<?= $h($check['verdict'] !== '' ? $check['verdict'] : 'none') ?>"><?= $h($check['verdict_label']) ?></span></p>
    </div>
    <div class="page-reference">
        <strong><?= $h($check['controller_callsign'] ?? 'Contrôleur inconnu') ?></strong>
        <?php if (!empty($check['grid'])): ?><div class="muted">Carroyage <?= $h($check['grid']) ?></div><?php endif; ?>
    </div>
</div>

<section class="panel">
    <div class="panel-header"><div class="panel-title"><span class="panel-index">01</span> Ce qui a été présenté</div></div>
    <div class="panel-body idp-grid">
        <div><span class="muted">Papier</span><br><strong><?= $h(C::label(C::DOC_TYPES, (string) ($check['doc_type'] ?? ''), 'Non précisé')) ?></strong></div>
        <div><span class="muted">Pays</span><br><strong><?= $h($check['country_code'] ?? '—') ?></strong></div>
        <div><span class="muted">Numéro</span><br><strong translate="no"><?= $h($check['doc_number'] ?? '—') ?></strong></div>
        <div><span class="muted">Nom présenté</span><br><strong><?= $h(trim(mb_strtoupper((string) ($check['presented_last'] ?? '')) . ' ' . ($check['presented_first'] ?? '')) ?: '—') ?></strong></div>
        <div><span class="muted">Date de naissance présentée</span><br><strong><?= $h($check['presented_dob'] ?? '—') ?></strong></div>
        <div><span class="muted">Heure en jeu</span><br><strong><?= $h($check['game_time'] ?? '—') ?></strong></div>
        <div><span class="muted">Opération</span><br><strong><?= $h($check['op_key'] ?? '—') ?></strong></div>
        <div><span class="muted">Dossier</span><br><strong><?= $h($check['case_ref'] ?? '—') ?></strong></div>
    </div>
    <div class="panel-body idp-actions">
        <?php if ($doc !== null): ?>
            <a class="btn btn--ghost" href="<?= $h(url('atak/sse/papiers/documents/' . (int) $doc['id'])) ?>">Papier du registre <?= $h($doc['ref']) ?></a>
        <?php else: ?>
            <span class="muted">Papier absent du registre.</span>
        <?php endif; ?>
        <?php if ($person !== null): ?>
            <a class="btn btn--ghost" href="<?= $h(url('atak/sse/identites/' . (int) $person['id'])) ?>">Fiche personne <?= $h($person['display_name'] ?? ('IDN-' . (int) $person['id'])) ?></a>
        <?php else: ?>
            <span class="muted">Aucune fiche personne rattachée.</span>
        <?php endif; ?>
    </div>
</section>

<section class="panel" style="margin-top:10px">
    <div class="panel-header"><div class="panel-title"><span class="panel-index">02</span> Détail par niveau</div></div>
    <div class="panel-body">
        <?php foreach (C::LEVELS as $lvl => $label): ?>
            <div class="idp-level">
                <strong><?= $h($label) ?></strong>
                <?php if (!in_array($lvl, $check['levels'], true) && !array_key_exists($lvl, $results)): ?>
                    <span class="muted"> — non réalisé</span>
                <?php else: ?>
                    <pre><?= $h(array_key_exists($lvl, $results) ? $describe($results[$lvl]) : 'Réalisé, sans détail transmis.') ?></pre>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel" style="margin-top:10px">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">03</span> Fiche FRM et photos</div>
        <?php if (!empty($check['forgery'])): ?><div class="panel-meta"><span class="badge idp-verdict--hvt">Faux document détecté</span></div><?php endif; ?>
    </div>
    <div class="panel-body">
        <?php $note = is_array($check['note'] ?? null) ? $check['note'] : null; ?>
        <?php if ($note === null): ?>
            <p class="muted">Aucune fiche de renseignement rattachée à ce contrôle. Le contrôleur peut en joindre une depuis l’ATAK (fiche CONTRÔLE ID) ou son vrai téléphone (Contrôle ID).</p>
        <?php else: ?>
            <p>Fiche <a class="link" href="<?= $h(url('atak/sse/fiches/' . (int) $note['id'])) ?>"><code><?= $h($note['ref']) ?></code></a> · <?= $h($note['status_label']) ?><?= $note['status'] === 'brouillon' ? ' (à valider)' : '' ?></p>
        <?php endif; ?>
        <?php if (!empty($check['photos'])): ?>
            <div class="idp-photos">
                <?php foreach ($check['photos'] as $p): ?>
                    <a href="<?= $h($p['url']) ?>" target="_blank" rel="noopener"><img src="<?= $h($p['url']) ?>" alt="<?= $h($p['caption'] !== '' ? $p['caption'] : 'Photo du contrôle') ?>" loading="lazy"></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
