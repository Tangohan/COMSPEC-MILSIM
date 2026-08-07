<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$type = (string) ($type ?? 'person');
$kinds = is_array($kinds ?? null) ? $kinds : [];
/** @var array<string, array{hint:string, fields:list<array<string,mixed>>}> $metaSchema */
$metaSchema = is_array($metaSchema ?? null) ? $metaSchema : [];
$classifications = is_array($classifications ?? null) ? $classifications : [];
if ($type === '' || !isset($kinds[$type])) {
    $type = array_key_first($kinds) ?: 'person';
}
?>
<div class="page-heading">
    <div>
        <div class="page-heading-overline">Objets // Création</div>
        <h1>Créer un objet</h1>
        <p>
            Choisissez le type, puis renseignez les champs propres à ce type.
            L’objet sera réutilisable dans les dossiers, investigations, chronologie et carte.
        </p>
    </div>
</div>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">O.01</span> Nouvel objet</div>
    </div>
    <div class="panel-body">
        <form method="post" action="<?= $h(url('atak/sse/objets')) ?>" id="sse-object-create">
            <?= \App\Core\Csrf::field() ?>

            <div class="sse-form-grid">
                <div>
                    <label for="kind">Type d’objet</label>
                    <select id="kind" name="kind" required>
                        <?php foreach ($kinds as $k => $lab): ?>
                            <option value="<?= $h($k) ?>" <?= $type === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="classification">Classification</label>
                    <select id="classification" name="classification">
                        <?php foreach ($classifications as $k => $lab): ?>
                            <option value="<?= $h($k) ?>" <?= $k === 'confidentiel' ? 'selected' : '' ?>><?= $h($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <label for="label">Libellé / désignation</label>
            <input id="label" name="label" type="text" required maxlength="200" placeholder="Désignation opérationnelle">

            <label for="detail">Précision libre (optionnel)</label>
            <input id="detail" name="detail" type="text" maxlength="255" placeholder="Complément non couvert par les champs ci-dessous">

            <div class="object-meta-host" id="object-meta-host">
                <?php foreach ($metaSchema as $kindKey => $schema): ?>
                    <?php
                    $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
                    $hint = (string) ($schema['hint'] ?? '');
                    $active = $type === $kindKey;
                    ?>
                    <fieldset
                        class="object-meta<?= $active ? ' is-active' : '' ?>"
                        data-kind="<?= $h($kindKey) ?>"
                        <?= $active ? '' : 'hidden' ?>
                        <?= $active ? '' : 'disabled' ?>
                    >
                        <legend>Caractéristiques — <?= $h($kinds[$kindKey] ?? $kindKey) ?></legend>
                        <?php if ($hint !== ''): ?>
                            <p class="sse-note"><?= $h($hint) ?></p>
                        <?php endif; ?>
                        <div class="sse-form-grid">
                            <?php foreach ($fields as $field): ?>
                                <?php
                                $fname = (string) ($field['name'] ?? '');
                                if ($fname === '') {
                                    continue;
                                }
                                $fid = 'meta_' . $kindKey . '_' . $fname;
                                $flabel = (string) ($field['label'] ?? $fname);
                                $ftype = (string) ($field['type'] ?? 'text');
                                $placeholder = (string) ($field['placeholder'] ?? '');
                                $options = is_array($field['options'] ?? null) ? $field['options'] : [];
                                ?>
                                <div>
                                    <label for="<?= $h($fid) ?>"><?= $h($flabel) ?></label>
                                    <?php if ($ftype === 'select'): ?>
                                        <select id="<?= $h($fid) ?>" name="meta[<?= $h($fname) ?>]">
                                            <option value="">— Choisir —</option>
                                            <?php foreach ($options as $ok => $olab): ?>
                                                <option value="<?= $h($ok) ?>"><?= $h($olab) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <input
                                            id="<?= $h($fid) ?>"
                                            name="meta[<?= $h($fname) ?>]"
                                            type="text"
                                            maxlength="200"
                                            placeholder="<?= $h($placeholder) ?>"
                                        >
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                <?php endforeach; ?>
            </div>

            <p class="sse-note">
                Pour une identité terrain complète avec photos et biométrie, préférez la collecte via terminal.
                Ici, l’objet est créé comme nœud d’investigation avec ses métadonnées.
            </p>

            <div class="toolbar-actions" style="margin-top:1rem">
                <button class="btn" type="submit">Créer et ouvrir dans une investigation</button>
                <a class="btn btn--ghost" href="<?= $h(url('atak/sse/operations')) ?>">Annuler</a>
            </div>
        </form>
    </div>
</section>

<script>
(function () {
    var kindSelect = document.getElementById('kind');
    var host = document.getElementById('object-meta-host');
    if (!kindSelect || !host) return;

    function sync() {
        var kind = kindSelect.value;
        host.querySelectorAll('.object-meta').forEach(function (fs) {
            var on = fs.getAttribute('data-kind') === kind;
            fs.classList.toggle('is-active', on);
            fs.hidden = !on;
            fs.disabled = !on;
        });
    }

    kindSelect.addEventListener('change', sync);
    sync();
})();
</script>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
