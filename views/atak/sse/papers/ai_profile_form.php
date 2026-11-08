<?php
declare(strict_types=1);

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed>|null $profile */
/** @var array<string,mixed>|null $identity */
/** @var list<array<string,mixed>> $identities */
/** @var list<array<string,mixed>> $documents */
/** @var list<array{id:int,label:string}> $maps */
/** @var array{view:bool,manage:bool,truth:bool} $rights */
$canEdit = $rights['manage'] && $rights['truth'];
$p = $profile ?? ['id' => 0, 'identity_id' => 0, 'context_id' => null, 'op_key' => '', 'label' => '', 'side' => 'civ', 'category' => 'civilian', 'faction' => '', 'papers' => 'identity', 'behaviour' => 'auto', 'kit' => '', 'notes' => ''];
$id = (int) $p['id'];
$opts = [
    'side' => ['civ' => 'Civils', 'west' => 'BLUFOR', 'east' => 'OPFOR', 'guer' => 'Indépendants'],
    'category' => ['civilian' => 'Civil', 'military' => 'Militaire', 'suspect' => 'Suspect', 'hvt' => 'HVT'],
    'papers' => ['identity' => 'Ceux de son identité', 'auto' => 'Selon son camp (générés)', 'none' => 'Aucun papier'],
    'behaviour' => ['auto' => 'Selon le contexte (IA réalistes)', 'coopere' => 'Coopère', 'nerveux' => 'Nerveux', 'ment' => 'Ment avec aplomb', 'refuse' => 'Refuse', 'fuit' => 'S’enfuit', 'agressif' => 'Devient agressif', 'rendu' => 'Se rend'],
];
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-id-workshop.css')) . '">';
$select = static function (string $name, string $label, array $choices, string $value) use ($h): string {
    $o = '';
    foreach ($choices as $k => $l) {
        $o .= '<option value="' . $h($k) . '"' . ((string) $k === $value ? ' selected' : '') . '>' . $h($l) . '</option>';
    }

    return '<div><label for="p-' . $h($name) . '">' . $h($label) . '</label><select id="p-' . $h($name) . '" name="' . $h($name) . '">' . $o . '</select></div>';
};
require __DIR__ . '/_subnav.php';
?>
<div class="breadcrumb">Athena / SSE / <a class="link" href="<?= $h(url('atak/sse/papiers/profils-ia')) ?>">Profils IA</a> / <strong><?= $id > 0 ? $h($p['ref']) : 'Nouveau' ?></strong></div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Profil IA // <?= $id > 0 ? $h($p['ref']) : 'Nouveau' ?></div>
        <h1><?= $id > 0 ? $h($p['label']) : 'Nouveau profil IA' ?></h1>
        <p>En jeu : éditeur SSE › IA (Zeus), ou attribut « Profil Athena » (Eden). Le nom et les papiers viennent de l’identité ; les changer sur sa fiche les change sur le profil.</p>
    </div>
</div>

<form class="panel idw-form" method="post" action="<?= $h(url('atak/sse/papiers/profils-ia' . ($id > 0 ? '/' . $id : ''))) ?>">
    <div class="panel-header"><div class="panel-title"><span class="panel-index">01</span> Profil</div></div>
    <div class="panel-body">
        <?= \App\Core\Csrf::field() ?>
        <label for="p-label">Libellé</label>
        <input id="p-label" name="label" maxlength="120" required value="<?= $h($p['label']) ?>" placeholder="Berger du nord, insurgé de garde…" <?= $canEdit ? '' : 'disabled' ?>>
        <div class="idp-statuses">
            <?= $select('side', 'Camp', $opts['side'], (string) $p['side']) ?>
            <?= $select('category', 'Catégorie', $opts['category'], (string) $p['category']) ?>
            <?= $select('papers', 'Papiers', $opts['papers'], (string) $p['papers']) ?>
            <?= $select('behaviour', 'Comportement au contrôle', $opts['behaviour'], (string) $p['behaviour']) ?>
        </div>
        <label for="p-faction">Faction</label>
        <input id="p-faction" name="faction" maxlength="120" value="<?= $h($p['faction'] ?? '') ?>">
        <label for="p-kit">Kit ou tenue</label>
        <input id="p-kit" name="kit" maxlength="190" value="<?= $h($p['kit'] ?? '') ?>" placeholder="Tenue civile, pas d’arme apparente">
        <div class="idp-statuses">
            <div><label for="p-map">Carte</label><select id="p-map" name="context_id"><option value="">Toutes</option><?php foreach ($maps as $m): ?><option value="<?= (int) $m['id'] ?>" <?= (int) ($p['context_id'] ?? 0) === $m['id'] ? 'selected' : '' ?>><?= $h($m['label']) ?></option><?php endforeach; ?></select></div>
            <div><label for="p-op">Opération</label><input id="p-op" name="op_key" maxlength="64" value="<?= $h($p['op_key'] ?? '') ?>"></div>
        </div>
        <label for="p-notes">Notes</label>
        <textarea id="p-notes" name="notes" rows="3"><?= $h($p['notes'] ?? '') ?></textarea>
    </div>
    <div class="panel-header"><div class="panel-title"><span class="panel-index">02</span> Identité</div></div>
    <div class="panel-body">
        <label for="p-identity">Identité du registre</label>
        <select id="p-identity" name="identity_id">
            <option value="">Aucune : générée en jeu, ou nouvelle ci-dessous</option>
            <?php foreach ($identities as $i): ?><option value="<?= (int) $i['id'] ?>" <?= (int) $p['identity_id'] === (int) $i['id'] ? 'selected' : '' ?>><?= $h($i['ref'] . ' · ' . $i['display_name']) ?></option><?php endforeach; ?>
        </select>
        <?php if ($id === 0): ?>
            <p class="field-help">Ou créez la personne ici : elle entrera au registre des identités.</p>
            <div class="idp-statuses">
                <div><label for="n-last">Nom</label><input id="n-last" name="new_last" maxlength="80"></div>
                <div><label for="n-first">Prénom</label><input id="n-first" name="new_first" maxlength="80"></div>
                <div><label for="n-sex">Sexe</label><select id="n-sex" name="new_sex"><option value="M">M</option><option value="F">F</option></select></div>
                <div><label for="n-birth">Né(e) le</label><input id="n-birth" type="date" name="new_birth"></div>
                <div><label for="n-place">Lieu de naissance</label><input id="n-place" name="new_place" maxlength="80"></div>
                <div><label for="n-nat">Nationalité</label><input id="n-nat" name="new_nat" maxlength="8" placeholder="ALT"></div>
                <div><label for="n-kind">Nature</label><select id="n-kind" name="new_kind"><option value="civil">Civile</option><option value="cover">Couverture</option><option value="forged">Falsifiée</option></select></div>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($canEdit): ?><div class="panel-body"><button class="button primary" type="submit">Enregistrer</button></div><?php endif; ?>
</form>

<?php if ($id > 0): ?>
<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">03</span> Ses papiers</div>
        <?php if ($identity !== null && $canEdit): ?><a class="button" href="<?= $h(url('atak/sse/papiers/documents/nouveau') . '?identity_id=' . (int) $identity['id']) ?>">Ajouter un papier</a><?php endif; ?>
    </div>
    <div class="panel-body">
        <?php if ($identity === null): ?>
            <p>Sans identité du registre, les papiers sont générés en jeu à la première demande.</p>
        <?php else: ?>
            <p>Identité : <a class="link" href="<?= $h(url('atak/sse/papiers/identites/' . (int) $identity['id'])) ?>"><?= $h($identity['ref'] . ' · ' . $identity['display_name']) ?></a></p>
            <?php if ($documents === []): ?><p>Aucun papier émis. Sans papier émis, l’IA reçoit des papiers générés à ce nom.</p><?php endif; ?>
            <ul>
                <?php foreach ($documents as $d): ?>
                    <li><a class="link" href="<?= $h(url('atak/sse/papiers/documents/' . (int) $d['id'])) ?>"><?= $h(($d['type_label'] ?? $d['doc_type'] ?? '') . ' n° ' . ($d['doc_number'] ?? '')) ?></a>
                        <?= in_array($d['st_origin'] ?? '', ['forged'], true) ? ' · <strong>Faux</strong>' : (($d['st_origin'] ?? '') === 'cover' ? ' · Couverture' : ' · Vrai') ?></li>
                <?php endforeach; ?>
            </ul>
            <p class="field-help">Vrai ou faux : statut « Origine » sur la fiche du papier.</p>
        <?php endif; ?>
    </div>
</section>
<?php if ($canEdit): ?>
<section class="panel"><div class="panel-body">
    <form method="post" action="<?= $h(url('atak/sse/papiers/profils-ia/' . $id . '/dupliquer')) ?>" style="display:inline"><?= \App\Core\Csrf::field() ?><button class="button" type="submit">Dupliquer</button></form>
    <form method="post" action="<?= $h(url('atak/sse/papiers/profils-ia/' . $id . '/supprimer')) ?>" style="display:inline" data-confirm="Supprimer ce profil ? L’identité et ses papiers restent au registre."><?= \App\Core\Csrf::field() ?><button class="button danger" type="submit">Supprimer</button></form>
</div></section>
<?php endif; endif; ?>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
