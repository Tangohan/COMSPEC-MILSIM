/**
 * Rédacteur de fiche de renseignement simplifiée (portail SSE).
 *
 * Trois responsabilités : basculer entre rédaction et pièces jointes, tenir à
 * jour les étiquettes et le compteur de caractères, et rassembler les pièces
 * jointes choisies via les trois boutons ronds dans un seul envoi.
 */
(function () {
    'use strict';

    var form = document.getElementById('fn-form');
    if (!form) {
        return;
    }

    var stage = document.getElementById('fn-stage');
    var bodyField = document.getElementById('fn-body');
    var counter = document.getElementById('fn-counter');
    var sheet = document.getElementById('fn-sheet');
    var tagsPreview = document.getElementById('fn-tags-preview');
    var attachmentsList = document.getElementById('fn-attachments');
    var attachmentsEmpty = document.getElementById('fn-attachments-empty');
    var edgeLabel = document.getElementById('fn-edge-attachments');
    var titleLabel = document.getElementById('fn-attachments-title');
    var dateLabel = document.getElementById('fn-date-label');
    var timeLabel = document.getElementById('fn-time-label');
    var placeLabel = document.getElementById('fn-place-label');
    var observedField = document.getElementById('fn-observed');
    var placeField = document.getElementById('fn-place');

    var bodyMax = parseInt(form.getAttribute('data-body-max'), 10) || 1000;
    var bodyMin = parseInt(form.getAttribute('data-body-min'), 10) || 10;
    var attachmentsMax = parseInt(form.getAttribute('data-attachments-max'), 10) || 4;
    var themesMax = parseInt(form.getAttribute('data-themes-max'), 10) || 4;
    var submitButtons = [
        document.getElementById('fn-submit'),
        document.getElementById('fn-sheet-submit')
    ].filter(Boolean);

    var fileInputs = ['fn-file-camera', 'fn-file-gallery', 'fn-file-document']
        .map(function (id) { return document.getElementById(id); })
        .filter(Boolean);

    // ---------- Volets ----------

    function showPane(name) {
        if (!stage) {
            return;
        }
        stage.setAttribute('data-pane', name === 'pieces' ? 'pieces' : 'redaction');
        if (name !== 'pieces' && bodyField) {
            bodyField.focus({ preventScroll: true });
        }
    }

    Array.prototype.forEach.call(document.querySelectorAll('[data-fn-pane]'), function (button) {
        button.addEventListener('click', function () {
            showPane(button.getAttribute('data-fn-pane'));
        });
    });

    // ---------- Feuille de contexte ----------

    function openSheet() {
        if (!sheet) {
            return;
        }
        sheet.hidden = false;
        var first = sheet.querySelector('input, button');
        if (first) {
            first.focus({ preventScroll: true });
        }
    }

    function closeSheet() {
        if (!sheet) {
            return;
        }
        sheet.hidden = true;
        refreshContext();
        if (bodyField) {
            bodyField.focus({ preventScroll: true });
        }
    }

    Array.prototype.forEach.call(document.querySelectorAll('[data-fn-open="contexte"]'), function (button) {
        button.addEventListener('click', openSheet);
    });
    Array.prototype.forEach.call(document.querySelectorAll('[data-fn-close="contexte"]'), function (button) {
        button.addEventListener('click', closeSheet);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }
        if (sheet && !sheet.hidden) {
            closeSheet();
        } else if (stage && stage.getAttribute('data-pane') === 'pieces') {
            showPane('redaction');
        }
    });

    // ---------- Étiquettes, date, lieu ----------

    function selectedThemes() {
        return Array.prototype.filter.call(
            form.querySelectorAll('input[name="themes[]"]'),
            function (input) { return input.checked; }
        );
    }

    function refreshThemeLocks() {
        var reached = selectedThemes().length >= themesMax;
        Array.prototype.forEach.call(form.querySelectorAll('input[name="themes[]"]'), function (input) {
            var blocked = reached && !input.checked;
            input.disabled = blocked;
            if (input.parentElement) {
                input.parentElement.classList.toggle('is-blocked', blocked);
            }
        });
        var addButton = document.getElementById('fn-theme-custom-add');
        var addInput = document.getElementById('fn-theme-custom-label');
        if (addButton) {
            addButton.disabled = reached;
        }
        if (addInput) {
            addInput.disabled = reached;
        }
    }

    function refreshTags() {
        if (!tagsPreview) {
            return;
        }
        tagsPreview.innerHTML = '';

        selectedThemes().forEach(function (input) {
            var tag = document.createElement('span');
            tag.className = 'fn-tag fn-tag--' + (input.getAttribute('data-fn-theme-tone') || 'neutral');
            tag.textContent = input.getAttribute('data-fn-theme-label') || input.value;
            tagsPreview.appendChild(tag);
        });

        var kind = form.querySelector('input[name="note_kind"]:checked');
        if (kind) {
            var kindTag = document.createElement('span');
            kindTag.className = 'fn-tag fn-tag--kind';
            kindTag.textContent = kind.getAttribute('data-fn-kind-label') || kind.value;
            tagsPreview.appendChild(kindTag);
        }

        if (!tagsPreview.childNodes.length) {
            var empty = document.createElement('span');
            empty.className = 'fn-tag fn-tag--neutral';
            empty.textContent = 'THÈME À CHOISIR';
            tagsPreview.appendChild(empty);
        }
    }

    function refreshContext() {
        refreshThemeLocks();
        refreshTags();

        if (observedField && observedField.value) {
            var parsed = new Date(observedField.value);
            if (!isNaN(parsed.getTime())) {
                if (dateLabel) {
                    dateLabel.textContent = ('0' + parsed.getDate()).slice(-2)
                        + '/' + ('0' + (parsed.getMonth() + 1)).slice(-2)
                        + '/' + parsed.getFullYear();
                }
                if (timeLabel) {
                    timeLabel.textContent = ('0' + parsed.getHours()).slice(-2)
                        + ':' + ('0' + parsed.getMinutes()).slice(-2);
                }
            }
        }

        if (placeLabel) {
            var place = placeField && placeField.value ? placeField.value.trim() : '';
            placeLabel.textContent = place !== '' ? place.toUpperCase() : 'LIEU À PRÉCISER';
        }

        refreshSubmitState();
    }

    function isComplete() {
        var text = bodyField ? bodyField.value.trim() : '';
        if (text.length < bodyMin) {
            return false;
        }
        return selectedThemes().length > 0;
    }

    function refreshSubmitState() {
        var ok = isComplete();
        var hint = ok
            ? 'Transmettre la fiche'
            : 'Complétez le texte et choisissez au moins un thème pour transmettre.';
        submitButtons.forEach(function (button) {
            button.disabled = !ok;
            button.setAttribute('aria-disabled', ok ? 'false' : 'true');
            button.setAttribute('title', hint);
            button.setAttribute('aria-label', hint);
        });
    }

    function selectedTone() {
        var active = document.querySelector('.fn-tone-pick.is-selected');
        return active ? (active.getAttribute('data-tone') || 'neutral') : 'neutral';
    }

    function themeLabelKey(value) {
        return String(value || '').replace(/^c:(critical|warning|info|neutral):/i, '').trim().toLowerCase();
    }

    function addCustomTheme() {
        var input = document.getElementById('fn-theme-custom-label');
        var grid = document.getElementById('fn-theme-grid');
        var help = document.getElementById('fn-theme-custom-help');
        if (!input || !grid) {
            return;
        }
        var label = input.value.replace(/[:\[\]"]/g, ' ').replace(/\s+/g, ' ').trim();
        if (label.length < 2) {
            if (help) {
                help.textContent = 'Indiquez un intitulé d’au moins deux caractères.';
            }
            input.focus();
            return;
        }
        if (label.length > 40) {
            label = label.slice(0, 40);
        }
        if (selectedThemes().length >= themesMax) {
            if (help) {
                help.textContent = 'Quatre thèmes au maximum. Décochez-en un pour en ajouter un autre.';
            }
            return;
        }

        var duplicate = Array.prototype.some.call(form.querySelectorAll('input[name="themes[]"]'), function (existing) {
            var existingLabel = existing.getAttribute('data-fn-theme-label') || existing.value;
            return themeLabelKey(existingLabel) === label.toLowerCase()
                || themeLabelKey(existing.value) === label.toLowerCase();
        });
        if (duplicate) {
            if (help) {
                help.textContent = 'Ce thème est déjà dans la liste.';
            }
            return;
        }

        var tone = selectedTone();
        var code = 'c:' + tone + ':' + label;
        var pill = document.createElement('label');
        pill.className = 'fn-theme fn-theme--custom fn-tone-' + tone;

        var checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.name = 'themes[]';
        checkbox.value = code;
        checkbox.checked = true;
        checkbox.setAttribute('data-fn-theme-label', label.toUpperCase());
        checkbox.setAttribute('data-fn-theme-tone', tone);

        var caption = document.createElement('span');
        caption.textContent = label;

        pill.appendChild(checkbox);
        pill.appendChild(caption);
        grid.appendChild(pill);

        input.value = '';
        if (help) {
            help.textContent = 'Créez un thème propre à la mission s’il manque dans la liste. Quatre thèmes au total.';
        }
        refreshContext();
    }

    var addThemeButton = document.getElementById('fn-theme-custom-add');
    var addThemeInput = document.getElementById('fn-theme-custom-label');
    if (addThemeButton) {
        addThemeButton.addEventListener('click', addCustomTheme);
    }
    if (addThemeInput) {
        addThemeInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                addCustomTheme();
            }
        });
    }
    Array.prototype.forEach.call(document.querySelectorAll('.fn-tone-pick'), function (pick) {
        pick.addEventListener('click', function () {
            Array.prototype.forEach.call(document.querySelectorAll('.fn-tone-pick'), function (other) {
                var on = other === pick;
                other.classList.toggle('is-selected', on);
                other.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
        });
    });

    form.addEventListener('change', function (event) {
        var target = event.target;
        if (!target || !target.name) {
            return;
        }
        if (target.name === 'themes[]' || target.name === 'note_kind') {
            refreshContext();
        }
    });

    if (observedField) {
        observedField.addEventListener('change', refreshContext);
    }
    if (placeField) {
        placeField.addEventListener('input', refreshContext);
    }

    // ---------- Compteur ----------

    function refreshCounter() {
        if (!bodyField || !counter) {
            return;
        }
        var length = bodyField.value.length;
        counter.textContent = length + '/' + bodyMax;
        counter.classList.toggle('is-ok', length > 0 && length < bodyMax);
        counter.classList.toggle('is-full', length >= bodyMax);
    }

    if (bodyField) {
        bodyField.addEventListener('input', function () {
            refreshCounter();
            refreshSubmitState();
        });
    }

    // ---------- Pièces jointes ----------

    var picked = [];

    function humanSize(bytes) {
        if (!bytes) {
            return '';
        }
        if (bytes < 1024) {
            return bytes + ' o';
        }
        if (bytes < 1024 * 1024) {
            return Math.round(bytes / 1024) + ' Ko';
        }
        return (bytes / (1024 * 1024)).toFixed(1).replace('.', ',') + ' Mo';
    }

    function syncFileInputs() {
        // Un seul champ porte la totalité des pièces retenues : les trois boutons
        // ne sont que des raccourcis de sélection.
        if (typeof DataTransfer === 'undefined' || !fileInputs.length) {
            return;
        }
        var bag = new DataTransfer();
        picked.forEach(function (entry) {
            bag.items.add(entry.file);
        });
        fileInputs[0].files = bag.files;
        fileInputs.slice(1).forEach(function (input) {
            input.files = new DataTransfer().files;
        });
    }

    function renderAttachments() {
        if (!attachmentsList) {
            return;
        }
        attachmentsList.innerHTML = '';

        picked.forEach(function (entry, index) {
            var item = document.createElement('li');
            item.className = 'fn-attachment';

            if (entry.file.type.indexOf('image/') === 0 && window.URL && window.URL.createObjectURL) {
                var img = document.createElement('img');
                img.className = 'fn-attachment-preview';
                img.alt = '';
                img.src = window.URL.createObjectURL(entry.file);
                item.appendChild(img);
            } else {
                var doc = document.createElement('div');
                doc.className = 'fn-attachment-doc';
                doc.textContent = 'Document';
                item.appendChild(doc);
            }

            var name = document.createElement('span');
            name.className = 'fn-attachment-name';
            name.textContent = entry.file.name;
            item.appendChild(name);

            var meta = document.createElement('span');
            meta.className = 'fn-attachment-meta';
            meta.textContent = humanSize(entry.file.size);
            item.appendChild(meta);

            var drop = document.createElement('button');
            drop.type = 'button';
            drop.className = 'fn-attachment-drop';
            drop.textContent = 'Retirer';
            drop.addEventListener('click', function () {
                picked.splice(index, 1);
                syncFileInputs();
                renderAttachments();
            });
            item.appendChild(drop);

            attachmentsList.appendChild(item);
        });

        var label = '(' + picked.length + '/' + attachmentsMax + ')';
        if (titleLabel) {
            titleLabel.textContent = label;
        }
        if (edgeLabel) {
            edgeLabel.textContent = 'Pièce(s) jointe(s) ' + label;
        }
        if (attachmentsEmpty) {
            attachmentsEmpty.hidden = picked.length > 0;
        }
    }

    Array.prototype.forEach.call(document.querySelectorAll('[data-fn-file]'), function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.getAttribute('data-fn-file'));
            if (input) {
                input.click();
            }
        });
    });

    fileInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            Array.prototype.forEach.call(input.files || [], function (file) {
                if (picked.length >= attachmentsMax) {
                    return;
                }
                var already = picked.some(function (entry) {
                    return entry.file.name === file.name && entry.file.size === file.size;
                });
                if (!already) {
                    picked.push({ file: file });
                }
            });
            syncFileInputs();
            renderAttachments();
            showPane('pieces');
        });
    });

    // ---------- Plein écran ----------

    var fullscreenButton = document.getElementById('fn-fullscreen');
    if (fullscreenButton) {
        fullscreenButton.addEventListener('click', function () {
            var root = document.documentElement;
            if (document.fullscreenElement) {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
            } else if (root.requestFullscreen) {
                root.requestFullscreen().catch(function () {
                    // Refus du navigateur : la page reste utilisable telle quelle.
                });
            }
        });
    }

    // ---------- Position ----------

    var locateButton = document.getElementById('fn-locate');
    var locateStatus = document.getElementById('fn-locate-status');
    if (locateButton) {
        locateButton.addEventListener('click', function () {
            if (!navigator.geolocation) {
                if (locateStatus) {
                    locateStatus.textContent = 'Votre navigateur ne donne pas la position. Saisissez le repère à la main.';
                }
                return;
            }
            locateButton.disabled = true;
            if (locateStatus) {
                locateStatus.textContent = 'Relevé de la position en cours…';
            }
            navigator.geolocation.getCurrentPosition(function (position) {
                var lat = document.getElementById('fn-lat');
                var lng = document.getElementById('fn-lng');
                if (lat) {
                    lat.value = position.coords.latitude.toFixed(6);
                }
                if (lng) {
                    lng.value = position.coords.longitude.toFixed(6);
                }
                locateButton.disabled = false;
                if (locateStatus) {
                    locateStatus.textContent = 'Position relevée : elle accompagnera la fiche.';
                }
            }, function () {
                locateButton.disabled = false;
                if (locateStatus) {
                    locateStatus.textContent = 'Position refusée ou indisponible. Saisissez le repère à la main.';
                }
            }, { enableHighAccuracy: true, timeout: 8000 });
        });
    }

    // ---------- Envoi ----------

    form.addEventListener('submit', function (event) {
        var text = bodyField ? bodyField.value.trim() : '';
        if (text.length < bodyMin) {
            event.preventDefault();
            showPane('redaction');
            if (bodyField) {
                bodyField.focus();
            }
            refreshSubmitState();
            return;
        }
        if (selectedThemes().length === 0) {
            event.preventDefault();
            openSheet();
            refreshSubmitState();
            return;
        }
        submitButtons.forEach(function (button) {
            button.disabled = true;
            button.classList.add('is-sending');
        });
    });

    refreshContext();
    refreshCounter();
    renderAttachments();
}());
