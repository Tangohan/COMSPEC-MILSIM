#!/usr/bin/env python3
"""
Retire des pylônes relais (MLOD P3DM) les câbles courbes et le filet de camouflage, sans Blender.

model_relay_towers.py est la source et ne produit plus ces pièces. Cet outil applique le même retrait aux .p3d déjà
générés, quand Blender et son extension ne sont pas là. Il ne touche rien d'autre : faces gardées, UV, sélections et
masse sont recopiés octet pour octet ; seuls les sommets et normales devenus orphelins sont renumérotés.

    python strip_tower_cables.py                 bilan, aucun fichier écrit
    python strip_tower_cables.py --write         écrit les .p3d (relancer ensuite ne retire plus rien)

Retiré, par composante connexe (une pièce du générateur = une composante) :
  - filet : toute face de texture tower_net ;
  - perches du filet (m20) : tube vertical aux quatre coins du filet, sur toutes les LOD ;
  - câbles courbes : texture tower_cable, tube de 4 ou 5 côtés à au moins 3 anneaux (sweep du générateur, bouchons quads ou triangulés) ;
    les câbles droits sont des tubes de 6 côtés à 2 anneaux et restent ;
  - bagues des jarretières : textures procédurales jaune et rouge ;
  - ombres : enveloppe convexe dont la boîte correspond à celle d'un câble retiré de la LOD 4.
"""
import glob
import os
import struct
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
DATA = os.path.join(HERE, "..", "Sources", "addons", "towers", "data")
# Filet de relay_tower_m20 (camo_net(B, 1.7, -2.4, 4.6, 2.1, 2.5)) : les quatre perches, en x, y Arma (z Blender = y Arma).
POLES = ((1.7, -2.4), (4.6, -2.4), (4.6, 2.1), (1.7, 2.1))
TAPES = ("color(0.85,0.62,0.05,1,co)", "color(0.62,0.06,0.04,1,co)")


def zstr(b, i):
    j = b.index(b"\0", i)
    return b[i:j], j + 1


class Lod:
    """Une LOD P3DM : points, normales, faces (brutes), balises, résolution."""

    def __init__(self, b, i):
        self.magic = b[i:i + 12]
        assert self.magic[:4] == b"P3DM", self.magic
        i += 12
        np, nn, nf, self.flags = struct.unpack_from("<4I", b, i)
        i += 16
        self.points = [b[i + k * 16:i + k * 16 + 16] for k in range(np)]
        i += np * 16
        self.normals = [b[i + k * 12:i + k * 12 + 12] for k in range(nn)]
        i += nn * 12
        self.faces = []
        for _ in range(nf):
            n = struct.unpack_from("<I", b, i)[0]
            cor = [struct.unpack_from("<IIff", b, i + 4 + k * 16) for k in range(4)]
            fl = struct.unpack_from("<I", b, i + 68)[0]
            i += 72
            tex, i = zstr(b, i)
            mat, i = zstr(b, i)
            self.faces.append((n, cor, fl, tex, mat))
        assert b[i:i + 4] == b"TAGG", b[i:i + 8]
        i += 4
        self.tags = []
        while True:
            act = b[i]
            name, i = zstr(b, i + 1)
            n = struct.unpack_from("<I", b, i)[0]
            i += 4
            self.tags.append((act, name, b[i:i + n]))
            i += n
            if name == b"#EndOfFile#":
                break
        self.res = b[i:i + 4]
        self.end = i + 4

    def xyz(self, k):
        return struct.unpack_from("<3f", self.points[k])

    def components(self):
        parent = list(range(len(self.points)))

        def find(a):
            while parent[a] != a:
                parent[a] = parent[parent[a]]
                a = parent[a]
            return a
        for n, cor, *_ in self.faces:
            r = find(cor[0][0])
            for c in cor[1:n]:
                parent[find(c[0])] = r
        comp = {}
        for fi, f in enumerate(self.faces):
            comp.setdefault(find(f[1][0][0]), []).append(fi)
        return list(comp.values())

    def comp_verts(self, faces):
        return sorted({c[0] for fi in faces for c in self.faces[fi][1][:self.faces[fi][0]]})

    def bbox(self, verts):
        ps = [self.xyz(v) for v in verts]
        return tuple(min(p[a] for p in ps) for a in range(3)), tuple(max(p[a] for p in ps) for a in range(3))

    def remove(self, faces):
        """Retire ces faces, renumérote sommets et normales, ajuste les balises dont la taille dépend d'eux."""
        drop = set(faces)
        keep_f = [k for k in range(len(self.faces)) if k not in drop]
        used_p, used_n = set(), set()
        for k in keep_f:
            n, cor, *_ = self.faces[k]
            for c in cor[:n]:
                used_p.add(c[0])
                used_n.add(c[1])
        # Géométrie, mémoire : une LOD peut porter des points sans face ; on ne garde les orphelins que s'ils l'étaient déjà.
        was = set()
        for f in self.faces:
            for c in f[1][:f[0]]:
                was.add(c[0])
        keep_p = [k for k in range(len(self.points)) if k in used_p or k not in was]
        keep_n = sorted(used_n)
        mp = {o: i for i, o in enumerate(keep_p)}
        mn = {o: i for i, o in enumerate(keep_n)}
        old_np, old_nf = len(self.points), len(self.faces)
        tags = []
        for act, name, data in self.tags:
            if name == b"#SharpEdges#":
                out = bytearray()
                for k in range(0, len(data), 8):
                    a, b_ = struct.unpack_from("<II", data, k)
                    if a in mp and b_ in mp:
                        out += struct.pack("<II", mp[a], mp[b_])
                data = bytes(out)
            elif name == b"#Mass#":
                assert len(data) == 4 * old_np, name
                data = b"".join(data[k * 4:k * 4 + 4] for k in keep_p)
            elif name == b"#UVSet#":
                out = bytearray(data[:4])
                i = 4
                for k in range(old_nf):
                    size = 8 * self.faces[k][0]
                    if k not in drop:
                        out += data[i:i + size]
                    i += size
                assert i == len(data), name
                data = bytes(out)
            elif name in (b"#Property#", b"#EndOfFile#"):
                pass
            elif name.startswith(b"#") and name not in (b"#Selected#", b"#Hide#"):
                raise SystemExit("balise inconnue %r : arrêt, rien écrit" % name)
            else:
                # Sélection nommée : un octet par point, puis un octet par face.
                assert len(data) == old_np + old_nf, name
                data = bytes(data[k] for k in keep_p) + bytes(data[old_np + k] for k in keep_f)
            tags.append((act, name, data))
        self.tags = tags
        new = []
        for k in keep_f:
            n, cor, fl, tex, mat = self.faces[k]
            new.append((n, [(mp[c[0]], mn[c[1]], c[2], c[3]) if j < n else c for j, c in enumerate(cor)], fl, tex, mat))
        self.faces = new
        self.points = [self.points[k] for k in keep_p]
        self.normals = [self.normals[k] for k in keep_n]

    def dump(self):
        o = bytearray(self.magic)
        o += struct.pack("<4I", len(self.points), len(self.normals), len(self.faces), self.flags)
        o += b"".join(self.points) + b"".join(self.normals)
        for n, cor, fl, tex, mat in self.faces:
            o += struct.pack("<I", n)
            for c in cor:
                o += struct.pack("<IIff", *c)
            o += struct.pack("<I", fl) + tex + b"\0" + mat + b"\0"
        o += b"TAGG"
        for act, name, data in self.tags:
            o += bytes([act]) + name + b"\0" + struct.pack("<I", len(data)) + data
        return bytes(o) + self.res


def is_curved(lod, faces):
    """Tube du sweep : 4 ou 5 côtés, au moins 3 anneaux (flancs en quads, deux bouchons entiers ou triangulés)."""
    verts = lod.comp_verts(faces)
    quads = sum(1 for fi in faces if lod.faces[fi][0] == 4)
    for n in (4, 5):
        rings = len(verts) / n
        if rings == int(rings) and rings >= 3 and len(faces) in (int(rings - 1) * n, int(rings - 1) * n + 2, int(rings - 1) * n + 2 * (n - 2)):
            lo, hi = lod.bbox(verts)
            # Fil fin : jamais un boîtier. Le rayon d'un sweep reste sous 5 cm.
            ring = [lod.xyz(v) for v in verts[:n]]
            r = max(max(p[a] for p in ring) - min(p[a] for p in ring) for a in range(3))
            if r < 0.12 and max(hi[a] - lo[a] for a in range(3)) > 0.05 and quads >= n:
                return True
    return False


def is_pole(lod, faces):
    lo, hi = lod.bbox(lod.comp_verts(faces))
    cx, cz = (lo[0] + hi[0]) / 2, (lo[2] + hi[2]) / 2
    return (hi[1] - lo[1]) > 2.0 and (hi[0] - lo[0]) < 0.08 and (hi[2] - lo[2]) < 0.08 and lo[1] < 0.3 and any(
        abs(cx - x) < 0.05 and abs(cz - y) < 0.05 for x, y in POLES)


def same_box(a, b, tol=0.03):
    return all(abs(a[i][k] - b[i][k]) <= tol for i in (0, 1) for k in (0, 1, 2))


def process(path, write):
    b = open(path, "rb").read()
    assert b[:4] == b"MLOD", path
    version, count = struct.unpack_from("<II", b, 4)
    i = 12
    lods = []
    for _ in range(count):
        lod = Lod(b, i)
        i = lod.end
        lods.append(lod)
    tail = b[i:]
    is_m20 = "m20" in os.path.basename(path)
    removed_boxes = []
    report = []
    for lod in lods:
        res = struct.unpack("<f", lod.res)[0]
        drop, curved, net, poles, tapes = [], 0, 0, 0, 0
        for comp in lod.components():
            tex = lod.faces[comp[0]][3].decode("latin1").lower()
            if "tower_net" in tex:
                net += 1
            elif any(t in tex for t in TAPES):
                tapes += 1
            elif is_m20 and is_pole(lod, comp):
                poles += 1
            elif "tower_cable" in tex and is_curved(lod, comp):
                curved += 1
                if abs(res - 4) < 0.01:
                    removed_boxes.append(lod.bbox(lod.comp_verts(comp)))
            else:
                continue
            drop += comp
        report.append((res, lod, drop, curved, net, poles, tapes))
    # Ombre proche (résolution 10000 dans le P3D, tirée de la LOD 4) : enveloppes des câbles retirés.
    out = []
    for res, lod, drop, curved, net, poles, tapes in report:
        hulls = 0
        if 9999 < res < 10001 and removed_boxes:
            for comp in lod.components():
                box = lod.bbox(lod.comp_verts(comp))
                if any(same_box(box, r) for r in removed_boxes):
                    drop = drop + comp
                    hulls += 1
        before = (len(lod.points), len(lod.faces))
        if drop:
            lod.remove(drop)
        out.append((res, before, (len(lod.points), len(lod.faces)), curved, net, poles, tapes, hulls))
    data = b[:12] + b"".join(lod.dump() for lod in lods) + tail
    # Sans retrait, le fichier réécrit doit être identique : garde-fou de l'analyse.
    if not any(r[1] != r[2] for r in out):
        assert data == b, "réécriture non identique : " + path
    if write and data != b:
        open(path, "wb").write(data)
    return len(b), len(data), out


def main():
    write = "--write" in sys.argv
    total0 = total1 = 0
    for path in sorted(glob.glob(os.path.join(DATA, "relay_tower_*.p3d"))):
        s0, s1, rows = process(path, write)
        total0 += s0
        total1 += s1
        print(f"{os.path.basename(path)}  {s0} -> {s1} octets")
        for res, a, c, curved, net, poles, tapes, hulls in rows:
            if a != c:
                print(f"  LOD {res:g} : faces {a[1]} -> {c[1]}, sommets {a[0]} -> {c[0]} ; courbes {curved}, filet {net}, perches {poles}, bagues {tapes}, ombres {hulls}")
    print(f"total {total0} -> {total1} octets" + ("" if write else " (bilan, rien écrit)"))


if __name__ == "__main__":
    main()
