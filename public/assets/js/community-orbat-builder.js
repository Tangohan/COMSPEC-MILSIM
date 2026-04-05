/**
 * Constructeur ORBAT pour le wizard création communauté.
 * Vue arborescence repliable (type explorateur) ; synchro JSON vers #wizard-units-json.
 */
(function (global) {
    'use strict';

    function slugify(name) {
        var s = String(name || '').toLowerCase().trim();
        if (s.normalize) {
            s = s.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }
        s = s.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        if (s.length > 48) s = s.substring(0, 48).replace(/-+$/g, '');
        return s || 'unite';
    }

    function OrbatBuilder(container, hiddenInput, options) {
        this.container = container;
        this.hiddenInput = hiddenInput;
        this.units = Array.isArray(options.initial) ? JSON.parse(JSON.stringify(options.initial)) : [];
        this.defaultUnits = options.defaultUnits || [];
        this.nextKey = 1;
        this.customSlug = options.customSlug === true;
        this.collapsedKeys = {};
        this._ensureKeys();
        this.render();
    }

    OrbatBuilder.prototype.setCustomSlug = function (v) {
        this.customSlug = !!v;
        if (!this.customSlug) {
            this.units.forEach(function (u) {
                u.slug = slugify(u.name);
            });
            this.sync();
        }
        this.render();
    };

    OrbatBuilder.prototype._ensureKeys = function () {
        var self = this;
        this.units.forEach(function (u) {
            if (!u.key || String(u.key).trim() === '') {
                u.key = 'u' + (self.nextKey++);
            } else {
                var m = String(u.key).match(/^u(\d+)$/);
                if (m) {
                    var n = parseInt(m[1], 10);
                    if (n >= self.nextKey) self.nextKey = n + 1;
                }
            }
        });
    };

    OrbatBuilder.prototype._newKey = function () {
        return 'u' + (this.nextKey++);
    };

    OrbatBuilder.prototype.sync = function () {
        if (this.hiddenInput) {
            this.hiddenInput.value = JSON.stringify(this.units);
        }
    };

    OrbatBuilder.prototype.loadUnits = function (arr) {
        this.units = Array.isArray(arr) ? JSON.parse(JSON.stringify(arr)) : [];
        this._ensureKeys();
        this.render();
        this.sync();
    };

    OrbatBuilder.prototype.addRoot = function () {
        var k = this._newKey();
        this.units.push({
            key: k,
            parent_key: '',
            name: 'Nouvelle unité',
            slug: 'nouvelle-unite-' + k,
            type: 'group',
            display_order: 0
        });
        this.render();
        this.sync();
    };

    OrbatBuilder.prototype.addChild = function (parentKey) {
        var k = this._newKey();
        var order = 0;
        this.units.forEach(function (u) {
            if (u.parent_key === parentKey) order++;
        });
        this.units.push({
            key: k,
            parent_key: parentKey,
            name: 'Sous-unité',
            slug: 'sous-unite-' + k,
            type: 'section',
            display_order: order
        });
        this.render();
        this.sync();
    };

    OrbatBuilder.prototype.remove = function (key) {
        var self = this;
        var toRemove = {};
        function mark(k) {
            toRemove[k] = true;
            self.units.forEach(function (u) {
                if (u.parent_key === k) mark(u.key);
            });
        }
        mark(key);
        this.units = this.units.filter(function (u) { return !toRemove[u.key]; });
        delete this.collapsedKeys[key];
        this.render();
        this.sync();
    };

    OrbatBuilder.prototype.updateField = function (key, field, value) {
        var self = this;
        this.units.forEach(function (u) {
            if (u.key !== key) return;
            if (field === 'name') {
                u.name = value;
                if (!self.customSlug) {
                    u.slug = slugify(value);
                }
            } else if (field === 'slug') {
                u.slug = value;
            } else if (field === 'type') {
                u.type = value;
            } else if (field === 'parent_key') {
                u.parent_key = value;
            } else if (field === 'display_order') {
                u.display_order = parseInt(value, 10) || 0;
            }
        });
        this.sync();
    };

    OrbatBuilder.prototype._byKey = function () {
        var m = {};
        this.units.forEach(function (u) {
            m[u.key] = u;
        });
        return m;
    };

    OrbatBuilder.prototype._childrenOf = function (parentKey) {
        var pk = parentKey || '';
        var list = this.units.filter(function (u) {
            return (u.parent_key || '') === pk;
        });
        list.sort(function (a, b) {
            var oa = a.display_order != null ? a.display_order : 0;
            var ob = b.display_order != null ? b.display_order : 0;
            if (oa !== ob) return oa - ob;
            return String(a.name || '').localeCompare(String(b.name || ''));
        });
        return list;
    };

    OrbatBuilder.prototype._toggleCollapse = function (key) {
        this.collapsedKeys[key] = !this.collapsedKeys[key];
        this.render();
    };

    OrbatBuilder.prototype.render = function () {
        var self = this;
        var el = this.container;
        if (!el) return;
        el.innerHTML = '';

        var wrap = document.createElement('div');
        wrap.className = 'orbat-tree rounded-2xl border border-slate-200 bg-slate-50/80 p-2 text-sm';
        wrap.setAttribute('role', 'tree');

        if (this.units.length === 0) {
            var empty = document.createElement('p');
            empty.className = 'p-4 text-sm text-slate-500';
            empty.textContent = 'Aucune unité. Ajoutez une racine ou utilisez le démarrage rapide.';
            wrap.appendChild(empty);
            el.appendChild(wrap);
            return;
        }

        function hasChildren(k) {
            return self.units.some(function (u) { return (u.parent_key || '') === k; });
        }

        function renderUnitRow(u, depth) {
            var row = document.createElement('div');
            row.className = 'orbat-tree-node select-none';
            row.setAttribute('role', 'treeitem');
            row.style.paddingLeft = Math.min(depth * 14, 120) + 'px';

            var line = document.createElement('div');
            line.className = 'flex flex-wrap items-center gap-1 rounded-lg border border-transparent py-1.5 pr-2 hover:bg-white/80';

            var ch = hasChildren(u.key);
            var collapsed = !!self.collapsedKeys[u.key];

            if (ch) {
                var chev = document.createElement('button');
                chev.type = 'button';
                chev.className = 'flex h-7 w-7 shrink-0 items-center justify-center rounded text-slate-500 hover:bg-slate-200';
                chev.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                chev.setAttribute('aria-label', collapsed ? 'Déplier' : 'Replier');
                chev.innerHTML = collapsed ? '&#9654;' : '&#9660;';
                chev.addEventListener('click', function () {
                    self._toggleCollapse(u.key);
                });
                line.appendChild(chev);
            } else {
                var spacer = document.createElement('span');
                spacer.className = 'inline-block w-7 shrink-0';
                line.appendChild(spacer);
            }

            var folder = document.createElement('span');
            folder.className = 'mr-1 text-slate-400';
            folder.setAttribute('aria-hidden', 'true');
            folder.textContent = ch ? (collapsed ? '📁' : '📂') : '📄';
            line.appendChild(folder);

            var nameIn = document.createElement('input');
            nameIn.type = 'text';
            nameIn.value = u.name || '';
            nameIn.className = 'min-w-[8rem] flex-1 rounded-lg border border-slate-200 bg-white px-2 py-1 text-sm font-medium text-slate-900';
            nameIn.addEventListener('input', function () {
                self.updateField(u.key, 'name', nameIn.value);
                if (!self.customSlug) {
                    var slugEl = self.container.querySelector('[data-slug-for="' + u.key + '"]');
                    if (slugEl) slugEl.textContent = slugify(nameIn.value);
                }
            });
            line.appendChild(nameIn);

            if (self.customSlug) {
                var slugIn = document.createElement('input');
                slugIn.type = 'text';
                slugIn.setAttribute('data-orbat-slug', '1');
                slugIn.value = u.slug || '';
                slugIn.className = 'w-28 rounded-lg border border-slate-200 bg-white px-2 py-1 font-mono text-xs text-slate-800';
                slugIn.title = 'Segment d’URL (slug)';
                slugIn.addEventListener('change', function () {
                    self.updateField(u.key, 'slug', slugify(slugIn.value));
                    slugIn.value = slugify(slugIn.value);
                });
                line.appendChild(slugIn);
            } else {
                var slugRo = document.createElement('span');
                slugRo.setAttribute('data-slug-for', u.key);
                slugRo.className = 'max-w-[10rem] truncate rounded bg-slate-100 px-2 py-0.5 font-mono text-[10px] text-slate-500';
                slugRo.textContent = u.slug || slugify(u.name || '');
                slugRo.title = 'Généré automatiquement à partir du nom';
                line.appendChild(slugRo);
            }

            var typeSel = document.createElement('select');
            typeSel.className = 'rounded-lg border border-slate-200 bg-white px-1 py-1 text-xs';
            [['group', 'Groupe'], ['section', 'Section'], ['team', 'Équipe'], ['squad', 'Escouade']].forEach(function (tt) {
                var opt = document.createElement('option');
                opt.value = tt[0];
                opt.textContent = tt[1];
                if ((u.type || 'group') === tt[0]) opt.selected = true;
                typeSel.appendChild(opt);
            });
            typeSel.addEventListener('change', function () {
                self.updateField(u.key, 'type', typeSel.value);
            });
            line.appendChild(typeSel);

            var ordIn = document.createElement('input');
            ordIn.type = 'number';
            ordIn.value = String(u.display_order != null ? u.display_order : 0);
            ordIn.className = 'w-14 rounded-lg border border-slate-200 px-1 py-1 text-xs';
            ordIn.title = 'Ordre d’affichage';
            ordIn.addEventListener('input', function () {
                self.updateField(u.key, 'display_order', ordIn.value);
            });
            line.appendChild(ordIn);

            var parentSel = document.createElement('select');
            parentSel.className = 'max-w-[10rem] rounded-lg border border-slate-200 bg-white px-1 py-1 text-xs';
            var optRoot = document.createElement('option');
            optRoot.value = '';
            optRoot.textContent = '(racine)';
            parentSel.appendChild(optRoot);
            self.units.forEach(function (other) {
                if (other.key === u.key) return;
                var o = document.createElement('option');
                o.value = other.key;
                o.textContent = (other.name || other.key) + ' — ' + other.key;
                if ((u.parent_key || '') === other.key) o.selected = true;
                parentSel.appendChild(o);
            });
            parentSel.addEventListener('change', function () {
                self.updateField(u.key, 'parent_key', parentSel.value);
                self.render();
            });
            line.appendChild(parentSel);

            var btnChild = document.createElement('button');
            btnChild.type = 'button';
            btnChild.className = 'text-xs font-bold text-emerald-700 hover:underline';
            btnChild.textContent = '+ Sous-unité';
            btnChild.addEventListener('click', function () {
                self.addChild(u.key);
            });
            line.appendChild(btnChild);

            var btnDel = document.createElement('button');
            btnDel.type = 'button';
            btnDel.className = 'text-xs font-bold text-red-600 hover:underline';
            btnDel.textContent = 'Suppr.';
            btnDel.addEventListener('click', function () {
                self.remove(u.key);
            });
            line.appendChild(btnDel);

            row.appendChild(line);
            return row;
        }

        function renderSubtree(parentKey, depth) {
            var frag = document.createElement('div');
            frag.setAttribute('role', 'group');
            var kids = self._childrenOf(parentKey);
            kids.forEach(function (u) {
                frag.appendChild(renderUnitRow(u, depth));
                if (hasChildren(u.key) && !self.collapsedKeys[u.key]) {
                    frag.appendChild(renderSubtree(u.key, depth + 1));
                }
            });
            return frag;
        }

        var roots = this._childrenOf('');
        if (roots.length === 0 && this.units.length > 0) {
            var warn = document.createElement('p');
            warn.className = 'p-3 text-amber-800';
            warn.textContent = 'Aucune racine : vérifiez les parents ou le JSON.';
            wrap.appendChild(warn);
        } else {
            roots.forEach(function (u) {
                wrap.appendChild(renderUnitRow(u, 0));
                if (hasChildren(u.key) && !self.collapsedKeys[u.key]) {
                    wrap.appendChild(renderSubtree(u.key, 1));
                }
            });
        }

        el.appendChild(wrap);
    };

    global.initOrbatBuilder = function (containerId, hiddenId, options) {
        var c = document.getElementById(containerId);
        var h = document.getElementById(hiddenId);
        if (!c || !h) return null;
        var initial = [];
        try {
            initial = JSON.parse(h.value || '[]');
        } catch (e) {
            initial = [];
        }
        if (!Array.isArray(initial)) initial = [];
        var customSlugEl = document.getElementById('wizard-orbat-custom-slug');
        var customSlug = customSlugEl ? customSlugEl.checked : false;
        var ob = new OrbatBuilder(c, h, {
            initial: initial,
            defaultUnits: options && options.defaultUnits ? options.defaultUnits : [],
            customSlug: customSlug
        });
        if (customSlugEl) {
            customSlugEl.addEventListener('change', function () {
                ob.setCustomSlug(customSlugEl.checked);
            });
        }
        ob.sync();
        return ob;
    };
})(typeof window !== 'undefined' ? window : this);
