"""Aide de l'éditeur de plans (plan-help.js) : tutoriel guidé, tiroir d'aide, astuces, plan d'exemple.

Tout passe par de vrais événements de souris et de clavier sur le banc (plan-backend.php). Les seuls appels directs au
JavaScript de la page servent à lire l'état, et à relancer le tutoriel avant les mesures aux petites tailles d'écran.

    php -d extension=pdo_sqlite -S 127.0.0.1:8792 -t . tests/Frontend/fixtures/plan-backend.php
    HARNESS=http://127.0.0.1:8792 python tests/Frontend/scenarios/tutoriel.py [dossier des captures]
"""
import json
import sys
import threading

from _base import CTRL, E, Run, reset

STEPS = ['unit', 'move', 'axis', 'phase', 'key', 'play', '3d', '2d', 'markers', 'publish']

# Fiche du tutoriel : dans l'écran, sans couvrir une cible (cadres affichés) ni l'élément réel attendu.
CARD = '''(function (sel) {
  var c = document.getElementById('ph-card');
  if (!c || c.hidden) return null;
  var r = c.getBoundingClientRect();
  function cut(b) { var x = Math.min(r.right, b.right) - Math.max(r.left, b.left), y = Math.min(r.bottom, b.bottom) - Math.max(r.top, b.top); return x > 1 && y > 1 ? Math.round(x * y) : 0; }
  var rings = [].slice.call(document.querySelectorAll('.ph-ring:not([hidden])')).map(function (g) { return g.getBoundingClientRect(); });
  var real = sel ? document.querySelector(sel) : null;
  var rb = real && real.getClientRects().length ? real.getBoundingClientRect() : null;
  return {
    inside: r.left >= 0 && r.top >= 0 && r.right <= window.innerWidth && r.bottom <= window.innerHeight,
    rings: rings.length, over: rings.reduce(function (a, b) { return a + cut(b); }, 0), real: rb ? cut(rb) : -1,
    ring0: rings[0] ? [rings[0].left, rings[0].top, rings[0].width, rings[0].height] : null,
    realRect: rb ? [rb.left, rb.top, rb.width, rb.height] : null, veil: document.querySelector('.ph-veil').style.display !== 'none'
  };
})(%s)'''
REAL = {
    'unit': '.pe-tile[data-lib="u:infantry"]', 'axis': '.pe-tile[data-lib="k:axis"]', 'phase': '#pe-time [data-run="addPhase"]',
    'key': '#pe-autokey', 'play': '#pe-play', '3d': '[data-view="3d"]', '2d': '[data-view="2d"]', 'publish': '[data-run="publish"]',
}


def opn(r, query=''):
    """Ouvre le plan 1 avec l'aide (le banc la retire par défaut) et garde la page au premier plan."""
    r.open(1, query, help=True)
    r.b.send('Page.bringToFront')


def mark(r, what):
    """Dit dans quelle partie une anomalie de console ou de réseau est apparue."""
    seen = getattr(r, '_seen', 0)
    for x in r.b.problems[seen:]:
        print('  (anomalie relevée pendant : %s) %s' % (what, ' '.join(x[:160].split())), flush=True)
    r._seen = len(r.b.problems)


def state(r):
    return r.js(E + '.help.state()')


def card(r, sel=None):
    return r.js(CARD % json.dumps(sel))


def toast(r):
    return r.js('(function(){var t=document.getElementById("plan-msg");return t.hidden?"":t.textContent;})()')


def map_xy(r, fx, fy):
    """Point de la carte utile, en fractions de sa largeur et de sa hauteur."""
    return r.js('(function(){var b=document.getElementById("plan-map").getBoundingClientRect();return [b.left+b.width*%f,b.top+b.height*%f];})()' % (fx, fy))


def check_card(r, step, where):
    r.b.pump(0.6)
    c = card(r, REAL.get(step))
    if not r.check(c is not None, '%s, étape %s : fiche affichée' % (where, step)):
        return
    r.check(c['inside'], '%s, étape %s : fiche dans l’écran' % (where, step))
    r.eq(c['over'], 0, '%s, étape %s : fiche hors des cibles (surface couverte)' % (where, step))
    if step in REAL:
        r.check(c['real'] == 0, '%s, étape %s : fiche hors de %s (couvert : %s)' % (where, step, REAL[step], c['real']))
    if step != 'end':
        r.check(c['rings'] >= 1 and c['veil'], '%s, étape %s : voile et cadre de la cible' % (where, step))


def main():
    reset('empty')
    r = Run('tutoriel')
    b = r.b
    # Ce scénario dure plus que les 300 s du chien de garde du navigateur (vue 3D, trois tailles d'écran) : 15 minutes.
    b._watchdog.cancel()
    b._watchdog = threading.Timer(900, b._kill)
    b._watchdog.daemon = True
    b._watchdog.start()

    mark(r, 'partie 0')
    # ---------- 1. Premier passage d'un membre qui peut modifier : le tutoriel démarre seul ----------
    opn(r)
    b.wait(E + '.help && ' + E + '.help.state().on', 5)
    s = state(r)
    r.eq((s['on'], s['id']), (True, 'unit'), 'tutoriel démarré seul à la première étape')
    check_card(r, 'unit', '1920')
    c = card(r, REAL['unit'])
    r.near(c['ring0'][:2], [c['realRect'][0] - 5, c['realRect'][1] - 5], 2, 'le cadre entoure la tuile « Infanterie »')
    r.eq(r.js('document.activeElement && document.activeElement.id'), 'ph-card', 'la fiche a le curseur clavier au démarrage')
    r.eq(r.js('document.getElementById("ph-live").textContent.indexOf("Posez une unité") >= 0'), True, 'annonce de l’étape (aria-live)')
    r.eq(r.js('document.querySelector("#ph-card [data-ph=example]").hidden'), False, 'plan d’exemple proposé sur la première fiche')

    # L'éditeur reste utilisable sous le tutoriel : un outil, un tracé, sans faire avancer l'étape.
    b.click('[data-tool="line"]')
    r.eq(r.js(E + '.P.tool'), 'line', 'outil Trait choisi pendant le tutoriel')
    b.click(*map_xy(r, 0.30, 0.75)); b.pump(0.35)
    b.click(*map_xy(r, 0.42, 0.80)); b.pump(0.35)
    b.key('Enter')
    r.eq(r.count(), 1, 'trait tracé sous le voile')
    r.eq(state(r)['id'], 'unit', 'un trait ne valide pas l’étape « unité »')

    # Étape 1 : glisser « Infanterie » de la bibliothèque à la carte.
    t = b.rect(REAL['unit'])
    m = map_xy(r, 0.5, 0.45)
    b.drag(t[0], t[1], m[0], m[1], steps=12)
    b.pump(0.4)
    r.eq(state(r)['id'], 'move', 'unité posée : étape « déplacer »')
    uid = r.js('Object.keys(%s.P.els).filter(function(u){return %s.P.els[u].kind==="unit";})[0]' % (E, E))
    check_card(r, 'move', '1920')
    c = card(r)
    p = r.el_at(uid)
    r.check(c['ring0'][0] < p[0] < c['ring0'][0] + c['ring0'][2] and c['ring0'][1] < p[1] < c['ring0'][1] + c['ring0'][3], 'le cadre entoure l’unité posée')

    # Étape 2 : déplacer l'unité.
    before = r.pos(uid)
    b.drag(p[0], p[1], p[0] + 150, p[1] + 60, steps=10)
    b.pump(0.4)
    r.check(r.pos(uid) != before, 'unité déplacée')
    r.eq(state(r)['id'], 'axis', 'unité déplacée : étape « axe »')
    check_card(r, 'axis', '1920')

    mark(r, 'partie 1')
    # ---------- 2. Quitter, reprendre, recharger ----------
    b.click(*map_xy(r, 0.15, 0.2))           # sort du champ « Indicatif »
    b.key('Escape')
    s = state(r)
    r.eq((s['on'], s['step']), (False, 2), 'Échap quitte le tutoriel et garde l’étape')
    r.check('Tutoriel en pause' in toast(r), 'message de mise en pause')
    r.eq(r.js('document.getElementById("ph-card").hidden && document.querySelector(".ph-veil").style.display === "none"'), True, 'fiche et voile retirés')
    r.saved()
    opn(r)
    b.pump(1.2)
    s = state(r)
    r.eq((s['on'], s['step']), (False, 2), 'rechargé après avoir quitté : pas de reprise d’office, étape gardée')

    mark(r, 'partie 2')
    # ---------- 3. Tiroir d'aide : touche « ? », recherche, rubriques ----------
    b.key('?')
    b.pump(0.3)
    r.eq(state(r)['drawer'], True, 'la touche « ? » ouvre le tiroir')
    r.eq(r.js('document.getElementById("toc-help-overlay").hidden'), True, 'l’ancienne fenêtre des raccourcis reste fermée')
    r.eq(r.js('document.activeElement.id'), 'ph-search', 'curseur dans la recherche')
    r.eq(r.js('document.querySelector("#ph-drawer [data-ph=tour]").textContent'), 'Reprendre le tutoriel', 'le tiroir propose de reprendre')
    n_how = r.js('document.querySelectorAll("#ph-pane-how .ph-how").length')
    r.check(n_how >= 14, 'au moins 14 modes d’emploi (%s)' % n_how)
    r.eq(r.js('document.querySelectorAll("#ph-pane-how .ph-how svg.ph-ill").length'), n_how, 'une illustration par mode d’emploi')
    ill = r.js('(function(){var s=document.querySelector("#ph-pane-how .ph-how[open] svg.ph-ill");var a=s.querySelector(".ph-a-go");var b=s.getBoundingClientRect();return [b.width>100&&b.height>40, getComputedStyle(a).animationName, getComputedStyle(a).animationIterationCount];})()')
    r.eq(ill, [True, 'ph-go', 'infinite'], 'illustration visible et animée en boucle')
    big = r.js('Math.max.apply(null,[].slice.call(document.querySelectorAll("svg.ph-ill")).map(function(s){return s.outerHTML.length;}))')
    r.check(big <= 2048, 'illustrations de 2 Ko au plus (la plus lourde : %s octets)' % big)
    r.eq(r.js('document.querySelectorAll("#ph-drawer img, #ph-drawer canvas, #ph-drawer [src]").length'), 0, 'aucune image ni ressource externe dans le tiroir')
    r.shot('aide')
    b.click('[data-tool="pan"]')
    r.eq(r.js(E + '.P.tool'), 'pan', 'le tiroir n’est pas modal : un outil se choisit tiroir ouvert')
    b.click('[data-tool="select"]')
    b.click('#ph-search')
    b.type('lasso')
    b.pump(0.2)
    cnt = r.js('[].slice.call(document.querySelectorAll("#ph-drawer .ph-count")).map(function(c){return c.hidden?-1:Number(c.textContent);})')
    r.check(cnt[0] >= 1 and cnt[1] >= 1 and cnt[2] == 0, 'recherche « lasso » : modes d’emploi et raccourcis (%s)' % cnt)
    b.key('a', CTRL); b.key('Backspace')
    b.type('deplier')                          # sans accent : « déplier »
    b.pump(0.2)
    r.check(r.js('document.querySelectorAll("#ph-pane-how .ph-how:not([hidden])").length') >= 1, 'recherche sans accent (« deplier »)')
    b.key('a', CTRL); b.key('Backspace')
    b.type('fscl')
    b.pump(0.2)
    r.eq(state(r)['tab'], 'gfx', 'la recherche ouvre la rubrique qui répond (« fscl » : Graphismes)')
    r.eq(r.js('document.querySelectorAll("#ph-pane-gfx .ph-gl:not([hidden])").length'), 1, 'un seul graphisme pour « fscl »')
    b.key('a', CTRL); b.key('Backspace')
    b.pump(0.2)
    b.click('[data-ph-tab="keys"]')
    src = r.js('document.querySelectorAll("#toc-help-overlay .toc-keys > kbd").length')
    r.eq(r.js('document.querySelectorAll("#ph-pane-keys .ph-key:not([hidden])").length'), src, 'raccourcis repris de la feuille de la page (%s lignes)' % src)
    r.eq(r.js('document.getElementById("ph-pane-keys").hidden'), False, 'rubrique Raccourcis affichée')
    b.click('[data-ph-tab="gfx"]')
    kinds = r.js('Object.keys(%s.KINDS).length' % E)
    r.eq(r.js('document.querySelectorAll("#ph-pane-gfx .ph-gl").length'), kinds, 'un graphisme par type du catalogue')
    r.eq(r.js('document.querySelectorAll("#ph-pane-gfx .ph-gl span").length'), kinds, 'chaque type a son explication')
    r.eq(r.js('document.getElementById("ph-doc").hidden'), False, 'le guide écrit est lié (adresse fournie par la page)')
    b.click('#ph-search')
    b.key('Escape')
    r.eq(state(r)['drawer'], False, 'Échap ferme le tiroir')
    b.key('?'); b.pump(0.2)
    b.key('Escape')                            # le curseur est dans la recherche
    b.key('?'); b.pump(0.2)
    b.click('#ph-drawer [data-ph="tour"]')
    b.pump(0.4)
    s = state(r)
    r.eq((s['on'], s['id'], s['drawer']), (True, 'axis', False), '« Reprendre le tutoriel » rouvre l’étape gardée')
    r.saved()
    opn(r)
    b.wait(E + '.help.state().on', 5)
    r.eq(state(r)['id'], 'axis', 'rechargé en cours de tutoriel : reprise à la même étape')

    mark(r, 'partie 3')
    # ---------- 4. Suite du tutoriel, pour de vrai ----------
    # Étape 3 : axe de progression (tuile, clics, Entrée).
    b.click(REAL['axis'])
    b.pump(0.3)
    r.eq(r.js(E + '.P.tool'), 'axis', 'tuile « Axe de progression » armée')
    for fx, fy in ((0.25, 0.30), (0.40, 0.36), (0.55, 0.30)):
        b.click(*map_xy(r, fx, fy)); b.pump(0.35)
    b.key('Enter')
    b.pump(0.4)
    r.eq(state(r)['id'], 'phase', 'axe tracé : étape « phase »')
    check_card(r, 'phase', '1920')
    # Étape 4 : une phase.
    b.click(REAL['phase'])
    b.pump(0.4)
    r.eq(r.js(E + '.P.phases.length'), 1, 'phase créée')
    r.eq(state(r)['id'], 'key', 'phase créée : étape « H+10 »')
    check_card(r, 'key', '1920')
    r.eq(r.js('document.querySelectorAll("#ph-card-checks li").length'), 3, 'trois gestes listés sur la fiche')
    # Étape 5 : Clé auto, frise à H+10, déplacer l'unité.
    b.click('#pe-autokey')
    b.pump(0.3)
    r.eq(r.js('document.querySelectorAll("#ph-card-checks li.is-done").length'), 1, '« Clé auto » coché sur la fiche')
    r.shot('tuto_etape')
    tr = r.js('(function(){var e=document.querySelector("#pe-track .is-ruler")||document.getElementById("pe-track");var b=e.getBoundingClientRect();var t=document.getElementById("pe-track").getBoundingClientRect();return [t.left,t.width,b.top+b.height/2];})()')
    b.click(tr[0] + tr[1] * 0.25, tr[2]); t1 = r.js(E + '.P.time')
    b.click(tr[0] + tr[1] * 0.75, tr[2]); t2 = r.js(E + '.P.time')
    r.check(t1 is not None and t2 is not None and t2 > t1, 'la frise répond au clic (%s, %s)' % (t1, t2))
    x10 = tr[0] + tr[1] * (0.25 + 0.5 * (10 - t1) / (t2 - t1))
    b.click(x10, tr[2])
    b.pump(0.3)
    r.near([r.js(E + '.P.time')], [10], 1.5, 'frise placée à H+10')
    r.eq(r.js('document.querySelectorAll("#ph-card-checks li.is-done").length'), 2, '« H+10 » coché sur la fiche')
    p = r.el_at(uid)
    b.drag(p[0], p[1], p[0] - 180, p[1] + 90, steps=10)
    b.pump(0.4)
    r.check(r.js('(%s.P.els[%s].props.track||[]).length' % (E, json.dumps(uid))) >= 2, 'l’unité a deux images clés')
    r.eq(state(r)['id'], 'play', 'image clé posée : étape « lecture »')
    check_card(r, 'play', '1920')
    # Étape 6 : lecture.
    b.click(REAL['play'])
    b.pump(0.4)
    r.eq(state(r)['id'], '3d', 'lecture lancée : étape « 3D »')
    b.click(REAL['play'])
    check_card(r, '3d', '1920')
    # Étapes 7 et 8 : 3D, puis 2D.
    b.click(REAL['3d'])
    b.wait(E + '.P.mode === "3d"', 30)
    b.pump(0.5)
    r.eq(state(r)['id'], '2d', 'vue 3D ouverte : étape « retour en 2D »')
    check_card(r, '2d', '1920')
    b.click(REAL['2d'])
    b.wait(E + '.P.mode === "2d"', 10)
    b.pump(0.4)
    r.eq(state(r)['id'], 'markers', 'retour en 2D : étape « marqueurs »')
    # Étape 9 : menu Importer, marqueurs du jeu.
    check_card(r, 'markers', '1920')
    has_action = r.js('!!%s.ACTIONS.importMarkers' % E)
    b.click('details.toc-menu:has([data-run="importMarkers"]) > summary')
    b.pump(0.5)
    c = card(r, '[data-run="importMarkers"]')
    r.check(c['real'] == 0 and c['over'] == 0, 'menu Importer ouvert : la fiche ne couvre pas « Marqueurs présents en jeu »')
    r.near((c['ring0'] or [0, 0])[:2], [(c['realRect'] or [-99, -99])[0] - 5, (c['realRect'] or [-99, -99])[1] - 5], 2, 'le cadre passe sur « Marqueurs présents en jeu »')
    r.eq(r.js('(function(){var l=document.querySelector("details.toc-menu[open] .toc-menu-list").getBoundingClientRect(),c=document.getElementById("ph-card").getBoundingClientRect();return c.left<l.right&&c.right>l.left&&c.top<l.bottom&&c.bottom>l.top;})()'), False, 'la fiche ne couvre pas le menu ouvert')
    b.click('[data-run="importMarkers"]')
    b.pump(1.5)
    go = r.js("(function(){var g=document.querySelector('#pe-markers [data-mk=go]');return g&&g.getClientRects().length?(g.disabled?'off':'on'):'';})()")
    if go:
        c = card(r, '#pe-markers')
        r.check(c['real'] == 0 and c['over'] == 0, 'volet des marqueurs ouvert : la fiche ne le couvre pas (%s)' % c['real'])
        c = card(r, '#pe-markers [data-mk="go"]')
        r.near((c['ring0'] or [0, 0])[:2], [c['realRect'][0] - 5, c['realRect'][1] - 5], 2, 'le cadre passe sur le bouton d’import du volet')
    if go == 'on':
        n0 = r.count()
        b.click('#pe-markers [data-mk="go"]')
        b.pump(0.8)
        r.check(r.count() > n0, 'marqueurs du jeu importés (%d éléments de plus)' % (r.count() - n0))
    auto = state(r)['id'] != 'markers'
    print('  NOTE action importMarkers %s ; étape validée %s' % ('présente' if has_action else 'absente', 'par l’événement markersImported' if auto else 'par « Je l’ai fait »'))
    if has_action:
        r.check(auto, 'l’import des marqueurs fait avancer le tutoriel tout seul')
    if not auto:
        if r.js("!!document.querySelector('#pe-markers [data-mk=close]') && document.body.classList.contains('pe-markers-open')"):
            b.click('#pe-markers [data-mk="close"]')
        r.eq(r.js('document.querySelector("#ph-card [data-ph=did]").hidden'), False, 'bouton « Je l’ai fait » sur l’étape des marqueurs')
        b.click('#ph-card [data-ph="did"]')
        b.pump(0.4)
    r.eq(state(r)['id'], 'publish', 'marqueurs : étape « envoyer en jeu »')
    check_card(r, 'publish', '1920')
    # Étape 10 : ouvrir la fenêtre d'envoi suffit ; rien n'est envoyé.
    b.click(REAL['publish'])
    b.pump(0.6)
    s = state(r)
    r.eq((s['on'], s['end']), (True, True), 'fenêtre d’envoi ouverte : tutoriel terminé')
    r.eq(r.js('document.getElementById("dlg-publish").hidden'), False, 'fenêtre « Envoyer en jeu » ouverte')
    r.eq(r.js('(function(){var l=document.querySelector("#dlg-publish .toc-dialog").getBoundingClientRect(),c=document.getElementById("ph-card").getBoundingClientRect();return c.left<l.right&&c.right>l.left&&c.top<l.bottom&&c.bottom>l.top;})()'), False, 'la fiche de fin ne couvre pas la fenêtre d’envoi')
    r.eq(r.js('window.__calls.filter(function(c){return /publish/.test(c);}).length'), 0, 'aucun envoi en jeu pendant le tutoriel')
    b.key('Escape')
    r.eq(r.js('document.getElementById("dlg-publish").hidden'), True, 'Échap ferme d’abord la fenêtre d’envoi')
    r.eq(state(r)['on'], True, 'la fiche de fin reste affichée')
    b.click('#ph-card [data-ph="finish"]')
    r.eq(state(r)['on'], False, '« Terminer » ferme le tutoriel')
    r.saved()
    opn(r)
    b.pump(1.2)
    r.eq(state(r)['on'], False, 'tutoriel terminé : il ne revient pas au rechargement')

    mark(r, 'partie 4')
    # ---------- 5. Astuces au premier usage d'un outil ----------
    b.click('[data-tool="lasso"]')
    b.pump(0.2)
    r.eq(state(r)['hint'], 'lasso', 'astuce au premier usage du lasso')
    b.key('s')                                 # message de l'éditeur (aimantation)
    b.pump(0.2)
    geo = r.js('(function(){var h=document.getElementById("ph-hint").getBoundingClientRect(),t=document.getElementById("plan-msg").getBoundingClientRect(),w=document.getElementById("plan-mapwrap").getBoundingClientRect();return [t.height>0, h.bottom<=t.top, h.left>=w.left&&h.right<=w.right&&h.top>=w.top];})()')
    r.eq(geo, [True, True, True], 'astuce dans la carte, au-dessus du message de l’éditeur')
    b.key('s')
    b.click('[data-tool="select"]'); b.pump(0.2)
    b.click('[data-tool="lasso"]'); b.pump(0.2)
    r.eq(state(r)['hint'], '', 'une astuce ne s’affiche qu’une fois')
    b.click('[data-tool="measure"]'); b.pump(0.2)
    r.eq(state(r)['hint'], 'measure', 'astuce de la mesure')
    b.click('#ph-hint [data-ph="hints-off"]')
    b.click('[data-tool="los"]'); b.pump(0.2)
    r.eq(state(r)['hint'], '', '« Ne plus afficher les astuces » les coupe')
    b.click('[data-tool="select"]')

    mark(r, 'partie 5')
    # ---------- 6. Ordre d'Échap : menus, fenêtre, tiroir, outil, puis tutoriel ----------
    b.key('k', CTRL)
    b.pump(0.2)
    b.type('recommencer')
    b.pump(0.2)
    b.key('Enter')
    b.pump(0.5)
    s = state(r)
    r.eq((s['on'], s['id']), (True, 'unit'), 'la palette relance le tutoriel (action tutorialRestart)')
    m = map_xy(r, 0.2, 0.6)
    b.click(m[0], m[1], button='right')
    b.pump(0.2)
    r.eq(r.js('document.getElementById("pe-menu").hidden'), False, 'menu du clic droit ouvert sous le tutoriel')
    b.key('Escape')
    r.eq((r.js('document.getElementById("pe-menu").hidden'), state(r)['on']), (True, True), 'Échap : le menu du clic droit d’abord')
    b.click('details.toc-menu:has([data-run="importMarkers"]) > summary')
    b.key('Escape')
    r.eq((r.js('document.querySelectorAll("details.toc-menu[open]").length'), state(r)['on']), (0, True), 'Échap : le menu Importer d’abord')
    b.click('[data-run="versions"]')
    b.pump(0.3)
    r.eq(r.js('document.getElementById("dlg-versions").hidden'), False, 'fenêtre Versions ouverte')
    b.key('Escape')
    r.eq((r.js('document.getElementById("dlg-versions").hidden'), state(r)['on']), (True, True), 'Échap : la fenêtre d’abord')
    b.click(*map_xy(r, 0.2, 0.2))
    b.key('?')
    b.pump(0.2)
    r.eq(state(r)['drawer'], True, 'tiroir ouvert pendant le tutoriel')
    b.key('Escape')
    s = state(r)
    r.eq((s['drawer'], s['on']), (False, True), 'Échap : le tiroir avant le tutoriel')
    b.key('l')
    r.eq(r.js(E + '.P.tool'), 'line', 'raccourci d’outil pendant le tutoriel')
    b.key('Escape')
    r.eq((r.js(E + '.P.tool'), state(r)['on']), ('select', True), 'Échap : l’outil avant le tutoriel')
    b.key('Escape')
    r.eq(state(r)['on'], False, 'Échap : le tutoriel en dernier')

    mark(r, 'partie 6')
    # ---------- 7. Écrans plus petits : la fiche reste dans l'écran et hors de sa cible, à chaque étape ----------
    for w, h in ((1366, 768), (1024, 768)):
        b.viewport(w, h)
        opn(r)
        b.pump(0.8)
        r.js(E + '.help.startTour(true)')
        for i, step in enumerate([x for x in STEPS if x != '2d']):   # déjà en 2D : l'étape « retour en 2D » est sautée
            b.pump(0.3)
            r.eq(state(r)['id'], step, '%d : étape attendue' % w)
            check_card(r, step, str(w))
            if w == 1366 and step == 'key':
                r.shot('tuto_1366')
            b.click('#ph-card [data-ph="skip"]')
        check_card(r, 'end', str(w))
        b.click('#ph-card [data-ph="finish"]')
        # Volets repliés : l'étape rouvre ce dont elle a besoin.
        b.click('[data-collapse="left"]')
        b.pump(0.3)
        r.js(E + '.help.startTour(true)')
        b.pump(0.8)
        r.eq(r.js('!!document.querySelector(\'.pe-tile[data-lib="u:infantry"]\') && document.querySelector(\'.pe-tile[data-lib="u:infantry"]\').getClientRects().length > 0'), True, '%d : volet de gauche rouvert pour la première étape' % w)
        check_card(r, 'unit', '%d, volet rouvert' % w)
        b.key('Escape')
        r.saved()
    b.viewport(1920, 1080)

    mark(r, 'partie 7')
    # ---------- 8. Lecture seule : jamais de démarrage d'office ----------
    opn(r, 'user=9')
    b.pump(1.5)
    r.eq(r.js(E + '.P.canEdit'), False, 'membre en lecture seule')
    r.eq(state(r)['on'], False, 'lecture seule : pas de tutoriel d’office')
    b.key('?')
    b.pump(0.3)
    r.eq(state(r)['drawer'], True, 'lecture seule : le tiroir s’ouvre')
    b.click('#ph-drawer [data-ph="tour"]')
    b.pump(0.3)
    r.eq(state(r)['on'], False, 'lecture seule : le tutoriel ne s’ouvre pas')
    r.check('plan que vous pouvez modifier' in toast(r), 'lecture seule : message qui renvoie au plan d’exemple')

    mark(r, 'partie 8')
    # ---------- 9. Anglais : la page se charge, le tiroir s'ouvre ----------
    opn(r, 'user=1&lang=en')
    b.pump(1.0)
    b.key('?')
    b.pump(0.4)
    r.eq(state(r)['drawer'], True, 'en anglais : le tiroir s’ouvre')
    r.eq(r.js('document.querySelectorAll("#ph-pane-keys .ph-key").length'), r.js('document.querySelectorAll("#toc-help-overlay .toc-keys > kbd").length'), 'en anglais : raccourcis repris')

    mark(r, 'partie 9')
    # ---------- 10. Plan d'exemple ----------
    opn(r, 'user=1')
    b.pump(0.8)
    before = len(b.problems)
    r.js(E + '.BOOT.csrf = "faux"')            # le site refuse : message clair, on reste sur le plan
    b.key('?'); b.pump(0.3)
    b.click('#ph-drawer [data-ph="example"]')
    b.pump(1.5)
    r.check(r.js('document.getElementById("plan-msg").classList.contains("is-error")') and 'Session expirée' in toast(r), 'refus du site : message d’erreur (%r)' % toast(r))
    r.check('/harness/plan/1' in r.js('location.pathname'), 'refus du site : on reste sur le plan')
    expected = [x for x in b.problems[before:] if '419' in x or ('status of 419' in x)]
    r.check(len(expected) >= 1, 'le refus simulé (419) a bien eu lieu')
    b.problems[before:] = [x for x in b.problems[before:] if x not in expected]
    r.js(E + '.BOOT.csrf = "essai"')
    b.pump(0.5)
    b.click('#ph-drawer [data-ph="example"]')
    b.wait('/\\/atak\\/toc\\/plans\\/\\d+$/.test(location.pathname) && window.__ready', 20)
    b.wait(E + '.dem', 15)
    b.wait(E + '.help.state().on', 5)
    r.eq(r.js(E + '.P.plan.title'), 'Plan d’exemple (tutoriel)', 'plan d’exemple ouvert')
    r.eq(r.js('location.search'), '', 'le paramètre ?tutoriel=1 ne sert qu’une fois')
    r.eq(state(r)['id'], 'unit', 'le plan d’exemple lance le tutoriel au début')
    r.eq((r.count(), r.js(E + '.P.phases.length')), (21, 3), 'plan d’exemple : 21 éléments, 3 phases')
    r.eq(r.js(E + '.P.plan.sharing'), 'private', 'plan d’exemple privé')
    r.check(r.js('(%s.P.els.a1.props.track||[]).length' % E) == 4, 'les unités du plan d’exemple ont leurs images clés')
    fit = r.js('(function(){var E=%s,b=document.getElementById("plan-map").getBoundingClientRect();return E.P.els.ao.pts.every(function(p){var q=E.px(p);return q[0]>=0&&q[1]>=0&&q[0]<=b.width&&q[1]<=b.height;});})()' % E)
    r.eq(fit, True, 'le plan d’exemple tient dans la vue')
    span = r.js('(function(){var p=%s.P.els.ao.pts;return Math.max.apply(null,p.map(function(q){return q[0];}))-Math.min.apply(null,p.map(function(q){return q[0];}));})()' % E)
    r.near([span], [3000], 50, 'plan d’exemple sur 3 km')
    r.shot('exemple')
    b.key('Escape')
    # Messages du navigateur lui-même (messagerie de ses extensions internes), sans rapport avec la page : comptés à part.
    own = [x for x in b.problems if 'Unchecked runtime.lastError' in x]
    if own:
        print('  NOTE %d message(s) « Unchecked runtime.lastError » du navigateur écarté(s)' % len(own))
        b.problems[:] = [x for x in b.problems if x not in own]
    # plan-markers.js (import des marqueurs) est en cours d'écriture : son absence est dite, pas comptée.
    miss = [x for x in b.problems if 'plan-markers.js' in x and '404' in x]
    if miss:
        print('  NOTE plan-markers.js absent (404) sur %d chargement(s)' % (len(miss) // 2))
        b.problems[:] = [x for x in b.problems if x not in miss]
    return r.done()


if __name__ == '__main__':
    sys.exit(main())
