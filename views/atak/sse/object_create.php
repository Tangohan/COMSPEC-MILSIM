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
$typeIcons = [
    'person' => 'ID',
    'alias' => 'AL',
    'organization' => 'OR',
    'site' => 'SI',
    'vehicle' => 'VE',
    'weapon' => 'AR',
    'phone' => 'TE',
    'terminal' => 'TM',
    'document' => 'DO',
    'event' => 'EV',
    'report' => 'CR',
    'photo' => 'PH',
    'biometric' => 'BIO',
    'seizure' => 'SA',
    'custom' => 'EL',
];
?>
<div class="breadcrumb">
    Athena / SSE /
    <a class="link" href="<?= $h(url('atak/sse/operations')) ?>">Opérations</a> /
    <strong>Nouvel objet</strong>
</div>

<header class="sse-obj-create__hero">
    <div>
        <p class="sse-obj-create__kicker">Registre // Création</p>
        <h1>Nouvel objet</h1>
        <p class="sse-obj-create__lead">
            Décrivez un élément réutilisable : identité, site, véhicule, pièce… Il pourra ensuite
            être rattaché à un dossier, une investigation, la chronologie ou la carte.
        </p>
    </div>
    <aside class="sse-obj-create__aside" aria-label="Rappel">
        <strong>Ce n’est pas une preuve</strong>
        <span>La fiche sert à organiser l’exploitation. L’identification formelle reste une décision humaine.</span>
    </aside>
</header>

<form method="post" action="<?= $h(url('atak/sse/objets/creer')) ?>" id="sse-object-create" class="sse-obj-create" enctype="multipart/form-data">
    <?= \App\Core\Csrf::field() ?>

    <section class="sse-obj-create__card" aria-labelledby="obj-step-1">
        <header class="sse-obj-create__card-head">
            <span class="sse-obj-create__step">01</span>
            <div>
                <h2 id="obj-step-1">Type et diffusion</h2>
                <p>Choisissez la nature de l’objet et le niveau de diffusion de la fiche.</p>
            </div>
        </header>

        <fieldset class="sse-obj-create__types">
            <legend class="sse-obj-create__label">Type d’objet</legend>
            <div class="sse-obj-create__type-grid" role="radiogroup" aria-label="Type d’objet">
                <?php foreach ($kinds as $k => $lab): ?>
                    <?php $checked = $type === $k; ?>
                    <label class="sse-obj-create__type<?= $checked ? ' is-active' : '' ?>">
                        <input
                            type="radio"
                            name="kind"
                            value="<?= $h($k) ?>"
                            <?= $checked ? 'checked' : '' ?>
                            required
                        >
                        <span class="sse-obj-create__type-code" aria-hidden="true"><?= $h($typeIcons[$k] ?? 'OB') ?></span>
                        <span class="sse-obj-create__type-lab"><?= $h($lab) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <div class="sse-obj-create__row">
            <div class="sse-obj-create__field">
                <label class="sse-obj-create__label" for="classification">Niveau de diffusion</label>
                <select id="classification" name="classification">
                    <?php foreach ($classifications as $k => $lab): ?>
                        <option value="<?= $h($k) ?>" <?= $k === 'confidentiel' ? 'selected' : '' ?>><?= $h($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sse-obj-create__field sse-obj-create__field--grow">
                <label class="sse-obj-create__label" for="label">Libellé / désignation</label>
                <input id="label" name="label" type="text" required maxlength="200" placeholder="Ex. Identité 01 — convoyeur secteur Nord" autocomplete="off">
            </div>
        </div>

        <div class="sse-obj-create__field">
            <label class="sse-obj-create__label" for="detail">Précision libre <span>(optionnel)</span></label>
            <textarea id="detail" name="detail" rows="3" maxlength="2000" placeholder="Complément non couvert par les champs ci-dessous"></textarea>
        </div>
    </section>

    <section class="sse-obj-create__card sse-obj-create__card--meta" aria-labelledby="obj-step-2">
        <header class="sse-obj-create__card-head">
            <span class="sse-obj-create__step">02</span>
            <div>
                <h2 id="obj-step-2">Caractéristiques</h2>
                <p>Champs adaptés au type sélectionné — renseignez ce qui est connu, laissez le reste vide.</p>
            </div>
        </header>

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
                            $rows = max(3, (int) ($field['rows'] ?? 4));
                            $isWide = $ftype === 'textarea';
                            ?>
                            <div class="<?= $isWide ? 'object-meta-field--full' : '' ?>">
                                <label for="<?= $h($fid) ?>"><?= $h($flabel) ?></label>
                                <?php if ($ftype === 'select'): ?>
                                    <select id="<?= $h($fid) ?>" name="meta[<?= $h($fname) ?>]">
                                        <option value="">— Choisir —</option>
                                        <?php foreach ($options as $ok => $olab): ?>
                                            <option value="<?= $h($ok) ?>"><?= $h($olab) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ($ftype === 'textarea'): ?>
                                    <textarea
                                        id="<?= $h($fid) ?>"
                                        name="meta[<?= $h($fname) ?>]"
                                        rows="<?= $rows ?>"
                                        maxlength="12000"
                                        placeholder="<?= $h($placeholder) ?>"
                                    ></textarea>
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
    </section>

    <section class="sse-obj-create__card" aria-labelledby="obj-step-3">
        <header class="sse-obj-create__card-head">
            <span class="sse-obj-create__step">03</span>
            <div>
                <h2 id="obj-step-3">Illustration</h2>
                <p>Photo, scan ou capture — optionnel. Les fichiers lourds sont compressés automatiquement.</p>
            </div>
        </header>

        <div class="object-image-upload sse-obj-create__upload">
            <label class="sse-obj-create__drop" for="object_image">
                <span class="sse-obj-create__drop-title">Joindre une image</span>
                <span class="sse-obj-create__drop-hint">JPEG, PNG, WebP ou GIF · compression au-delà de 5 Mo</span>
            </label>
            <input
                id="object_image"
                name="image"
                type="file"
                accept="image/jpeg,image/png,image/webp,image/gif,.jpg,.jpeg,.png,.webp,.gif"
                data-sse-image-target-bytes="5000000"
                data-sse-image-upload-max-bytes="25000000"
            >
            <p class="sse-note" id="object-image-hint">Envoi accepté jusqu’à 25 Mo ; au-delà de 5 Mo, compression automatique.</p>
            <p class="sse-note" id="object-image-status" hidden></p>
            <div class="object-image-preview" id="object-image-preview" hidden>
                <img id="object-image-preview-img" alt="Aperçu de l’image sélectionnée">
            </div>
        </div>
    </section>

    <footer class="sse-obj-create__actions">
        <button class="btn" type="submit">Créer et ouvrir dans une investigation</button>
        <a class="btn btn--ghost" href="<?= $h(url('atak/sse/operations')) ?>">Annuler</a>
    </footer>
</form>

<script>
(function () {
    var host = document.getElementById('object-meta-host');
    var form = document.getElementById('sse-object-create');

    function currentKind() {
        var checked = form && form.querySelector('input[name="kind"]:checked');
        return checked ? checked.value : '';
    }

    function syncMeta() {
        if (!host) return;
        var kind = currentKind();
        host.querySelectorAll('.object-meta').forEach(function (fs) {
            var on = fs.getAttribute('data-kind') === kind;
            fs.classList.toggle('is-active', on);
            fs.hidden = !on;
            fs.disabled = !on;
        });
        if (form) {
            form.querySelectorAll('.sse-obj-create__type').forEach(function (lab) {
                var input = lab.querySelector('input[type="radio"]');
                lab.classList.toggle('is-active', !!(input && input.checked));
            });
        }
    }

    if (form) {
        form.querySelectorAll('input[name="kind"]').forEach(function (radio) {
            radio.addEventListener('change', syncMeta);
        });
    }
    syncMeta();

    var fileInput = document.getElementById('object_image');
    var preview = document.getElementById('object-image-preview');
    var previewImg = document.getElementById('object-image-preview-img');
    var statusEl = document.getElementById('object-image-status');
    var TARGET = parseInt((fileInput && fileInput.getAttribute('data-sse-image-target-bytes')) || '5000000', 10);
    var UPLOAD_MAX = parseInt((fileInput && fileInput.getAttribute('data-sse-image-upload-max-bytes')) || '25000000', 10);
    var MAX_EDGE = 2048;

    function setStatus(msg, show) {
        if (!statusEl) return;
        statusEl.textContent = msg || '';
        statusEl.hidden = !show;
    }

    function formatMo(bytes) {
        return (bytes / 1000000).toFixed(1).replace('.0', '') + ' Mo';
    }

    function loadImage(file) {
        return new Promise(function (resolve, reject) {
            var url = URL.createObjectURL(file);
            var img = new Image();
            img.onload = function () {
                URL.revokeObjectURL(url);
                resolve(img);
            };
            img.onerror = function () {
                URL.revokeObjectURL(url);
                reject(new Error('read'));
            };
            img.src = url;
        });
    }

    function canvasToBlob(canvas, quality) {
        return new Promise(function (resolve) {
            canvas.toBlob(function (blob) { resolve(blob); }, 'image/jpeg', quality);
        });
    }

    async function compressImageFile(file) {
        var img = await loadImage(file);
        var w = img.naturalWidth || img.width;
        var h = img.naturalHeight || img.height;
        if (!w || !h) {
            throw new Error('size');
        }
        var edge = MAX_EDGE;
        var qualities = [0.88, 0.8, 0.72, 0.64, 0.55, 0.45, 0.36];
        var best = null;

        for (var pass = 0; pass < 7; pass++) {
            var scale = Math.min(1, edge / Math.max(w, h));
            var nw = Math.max(1, Math.round(w * scale));
            var nh = Math.max(1, Math.round(h * scale));
            var canvas = document.createElement('canvas');
            canvas.width = nw;
            canvas.height = nh;
            var ctx = canvas.getContext('2d');
            if (!ctx) {
                throw new Error('canvas');
            }
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, nw, nh);
            ctx.drawImage(img, 0, 0, nw, nh);

            for (var qi = 0; qi < qualities.length; qi++) {
                var blob = await canvasToBlob(canvas, qualities[qi]);
                if (!blob) continue;
                if (!best || blob.size < best.size) {
                    best = blob;
                }
                if (blob.size <= TARGET) {
                    return new File([blob], (file.name || 'image').replace(/\.[^.]+$/, '') + '.jpg', {
                        type: 'image/jpeg',
                        lastModified: Date.now()
                    });
                }
            }
            edge = Math.max(640, Math.floor(edge * 0.82));
        }

        if (best && best.size <= TARGET * 1.5) {
            return new File([best], (file.name || 'image').replace(/\.[^.]+$/, '') + '.jpg', {
                type: 'image/jpeg',
                lastModified: Date.now()
            });
        }
        throw new Error('too_heavy');
    }

    function showPreview(file) {
        if (!preview || !previewImg) return;
        var url = URL.createObjectURL(file);
        previewImg.onload = function () { URL.revokeObjectURL(url); };
        previewImg.src = url;
        preview.hidden = false;
    }

    if (fileInput && preview && previewImg) {
        fileInput.addEventListener('change', function () {
            var file = fileInput.files && fileInput.files[0];
            if (!file || !file.type || file.type.indexOf('image/') !== 0) {
                preview.hidden = true;
                previewImg.removeAttribute('src');
                setStatus('', false);
                return;
            }
            showPreview(file);
            if (file.size > UPLOAD_MAX) {
                setStatus('Cette image dépasse ' + formatMo(UPLOAD_MAX) + ' — choisissez un fichier plus léger.', true);
            } else if (file.size > TARGET) {
                setStatus('Image de ' + formatMo(file.size) + ' : compression automatique au moment de l’enregistrement.', true);
            } else {
                setStatus('', false);
            }
        });
    }

    if (form && fileInput) {
        form.addEventListener('submit', function (ev) {
            var file = fileInput.files && fileInput.files[0];
            if (!file || file.size <= TARGET) {
                return;
            }
            if (file.size > UPLOAD_MAX) {
                ev.preventDefault();
                setStatus('Image trop lourde (maximum ' + formatMo(UPLOAD_MAX) + ' à l’envoi).', true);
                return;
            }
            if (!window.File || !window.FileReader || !document.createElement('canvas').toBlob) {
                return;
            }
            ev.preventDefault();
            setStatus('Compression de l’image en cours…', true);
            var submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;

            compressImageFile(file).then(function (out) {
                var dt = new DataTransfer();
                dt.items.add(out);
                fileInput.files = dt.files;
                showPreview(out);
                setStatus('Image compressée : ' + formatMo(file.size) + ' → ' + formatMo(out.size) + '.', true);
                if (submitBtn) submitBtn.disabled = false;
                HTMLFormElement.prototype.submit.call(form);
            }).catch(function () {
                if (submitBtn) submitBtn.disabled = false;
                setStatus('Compression navigateur impossible — envoi au serveur pour traitement…', true);
                HTMLFormElement.prototype.submit.call(form);
            });
        });
    }
})();
</script>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
