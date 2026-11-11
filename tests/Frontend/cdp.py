"""Pilote minimal d'un navigateur Chromium (Edge ou Chrome) sans écran par le protocole DevTools, sans dépendance :
vrais événements de souris, de clavier et tactiles, lecture d'état par JavaScript, captures d'écran, et relevé de tout
ce qui est une anomalie (erreur de console, exception, promesse rejetée, réponse HTTP 4xx / 5xx).

Sert aux scénarios de tests/Frontend/scenarios/*.py (éditeur de plans). Python 3.9+, bibliothèque standard seulement.

    from cdp import Browser
    with Browser(size=(1920, 1080)) as b:
        b.open('http://127.0.0.1:8790/harness/plan/1')
        b.wait('window.TocPlanEditor && TocPlanEditor.P.loaded')
        b.drag(400, 300, 700, 420)
        assert b.eval('TocPlanEditor.P.sel.length') == 3
        b.screenshot('out.png')
        assert not b.problems, b.problems
"""
import atexit
import base64
import json
import os
import shutil
import socket
import struct
import subprocess
import tempfile
import threading
import time
import urllib.request

EDGE = [
    r'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
    r'C:\Program Files\Microsoft\Edge\Application\msedge.exe',
    r'C:\Program Files\Google\Chrome\Application\chrome.exe',
    '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome', '/usr/bin/microsoft-edge',
]
KEYS = {
    'Enter': (13, '\r'), 'Escape': (27, ''), 'Backspace': (8, ''), 'Delete': (46, ''), 'Tab': (9, '\t'), ' ': (32, ' '),
    'ArrowLeft': (37, ''), 'ArrowUp': (38, ''), 'ArrowRight': (39, ''), 'ArrowDown': (40, ''), 'PageUp': (33, ''), 'PageDown': (34, ''),
    'Shift': (16, ''), 'Control': (17, ''), 'Alt': (18, ''), 'F10': (121, ''),
}
ALT, CTRL, META, SHIFT = 1, 2, 4, 8


class Browser:
    def __init__(self, size=(1920, 1080), port=0, gl=True, exe=None, life=300):
        self.size = size
        self.problems = []
        self.console = []
        self.dialogs = []
        self.dialog_accept = True
        self.dialog_text = ''
        self.patience = 30
        self._id = 0
        self._closed = False
        self._events = []
        exe = exe or os.environ.get('BROWSER') or next((p for p in EDGE if os.path.exists(p)), None)
        if not exe:
            raise RuntimeError('aucun navigateur Chromium trouvé (variable BROWSER)')
        self.profile = tempfile.mkdtemp(prefix='cdp-profil-')
        if not port:
            s = socket.socket(); s.bind(('127.0.0.1', 0)); port = s.getsockname()[1]; s.close()
        self.port = port
        # Sans extension : une extension imposée au poste ouvre son propre onglet, qui prend la main (page « cachée »,
        # plus d'images ni de sondage) ou détache l'inspecteur en plein scénario.
        args = [exe, '--headless=new', '--remote-debugging-port=%d' % port, '--user-data-dir=' + self.profile, '--no-first-run', '--no-default-browser-check',
                '--disable-extensions', '--disable-component-extensions-with-background-pages', '--disable-background-networking', '--disable-sync',
                '--hide-scrollbars', '--window-size=%d,%d' % size, '--remote-allow-origins=*', '--disable-features=Translate', 'about:blank']
        if gl:
            args[2:2] = ['--enable-unsafe-swiftshader', '--use-angle=swiftshader', '--ignore-gpu-blocklist']
        self.proc = subprocess.Popen(args, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        # Le navigateur ne survit jamais au scénario : fermé à la sortie, et de toute façon après « life » secondes
        # (scénario figé ou tué), sinon des navigateurs sans écran s'accumulent et chargent la machine.
        atexit.register(self.close)
        self._watchdog = threading.Timer(life, self._kill)
        self._watchdog.daemon = True
        self._watchdog.start()
        ws = None
        for _ in range(100):
            try:
                tabs = json.loads(urllib.request.urlopen('http://127.0.0.1:%d/json' % port, timeout=1).read())
                page = [t for t in tabs if t.get('type') == 'page' and t.get('url', '').startswith('about:')] or [t for t in tabs if t.get('type') == 'page']
                if page:
                    ws = page[0]['webSocketDebuggerUrl']
                    break
            except Exception:
                pass
            time.sleep(0.1)
        if not ws:
            self.close()
            raise RuntimeError('le navigateur ne répond pas')
        self._connect(ws)
        for d in ('Page', 'Runtime', 'Network', 'Log'):
            self.send(d + '.enable')
        self.viewport(*size)

    # ---------- WebSocket (RFC 6455, client, sans extension) ----------
    def _connect(self, url):
        host, rest = url[5:].split('/', 1)
        h, p = host.split(':')
        self.sock = socket.create_connection((h, int(p)))
        key = base64.b64encode(os.urandom(16)).decode()
        self.sock.sendall(('GET /%s HTTP/1.1\r\nHost: %s\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: %s\r\nSec-WebSocket-Version: 13\r\n\r\n' % (rest, host, key)).encode())
        buf = b''
        while b'\r\n\r\n' not in buf:
            buf += self.sock.recv(4096)
        self._buf = buf.split(b'\r\n\r\n', 1)[1]

    def _take(self):
        """Une trame complète prise dans le tampon, ou None s'il en manque : rien n'est consommé tant qu'elle n'est pas
        entière (une attente dépassée au milieu d'une trame ne désynchronise pas la lecture)."""
        buf = self._buf
        if len(buf) < 2:
            return None
        n = buf[1] & 127
        at = 2
        if n == 126:
            if len(buf) < 4:
                return None
            n = struct.unpack('>H', buf[2:4])[0]
            at = 4
        elif n == 127:
            if len(buf) < 10:
                return None
            n = struct.unpack('>Q', buf[2:10])[0]
            at = 10
        if len(buf) < at + n:
            return None
        self._buf = buf[at + n:]
        return buf[0], buf[at:at + n]

    def _recv(self):
        while True:
            frame = self._take()
            if frame is None:
                chunk = self.sock.recv(1 << 18)
                if not chunk:
                    raise RuntimeError('connexion fermée par le navigateur')
                self._buf += chunk
                continue
            b1, payload = frame
            op = b1 & 15
            if op == 9:
                self._frame(payload, 10)
                continue
            if op == 8:
                raise RuntimeError('connexion fermée')
            self._part = getattr(self, '_part', b'') + payload
            if b1 & 128:
                data, self._part = self._part, b''
                return json.loads(data.decode('utf-8'))

    def _frame(self, payload, op=1):
        n = len(payload)
        head = bytes([128 | op])
        mask = os.urandom(4)
        if n < 126:
            head += bytes([128 | n])
        elif n < 65536:
            head += bytes([128 | 126]) + struct.pack('>H', n)
        else:
            head += bytes([128 | 127]) + struct.pack('>Q', n)
        self.sock.sendall(head + mask + bytes(c ^ mask[i % 4] for i, c in enumerate(payload)))

    def send(self, method, **params):
        self._id += 1
        mid = self._id
        self._frame(json.dumps({'id': mid, 'method': method, 'params': params}).encode('utf-8'))
        self.sock.settimeout(self.patience)
        while True:
            try:
                msg = self._recv()
            except socket.timeout:
                raise TimeoutError('le navigateur ne répond pas à %s %s' % (method, json.dumps(params)[:160]))
            if msg.get('id') == mid:
                if 'error' in msg:
                    raise RuntimeError('%s : %s' % (method, msg['error'].get('message')))
                return msg.get('result', {})
            self._event(msg)

    def _event(self, msg):
        m, p = msg.get('method'), msg.get('params', {})
        if m == 'Runtime.consoleAPICalled':
            text = ' '.join(str(a.get('value', a.get('description', ''))) for a in p.get('args', []))
            self.console.append((p.get('type'), text))
            if p.get('type') in ('error', 'assert'):
                self.problems.append('console.error : ' + text[:400])
        elif m == 'Runtime.exceptionThrown':
            d = p.get('exceptionDetails', {})
            self.problems.append('exception : ' + str((d.get('exception') or {}).get('description') or d.get('text'))[:500])
        elif m == 'Network.responseReceived':
            r = p.get('response', {})
            if r.get('status', 200) >= 400 and not self._ignored(r.get('url', '')):
                self.problems.append('HTTP %s : %s' % (r.get('status'), r.get('url')))
        elif m == 'Log.entryAdded':
            e = p.get('entry', {})
            if e.get('level') == 'error' and not self._ignored(e.get('url', '') + e.get('text', '')):
                self.problems.append('journal : %s %s' % (e.get('text', '')[:300], e.get('url', '')))
        elif m == 'Page.javascriptDialogOpening':
            # Boîte du navigateur (confirm, prompt, quitter la page) : répondue tout de suite, sinon la page reste figée.
            self.dialogs.append((p.get('type'), p.get('message', '')))
            self._id += 1
            self._frame(json.dumps({'id': self._id, 'method': 'Page.handleJavaScriptDialog', 'params': {'accept': bool(self.dialog_accept), 'promptText': self.dialog_text}}).encode('utf-8'))

    ignore = ('favicon.ico', 'fonts.googleapis.com', 'fonts.gstatic.com', '/fixtures/none/')

    def _ignored(self, url):
        return any(s in url for s in self.ignore)

    def pump(self, seconds=0.0):
        """Laisse passer du temps en relevant les événements."""
        end = time.time() + seconds
        self.sock.settimeout(0.05)
        try:
            while True:
                try:
                    self._event(self._recv())
                except socket.timeout:
                    if time.time() >= end:
                        break
        finally:
            self.sock.settimeout(None)

    # ---------- Page ----------
    def viewport(self, w, h, scale=1, mobile=False):
        self.size = (w, h)
        self.send('Emulation.setDeviceMetricsOverride', width=w, height=h, deviceScaleFactor=scale, mobile=mobile)
        if mobile:
            self.send('Emulation.setTouchEmulationEnabled', enabled=True)

    def open(self, url, wait=None, timeout=30):
        self.send('Page.navigate', url=url)
        self.wait('document.readyState === "complete"', timeout)
        if wait:
            self.wait(wait, timeout)

    def eval(self, js, await_promise=True):
        r = self.send('Runtime.evaluate', expression=js, returnByValue=True, awaitPromise=await_promise, userGesture=True)
        if 'exceptionDetails' in r:
            raise RuntimeError('eval : ' + str(r['exceptionDetails'].get('exception', {}).get('description') or r['exceptionDetails'].get('text')))
        return r.get('result', {}).get('value')

    def wait(self, js, timeout=15, step=0.1):
        end = time.time() + timeout
        while time.time() < end:
            try:
                if self.eval('!!(' + js + ')'):
                    return True
            except RuntimeError:
                pass
            self.pump(step)
        raise TimeoutError('attente dépassée : ' + js)

    def rect(self, selector, index=0):
        """Centre et taille d'un élément : (x, y, largeur, hauteur)."""
        r = self.eval('(function(){var e=document.querySelectorAll(%s)[%d];if(!e)return null;e.scrollIntoView({block:"nearest"});var r=e.getBoundingClientRect();return [r.left+r.width/2,r.top+r.height/2,r.width,r.height];})()' % (json.dumps(selector), index))
        if not r:
            raise LookupError('élément introuvable : ' + selector)
        return r

    # ---------- Entrées ----------
    def mouse(self, kind, x, y, button='left', buttons=None, mods=0, clicks=0):
        b = {'left': 1, 'right': 2, 'middle': 4, 'none': 0}[button]
        self.send('Input.dispatchMouseEvent', type=kind, x=x, y=y, button=button if kind != 'mouseMoved' or buttons else 'none',
                  buttons=b if buttons is None else buttons, modifiers=mods, clickCount=clicks, pointerType='mouse')

    def move(self, x, y, mods=0):
        self.mouse('mouseMoved', x, y, 'none', 0, mods)

    def click(self, x, y=None, button='left', mods=0, count=1):
        if isinstance(x, str):
            x, y = self.rect(x)[:2]
        self.move(x, y, mods)
        for i in range(count):
            self.mouse('mousePressed', x, y, button, None, mods, i + 1)
            self.mouse('mouseReleased', x, y, button, 0, mods, i + 1)
        self.pump(0.05)

    def drag(self, x0, y0, x1, y1, steps=8, mods=0, button='left', hold=0.0):
        self.move(x0, y0, mods)
        self.mouse('mousePressed', x0, y0, button, None, mods, 1)
        for i in range(1, steps + 1):
            self.mouse('mouseMoved', x0 + (x1 - x0) * i / steps, y0 + (y1 - y0) * i / steps, button, None, mods)
            self.pump(0.01)
        if hold:
            self.pump(hold)
        self.mouse('mouseReleased', x1, y1, button, 0, mods, 1)
        self.pump(0.05)

    def wheel(self, x, y, dy, mods=0):
        self.send('Input.dispatchMouseEvent', type='mouseWheel', x=x, y=y, deltaX=0, deltaY=dy, modifiers=mods)
        self.pump(0.05)

    def key(self, key, mods=0, text=None, commands=None):
        code, auto = KEYS.get(key, (ord(key.upper()) if len(key) == 1 else 0, key if len(key) == 1 else ''))
        txt = auto if text is None else text
        if mods & (CTRL | ALT | META):
            txt = ''
        p = dict(key=key, windowsVirtualKeyCode=code, nativeVirtualKeyCode=code, modifiers=mods)
        if len(key) == 1 and key.isalpha():
            p['code'] = 'Key' + key.upper()
        # Copier, couper, coller : la commande d'édition du navigateur (événements copy / cut / paste) part avec la touche.
        down = dict(p, commands=commands) if commands else p
        self.send('Input.dispatchKeyEvent', type='keyDown' if txt else 'rawKeyDown', text=txt, **down)
        self.send('Input.dispatchKeyEvent', type='keyUp', **p)
        self.pump(0.03)

    def keydown(self, key, mods=0):
        code = KEYS.get(key, (ord(key.upper()) if len(key) == 1 else 0, ''))[0]
        self.send('Input.dispatchKeyEvent', type='rawKeyDown', key=key, windowsVirtualKeyCode=code, modifiers=mods)

    def keyup(self, key, mods=0):
        code = KEYS.get(key, (ord(key.upper()) if len(key) == 1 else 0, ''))[0]
        self.send('Input.dispatchKeyEvent', type='keyUp', key=key, windowsVirtualKeyCode=code, modifiers=mods)

    def type(self, text):
        self.send('Input.insertText', text=text)
        self.pump(0.03)

    def touch(self, kind, points):
        self.send('Input.dispatchTouchEvent', type=kind, touchPoints=[{'x': x, 'y': y, 'id': i} for i, (x, y) in enumerate(points)])

    def tap_drag(self, x0, y0, x1, y1, steps=8):
        self.touch('touchStart', [(x0, y0)])
        for i in range(1, steps + 1):
            self.touch('touchMove', [(x0 + (x1 - x0) * i / steps, y0 + (y1 - y0) * i / steps)])
            self.pump(0.01)
        self.touch('touchEnd', [])
        self.pump(0.05)

    def screenshot(self, path):
        data = self.send('Page.captureScreenshot', format='png')['data']
        with open(path, 'wb') as f:
            f.write(base64.b64decode(data))
        return path

    def grant_clipboard(self, origin):
        self.send('Browser.grantPermissions', origin=origin, permissions=['clipboardReadWrite', 'clipboardSanitizedWrite'])

    def _kill(self):
        try:
            self.proc.kill()
        except Exception:
            pass
        os._exit(3)

    def close(self):
        if self._closed:
            return
        self._closed = True
        self._watchdog.cancel()
        try:
            self.sock.settimeout(3)
            self._id += 1
            self._frame(json.dumps({'id': self._id, 'method': 'Browser.close', 'params': {}}).encode('utf-8'))
        except Exception:
            pass
        try:
            self.proc.wait(timeout=5)
        except Exception:
            self.proc.kill()
        shutil.rmtree(self.profile, ignore_errors=True)

    def __enter__(self):
        return self

    def __exit__(self, *a):
        self.close()
