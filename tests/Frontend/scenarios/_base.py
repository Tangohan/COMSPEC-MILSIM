"""Outils communs des scénarios de l'éditeur de plans : remise à zéro du banc, ouverture, positions à l'écran,
vérifications qui s'accumulent (un scénario va au bout et rend la liste de ce qui a échoué).

Le banc doit tourner :  php -d extension=pdo_sqlite -S 127.0.0.1:8790 -t . tests/Frontend/fixtures/plan-backend.php
Lancer un scénario :   python tests/Frontend/scenarios/edition.py [dossier des captures]
"""
import json
import os
import sys
import time
import urllib.request

T0 = time.time()

sys.path.insert(0, os.path.join(os.path.dirname(os.path.abspath(__file__)), '..'))
from cdp import ALT, CTRL, SHIFT, Browser  # noqa: E402,F401

BASE = os.environ.get('HARNESS', 'http://127.0.0.1:8790')
OUT = sys.argv[1] if len(sys.argv) > 1 else os.path.join(os.path.dirname(os.path.abspath(__file__)), 'captures')
E = 'TocPlanEditor'


def reset(fixture='demo'):
    return json.loads(urllib.request.urlopen(BASE + '/__harness/reset?fixture=' + fixture, timeout=30).read())


class Run:
    def __init__(self, name, size=(1920, 1080), life=300):
        self.name = name
        self.fails = []
        self.checks = 0
        self.b = Browser(size=size, life=life)
        os.makedirs(OUT, exist_ok=True)

    def open(self, plan=1, query='', wait_dem=True, help=False):
        b = self.b
        if not help:
            query = (query + '&' if query else '') + 'nohelp=1'
        if 'user=' not in query:
            query += '&user=1'  # le témoin d'une ouverture en lecture seule ne doit pas suivre
        b.open(BASE + '/harness/plan/%d%s' % (plan, ('?' + query) if query else ''), 'window.__ready')
        if wait_dem and 'nodem' not in query:
            b.wait(E + '.dem', 15)
        b.pump(0.3)

    def js(self, expr):
        return self.b.eval(expr)

    def at(self, x, y):
        """Position à l'écran d'un point du terrain (mètres)."""
        return self.js('(function(){var r=document.getElementById("plan-map").getBoundingClientRect();var p=%s.px([%f,%f]);return [r.left+p[0],r.top+p[1]];})()' % (E, x, y))

    def el_at(self, uid):
        """Position à l'écran du centre d'un élément tel qu'il est affiché."""
        return self.js('(function(){var E=%s;var d=E.shownOf(%s);var c=d.geom==="point"?d.pts[0]:E.G.centroid(d);var r=document.getElementById("plan-map").getBoundingClientRect();var p=E.px(c);return [r.left+p[0],r.top+p[1]];})()' % (E, json.dumps(uid)))

    def pos(self, uid):
        return self.js('%s.P.els[%s] ? %s.P.els[%s].pts[0] : null' % (E, json.dumps(uid), E, json.dumps(uid)))

    def sel(self):
        return self.js(E + '.P.sel.slice().sort()')

    def count(self):
        return self.js('Object.keys(%s.P.els).length' % E)

    def clip(self, kind, text=None):
        """Événement copy / cut / paste du navigateur avec son presse-papiers (le presse-papiers du système n'existe pas
        sans écran : c'est le seul maillon remplacé). Rend le texte posé par copy ou cut."""
        return self.js('(function(){var dt=new DataTransfer();%s var ev=new ClipboardEvent(%s,{clipboardData:dt,bubbles:true,cancelable:true});document.dispatchEvent(ev);return dt.getData("text/plain");})()'
                       % ('dt.setData("text/plain",%s);' % json.dumps(text) if text is not None else '', json.dumps(kind)))

    def saved(self, timeout=8):
        """Attend que tout soit enregistré."""
        try:
            self.b.wait('document.getElementById("plan-save").getAttribute("data-state") === "ok"', timeout)
        except TimeoutError:
            self.check(False, 'enregistrement non terminé en %d s : « %s », message « %s »' % (
                timeout, self.js('document.getElementById("plan-save").textContent'), self.js('document.getElementById("plan-msg").textContent')))

    def check(self, cond, what):
        self.checks += 1
        if not cond:
            self.fails.append(what)
            print('  ÉCHEC', what, flush=True)
        elif os.environ.get('VERBOSE'):
            print('  ok %5.1fs' % (time.time() - T0), what, flush=True)
        return cond

    def eq(self, got, want, what):
        return self.check(got == want, '%s : obtenu %r, attendu %r' % (what, got, want))

    def near(self, got, want, tol, what):
        ok = got is not None and all(abs(a - b) <= tol for a, b in zip(got, want))
        return self.check(ok, '%s : obtenu %r, attendu %r ± %s' % (what, got, want, tol))

    def shot(self, name):
        return self.b.screenshot(os.path.join(OUT, name + '.png'))

    def done(self):
        self.b.pump(0.3)
        problems = list(self.b.problems)
        self.b.close()
        for p in problems:
            print('  ANOMALIE', p)
        print('%s : %d vérifications, %d échec(s), %d anomalie(s) de console ou de réseau' % (self.name, self.checks, len(self.fails), len(problems)))
        return 1 if self.fails or problems else 0
