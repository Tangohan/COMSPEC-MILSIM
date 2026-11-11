<?php
declare(strict_types=1);
/**
 * Fiche sujet imprimable (une page A4, fond clair) : en-tête du système, mise en garde, identité, signalement, alias,
 * papiers, liens, mentions, derniers contacts, message brut. Mention SIMULATION en tête et en pied.
 *
 * @var array<string,mixed> $record
 * @var string $printedBy
 */
use App\Support\SseFileSubjectCatalog as S;
use App\Support\SseRecordsFormat as RF;
use App\Support\SseRecordsCountryPacks as Packs;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$r = $record;
$i = $r['identity'];
$p = $r['profile'] ?? [];
$c = (string) $r['country'];
$photo = $r['photos'][0] ?? null;
$red = array_filter($r['flags'], static fn (array $f): bool => $f['tone'] === 'red');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= $h($i['ref'] . ' — ' . $i['display_name']) ?> — Fiche</title>
<style>
    @page { size: A4; margin: 14mm; }
    body { margin: 0; background: #fff; color: #111; font: 12.5px/1.45 Inter, system-ui, sans-serif; }
    .pg { max-width: 190mm; margin: 0 auto; padding: 16px; }
    .sim { border: 1.5px solid #b45309; color: #92400e; padding: 6px 10px; font-weight: 700; letter-spacing: .08em; font-size: 12px; }
    header { display: flex; justify-content: space-between; gap: 12px; align-items: flex-end; border-bottom: 2px solid #111; padding: 10px 0 8px; }
    h1 { margin: 0; font-size: 22px; letter-spacing: .01em; }
    .mono { font-family: "JetBrains Mono", ui-monospace, Consolas, monospace; }
    .caut { margin: 10px 0 0; padding: 8px 10px; border: 2px solid #b91c1c; color: #b91c1c; font-weight: 800; text-transform: uppercase; }
    .id { display: grid; grid-template-columns: 110px minmax(0, 1fr); gap: 14px; margin-top: 12px; }
    .ph { width: 110px; height: 138px; border: 1px solid #444; background: #e5e7eb; display: grid; place-items: center; overflow: hidden; color: #555; font-size: 12px; }
    .ph img { width: 100%; height: 100%; object-fit: cover; }
    dl { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 6px 12px; margin: 0; }
    dt { font-size: 12px; color: #555; }
    dd { margin: 0; font-family: "JetBrains Mono", ui-monospace, Consolas, monospace; font-size: 12.5px; }
    h2 { font-size: 12px; text-transform: uppercase; letter-spacing: .12em; margin: 14px 0 6px; border-bottom: 1px solid #999; padding-bottom: 3px; }
    table { width: 100%; border-collapse: collapse; font-size: 12px; }
    td, th { text-align: left; padding: 4px 6px; border-bottom: 1px solid #ddd; vertical-align: top; }
    pre { white-space: pre-wrap; font-size: 12px; border: 1px solid #999; padding: 8px; margin: 0; }
    footer { margin-top: 14px; font-size: 12px; color: #555; display: flex; justify-content: space-between; gap: 8px; }
    .noprint { margin: 12px 0; }
    @media print { .noprint { display: none; } }
</style>
</head>
<body>
<div class="pg">
    <div class="noprint"><button type="button" onclick="window.print()">Imprimer</button></div>
    <div class="sim">SIMULATION — DONNÉES FICTIVES DU JEU · AUCUNE BASE RÉELLE</div>
    <header>
        <div><div class="mono"><?= $h($r['response']['system']) ?> · <?= $h($i['ref']) ?></div><h1><?= $h(mb_strtoupper((string) $i['last_name'])) ?><?= ($i['first_name'] ?? '') !== '' ? ', ' . $h($i['first_name']) : '' ?></h1></div>
        <div class="mono"><?= $h($r['response']['text']) ?></div>
    </header>
    <?php if ($red !== []): ?><div class="caut"><?= $h(implode(' · ', array_map(static fn (array $f): string => $f['label'], $red))) ?></div><?php endif; ?>
    <div class="id">
        <div class="ph"><?php if ($photo !== null): ?><img src="<?= $h(user_media_public_url((string) $photo['path'])) ?>" alt=""><?php else: ?>SANS PHOTO<?php endif; ?></div>
        <dl>
            <div><dt>Naissance</dt><dd><?= $h(($i['birth_date'] ?? null) ? RF::date((string) $i['birth_date'], $c) : '—') ?></dd></div>
            <div><dt>Sexe</dt><dd><?= $h($i['sex'] ?? 'X') ?></dd></div>
            <div><dt>Nationalité</dt><dd><?= $h(($i['nationality'] ?? null) ? Packs::label((string) $i['nationality']) : '—') ?></dd></div>
            <div><dt>Taille</dt><dd><?= $h(($p['height_cm'] ?? null) !== null ? RF::height((int) $p['height_cm'], $c) : '—') ?></dd></div>
            <div><dt>Poids</dt><dd><?= $h(($p['weight_kg'] ?? null) !== null ? RF::weight((int) $p['weight_kg'], $c) : '—') ?></dd></div>
            <div><dt>Corpulence</dt><dd><?= $h(S::label(S::BUILDS, $p['build'] ?? null)) ?></dd></div>
            <div><dt>Cheveux</dt><dd><?= $h(S::label(S::HAIR, $p['hair'] ?? null)) ?></dd></div>
            <div><dt>Yeux</dt><dd><?= $h(S::label(S::EYES, $p['eyes'] ?? null)) ?></dd></div>
            <div><dt>Profession</dt><dd><?= $h($i['profession'] ?? '—') ?></dd></div>
        </dl>
    </div>
    <h2>Alias et signes</h2>
    <p><b>AKA :</b> <?= $h($r['aliases'] !== [] ? implode(' ; ', array_map(static fn (array $a): string => (string) $a['alias'], $r['aliases'])) : '—') ?><br><b>Signes :</b> <?= $h($p['marks'] ?? '—') ?></p>
    <h2>Mentions</h2>
    <?php if ($r['flags'] === []): ?><p>Aucune mention.</p><?php else: ?><p><?= $h(implode(' · ', array_map(static fn (array $f): string => $f['label'], $r['flags']))) ?></p><?php endif; ?>
    <?php if ($r['documents'] !== []): ?>
        <h2>Papiers délivrés</h2>
        <table><tr><th>Papier</th><th>Pays</th><th>Numéro</th><th>État</th></tr>
        <?php foreach ($r['documents'] as $d): ?><tr><td><?= $h($d['doc_type_label'] ?? $d['doc_type']) ?></td><td><?= $h($d['country_code']) ?></td><td class="mono"><?= $h($d['doc_number']) ?></td><td><?= $h($d['state_label']) ?></td></tr><?php endforeach; ?>
        </table>
    <?php endif; ?>
    <?php $links = array_merge($r['links']['address'], $r['links']['vehicle'], $r['links']['phone']); ?>
    <?php if ($links !== []): ?>
        <h2>Adresses, véhicules, téléphones</h2>
        <table><?php foreach ($links as $l): ?><tr><td><?= $h($l['kind_label']) ?></td><td class="mono"><?= $h($l['value']) ?></td><td><?= $h($l['flag_label']) ?></td></tr><?php endforeach; ?></table>
    <?php endif; ?>
    <?php if ($r['contacts']['list'] !== []): ?>
        <h2>Derniers contacts</h2>
        <table><?php foreach (array_slice($r['contacts']['list'], 0, 8) as $ct): ?><tr><td class="mono"><?= $h(substr((string) $ct['at'], 0, 16)) ?>Z</td><td><?= $h($ct['label']) ?></td><td><?= $h($ct['where']) ?></td><td><?= $h($ct['who']) ?></td><td><?= $h($ct['outcome']) ?></td></tr><?php endforeach; ?></table>
    <?php endif; ?>
    <h2>Message brut</h2>
    <pre><?= $h($r['raw']) ?></pre>
    <footer><span>Imprimé le <?= gmdate('Y-m-d H:i') ?>Z par <?= $h($printedBy) ?></span><span>SIMULATION — DONNÉES FICTIVES</span></footer>
</div>
</body>
</html>
