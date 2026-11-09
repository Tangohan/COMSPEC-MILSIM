"""
Modèles 3D des lunettes de sport COMSPEC SSE (addon eyewear) : géométrie paramétrique écrite en MLOD (P3D non binarisé).

    PYTHONUTF8=1 python -I gen_p3d.py            # écrit addons/eyewear/data/models/*.p3d et model.cfg
    PYTHONUTF8=1 python -I gen_p3d.py --stats    # nombre de faces / triangles par LOD, sans rien écrire

Format MLOD (community.bistudio.com « P3D File Format - MLOD », recoupé avec le lecteur / écrivain de l'extension
Blender « Arma 3 Object Builder » et les P3D déjà livrés par l'ATAK natif) :
  "MLOD", version 257, nombre de LODs ; chaque LOD "P3DM" 28/256, points (x, y, z, flags), normales, faces (3 ou 4
  sommets : point, normale, u, v ; flags, texture, matériau), "TAGG" (sélections, #SharpEdges#, #Property#, #Mass#,
  #UVSet#), #EndOfFile#, puis la résolution du LOD (1 visuel, 1e4 ombre, 1e13 géométrie).
  Conventions vérifiées sur les P3D de l'ATAK (exportés par Object Builder pour Blender, utilisés en jeu) : l'ordre des
  sommets d'une face est horaire vu de l'extérieur dans le repère Arma (X droite, Y haut, Z avant) et la normale
  stockée pointe vers l'intérieur ; v = 0 en haut de la texture.

Trois modèles par coloris (tous à partir du même maillage, repère local = milieu des pupilles, voir eyewear_spec) :
  ew_<coloris>.p3d       slot lunettes (CfgGlasses), dans le repère du personnage (HEAD_OFFSET), pondéré sur « head »
  ew_<coloris>_nvg.p3d   slot JVN (proxy « nvg », même repère que les lunettes + NVG_OFFSET), pondéré sur « head »
  ew_<coloris>_head.p3d  slot virtuel COMSPEC (objet accroché par attachTo), centré sur les yeux (+ ATTACH_OFFSET)
"""
import math
import os
import struct
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
import eyewear_spec as S  # noqa: E402

OUT = os.path.normpath(os.path.join(HERE, "..", "..", "addons", "eyewear", "data", "models"))

# Régions de l'atlas de la monture (u0, v0, u1, v1), v = 0 en haut. Même découpage dans gen_textures.py.
ATLAS = {
    "frame": (0.0, 0.0, 1.0, 0.375),
    "temple": (0.0, 0.375, 1.0, 0.625),
    "nose": (0.0, 0.625, 0.5, 0.75),
    "pod": (0.5, 0.625, 1.0, 0.75),
    "logo": (0.0, 0.75, 0.5, 0.875),
    "logoside": (0.5, 0.75, 1.0, 0.875),
    "pads": (0.5, 0.875, 1.0, 1.0),
}
INSET = 4.0 / 1024.0


# ------------------------------------------------------------------ vecteurs
def add(a, b):
    return (a[0] + b[0], a[1] + b[1], a[2] + b[2])


def sub(a, b):
    return (a[0] - b[0], a[1] - b[1], a[2] - b[2])


def mul(a, k):
    return (a[0] * k, a[1] * k, a[2] * k)


def dot(a, b):
    return a[0] * b[0] + a[1] * b[1] + a[2] * b[2]


def cross(a, b):
    return (a[1] * b[2] - a[2] * b[1], a[2] * b[0] - a[0] * b[2], a[0] * b[1] - a[1] * b[0])


def norm(a):
    n = math.sqrt(dot(a, a))
    return (0.0, 0.0, 0.0) if n < 1e-12 else (a[0] / n, a[1] / n, a[2] / n)


def lerp(a, b, k):
    return add(a, mul(sub(b, a), k))


def smoothstep(k):
    k = min(1.0, max(0.0, k))
    return k * k * (3 - 2 * k)


def region_uv(name, u, v):
    u0, v0, u1, v1 = ATLAS[name]
    u0, v0, u1, v1 = u0 + INSET, v0 + INSET, u1 - INSET, v1 - INSET
    return (u0 + (u1 - u0) * min(1.0, max(0.0, u)), v0 + (v1 - v0) * min(1.0, max(0.0, v)))


# ------------------------------------------------------------------ maillage
class Mesh:
    """Points (position, groupe de lissage) et faces (indices dans l'ordre anti-horaire vu de l'extérieur, uv, matière).
    Les arêtes vives viennent de points dédoublés (groupes différents) ; les normales sont lissées par groupe."""

    def __init__(self):
        self.pts = []
        self.grp = []
        self.faces = []  # (idx, uvs, kind)
        self._g = 0

    def group(self):
        self._g += 1
        return self._g

    def pt(self, p, g):
        self.pts.append(tuple(p))
        self.grp.append(g)
        return len(self.pts) - 1

    def face(self, idx, uvs, kind, out=None):
        if out is not None:
            a, b, c = (self.pts[i] for i in idx[:3])
            n = cross(sub(b, a), sub(c, a))
            if dot(n, n) < 1e-24 and len(idx) == 4:
                a, b, c = (self.pts[i] for i in (idx[0], idx[2], idx[3]))
                n = cross(sub(b, a), sub(c, a))
            if dot(n, out) < 0:
                idx, uvs = idx[::-1], uvs[::-1]
        self.faces.append((list(idx), list(uvs), kind))

    def extend(self, other):
        base = len(self.pts)
        gbase = self._g
        self.pts += other.pts
        self.grp += [g + gbase for g in other.grp]
        self._g += other._g
        for idx, uvs, kind in other.faces:
            self.faces.append(([i + base for i in idx], uvs, kind))

    def mirrored(self, flip_u_kinds=()):
        m = Mesh()
        m.pts = [(-x, y, z) for x, y, z in self.pts]
        m.grp = list(self.grp)
        m._g = self._g
        for idx, uvs, kind in self.faces:
            uv2 = uvs
            if kind in flip_u_kinds:
                # Texte du logo lisible des deux côtés : u retourné sur toute la région de l'atlas.
                lo, hi = ATLAS[kind][0], ATLAS[kind][2]
                uv2 = [(lo + hi - u, v) for u, v in uvs]
            m.faces.append((idx[::-1], uv2[::-1], kind))
        return m

    def moved(self, off):
        m = Mesh()
        m.pts = [add(p, off) for p in self.pts]
        m.grp, m._g, m.faces = list(self.grp), self._g, list(self.faces)
        return m

    def normals(self):
        acc = [(0.0, 0.0, 0.0)] * len(self.pts)
        for idx, _, _ in self.faces:
            ps = [self.pts[i] for i in idx]
            n = (0.0, 0.0, 0.0)
            for k in range(1, len(ps) - 1):
                n = add(n, cross(sub(ps[k], ps[0]), sub(ps[k + 1], ps[0])))
            for i in idx:
                acc[i] = add(acc[i], n)
        # Soudure : points confondus d'un même groupe (couture d'UV d'un profil fermé) -> même normale.
        weld = {}
        for i, p in enumerate(self.pts):
            key = (self.grp[i], round(p[0], 7), round(p[1], 7), round(p[2], 7))
            weld.setdefault(key, []).append(i)
        out = list(acc)
        for ids in weld.values():
            if len(ids) > 1:
                s = (0.0, 0.0, 0.0)
                for i in ids:
                    s = add(s, acc[i])
                for i in ids:
                    out[i] = s
        res = []
        for n in out:
            n = norm(n)
            res.append(n if n != (0.0, 0.0, 0.0) else (0.0, 0.0, 1.0))
        return res

    def welded(self, eps=1e-6):
        """Points confondus fusionnés (volumes fermés : LOD d'ombre et de géométrie), faces dégénérées retirées."""
        m = Mesh()
        g = m.group()
        keys, remap = {}, {}
        for i, p in enumerate(self.pts):
            k = (round(p[0] / eps), round(p[1] / eps), round(p[2] / eps))
            if k not in keys:
                keys[k] = m.pt(p, g)
            remap[i] = keys[k]
        for idx, uvs, kind in self.faces:
            ni = [remap[i] for i in idx]
            if len(set(ni)) == len(ni):
                m.faces.append((ni, uvs, kind))
        return m

    def tris(self):
        return sum(len(f[0]) - 2 for f in self.faces)


# ------------------------------------------------------------------ verre (surface torique)
class Lens:
    def __init__(self, shape):
        self.shape = shape
        p = S.SHAPES[shape]
        self.p = p
        self.W, self.Rh, self.Rv, self.z0 = p["W"], p["Rh"], p["Rv"], p["z0"]
        self.tilt = math.tan(math.radians(p["tilt"]))
        self.th = p["th"]

    def surf(self, s, t):
        th = s / self.Rh
        x = self.Rh * math.sin(th)
        z = self.z0 - self.Rh * (1.0 - math.cos(th)) - t * t / (2.0 * self.Rv) + t * self.tilt
        return (x, t, z)

    def frame(self, s, t):
        h = 1e-5
        ds = mul(sub(self.surf(s + h, t), self.surf(s - h, t)), 0.5 / h)
        dt = mul(sub(self.surf(s, t + h), self.surf(s, t - h)), 0.5 / h)
        return ds, dt, norm(cross(ds, dt))

    def normal(self, s, t):
        return self.frame(s, t)[2]

    def mid(self, s, t, off=0.0):
        """Point du plan moyen du verre décalé de off le long de la normale (avant +)."""
        return add(self.surf(s, t), mul(self.normal(s, t), off - self.th * 0.5))

    def outline(self, s):
        return S.outline(self.shape, abs(s) / self.W)

    def uv(self, s, t):
        p = self.p
        return ((s + self.W) / (2.0 * self.W), (p["t_hi"] - t) / (p["t_hi"] - p["t_lo"]))

    def columns(self, fine=1.0):
        a_n = S.notch_a(self.shape)
        a_e = 0.86
        n1, n2, n3 = int(9 * fine), int(16 * fine), int(9 * fine)
        half = [a_n * i / n1 for i in range(n1)]
        half += [a_n + (a_e - a_n) * i / n2 for i in range(n2)]
        half += [a_e + (1.0 - a_e) * math.sin(0.5 * math.pi * i / n3) for i in range(n3 + 1)]
        full = [-a * self.W for a in reversed(half[1:])] + [a * self.W for a in half]
        return full


def build_lens(L, rows=12, fine=1.0, kind="lens"):
    m = Mesh()
    cols = L.columns(fine)
    J, R = len(cols), rows
    gf, gb, ge = m.group(), m.group(), m.group()
    grid = []
    for s in cols:
        top, bot = L.outline(s)
        grid.append([(s, bot + (top - bot) * i / R) for i in range(R + 1)])
    F = [[m.pt(L.surf(*grid[j][i]), gf) for i in range(R + 1)] for j in range(J)]
    B = [[m.pt(add(L.surf(*grid[j][i]), mul(L.normal(*grid[j][i]), -L.th)), gb) for i in range(R + 1)] for j in range(J)]
    for j in range(J - 1):
        for i in range(R):
            st = [grid[j][i], grid[j + 1][i], grid[j + 1][i + 1], grid[j][i + 1]]
            uvs = [L.uv(*q) for q in st]
            n = L.normal(*grid[j][i])
            m.face([F[j][i], F[j + 1][i], F[j + 1][i + 1], F[j][i + 1]], uvs, kind, out=n)
            m.face([B[j][i], B[j][i + 1], B[j + 1][i + 1], B[j + 1][i]], [uvs[0], uvs[3], uvs[2], uvs[1]], kind, out=mul(n, -1))
    # Tranche : boucle du contour (bas gauche->droite, bout droit, haut droite->gauche, bout gauche).
    loop = [(j, 0) for j in range(J)] + [(J - 1, i) for i in range(1, R + 1)] + \
           [(j, R) for j in range(J - 2, -1, -1)] + [(0, i) for i in range(R - 1, 0, -1)]
    st2 = [grid[j][i] for j, i in loop]
    ef = [m.pt(L.surf(*q), ge) for q in st2]
    eb = [m.pt(add(L.surf(*q), mul(L.normal(*q), -L.th)), ge) for q in st2]
    n = len(loop)
    for k in range(n):
        k2 = (k + 1) % n
        (s0, t0), (s1, t1) = st2[k], st2[k2]
        d = (s1 - s0, t1 - t0)
        o2 = (d[1], -d[0])  # extérieur d'un contour parcouru en sens anti-horaire vu de face
        ds, dt, _ = L.frame(0.5 * (s0 + s1), 0.5 * (t0 + t1))
        out = add(mul(norm(ds), o2[0]), mul(norm(dt), o2[1]))
        uvs = [L.uv(s0, t0), L.uv(s0, t0), L.uv(s1, t1), L.uv(s1, t1)]
        m.face([ef[k], eb[k], eb[k2], ef[k2]], uvs, kind, out=out)
    return m


# ------------------------------------------------------------------ balayage d'un profil le long d'un chemin
def superellipse(hb, hn, k=12, e=0.55, cb=0.0, cn=0.0):
    """Profil fermé (pb, pn) anti-horaire dans le plan (B, N), départ en haut (B+)."""
    pts = []
    for i in range(k):
        a = 2.0 * math.pi * i / k
        c, s = math.cos(a), math.sin(a)
        pts.append((cb + hb * math.copysign(abs(c) ** e, c), cn + hn * math.copysign(abs(s) ** e, s)))
    return pts


def sweep(m, path, hints, profiles, region, kind="frame", closed=False, caps=(True, True), sharp=False, us=None):
    M = len(path)
    if us is None:
        L = [0.0]
        for i in range(1, M):
            L.append(L[-1] + math.sqrt(dot(sub(path[i], path[i - 1]), sub(path[i], path[i - 1]))))
        us = [x / L[-1] for x in L]
    frames = []
    for i in range(M):
        if closed:
            T = norm(sub(path[(i + 1) % M], path[(i - 1) % M]))
        else:
            T = norm(sub(path[min(M - 1, i + 1)], path[max(0, i - 1)]))
        h = hints[i]
        N = norm(sub(h, mul(T, dot(h, T))))
        B = cross(N, T)
        frames.append((T, B, N))
    K = len(profiles[0])
    rings = []  # rings[i] = liste de (point, v) avec doublons pour arêtes vives / couture
    gsmooth = m.group()
    gside = [m.group() for _ in range(K)] if sharp else None
    ring_pos = []
    for i in range(M + (1 if closed else 0)):
        ii = i % M
        T, B, N = frames[ii]
        prof = profiles[ii]
        ring_pos.append([add(path[ii], add(mul(B, pb), mul(N, pn))) for pb, pn in prof])
    nr = len(ring_pos)
    if sharp:
        idx = [[(m.pt(ring_pos[i][k], gside[k]), m.pt(ring_pos[i][(k + 1) % K], gside[k])) for k in range(K)] for i in range(nr)]
    else:
        idx = [[m.pt(ring_pos[i][k % K], gsmooth) for k in range(K + 1)] for i in range(nr)]
    for i in range(nr - 1):
        ui, uj = (us[i] if i < M else 1.0), (us[i + 1] if i + 1 < M else 1.0)
        cen = add(path[i % M], add(mul(frames[i % M][1], sum(p[0] for p in profiles[i % M]) / K),
                                   mul(frames[i % M][2], sum(p[1] for p in profiles[i % M]) / K)))
        for k in range(K):
            v0, v1 = k / K, (k + 1) / K
            if sharp:
                a, b = idx[i][k]
                c, d = idx[i + 1][k]
                quad = [a, b, d, c]
            else:
                quad = [idx[i][k], idx[i][k + 1], idx[i + 1][k + 1], idx[i + 1][k]]
            uvs = [region_uv(region, ui, v0), region_uv(region, ui, v1), region_uv(region, uj, v1), region_uv(region, uj, v0)]
            pm = mul(add(add(m.pts[quad[0]], m.pts[quad[1]]), add(m.pts[quad[2]], m.pts[quad[3]])), 0.25)
            m.face(quad, uvs, kind, out=sub(pm, cen))
    if not closed:
        for end, flag in ((0, caps[0]), (M - 1, caps[1])):
            if not flag:
                continue
            T, B, N = frames[end]
            g = m.group()
            ps = ring_pos[end]
            c = mul(ps[0], 0.0)
            for p in ps:
                c = add(c, mul(p, 1.0 / K))
            ci = m.pt(c, g)
            pi = [m.pt(p, g) for p in ps]
            uvc = region_uv(region, us[end], 0.5)
            out = T if end == M - 1 else mul(T, -1)
            for k in range(K):
                m.face([ci, pi[k], pi[(k + 1) % K]], [uvc, uvc, uvc], kind, out=out)
    return m


def catmull(ctrl, n):
    """Courbe de Catmull-Rom (centripète simplifiée) passant par les points de contrôle, n points."""
    P = [ctrl[0]] + list(ctrl) + [ctrl[-1]]
    segs = len(ctrl) - 1
    out = []
    for k in range(n):
        x = k / (n - 1) * segs
        i = min(segs - 1, int(x))
        t = x - i
        p0, p1, p2, p3 = P[i], P[i + 1], P[i + 2], P[i + 3]
        t2, t3 = t * t, t * t * t
        out.append(tuple(0.5 * ((2 * p1[j]) + (-p0[j] + p2[j]) * t + (2 * p0[j] - 5 * p1[j] + 4 * p2[j] - p3[j]) * t2
                                + (-p0[j] + 3 * p1[j] - 3 * p2[j] + p3[j]) * t3) for j in range(3)))
    return out


# ------------------------------------------------------------------ pièces de monture
def offset_loop(pts2, d):
    """Décale un contour 2D (s, t) parcouru en sens horaire vu de face, vers l'extérieur, de d."""
    n = len(pts2)
    out = []
    for i in range(n):
        a, b, c = pts2[i - 1], pts2[i], pts2[(i + 1) % n]
        n1 = norm((-(b[1] - a[1]), b[0] - a[0], 0.0))
        n2 = norm((-(c[1] - b[1]), c[0] - b[0], 0.0))
        nn = norm(add(n1, n2))
        k = max(0.5, dot(nn, n1))
        out.append((b[0] + nn[0] * d / k, b[1] + nn[1] * d / k))
    return out


def top_bar(m, L, cfg):
    """Barre haute (Radar, Wind) prolongée au-delà du bout du verre en embout de charnière ; renvoie les charnières."""
    W, ext = L.W, L.p["ext"]
    cols = [s for s in L.columns(1.0)]
    n_ext = 7
    right = [W + ext * smoothstep((i + 1) / n_ext) * 1.0 for i in range(n_ext)]
    ss = [-s for s in reversed(right)] + cols + right
    top1, bot1 = L.outline(W)
    t_end = 0.5 * (top1 + bot1) + cfg["t_end"]
    path, hints, profs = [], [], []
    for s in ss:
        a = abs(s) / W
        if a <= 1.0:
            t = L.outline(s)[0] + cfg["dt"]
            k = 0.0
        else:
            k = smoothstep((abs(s) - W) / ext)
            t = (L.outline(W)[0] + cfg["dt"]) * (1 - k) + t_end * k
        kk = max(0.0, (a - cfg["grow_from"]) / (1.0 - cfg["grow_from"])) if a <= 1.0 else 1.0
        kk = smoothstep(kk) * 0.6 + 0.4 * k if a <= 1.0 else 0.6 + 0.4 * k
        hb = cfg["hb"] + (cfg["hb_pod"] - cfg["hb"]) * kk
        hn = cfg["hn"] + (cfg["hn_pod"] - cfg["hn"]) * kk
        path.append(L.mid(min(abs(s), W + ext) * (1 if s >= 0 else -1), t, cfg["dn"]))
        hints.append(L.normal(s, t))
        profs.append(superellipse(hb, hn, cfg.get("k", 12), cfg.get("e", 0.55)))
    sweep(m, path, hints, profs, "frame", us=[(s + W + ext) / (2 * (W + ext)) for s in ss])
    hinge = path[-1]
    return hinge, t_end


def nose_piece(m, L, cfg, region="nose"):
    """Plaquette de nez le long de l'échancrure (parcours droite -> gauche, côté extérieur du verre = B+)."""
    a_n = S.notch_a(L.shape) + cfg.get("over", 0.05)
    n = 25
    ss = [L.W * a_n * (1 - 2 * i / (n - 1)) for i in range(n)]
    path, hints, profs = [], [], []
    for s in ss:
        t = L.outline(s)[1]
        path.append(L.mid(s, t, 0.0))
        hints.append(L.normal(s, t))
        profs.append(superellipse(cfg["hb"], cfg["hn"], 10, 0.6, cfg["cb"], cfg["cn"]))
    sweep(m, path, hints, profs, region)


def nose_pads(m, L, cfg):
    """Coussinets noirs derrière la plaquette (Wind) : deux pièces sur le bas des flancs de l'échancrure."""
    a_n = S.notch_a(L.shape)
    for side in (1, -1):
        n = 8
        ss = [side * L.W * (a_n * 0.35 + (a_n + 0.04 - a_n * 0.35) * i / (n - 1)) for i in range(n)]
        if side > 0:
            ss = ss[::-1]
        path, hints, profs = [], [], []
        for s in ss:
            t = L.outline(s)[1]
            path.append(L.mid(s, t, 0.0))
            hints.append(L.normal(s, t))
            profs.append(superellipse(cfg["hb"], cfg["hn"], 8, 0.6, cfg["cb"], cfg["cn"]))
        sweep(m, path, hints, profs, "pads")


def full_frame(m, L, cfg):
    """Cadre complet (Hurricane) autour du verre, profil anguleux, plus pont vertical et embouts de charnière."""
    cols = L.columns(1.0)
    W = L.W
    loop2 = [(s, L.outline(s)[0]) for s in cols] + [(s, L.outline(s)[1]) for s in reversed(cols)]
    # Supprime les doublons (bout du verre très court).
    clean = []
    for p in loop2:
        if not clean or abs(p[0] - clean[-1][0]) + abs(p[1] - clean[-1][1]) > 1e-5:
            clean.append(p)
    off = offset_loop(clean, cfg["d"])
    path = [L.mid(s, t, cfg["dn"]) for s, t in off]
    hints = [L.normal(s, t) for s, t in off]
    profs = [superellipse(cfg["hb"], cfg["hn"], 8, 0.25) for _ in off]
    sweep(m, path, hints, profs, "frame", closed=True, sharp=True)
    # Pont vertical (verre séparé en deux au centre).
    top0, bot0 = L.outline(0.0)
    n = 8
    pts = [(0.0, bot0 + (top0 - bot0) * i / (n - 1)) for i in range(n)]
    sweep(m, [L.mid(s, t, cfg["dn"]) for s, t in pts], [L.normal(s, t) for s, t in pts],
          [superellipse(cfg["bridge"], cfg["hn"] * 0.9, 8, 0.3) for _ in pts], "frame", sharp=True)
    # Embouts : du coin haut extérieur vers la charnière.
    top1, bot1 = L.outline(W)
    t_h = top1 - cfg["pod_dt"]
    ext = L.p["ext"]
    n = 6
    ss = [W - 0.006 + (ext + 0.006) * i / (n - 1) for i in range(n)]
    path = [L.mid(s, t_h, cfg["dn"]) for s in ss]
    sweep(m, path, [L.normal(s, t_h) for s in ss], [superellipse(cfg["pod_hb"], cfg["pod_hn"], 8, 0.3) for _ in ss], "pod", sharp=True)
    return path[-1], t_h


def temple(L, hinge, cid, cfg):
    """Branche droite : tube aplati balayé (droit puis courbe derrière l'oreille), plaque logo côté extérieur."""
    m = Mesh()
    hx, hy, hz = hinge
    xt = L.p["xt"]
    ctrl = [
        (hx - 0.0012, hy, hz + 0.004),
        (hx + 0.0035, hy - 0.0002, hz - 0.016),
        (xt, hy - 0.0012, -0.052),
        (xt + 0.0012, hy - 0.0040, -0.082),
        (xt - 0.0015, hy - 0.0125, -0.105),
        (xt - 0.0065, hy - 0.0260, -0.1205),
    ]
    path = catmull(ctrl, 30)
    hints = [(0.0, 1.0, 0.0)] * len(path)
    L_ = [0.0]
    for i in range(1, len(path)):
        L_.append(L_[-1] + math.sqrt(dot(sub(path[i], path[i - 1]), sub(path[i], path[i - 1]))))
    us = [x / L_[-1] for x in L_]
    profs = []
    for u in us:
        hh = cfg["h0"] + (cfg["h1"] - cfg["h0"]) * smoothstep(u * 1.15)
        ht = cfg["t0"] + (cfg["t1"] - cfg["t0"]) * u
        if cfg.get("sock_from") is not None and u > cfg["sock_from"]:
            k = smoothstep((u - cfg["sock_from"]) / 0.06)
            hh += 0.0006 * k
            ht += 0.0007 * k
        if u > 0.93:
            k = (u - 0.93) / 0.07
            hh *= 1.0 - 0.35 * k
        profs.append(superellipse(ht, hh, 12, cfg.get("e", 0.5)))
    sweep(m, path, hints, profs, "temple", us=us)
    # Plaque logo, côté extérieur (+X sur la branche droite) : B = N x T = haut x arrière = -X.
    u0, u1 = cfg["logo"]
    sub_ = [i for i, u in enumerate(us) if u0 <= u <= u1]
    i0, i1 = sub_[0], sub_[-1]
    nseg = 10
    pp, fr = [], []
    for k in range(nseg + 1):
        x = i0 + (i1 - i0) * k / nseg
        i = min(len(path) - 2, int(x))
        f = x - i
        p = lerp(path[i], path[i + 1], f)
        T = norm(sub(path[i + 1], path[i]))
        N = norm(sub((0.0, 1.0, 0.0), mul(T, T[1])))
        B = cross(N, T)
        ht = cfg["t0"] + (cfg["t1"] - cfg["t0"]) * (us[i] + (us[i + 1] - us[i]) * f)
        pp.append(p)
        fr.append((T, B, N, ht))
    g_face, g_side = m.group(), m.group()
    hp = cfg["logo_h"]
    top_o, bot_o, top_i, bot_i = [], [], [], []
    for (p, (T, B, N, ht)) in zip(pp, fr):
        o = add(p, mul(B, -(ht + cfg["logo_raise"])))
        ii = add(p, mul(B, -(ht * 0.6)))
        top_o.append(add(o, mul(N, hp)))
        bot_o.append(add(o, mul(N, -hp)))
        top_i.append(add(ii, mul(N, hp)))
        bot_i.append(add(ii, mul(N, -hp)))
    out_dir = mul(fr[nseg // 2][1], -1)
    NR = 4
    G = [[m.pt(lerp(top_o[k], bot_o[k], r / NR), g_face) for k in range(nseg + 1)] for r in range(NR + 1)]
    for r in range(NR):
        va, vb = r / NR, (r + 1) / NR
        for k in range(nseg):
            u_a, u_b = k / nseg, (k + 1) / nseg
            m.face([G[r][k], G[r][k + 1], G[r + 1][k + 1], G[r + 1][k]],
                   [region_uv("logo", u_a, va), region_uv("logo", u_b, va), region_uv("logo", u_b, vb), region_uv("logo", u_a, vb)],
                   "logo", out=out_dir)
    # Flancs de la plaque.
    cen = mul(add(pp[nseg // 2], mul(fr[nseg // 2][1], -fr[nseg // 2][3])), 1.0)
    uvs = [region_uv("logoside", 0.5, 0.5)] * 4
    for outer, inner in ((top_o, top_i), (bot_o, bot_i)):
        g = m.group()
        o = [m.pt(p, g) for p in outer]
        n_ = [m.pt(p, g) for p in inner]
        for k in range(nseg):
            pm = mul(add(outer[k], outer[k + 1]), 0.5)
            m.face([o[k], o[k + 1], n_[k + 1], n_[k]], uvs, "frame", out=sub(pm, cen))
    for k, (T, sgn) in ((0, (fr[0][0], -1)), (nseg, (fr[-1][0], 1))):
        g = m.group()
        q = [m.pt(top_o[k], g), m.pt(bot_o[k], g), m.pt(bot_i[k], g), m.pt(top_i[k], g)]
        m.face(q, uvs, "frame", out=mul(T, sgn))
    return m


# ------------------------------------------------------------------ assemblage
FRAME_CFG = {
    "radar": dict(bar=dict(dt=0.0012, dn=0.0006, hb=0.0029, hn=0.0031, hb_pod=0.0058, hn_pod=0.0044, grow_from=0.70,
                           t_end=0.0035, k=12, e=0.55),
                  nose=dict(hb=0.0026, hn=0.0042, cb=0.0010, cn=-0.0030, over=0.05),
                  temple=dict(h0=0.0056, h1=0.0032, t0=0.0019, t1=0.0016, sock_from=0.30, logo=(0.03, 0.20),
                              logo_h=0.0028, logo_raise=0.0004)),
    "wind": dict(bar=dict(dt=0.0018, dn=0.0006, hb=0.0039, hn=0.0040, hb_pod=0.0050, hn_pod=0.0046, grow_from=0.80,
                          t_end=0.0040, k=12, e=0.45),
                 nose=dict(hb=0.0030, hn=0.0045, cb=0.0012, cn=-0.0025, over=0.05),
                 pads=dict(hb=0.0030, hn=0.0032, cb=0.0006, cn=-0.0075),
                 temple=dict(h0=0.0046, h1=0.0033, t0=0.0019, t1=0.0017, sock_from=0.78, logo=(0.10, 0.34),
                             logo_h=0.0024, logo_raise=0.0004, e=0.4)),
    "hurricane": dict(frame=dict(d=0.0016, dn=0.0004, hb=0.0026, hn=0.0034, bridge=0.0011, pod_dt=0.0040,
                                 pod_hb=0.0042, pod_hn=0.0036),
                      temple=dict(h0=0.0052, h1=0.0035, t0=0.0020, t1=0.0018, sock_from=None, logo=(0.06, 0.32),
                                  logo_h=0.0026, logo_raise=0.0003, e=0.3)),
}


def build_visual(cid):
    c = S.COLOURS[cid]
    shape = c["shape"]
    L = Lens(shape)
    cfg = FRAME_CFG[shape]
    m = build_lens(L)
    fr = Mesh()
    if shape == "hurricane":
        hinge, _ = full_frame(fr, L, cfg["frame"])
    else:
        hinge, _ = top_bar(fr, L, cfg["bar"])
        nose_piece(fr, L, cfg["nose"])
        if "pads" in cfg:
            nose_pads(fr, L, cfg["pads"])
    m.extend(fr)
    tr = temple(L, hinge, cid, cfg["temple"])
    m.extend(tr)
    m.extend(tr.mirrored(flip_u_kinds=("logo",)))
    return m


def build_shadow(cid):
    """LOD d'ombre : volumes fermés grossiers (verre + barre, branches), triangulés, sans texture."""
    c = S.COLOURS[cid]
    L = Lens(c["shape"])
    m = Mesh()
    W = L.W
    n = 13
    cols = [-W + 2 * W * i / (n - 1) for i in range(n)]
    g = m.group()
    top = [m.pt(L.mid(s, L.outline(s)[0] + 0.003, 0.002), g) for s in cols]
    bot = [m.pt(L.mid(s, L.outline(s)[1], 0.002), g) for s in cols]
    topb = [m.pt(L.mid(s, L.outline(s)[0] + 0.003, -0.002), g) for s in cols]
    botb = [m.pt(L.mid(s, L.outline(s)[1], -0.002), g) for s in cols]
    z4 = [(0, 0)] * 4
    for j in range(n - 1):
        sm = 0.5 * (cols[j] + cols[j + 1])
        ds, dt, nn = L.frame(sm, 0.0)
        m.face([bot[j], bot[j + 1], top[j + 1], top[j]], z4, "shadow", out=nn)
        m.face([botb[j], topb[j], topb[j + 1], botb[j + 1]], z4, "shadow", out=mul(nn, -1))
        m.face([top[j], top[j + 1], topb[j + 1], topb[j]], z4, "shadow", out=dt)
        m.face([bot[j], botb[j], botb[j + 1], bot[j + 1]], z4, "shadow", out=mul(dt, -1))
    for j, sgn in ((0, -1), (n - 1, 1)):
        ds = L.frame(cols[j], 0.0)[0]
        m.face([top[j], bot[j], botb[j], topb[j]], z4, "shadow", out=mul(ds, sgn))
    # Branches : tubes à 4 côtés.
    cfgt = FRAME_CFG[c["shape"]]["temple"]
    for side in (1, -1):
        tm = Mesh()
        hinge = _hinge_of(cid)
        hx, hy, hz = hinge
        xt = L.p["xt"]
        ctrl = [(hx, hy, hz), (xt, hy - 0.0012, -0.052), (xt + 0.0012, hy - 0.004, -0.082), (xt - 0.0065, hy - 0.026, -0.1205)]
        path = catmull(ctrl, 7)
        profs = [superellipse(cfgt["t1"] * 0.8, cfgt["h1"] * 0.45, 4, 1.0) for _ in path]
        sweep(tm, path, [(0.0, 1.0, 0.0)] * len(path), profs, "frame", kind="shadow")
        if side < 0:
            tm = tm.mirrored()
        m.extend(tm)
    tri = Mesh()
    tri.pts, tri.grp, tri._g = m.pts, m.grp, m._g
    for idx, uvs, kind in m.faces:
        for k in range(1, len(idx) - 1):
            tri.faces.append(([idx[0], idx[k], idx[k + 1]], [(0.0, 0.0)] * 3, "shadow"))
    return tri.welded()


def _orient_closed(m):
    """Oriente les faces d'un volume fermé convexe vers l'extérieur (par rapport à son centre)."""
    cen = (0.0, 0.0, 0.0)
    for p in m.pts:
        cen = add(cen, mul(p, 1.0 / len(m.pts)))
    faces = m.faces
    m.faces = []
    for idx, uvs, kind in faces:
        pm = (0.0, 0.0, 0.0)
        for i in idx:
            pm = add(pm, mul(m.pts[i], 1.0 / len(idx)))
        m.face(idx, uvs, kind, out=sub(pm, cen))


_HINGES = {}


def _hinge_of(cid):
    if cid not in _HINGES:
        c = S.COLOURS[cid]
        L = Lens(c["shape"])
        cfg = FRAME_CFG[c["shape"]]
        tmp = Mesh()
        if c["shape"] == "hurricane":
            _HINGES[cid] = full_frame(tmp, L, cfg["frame"])[0]
        else:
            _HINGES[cid] = top_bar(tmp, L, cfg["bar"])[0]
    return _HINGES[cid]


def build_geometry():
    """LOD géométrie : petit cube (masse, autocenter=0) au centre ; les lunettes n'ont pas de collision utile."""
    m = Mesh()
    g = m.group()
    h = 0.002
    P = [m.pt((x, y, z), g) for x in (-h, h) for y in (-h, h) for z in (-h, h)]
    for q in ([0, 1, 3, 2], [4, 6, 7, 5], [0, 4, 5, 1], [2, 3, 7, 6], [0, 2, 6, 4], [1, 5, 7, 3]):
        m.face([P[i] for i in q], [(0, 0)] * 4, "geo")
    _orient_closed(m)
    return m


# ------------------------------------------------------------------ écriture MLOD
def _asciiz(s):
    return s.encode("ascii") + b"\0"


def _tagg(name, data):
    return b"\x01" + _asciiz(name) + struct.pack("<I", len(data)) + data


def lod_bytes(m, res, materials, selections=(), props=(), mass=None, sharp_all=False, uvset=True):
    nrm = m.normals()
    out = bytearray(b"P3DM")
    out += struct.pack("<6I", 28, 256, len(m.pts), len(m.pts), len(m.faces), 0)
    for x, y, z in m.pts:
        out += struct.pack("<fffI", x, y, z, 0)
    for n in nrm:
        out += struct.pack("<fff", -n[0], -n[1], -n[2])
    uvdata = bytearray(struct.pack("<I", 0))
    for idx, uvs, kind in m.faces:
        tex, mat = materials.get(kind, ("", ""))
        ridx, ruv = idx[::-1], uvs[::-1]  # horaire vu de l'extérieur (repère Arma)
        out += struct.pack("<I", len(ridx))
        for i, (u, v) in zip(ridx, ruv):
            out += struct.pack("<IIff", i, i, u, v)
            uvdata += struct.pack("<ff", u, v)
        if len(ridx) == 3:
            out += bytes(16)
        out += struct.pack("<I", 0)
        out += _asciiz(tex) + _asciiz(mat)
    out += b"TAGG"
    if sharp_all:
        edges = bytearray()
        for idx, _, _ in m.faces:
            for k in range(len(idx)):
                edges += struct.pack("<II", idx[k], idx[(k + 1) % len(idx)])
        out += _tagg("#SharpEdges#", bytes(edges))
    if uvset:
        out += _tagg("#UVSet#", bytes(uvdata))
    for key, val in props:
        out += _tagg("#Property#", key.encode().ljust(64, b"\0") + val.encode().ljust(64, b"\0"))
    if mass is not None:
        out += _tagg("#Mass#", b"".join(struct.pack("<f", mass / len(m.pts)) for _ in m.pts))
    # Sélections : nom -> None (tous les points et toutes les faces, poids 100 %) ou liste d'indices de points.
    # Poids MLOD d'un point : octet 0 = hors sélection, 1 = 100 % (2..255 : poids décroissants).
    sel_map = dict(selections) if isinstance(selections, dict) else {n: None for n in selections}
    for sel, ids in sel_map.items():
        if ids is None:
            out += _tagg(sel, bytes([1]) * len(m.pts) + bytes([1]) * len(m.faces))
        else:
            pts = bytearray(len(m.pts))
            for i in ids:
                pts[i] = 1
            faces = bytes(1 if all(pts[i] for i in idx) else 0 for idx, _, _ in m.faces)
            out += _tagg(sel, bytes(pts) + faces)
    out += b"\x01" + _asciiz("#EndOfFile#") + struct.pack("<I", 0)
    out += struct.pack("<f", res)
    return bytes(out)


def materials_for(cid):
    p = S.PREFIX
    frame = (p + cid + "_frame_co.paa", p + cid + "_frame.rvmat")
    return {"frame": frame, "logo": frame, "lens": (p + cid + "_lens_ca.paa", p + cid + "_lens.rvmat")}


def write_p3d(path, lods):
    data = bytearray(b"MLOD") + struct.pack("<II", 257, len(lods))
    for b in lods:
        data += b
    with open(path, "wb") as f:
        f.write(data)
    return len(data)


VARIANTS = {
    "": (S.HEAD_OFFSET, True),
    "_nvg": (add(S.HEAD_OFFSET, S.NVG_OFFSET), True),
    "_head": (S.ATTACH_OFFSET, False),
}


# Os de la chaîne pelvis -> neck1 du squelette OFP2_ManSkeleton (indices 0 à 6 ; « head » est l'index 7).
ANCHOR_BONES = ["pelvis", "spine", "spine1", "spine2", "spine3", "neck", "neck1"]


def add_bone_anchors(m, kinds, at, size=0.00015):
    """Ancres d'os : un tétraèdre fermé minuscule (0,15 mm, caché dans la barre) par os pelvis..neck1 et par matière.

    Binarize (Arma 3 Tools 2025) construit le sous-squelette d'une section avec les seuls os utilisés puis y range
    l'os par son index dans le squelette complet : une section pondérée uniquement sur « head » (index 7, sous-squelette
    de 1 os) donne « Subskeleton index out of range » et, en jeu, un maillage déformé par un os quelconque (seuls les
    bouts des branches restaient visibles). Avec un point sur chaque os 0..6, le sous-squelette de chaque section est
    pelvis..head, l'index de « head » y est le même que dans le squelette : plus d'avertissement, pondération juste.
    Renvoie {os: [indices des points]} ; tous les autres points vont sur « head »."""
    bones = {}
    for bone in ANCHOR_BONES:
        ids = []
        for kind in kinds:
            g = m.group()
            P = [m.pt(add(at, d), g) for d in ((0, 0, 0), (size, 0, 0), (0, size, 0), (0, 0, size))]
            for f in ((0, 2, 1), (0, 1, 3), (0, 3, 2), (1, 2, 3)):
                m.face([P[i] for i in f], [(0.5, 0.5)] * 3, kind)
            ids += P
        bones[bone] = ids
    return bones


def skin_map(m, bones):
    """Sélections de pondération : ancres sur leur os, tout le reste sur « head »."""
    taken = set(i for ids in bones.values() for i in ids)
    sel = {"head": [i for i in range(len(m.pts)) if i not in taken]}
    sel.update(bones)
    return sel


def anchor_point(cid):
    """Point caché dans la monture (centre de la barre haute ou du cadre), repère local."""
    L = Lens(S.COLOURS[cid]["shape"])
    top = L.outline(0.0)[0]
    return L.mid(0.0, top + 0.0012, 0.0006)


def build_all(cid):
    vis = build_visual(cid)
    sh = build_shadow(cid)
    geo = build_geometry()
    return vis, sh, geo


def model_cfg(names):
    bones = []
    with open(os.path.join(HERE, "ofp2_manskeleton.txt"), encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if line:
                a, b = line.split(",")
                bones.append('            "%s", "%s"' % (a, b))
    lines = [
        "// Généré par tools/eyewear/gen_p3d.py : ne pas modifier à la main.",
        "// Squelette OFP2_ManSkeleton relevé dans les lunettes vanilla (a3\\characters_f_beta\\heads\\glasses\\g_sport_red.p3d) :",
        "// les modèles des slots lunettes et JVN sont pondérés sur l'os « head » et suivent la tête comme les lunettes vanilla.",
        "class CfgSkeletons {",
        "    class Default { isDiscrete = 1; skeletonInherit = \"\"; skeletonBones[] = {}; };",
        "    class OFP2_ManSkeleton {",
        "        isDiscrete = 0;",
        "        skeletonInherit = \"\";",
        "        skeletonBones[] = {",
        ",\n".join(bones),
        "        };",
        "    };",
        "};",
        "class CfgModels {",
        "    class Default { sectionsInherit = \"\"; sections[] = {}; skeletonName = \"\"; };",
        "    class ArmaMan: Default { sections[] = {}; skeletonName = \"OFP2_ManSkeleton\"; };",
    ]
    for n, skinned in names:
        lines.append("    class %s: %s {};" % (n, "ArmaMan" if skinned else "Default"))
    lines.append("};")
    return "\n".join(lines) + "\n"


def write_variant(name, cid, vis, sh, geo, off, skinned, stats):
    mats = materials_for(cid)
    v, s_, g_ = vis.moved(off), sh.moved(off), geo.moved(off)
    sel_v, sel_s = (), ()
    if skinned:
        at = add(anchor_point(cid), off)
        sel_v = skin_map(v, add_bone_anchors(v, ("frame", "lens"), at))
        sel_s = skin_map(s_, add_bone_anchors(s_, ("shadow",), at))
    lods = [
        lod_bytes(v, 1.0, mats, selections=sel_v, props=(("lodnoshadow", "1"),)),
        lod_bytes(s_, 10000.0, {}, selections=sel_s, sharp_all=True, uvset=False),
        # Géométrie : sans pondération (aucune collision utile, ne sert qu'à autocenter=0 et à la masse).
        lod_bytes(g_, 1e13, {}, selections=("component01",), props=(("autocenter", "0"),), mass=0.03,
                  sharp_all=True, uvset=False),
    ]
    if stats:
        print("%-28s visuel %5d faces %5d triangles %5d points | ombre %4d triangles" % (
            name, len(v.faces), v.tris(), len(v.pts), s_.tris()))
        return
    size = write_p3d(os.path.join(OUT, name + ".p3d"), lods)
    print("écrit %s.p3d (%d Ko, %d triangles)" % (name, size // 1024, v.tris()))


# Variantes de CALIBRATION (temporaires, test en jeu seulement) : Radar blanche décalée vers l'avant (Z) ou plus large.
# (étiquette, décalage Z ajouté à HEAD_OFFSET, écart des branches xt ou None, pondérée sur « head »)
CALIBRATION = [
    ("z03", 0.03, None, True),
    ("z06", 0.06, None, True),
    ("z09", 0.09, None, True),
    ("z12", 0.12, None, True),
    ("w", 0.06, 0.082, True),
    ("noskin", 0.0, None, False),
]


def main():
    stats = "--stats" in sys.argv
    os.makedirs(OUT, exist_ok=True)
    names = []
    for cid in S.ORDER:
        vis, sh, geo = build_all(cid)
        for suffix, (off, skinned) in VARIANTS.items():
            name = "ew_" + cid + suffix
            names.append((name, skinned))
            write_variant(name, cid, vis, sh, geo, off, skinned, stats)
    xt0 = S.SHAPES["radar"]["xt"]
    for tag, dz, xt, skinned in CALIBRATION:
        S.SHAPES["radar"]["xt"] = xt if xt is not None else xt0
        _HINGES.clear()
        vis, sh, geo = build_all("radar_white")
        name = "ew_cal_" + tag
        names.append((name, skinned))
        write_variant(name, "radar_white", vis, sh, geo, add(S.HEAD_OFFSET, (0.0, 0.0, dz)), skinned, stats)
    S.SHAPES["radar"]["xt"] = xt0
    _HINGES.clear()
    if not stats:
        with open(os.path.join(OUT, "model.cfg"), "w", encoding="utf-8", newline="\n") as f:
            f.write(model_cfg(names))
        print("écrit model.cfg")


if __name__ == "__main__":
    main()
