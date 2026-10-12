#!/usr/bin/env python3
"""
Gestes du joueur (ATAK natif 1.33.0) : génère les animations non binarisées (RTM_0101) de
Sources/addons/main/anim/ :
  comspec_finger.rtm       doigt d'honneur, bras gauche (geste GestureComspecFinger)
  comspec_selfie_up.rtm    bras droit qui se lève pour le selfie (GestureComspecSelfieUp)
  comspec_selfie_hold.rtm  bras droit tendu, téléphone face au visage, en boucle (GestureComspecSelfie)
  comspec_selfie_down.rtm  bras qui redescend (GestureComspecSelfieDown)

Usage : python3 gen_gestures.py [--preview dossier]   (numpy + scipy ; matplotlib pour --preview)

Principe : cinématique directe sur le squelette OFP2_ManSkeleton. Chaque os reçoit une rotation dans
l'espace du modèle ; son pivot suit celui de son parent. Une matrice RTM est la transformation de l'os
depuis la pose de référence (bras en A), en vecteur ligne : p' = p · R + T (lignes 0-2 = R, ligne 3 = T).
Repère du squelette : x = gauche du personnage, y = haut, z = arrière (avant = -z).
Les pivots ci-dessous sont les centres d'articulation du squelette d'Arma 3, mesurés (moindres carrés sur
la continuité des articulations dans des animations libres) ; aucune image clé n'est reprise d'un autre mod.
Entre deux poses clés, chaque os interpole sa rotation relative à son parent (slerp, adouci) : les
articulations plient sur leur chemin naturel, sans détacher ni étirer les segments.
"""
import argparse, os, struct
import numpy as np
from scipy.spatial.transform import Rotation as Rot, Slerp

# (os, parent, pivot x y z dans la pose de référence ; None = os rigide avec son parent)
SKELETON = [
    ("Pelvis", "", (0, 0, 0)),
    ("Spine", "Pelvis", (0.0, 0.0, 0.0004)),
    ("Spine1", "Spine", (0.0023, 0.1558, 0.0123)),
    ("Spine2", "Spine1", (0.0, 0.2866, 0.0267)),
    ("Spine3", "Spine2", (-0.0, 0.4381, 0.0335)),
    ("camera", "Pelvis", None),
    ("weapon", "Spine1", None),
    ("launcher", "Spine1", None),
    ("Neck", "Spine3", (0.0, 0.5551, 0.0247)),
    ("Neck1", "Neck", (0.0002, 0.6265, 0.0082)),
    ("Head", "Neck1", (0.0, 0.6675, 0.0062)),
    ("LeftShoulder", "Spine3", (0.0597, 0.5029, 0.004)),
    ("LeftArm", "LeftShoulder", (0.2027, 0.4767, 0.0503)),
    ("LeftArmRoll", "LeftArm", (0.2795, 0.3935, 0.0606)),
    ("LeftForeArm", "LeftArmRoll", (0.3762, 0.2814, 0.0838)),
    ("LeftForeArmRoll", "LeftForeArm", (0.4579, 0.2008, 0.0788)),
    ("LeftHand", "LeftForeArmRoll", (0.5853, 0.0696, 0.0739)),
    ("LeftHandRing", "LeftHand", (0.6316, 0.0199, 0.0884)),
    ("LeftHandRing1", "LeftHandRing", (0.65, 0.0017, 0.0867)),
    ("LeftHandRing2", "LeftHandRing1", (0.6741, -0.0216, 0.1)),
    ("LeftHandRing3", "LeftHandRing2", (0.6915, -0.0385, 0.1031)),
    ("LeftHandPinky1", "LeftHandRing", (0.6456, 0.0018, 0.1038)),
    ("LeftHandPinky2", "LeftHandPinky1", (0.6571, -0.012, 0.1243)),
    ("LeftHandPinky3", "LeftHandPinky2", (0.6733, -0.0267, 0.1222)),
    ("LeftHandMiddle1", "LeftHand", (0.6552, 0.0005, 0.063)),
    ("LeftHandMiddle2", "LeftHandMiddle1", (0.685, -0.031, 0.0603)),
    ("LeftHandMiddle3", "LeftHandMiddle2", (0.7026, -0.0504, 0.0681)),
    ("LeftHandIndex1", "LeftHand", (0.6477, 0.0039, 0.0418)),
    ("LeftHandIndex2", "LeftHandIndex1", (0.6797, -0.0237, 0.0338)),
    ("LeftHandIndex3", "LeftHandIndex2", (0.6965, -0.04, 0.03)),
    ("LeftHandThumb1", "LeftHand", (0.5879, 0.0624, 0.0524)),
    ("LeftHandThumb2", "LeftHandThumb1", (0.6142, 0.0354, 0.0128)),
    ("LeftHandThumb3", "LeftHandThumb2", (0.631, 0.0132, -0.0094)),
    ("RightShoulder", "Spine3", (-0.0558, 0.5098, 0.0114)),
    ("RightArm", "RightShoulder", (-0.203, 0.477, 0.05)),
    ("RightArmRoll", "RightArm", (-0.2572, 0.4217, 0.0514)),
    ("RightForeArm", "RightArmRoll", (-0.3784, 0.2835, 0.0839)),
    ("RightForeArmRoll", "RightForeArm", (-0.475, 0.1863, 0.0822)),
    ("RightHand", "RightForeArmRoll", (-0.5874, 0.0655, 0.0748)),
    ("RightHandRing", "RightHand", (-0.6466, -0.0115, 0.0724)),
    ("RightHandRing1", "RightHandRing", (-0.6508, 0.0016, 0.083)),
    ("RightHandRing2", "RightHandRing1", (-0.6754, -0.0229, 0.0922)),
    ("RightHandRing3", "RightHandRing2", (-0.692, -0.0395, 0.0987)),
    ("RightHandPinky1", "RightHandRing", (-0.642, 0.0047, 0.1037)),
    ("RightHandPinky2", "RightHandPinky1", (-0.6589, -0.0136, 0.1168)),
    ("RightHandPinky3", "RightHandPinky2", (-0.6735, -0.0278, 0.122)),
    ("RightHandMiddle1", "RightHand", (-0.6553, 0.0005, 0.0611)),
    ("RightHandMiddle2", "RightHandMiddle1", (-0.685, -0.0308, 0.0604)),
    ("RightHandMiddle3", "RightHandMiddle2", (-0.7029, -0.0498, 0.0598)),
    ("RightHandIndex1", "RightHand", (-0.6548, 0.005, 0.0622)),
    ("RightHandIndex2", "RightHandIndex1", (-0.6789, -0.0227, 0.0289)),
    ("RightHandIndex3", "RightHandIndex2", (-0.6954, -0.0385, 0.0226)),
    ("RightHandThumb1", "RightHand", (-0.5872, 0.0755, 0.0468)),
    ("RightHandThumb2", "RightHandThumb1", (-0.6131, 0.0341, 0.0112)),
    ("RightHandThumb3", "RightHandThumb2", (-0.631, 0.0133, -0.0083)),
    ("LeftUpLeg", "Pelvis", (0.1143, -0.0594, -0.0)),
    ("LeftUpLegRoll", "LeftUpLeg", (0.1255, -0.2476, -0.008)),
    ("LeftLeg", "LeftUpLegRoll", (0.1339, -0.4673, -0.0194)),
    ("LeftLegRoll", "LeftLeg", (0.1405, -0.4689, -0.0176)),
    ("LeftFoot", "LeftLegRoll", (0.1696, -0.8886, 0.0827)),
    ("LeftToeBase", "LeftFoot", (0.1737, -0.9916, -0.08)),
    ("RightUpLeg", "Pelvis", (-0.1143, -0.0594, -0.0)),
    ("RightUpLegRoll", "RightUpLeg", (-0.1269, -0.2591, -0.0091)),
    ("RightLeg", "RightUpLegRoll", (-0.1439, -0.4667, -0.0198)),
    ("RightLegRoll", "RightLeg", (-0.1505, -0.6175, 0.0189)),
    ("RightFoot", "RightLegRoll", (-0.1673, -0.8896, 0.0833)),
    ("RightToeBase", "RightFoot", (-0.1737, -0.9916, -0.08)),
]
PARENT = {b: p for b, p, _ in SKELETON}
PIV = {b: np.array(x, float) for b, _, x in SKELETON if x is not None}
BONES = [b for b, _, _ in SKELETON]
I3 = np.eye(3)
FWD = np.array([0, 0, -1.0]); UP = np.array([0, 1.0, 0])
SIDE = {"L": "Left", "R": "Right"}


def nrm(v):
    v = np.asarray(v, float); return v / np.linalg.norm(v)


def axang(a, deg):
    a = nrm(a); t = np.radians(deg)
    K = np.array([[0, -a[2], a[1]], [a[2], 0, -a[0]], [-a[1], a[0], 0]])
    return I3 + np.sin(t) * K + (1 - np.cos(t)) * K @ K


def swing(a, b):
    a = nrm(a); b = nrm(b); c = np.cross(a, b); s = np.linalg.norm(c)
    return I3.copy() if s < 1e-8 else axang(c, np.degrees(np.arctan2(s, np.dot(a, b))))


def frame(a0, b0, a1, b1):
    """Rotation qui amène (a0, b0) sur (a1, b1), b orthogonalisé contre a."""
    def basis(a, b):
        a = nrm(a); b = nrm(b - np.dot(b, a) * a); return np.column_stack([a, b, np.cross(a, b)])
    return basis(a1, b1) @ basis(a0, b0).T


def twist_angle(R, axis):
    axis = nrm(axis); p = nrm(np.cross(axis, [0.3, 0.5, 0.8])); r = R @ p; r = r - np.dot(r, axis) * axis
    return np.degrees(np.arctan2(np.dot(np.cross(p, r), axis), np.dot(p, r)))


def sx(side):
    return 1.0 if side == "L" else -1.0


def dirb(a, b):
    return nrm(PIV[b] - PIV[a])


def palm_normal(side):
    S = SIDE[side]; h0 = dirb(S + "Hand", S + "HandMiddle1")
    across = nrm(PIV[S + "HandPinky1"] - PIV[S + "HandIndex1"])
    n = np.cross(across, h0) if side == "L" else np.cross(h0, across)
    return nrm(-n if n[1] > 0 else n)  # pose de référence : paume vers le bas et vers le corps


class Pose:
    def __init__(self):
        self.W = {}  # os -> rotation dans l'espace du modèle (convention colonne)

    def world(self):
        W = {}
        def g(b):
            if b not in W:
                par = PARENT[b]
                W[b] = self.W[b] if b in self.W else (g(par) if par else I3)
            return W[b]
        for b in BONES: g(b)
        return W

    def matrices(self):
        """Matrices RTM (4x3, vecteur ligne) ; le pivot de chaque os suit son parent."""
        W = self.world(); M = {}
        def get(b):
            if b in M: return M[b]
            par = PARENT[b]
            pm = get(par) if par else np.vstack([I3, np.zeros(3)])
            if b not in PIV:
                M[b] = pm; return pm
            c = PIV[b]; cnew = c @ pm[:3] + pm[3]; Wr = W[b].T
            M[b] = np.vstack([Wr, cnew - c @ Wr]); return M[b]
        for b in BONES: get(b)
        return M


def arm(pose, side, u, f, h, n, clav=0.0):
    """Bras : u = direction épaule->coude, f = coude->poignet, h = direction des doigts, n = normale de la paume."""
    S = SIDE[side]
    sh, ar, arr, fa, far, ha = [S + x for x in ("Shoulder", "Arm", "ArmRoll", "ForeArm", "ForeArmRoll", "Hand")]
    pose.W[sh] = axang(np.cross(dirb(sh, ar), UP), clav) if clav else I3.copy()
    u0 = dirb(ar, fa); f0 = dirb(fa, ha); h0 = dirb(ha, S + "HandMiddle1"); n0 = palm_normal(side)
    fp = f - np.dot(f, u) * u
    if np.linalg.norm(fp) < 0.15: fp = fp + UP * 0.2
    Wua = frame(u0, FWD - np.dot(FWD, u0) * u0, u, fp)   # le coude plie vers l'avant du bras
    Wfa = swing(Wua @ f0, f) @ Wua
    Wh = frame(h0, n0, h, n)
    tw = twist_angle(Wh @ np.linalg.inv(Wfa), f)          # rotation de l'avant-bras répartie sur les os roll
    pose.W[ar] = Wua; pose.W[arr] = Wua
    pose.W[fa] = axang(f, 0.25 * tw) @ Wfa; pose.W[far] = axang(f, 0.7 * tw) @ Wfa
    pose.W[ha] = Wh


def finger(pose, side, name, a1, a2, a3):
    S = SIDE[side]; n0 = palm_normal(side); W = pose.W[S + "Hand"].copy()
    segs = [S + "Hand" + name + str(i) for i in (1, 2, 3)]
    if name in ("Ring", "Pinky"): pose.W[S + "HandRing"] = W.copy()
    for i, ang in enumerate((a1, a2, a3)):
        d0 = dirb(segs[i], segs[i + 1]) if i < 2 else dirb(segs[1], segs[2])
        W = W @ axang(np.cross(d0, n0), ang)  # vers la paume
        pose.W[segs[i]] = W


def thumb(pose, side, a1, a2, a3, across=0.6):
    S = SIDE[side]; n0 = palm_normal(side); W = pose.W[S + "Hand"].copy()
    segs = [S + "HandThumb" + str(i) for i in (1, 2, 3)]
    tgt = nrm(n0 * (1 - across) + nrm(PIV[S + "HandPinky1"] - PIV[S + "HandIndex1"]) * across)
    for i, ang in enumerate((a1, a2, a3)):
        d0 = dirb(segs[min(i, 1)], segs[min(i + 1, 2)])
        W = W @ axang(np.cross(d0, tgt), ang); pose.W[segs[i]] = W


def fist(pose, side, keep=(), curl=(85, 95, 60)):
    for f in ("Index", "Middle", "Ring", "Pinky"):
        finger(pose, side, f, *((0, 0, 0) if f in keep else curl))


def blend(A, B, t):
    WA = A.world(); WB = B.world(); W = {}
    def g(b):
        if b not in W:
            par = PARENT[b]
            pa = WA[par] if par else I3; pb = WB[par] if par else I3
            l = Slerp([0, 1], Rot.from_matrix([pa.T @ WA[b], pb.T @ WB[b]]))([t]).as_matrix()[0]
            W[b] = (g(par) if par else I3) @ l
        return W[b]
    for b in BONES: g(b)
    out = Pose(); out.W = W; return out


# ---- Poses clés -------------------------------------------------------------------------------------
def hang(side):
    """Bras le long du corps, coude un peu plié, doigts détendus (pose sans arme)."""
    s = sx(side); p = Pose()
    arm(p, side, nrm([0.12 * s, -1, 0.05]), nrm([0.12 * s, -0.9, -0.3]), nrm([0.05 * s, -1, -0.15]), nrm([-s, 0, 0]))
    fist(p, side, curl=(25, 30, 20)); thumb(p, side, 10, 10, 5)
    return p


def finger_up(pump=0.0):
    """Main gauche levée devant l'épaule, dos de la main vers la cible, majeur tendu ; pump = coup vers l'avant."""
    p = Pose()
    arm(p, "L", nrm([0.25, -0.35, -0.85]), nrm([-0.12, 0.9 - 0.25 * pump, -0.4 - 0.4 * pump]),
        nrm([-0.05, 1 - 0.3 * pump, -0.1 - 0.5 * pump]), nrm([0, 0, 1]), clav=10)
    fist(p, "L", keep=("Middle",), curl=(95, 100, 70)); thumb(p, "L", 35, 40, 30, across=0.8)
    return p


def selfie(sway=0.0):
    """Bras droit tendu vers l'avant et le haut, main à hauteur des yeux, paume (et écran) vers le visage."""
    p = Pose(); d = nrm([0.19, 0.46, -0.87])
    arm(p, "R", nrm(d + [0, -0.18, 0.02]), nrm(d + [0.02, 0.06 + 0.02 * sway, 0]), nrm([0.55, 0.8, 0.05]), -d, clav=6)
    fist(p, "R", curl=(40, 45, 30)); thumb(p, "R", 25, 15, 10, across=0.3)
    return p


# ---- Écriture ---------------------------------------------------------------------------------------
def ease(t):
    return t * t * (3 - 2 * t)


def timeline(keys, step):
    ph = sorted(set([k[0] for k in keys] + [round(x, 4) for x in np.arange(0, 1.0001, step)]))
    out = []
    for t in ph:
        for (t0, a), (t1, b) in zip(keys, keys[1:]):
            if t0 <= t <= t1:
                out.append((t, blend(a, b, 0 if t1 == t0 else ease((t - t0) / (t1 - t0))).matrices())); break
    return out


def write_rtm(path, frames):
    data = bytearray(b"RTM_0101") + struct.pack("<3f", 0, 0, 0) + struct.pack("<2i", len(frames), len(BONES))
    for b in BONES: data += b.encode().ljust(32, b"\0")
    for t, M in frames:
        data += struct.pack("<f", t)
        for b in BONES: data += b.encode().ljust(32, b"\0") + struct.pack("<12f", *M[b].reshape(12))
    with open(path, "wb") as fh: fh.write(data)


def build():
    hl, hr = hang("L"), hang("R")
    f0, f1 = finger_up(0), finger_up(1)
    s0 = selfie(0)
    return {
        "comspec_finger": timeline([(0, hl), (0.18, f0), (0.3, f1), (0.42, f0), (0.54, f1), (0.66, f0), (0.84, f0), (1, hl)], 0.02),
        "comspec_selfie_up": timeline([(0, hr), (1, s0)], 0.05),
        "comspec_selfie_hold": timeline([(0, s0), (0.5, selfie(1)), (1, s0)], 0.05),
        "comspec_selfie_down": timeline([(0, s0), (1, hr)], 0.05),
    }


def preview(anims, folder):
    """Silhouettes filaires (vue de face, de côté, main) des images clés, pour relire une pose sans le jeu."""
    import matplotlib; matplotlib.use("Agg"); import matplotlib.pyplot as plt
    def ap(p, M):
        q = p @ M[:3] + M[3]; return np.array([-q[0], q[1], q[2]])  # repère gaucher : miroir pour dessiner
    def col(b):
        for k, c in (("Middle", "#d62728"), ("Thumb", "#2ca02c"), ("Index", "#ff7f0e")):
            if k in b: return c
        return "#1f4e9c" if b.startswith("Left") else ("#7b3fa0" if b.startswith("Right") else "#222")
    def draw(ax, M, az, focus=None, r=0.18):
        a = np.radians(az); F = np.array([np.sin(a), 0, np.cos(a)]); R = nrm(np.cross(F, UP)); U = np.cross(R, F)
        segs = [(b, ap(PIV[PARENT[b]], M[PARENT[b]]), ap(PIV[b], M[b])) for b in BONES if PARENT[b] in PIV and b in PIV]
        for S in ("Left", "Right"):
            for f in ("Index", "Middle", "Ring", "Pinky", "Thumb"):
                b3, b2 = f"{S}Hand{f}3", f"{S}Hand{f}2"
                segs.append((b3, ap(PIV[b3], M[b3]), ap(2 * PIV[b3] - PIV[b2], M[b3])))
        segs.sort(key=lambda s: -np.dot((s[1] + s[2]) / 2, F))
        for b, p, q in segs:
            ax.plot([p @ R, q @ R], [p @ U, q @ U], color=col(b), lw=3 if "Hand" in b else 2, solid_capstyle="round")
        ax.set_aspect("equal"); ax.set_xticks([]); ax.set_yticks([])
        if focus:
            c = ap(PIV[focus], M[focus]); ax.set_xlim(c @ R - r, c @ R + r); ax.set_ylim(c @ U - r, c @ U + r)
        else:
            ax.set_xlim(-0.9, 0.9); ax.set_ylim(-1.1, 1.1)
    os.makedirs(folder, exist_ok=True)
    def at(frames, t):
        return min(frames, key=lambda f: abs(f[0] - t))[1]
    fin, up = anims["comspec_finger"], anims["comspec_selfie_up"]
    for name, sel, hand in (("doigt-honneur", [at(fin, t) for t in (0, 0.1, 0.18, 0.3, 0.84)], "LeftHand"),
                            ("selfie", [at(up, 0), at(up, 0.5), at(anims["comspec_selfie_hold"], 0)], "RightHand")):
        fig, axs = plt.subplots(3, len(sel), figsize=(3.2 * len(sel), 9.6))
        for i, M in enumerate(sel):
            draw(axs[0, i], M, 0); draw(axs[1, i], M, 90); draw(axs[2, i], M, 0, hand)
        axs[0, 0].set_ylabel("de face"); axs[1, 0].set_ylabel("de profil"); axs[2, 0].set_ylabel("main")
        fig.tight_layout(); fig.savefig(os.path.join(folder, name + ".png"), dpi=80); plt.close(fig)


if __name__ == "__main__":
    ap_ = argparse.ArgumentParser(); ap_.add_argument("--preview")
    args = ap_.parse_args()
    out = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "Sources", "addons", "main", "anim")
    anims = build()
    for name, frames in anims.items():
        write_rtm(os.path.join(out, name + ".rtm"), frames); print(name, len(frames), "images")
    if args.preview: preview(anims, args.preview)
