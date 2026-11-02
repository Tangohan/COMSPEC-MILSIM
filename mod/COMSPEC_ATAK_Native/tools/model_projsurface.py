"""
Surface de projection COMSPEC (comspec_projsurface.p3d, PBO items) : un seul rectangle 16:9 de 1,7778 x 1 m, centré sur
l'origine, tourné vers +Y, sélection « screen » (UV 0-1 plein cadre, haut de l'image en haut). Pas d'ombre ni de
géométrie : objet local posé par fn_projector devant le tableau blanc du jeu (Land_MapBoard_F, Land_MapBoard_01_Wall_F)
qui sert de toile, mis à l'échelle (setObjectScale) et incliné comme le tableau. Invisible tant qu'aucune image n'y est
posée (texture vide).

    python model_projsurface.py      écrit ../Sources/addons/items/data/comspec_projsurface.p3d (MLOD, P3DM 28.256)
Repère : X à droite, Y vers l'avant, Z vers le haut (écrit X, Z, Y dans le P3D).
"""
import os
import struct

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "..", "Sources", "addons", "items", "data", "comspec_projsurface.p3d")
TEX = "z\\comspec_atak_native\\addons\\items\\data\\"
W, H = 16 / 9, 1.0


def asciiz(s):
    return s.encode("ascii") + b"\0"


def tagg(name, data):
    return b"\x01" + asciiz(name) + struct.pack("<I", len(data)) + data


def main():
    # Vu de face (depuis +Y), la droite de l'image est -X : coin haut gauche en +X.
    verts = [(W / 2, 0, H / 2), (-W / 2, 0, H / 2), (-W / 2, 0, -H / 2), (W / 2, 0, -H / 2)]
    uvs = [(0, 0), (1, 0), (1, 1), (0, 1)]
    order = [0, 3, 2, 1]               # sens direct vu de +Y (normale +Y), comme les autres modèles du mod
    out = b"P3DM" + struct.pack("<IIIIII", 28, 256, 4, 1, 1, 0)
    for v in verts:
        out += struct.pack("<fffI", v[0], v[2], v[1], 0x02000000)
    out += struct.pack("<fff", 0.0, 0.0, -1.0)      # normale +Y (écrite opposée, axes échangés)
    out += struct.pack("<I", 4)
    for i in order:
        out += struct.pack("<IIff", i, 0, uvs[i][0], uvs[i][1])
    out += struct.pack("<I", 0) + asciiz("") + asciiz(TEX + "comspec_proj_on.rvmat")
    out += b"TAGG"
    out += tagg("#SharpEdges#", b"".join(struct.pack("<II", a, b) for a, b in ((0, 1), (1, 2), (2, 3), (0, 3))))
    out += tagg("#UVSet#", struct.pack("<I", 0) + b"".join(struct.pack("<ff", *uvs[i]) for i in order))
    out += tagg("screen", bytes([1, 1, 1, 1]) + bytes([1]))
    out += b"\x01" + asciiz("#EndOfFile#") + struct.pack("<I", 0)
    out += struct.pack("<f", 1.0)
    with open(OUT, "wb") as f:
        f.write(b"MLOD" + struct.pack("<II", 257, 1) + out)
    print("écrit", OUT)


if __name__ == "__main__":
    main()
