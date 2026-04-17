<?php
declare(strict_types=1);
if (!\App\Core\Session::get('user_id')) {
    return;
}
$phEndpoint = url('api/community/report');
$phSearchEndpoint = url('api/portal/search');
$phCsrf = \App\Core\Csrf::token();
?>
<div id="portal-help-modal" class="fixed inset-0 z-[502] hidden flex items-center justify-center p-4 bg-slate-900/55 backdrop-blur-[2px]" role="dialog" aria-modal="true" aria-labelledby="portal-help-title">
    <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/25 overflow-hidden max-h-[min(90dvh,700px)] flex flex-col">
        <div class="px-5 py-4 border-b border-slate-100 bg-rose-50/90 shrink-0">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-600 text-xs font-black text-white shadow-sm" aria-hidden="true">?</span>
                <div class="min-w-0">
                    <h2 id="portal-help-title" class="text-sm font-black uppercase tracking-wide text-rose-950">Aide et signalement</h2>
                    <p class="mt-1 text-xs text-rose-900/80 leading-relaxed">Votre message est transmis aux administrateurs et modérateurs de la communauté. Décrivez le problème avec précision.</p>
                </div>
            </div>
        </div>
        <form id="portal-help-form" class="p-5 space-y-4 overflow-y-auto flex-1 min-h-0">
            <input type="hidden" name="target_type" value="portal_help">
            <input type="hidden" name="target_id" value="0">
            <input type="hidden" id="ph-selected-url" name="selected_target_url" value="">
            <input type="hidden" id="ph-selected-kind" name="selected_target_kind" value="">
            <input type="hidden" id="ph-selected-member-id" name="selected_member_id" value="0">
            <input type="hidden" id="ph-selected-member-label" name="selected_member_label" value="">
            <div>
                <label for="ph-subject" class="block text-xs font-bold text-slate-700 mb-1.5">Sujet</label>
                <select id="ph-subject" name="help_subject" required class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-900 bg-white">
                    <option value="">Choisir…</option>
                    <option value="profile">Fiche ou profil</option>
                    <option value="page_content">Contenu affiché sur une page</option>
                    <option value="message">Message ou discussion</option>
                    <option value="user_account">Compte ou personne</option>
                    <option value="other">Autre</option>
                </select>
            </div>
            <div>
                <label for="ph-target-search" class="block text-xs font-bold text-slate-700 mb-1.5">Élément concerné <span class="font-normal text-slate-500">(recherche membres, pages, discussions)</span></label>
                <input type="search" id="ph-target-search" autocomplete="off" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400" placeholder="Rechercher un membre, une page, ou coller une URL du site…">
                <div id="ph-target-chip" class="mt-2 hidden rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-900"></div>
                <div id="ph-target-results" class="mt-2 hidden max-h-44 overflow-y-auto rounded-xl border border-slate-200 bg-white"></div>
                <p id="ph-target-help" class="mt-1 text-[11px] text-slate-500">Astuce : vous pouvez aussi coller une URL interne du site.</p>
            </div>
            <div>
                <label for="ph-reference" class="block text-xs font-bold text-slate-700 mb-1.5">Repère utile <span class="font-normal text-slate-500">(optionnel)</span></label>
                <input type="text" id="ph-reference" name="reference_note" maxlength="500" autocomplete="off" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400" placeholder="Ex. contexte, pseudo secondaire, heure, endroit exact…">
            </div>
            <div>
                <label for="ph-reason" class="block text-xs font-bold text-slate-700 mb-1.5">Motif</label>
                <select id="ph-reason" name="reason" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-900 bg-white">
                    <option value="inappropriate">Contenu inapproprié</option>
                    <option value="harassment">Harcèlement</option>
                    <option value="spam">Spam ou publicité abusive</option>
                    <option value="suspicious_link">Lien ou pièce douteuse</option>
                    <option value="illegal">Contenu illégal</option>
                    <option value="other">Autre</option>
                </select>
            </div>
            <div>
                <label for="ph-details" class="block text-xs font-bold text-slate-700 mb-1.5">Votre message</label>
                <textarea id="ph-details" name="details" rows="5" maxlength="2000" required class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400" placeholder="Expliquez la situation pour que l’équipe puisse agir."></textarea>
            </div>
            <p class="text-[11px] text-slate-500 leading-relaxed">La page où vous vous trouvez est indiquée automatiquement. Les échanges restent confidentiels côté modération.</p>
            <div class="flex flex-wrap gap-2 justify-end pt-1 border-t border-slate-100">
                <button type="button" id="ph-cancel" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold uppercase tracking-wide text-slate-700 hover:bg-slate-50">Annuler</button>
                <button type="submit" class="rounded-xl bg-rose-600 px-4 py-2.5 text-xs font-bold uppercase tracking-wide text-white hover:bg-rose-500 shadow-sm">Envoyer</button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById('portal-help-modal');
    var form = document.getElementById('portal-help-form');
    if (!modal || !form) return;
    var endpoint = <?= json_encode($phEndpoint, JSON_UNESCAPED_SLASHES) ?>;
    var searchEndpoint = <?= json_encode($phSearchEndpoint, JSON_UNESCAPED_SLASHES) ?>;
    var csrf = <?= json_encode($phCsrf, JSON_UNESCAPED_SLASHES) ?>;

    var searchInput = document.getElementById('ph-target-search');
    var resultsBox = document.getElementById('ph-target-results');
    var chip = document.getElementById('ph-target-chip');
    var hiddenUrl = document.getElementById('ph-selected-url');
    var hiddenKind = document.getElementById('ph-selected-kind');
    var hiddenMemberId = document.getElementById('ph-selected-member-id');
    var hiddenMemberLabel = document.getElementById('ph-selected-member-label');
    var searchTimer = null;
    var searchTicket = 0;

    function resetSelection() {
        if (hiddenUrl) hiddenUrl.value = '';
        if (hiddenKind) hiddenKind.value = '';
        if (hiddenMemberId) hiddenMemberId.value = '0';
        if (hiddenMemberLabel) hiddenMemberLabel.value = '';
        if (chip) {
            chip.innerHTML = '';
            chip.classList.add('hidden');
        }
    }

    function setSelection(item) {
        if (hiddenUrl) hiddenUrl.value = item.url || '';
        if (hiddenKind) hiddenKind.value = item.kind || '';
        if (hiddenMemberId) hiddenMemberId.value = item.memberId ? String(item.memberId) : '0';
        if (hiddenMemberLabel) hiddenMemberLabel.value = item.memberLabel || '';
        if (chip) {
            chip.innerHTML = '<span class="font-semibold">Ciblé :</span> ' + escapeHtml(item.label || '')
                + ' <button type="button" id="ph-clear-target" class="ml-2 rounded border border-emerald-300 px-2 py-0.5 text-[10px] font-bold uppercase hover:bg-emerald-100">Retirer</button>';
            chip.classList.remove('hidden');
            var clearBtn = document.getElementById('ph-clear-target');
            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    resetSelection();
                    if (searchInput) searchInput.focus();
                });
            }
        }
    }

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = value || '';
        return div.innerHTML;
    }

    function isSameSiteUrl(url) {
        try {
            var u = new URL(url, window.location.origin);
            return u.origin === window.location.origin;
        } catch (_e) {
            return false;
        }
    }

    function normalizeUrl(url) {
        try {
            return new URL(url, window.location.origin).toString();
        } catch (_e) {
            return '';
        }
    }

    function renderResults(items) {
        if (!resultsBox) return;
        if (!items || !items.length) {
            resultsBox.innerHTML = '<p class="px-3 py-2 text-xs text-slate-500">Aucun résultat. Essayez un autre mot-clé.</p>';
            resultsBox.classList.remove('hidden');
            return;
        }
        var html = items.map(function (item, idx) {
            var subtitle = item.subtitle ? '<p class="text-[11px] text-slate-500">' + escapeHtml(item.subtitle) + '</p>' : '';
            return '<button type="button" class="block w-full border-b last:border-b-0 border-slate-100 px-3 py-2 text-left hover:bg-slate-50" data-ph-select="' + idx + '">'
                + '<p class="text-xs font-semibold text-slate-900">' + escapeHtml(item.label) + '</p>'
                + subtitle
                + '</button>';
        }).join('');
        resultsBox.innerHTML = html;
        resultsBox.classList.remove('hidden');
        Array.prototype.forEach.call(resultsBox.querySelectorAll('[data-ph-select]'), function (btn) {
            btn.addEventListener('click', function () {
                var i = parseInt(btn.getAttribute('data-ph-select') || '-1', 10);
                if (i < 0 || !items[i]) return;
                setSelection(items[i]);
                resultsBox.classList.add('hidden');
                if (searchInput) {
                    searchInput.value = items[i].label;
                }
            });
        });
    }

    function searchTargets(raw) {
        var query = (raw || '').trim();
        if (!resultsBox) return;

        var manualItems = [];
        if (query !== '' && isSameSiteUrl(query)) {
            var normalized = normalizeUrl(query);
            manualItems.push({
                label: 'URL du site : ' + normalized,
                subtitle: 'Lien direct saisi manuellement',
                url: normalized,
                kind: 'manual_url',
                memberId: 0,
                memberLabel: ''
            });
        }

        if (query.length < 2) {
            if (manualItems.length > 0) {
                renderResults(manualItems);
            } else {
                resultsBox.classList.add('hidden');
                resultsBox.innerHTML = '';
            }
            return;
        }

        searchTicket += 1;
        var ticket = searchTicket;
        var url = searchEndpoint + '?q=' + encodeURIComponent(query) + '&documents=1&forum=1&personnel=1';
        fetch(url, {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        }).then(function (r) {
            if (!r.ok) throw new Error('search_failed');
            return r.json();
        }).then(function (data) {
            if (ticket !== searchTicket) return;
            var items = manualItems.slice();
            var personnel = Array.isArray(data.personnel) ? data.personnel : [];
            var documents = Array.isArray(data.documents) ? data.documents : [];
            var forum = Array.isArray(data.forum) ? data.forum : [];

            personnel.forEach(function (u) {
                items.push({
                    label: 'Membre : ' + (u.title || 'Membre'),
                    subtitle: (u.subtitle || '') + (u.href ? ' · ' + u.href : ''),
                    url: u.href || '',
                    kind: 'member_profile',
                    memberId: u.id || 0,
                    memberLabel: u.title || ''
                });
            });
            documents.forEach(function (d) {
                items.push({
                    label: 'Page / document : ' + (d.title || 'Page'),
                    subtitle: d.href || '',
                    url: d.href || '',
                    kind: 'document',
                    memberId: 0,
                    memberLabel: ''
                });
            });
            forum.forEach(function (f) {
                items.push({
                    label: 'Discussion : ' + (f.title || 'Sujet'),
                    subtitle: f.href || '',
                    url: f.href || '',
                    kind: 'forum_topic',
                    memberId: 0,
                    memberLabel: ''
                });
            });

            renderResults(items.slice(0, 18));
        }).catch(function () {
            if (ticket !== searchTicket) return;
            if (manualItems.length > 0) {
                renderResults(manualItems);
                return;
            }
            resultsBox.innerHTML = '<p class="px-3 py-2 text-xs text-amber-700">Recherche indisponible temporairement.</p>';
            resultsBox.classList.remove('hidden');
        });
    }

    function openPh() {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        var d = document.getElementById('ph-details');
        if (d) d.focus();
    }
    function closePh() {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        if (resultsBox) {
            resultsBox.classList.add('hidden');
            resultsBox.innerHTML = '';
        }
    }

    document.querySelectorAll('[data-portal-help-trigger]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            openPh();
        });
    });

    var phCancel = document.getElementById('ph-cancel');
    if (phCancel) phCancel.addEventListener('click', closePh);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closePh();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) closePh();
    });

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            resetSelection();
            if (searchTimer) {
                window.clearTimeout(searchTimer);
            }
            searchTimer = window.setTimeout(function () {
                searchTargets(searchInput.value || '');
            }, 220);
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var subj = (document.getElementById('ph-subject') || {}).value || '';
        var details = (document.getElementById('ph-details') || {}).value || '';
        if (!subj.trim()) {
            alert('Choisissez un sujet dans la liste.');
            return;
        }
        if (!details.trim() || details.trim().length < 10) {
            alert('Décrivez la situation en quelques phrases (au moins dix caractères).');
            return;
        }
        var payload = {
            csrf_token: csrf,
            target_type: 'portal_help',
            target_id: 0,
            help_subject: subj,
            reference_note: (document.getElementById('ph-reference') || {}).value || '',
            selected_target_url: (hiddenUrl || {}).value || '',
            selected_target_kind: (hiddenKind || {}).value || '',
            selected_member_id: (hiddenMemberId || {}).value || 0,
            selected_member_label: (hiddenMemberLabel || {}).value || '',
            reason: (document.getElementById('ph-reason') || {}).value || 'other',
            details: details,
            page_url: window.location.href
        };
        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload),
            credentials: 'same-origin'
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (x) {
                if (x.ok && x.j && x.j.success) {
                    closePh();
                    form.reset();
                    resetSelection();
                    alert('Merci, votre message a été transmis à l’équipe de modération.');
                } else {
                    alert((x.j && x.j.error) ? x.j.error : 'Envoi impossible pour le moment.');
                }
            })
            .catch(function () { alert('Erreur réseau.'); });
    });
})();
</script>
