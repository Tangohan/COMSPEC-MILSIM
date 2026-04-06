/**
 * Recherche portail : saisie avec debounce, annulation des requêtes précédentes, rendu des sections.
 */
(function () {
    var root = document.getElementById('portal-search-root');
    if (!root) {
        return;
    }

    var apiUrl = root.getAttribute('data-api-url') || '';
    var initialQ = root.getAttribute('data-initial-q') || '';
    var minLen = parseInt(root.getAttribute('data-min-length') || '2', 10) || 2;

    var input = document.getElementById('global-search');
    var form = document.getElementById('portal-search-form');
    var statusEl = document.getElementById('portal-search-status');
    var liveEl = document.getElementById('portal-search-live');
    var resultsEl = document.getElementById('portal-search-results');
    var emptyHint = document.getElementById('portal-search-empty-hint');
    var cbDoc = document.getElementById('scope-documents');
    var cbForum = document.getElementById('scope-forum');
    var cbPers = document.getElementById('scope-personnel');

    var debounceMs = 320;
    var timer = null;
    var seq = 0;
    var abortCtl = null;

    function esc(s) {
        var t = document.createElement('div');
        t.textContent = s == null ? '' : String(s);
        return t.innerHTML;
    }

    function anyScopeChecked() {
        var d = cbDoc && !cbDoc.disabled && cbDoc.checked;
        var f = cbForum && cbForum.checked;
        var p = cbPers && !cbPers.disabled && cbPers.checked;
        return d || f || p;
    }

    function buildQuery() {
        var p = new URLSearchParams();
        p.set('q', (input && input.value) ? input.value.trim() : '');
        if (cbDoc && !cbDoc.disabled) {
            p.set('documents', cbDoc.checked ? '1' : '0');
        } else {
            p.set('documents', '0');
        }
        if (cbForum) {
            p.set('forum', cbForum.checked ? '1' : '0');
        }
        if (cbPers && !cbPers.disabled) {
            p.set('personnel', cbPers.checked ? '1' : '0');
        } else {
            p.set('personnel', '0');
        }
        return p.toString();
    }

    function setStatus(kind, text) {
        if (!statusEl) {
            return;
        }
        statusEl.className =
            'mt-4 flex items-center gap-2 text-sm ' +
            (kind === 'error' ? 'text-rose-700' : kind === 'loading' ? 'text-slate-500' : 'text-slate-600');
        statusEl.innerHTML = text;
        statusEl.setAttribute('aria-live', 'polite');
    }

    function setLive(text) {
        if (liveEl) {
            liveEl.textContent = text;
        }
    }

    function render(data) {
        if (!resultsEl) {
            return;
        }
        var docs = data.documents || [];
        var forum = data.forum || [];
        var pers = data.personnel || [];
        var total = docs.length + forum.length + pers.length;

        if (total === 0) {
            resultsEl.innerHTML =
                '<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 px-6 py-14 text-center">' +
                '<p class="text-sm font-semibold text-slate-700">Aucun résultat pour cette recherche</p>' +
                '<p class="mt-2 text-sm text-slate-500">Essayez d’autres mots-clés ou élargissez les sources cochées.</p>' +
                '</div>';
            setLive('Aucun résultat');
            return;
        }

        var blocks = [];

        function section(title, icon, accent, inner) {
            return (
                '<section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">' +
                '<div class="flex items-center gap-2 border-b border-slate-100 bg-slate-50/80 px-4 py-3">' +
                '<span class="inline-flex h-8 w-8 items-center justify-center rounded-xl ' +
                accent +
                ' text-white [&>svg]:h-4 [&>svg]:w-4">' +
                icon +
                '</span>' +
                '<h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-600">' +
                esc(title) +
                '</h2>' +
                '</div>' +
                '<ul class="divide-y divide-slate-100" role="list">' +
                inner +
                '</ul>' +
                '</section>'
            );
        }

        if (docs.length) {
            var rows = docs
                .map(function (it) {
                    var meta = [];
                    if (it.category) {
                        meta.push(esc(it.category));
                    }
                    if (it.updated_at) {
                        meta.push(esc(String(it.updated_at).replace('T', ' ').slice(0, 16)));
                    }
                    var sub = meta.length ? '<p class="mt-1 text-xs text-slate-500">' + meta.join(' · ') + '</p>' : '';
                    return (
                        '<li>' +
                        '<a href="' +
                        esc(it.href) +
                        '" class="block px-4 py-3 transition hover:bg-sky-50/80 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-sky-500">' +
                        '<p class="text-sm font-semibold text-slate-900">' +
                        esc(it.title) +
                        '</p>' +
                        (it.excerpt
                            ? '<p class="mt-1 max-h-[2.75rem] overflow-hidden text-sm leading-snug text-slate-600">' +
                              esc(it.excerpt) +
                              '</p>'
                            : '') +
                        sub +
                        '</a>' +
                        '</li>'
                    );
                })
                .join('');
            blocks.push(
                section(
                    'Documents',
                    '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>',
                    'bg-emerald-600',
                    rows
                )
            );
        }

        if (forum.length) {
            var rowsF = forum
                .map(function (it) {
                    var bits = [];
                    if (it.category) {
                        bits.push(esc(it.category));
                    }
                    if (it.author) {
                        bits.push(esc(it.author));
                    }
                    var sub = bits.length ? '<p class="mt-1 text-xs text-slate-500">' + bits.join(' · ') + '</p>' : '';
                    return (
                        '<li>' +
                        '<a href="' +
                        esc(it.href) +
                        '" class="block px-4 py-3 transition hover:bg-sky-50/80 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-sky-500">' +
                        '<p class="text-sm font-semibold text-slate-900">' +
                        esc(it.title) +
                        '</p>' +
                        sub +
                        '</a>' +
                        '</li>'
                    );
                })
                .join('');
            blocks.push(
                section(
                    'Forum',
                    '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3.819-2.24a9.015 9.015 0 0 1-5.801 0l-3.82 2.24v-3.09c-.34-.02-.68-.046-1.02-.072-1.132-.094-1.98-1.057-1.98-2.193V10.61c0-.97.616-1.813 1.5-2.097V6.75A2.25 2.25 0 0 1 4.5 4.5h15a2.25 2.25 0 0 1 2.25 2.25v1.761Z"/></svg>',
                    'bg-sky-600',
                    rowsF
                )
            );
        }

        if (pers.length) {
            var rowsP = pers
                .map(function (it) {
                    var sub = it.subtitle
                        ? '<p class="mt-1 text-xs font-medium text-sky-800">' + esc(it.subtitle) + '</p>'
                        : '';
                    return (
                        '<li>' +
                        '<a href="' +
                        esc(it.href) +
                        '" class="block px-4 py-3 transition hover:bg-sky-50/80 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-sky-500">' +
                        '<p class="text-sm font-semibold text-slate-900">' +
                        esc(it.title) +
                        '</p>' +
                        sub +
                        '</a>' +
                        '</li>'
                    );
                })
                .join('');
            blocks.push(
                section(
                    'Personnel',
                    '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>',
                    'bg-indigo-600',
                    rowsP
                )
            );
        }

        resultsEl.innerHTML = '<div class="space-y-6">' + blocks.join('') + '</div>';
        setLive(total + ' résultat' + (total > 1 ? 's' : ''));
    }

    function showSkeleton() {
        if (!resultsEl) {
            return;
        }
        var bar = function (w) {
            return (
                '<div class="h-4 animate-pulse rounded-lg bg-slate-200" style="width:' +
                w +
                '%"></div>'
            );
        };
        resultsEl.innerHTML =
            '<div class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">' +
            bar(40) +
            '<div class="space-y-2 pt-2">' +
            bar(100) +
            bar(85) +
            bar(60) +
            '</div></div>';
    }

    function runSearch() {
        if (!apiUrl) {
            return;
        }
        if (!anyScopeChecked()) {
            if (resultsEl) {
                resultsEl.innerHTML =
                    '<div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-8 text-center text-sm text-amber-900">Cochez au moins une source (documents, forum ou personnel) pour lancer la recherche.</div>';
            }
            setStatus('', '');
            setLive('');
            return;
        }

        var q = input ? input.value.trim() : '';
        if (q.length < minLen) {
            try {
                var uClear = new URL(window.location.href);
                uClear.searchParams.delete('q');
                var qsClear = uClear.searchParams.toString();
                window.history.replaceState(
                    {},
                    '',
                    uClear.pathname + (qsClear ? '?' + qsClear : '') + uClear.hash
                );
            } catch (e1) {}
            if (resultsEl) {
                resultsEl.innerHTML = '';
            }
            if (q.length === 0) {
                setStatus('', '');
                if (emptyHint) {
                    emptyHint.classList.remove('hidden');
                }
            } else {
                setStatus(
                    '',
                    '<span class="inline-flex h-2 w-2 rounded-full bg-amber-400"></span><span>Saisissez au moins ' +
                        minLen +
                        ' caractères pour afficher les résultats.</span>'
                );
                if (emptyHint) {
                    emptyHint.classList.add('hidden');
                }
            }
            setLive('');
            return;
        }
        if (emptyHint) {
            emptyHint.classList.add('hidden');
        }

        seq += 1;
        var mySeq = seq;
        if (abortCtl) {
            abortCtl.abort();
        }
        abortCtl = typeof AbortController !== 'undefined' ? new AbortController() : null;

        setStatus('loading', '<span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-sky-600"></span><span>Recherche en cours…</span>');
        showSkeleton();

        var url = apiUrl + (apiUrl.indexOf('?') >= 0 ? '&' : '?') + buildQuery();
        fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            signal: abortCtl ? abortCtl.signal : undefined,
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.json();
            })
            .then(function (data) {
                if (mySeq !== seq) {
                    return;
                }
                if (!data || !data.success) {
                    throw new Error((data && data.error) || 'Erreur');
                }
                try {
                    var uSync = new URL(window.location.href);
                    uSync.searchParams.set('q', q);
                    var qsSync = uSync.searchParams.toString();
                    window.history.replaceState(
                        {},
                        '',
                        uSync.pathname + (qsSync ? '?' + qsSync : '') + uSync.hash
                    );
                } catch (e2) {}
                setStatus('', '');
                render(data);
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') {
                    return;
                }
                if (mySeq !== seq) {
                    return;
                }
                setStatus('error', '<span class="text-rose-600">Impossible de charger les résultats. Réessayez.</span>');
                if (resultsEl) {
                    resultsEl.innerHTML = '';
                }
            });
    }

    function schedule() {
        if (timer) {
            clearTimeout(timer);
        }
        timer = window.setTimeout(runSearch, debounceMs);
    }

    if (input) {
        input.addEventListener('input', schedule);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                input.value = '';
                schedule();
            }
        });
    }

    [cbDoc, cbForum, cbPers].forEach(function (el) {
        if (el) {
            el.addEventListener('change', function () {
                schedule();
            });
        }
    });

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (timer) {
                clearTimeout(timer);
            }
            runSearch();
        });
    }

    if (input && initialQ) {
        input.value = initialQ;
    }

    window.setTimeout(function () {
        if (input && initialQ && initialQ.trim().length >= minLen) {
            runSearch();
        }
    }, 0);

    if (input) {
        input.focus();
    }
})();
