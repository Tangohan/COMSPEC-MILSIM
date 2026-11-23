<?php
declare(strict_types=1);
ob_start();

use App\Support\SseCreationBrief;
use App\Support\SseWizardUi;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$type = (string) ($type ?? 'person');
$kinds = is_array($kinds ?? null) ? $kinds : [];
/** @var array<string, array{hint:string, fields:list<array<string,mixed>>}> $metaSchema */
$metaSchema = is_array($metaSchema ?? null) ? $metaSchema : [];
$classifications = is_array($classifications ?? null) ? $classifications : [];
$meshes = is_array($meshes ?? null) ? $meshes : [];
$cases = is_array($cases ?? null) ? $cases : [];
$maxLevel = (string) ($maxLevel ?? 'interne');
$classDefault = (string) ($classDefault ?? 'confidentiel');
$typeSeeded = (bool) ($typeSeeded ?? false);
if ($type === '' || !isset($kinds[$type])) {
    $type = array_key_first($kinds) ?: 'person';
}
$kindOptions = [];
foreach ($kinds as $k => $lab) {
    $kindOptions[$k] = [
        'label' => SseCreationBrief::OBJECT_KINDS[$k]['label'] ?? $lab,
        'desc' => SseCreationBrief::OBJECT_KINDS[$k]['desc'] ?? '',
    ];
}
$physicalWhen = 'kind=' . implode('|', SseCreationBrief::PHYSICAL_KINDS);
$stepTitles = ['Catégorie et état', 'Description', 'Rattachements', 'Classification et accès', 'Vérifier et créer'];
$wzCancelUrl = url('atak/sse/operations');
$wzSubmits = [['label' => 'Créer l’objet']];
$sseExtraStyles = '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse_wizard.css')) . '">';
?>

<div class="wz">
    <header class="wz-intro">
        <h1>Nouvel objet</h1>
        <p>Décrivez un élément réutilisable : identité, site, véhicule, pièce… Il sera placé dans une toile d’investigation, où vous pourrez le relier à d’autres éléments.</p>
    </header>

    <form method="post" action="<?= $h(url('atak/sse/objets/creer')) ?>" id="sse-object-create" data-sse-wizard data-wz-key="object" enctype="multipart/form-data" novalidate>
        <?= \App\Core\Csrf::field() ?>
        <?= SseWizardUi::progress($stepTitles) ?>
        <?php require __DIR__ . '/partials/wizard_banners.php'; ?>

        <section class="wz-step" data-wz-step aria-labelledby="wz-obj-t1">
            <h2 class="wz-step__title" id="wz-obj-t1" data-wz-step-title>Catégorie et état</h2>
            <p class="wz-step__lead">De quel genre d’élément s’agit-il ? La suite s’adapte à votre choix.</p>
            <div class="wz-alert" data-wz-alert role="alert" hidden>Un choix est nécessaire avant de continuer.</div>
            <?= SseWizardUi::cards('kind', 'Catégorie d’objet', $kindOptions, $type, [
                'required' => true,
                'compact' => true,
                'seeded' => $typeSeeded,
                'help' => 'Choisissez la catégorie la plus précise. Si rien ne convient, « Élément libre » accepte tout.',
                'error' => 'Choisissez la catégorie de l’objet.',
            ]) ?>
            <?= SseWizardUi::cards('meta[etat]', 'État de conservation', SseCreationBrief::OBJECT_STATES, 'non_verifie', [
                'compact' => true,
                'when' => $physicalWhen,
                'hint' => 'Utile pour un objet physique ou un support : il dit dans quel état il a été trouvé.',
            ]) ?>
        </section>

        <section class="wz-step" data-wz-step aria-labelledby="wz-obj-t2">
            <h2 class="wz-step__title" id="wz-obj-t2" data-wz-step-title>Description</h2>
            <p class="wz-step__lead">Renseignez ce qui est connu et laissez le reste vide.</p>
            <div class="wz-alert" data-wz-alert role="alert" hidden>Corrigez le champ signalé avant de continuer.</div>
            <p class="wz-reminder"><strong>Ce n’est pas une preuve.</strong> La fiche sert à organiser l’exploitation ; l’identification formelle reste une décision humaine.</p>
            <div class="wz-field" data-wz-field="label" data-wz-rules="required min:2">
                <label for="label">Libellé / désignation <span class="wz-flag wz-flag--req">obligatoire</span></label>
                <p class="wz-hint" id="label-hint">Le nom court sous lequel l’objet apparaîtra sur la toile.</p>
                <input id="label" name="label" type="text" maxlength="200" autocomplete="off"
                       placeholder="Ex. Identité 01 — convoyeur secteur Nord" aria-describedby="label-hint">
                <?= SseWizardUi::errors('label', [
                    'required' => 'Indiquez un libellé pour l’objet.',
                    'min' => 'Le libellé est trop court : écrivez au moins 2 caractères.',
                ]) ?>
            </div>

            <div class="object-meta-host" id="object-meta-host">
                <?php foreach ($metaSchema as $kindKey => $schema): ?>
                    <?php
                    $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
                    $hint = (string) ($schema['hint'] ?? '');
                    $active = $type === $kindKey;
                    ?>
                    <fieldset class="object-meta<?= $active ? ' is-active' : '' ?>" data-kind="<?= $h($kindKey) ?>"
                              data-wz-when="kind=<?= $h($kindKey) ?>"<?= $active ? '' : ' hidden disabled' ?>>
                        <legend>Caractéristiques — <?= $h($kinds[$kindKey] ?? $kindKey) ?></legend>
                        <?php if ($hint !== ''): ?>
                            <p class="wz-hint"><?= $h($hint) ?></p>
                        <?php endif; ?>
                        <div class="wz-grid">
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
                                ?>
                                <div class="wz-field<?= $ftype === 'textarea' ? ' object-meta-field--full' : '' ?>">
                                    <label for="<?= $h($fid) ?>"><?= $h($flabel) ?></label>
                                    <?php if ($ftype === 'select'): ?>
                                        <select id="<?= $h($fid) ?>" name="meta[<?= $h($fname) ?>]">
                                            <option value="">— Choisir —</option>
                                            <?php foreach ($options as $ok => $olab): ?>
                                                <option value="<?= $h($ok) ?>"><?= $h($olab) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($ftype === 'textarea'): ?>
                                        <textarea id="<?= $h($fid) ?>" name="meta[<?= $h($fname) ?>]" rows="<?= $rows ?>" maxlength="12000"
                                                  placeholder="<?= $h($placeholder) ?>"></textarea>
                                    <?php else: ?>
                                        <input id="<?= $h($fid) ?>" name="meta[<?= $h($fname) ?>]" type="text" maxlength="200"
                                               placeholder="<?= $h($placeholder) ?>">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                <?php endforeach; ?>
            </div>

            <div class="wz-field" data-wz-field="detail">
                <label for="detail">Précision libre <span class="wz-flag">facultatif</span></label>
                <textarea id="detail" name="detail" rows="3" maxlength="2000" placeholder="Complément non couvert par les champs ci-dessus"></textarea>
            </div>

            <div class="wz-field object-image-upload">
                <label class="wz-drop" for="object_image"><b>Joindre une image</b>
                    Photo, scan ou capture (facultatif). JPEG, PNG, WebP ou GIF ; les fichiers de plus de 5 Mo sont compressés automatiquement.</label>
                <input id="object_image" name="image" type="file"
                       accept="image/jpeg,image/png,image/webp,image/gif,.jpg,.jpeg,.png,.webp,.gif"
                       data-sse-image-target-bytes="5000000" data-sse-image-upload-max-bytes="25000000">
                <p class="wz-hint" id="object-image-hint">Envoi accepté jusqu’à 25 Mo.</p>
                <p class="wz-hint" id="object-image-status" role="status" hidden></p>
                <div class="object-image-preview" id="object-image-preview" hidden>
                    <img id="object-image-preview-img" alt="Aperçu de l’image sélectionnée">
                </div>
            </div>
        </section>

        <section class="wz-step" data-wz-step aria-labelledby="wz-obj-t3">
            <h2 class="wz-step__title" id="wz-obj-t3" data-wz-step-title>Rattachements</h2>
            <p class="wz-step__lead">Un objet vit dans une toile. Placez-le dans une toile existante, ou laissez le portail en ouvrir une pour lui.</p>
            <div class="wz-alert" data-wz-alert role="alert" hidden>Corrigez le champ signalé avant de continuer.</div>
            <?php if ($meshes === []): ?>
                <div class="wz-empty">Aucune toile ouverte n’est disponible : une nouvelle toile dédiée sera créée avec l’objet.</div>
            <?php else: ?>
                <div class="wz-field" data-wz-field="mesh_id">
                    <label for="mesh_id">Toile d’accueil <span class="wz-flag">facultatif</span></label>
                    <p class="wz-hint" id="mesh_id-hint">Par défaut, une nouvelle toile est créée pour l’objet.</p>
                    <select id="mesh_id" name="mesh_id" aria-describedby="mesh_id-hint">
                        <option value="">Nouvelle toile dédiée</option>
                        <?php foreach ($meshes as $m): ?>
                            <option value="<?= (int) $m['id'] ?>" data-class="<?= $h($m['classification'] ?? '') ?>">
                                <?= $h(($m['reference_code'] ?? '') . ' — ' . ($m['title'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <?php if ($cases !== []): ?>
                <div class="wz-field"<?= $meshes === [] ? '' : ' data-wz-when="mesh_id="' ?> data-wz-field="case_id">
                    <label for="case_id">Dossier d’affaire de la nouvelle toile <span class="wz-flag">facultatif</span></label>
                    <p class="wz-hint" id="case_id-hint">Seuls les dossiers de votre périmètre sont proposés.</p>
                    <select id="case_id" name="case_id" data-wz-follow aria-describedby="case_id-hint">
                        <option value="">Aucun</option>
                        <?php foreach ($cases as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" data-class="<?= $h($c['classification'] ?? '') ?>">
                                <?= $h(($c['reference_code'] ?? '') . ' — ' . ($c['title'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </section>

        <section class="wz-step" data-wz-step aria-labelledby="wz-obj-t4">
            <h2 class="wz-step__title" id="wz-obj-t4" data-wz-step-title>Classification et accès</h2>
            <p class="wz-step__lead">Le niveau de diffusion décide qui pourra ouvrir la toile qui contient l’objet, dans le périmètre de votre communauté.</p>
            <div class="wz-alert" data-wz-alert role="alert" hidden>Un choix est nécessaire avant de continuer.</div>
            <p class="wz-inherit" data-wz-when="mesh_id!=">L’objet rejoint une toile existante : il garde le niveau de diffusion de cette toile.</p>
            <?php
            $classLegend = 'Niveau de diffusion de la nouvelle toile';
            $classWhen = 'mesh_id=';
            require __DIR__ . '/partials/wizard_classification.php';
            ?>
        </section>

        <section class="wz-step" data-wz-step aria-labelledby="wz-obj-t5">
            <h2 class="wz-step__title" id="wz-obj-t5" data-wz-step-title>Vérifier et créer</h2>
            <p class="wz-step__lead">Relisez avant de créer. Chaque ligne se corrige en un clic.</p>
            <dl class="wz-sum">
                <div class="wz-sum__row" data-wz-from="kind"><dt>Catégorie</dt><dd data-wz-val></dd><button type="button" class="wz-linkbtn" data-wz-goto="1">Modifier</button></div>
                <div class="wz-sum__row" data-wz-from="meta[etat]" data-wz-row-when="<?= $h($physicalWhen) ?>"><dt>État de conservation</dt><dd data-wz-val></dd><button type="button" class="wz-linkbtn" data-wz-goto="1">Modifier</button></div>
                <div class="wz-sum__row" data-wz-from="label"><dt>Libellé</dt><dd data-wz-val></dd><button type="button" class="wz-linkbtn" data-wz-goto="2">Modifier</button></div>
                <div class="wz-sum__row" data-wz-from="detail"><dt>Précision</dt><dd data-wz-val></dd><button type="button" class="wz-linkbtn" data-wz-goto="2">Modifier</button></div>
                <div class="wz-sum__row" data-wz-from="image"><dt>Image</dt><dd data-wz-val></dd><button type="button" class="wz-linkbtn" data-wz-goto="2">Modifier</button></div>
                <?php if ($meshes !== []): ?>
                    <div class="wz-sum__row" data-wz-from="mesh_id"><dt>Toile d’accueil</dt><dd data-wz-val></dd><button type="button" class="wz-linkbtn" data-wz-goto="3">Modifier</button></div>
                <?php endif; ?>
                <?php if ($cases !== []): ?>
                    <div class="wz-sum__row" data-wz-from="case_id" data-wz-row-when="mesh_id="><dt>Dossier lié</dt><dd data-wz-val></dd><button type="button" class="wz-linkbtn" data-wz-goto="3">Modifier</button></div>
                <?php endif; ?>
                <div class="wz-sum__row" data-wz-from="classification" data-wz-row-when="mesh_id="><dt>Niveau de diffusion</dt><dd data-wz-val></dd><button type="button" class="wz-linkbtn" data-wz-goto="4">Modifier</button></div>
            </dl>
        </section>

        <?php require __DIR__ . '/partials/wizard_nav.php'; ?>
    </form>
</div>
<script src="<?= $h(asset_url('assets/js/sse-wizard.js')) ?>"></script>
<script>
(function () {
    var form = document.getElementById('sse-object-create');
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
