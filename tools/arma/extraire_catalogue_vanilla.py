#!/usr/bin/env python3
"""
Extraction HORS LIGNE du catalogue de base d'Arma 3 (jeu de base + DLC officiels installés) pour le configurateur
d'équipement d'Athena (game_item_specs, source « vanilla »).

Lit directement les PBO de l'installation locale (en-tête PBO, puis seulement les config.bin et stringtable.xml),
décode la configuration binarisée (format « raP »), fusionne les addons dans l'ordre de leurs requiredAddons, résout
l'héritage des classes, puis écrit un fichier JSON compact : classes, noms affichés anglais et français, famille,
masse, contenance (maximumLoad), emplacements autorisés, accessoires compatibles
(WeaponSlotsInfo >> *Slot >> compatibleItems) et chargeurs (magazines[], magazineWell[], muzzles[]).

Ne copie AUCUN fichier du jeu (ni texture, ni modèle, ni icône) : uniquement des métadonnées de configuration
(noms de classes, textes affichés, nombres). Aucune connexion réseau. Les DLC chiffrés (.ebo, CDLC) sont ignorés.

Usage :
    PYTHONUTF8=1 python -I tools/arma/extraire_catalogue_vanilla.py \
        --arma "F:/SteamLibrary/steamapps/common/Arma 3" --out database/data/arma3_catalogue_vanilla.json

Format de sortie (lu par App\\Services\\GameEquipment\\Configurator\\VanillaCatalog) :
    {"format": "comspec-vanilla-catalog", "v": 1, "version": "<empreinte>", "game": "...", "dlc": [...],
     "groups": [[classe, ...], ...],
     "items": [[classe, racine w|m|v|g, type, sous-type, nom EN, nom FR, masse, charge max, type d'emplacement,
                munitions, [emplacements autorisés], [bouche, optique, rail, bipied] (index de groupes) | null,
                chargeurs (index de groupe) | null, dlc], ...]}
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
import struct
import sys
import xml.etree.ElementTree as ET
from collections import OrderedDict

# Dossier d'addons -> nom affiché du contenu (jeu de base et DLC officiels de Bohemia).
DLC_FOLDERS = OrderedDict([
    ('Addons', 'Arma 3'),
    ('Curator', 'Zeus'),
    ('Kart', 'Karts'),
    ('Heli', 'Helicopters'),
    ('Mark', 'Marksmen'),
    ('Expansion', 'Apex'),
    ('Jets', 'Jets'),
    ('Argo', 'Malden'),
    ('Orange', 'Laws of War'),
    ('Tacops', 'Tac-Ops'),
    ('Tank', 'Tanks'),
    ('Contact', 'Contact'),
    ('Enoch', 'Contact'),
    ('AoW', 'Art of War'),
])

# ---------------------------------------------------------------------------------------------------------- PBO


def _cstr(f) -> bytes:
    out = bytearray()
    while True:
        c = f.read(1)
        if not c or c == b'\0':
            return bytes(out)
        out += c


def pbo_entries(path: str):
    """En-tête d'un PBO : liste (nom, mime, taille d'origine, taille stockée, position des données)."""
    entries = []
    with open(path, 'rb') as f:
        first = True
        while True:
            name = _cstr(f)
            head = f.read(20)
            if len(head) < 20:
                return []
            mime, orig, _res, _ts, size = struct.unpack('<5I', head)
            if name == b'' and first and mime == 0x56657273:
                while _cstr(f) != b'':
                    pass
                first = False
                continue
            first = False
            if name == b'':
                break
            entries.append((name.decode('latin-1').replace('\\', '/'), mime, orig, size))
        pos = f.tell()
    out = []
    for name, mime, orig, size in entries:
        out.append((name, mime, orig, size, pos))
        pos += size
    return out


def lzss(data: bytes, outlen: int) -> bytes:
    out = bytearray()
    i = 0
    while len(out) < outlen and i < len(data):
        flags = data[i]
        i += 1
        for bit in range(8):
            if len(out) >= outlen or i >= len(data):
                break
            if flags & (1 << bit):
                out.append(data[i])
                i += 1
            else:
                b1, b2 = data[i], data[i + 1]
                i += 2
                rpos = len(out) - (b1 | ((b2 & 0xF0) << 4))
                rlen = (b2 & 0x0F) + 3
                for k in range(rlen):
                    p = rpos + k
                    out.append(out[p] if 0 <= p < len(out) else 0x20)
    return bytes(out[:outlen])


def pbo_read(path: str, entry) -> bytes:
    name, mime, orig, size, pos = entry
    with open(path, 'rb') as f:
        f.seek(pos)
        data = f.read(size)
    if mime == 0x43707273 and orig and orig != size:
        data = lzss(data, orig)
    return data


# ------------------------------------------------------------------------------------------ configuration binarisée


def _rcstr(buf: bytes, off: int):
    end = buf.index(b'\0', off)
    return buf[off:end].decode('utf-8', 'replace'), end + 1


def _rcint(buf: bytes, off: int):
    n = 0
    shift = 0
    while True:
        b = buf[off]
        off += 1
        n |= (b & 0x7F) << shift
        if not b & 0x80:
            return n, off
        shift += 7


def _rarray(buf: bytes, off: int):
    n, off = _rcint(buf, off)
    out = []
    for _ in range(n):
        t = buf[off]
        off += 1
        if t in (0, 4):
            v, off = _rcstr(buf, off)
        elif t == 1:
            v = struct.unpack_from('<f', buf, off)[0]
            off += 4
        elif t == 2:
            v = struct.unpack_from('<i', buf, off)[0]
            off += 4
        elif t == 6:
            v = struct.unpack_from('<q', buf, off)[0]
            off += 8
        elif t == 3:
            v, off = _rarray(buf, off)
        else:
            raise ValueError('type de tableau inconnu %d' % t)
        out.append(v)
    return out, off


def parse_rap(buf: bytes):
    """Corps de classe : (parent, [(genre, nom, valeur)]) ; genre class|val|arr|arr+|del (les « class X; » sont ignorées)."""
    if buf[:4] != b'\0raP':
        return None

    def body(off: int):
        parent, off = _rcstr(buf, off)
        n, off = _rcint(buf, off)
        entries = []
        for _ in range(n):
            t = buf[off]
            off += 1
            if t == 0:
                name, off = _rcstr(buf, off)
                child = struct.unpack_from('<I', buf, off)[0]
                off += 4
                entries.append(('class', name, body(child)))
            elif t == 1:
                sub = buf[off]
                off += 1
                name, off = _rcstr(buf, off)
                if sub in (0, 4):
                    v, off = _rcstr(buf, off)
                elif sub == 1:
                    v = struct.unpack_from('<f', buf, off)[0]
                    off += 4
                elif sub == 2:
                    v = struct.unpack_from('<i', buf, off)[0]
                    off += 4
                elif sub == 6:
                    v = struct.unpack_from('<q', buf, off)[0]
                    off += 8
                else:
                    raise ValueError('type de valeur inconnu %d' % sub)
                entries.append(('val', name, v))
            elif t == 2:
                name, off = _rcstr(buf, off)
                v, off = _rarray(buf, off)
                entries.append(('arr', name, v))
            elif t == 3:
                _name, off = _rcstr(buf, off)
            elif t == 4:
                name, off = _rcstr(buf, off)
                entries.append(('del', name, None))
            elif t == 5:
                off += 4
                name, off = _rcstr(buf, off)
                v, off = _rarray(buf, off)
                entries.append(('arr+', name, v))
            else:
                raise ValueError('entrée inconnue %d' % t)
        return parent, entries

    return body(16)


class Node:
    __slots__ = ('name', 'parent', 'container', 'children', 'values', 'origin', '_pcache')

    def __init__(self, name: str, container, origin: str):
        self.name = name
        self.parent = ''
        self.container = container
        self.children: dict[str, Node] = {}
        self.values: dict[str, object] = {}
        self.origin = origin
        self._pcache = False

    def merge(self, parsed, origin: str):
        parent, entries = parsed
        if parent:
            self.parent = parent
        for kind, name, value in entries:
            key = name.lower()
            if kind == 'class':
                node = self.children.get(key)
                if node is None:
                    node = Node(name, self, origin)
                    self.children[key] = node
                node.merge(value, origin)
            elif kind == 'del':
                self.children.pop(key, None)
            elif kind == 'arr+':
                prev = self.values.get(key)
                if isinstance(prev, list):
                    self.values[key] = prev + list(value)
                elif isinstance(prev, tuple):
                    self.values[key] = ('+=', list(prev[1]) + list(value))
                else:
                    self.values[key] = ('+=', value)
            else:
                self.values[key] = value


def parent_of(node: Node):
    if node._pcache is not False:
        return node._pcache
    res = None
    if node.parent:
        key = node.parent.lower()
        c = node.container
        while c is not None:
            cand = child(c, key, exclude=node)
            if cand is not None:
                res = cand
                break
            c = c.container
    node._pcache = res
    return res


def child(node: Node, key: str, exclude=None, depth=0):
    """Classe enfant, y compris héritée des parents de la classe (comme le moteur)."""
    key = key.lower()
    seen = 0
    n = node
    while n is not None and seen < 64:
        c = n.children.get(key)
        if c is not None and c is not exclude:
            return c
        n = parent_of(n)
        seen += 1
    return None


def get(node: Node, key: str, default=None):
    key = key.lower()
    n = node
    seen = 0
    while n is not None and seen < 64:
        if key in n.values:
            v = n.values[key]
            if isinstance(v, tuple) and v and v[0] == '+=':
                base = get(parent_of(n), key, []) if parent_of(n) is not None else []
                return (base if isinstance(base, list) else []) + list(v[1])
            return v
        n = parent_of(n)
        seen += 1
    return default


def path(node: Node, *keys):
    for k in keys:
        if node is None:
            return None
        node = child(node, k)
    return node


def num(node, key, default=0.0):
    if node is None:
        return default
    v = get(node, key, default)
    if isinstance(v, (int, float)):
        return v
    try:
        return float(v)
    except (TypeError, ValueError):
        return default


def text(node, key, default=''):
    if node is None:
        return default
    v = get(node, key, default)
    return v if isinstance(v, str) else default


def arr(node, key):
    if node is None:
        return []
    v = get(node, key, [])
    return v if isinstance(v, list) else []


# --------------------------------------------------------------------------------------------------- lecture du jeu


def collect(arma: str):
    configs = []  # (fichier, dlc, arbre)
    strings: dict[str, dict[str, str]] = {}
    skipped_ebo = 0
    # DLC tiers chiffrés (Global Mobilization, S.O.G., Western Sahara…) : comptés, jamais lus.
    for sub in sorted(os.listdir(arma)):
        d = os.path.join(arma, sub, 'Addons')
        if os.path.isdir(d):
            skipped_ebo += sum(1 for fn in os.listdir(d) if fn.lower().endswith('.ebo'))
    # Configuration du moteur (Dta : classes Default, RifleCore…), puis jeu de base et DLC officiels.
    folders = [(os.path.join(arma, 'Dta'), 'Arma 3')]
    for folder, dlc in DLC_FOLDERS.items():
        folders.append((os.path.join(arma, folder, 'Addons') if folder != 'Addons' else os.path.join(arma, 'Addons'), dlc))
    for d, dlc in folders:
        if not os.path.isdir(d):
            continue
        for fn in sorted(os.listdir(d)):
            low = fn.lower()
            if not low.endswith('.pbo'):
                continue
            p = os.path.join(d, fn)
            try:
                entries = pbo_entries(p)
            except Exception as e:  # PBO illisible : ignoré
                print('  [!] %s : %s' % (fn, e), file=sys.stderr)
                continue
            for e in entries:
                base = e[0].rsplit('/', 1)[-1].lower()
                if base == 'config.bin':
                    try:
                        tree = parse_rap(pbo_read(p, e))
                    except Exception as ex:
                        print('  [!] %s/%s : %s' % (fn, e[0], ex), file=sys.stderr)
                        continue
                    if tree is not None:
                        configs.append((fn + '/' + e[0], dlc, tree))
                elif base == 'stringtable.xml':
                    read_strings(pbo_read(p, e), strings)
    return configs, strings, skipped_ebo


def read_strings(data: bytes, out: dict):
    try:
        root = ET.fromstring(data)
    except ET.ParseError:
        return
    for key in root.iter('Key'):
        kid = (key.get('ID') or '').strip().lower()
        if not kid:
            continue
        rec = {}
        for lang in ('Original', 'English', 'French'):
            el = key.find(lang)
            if el is not None and el.text:
                rec[lang] = el.text.strip()
        if rec:
            out[kid] = rec


def load_order(configs):
    """Tri topologique des config.bin selon CfgPatches >> requiredAddons (ordre de chargement du moteur)."""
    provides = {}
    reqs = []
    for i, (_f, _d, tree) in enumerate(configs):
        req = set()
        for kind, name, value in tree[1]:
            if kind == 'class' and name.lower() == 'cfgpatches':
                for k2, n2, v2 in value[1]:
                    if k2 == 'class':
                        provides.setdefault(n2.lower(), i)
                        for k3, n3, v3 in v2[1]:
                            if k3 == 'arr' and n3.lower() == 'requiredaddons':
                                req.update(str(x).lower() for x in v3)
        reqs.append(req)
    order = []
    state = [0] * len(configs)

    def visit(i):
        if state[i] == 2:
            return
        if state[i] == 1:
            return  # cycle : ordre de lecture
        state[i] = 1
        for r in sorted(reqs[i]):
            j = provides.get(r)
            if j is not None and j != i:
                visit(j)
        state[i] = 2
        order.append(i)

    for i in range(len(configs)):
        visit(i)
    return order


# ----------------------------------------------------------------------------------------------------- extraction


def display(node, strings):
    raw = text(node, 'displayName').strip()
    if raw.startswith('$'):
        rec = strings.get(raw[1:].lower())
        if not rec:
            return '', ''
        en = rec.get('English') or rec.get('Original') or ''
        fr = rec.get('French') or en
        return en, fr
    return raw, raw


WEAPON_SUB = [
    ('arifle_', 'AssaultRifle'), ('lmg_', 'MachineGun'), ('mmg_', 'MachineGun'), ('srifle_', 'SniperRifle'),
    ('sgun_', 'Shotgun'), ('smg_', 'SubmachineGun'), ('hgun_', 'Handgun'),
]

SLOTS = [('muzzle', 'MuzzleSlot'), ('optic', 'CowsSlot'), ('pointer', 'PointerSlot'), ('bipod', 'UnderBarrelSlot')]


def weapon_sub(cls: str, node, wtype: int):
    low = cls.lower()
    cursor = text(node, 'cursor').lower()
    if wtype == 2:
        return 'Handgun'
    if wtype == 4:
        if 'missile' in cursor or text(node, 'lockingTargetSound') or num(node, 'canLock', 0) >= 1 or 'titan' in low:
            return 'MissileLauncher'
        if 'rocket' in cursor or 'rpg' in low or 'nlaw' in low or 'maaws' in low or 'mraws' in low:
            return 'RocketLauncher'
        return 'Launcher'
    for prefix, sub in WEAPON_SUB:
        if low.startswith(prefix):
            return sub
    if cursor in ('mg',):
        return 'MachineGun'
    if cursor in ('srifle',):
        return 'SniperRifle'
    if cursor in ('sgun',):
        return 'Shotgun'
    if cursor in ('smg',):
        return 'SubmachineGun'
    if 'gl_' in low or 'glauncher' in low:
        return 'GrenadeLauncher'
    return 'Rifle' if wtype == 1 else ''


ITEM_INFO_TYPES = {
    101: ('Item', 'AccessoryMuzzle'), 201: ('Item', 'AccessorySights'), 301: ('Item', 'AccessoryPointer'),
    302: ('Item', 'AccessoryBipod'), 401: ('Item', 'FirstAidKit'), 605: ('Equipment', 'Headgear'),
    616: ('Item', 'NVGoggles'), 619: ('Item', 'Medikit'), 620: ('Item', 'Toolkit'), 621: ('Item', 'UAVTerminal'),
    701: ('Equipment', 'Vest'), 801: ('Equipment', 'Uniform'),
}
SIMULATION_TYPES = {
    'itemmap': 'Map', 'itemgps': 'GPS', 'itemradio': 'Radio', 'itemcompass': 'Compass', 'itemwatch': 'Watch',
    'itemminedetector': 'MineDetector', 'binocular': 'Binocular', 'nvgoggles': 'NVGoggles',
}

AMMO_SIM = {
    'shotbullet': ('Magazine', 'Bullet'), 'shotspread': ('Magazine', 'Bullet'), 'shotgrenade': ('Magazine', 'Grenade'),
    'shotsmoke': ('Magazine', 'SmokeShell'), 'shotsmokex': ('Magazine', 'SmokeShell'),
    'shotilluminating': ('Magazine', 'Flare'), 'shotshell': ('Magazine', 'Shell'), 'shotsubmunitions': ('Magazine', 'Shell'),
    'shotrocket': ('Magazine', 'Rocket'), 'shotmissile': ('Magazine', 'Missile'),
    'shotmine': ('Mine', 'Mine'), 'shotboundingmine': ('Mine', 'MineBounding'), 'shotdirectionalbomb': ('Mine', 'MineDirectional'),
    'shotnvgmarker': ('Magazine', 'Flare'), 'shotcm': ('Magazine', 'Flare'),
}


def ints(values):
    out = []
    for v in values:
        try:
            n = int(round(float(v)))
        except (TypeError, ValueError):
            continue
        if 0 < n < 100000 and n not in out:
            out.append(n)
    return out[:8]


def compat_items(slot):
    """Accessoires d'un emplacement : tableau compatibleItems[] et/ou classe compatibleItems { classe = 1; }."""
    out = []
    if slot is None:
        return out
    out.extend(str(x) for x in arr(slot, 'compatibleItems') if isinstance(x, str))
    cls = child(slot, 'compatibleItems')
    chain = []
    n = cls
    while n is not None and len(chain) < 32:
        chain.append(n)
        n = parent_of(n)
    flags = {}
    for n in reversed(chain):
        for k, v in n.values.items():
            flags[k] = v
    for k, v in flags.items():
        try:
            on = float(v) >= 1
        except (TypeError, ValueError):
            on = False
        if on:
            out.append(k)
    return out


def extract(arma: str):
    configs, strings, ebo = collect(arma)
    print('config.bin lus : %d · textes : %d · DLC chiffrés ignorés : %d' % (len(configs), len(strings), ebo), file=sys.stderr)
    root = Node('', None, '')
    for i in load_order(configs):
        f, dlc, tree = configs[i]
        root.merge(tree, dlc)

    weapons = child(root, 'CfgWeapons')
    mags = child(root, 'CfgMagazines')
    vehicles = child(root, 'CfgVehicles')
    glasses = child(root, 'CfgGlasses')
    ammo = child(root, 'CfgAmmo')
    wells = child(root, 'CfgMagazineWells')

    items = OrderedDict()  # classe minuscule -> ligne

    def max_load(container_cls):
        if not container_cls or vehicles is None:
            return None
        v = child(vehicles, container_cls)
        n = num(v, 'maximumLoad', 0) if v is not None else 0
        return int(round(n)) if n > 0 else None

    def magazine_list(wnode):
        out = []
        out.extend(arr(wnode, 'magazines'))
        for wname in arr(wnode, 'magazineWell'):
            well = child(wells, str(wname)) if wells is not None else None
            if well is None:
                continue
            seen = set()
            n = well
            while n is not None and id(n) not in seen:
                seen.add(id(n))
                for v in n.values.values():
                    if isinstance(v, list):
                        out.extend(x for x in v if isinstance(x, str))
                n = parent_of(n)
        return out

    weapon_mags = {}
    weapon_compat = {}
    referenced_mags = set()

    # Armes, accessoires, objets assignés, casques, gilets, uniformes.
    for key, node in (weapons.children.items() if weapons else []):
        if int(num(node, 'scope', 0)) != 2:
            continue
        cls = node.name
        base = text(node, 'baseWeapon')
        wtype = int(num(node, 'type', 0))
        sim = text(node, 'simulation').lower()
        info = child(node, 'ItemInfo')
        wsi = child(node, 'WeaponSlotsInfo')
        tmain = tsub = ''
        slot_type = None
        if wtype in (1, 2, 4):
            if base and base.lower() != key:
                continue  # variante avec accessoires montés : la base suffit
            tmain, tsub, slot_type = 'Weapon', weapon_sub(cls, node, wtype), wtype
        elif sim in SIMULATION_TYPES:
            tmain, tsub = 'Item', SIMULATION_TYPES[sim]
            if tsub == 'Binocular' and 'laserdesignator' in key:
                tsub = 'LaserDesignator'
        elif wtype == 4096 and 'laserdesignator' in key:
            tmain, tsub = 'Item', 'LaserDesignator'
        elif info is not None and int(num(info, 'type', 0)) in ITEM_INFO_TYPES:
            tmain, tsub = ITEM_INFO_TYPES[int(num(info, 'type', 0))]
        else:
            continue
        en, fr = display(node, strings)
        if not en:
            continue
        mass = num(info, 'mass', 0) if info is not None and get(info, 'mass') is not None else num(wsi, 'mass', 0)
        load = None
        if tsub in ('Uniform', 'Vest') and info is not None:
            load = max_load(text(info, 'containerClass'))
        allowed = ints(arr(wsi if tmain == 'Weapon' else info, 'allowedSlots'))
        row = [cls, 'w', tmain, tsub, en, fr, round(float(mass), 3), load, slot_type, None, allowed, None, None, node.origin]
        items[key] = row
        if tmain == 'Weapon':
            ml = list(magazine_list(node))
            for mz in arr(node, 'muzzles'):
                if isinstance(mz, str) and mz.lower() != 'this':
                    ml.extend(magazine_list(child(node, mz)))
            weapon_mags[key] = ml
            referenced_mags.update(m.lower() for m in ml if isinstance(m, str))
            weapon_compat[key] = {s: compat_items(child(wsi, cname)) for s, cname in SLOTS} if wsi is not None else None

    # Grenades à lancer et explosifs à poser (muzzles des armes « Throw » et « Put »).
    for special in ('Throw', 'Put'):
        sp = child(weapons, special) if weapons else None
        for mz in arr(sp, 'muzzles'):
            m = child(sp, str(mz))
            referenced_mags.update(str(x).lower() for x in arr(m, 'magazines'))

    # Chargeurs et munitions utilisés par une arme portée (les chargeurs de véhicules sont écartés).
    for key, node in (mags.children.items() if mags else []):
        if key not in referenced_mags or int(num(node, 'scope', 0)) != 2:
            continue
        en, fr = display(node, strings)
        if not en:
            continue
        a = child(ammo, text(node, 'ammo')) if ammo is not None else None
        tmain, tsub = AMMO_SIM.get(text(a, 'simulation').lower(), ('Magazine', ''))
        count = int(num(node, 'count', 0)) or None
        allowed = ints(arr(node, 'allowedSlots'))
        items[key] = [node.name, 'm', tmain, tsub, en, fr, round(float(num(node, 'mass', 0)), 3), None, None, count, allowed, None, None, node.origin]

    # Sacs à dos.
    for key, node in (vehicles.children.items() if vehicles else []):
        if int(num(node, 'scope', 0)) != 2 or int(num(node, 'isBackpack', 0)) != 1:
            continue
        en, fr = display(node, strings)
        if not en:
            continue
        load = int(round(num(node, 'maximumLoad', 0))) or None
        items[key] = [node.name, 'v', 'Equipment', 'Backpack', en, fr, round(float(num(node, 'mass', 0)), 3), load, None, None, [], None, None, node.origin]

    # Lunettes et cagoules.
    for key, node in (glasses.children.items() if glasses else []):
        if int(num(node, 'scope', 0)) != 2:
            continue
        en, fr = display(node, strings)
        if not en or key == 'none':
            continue
        items[key] = [node.name, 'g', 'Equipment', 'Glasses', en, fr, round(float(num(node, 'mass', 0)), 3), None, None, None, [], None, None, node.origin]

    # Compatibilités : seulement les objets présents dans le catalogue (casse d'origine), listes dédoublonnées.
    groups = []
    gindex = {}

    def group(lst):
        seen = OrderedDict()
        for c in lst:
            k = str(c).lower()
            if k in items and k not in seen:
                seen[k] = items[k][0]
        vals = sorted(seen.values(), key=str.lower)
        sig = '|'.join(vals).lower()
        if sig not in gindex:
            gindex[sig] = len(groups)
            groups.append(vals)
        return gindex[sig]

    for key, row in items.items():
        if row[2] != 'Weapon':
            continue
        comp = weapon_compat.get(key)
        if comp is not None:
            row[11] = [group(comp[s]) for s, _ in SLOTS]
        row[12] = group(weapon_mags.get(key, []))

    rows = sorted(items.values(), key=lambda r: (r[1], r[2], r[3], r[0].lower()))
    digest = hashlib.sha256(json.dumps([rows, groups], ensure_ascii=False, separators=(',', ':')).encode('utf-8')).hexdigest()[:16]
    game = ''
    ver = os.path.join(arma, 'arma3_x64.exe')
    if os.path.isfile(ver):
        game = 'arma3_x64.exe %d' % os.path.getsize(ver)
    dlcs = sorted({r[13] for r in rows})
    return {
        'format': 'comspec-vanilla-catalog',
        'v': 1,
        'version': digest,
        'game': game,
        'dlc': dlcs,
        'skipped_encrypted': ebo,
        'groups': groups,
        'items': rows,
    }


def stats(doc):
    fam = OrderedDict()
    for r in doc['items']:
        k = (r[2] + '/' + r[3]) if r[3] else r[2]
        fam[k] = fam.get(k, 0) + 1
    return fam


def main():
    ap = argparse.ArgumentParser(description=__doc__.split('\n\n')[0])
    ap.add_argument('--arma', default=r'F:\SteamLibrary\steamapps\common\Arma 3')
    ap.add_argument('--out', default=os.path.join('database', 'data', 'arma3_catalogue_vanilla.json'))
    args = ap.parse_args()
    if not os.path.isdir(os.path.join(args.arma, 'Addons')):
        print('Installation d’Arma 3 introuvable : %s' % args.arma, file=sys.stderr)
        return 2
    doc = extract(args.arma)
    os.makedirs(os.path.dirname(os.path.abspath(args.out)), exist_ok=True)
    body = json.dumps(doc, ensure_ascii=False, separators=(',', ':'))
    with open(args.out, 'w', encoding='utf-8', newline='\n') as f:
        f.write(body)
        f.write('\n')
    print('%d objets, %d groupes, %d octets -> %s' % (len(doc['items']), len(doc['groups']), len(body.encode('utf-8')), args.out), file=sys.stderr)
    for k, n in sorted(stats(doc).items()):
        print('  %-28s %5d' % (k, n), file=sys.stderr)
    return 0


if __name__ == '__main__':
    sys.exit(main())
