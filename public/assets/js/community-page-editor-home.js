/*
 * Éditeur de blocs (community-page-editor.js) : types de blocs propres au bloc personnalisé de l'accueil du back-office.
 * Article, texte riche, citation, liste de liens, galerie, vidéo, tableau simple, tableau de données, séparateur.
 * Ne s'active que si la page l'inclut ; le serveur revalide tout (App\Services\BackOffice\Home\HomeBlocks).
 */
(function () {
  'use strict';
  window.pbeExt = window.pbeExt || [];
  window.pbeExt.push(function (api) {
    var cfg = api.cfg;

    var labels = {
      accent: ['Couleur d’accent', { default: 'Par défaut', emerald: 'Vert', info: 'Bleu', warn: 'Ambre', danger: 'Rouge', neutral: 'Neutre' }],
      layout: ['Image', { top: 'Au-dessus', left: 'À gauche', right: 'À droite' }],
      display: ['Présentation', { buttons: 'Boutons', list: 'Liste' }],
      line: ['Trait', { solid: 'Plein', dashed: 'Tirets', dotted: 'Points' }],
      striped: ['Lignes alternées', { on: 'Oui', off: 'Non' }],
      vratio: ['Format', { '16x9': '16/9', '4x3': '4/3', '21x9': 'Panoramique' }]
    };

    var art = {
      article: '<rect x="10" y="10" width="140" height="26" rx="3" fill="#24332e"/><rect x="14" y="44" width="70" height="6" rx="2" fill="#fff"/><rect x="14" y="56" width="120" height="4" rx="2" fill="#7d8a84"/><rect x="14" y="64" width="100" height="4" rx="2" fill="#7d8a84"/>',
      richtext: '<rect x="12" y="12" width="60" height="7" rx="2" fill="#fff"/><rect x="12" y="26" width="136" height="4" rx="2" fill="#7d8a84"/><rect x="12" y="35" width="120" height="4" rx="2" fill="#7d8a84"/><circle cx="16" cy="50" r="2" fill="#12d18e"/><rect x="24" y="48" width="70" height="4" rx="2" fill="#7d8a84"/><circle cx="16" cy="60" r="2" fill="#12d18e"/><rect x="24" y="58" width="54" height="4" rx="2" fill="#7d8a84"/>',
      quote: '<rect x="10" y="14" width="140" height="50" rx="4" fill="#121a1c"/><rect x="10" y="14" width="4" height="50" fill="#12d18e"/><rect x="26" y="26" width="104" height="6" rx="2" fill="#fff"/><rect x="26" y="38" width="80" height="6" rx="2" fill="#fff"/><rect x="26" y="52" width="40" height="4" rx="2" fill="#7d8a84"/>',
      links: '<rect x="12" y="12" width="136" height="16" rx="3" fill="#24332e"/><rect x="12" y="32" width="136" height="16" rx="3" fill="#24332e"/><rect x="12" y="52" width="136" height="16" rx="3" fill="#24332e"/><rect x="20" y="18" width="50" height="4" rx="2" fill="#fff"/><rect x="20" y="38" width="62" height="4" rx="2" fill="#fff"/><rect x="20" y="58" width="42" height="4" rx="2" fill="#fff"/>',
      gallery: '<rect x="8" y="10" width="68" height="58" rx="3" fill="#24332e"/><rect x="84" y="10" width="32" height="27" rx="3" fill="#1f2c28"/><rect x="120" y="10" width="32" height="27" rx="3" fill="#1f2c28"/><rect x="84" y="41" width="32" height="27" rx="3" fill="#1f2c28"/><rect x="120" y="41" width="32" height="27" rx="3" fill="#24332e"/>',
      video: '<rect x="12" y="10" width="136" height="58" rx="4" fill="#0c1116"/><circle cx="80" cy="39" r="14" fill="#12d18e"/><path d="M75 32l14 7-14 7z" fill="#04231a"/>',
      divider: '<rect x="12" y="38" width="48" height="2" fill="#7d8a84"/><rect x="68" y="35" width="24" height="8" rx="2" fill="#fff"/><rect x="100" y="38" width="48" height="2" fill="#7d8a84"/>',
      table: '<rect x="10" y="12" width="140" height="54" rx="3" fill="#121a1c"/><rect x="10" y="12" width="140" height="12" fill="#24332e"/><path d="M57 12v54M104 12v54M10 36h140M10 50h140" stroke="#3c524a" stroke-width="1.5" fill="none"/>',
      sheet: '<rect x="10" y="12" width="140" height="54" rx="3" fill="#121a1c"/><rect x="10" y="12" width="140" height="12" fill="#12d18e" opacity=".7"/><path d="M57 12v54M104 12v54M10 36h140M10 50h140" stroke="#3c524a" stroke-width="1.5" fill="none"/><rect x="16" y="40" width="26" height="5" rx="2" fill="#fff"/><rect x="63" y="40" width="26" height="5" rx="2" fill="#fff"/>'
    };

    function el(tag, cls, text) {
      var e = document.createElement(tag);
      if (cls) { e.className = cls; }
      if (text != null) { e.textContent = text; }
      return e;
    }

    /* Champ de texte d'un bloc : met à jour le texte affiché sur place, sinon redessine le bloc. */
    function propInput(g, b, label, key, o) {
      o = o || {};
      b.props = b.props || {};
      g.appendChild(api.input(label, b.props[key], function (v) {
        b.props[key] = v;
        var t = document.querySelector('.pb-block[data-pb-id="' + b.id + '"] [data-cl-prop="' + key + '"]');
        if (t && !o.rerender) {
          t.textContent = v;
          if (o.multiline) { t.style.whiteSpace = 'pre-line'; }
        } else {
          api.scheduleRerender(b);
        }
        api.markDirty();
      }, { key: key, multiline: !!o.multiline, rows: o.rows || 3, placeholder: o.placeholder, hint: o.hint }));
    }

    /* Texte riche : zone de saisie et petite barre de mise en forme (balisage léger, assaini par le serveur). */
    function richField(g, b, label, key) {
      b.props = b.props || {};
      var wrap = el('div', 'pbe-rich');
      var bar = el('div', 'pbe-rich__bar');
      var ta = el('textarea', 'pbe-input');
      ta.rows = 9;
      ta.value = b.props[key] || '';
      ta.setAttribute('data-pbe-key', key);
      var wrapSel = function (before, after, fallback) {
        var s = ta.selectionStart; var e = ta.selectionEnd;
        var sel = ta.value.slice(s, e) || fallback;
        ta.value = ta.value.slice(0, s) + before + sel + after + ta.value.slice(e);
        ta.focus();
        ta.setSelectionRange(s + before.length, s + before.length + sel.length);
        ta.dispatchEvent(new Event('input', { bubbles: true }));
      };
      var linePrefix = function (prefix) {
        var s = ta.selectionStart;
        var start = ta.value.lastIndexOf('\n', s - 1) + 1;
        ta.value = ta.value.slice(0, start) + prefix + ta.value.slice(start);
        ta.focus();
        ta.setSelectionRange(s + prefix.length, s + prefix.length);
        ta.dispatchEvent(new Event('input', { bubbles: true }));
      };
      [
        ['Gras', '<b>G</b>', function () { wrapSel('**', '**', 'texte'); }],
        ['Italique', '<i>I</i>', function () { wrapSel('*', '*', 'texte'); }],
        ['Titre', 'H', function () { linePrefix('## '); }],
        ['Liste à puces', '•', function () { linePrefix('- '); }],
        ['Liste numérotée', '1.', function () { linePrefix('1. '); }],
        ['Citation', '“', function () { linePrefix('> '); }],
        ['Lien', 'Lien', function () { wrapSel('[', '](https://)', 'texte du lien'); }]
      ].forEach(function (d) {
        var bt = el('button', 'pbe-icon');
        bt.type = 'button';
        bt.setAttribute('aria-label', d[0]);
        bt.title = d[0];
        bt.innerHTML = d[1];
        bt.addEventListener('click', d[2]);
        bar.appendChild(bt);
      });
      ta.addEventListener('input', function () {
        b.props[key] = ta.value;
        api.markDirty();
        api.scheduleRerender(b);
      });
      wrap.appendChild(bar);
      wrap.appendChild(ta);
      g.appendChild(api.field(label, wrap, 'Titres (## ), listes (- ou 1. ), **gras**, *italique*, [texte](https://adresse). Tout autre balisage est ignoré.'));
    }

    function imageGroup(b, title, key, focusImage) {
      var gi = api.group(title);
      gi.appendChild(api.imagePicker('Image', b.props[key], function (v) { b.props[key] = v; api.markDirty(); api.scheduleRerender(b); }));
      gi.appendChild(api.input('Description de l’image', b.props[key + '_alt'], function (v) { b.props[key + '_alt'] = v; api.markDirty(); }, { hint: 'Lue par les lecteurs d’écran.' }));
      if (focusImage === key) { setTimeout(function () { gi.scrollIntoView({ block: 'start' }); }, 50); }
    }

    function buttonGroup(b) {
      var gb = api.group('Bouton');
      gb.appendChild(api.input('Texte du bouton', b.props.button_label, function (v) { b.props.button_label = v; api.markDirty(); api.scheduleRerender(b); }, { placeholder: 'Sans bouton' }));
      gb.appendChild(api.input('Lien', b.props.button_url, function (v) { b.props.button_url = v; api.markDirty(); }, { placeholder: '/chemin, https://… ou mailto:' }));
    }

    /* Liste d'éléments répétés (liens, images) : ajout, suppression, ordre. */
    function itemsEditor(b, title, max, addLabel, blank, subs, withThumb, focusImage) {
      var g = api.group(title);
      var wrap = el('div', 'pbe-items');
      b.props.items = b.props.items || [];
      var draw = function () {
        wrap.innerHTML = '';
        b.props.items.forEach(function (it, idx) {
          var row = el('div', 'pbe-item');
          if (withThumb) {
            var th = el('div', 'pbe-item__thumb');
            th.title = 'Changer l’image';
            var u = api.imgSrc(it.image);
            if (u) { th.style.backgroundImage = 'url("' + u.replace(/"/g, '%22') + '")'; }
            th.addEventListener('click', function () {
              var holder = row.nextSibling && row.nextSibling.classList && row.nextSibling.classList.contains('pbe-field') ? row.nextSibling : null;
              if (holder) { holder.remove(); return; }
              wrap.insertBefore(api.imagePicker('Image', it.image, function (v) { it.image = v; api.markDirty(); api.scheduleRerender(b); draw(); }), row.nextSibling);
            });
            row.appendChild(th);
          }
          var main = el('div', 'pbe-item__main');
          subs.forEach(function (s) {
            var inp = el('input', 'pbe-input');
            inp.placeholder = s[1];
            inp.value = it[s[0]] || '';
            inp.addEventListener('input', function () { it[s[0]] = inp.value; api.markDirty(); api.scheduleRerender(b); });
            main.appendChild(inp);
          });
          row.appendChild(main);
          var tools = el('div', 'pbe-item__tools');
          tools.innerHTML = '<button type="button" class="pbe-icon" data-t="up" aria-label="Monter">' + api.svg('up') + '</button><button type="button" class="pbe-icon" data-t="down" aria-label="Descendre">' + api.svg('down') + '</button><button type="button" class="pbe-icon pbe-icon--danger" data-t="rm" aria-label="Retirer">' + api.svg('trash') + '</button>';
          tools.addEventListener('click', function (e) {
            var t = e.target.closest('button');
            if (!t) { return; }
            var items = b.props.items;
            var w = t.getAttribute('data-t');
            if (w === 'rm') { items.splice(idx, 1); }
            if (w === 'up' && idx > 0) { items.splice(idx - 1, 0, items.splice(idx, 1)[0]); }
            if (w === 'down' && idx < items.length - 1) { items.splice(idx + 1, 0, items.splice(idx, 1)[0]); }
            draw();
            api.markDirty();
            api.scheduleRerender(b);
          });
          row.appendChild(tools);
          wrap.appendChild(row);
          if (withThumb && focusImage === 'items.' + idx + '.image') {
            focusImage = null;
            setTimeout(function () { row.querySelector('.pbe-item__thumb').click(); row.scrollIntoView({ block: 'center' }); }, 60);
          }
        });
        var add = el('button', 'pbe-btn pbe-btn--soft');
        add.type = 'button';
        add.innerHTML = api.svg('plus') + addLabel;
        add.disabled = b.props.items.length >= max;
        add.addEventListener('click', function () {
          b.props.items.push(JSON.parse(JSON.stringify(blank)));
          draw();
          api.markDirty();
          api.scheduleRerender(b);
        });
        wrap.appendChild(add);
      };
      draw();
      g.appendChild(wrap);
    }

    var panels = {
      article: function (b, node, focusImage) {
        var gc = api.group('Contenu');
        propInput(gc, b, 'Surtitre', 'kicker');
        propInput(gc, b, 'Titre', 'title');
        richField(gc, b, 'Texte', 'text');
        imageGroup(b, 'Image', 'image', focusImage === 'image' ? 'image' : null);
        buttonGroup(b);
      },
      richtext: function (b) {
        var gc = api.group('Contenu');
        propInput(gc, b, 'Titre (facultatif)', 'title');
        richField(gc, b, 'Texte', 'text');
      },
      quote: function (b) {
        var gc = api.group('Contenu');
        propInput(gc, b, 'Citation', 'text', { multiline: true, rows: 3 });
        propInput(gc, b, 'Auteur', 'name');
        propInput(gc, b, 'Rôle ou contexte', 'meta');
      },
      links: function (b) {
        var gc = api.group('Contenu');
        propInput(gc, b, 'Titre', 'title');
        itemsEditor(b, 'Liens', 12, 'Ajouter un lien', { label: 'Nouveau lien', url: '', hint: '' }, [['label', 'Libellé'], ['url', 'Adresse : /chemin, https://… ou mailto:'], ['hint', 'Précision (facultatif)']], false, null);
      },
      gallery: function (b, node, focusImage) {
        var gc = api.group('Contenu');
        propInput(gc, b, 'Titre (facultatif)', 'title');
        itemsEditor(b, 'Images', 12, 'Ajouter une image', { image: '', alt: '', caption: '' }, [['caption', 'Légende'], ['alt', 'Description (lecteurs d’écran)']], true, focusImage);
      },
      video: function (b) {
        var gc = api.group('Contenu');
        propInput(gc, b, 'Titre (facultatif)', 'title');
        gc.appendChild(api.input('Adresse de la vidéo', b.props.video_url, function (v) { b.props.video_url = v; api.markDirty(); api.scheduleRerender(b); }, {
          placeholder: 'https://www.youtube.com/watch?v=…',
          hint: 'YouTube ou Vimeo uniquement (https). Lecture sans cookies de suivi. Toute autre adresse est refusée.'
        }));
        propInput(gc, b, 'Légende (facultative)', 'text', { multiline: true, rows: 2 });
      },
      table: function (b) {
        var gc = api.group('Contenu');
        propInput(gc, b, 'Titre (facultatif)', 'title');
        propInput(gc, b, 'Tableau', 'text', {
          multiline: true, rows: 8, rerender: true,
          placeholder: 'Créneau | Lieu | Responsable',
          hint: 'Une ligne par rangée, colonnes séparées par « | ». La première ligne sert d’en-tête. 6 colonnes et 21 lignes au plus.'
        });
      },
      sheet: function (b) {
        var gc = api.group('Contenu');
        propInput(gc, b, 'Titre (facultatif)', 'title');
        var sel = el('select', 'pbe-input');
        var none = el('option', null, 'Choisir un tableau…');
        none.value = '0';
        sel.appendChild(none);
        (cfg.sheets || []).forEach(function (s) {
          var o = el('option', null, s.title + (s.shared ? '' : ' (non partagé)'));
          o.value = String(s.id);
          if (Number(b.props.sheet_id) === s.id) { o.selected = true; }
          sel.appendChild(o);
        });
        sel.addEventListener('change', function () { b.props.sheet_id = parseInt(sel.value, 10) || 0; api.markDirty(); api.scheduleRerender(b); });
        gc.appendChild(api.field('Tableau de données', sel, 'Seuls les tableaux partagés avec la communauté sont visibles par les autres membres de l’équipe. Un tableau se crée depuis l’accueil, bouton « Personnaliser ».'));
      },
      divider: function (b) {
        var gc = api.group('Contenu');
        propInput(gc, b, 'Titre (facultatif)', 'title');
      }
    };

    return { labels: labels, art: art, panels: panels };
  });
})();
