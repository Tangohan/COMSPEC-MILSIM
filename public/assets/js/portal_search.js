/**
 * Recherche portail : saisie avec debounce, annulation des requêtes précédentes, rendu des sections.
 * Scopes alignés sur l’API / la palette : documents, forum, personnel, events, training, commands.
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
    var scopes = {
        documents: document.getElementById('scope-documents'),
        forum: document.getElementById('scope-forum'),
        personnel: document.getElementById('scope-personnel'),
        events: document.getElementById('scope-events'),
        training: document.getElementById('scope-training'),
        commands: document.getElementById('scope-commands'),
    };

    var debounceMs = 320;
    var timer = null;
    var seq = 0;
    var abortCtl = null;

    function esc(s) {
        var t = document.createElement('div');
        t.textContent = s == null ? '' : String(s);
        return t.innerHTML;
    }

    function scopeChecked(el) {
        return !!(el && !el.disabled && el.checked);
    }

    function anyScopeChecked() {
        return (
            scopeChecked(scopes.documents) ||
            scopeChecked(scopes.forum) ||
            scopeChecked(scopes.personnel) ||
            scopeChecked(scopes.events) ||
            scopeChecked(scopes.training) ||
            scopeChecked(scopes.commands)
        );
    }

    function buildQuery() {
        var p = new URLSearchParams();
        p.set('q', input && input.value ? input.value.trim() : '');
        Object.keys(scopes).forEach(function (key) {
            var el = scopes[key];
            if (!el || el.disabled) {
                p.set(key, '0');
            } else {
                p.set(key, el.checked ? '1' : '0');
            }
        });
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

    function hitRow(it, extraSub) {
        var subBits = [];
        if (it.subtitle) {
            subBits.push(esc(it.subtitle));
        }
        if (extraSub) {
            subBits.push(extraSub);
        }
        if (it.category) {
            subBits.push(esc(it.category));
        }
        if (it.author) {
            subBits.push(esc(it.author));
        }
        if (it.updated_at) {
            subBits.push(esc(String(it.updated_at).replace('T', ' ').slice(0, 16)));
        }
        var sub = subBits.length ? '<p class="mt-1 text-xs text-slate-500">' + subBits.join(' · ') + '</p>' : '';
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
    }

    function render(data) {
        if (!resultsEl) {
            return;
        }
        var docs = data.documents || [];
        var forum = data.forum || [];
        var pers = data.personnel || [];
        var events = data.events || [];
        var training = data.training || [];
        var commands = data.commands || [];
        var total = docs.length + forum.length + pers.length + events.length + training.length + commands.length;

        if (total === 0) {
            var hubU = typeof window.__portalHubUrl === 'string' ? window.__portalHubUrl.trim() : '';
            var searchU = typeof window.__portalSearchPageUrl === 'string' ? window.__portalSearchPageUrl.trim() : '';
            var links = '';
            if (hubU) {
                links +=
                    '<a href="' +
                    esc(hubU) +
                    '" class="inline-flex rounded-xl bg-slate-900 px-4 py-2 font-semibold text-white transition hover:bg-slate-800">Centre opérationnel</a>';
            }
            if (searchU) {
                links +=
                    '<a href="' +
                    esc(searchU) +
                    '" class="inline-flex rounded-xl border border-slate-300 bg-white px-4 py-2 font-semibold text-slate-800 transition hover:bg-slate-50">Recherche détaillée</a>';
            }
            resultsEl.innerHTML =
                '<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 px-6 py-14 text-center">' +
                '<p class="text-sm font-semibold text-slate-700">Aucun résultat pour cette recherche</p>' +
                '<p class="mt-2 text-sm text-slate-500">Essayez d’autres mots-clés, une fonction (radio, médic…) ou élargissez les sources cochées.</p>' +
                (links ? '<p class="mt-6 flex flex-wrap justify-center gap-3 text-sm">' + links + '</p>' : '') +
                '</div>';
            setLive('Aucun résultat');
            return;
        }

        var blocks = [];
        var icons = {
            docs: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>',
            forum: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3.819-2.24a9.015 9.015 0 0 1-5.801 0l-3.82 2.24v-3.09c-.34-.02-.68-.046-1.02-.072-1.132-.094-1.98-1.057-1.98-2.193V10.61c0-.97.616-1.813 1.5-2.097V6.75A2.25 2.25 0 0 1 4.5 4.5h15a2.25 2.25 0 0 1 2.25 2.25v1.761Z"/></svg>',
            pers: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>',
            events: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>',
            training: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.25c2.582 0 5.093-.33 7.492-.956.466-.135.937-.281 1.408-.434a60.44 60.44 0 0 0-.491-6.347m-15.918 0A61.5 61.5 0 0 1 12 9.75c2.582 0 5.093.33 7.492.956.466.135.937.281 1.408.434m-15.918 0A48.456 48.456 0 0 1 12 8.25c2.582 0 5.093.33 7.492.956"/></svg>',
            commands: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z"/></svg>',
        };

        if (commands.length) {
            blocks.push(section('Raccourcis', icons.commands, 'bg-amber-600', commands.map(function (it) { return hitRow(it); }).join('')));
        }
        if (docs.length) {
            blocks.push(section('Documents', icons.docs, 'bg-emerald-600', docs.map(function (it) { return hitRow(it); }).join('')));
        }
        if (forum.length) {
            blocks.push(section('Forum', icons.forum, 'bg-sky-600', forum.map(function (it) { return hitRow(it); }).join('')));
        }
        if (pers.length) {
            blocks.push(section('Personnel & fonctions', icons.pers, 'bg-indigo-600', pers.map(function (it) { return hitRow(it); }).join('')));
        }
        if (events.length) {
            blocks.push(section('Événements', icons.events, 'bg-rose-600', events.map(function (it) { return hitRow(it); }).join('')));
        }
        if (training.length) {
            blocks.push(section('Formations', icons.training, 'bg-violet-600', training.map(function (it) { return hitRow(it); }).join('')));
        }

        resultsEl.innerHTML = '<div class="space-y-6">' + blocks.join('') + '</div>';
        setLive(total + ' résultat' + (total > 1 ? 's' : ''));
    }

    function showSkeleton() {
        if (!resultsEl) {
            return;
        }
        var bar = function (w) {
            return '<div class="h-4 animate-pulse rounded-lg bg-slate-200" style="width:' + w + '%"></div>';
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
                    '<div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-8 text-center text-sm text-amber-900">Cochez au moins une source (documents, forum, personnel, événements, formations ou raccourcis) pour lancer la recherche.</div>';
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
                window.history.replaceState({}, '', uClear.pathname + (qsClear ? '?' + qsClear : '') + uClear.hash);
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

        setStatus(
            'loading',
            '<span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-sky-600"></span><span>Recherche en cours…</span>'
        );
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
                    window.history.replaceState({}, '', uSync.pathname + (qsSync ? '?' + qsSync : '') + uSync.hash);
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

    Object.keys(scopes).forEach(function (key) {
        var el = scopes[key];
        if (el) {
            el.addEventListener('change', schedule);
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
