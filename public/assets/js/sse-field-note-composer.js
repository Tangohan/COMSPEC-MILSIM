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
    var attachmentsMax = parseInt(form.getAttribute('data-attachments-max'), 10) || 4;
    var themesMax = parseInt(form.getAttribute('data-themes-max'), 10) || 4;

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
    }

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
        bodyField.addEventListener('input', refreshCounter);
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
        if (text === '') {
            event.preventDefault();
            showPane('redaction');
            if (bodyField) {
                bodyField.focus();
            }
            return;
        }
        if (selectedThemes().length === 0) {
            event.preventDefault();
            openSheet();
            return;
        }
        var submit = document.getElementById('fn-submit');
        if (submit) {
            submit.disabled = true;
        }
    });

    refreshContext();
    refreshCounter();
    renderAttachments();
}());
