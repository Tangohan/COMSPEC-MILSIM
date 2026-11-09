"""
Textures, matériaux et icônes des lunettes de sport COMSPEC SSE (addon eyewear).

    PYTHONUTF8=1 python -I gen_textures.py              # PNG sources dans tools/eyewear/src, PAA + RVMAT dans addons/eyewear/data
    PYTHONUTF8=1 python -I gen_textures.py --png        # PNG seulement (pas de conversion PAA)
    PYTHONUTF8=1 python -I gen_textures.py --preview D  # rendus de contrôle (face, 3/4, profil) dans le dossier D

Par coloris (voir eyewear_spec.COLOURS) :
  <id>_frame_co    1024 : atlas de la monture (barre, branches, nez, embouts, plaque logo), régions dans gen_p3d.ATLAS
  <id>_frame_nohq  1024 : normal map (évents, logo en relief ou gravé)
  <id>_frame_smdi  1024 : spéculaire (G intensité, B brillance) : poli (Oakley), mat (Hurricane), brillant (Wind)
  <id>_lens_ca      512 : verre, dégradé miroir et alpha (transparence partielle)
  icon_<id>_ca      128 : icône d'inventaire (rendu 3/4 du vrai maillage, fond transparent)
  <id>_frame.rvmat, <id>_lens.rvmat : shader Super (comme a3\\characters_f\\heads\\glasses\\data\\sunglasses_sport.rvmat ;
  le verre vanilla d'Arma 3, glass_veh.rvmat compris, est aussi en Super : le shader « Glass » d'Arma 2 n'y sert plus).
Conversion PAA : ImageToPAA des Arma 3 Tools (l'ancien PAA est supprimé d'abord), sinon HEMTT.
"""
import math
import os
import shutil
import subprocess
import sys

import numpy as np
from PIL import Image, ImageDraw, ImageFilter, ImageFont

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
import eyewear_spec as S  # noqa: E402

SRC = os.path.join(HERE, "src")
DATA = os.path.normpath(os.path.join(HERE, "..", "..", "addons", "eyewear", "data"))
IMAGETOPAA = r"F:\SteamLibrary\steamapps\common\Arma 3 Tools\ImageToPAA\ImageToPAA.exe"
FONT = r"C:\Windows\Fonts\arialbi.ttf"
FA = 1024
FL = 512


def region_px(name, size=FA):
    from gen_p3d import ATLAS
    u0, v0, u1, v1 = ATLAS[name]
    return int(u0 * size), int(v0 * size), int(u1 * size), int(v1 * size)


def smooth(k):
    k = np.clip(k, 0.0, 1.0)
    return k * k * (3 - 2 * k)


def fill_region(img, name, fn):
    """Remplit une région avec fn(u, v) -> tableau (h, w, 3) (u, v : 0..1 dans la région)."""
    x0, y0, x1, y1 = region_px(name)
    w, h = x1 - x0, y1 - y0
    u = (np.arange(w) + 0.5)[None, :] / w * np.ones((h, 1))
    v = (np.arange(h) + 0.5)[:, None] / h * np.ones((1, w))
    img[y0:y1, x0:x1, :] = fn(u, v)


def solid(rgb):
    return lambda u, v: np.ones(u.shape + (3,)) * np.array(rgb)


def rainbow(u, v, amp=1.0):
    """Monture tie-dye translucide (Wind Rainbow) : mélange violet / rose / turquoise le long de u."""
    stops = [(0.00, (0.10, 0.62, 0.70)), (0.10, (0.55, 0.28, 0.72)), (0.22, (0.90, 0.35, 0.70)),
             (0.33, (0.45, 0.75, 0.95)), (0.45, (0.55, 0.28, 0.72)), (0.55, (0.55, 0.28, 0.72)),
             (0.67, (0.45, 0.75, 0.95)), (0.78, (0.90, 0.35, 0.70)), (0.90, (0.55, 0.28, 0.72)),
             (1.00, (0.10, 0.62, 0.70))]
    xs = np.array([s[0] for s in stops])
    cs = np.array([s[1] for s in stops])
    uu = np.clip(u + 0.03 * np.sin(v * 6.28 * 2 + u * 40), 0, 1)
    out = np.stack([np.interp(uu, xs, cs[:, i]) for i in range(3)], -1)
    # Translucide : reflet clair sur le dessus du profil.
    hi = np.exp(-((v - 0.05) ** 2) / 0.004) + np.exp(-((v - 0.95) ** 2) / 0.004)
    return np.clip(out * (0.92 + 0.12 * hi[..., None]) + 0.06 * hi[..., None], 0, 1)


def frame_fn(c):
    base = np.array(c["frame"])

    def fn(u, v):
        if c.get("rainbow"):
            return rainbow(u, v)
        out = np.ones(u.shape + (3,)) * base
        if c.get("black_ends"):
            a = np.abs(2 * u - 1)
            k = smooth((a - 0.62) / 0.30)[..., None]
            smoke = np.array([0.10, 0.05, 0.03])
            out = out * (1 - k) + smoke * k
            # Translucide : légère variation et reflet sur le dessus.
            hi = np.exp(-((v - 0.05) ** 2) / 0.004) + np.exp(-((v - 0.95) ** 2) / 0.004)
            out = np.clip(out * (0.94 + 0.10 * hi[..., None]), 0, 1)
        # Légère occlusion sous la barre (côté verre, v ≈ 0,5).
        occ = 1.0 - 0.10 * np.exp(-((v - 0.5) ** 2) / 0.01)
        return out * occ[..., None]
    return fn


def temple_fn(c, cid):
    def fn(u, v):
        if cid == "radar_white":
            out = np.ones(u.shape + (3,)) * np.array(c["temple"])
            k0 = smooth((0.22 - u) / 0.02)[..., None]
            k1 = smooth((u - 0.95) / 0.01)[..., None]
            out = out * (1 - k0) + np.array(c["frame"]) * k0
            out = out * (1 - k1) + np.array(c["tip"]) * k1
            return out
        if c.get("rainbow"):
            out = rainbow(u * 0.5, v)
            k1 = smooth((u - 0.80) / 0.03)[..., None]
            return out * (1 - k1) + np.array(c["tip"]) * k1
        if c.get("black_ends"):
            out = np.ones(u.shape + (3,)) * np.array(c["temple"])
            k0 = smooth((0.10 - u) / 0.08)[..., None]
            k1 = smooth((u - 0.78) / 0.04)[..., None]
            smoke = np.array([0.10, 0.05, 0.03])
            out = out * (1 - k0) + smoke * k0
            return out * (1 - k1) + np.array(c["tip"]) * k1
        out = np.ones(u.shape + (3,)) * np.array(c["temple"])
        k1 = smooth((u - 0.30) / 0.05)[..., None]
        return out * (1 - 0.25 * k1)  # embout (earsock) un peu plus mat / foncé
    return fn


def text_mask(text, w, h, font_px, italic=True):
    img = Image.new("L", (w, h), 0)
    d = ImageDraw.Draw(img)
    try:
        f = ImageFont.truetype(FONT if italic else r"C:\Windows\Fonts\arialbd.ttf", font_px)
    except OSError:
        f = ImageFont.load_default()
    bb = d.textbbox((0, 0), text, font=f)
    tw, th = bb[2] - bb[0], bb[3] - bb[1]
    d.text(((w - tw) / 2 - bb[0], (h - th) / 2 - bb[1]), text, fill=255, font=f)
    return np.asarray(img, np.float32) / 255.0


def o_mask(w, h):
    """Logo « O » Oakley : ellipse épaisse."""
    img = Image.new("L", (w * 4, h * 4), 0)
    d = ImageDraw.Draw(img)
    cx, cy = w * 2, h * 2
    rx, ry = w * 4 * 0.20, h * 4 * 0.36
    d.ellipse([cx - rx, cy - ry, cx + rx, cy + ry], fill=255)
    d.ellipse([cx - rx * 0.62, cy - ry * 0.52, cx + rx * 0.62, cy + ry * 0.52], fill=0)
    return np.asarray(img.resize((w, h), Image.LANCZOS), np.float32) / 255.0


def logo_mask(c):
    x0, y0, x1, y1 = region_px("logo")
    w, h = x1 - x0, y1 - y0
    if c["logo"] == "O":
        return o_mask(w, h)
    return text_mask(c["logo"], w, h, int(h * 0.62))


def build_frame(cid):
    c = S.COLOURS[cid]
    col = np.zeros((FA, FA, 3), np.float32)
    hgt = np.zeros((FA, FA), np.float32)
    spec = np.zeros((FA, FA, 3), np.float32)
    spec[..., 0] = 1.0
    fill_region(col, "frame", frame_fn(c))
    fill_region(col, "temple", temple_fn(c, cid))
    fill_region(col, "nose", solid(c["nose"]))
    fill_region(col, "pod", solid(c["frame"]))
    fill_region(col, "logoside", solid(c["logo_bg"]))
    fill_region(col, "pads", solid((0.03, 0.03, 0.035)))
    # Logo de la plaque.
    x0, y0, x1, y1 = region_px("logo")
    mk = logo_mask(c)
    bg = np.array(c["logo_bg"]) * np.ones((y1 - y0, x1 - x0, 3))
    if c.get("rainbow"):
        bg = bg * (0.9 + 0.2 * np.linspace(0, 1, x1 - x0)[None, :, None])
    col[y0:y1, x0:x1] = bg * (1 - mk[..., None]) + np.array(c["logo_fg"]) * mk[..., None]
    engraved = cid == "hurricane_black"
    hgt[y0:y1, x0:x1] = (-1.0 if engraved else 1.0) * mk
    # Évents de la Radar : deux fentes sombres sur le dessus de la barre, de part et d'autre du centre.
    fx0, fy0, fx1, fy1 = region_px("frame")
    fw, fh = fx1 - fx0, fy1 - fy0
    if c.get("vents"):
        for uc in (0.385, 0.615):
            for vband in ((0.0, 0.07), (0.93, 1.0)):
                xa, xb = int(fx0 + (uc - 0.035) * fw), int(fx0 + (uc + 0.035) * fw)
                ya, yb = int(fy0 + vband[0] * fh), int(fy0 + vband[1] * fh)
                col[ya:yb, xa:xb] = np.array(c["vents"])
                hgt[ya:yb, xa:xb] = -1.0
    # Inscription D.FRANKLIN en relief sur l'avant de la barre (Wind), légèrement plus foncée.
    if c["shape"] == "wind":
        w, h = int(0.10 * fw), int(0.10 * fh)
        mk2 = text_mask("D.FRANKLIN", w, h, int(h * 0.8))
        xa = fx0 + int(0.5 * fw - w / 2)
        ya = fy0 + int(0.25 * fh - h / 2)
        col[ya:ya + h, xa:xa + w] *= (1 - 0.25 * mk2[..., None])
        hgt[ya:ya + h, xa:xa + w] += mk2
    # Spéculaire.
    for name, (g, b) in (("frame", c["frame_gloss"]), ("pod", c["frame_gloss"]), ("logo", c["frame_gloss"]),
                         ("logoside", c["frame_gloss"]), ("temple", c["temple_gloss"]), ("nose", (0.25, 0.30)),
                         ("pads", (0.08, 0.10))):
        rx0, ry0, rx1, ry1 = region_px(name)
        spec[ry0:ry1, rx0:rx1, 1] = g
        spec[ry0:ry1, rx0:rx1, 2] = b
    if cid == "radar_white":
        tx0, ty0, tx1, ty1 = region_px("temple")
        u = (np.arange(tx1 - tx0) + 0.5) / (tx1 - tx0)
        k = ((u < 0.22) | (u > 0.95)).astype(np.float32)
        spec[ty0:ty1, tx0:tx1, 1] = c["temple_gloss"][0] * (1 - k) + c["frame_gloss"][0] * k
        spec[ty0:ty1, tx0:tx1, 2] = c["temple_gloss"][1] * (1 - k) + c["frame_gloss"][1] * k
    # Normal map depuis la hauteur.
    hb = np.asarray(Image.fromarray((hgt * 60 + 128).clip(0, 255).astype(np.uint8)).filter(ImageFilter.GaussianBlur(1.2)),
                    np.float32) / 255.0
    gy, gx = np.gradient(hb)
    nx, ny, nz = -gx * 6.0, gy * 6.0, np.ones_like(hb)
    ln = np.sqrt(nx * nx + ny * ny + nz * nz)
    nrm = np.stack([nx / ln, ny / ln, nz / ln], -1) * 0.5 + 0.5
    to8 = lambda a: Image.fromarray((np.clip(a, 0, 1) * 255 + 0.5).astype(np.uint8))  # noqa: E731
    return to8(col), to8(nrm), to8(spec)


def build_lens(cid):
    c = S.COLOURS[cid]
    img = np.zeros((FL, FL, 4), np.float32)
    for y in range(FL):
        v = (y + 0.5) / FL
        for x in range(FL):
            u = (x + 0.5) / FL
            img[y, x, :3] = S.lens_colour(cid, u, v)
    img[..., 3] = c["lens_alpha"]
    return Image.fromarray((img * 255 + 0.5).astype(np.uint8), "RGBA")


def rvmat_frame(cid):
    c = S.COLOURS[cid]
    p = S.PREFIX + cid
    g = c["frame_gloss"][1]
    power = int(40 + 260 * g)
    return RVMAT.format(ambient="1,1,1,1", diffuse="1,1,1,1", emmisive="0,0,0,1", specular="1,1,1,1", power=power,
                        nohq=p + "_frame_nohq.paa", smdi=p + "_frame_smdi.paa", fresnel="0.6,0.3", flags="")


def rvmat_lens(cid):
    c = S.COLOURS[cid]
    r, g, b = c["spec"]
    return RVMAT.format(ambient="1,1,1,1", diffuse="0.35,0.35,0.35,1", emmisive="0,0,0,1",
                        specular="%.2f,%.2f,%.2f,1" % (r, g, b), power=600,
                        nohq="#(argb,8,8,3)color(0.5,0.5,1,1,NOHQ)", smdi="#(argb,8,8,3)color(1,1,1,1,SMDI)",
                        fresnel="1.6,0.9", flags="")


RVMAT = """// Généré par tools/eyewear/gen_textures.py
ambient[] = {{{ambient}}};
diffuse[] = {{{diffuse}}};
forcedDiffuse[] = {{0,0,0,0}};
emmisive[] = {{{emmisive}}};
specular[] = {{{specular}}};
specularPower = {power};
PixelShaderID = "Super";
VertexShaderID = "Super";{flags}
class Stage1 {{ texture = "{nohq}"; uvSource = "tex"; class uvTransform {{ aside[] = {{1,0,0}}; up[] = {{0,1,0}}; dir[] = {{0,0,0}}; pos[] = {{0,0,0}}; }}; }};
class Stage2 {{ texture = "#(argb,8,8,3)color(0.5,0.5,0.5,1,DT)"; uvSource = "tex"; class uvTransform {{ aside[] = {{1,0,0}}; up[] = {{0,1,0}}; dir[] = {{0,0,0}}; pos[] = {{0,0,0}}; }}; }};
class Stage3 {{ texture = "#(argb,8,8,3)color(0,0,0,0,MC)"; uvSource = "tex"; class uvTransform {{ aside[] = {{1,0,0}}; up[] = {{0,1,0}}; dir[] = {{0,0,0}}; pos[] = {{0,0,0}}; }}; }};
class Stage4 {{ texture = "#(argb,8,8,3)color(1,1,1,1,AS)"; uvSource = "tex"; class uvTransform {{ aside[] = {{1,0,0}}; up[] = {{0,1,0}}; dir[] = {{0,0,0}}; pos[] = {{0,0,0}}; }}; }};
class Stage5 {{ texture = "{smdi}"; uvSource = "tex"; class uvTransform {{ aside[] = {{1,0,0}}; up[] = {{0,1,0}}; dir[] = {{0,0,0}}; pos[] = {{0,0,0}}; }}; }};
class Stage6 {{ texture = "#(ai,64,64,1)fresnel({fresnel})"; uvSource = "none"; }};
class Stage7 {{ useWorldEnvMap = "true"; texture = "a3\\data_f\\env_land_co.paa"; uvSource = "none"; }};
"""


# ------------------------------------------------------------------ rendu logiciel (icônes, contrôle)
def render(cid, size, yaw, pitch, bg=None, ss=4, mesh=None, frame_img=None, lens_img=None, zoom=1.0):
    import gen_p3d as G
    if mesh is None:
        mesh = G.build_visual(cid)
    if frame_img is None:
        frame_img = build_frame(cid)[0]
    if lens_img is None:
        lens_img = build_lens(cid)
    fa = np.asarray(frame_img, np.float32) / 255.0
    la = np.asarray(lens_img, np.float32) / 255.0
    W = size * ss
    cy_, sy_ = math.cos(math.radians(yaw)), math.sin(math.radians(yaw))
    cp, sp = math.cos(math.radians(pitch)), math.sin(math.radians(pitch))

    def tr(p):
        x, y, z = p
        x, z = x * cy_ + z * sy_, -x * sy_ + z * cy_
        y, z = y * cp - z * sp, y * sp + z * cp
        return (x, y, z)

    P = [tr(p) for p in mesh.pts]
    xs = [p[0] for p in P]
    ys = [p[1] for p in P]
    cx, cyy = (min(xs) + max(xs)) / 2, (min(ys) + max(ys)) / 2
    scale = 0.90 * W / max(max(xs) - min(xs), max(ys) - min(ys)) * zoom
    scr = [((p[0] - cx) * scale + W / 2, -(p[1] - cyy) * scale + W / 2) for p in P]
    light = G.norm((-0.45, 0.65, 0.75))
    Lw = S.SHAPES[S.COLOURS[cid]["shape"]]
    lens = G.Lens(S.COLOURS[cid]["shape"])
    wc = lens.z0 - lens.Rh
    opaque, glass, front = [], [], []
    for idx, uvs, kind in mesh.faces:
        ps = [P[i] for i in idx]
        n = (0.0, 0.0, 0.0)
        for k in range(1, len(ps) - 1):
            n = G.add(n, G.cross(G.sub(ps[k], ps[0]), G.sub(ps[k + 1], ps[0])))
        n = G.norm(n)
        if n[2] <= 0:
            continue
        depth = sum(p[2] for p in ps) / len(ps)
        u = sum(q[0] for q in uvs) / len(uvs)
        v = sum(q[1] for q in uvs) / len(uvs)
        lam = max(0.0, G.dot(n, light))
        refl = G.sub(G.mul(n, 2 * G.dot(n, light)), light)
        if kind == "lens":
            t = la[min(FL - 1, int(v * FL)), min(FL - 1, int(u * FL))]
            rgb = np.array(t[:3]) * (0.55 + 0.45 * lam) + 0.55 * max(0.0, refl[2]) ** 40
            glass.append((depth, [scr[i] for i in idx], rgb, t[3]))
        else:
            t = fa[min(FA - 1, int(v * FA)), min(FA - 1, int(u * FA))]
            rgb = np.array(t[:3]) * (0.35 + 0.65 * lam) + 0.35 * max(0.0, refl[2]) ** 25
            lp = mesh.pts[idx[0]]
            radial = math.sqrt(lp[0] ** 2 + (lp[2] - wc) ** 2)
            item = (depth, [scr[i] for i in idx], rgb)
            (front if radial > lens.Rh - 0.0006 and abs(lp[0]) < 0.07 else opaque).append(item)
    img = Image.new("RGBA", (W, W), (0, 0, 0, 0) if bg is None else bg)
    d = ImageDraw.Draw(img)

    def draw(items, alpha=None):
        for it in sorted(items, key=lambda t: t[0]):
            col = tuple(int(255 * min(1.0, max(0.0, x))) for x in it[2]) + (255,)
            d.polygon(it[1], fill=col)

    draw(opaque)
    layer = Image.new("RGBA", (W, W), (0, 0, 0, 0))
    dl = ImageDraw.Draw(layer)
    for it in sorted(glass, key=lambda t: t[0]):
        col = tuple(int(255 * min(1.0, max(0.0, x))) for x in it[2]) + (int(255 * it[3]),)
        dl.polygon(it[1], fill=col)
    img = Image.alpha_composite(img, layer)
    d = ImageDraw.Draw(img)
    draw(front)
    del Lw
    return img.resize((size, size), Image.LANCZOS)


# ------------------------------------------------------------------ PAA
def to_paa(png, paa):
    if os.path.exists(paa):
        os.remove(paa)
    if os.path.exists(IMAGETOPAA):
        subprocess.run([IMAGETOPAA, png, paa], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    else:
        subprocess.run([shutil.which("hemtt") or "hemtt", "utils", "paa", "convert", png, paa], check=True,
                       stdout=subprocess.DEVNULL)
    if not os.path.exists(paa):
        raise SystemExit("conversion ratée : " + paa)


def main():
    if "--preview" in sys.argv:
        out = sys.argv[sys.argv.index("--preview") + 1]
        os.makedirs(out, exist_ok=True)
        import gen_p3d as G
        only = sys.argv[sys.argv.index("--only") + 1].split(",") if "--only" in sys.argv else S.ORDER
        for cid in only:
            mesh = G.build_visual(cid)
            fr = build_frame(cid)[0]
            ln = build_lens(cid)
            for tag, yaw, pitch in (("face", 0, 4), ("34", -35, 10), ("profil", -90, 2), ("dessus", 0, 70), ("arriere", 160, 15)):
                render(cid, 640, yaw, pitch, bg=(225, 225, 228, 255), mesh=mesh, frame_img=fr, lens_img=ln).save(
                    os.path.join(out, "%s_%s.png" % (cid, tag)))
            print("aperçus", cid)
        return
    png_only = "--png" in sys.argv
    os.makedirs(SRC, exist_ok=True)
    os.makedirs(DATA, exist_ok=True)
    jobs = []
    import gen_p3d as G
    for cid in S.ORDER:
        co, nohq, smdi = build_frame(cid)
        lens = build_lens(cid)
        for name, img in (("_frame_co", co), ("_frame_nohq", nohq), ("_frame_smdi", smdi), ("_lens_ca", lens)):
            p = os.path.join(SRC, cid + name + ".png")
            img.save(p)
            jobs.append((p, os.path.join(DATA, cid + name + ".paa")))
        icon = render(cid, 128, -32, 12, mesh=G.build_visual(cid), frame_img=co, lens_img=lens)
        p = os.path.join(SRC, "icon_" + cid + "_ca.png")
        icon.save(p)
        jobs.append((p, os.path.join(DATA, "icon_" + cid + "_ca.paa")))
        for name, text in (("_frame.rvmat", rvmat_frame(cid)), ("_lens.rvmat", rvmat_lens(cid))):
            with open(os.path.join(DATA, cid + name), "w", encoding="utf-8", newline="\n") as f:
                f.write(text)
        print("textures", cid)
    if png_only:
        return
    for png, paa in jobs:
        to_paa(png, paa)
        print("paa", os.path.basename(paa))


if __name__ == "__main__":
    main()
