"""
Pièces des modèles « relais » : treillis (cornières ou tubes, croix de Saint-André, K, zigzag), massifs et platines,
échelle à crinoline, chemin de câbles, plates-formes à caillebotis et garde-corps, haubans (ridoirs, massifs d'ancrage),
antennes (panneau de secteur + RRU, faisceau hertzien, fouet colinéaire, radio MANET), balisage, paratonnerre, armoires,
abri, groupe électrogène, panneau solaire, clôture, sacs de sable, caisses. Dimensions réelles (m).

Chaque pièce se dessine selon m.d (0 : de près ... 4 : silhouette) et dépose ses volumes simples (ombre, collision).
Matières : relay_tex.py (st_* : acier ; cb_* : câbles ; le reste : atlas des équipements).
"""
import math

from relay3d import (add_prism_solid, angle, basis, beam, bolt, box, cable, catenary, lathe, plate, prism, quad, rot,
                     solid_box, solid_hull8, tube, vadd, vcross, vdot, vlen, vlerp, vmul, vnorm, vsub)

UP = (0.0, 0.0, 1.0)


def band_mat(z, H, bands=7):
    """Bande de peinture A / B selon la hauteur (7 bandes, A en pied et en tête : balisage rouge-blanc)."""
    return "st_a" if int(max(0.0, min(0.9999, z / H)) * bands) % 2 == 0 else "st_b"


# --------------------------------------------------------------------------------------------------------------------
# Treillis
# --------------------------------------------------------------------------------------------------------------------
class Lattice:
    """Pylône treillis à n jambes (3 ou 4). widths : [(z, largeur de face)] ; panels : cotes des nœuds ;
    leg : ("angle", aile bas, aile haut) ou ("tube", rayon bas, rayon haut) ; brace : (aile bas, aile haut) ;
    pattern : fonction (index de panneau, z) -> "X", "K" ou "Z"."""

    def __init__(self, n, widths, panels, leg, brace, pattern="X", H=None, z0=0.0, rod=False):
        self.n, self.widths, self.panels, self.leg, self.brace = n, widths, panels, leg, brace
        self.pattern = pattern if callable(pattern) else (lambda k, z: pattern)
        self.H = H or panels[-1]
        self.z0 = z0
        self.rod = rod      # entretoises en rond plein (pylône haubané léger)
        self.phi = [math.radians((90 if n == 3 else 45) + i * 360.0 / n) for i in range(n)]

    def width(self, z):
        w = self.widths
        if z <= w[0][0]:
            return w[0][1]
        for (z0, w0), (z1, w1) in zip(w, w[1:]):
            if z <= z1:
                return w0 + (w1 - w0) * (z - z0) / (z1 - z0)
        return w[-1][1]

    def radius(self, z):
        return self.width(z) / (math.sqrt(3) if self.n == 3 else math.sqrt(2))

    def inradius(self, z):
        return self.width(z) / (2 * math.sqrt(3)) if self.n == 3 else self.width(z) / 2

    def corner(self, i, z):
        r, p = self.radius(z), self.phi[i % self.n]
        return (r * math.cos(p), r * math.sin(p), z)

    def out(self, i):
        p = self.phi[i % self.n]
        return (math.cos(p), math.sin(p), 0.0)

    def face_n(self, i):
        a, b = self.out(i), self.out(i + 1)
        return vnorm((a[0] + b[0], a[1] + b[1], 0.0))

    def face_mid(self, i, z, off=0.0):
        a, b = self.corner(i, z), self.corner(i + 1, z)
        return vadd(vmul(vadd(a, b), 0.5), vmul(self.face_n(i), off))

    def t(self, z):
        return max(0.0, min(1.0, (z - self.z0) / max(1e-6, self.H - self.z0)))

    def leg_size(self, z):
        return self.leg[1] + (self.leg[2] - self.leg[1]) * self.t(z)

    def brace_size(self, z):
        return self.brace[0] + (self.brace[1] - self.brace[0]) * self.t(z)

    def build(self, m, bands=7, footing=True, top_plate=True):
        d = m.d
        P = self.panels
        H = self.H
        breaks = {z for z, _ in self.widths}
        # --- Jambes : un tronçon par panneau (bandes de peinture), volumes simples par tronçon de conicité ---
        segs = [P[0]] + [z for z in P[1:-1] if z in breaks] + [P[-1]]
        for i in range(self.n):
            for z0, z1 in zip(P, P[1:]):
                mat = band_mat((z0 + z1) / 2, H, bands)
                c0, c1 = self.corner(i, z0), self.corner(i, z1)
                s = self.leg_size(z0)
                if d >= 3 and not (z0 in segs):
                    continue
                if d >= 3:
                    z1 = min(z for z in segs if z > z0)
                    c1 = self.corner(i, z1)
                if self.leg[0] == "tube":
                    tube(m, c0, c1, s, mat, n=(10, 8, 6, 4, 3)[d], r1=self.leg_size(z1) if d < 3 else s * 0.8, caps=(False, False))
                else:
                    na, nb = vsub(self.corner(i - 1, z0), c0), vsub(self.corner(i + 1, z0), c0)
                    o = vmul(self.out(i), s * 0.05)
                    angle(m, vadd(c0, o), vadd(c1, o), s, mat, na, nb, t=s * 0.1)
            for z0, z1 in zip(segs, segs[1:]):
                s = self.leg_size(z0)
                c0, c1 = self.corner(i, z0), self.corner(i, z1)
                a = self.out(i)
                b = vcross(UP, a)
                k = s * (0.9 if self.leg[0] == "tube" else 0.6)
                add_prism_solid(m, c0, c1, a, b, [(k, 0), (-k * 0.5, k * 0.87), (-k * 0.5, -k * 0.87)], "sSgf")
            # Brides de raboutage des jambes tubulaires / éclisses des cornières, tous les 6 m environ.
            if d <= 1:
                last = P[0]
                for z in P[1:-1]:
                    if z - last < 5.5:
                        continue
                    last = z
                    c = self.corner(i, z)
                    s = self.leg_size(z)
                    if self.leg[0] == "tube":
                        with m.push(t=c):
                            lathe(m, [(s, -0.022), (s + 0.055, -0.022), (s + 0.055, 0.022), (s, 0.022)], "st_plate", n=10 if d == 0 else 6)
                            if d == 0:
                                for k in range(8):
                                    a = k * math.pi / 4
                                    bolt(m, ((s + 0.032) * math.cos(a), (s + 0.032) * math.sin(a), 0.022), UP, 0.012, 0.014, "st_pipe")
                    else:
                        o = self.out(i)
                        for j in (i - 1, i + 1):
                            al = vnorm(vsub(self.corner(j, z), c))
                            nf = vcross(al, UP) if vdot(vcross(al, UP), o) > 0 else vcross(UP, al)
                            p0 = vadd(vadd(c, vmul(o, s * 0.05)), vmul(al, s * 0.12))
                            plate(m, [vadd(p0, (0, 0, -0.22)), vadd(vadd(p0, vmul(al, s * 0.8)), (0, 0, -0.22)),
                                      vadd(vadd(p0, vmul(al, s * 0.8)), (0, 0, 0.22)), vadd(p0, (0, 0, 0.22))], 0.012, "st_plate", nf)
        # --- Faces : membrures horizontales, diagonales, goussets ---
        for k, (z0, z1) in enumerate(zip(P, P[1:])):
            if self.rod and d >= 3:
                step = 2 if d == 3 else 4
                if k % step:
                    continue
                z1 = P[min(k + step, len(P) - 1)]
            pat = self.pattern(k, z0)
            mat = band_mat((z0 + z1) / 2, H, bands)
            s = self.brace_size(z0)
            for i in range(self.n):
                nf = self.face_n(i)
                inw = vmul(nf, -1)
                a0, b0, a1, b1 = self.corner(i, z0), self.corner(i + 1, z0), self.corner(i, z1), self.corner(i + 1, z1)
                ins = self.leg_size(z0) * (1.0 if self.leg[0] == "tube" else 0.5)

                def seg(p, q, off=0.0, flip=False, S="", size=s, mat=mat):
                    if self.rod:
                        S = ""      # ronds de 2 cm : pas d'ombre portée utile
                    dv = vnorm(vsub(q, p))
                    p2, q2 = vadd(vadd(p, vmul(dv, ins)), vmul(inw, off)), vadd(vsub(q, vmul(dv, ins)), vmul(inw, off))
                    if d >= 4:
                        w = vmul(vnorm(vcross(nf, dv)), size * 0.7)
                        _ribbon(m, p2, q2, w, mat)
                    elif self.rod:
                        tube(m, p2, q2, size * 0.5, mat, n=(5, 4, 3, 3)[d], caps=(False, False), S=S)
                    else:
                        angle(m, p2, q2, size, mat, vcross(nf, dv) if not flip else vcross(dv, nf), inw, S=S)
                horiz = (k % (1, 1, 1, 2, 99)[d] == 0) or z0 == P[0]
                if d == 4:
                    horiz = False
                if horiz and pat != "N":
                    seg(a0, b0, 0.0, S="s" if k % 2 == 0 else "")
                if k == len(P) - 2 and d < 4:
                    seg(a1, b1, 0.0)
                if pat == "X":
                    seg(a0, b1, s * 0.04, S="s")
                    if d <= 2:
                        seg(b0, a1, s * 0.16, flip=True)
                    elif d == 3 and k % 2 == 0:
                        seg(b0, a1, s * 0.16, flip=True)
                    if d == 0:
                        c = vlerp(a0, b1, 0.5)
                        e, f = vnorm(vsub(b1, a0)), vnorm(vsub(a1, b0))
                        g = 0.11
                        plate(m, [vadd(c, vmul(e, -g)), vadd(c, vmul(f, -g)), vadd(c, vmul(e, g)), vadd(c, vmul(f, g))], 0.008, "st_plate", inw)
                elif pat == "K":
                    mid = vlerp(a1, b1, 0.5)
                    seg(a0, mid, s * 0.04, S="s")
                    seg(b0, mid, s * 0.04, flip=True, S="s" if d < 3 else "")
                    if d <= 1:
                        # contreventement secondaire : du milieu de chaque diagonale à la jambe et à la membrure basse
                        for p, q, leg0, leg1 in ((a0, mid, a0, a1), (b0, mid, b0, b1)):
                            h = vlerp(p, q, 0.5)
                            seg(h, vlerp(leg0, leg1, 0.5), s * 0.2, size=s * 0.6)
                else:
                    (p, q) = (a0, b1) if (k + i) % 2 == 0 else (b0, a1)
                    seg(p, q, s * 0.04, S="s")
                # Goussets aux nœuds (de près) : platine boulonnée dans le plan de la face, contre la jambe.
                if d <= 1 and not self.rod and z0 > P[0]:
                    for c, o_ in ((a0, b0), (b0, a0)):
                        al = vnorm(vsub(o_, c))
                        g0 = vadd(vadd(c, vmul(al, ins * 0.6)), vmul(inw, -0.004))
                        w, h = 0.20 + s * 1.2, 0.16 + s * 0.9
                        plate(m, [vadd(g0, (0, 0, -h * 0.5)), vadd(vadd(g0, vmul(al, w)), (0, 0, -h * 0.25)),
                                  vadd(vadd(g0, vmul(al, w)), (0, 0, h * 0.25)), vadd(g0, (0, 0, h * 0.5))], 0.008, "st_plate", nf)
            # Diaphragme (contreventement de plan) aux cassures de conicité.
            if d <= 1 and z1 in breaks and self.n == 4 and z1 < P[-1]:
                for i in (0, 1):
                    p, q = self.corner(i, z1), self.corner(i + 2, z1)
                    dv = vnorm(vsub(q, p))
                    angle(m, vadd(p, vmul(dv, 0.15)), vsub(q, vmul(dv, 0.15)), s * 0.8, mat, vcross(dv, UP), (0, 0, -1))
        # --- Massifs, platines d'ancrage ---
        if footing:
            with m.push(tag="ground"):
                for i in range(self.n):
                    c = self.corner(i, P[0])
                    s = self.leg_size(P[0])
                    with m.push(R=rot("z", math.degrees(self.phi[i])), t=(c[0], c[1], 0.0)):
                        pier(m, 0.55 + s * 2.2, P[0], s)
        if top_plate and d <= 2:
            z = P[-1]
            pts = [self.corner(i, z) for i in range(self.n)]
            m.face([vadd(p, (0, 0, 0.012)) for p in pts], band_mat(z, H, bands), out=UP)
            m.face(pts, band_mat(z, H, bands), out=(0, 0, -1))


def _ribbon(m, p, q, w, mat):
    """Membrure réduite à un ruban visible des deux côtés (LOD lointaine)."""
    m.face([vsub(p, w), vadd(p, w), vadd(q, w), vsub(q, w)], mat)
    m.face([vsub(q, w), vadd(q, w), vadd(p, w), vsub(p, w)], mat)


def pier(m, w, top, leg, depth=2.2, bolts=4):
    """Massif béton (enterré de depth : rattrape les pentes), platine boulonnée, tiges d'ancrage et écrous."""
    h = w / 2
    box(m, -h, h, -h, h, -depth, top - 0.03, "concrete", S="gfv", skip="-z")
    if m.d <= 2:
        # chanfrein de mortier de calage et platine
        p = min(h * 0.72, 0.16 + leg * 1.6)
        box(m, -p - 0.03, p + 0.03, -p - 0.03, p + 0.03, top - 0.03, top - 0.012, "concrete", skip="-z")
        box(m, -p, p, -p, p, top - 0.012, top + 0.016, "st_plate", skip="-z")
    if m.d == 0:
        p = min(h * 0.72, 0.16 + leg * 1.6) - 0.06
        for sx in (-1, 1):
            for sy in (-1, 1):
                tube(m, (sx * p, sy * p, top), (sx * p, sy * p, top + 0.11), 0.015, "st_rust", n=6, caps=(False, True))
                tube(m, (sx * p, sy * p, top + 0.016), (sx * p, sy * p, top + 0.05), 0.03, "st_pipe", n=6, caps=(False, True))
        # raidisseurs triangulaires du pied
        for a in (45, 135, 225, 315):
            with m.push(R=rot("z", a)):
                plate(m, [(leg * 0.5, -0.004, top + 0.016), (p + 0.03, -0.004, top + 0.016), (leg * 0.5, -0.004, top + 0.2)], 0.008, "st_plate", (0, 1, 0))


# --------------------------------------------------------------------------------------------------------------------
# Échelle à crinoline, chemin de câbles
# --------------------------------------------------------------------------------------------------------------------
def ladder(m, p0, p1, out, cage_from=2.6, rung=0.28, width=0.42, cage=True, mat="st_pipe"):
    """Échelle droite de p0 à p1 (montants plats, barreaux ronds), crinoline à partir de cage_from au-dessus de p0 ;
    out : côté du grimpeur."""
    d = m.d
    if d >= 4:
        return
    up = vnorm(vsub(p1, p0))
    L = vlen(vsub(p1, p0))
    out = vnorm(vsub(out, vmul(up, vdot(out, up))))
    side = vcross(up, out)
    for s in (-1, 1):
        o = vmul(side, s * width / 2)
        beam(m, vadd(p0, o), vadd(p1, o), 0.012, 0.06, mat, a=side, caps=False, S="s" if d == 2 else "")
    if d <= 2:
        n = int(L / rung)
        step = (1, 1, 3)[d]
        for k in range(1, n, step):
            c = vadd(p0, vmul(up, k * rung))
            a, b = vadd(c, vmul(side, -width / 2)), vadd(c, vmul(side, width / 2))
            if d == 0:
                beam(m, a, b, 0.022, 0.022, mat, a=up, caps=False)
            else:
                e = vmul(up, 0.013 * step)
                m.face([vsub(a, e), vsub(b, e), vadd(b, e), vadd(a, e)], mat, out=out)
                m.face([vsub(a, e), vsub(b, e), vadd(b, e), vadd(a, e)], mat, out=vmul(out, -1))
    if cage and d <= 2 and L > cage_from + 1:
        r = 0.36
        nh = 8 if d == 0 else 6
        arc = [math.radians(-125 + 250.0 * k / nh) for k in range(nh + 1)]

        def hp(z, a, rr=r):
            c = vadd(vadd(p0, vmul(up, z)), vmul(out, r * 0.95))
            return vadd(c, vadd(vmul(out, rr * math.cos(a) - 0), vmul(side, rr * math.sin(a))))
        z = cage_from
        step = (0.9, 0.9, 2.7)[d]
        hoops = []
        while z < L - 0.2:
            hoops.append(z)
            z += step
        for z in hoops:
            for a0, a1 in zip(arc, arc[1:]):
                q0, q1 = hp(z, a0), hp(z, a1)
                e = vmul(up, 0.025)
                for o_ in (1, -1):
                    c = vadd(vadd(p0, vmul(up, z)), vmul(out, r * 0.95))
                    m.face([vsub(q0, e), vsub(q1, e), vadd(q1, e), vadd(q0, e)], mat, out=vmul(vsub(vmul(vadd(q0, q1), 0.5), c), o_))
            # attaches de l'arceau sur les montants
            for s in (-1, 1):
                q = hp(z, arc[0] if s < 0 else arc[-1])
                b_ = vadd(vadd(p0, vmul(up, z)), vmul(side, s * width / 2))
                e = vmul(up, 0.025)
                m.face([vsub(b_, e), vsub(q, e), vadd(q, e), vadd(b_, e)], mat, out=vmul(side, s))
                m.face([vsub(b_, e), vsub(q, e), vadd(q, e), vadd(b_, e)], mat, out=vmul(side, -s))
        if hoops:
            straps = (0, nh // 4, nh // 2, 3 * nh // 4, nh) if d <= 1 else (0, nh // 2, nh)
            for k in straps:
                q0, q1 = hp(hoops[0], arc[k]), hp(hoops[-1], arc[k])
                rad = vnorm(vsub(q0, vadd(vadd(p0, vmul(up, hoops[0])), vmul(out, r * 0.95))))
                tang = vcross(up, rad)
                e = vmul(tang, 0.02)
                for o_ in (1, -1):
                    m.face([vsub(q0, e), vadd(q0, e), vadd(q1, e), vsub(q1, e)], mat, out=vmul(rad, o_))


def cable_ladder(m, p0, p1, out, ncab=5, stops=None, width=0.32, rails=True):
    """Chemin de câbles en échelle de p0 à p1, câbles coaxiaux noirs côté out. stops : fraction de la longueur où
    s'arrête chaque câble (les câbles quittent le chemin à la hauteur de leur antenne)."""
    d = m.d
    if d >= 4:
        return
    up = vnorm(vsub(p1, p0))
    L = vlen(vsub(p1, p0))
    out = vnorm(vsub(out, vmul(up, vdot(out, up))))
    side = vcross(up, out)
    stops = stops or [1.0] * ncab
    if rails and d <= 2:
        for s in (-1, 1):
            o = vmul(side, s * width / 2)
            beam(m, vadd(p0, o), vadd(p1, o), 0.015, 0.045, "st_pipe", a=side, caps=False)
        if d <= 1:
            n = int(L / 0.6)
            for k in range(1, n):
                c = vadd(p0, vmul(up, k * 0.6))
                beam(m, vadd(c, vmul(side, -width / 2)), vadd(c, vmul(side, width / 2)), 0.03, 0.012, "st_pipe", a=up, caps=False)
    if d <= 2:
        cnt = ncab if d <= 1 else max(2, ncab // 2)
        for k in range(cnt):
            x = (k + 0.5) / cnt - 0.5
            o = vadd(vmul(side, x * (width - 0.06)), vmul(out, 0.03))
            q1 = vadd(p0, vmul(up, L * stops[k % len(stops)]))
            cable(m, [vadd(p0, o), vadd(q1, o)], 0.014 if k % 3 else 0.019, "cb_black" if k % 4 != 3 else "cb_fibre", n=(5, 4, 3)[d])
    else:
        o = vmul(out, 0.02)
        beam(m, vadd(p0, o), vadd(vadd(p0, vmul(up, L * max(stops))), o), width * 0.7, 0.03, "cb_black", a=side, caps=False)


# --------------------------------------------------------------------------------------------------------------------
# Plate-forme annulaire : caillebotis, cadre, garde-corps, jambes de force
# --------------------------------------------------------------------------------------------------------------------
def railing(m, a, b, h=1.1, posts=2, toe=True, mat="st_pipe"):
    """Garde-corps de a à b (poteaux, lisse, sous-lisse, plinthe)."""
    d = m.d
    if d >= 4:
        return
    L = vlen(vsub(b, a))
    n = max(1, int(round(L / 1.4))) if posts is None else posts
    nt = (6, 5, 3, 3)[d]
    tube(m, vadd(a, (0, 0, h)), vadd(b, (0, 0, h)), 0.021, mat, n=nt, caps=(False, False), S="s" if d == 2 else "")
    if d <= 2:
        tube(m, vadd(a, (0, 0, h * 0.5)), vadd(b, (0, 0, h * 0.5)), 0.017, mat, n=nt, caps=(False, False))
        for k in range(n + 1):
            p = vlerp(a, b, k / n)
            tube(m, p, vadd(p, (0, 0, h)), 0.021, mat, n=nt, caps=(False, False))
    if toe and d <= 1:
        dv = vnorm(vsub(b, a))
        nrm = vcross(dv, UP)
        plate(m, [a, b, vadd(b, (0, 0, 0.12)), vadd(a, (0, 0, 0.12))], 0.004, mat, nrm)


def ring_platform(m, lat, z, over=0.85, rail=True, gap_face=None, struts=True):
    """Plate-forme extérieure autour du treillis à la cote z : une travée de caillebotis par face, cadre en U,
    garde-corps (ouvert sur gap_face : accès de l'échelle), jambes de force sous les angles."""
    d = m.d
    n = lat.n
    k = (lat.radius(z) + over / math.cos(math.pi / n)) / lat.radius(z)
    inner = [lat.corner(i, z) for i in range(n)]
    outer = [(p[0] * k, p[1] * k, z) for p in inner]
    for i in range(n):
        a0, b0, a1, b1 = inner[i], inner[(i + 1) % n], outer[i], outer[(i + 1) % n]
        if d <= 3:
            # deux lés de caillebotis (alpha) ; de loin : tôle pleine
            for (u0, u1) in ((0.0, 0.5), (0.5, 1.0)):
                q = [vlerp(a0, a1, u0), vlerp(b0, b1, u0), vlerp(b0, b1, u1), vlerp(a0, a1, u1)]
                L = vlen(vsub(q[1], q[0]))
                st = [(0, 0), (L, 0), (L, 1), (0, 1)]
                if d <= 2:
                    m.face(q, "grating", st=st, out=UP)
                    m.face(q, "grating", st=st, out=(0, 0, -1))
                else:
                    m.face(q, "st_pipe", out=UP)
                    m.face(q, "st_pipe", out=(0, 0, -1))
        else:
            m.face([a0, b0, b1, a1], "st_pipe", out=UP)
            m.face([a0, b0, b1, a1], "st_pipe", out=(0, 0, -1))
        m.road([a0, b0, b1, a1])
        lo = (0, 0, -0.1)
        solid_hull8(m, [vadd(a0, lo), vadd(b0, lo), vadd(b1, lo), vadd(a1, lo)], [a0, b0, b1, a1], "sSg")
        if d <= 2:
            beam(m, vadd(a1, (0, 0, -0.05)), vadd(b1, (0, 0, -0.05)), 0.05, 0.1, "st_a", a=UP, caps=False)
            beam(m, vadd(a0, (0, 0, -0.05)), vadd(a1, (0, 0, -0.05)), 0.05, 0.1, "st_a", a=UP, caps=False)
            if d <= 1:
                mid0, mid1 = vlerp(a0, b0, 0.5), vlerp(a1, b1, 0.5)
                beam(m, vadd(mid0, (0, 0, -0.04)), vadd(mid1, (0, 0, -0.04)), 0.04, 0.08, "st_a", a=UP, caps=False)
        if struts and d <= 2:
            foot = lat.corner(i, z - over * 1.1)
            angle(m, vadd(a1, (0, 0, -0.1)), foot, 0.06, "st_a", vcross(lat.out(i), UP), vmul(lat.out(i), -1))
        if rail and i != gap_face:
            railing(m, a1, b1, posts=max(2, int(round(vlen(vsub(b1, a1)) / 1.3))))
        elif rail:
            # ouverture de l'échelle : deux demi-travées
            railing(m, a1, vlerp(a1, b1, 0.3), posts=1)
            railing(m, vlerp(a1, b1, 0.7), b1, posts=1)
    return outer


# --------------------------------------------------------------------------------------------------------------------
# Haubans
# --------------------------------------------------------------------------------------------------------------------
def guy(m, top, anchor, r=0.007, marker=True, turnbuckle=True):
    """Hauban en toron d'acier de top à anchor (au sol) : ridoir à chape en pied, gaine de signalisation jaune."""
    d = m.d
    if d >= 4:
        return
    dv = vnorm(vsub(anchor, top))
    L = vlen(vsub(anchor, top))
    end = vsub(anchor, vmul(dv, 0.75 if turnbuckle else 0.0))
    tube(m, top, end, max(r, (0.007, 0.009, 0.014, 0.022)[d]), "st_guy", n=(4, 3, 3, 3)[d], caps=(False, False))
    if turnbuckle and d <= 1:
        a, b = vsub(anchor, vmul(dv, 0.75)), vsub(anchor, vmul(dv, 0.08))
        tube(m, vlerp(a, b, 0.2), vlerp(a, b, 0.8), 0.022, "st_pipe", n=6 if d == 0 else 4)
        tube(m, a, b, 0.009, "st_rust", n=4, caps=(False, False))
    if marker and d <= 2 and L > 6:
        a, b = vsub(anchor, vmul(dv, 3.6)), vsub(anchor, vmul(dv, 0.95))
        tube(m, a, b, 0.028, "cb_hazard", n=(6, 5, 3)[d], caps=(False, False))


def guy_anchor(m, p, toward, n_guys=1):
    """Massif d'ancrage de hauban : bloc béton enterré, tige et platine d'égalisation inclinées vers le mât."""
    with m.push(tag="ground"):
        dv = vnorm((toward[0] - p[0], toward[1] - p[1], 0.0))
        with m.push(R=basis(x=dv, z=UP), t=(p[0], p[1], 0.0)):
            box(m, -0.6, 0.6, -0.45, 0.45, -3.0, 0.3, "concrete", S="gfv", skip="-z")
            if m.d <= 2:
                # tige d'ancrage sortant du béton, platine triangulaire
                plate(m, [(0.05, -0.006, 0.3), (0.5, -0.006, 0.3), (0.42, -0.006, 0.62), (0.2, -0.006, 0.55)], 0.012, "st_rust", (0, 1, 0))
                if m.d == 0:
                    for x in (0.25, 0.38):
                        bolt(m, (x, 0.006, 0.48), (0, 1, 0), 0.02, 0.02, "st_pipe")
    return vadd(p, vadd(vmul(vnorm((toward[0] - p[0], toward[1] - p[1], 0.0)), 0.36), (0, 0, 0.56)))


def stake(m, p, toward, sandbags=2):
    """Piquet d'ancrage (cornière enfoncée de biais) d'un mât tactique, lesté de sacs de sable."""
    dv = vnorm((toward[0] - p[0], toward[1] - p[1], 0.0))
    with m.push(tag="ground"):
        top = vadd(p, vadd(vmul(dv, -0.12), (0, 0, 0.28)))
        if m.d <= 2:
            angle(m, vadd(p, vadd(vmul(dv, 0.2), (0, 0, -0.5))), top, 0.04, "st_rust", vcross(dv, UP), dv)
        for k in range(sandbags if m.d <= 3 else 0):
            with m.push(R=rot("z", math.degrees(math.atan2(dv[1], dv[0])) + 90 + (k * 37 - 18)),
                        t=vadd(p, vadd(vmul(dv, 0.32 + 0.05 * k), (0, 0, 0.0 + 0.13 * k)))):
                sandbag(m)
    return top


def sandbag(m, l=0.6, w=0.32, h=0.14):
    """Sac de sable (toile de jute) posé à plat, ventre bombé."""
    if m.d >= 3:
        box(m, -l / 2, l / 2, -w / 2, w / 2, 0, h, "sandbag", skip="-z")
        return
    # section octogonale aplatie extrudée dans la longueur, bouts pincés
    prof = [(-w / 2, h * 0.35), (-w * 0.38, h * 0.05), (w * 0.38, h * 0.05), (w / 2, h * 0.35), (w / 2, h * 0.65), (w * 0.38, h), (-w * 0.38, h), (-w / 2, h * 0.65)]
    xs = [(-l / 2, 0.55), (-l * 0.36, 1.0), (l * 0.36, 1.0), (l / 2, 0.55)]
    rings = [[(x, y * k, h / 2 + (z - h / 2) * (k if k < 1 else 1)) for y, z in prof] for x, k in xs]
    for r0, r1 in zip(rings, rings[1:]):
        for i in range(8):
            j = (i + 1) % 8
            m.face([r0[i], r1[i], r1[j], r0[j]], "sandbag", out=(0, r0[i][1] + r0[j][1], r0[i][2] + r0[j][2] - h))
    for ring, o in ((rings[0], (-1, 0, 0)), (rings[-1], (1, 0, 0))):
        for q in ((0, 1, 2, 3), (0, 3, 4, 7), (4, 5, 6, 7)):
            m.face([ring[i] for i in q], "sandbag", out=o)


# --------------------------------------------------------------------------------------------------------------------
# Antennes et radios
# --------------------------------------------------------------------------------------------------------------------
def panel_antenna(m, h=2.0, w=0.30, t=0.12, tilt=4.0, pipe=2.6, rru=True, pipe_r=0.03):
    """Antenne panneau de secteur sur son tube support. Repère local : origine sur l'axe du tube au milieu de l'antenne,
    +X = direction de tir. Radôme, capots, deux étriers (réglage du tilt en haut), RRU au dos, jarretières."""
    d = m.d
    with m.push(tag="equip"):
        tube(m, (0, 0, -pipe / 2), (0, 0, pipe / 2), pipe_r, "st_pipe", n=(8, 6, 4, 3, 3)[d], caps=(d == 0, d <= 1), S="s")
        gap = 0.10
        with m.push(R=rot("y", tilt), t=(pipe_r + gap + t / 2, 0, 0)):
            mats = {"+x": "radome_front", "-x": "radome_back", "side": "radome_side", "*": "radome_side"}
            if d <= 3:
                box(m, -t / 2, t / 2, -w / 2, w / 2, -h / 2, h / 2, mats, bevel=0.03 if d == 0 else 0.0, S="sS")
            else:
                box(m, -t / 2, t / 2, -w / 2, w / 2, -h / 2, h / 2, mats, skip="-x+z-z")
            if d <= 1:
                for z in (-h * 0.36, h * 0.36):
                    box(m, -t / 2 - gap + 0.01, -t / 2, -0.05, 0.05, z - 0.03, z + 0.03, "st_pipe", skip="+x")
                    if d == 0:
                        with m.push(t=(-t / 2 - gap - pipe_r, 0, z)):
                            lathe(m, [(pipe_r + 0.012, -0.035), (pipe_r + 0.012, 0.035)], "st_plate", n=8)
                            bolt(m, (0, pipe_r + 0.01, 0), (0, 1, 0), 0.01, 0.03, "st_pipe")
                            bolt(m, (0, -pipe_r - 0.01, 0), (0, -1, 0), 0.01, 0.03, "st_pipe")
        if rru and d <= 3:
            zc = -h * 0.5 - 0.05
            with m.push(t=(-pipe_r - 0.09, 0, zc)):
                mats = {"-x": "rru_front", "+x": "rru_side", "*": "rru_side"}
                box(m, -0.07, 0.07, -0.16, 0.16, -0.22, 0.22, mats, S="s")
            if d <= 1:
                for y in (-0.08, 0.0, 0.08) if d == 0 else (0.0,):
                    a = (-pipe_r - 0.09, y, zc - 0.22)
                    b = (pipe_r + gap + t / 2 - 0.02, y * 0.8, -h / 2 + 0.02)
                    pts = [a, vadd(a, (0, 0, -0.12)), (0.0, y * 1.6 + 0.07, zc - 0.36), vadd(b, (0.02, 0, -0.2)), b]
                    cable(m, pts, 0.008, "cb_black", n=4)


def dish(m, D=0.6, depth=None, pipe=1.4, pipe_r=0.057, odu=True):
    """Faisceau hertzien : parabole à radôme et jupe, sur tube vertical. Origine sur l'axe du tube, +X = visée."""
    d = m.d
    R = D / 2
    depth = depth or D * 0.28
    n = (20, 14, 10, 8, 6)[d]
    with m.push(tag="equip"):
        tube(m, (0, 0, -pipe / 2), (0, 0, pipe / 2), pipe_r, "st_pipe", n=(8, 6, 4, 3, 3)[d], caps=(d == 0, d <= 1), S="s")
        off = pipe_r + 0.20 + depth * 0.4
        with m.push(R=basis(x=(0, 1, 0), z=(1, 0, 0)), t=(off, 0, 0)):   # axe Z local = visée
            # dos : cuvette parabolique, de la jupe vers le moyeu
            back = [(R * k, -depth * 0.1 - depth * 0.9 * (1 - k * k)) for k in (1.0, 0.85, 0.65, 0.4, 0.18)] if d <= 1 else [(R, -depth * 0.1), (R * 0.6, -depth * 0.7), (R * 0.18, -depth)]
            lathe(m, list(reversed(back)), "dish_back", n=n, polar=True, closed=(True, False))
            # jupe (cylindre court) et radôme légèrement bombé
            lathe(m, [(R, -depth * 0.1), (R, depth * 0.45)], "radome_side", n=n)
            front = [(R, depth * 0.45), (R * 0.7, depth * 0.52), (R * 0.35, depth * 0.56), (0.0, depth * 0.58)] if d <= 1 else [(R, depth * 0.45), (0.0, depth * 0.56)]
            lathe(m, front, "dish_front", n=n, polar=True)
            add_prism_solid(m, (0, 0, -depth), (0, 0, depth * 0.5), (1, 0, 0), (0, 1, 0), [(R * 0.92, 0), (0, R * 0.92), (-R * 0.92, 0), (0, -R * 0.92)], "sS")
            if d <= 2:
                # moyeu, bras de fixation vers le tube
                lathe(m, [(R * 0.2, -depth - 0.10), (R * 0.2, -depth * 0.95)], "st_pipe", n=8 if d == 0 else 5, closed=(True, False))
        if d <= 2:
            box(m, pipe_r * 0.3, off - depth - 0.02, -0.05, 0.05, -0.06, 0.06, "st_plate")
            if d == 0:
                for z in (-0.14, 0.14):
                    with m.push(t=(0, 0, z)):
                        lathe(m, [(pipe_r + 0.012, -0.03), (pipe_r + 0.012, 0.03)], "st_plate", n=8)
        if odu and d <= 2:
            k = min(1.0, D / 0.6)
            with m.push(t=(off - depth - 0.16, 0.0, -0.22)):
                box(m, -0.06 * k, 0.06 * k, -0.12 * k, 0.12 * k, 0.13 - 0.26 * k, 0.13, {"-x": "rru_front", "*": "rru_side"})
            if d <= 1:
                a = (off - depth - 0.16, 0.0, -0.35)
                cable(m, [a, vadd(a, (0, 0, -0.15)), (-pipe_r - 0.03, 0.03, -pipe / 2 + 0.1), (-pipe_r - 0.03, 0.03, -pipe / 2 - 0.3)], 0.009, "cb_black", n=4)


def omni(m, L=1.6, r=0.016, base=0.25):
    """Antenne fouet colinéaire en fibre : embase alu, radôme blanc, capuchon. Origine au pied, vers +Z."""
    d = m.d
    with m.push(tag="equip"):
        if d <= 2:
            tube(m, (0, 0, 0), (0, 0, base), r * 1.5, "st_pipe", n=(8, 6, 4)[d], caps=(False, True))
        tube(m, (0, 0, base if d <= 2 else 0), (0, 0, L), max(r, (0, 0, 0.02, 0.03, 0.04)[d]), "radome_side", n=(8, 6, 4, 3, 3)[d], r1=max(r * 0.8, (0, 0, 0.02, 0.03, 0.04)[d]), caps=(False, d <= 1), around=0.3)


def radio_box(m, w=0.26, t=0.11, h=0.34, ants=2, led=True):
    """Radio MANET (type MPU5) en coffret extérieur à ailettes : antennes boudin dessus, presse-étoupes dessous,
    voyant d'état (sélection « led »). Origine au dos (plan de fixation), +X = face avant."""
    d = m.d
    with m.push(tag="equip"):
        if d >= 4:
            return
        box(m, 0.0, t, -w / 2, w / 2, -h / 2, h / 2, {"+x": "mpu5", "*": "mpu5_side"}, S="s", bevel=0.012 if d == 0 else 0)
        if d <= 2:
            for k in range(ants):
                y = (k - (ants - 1) / 2) * (w * 0.6 / max(1, ants - 1)) if ants > 1 else 0.0
                tube(m, (t / 2, y, h / 2), (t / 2, y, h / 2 + 0.05), 0.012, "st_pipe", n=6 if d == 0 else 4, caps=(False, False))
                tube(m, (t / 2, y, h / 2 + 0.05), (t / 2, y, h / 2 + 0.33), 0.009, "cb_rubber", n=6 if d == 0 else 3, r1=0.006, caps=(False, True))
        if d <= 1:
            for y in (-w * 0.25, 0.0, w * 0.25):
                tube(m, (t / 2, y, -h / 2 - 0.035), (t / 2, y, -h / 2), 0.014, "cb_rubber", n=6 if d == 0 else 4, caps=(True, False))
        if led and d <= 1:
            with m.push(sels=("led",)):
                box(m, t, t + 0.004, w * 0.28, w * 0.36, h * 0.30, h * 0.36, "led", skip="-x")


def obstruction_light(m, name, big=False, guard=True):
    """Feu d'obstacle rouge (basse intensité, ou balise moyenne intensité si big) : embase alu, globe rouge strié,
    point mémoire name au centre du globe (MarkerLights de la config). Origine sous l'embase."""
    d = m.d
    r = 0.11 if big else 0.062
    h = 0.24 if big else 0.13
    with m.push(tag="equip"):
        if d <= 3:
            lathe(m, [(r * 1.15, 0.0), (r * 1.15, r * 0.7), (r * 0.95, r * 0.9)], "lamp", n=(12, 8, 6, 4)[d], closed=(True, False))
        prof = [(r * 0.95, r * 0.9), (r, r * 0.9 + h * 0.5), (r * 0.8, r * 0.9 + h * 0.85), (0.0, r * 0.9 + h)] if d <= 1 else [(r, r * 0.9), (r, r * 0.9 + h * 0.7), (0.0, r * 0.9 + h)]
        with m.push(sels=("lights",)):
            lathe(m, prof if d <= 3 else [(r * 1.3, 0.0), (r * 1.3, h), (0.0, h * 1.3)], "light_red", n=(12, 8, 6, 5, 4)[d])
        m.memory(name, (0, 0, r * 0.9 + h * 0.5))


def lightning_rod(m, L=3.0):
    """Paratonnerre à tige (Franklin) : tige effilée, pointe, collier ; origine au pied."""
    d = m.d
    if d >= 4:
        return
    with m.push(tag="equip"):
        tube(m, (0, 0, 0), (0, 0, L), max(0.014, (0, 0, 0.02, 0.03)[d]), "st_pipe", n=(6, 5, 3, 3)[d], r1=max(0.005, (0, 0, 0.012, 0.02)[d]), caps=(False, True))


def pipe_mount(m, p_pipe, anchors, r=0.024, mat="st_pipe"):
    """Bras de déport (tubes) du tube support p_pipe vers des points d'ancrage (jambes, colliers)."""
    if m.d >= 3:
        return
    for a in anchors:
        tube(m, a, p_pipe, r, mat, n=(6, 5, 3)[m.d], caps=(False, False), S="s" if m.d == 2 else "")


def collar(m, lat, z, over=0.06, mat="st_pipe", r=0.03):
    """Ceinture de tubes autour du treillis (cadre d'antennes) : relie les jambes à la cote z. Renvoie ses sommets."""
    pts = [vadd(lat.corner(i, z), vmul(lat.out(i), over)) for i in range(lat.n)]
    if m.d <= 2:
        for i in range(lat.n):
            tube(m, pts[i], pts[(i + 1) % lat.n], r, mat, n=(6, 5, 3)[m.d], caps=(False, False))
    return pts


def sector_ring(m, lat, z, azimuths, standoff=0.7, h=2.0, w=0.30, rru=True, tilt=4.0):
    """Couronne d'antennes de secteur à la cote z : deux ceintures, bras de déport, tube support, antenne, RRU."""
    hz = h * 0.36
    rings = [collar(m, lat, z - hz), collar(m, lat, z + hz)] if m.d <= 2 else [None, None]
    for az in azimuths:
        a = math.radians(az)
        u = (math.cos(a), math.sin(a), 0.0)
        R = lat.radius(z) + standoff
        p = (u[0] * R, u[1] * R, z)
        if m.d <= 2:
            for ring, dz in zip(rings, (-hz, hz)):
                # deux bras vers les deux sommets de ceinture les plus proches
                near = sorted(ring, key=lambda q: -(q[0] * u[0] + q[1] * u[1]))[:2]
                pipe_mount(m, vadd(p, (0, 0, dz)), near)
        with m.push(R=rot("z", az), t=p):
            panel_antenna(m, h=h, w=w, rru=rru, tilt=tilt, pipe=h + 0.6)


def dish_mount(m, lat, z, az, D=0.6, standoff=0.35):
    a = math.radians(az)
    u = (math.cos(a), math.sin(a), 0.0)
    R = lat.radius(z) + standoff
    p = (u[0] * R, u[1] * R, z)
    if m.d <= 2:
        for dz in (-0.45, 0.45):
            ring = [vadd(lat.corner(i, z + dz), vmul(lat.out(i), 0.05)) for i in range(lat.n)]
            near = sorted(ring, key=lambda q: -(q[0] * u[0] + q[1] * u[1]))[:2]
            pipe_mount(m, vadd(p, (0, 0, dz)), near, r=0.03)
    with m.push(R=rot("z", az), t=p):
        dish(m, D=D, pipe=max(1.3, D + 0.5))


# --------------------------------------------------------------------------------------------------------------------
# Équipements au sol
# --------------------------------------------------------------------------------------------------------------------
def cabinet(m, w=1.3, dp=0.8, h=1.9, led=True, slab=True):
    """Armoire extérieure (baie radio / énergie) sur plinthe béton. Origine au sol au centre, face avant vers -Y.
    Toit débordant, voyant d'état (sélection « led ») sous le larmier."""
    d = m.d
    with m.push(tag="ground"):
        if slab:
            box(m, -w / 2 - 0.25, w / 2 + 0.25, -dp / 2 - 0.25, dp / 2 + 0.25, -1.6, 0.12, "concrete", S="gfv", skip="-z")
            m.road([(-w / 2 - 0.25, -dp / 2 - 0.25, 0.12), (w / 2 + 0.25, -dp / 2 - 0.25, 0.12), (w / 2 + 0.25, dp / 2 + 0.25, 0.12), (-w / 2 - 0.25, dp / 2 + 0.25, 0.12)])
        z0 = 0.12 if slab else 0.0
        mats = {"-y": "cab_front", "+y": "cab_side", "+x": "cab_side", "-x": "cab_side", "+z": "cab_top", "*": "cab_top"}
        box(m, -w / 2, w / 2, -dp / 2, dp / 2, z0, z0 + h, mats, S="sSgfv", skip="-z")
        if d <= 2:
            box(m, -w / 2 - 0.04, w / 2 + 0.04, -dp / 2 - 0.07, dp / 2 + 0.04, z0 + h, z0 + h + 0.05, {"+z": "cab_top", "-z": "cab_top", "*": "cab_top"})
        if d == 0:
            # poignée en relief, cadenas
            box(m, 0.02, 0.07, -dp / 2 - 0.035, -dp / 2, z0 + h * 0.42, z0 + h * 0.58, "plastic", skip="+y")
        if led and d <= 2:
            with m.push(sels=("led",)):
                box(m, w * 0.30, w * 0.36, -dp / 2 - 0.012, -dp / 2, z0 + h - 0.16, z0 + h - 0.10, "led", skip="+y")
    return z0 + h


def shelter(m, w=3.0, dp=2.4, h=2.6):
    """Abri technique préfabriqué sur dalle : tôle nervurée, porte acier (face -Y), clim, traversée de câbles."""
    d = m.d
    with m.push(tag="ground"):
        box(m, -w / 2 - 0.3, w / 2 + 0.3, -dp / 2 - 0.3, dp / 2 + 0.3, -1.6, 0.18, "concrete", S="gfv", skip="-z")
        m.road([(-w / 2 - 0.3, -dp / 2 - 0.3, 0.18), (w / 2 + 0.3, -dp / 2 - 0.3, 0.18), (w / 2 + 0.3, dp / 2 + 0.3, 0.18), (-w / 2 - 0.3, dp / 2 + 0.3, 0.18)])
        mats = {"-y": "shelter_front", "+y": "shelter_side", "+x": "shelter_side", "-x": "shelter_side", "*": "cab_top"}
        box(m, -w / 2, w / 2, -dp / 2, dp / 2, 0.18, 0.18 + h, mats, S="sSgfv", skip="-z")
        if d <= 2:
            box(m, -w / 2 - 0.08, w / 2 + 0.08, -dp / 2 - 0.12, dp / 2 + 0.08, 0.18 + h, 0.18 + h + 0.07, "cab_top")
        if d <= 1:
            # blocs de climatisation en saillie, auvent de porte
            for x in (w * 0.33,):
                box(m, x - 0.35, x + 0.35, -dp / 2 - 0.22, -dp / 2, 1.75, 2.35, {"-y": "grille", "*": "cab_top"}, skip="+y")
            box(m, -w * 0.08 - 0.55, -w * 0.08 + 0.55, -dp / 2 - 0.45, -dp / 2, 0.18 + h * 0.87, 0.18 + h * 0.89, "cab_top")
        with m.push(sels=("led",)):
            if d <= 2:
                box(m, -w * 0.08 - 0.05, -w * 0.08 + 0.05, -dp / 2 - 0.02, -dp / 2, 0.18 + h * 0.90, 0.18 + h * 0.93, "led", skip="+y")
    return 0.18 + h


def genset(m, jerry=2):
    """Groupe électrogène capoté sur châssis traîneau, pot d'échappement, jerrycans. Origine au sol, long côté selon X."""
    d = m.d
    with m.push(tag="ground"):
        mats = {"-y": "genset_side", "+y": "genset_side", "+x": "genset_end", "-x": "genset_end", "*": "olive"}
        mats["+z"] = "cab_top"
        box(m, -0.8, 0.8, -0.4, 0.4, 0.08, 1.1, mats, S="sSgfv", bevel=0.04 if d == 0 else 0)
        if d <= 2:
            for y in (-0.3, 0.3):
                beam(m, (-0.9, y, 0.04), (0.9, y, 0.04), 0.08, 0.08, "st_rust", a=UP)
            tube(m, (0.55, 0.15, 1.1), (0.55, 0.15, 1.42), 0.03, "st_rust", n=6 if d == 0 else 4, caps=(False, True))
        if d <= 3:
            for k in range(jerry):
                with m.push(R=rot("z", 8 - 14 * k), t=(-1.25 - 0.02 * k, -0.2 + 0.4 * k, 0.0)):
                    jerrycan(m)


def jerrycan(m):
    d = m.d
    with m.push(tag="ground"):
        box(m, -0.085, 0.085, -0.17, 0.17, 0.0, 0.40, {"+x": "jerrycan", "-x": "jerrycan", "*": "olive"}, bevel=0.025 if d == 0 else 0, skip="-z")
        if d <= 1:
            box(m, -0.02, 0.02, -0.09, 0.09, 0.40, 0.46, "olive", skip="-z")
            tube(m, (0, 0.13, 0.40), (0, 0.15, 0.46), 0.022, "olive", n=6, caps=(False, True))


def solar_array(m, w=1.7, h=1.0, tilt=32, legs=True, n=1):
    """Panneau(x) solaire(s) sur châssis incliné lesté. Origine au sol sous le bord bas, face vers -Y (sud du site)."""
    d = m.d
    with m.push(tag="ground"):
        for k in range(n):
            with m.push(t=((k - (n - 1) / 2) * (w + 0.05), 0, 0.25), R=rot("x", -tilt)):
                # repère local : X largeur, Y montant le long du panneau, Z normale
                m.face([(-w / 2, 0, 0.02), (w / 2, 0, 0.02), (w / 2, h, 0.02), (-w / 2, h, 0.02)], "solar", uvs=[(0, 1), (1, 1), (1, 0), (0, 0)], out=(0, 0, 1))
                box(m, -w / 2, w / 2, 0, h, -0.02, 0.02, "st_pipe", skip="+z", S="sS")
        if legs and d <= 2:
            c, s = math.cos(math.radians(tilt)), math.sin(math.radians(tilt))
            W = n * (w + 0.05)
            for x in (-W / 2 + 0.2, W / 2 - 0.2):
                top = (x, h * c, 0.25 + h * s)
                tube(m, (x, h * c, 0.0), top, 0.02, "st_pipe", n=5 if d == 0 else 3, caps=(False, False))
                tube(m, (x, 0, 0.0), (x, 0, 0.25), 0.02, "st_pipe", n=5 if d == 0 else 3, caps=(False, False))
                tube(m, (x, -0.1, 0.03), (x, h * c + 0.1, 0.03), 0.02, "st_pipe", n=5 if d == 0 else 3, caps=(False, False))
                tube(m, (x, 0, 0.25), (x, h * c, 0.03), 0.015, "st_pipe", n=4 if d == 0 else 3, caps=(False, False))
                with m.push(t=(x, h * c * 0.5, 0.05), R=rot("z", 90)):
                    sandbag(m)


def transit_case(m, l=0.8, w=0.5, h=0.4, mat="case_top"):
    """Caisse de transport en polymère nervuré. Origine au sol, au centre."""
    d = m.d
    with m.push(tag="ground"):
        mats = {"+z": mat, "*": "plastic"}
        box(m, -l / 2, l / 2, -w / 2, w / 2, 0.0, h, mats, S="sSgfv", bevel=0.03 if d == 0 else 0, skip="-z")
        if d <= 1:
            box(m, -l / 2 - 0.012, l / 2 + 0.012, -w / 2 - 0.012, w / 2 + 0.012, h * 0.58, h * 0.66, "plastic")
            for x in (-l * 0.25, l * 0.25):
                box(m, x - 0.04, x + 0.04, -w / 2 - 0.022, -w / 2, h * 0.5, h * 0.72, "st_pipe", skip="+y")
            if d == 0:
                for s in (-1, 1):
                    box(m, s * (l / 2 + 0.012), s * (l / 2 + 0.035), -0.09, 0.09, h * 0.4, h * 0.5, "cb_rubber")


def fence(m, x0, x1, y0, y1, h=2.0, gate=(0.0, 1.2), bay=2.5, signs=True):
    """Clôture grillagée simple torsion (poteaux, lisse haute, bavolet à trois fils barbelés), portail côté y0
    (gate : centre x, demi-largeur). Grillage en alpha : pas d'ombre, les balles passent, la collision arrête."""
    d = m.d
    with m.push(tag="ground"):
        sides = [((x0, y0), (x1, y0)), ((x1, y0), (x1, y1)), ((x1, y1), (x0, y1)), ((x0, y1), (x0, y0))]
        for si, (a, b) in enumerate(sides):
            L = math.hypot(b[0] - a[0], b[1] - a[1])
            n = max(1, int(round(L / bay)))
            dv = ((b[0] - a[0]) / L, (b[1] - a[1]) / L, 0.0)
            outn = (dv[1], -dv[0], 0.0)
            for k in range(n):
                p, q = (a[0] + dv[0] * L * k / n, a[1] + dv[1] * L * k / n), (a[0] + dv[0] * L * (k + 1) / n, a[1] + dv[1] * L * (k + 1) / n)
                is_gate = si == 0 and gate and abs((p[0] + q[0]) / 2 - gate[0]) < L / n / 2
                if d <= 3:
                    tube(m, (p[0], p[1], -0.6), (p[0], p[1], h + 0.05), 0.03, "st_pipe", n=(6, 5, 4, 3)[d], caps=(False, d == 0))
                if d <= 3:
                    rows = 4 if d <= 2 else 1
                    for r_ in range(rows):
                        za, zb = 0.05 + (h - 0.05) * r_ / rows, 0.05 + (h - 0.05) * (r_ + 1) / rows
                        Lb = L / n
                        qd = [(p[0], p[1], za), (q[0], q[1], za), (q[0], q[1], zb), (p[0], p[1], zb)]
                        st = [(0, 1), (Lb, 1), (Lb, 0), (0, 0)]
                        m.face(qd, "fence", st=st, out=outn)
                        m.face(qd, "fence", st=st, out=vmul(outn, -1))
                if d <= 2:
                    tube(m, (p[0], p[1], h), (q[0], q[1], h), 0.021, "st_pipe", n=(5, 4, 3)[d], caps=(False, False))
                    if is_gate:
                        # cadre du portail et panneau d'avertissement
                        tube(m, (p[0], p[1], 0.08), (q[0], q[1], 0.08), 0.021, "st_pipe", n=(5, 4, 3)[d], caps=(False, False))
                        tube(m, (p[0], p[1], 0.08), (q[0], q[1], h), 0.014, "st_pipe", n=(5, 4, 3)[d], caps=(False, False))
                if d <= 1:
                    # bavolet incliné vers l'extérieur, trois fils
                    for k2 in (0, 1, 2):
                        o = vadd(vmul(outn, 0.08 + 0.1 * k2), (0, 0, h + 0.1 + 0.09 * k2))
                        tube(m, vadd((p[0], p[1], 0), o), vadd((q[0], q[1], 0), o), 0.004, "st_rust", n=3, caps=(False, False))
                    tube(m, (p[0], p[1], h), vadd((p[0], p[1], h + 0.33), vmul(outn, 0.32)), 0.018, "st_pipe", n=4, caps=(False, False))
                if signs and d <= 2 and (is_gate or (k == n // 2 and si != 0)):
                    c = ((p[0] + q[0]) / 2, (p[1] + q[1]) / 2, 1.45)
                    e = vmul(dv, 0.2)
                    o = vmul(outn, 0.012)
                    quad(m, [vadd(vsub(c, e), vadd(o, (0, 0, -0.2))), vadd(vadd(c, e), vadd(o, (0, 0, -0.2))),
                             vadd(vadd(c, e), vadd(o, (0, 0, 0.2))), vadd(vsub(c, e), vadd(o, (0, 0, 0.2)))], "sign_rf" if is_gate or si % 2 else "sign_id")
            # collision : une dalle mince par côté (g), rien pour le tir ni la vue
            c0, c1 = (a[0], a[1], 0.0), (b[0], b[1], 0.0)
            t_ = vmul(outn, 0.03)
            solid_hull8(m, [vsub(c0, t_), vsub(c1, t_), vadd(c1, t_), vadd(c0, t_)],
                        [vadd(vsub(c0, t_), (0, 0, h)), vadd(vsub(c1, t_), (0, 0, h)), vadd(vadd(c1, t_), (0, 0, h)), vadd(vadd(c0, t_), (0, 0, h))], "g")


def earth_rod(m, p, to):
    """Piquet de terre cuivré et tresse vert-jaune jusqu'à to."""
    if m.d > 1:
        return
    with m.push(tag="ground"):
        tube(m, (p[0], p[1], -0.3), (p[0], p[1], 0.18), 0.009, "cb_copper", n=5, caps=(False, True))
        mid = (p[0] * 0.5 + to[0] * 0.5, p[1] * 0.5 + to[1] * 0.5, 0.02)
        cable(m, [(p[0], p[1], 0.12), (p[0] * 0.8 + to[0] * 0.2, p[1] * 0.8 + to[1] * 0.2, 0.02), mid, (to[0], to[1], 0.03), to], 0.006, "cb_earth", n=4)


def cable_bridge(m, a, b, h, ncab=5, posts=True):
    """Passerelle de câbles horizontale (hauteur h) de a à b (x, y) : échelle à câbles sur poteaux, câbles dessus."""
    d = m.d
    if d >= 4:
        return
    pa, pb = (a[0], a[1], h), (b[0], b[1], h)
    cable_ladder(m, pa, pb, UP, ncab=ncab)
    if posts and d <= 2:
        with m.push(tag="ground"):
            for p in (vlerp(pa, pb, 0.12), vlerp(pa, pb, 0.88)):
                tube(m, (p[0], p[1], -0.5), (p[0], p[1], h - 0.03), 0.035, "st_pipe", n=(6, 5, 3)[d], caps=(False, False), S="s")
