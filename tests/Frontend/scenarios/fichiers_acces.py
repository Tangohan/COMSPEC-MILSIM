"""Scénario « fichiers, accès et divers » : import JSON, GeoJSON et KML par le sélecteur de fichier, exports, copier-coller
d'un plan à l'autre, menu du clic droit, palette de commandes, empilement des fenêtres et Échap, lecture seule, deux
éditeurs en même temps, plan vide, grand plan (500 éléments), page en anglais."""
import json
import os
import tempfile
import time

from _base import BASE, CTRL, E, SHIFT, Browser, Run, reset

reset('demo')
r = Run('fichiers, accès et divers')
b = r.b
r.open()
tmp = tempfile.mkdtemp(prefix='plan-essai-')


def write(name, text):
    p = os.path.join(tmp, name)
    with open(p, 'w', encoding='utf-8') as f:
        f.write(text)
    return p


def pick(path):
    doc = b.send('DOM.getDocument')
    node = b.send('DOM.querySelector', nodeId=doc['root']['nodeId'], selector='#plan-file')
    b.send('DOM.setFileInputFiles', nodeId=node['nodeId'], files=[path])
    b.pump(1.2)


# --- Exports : le site rend des fichiers lisibles
n0 = r.count()
exp = {}
for fmt in ('json', 'geojson', 'kml'):
    res = r.js('fetch("/public/atak/toc/plans/1/export.%s").then(function (x) { return x.text().then(function (t) { return [x.status, t]; }); })' % fmt)
    r.eq(res[0], 200, 'export %s : 200' % fmt)
    exp[fmt] = res[1]
doc = json.loads(exp['json'])
r.eq(len(doc['elements']), 23, 'export JSON : les éléments posés du plan (l’unité à poser reste dans l’ORBAT)') if len(doc['elements']) == 23 else r.eq(len(doc['elements']), 24, 'export JSON : tous les éléments')
gj = json.loads(exp['geojson'])
r.check(gj.get('type') == 'FeatureCollection' and len(gj['features']) >= 20, 'export GeoJSON : collection de %d objets' % len(gj.get('features', [])))
r.check(exp['kml'].lstrip().startswith('<?xml') and '<Placemark>' in exp['kml'], 'export KML : document lisible')

# --- Imports par le sélecteur de fichier (même chemin que le dépôt sur la carte)
pick(write('plan.geojson', exp['geojson']))
n1 = r.count()
r.check(n1 > n0 + 15, 'import GeoJSON : éléments ajoutés (%d → %d)' % (n0, n1))
b.key('z', CTRL)
r.eq(r.count(), n0, 'import GeoJSON : un seul Ctrl+Z le défait')
pick(write('plan.kml', exp['kml']))
r.check(r.count() > n0 + 15, 'import KML : éléments ajoutés')
b.key('z', CTRL)
pick(write('plan.json', exp['json']))
r.check(r.count() > n0 + 15, 'import JSON du site : éléments ajoutés')
b.key('z', CTRL)
r.eq(r.count(), n0, 'imports annulés : le plan est comme au départ')
foreign = {'type': 'FeatureCollection', 'features': [
    {'type': 'Feature', 'properties': {'name': 'Pont'}, 'geometry': {'type': 'Point', 'coordinates': [2.35, 48.85]}},
    {'type': 'Feature', 'properties': {'name': 'Route', 'stroke': '#ff0000'}, 'geometry': {'type': 'LineString', 'coordinates': [[2.30, 48.80], [2.40, 48.90]]}},
    {'type': 'Feature', 'properties': {}, 'geometry': {'type': 'Polygon', 'coordinates': [[[2.31, 48.81], [2.36, 48.81], [2.36, 48.86], [2.31, 48.81]]]}}]}
pick(write('etranger.geojson', json.dumps(foreign)))
r.eq(r.count(), n0 + 3, 'GeoJSON étranger en degrés : trois éléments ajustés dans la vue')
inside = r.js('(function(){var E=%s;var b=E.tm.map.getBounds();return E.P.sel.every(function(u){return b.contains(E.tm.toLL(E.G.centroid(E.P.els[u])));});})()' % E)
r.eq(inside, True, 'GeoJSON étranger : posé dans la vue affichée')
b.key('z', CTRL)
pick(write('casse.kml', '<?xml version="1.0"?><!DOCTYPE x [<!ENTITY a "b">]><kml><Placemark><Point><coordinates>1,2</coordinates></Point></Placemark></kml>'))
r.eq(r.count(), n0, 'KML piégé (DOCTYPE) : refusé, rien n’est ajouté')
r.check(r.js('document.getElementById("plan-msg").classList.contains("is-error")'), 'KML piégé : message d’erreur affiché')
b.problems[:] = [p for p in b.problems if '/import' not in p]  # le refus 422 est voulu

# --- Export image et fiche : les actions passent sans erreur
r.js(E + '.run("exportPng")')
b.pump(0.6)
r.check('export' in r.js('document.getElementById("plan-msg").textContent').lower(), 'export PNG : fait')
r.js(E + '.run("exportPdf")')
b.pump(1.2)
r.check('PDF' in r.js('document.getElementById("plan-msg").textContent'), 'fiche PDF : demandée au site et reçue')

# --- Copier dans ce plan, coller dans un autre
b.key('Escape')
p = r.el_at('cp1')
b.click(p[0], p[1])
q = r.el_at('med')
b.click(q[0], q[1], mods=SHIFT)
clip = r.clip('copy')
r.saved()
r.open(plan=2)
r.eq(r.count(), 0, 'second plan : vide')
r.check('vide' in r.js('document.getElementById("pe-right-body").textContent'), 'plan vide : le volet le dit et propose quoi faire')
r.shot('acces_plan_vide')
r.clip('paste', clip)
r.eq(r.count(), 2, 'collé dans l’autre plan : deux éléments')
r.saved()

# --- Menu du clic droit, palette, Échap
r.open()
p = r.el_at('eni2')
b.click(p[0], p[1], button='right')
r.eq(r.js('document.getElementById("pe-menu").hidden'), False, 'clic droit : menu ouvert')
r.eq(r.sel(), ['eni2'], 'clic droit : l’élément visé est choisi')
r.shot('acces_menu')
b.key('Escape')
r.eq(r.js('document.getElementById("pe-menu").hidden'), True, 'Échap ferme le menu')
r.eq(r.sel(), ['eni2'], 'Échap ferme le menu sans toucher à la sélection')
b.key('k', CTRL)
r.eq(r.js('document.getElementById("pe-palette").hidden'), False, 'Ctrl+K : palette ouverte')
b.type('0590 0420')
b.key('Enter')
r.eq(r.js('document.getElementById("pe-palette").hidden'), True, 'palette : Entrée lance et ferme')
b.pump(0.8)
c = r.js('(function(){var E=%s;return E.tm.toWorld(E.tm.map.getCenter());})()' % E)
r.near(c, [5905, 4205], 12, 'palette : « 0590 0420 » centre la carte sur la grille')
b.key('k', CTRL)
b.type('versions')
b.key('Enter')
r.eq(r.js('document.getElementById("dlg-versions").hidden'), False, 'palette : commande « Versions » ouverte')
b.key('?')
b.key('Escape')
r.eq(r.js('document.getElementById("dlg-versions").hidden'), True, 'Échap ferme la fenêtre')
r.eq(r.sel(), ['eni2'], 'Échap sur une fenêtre ne désélectionne pas')
b.key('Escape')
r.eq(r.sel(), [], 'Échap suivant : désélection')

# --- Lecture seule
r.open(query='user=9')
r.eq(r.js(E + '.P.canEdit'), False, 'lecture seule : pas de droit d’édition')
r.eq(r.js('document.getElementById("plan-ro").hidden'), False, 'lecture seule : bandeau affiché')
r.eq(r.js('Array.prototype.filter.call(document.querySelectorAll("[data-needs-edit]"), function (x) { return x.offsetWidth > 0; }).length'), 0, 'lecture seule : outils d’édition masqués')
before = r.pos('eni2')
p = r.el_at('eni2')
b.click(p[0], p[1])
b.drag(p[0], p[1], p[0] + 90, p[1])
b.key('Delete')
b.key('l')
r.near(r.pos('eni2'), before, 0.1, 'lecture seule : rien ne bouge')
r.eq(r.js(E + '.P.tool'), 'select', 'lecture seule : pas d’outil de dessin')
r.eq(r.count(), 24, 'lecture seule : rien n’est supprimé')
b.pump(1.2)
posts = r.js('window.__calls.filter(function (c) { return /^POST/.test(c) && /plans/.test(c); })')
r.eq(posts, [], 'lecture seule : aucune écriture envoyée au site')
b.key('m')
r.eq(r.js(E + '.P.tool'), 'measure', 'lecture seule : la mesure reste permise')
b.key('Escape')
r.shot('acces_lecture_seule')

# --- Deux éditeurs : le dernier enregistré gagne, l'autre voit le changement
r.open()
other = Browser(size=(1280, 800))
other.open(BASE + '/harness/plan/1?user=2&nohelp=1', 'window.__ready')
other.pump(0.5)
moved = other.eval('(function(){var E=%s;var c=E.G.translate(E.P.els.hlz,300,200);var m={};m.hlz=c;E.commit(m);return c.pts[0];})()' % E)
other.wait('document.getElementById("plan-save").getAttribute("data-state") === "ok"', 10)
b.wait('Math.abs(%s.P.els.hlz.pts[0][0] - %f) < 0.2' % (E, moved[0]), 12)
r.near(r.pos('hlz'), moved, 0.2, 'deux éditeurs : le déplacement de l’autre arrive par le sondage')
r.check(r.js('document.querySelectorAll("#plan-presence .pe-avatar").length') >= 1, 'deux éditeurs : l’autre membre est affiché')
r.check(not other.problems, 'second éditeur : aucune anomalie (%r)' % other.problems)
other.close()

# --- Grand plan : 500 éléments
reset('big')
r.open()
r.eq(r.count(), 500, 'grand plan : 500 éléments chargés')
ms = r.js('(function(){var t=performance.now();for(var i=0;i<3;i++)%s.renderAll();return Math.round((performance.now()-t)/3);})()' % E)
r.check(ms < 400, 'grand plan : un rendu complet en %d ms (rendu logiciel, limite 400)' % ms)
t = time.time()
a, c = r.at(600, 600), r.at(7800, 7800)
b.key('a', CTRL)
r.eq(len(r.sel()), 499, 'grand plan : Ctrl+A choisit tout ce qui est affiché')
ms2 = r.js('(function(){var t=performance.now();%s.renderOverlay();return Math.round(performance.now()-t);})()' % E)
r.check(ms2 < 300, 'grand plan : contours de 499 éléments choisis en %d ms' % ms2)
b.key('ArrowRight')
r.saved(20)
r.check(time.time() - t < 25, 'grand plan : déplacer 499 éléments et enregistrer en %.1f s' % (time.time() - t))
b.key('z', CTRL)
r.saved(20)
r.shot('acces_grand_plan')

# --- Anglais : la page se charge et se traduit sans erreur
reset('demo')
r.open(query='lang=en')
b.pump(1.0)
r.check('Library' in r.js('document.querySelector("[data-left=lib]").textContent') or 'LIBRARY' in r.js('document.querySelector("[data-left=lib]").textContent.toUpperCase()'), 'anglais : onglet « Library »')
r.js(E + '.run("importMarkers")')
b.wait('document.querySelector(".pe-mk-list")')
b.pump(0.5)
r.check('Markers' in r.js('document.getElementById("pe-mk-title").textContent'), 'anglais : volet des marqueurs traduit (%s)' % r.js('document.getElementById("pe-mk-title").textContent'))
r.shot('acces_anglais')
raise SystemExit(r.done())
