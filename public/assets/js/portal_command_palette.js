/**
 * Palette commande (Ctrl+K) : recherche portail + liens rapides.
 * Ouverture déléguée depuis navigation.js si #portal-command-palette est présent.
 */
(function () {
    var dlg = document.getElementById('portal-command-palette');
    if (!dlg || typeof dlg.showModal !== 'function') {
        return;
    }
    var apiUrl = dlg.getAttribute('data-api-url') || '';
    var input = document.getElementById('portal-command-palette-q');
    var resultsEl = document.getElementById('portal-command-palette-results');
    var debounceMs = 320;
    var timer = null;
    var seq = 0;
    var abortCtl = null;
    var minLen = 2;

    function esc(s) {
        var t = document.createElement('div');
        t.textContent = s == null ? '' : String(s);
        return t.innerHTML;
    }

    function showSkeleton() {
        if (!resultsEl) {
            return;
        }
        var bar = function (w) {
            return (
                '<div class="h-3 animate-pulse rounded-lg bg-slate-200" style="width:' + w + '%"></div>'
            );
        };
        resultsEl.innerHTML =
            '<div class="space-y-3 rounded-xl border border-slate-100 bg-slate-50/80 p-4">' +
            bar(45) +
            '<div class="space-y-2 pt-1">' +
            bar(100) +
            bar(80) +
            '</div></div>';
    }

    function render(data) {
        if (!resultsEl) {
            return;
        }
        var commands = data.commands || [];
        var docs = data.documents || [];
        var forum = data.forum || [];
        var pers = data.personnel || [];
        var events = data.events || [];
        var training = data.training || [];
        var total =
            commands.length + docs.length + forum.length + pers.length + events.length + training.length;
        if (total === 0) {
            resultsEl.innerHTML =
                '<div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-600">' +
                '<p class="font-semibold text-slate-800">Aucun résultat</p>' +
                '<p class="mt-2 text-xs text-slate-500">Affinez la saisie ou ouvrez la recherche en plein écran pour plus d’options.</p>' +
                '</div>';
            return;
        }
        var blocks = [];
        function rows(items, title) {
            if (!items.length) {
                return '';
            }
            var inner = items
                .map(function (it) {
                    var sub = it.subtitle
                        ? '<p class="mt-0.5 text-xs text-slate-500">' + esc(it.subtitle) + '</p>'
                        : it.excerpt
                          ? '<p class="mt-0.5 text-xs text-slate-500">' + esc(it.excerpt) + '</p>'
                          : '';
                    return (
                        '<li><a href="' +
                        esc(it.href) +
                        '" class="block rounded-lg px-3 py-2 transition hover:bg-sky-50/80">' +
                        '<p class="text-sm font-semibold text-slate-900">' +
                        esc(it.title) +
                        '</p>' +
                        sub +
                        '</a></li>'
                    );
                })
                .join('');
            return (
                '<section class="mb-3 rounded-xl border border-slate-100 bg-white p-2 shadow-sm">' +
                '<p class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">' +
                esc(title) +
                '</p><ul class="divide-y divide-slate-100">' +
                inner +
                '</ul></section>'
            );
        }
        blocks.push(rows(commands, 'Raccourcis'));
        blocks.push(rows(docs, 'Documents'));
        blocks.push(rows(forum, 'Forum'));
        blocks.push(rows(pers, 'Personnel'));
        blocks.push(rows(events, 'Événements'));
        blocks.push(rows(training, 'Formations'));
        resultsEl.innerHTML = blocks.join('');
    }

    function runSearch() {
        if (!apiUrl || !resultsEl) {
            return;
        }
        var q = input ? input.value.trim() : '';
        if (q.length < minLen) {
            if (q.length === 0) {
                resultsEl.innerHTML = '';
                return;
            }
            // Toujours proposer les raccourcis même avec une requête courte
            seq += 1;
            var mySeqShort = seq;
            if (abortCtl) {
                abortCtl.abort();
            }
            abortCtl = typeof AbortController !== 'undefined' ? new AbortController() : null;
            var shortUrl =
                apiUrl +
                (apiUrl.indexOf('?') >= 0 ? '&' : '?') +
                'q=' +
                encodeURIComponent(q) +
                '&commands=1&documents=0&forum=0&personnel=0&events=0&training=0';
            fetch(shortUrl, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                signal: abortCtl ? abortCtl.signal : undefined,
            })
                .then(function (res) {
                    return res.ok ? res.json() : Promise.reject();
                })
                .then(function (data) {
                    if (mySeqShort !== seq || !data || !data.success) {
                        return;
                    }
                    if ((data.commands || []).length) {
                        render(data);
                    } else {
                        resultsEl.innerHTML =
                            '<p class="px-2 py-6 text-center text-xs text-slate-500">Saisissez au moins ' +
                            minLen +
                            ' caractères.</p>';
                    }
                })
                .catch(function () {
                    if (mySeqShort !== seq) {
                        return;
                    }
                    resultsEl.innerHTML =
                        '<p class="px-2 py-6 text-center text-xs text-slate-500">Saisissez au moins ' +
                        minLen +
                        ' caractères.</p>';
                });
            return;
        }
        seq += 1;
        var mySeq = seq;
        if (abortCtl) {
            abortCtl.abort();
        }
        abortCtl = typeof AbortController !== 'undefined' ? new AbortController() : null;
        showSkeleton();
        var url =
            apiUrl +
            (apiUrl.indexOf('?') >= 0 ? '&' : '?') +
            'q=' +
            encodeURIComponent(q) +
            '&documents=1&forum=1&personnel=1&events=1&training=1&commands=1';
        fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            signal: abortCtl ? abortCtl.signal : undefined,
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP');
                }
                return res.json();
            })
            .then(function (data) {
                if (mySeq !== seq || !data || !data.success) {
                    throw new Error('bad');
                }
                render(data);
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') {
                    return;
                }
                if (mySeq !== seq) {
                    return;
                }
                resultsEl.innerHTML =
                    '<p class="px-2 py-6 text-center text-sm text-rose-700">Impossible de charger les résultats. Réessayez.</p>';
            });
    }

    function schedule() {
        if (timer) {
            clearTimeout(timer);
        }
        timer = window.setTimeout(runSearch, debounceMs);
    }

    document.addEventListener('portal-command-palette-open', function () {
        if (!dlg.open) {
            try {
                dlg.showModal();
            } catch (e) {
                return;
            }
        }
        if (input) {
            window.setTimeout(function () {
                input.focus();
                input.select();
            }, 0);
        }
        schedule();
    });

    dlg.addEventListener('cancel', function () {
        if (input) {
            input.value = '';
        }
        if (resultsEl) {
            resultsEl.innerHTML = '';
        }
    });

    dlg.addEventListener('close', function () {
        if (input) {
            input.value = '';
        }
        if (resultsEl) {
            resultsEl.innerHTML = '';
        }
    });

    if (input) {
        input.addEventListener('input', schedule);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                dlg.close();
            }
        });
    }

    document.querySelectorAll('[data-portal-command-palette-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            dlg.close();
        });
    });

    document.querySelectorAll('[data-portal-command-palette-open]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            document.dispatchEvent(new Event('portal-command-palette-open'));
        });
    });
})();
