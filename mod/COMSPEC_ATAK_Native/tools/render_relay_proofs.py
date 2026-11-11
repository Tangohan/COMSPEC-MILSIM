"""
Planches de contrôle des relais 3D (pylônes, mâts, kits, antennes murales) et aperçus Eden, rendus dans Blender.

    python render_relay_proofs.py DOSSIER_SORTIE [ANCIENS_MODELES]
        BLENDER=chemin de blender.exe (sinon « blender » du PATH) ; WORK=dossier de travail (OBJ, PNG) ;
        ANCIENS_MODELES : dossier des anciens .p3d et des PNG tower_*_co.png (comparaison ancien / nouveau), facultatif ;
        ONLY=previews : aperçus Eden seulement.
Écrit DOSSIER_SORTIE/*.png, _planche.png, _tailles.png et Sources/addons/towers/data/*_preview.jpg.
"""
import json
import os
import shutil
import struct
import subprocess
import sys
import tempfile

from PIL import Image, ImageDraw

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import model_relay_kits as kits  # noqa: E402
import model_relay_towers as towers  # noqa: E402
import model_relay_trees as trees  # noqa: E402
import relay3d as r3  # noqa: E402
import relay_tex  # noqa: E402

HERE = os.path.dirname(os.path.abspath(__file__))
TOWERS = ["relay_tower_m12", "relay_tower_m20", "relay_rooftop_8", "relay_tower_30", "relay_monopole_30", "relay_tower_40", "relay_tower_50", "relay_guyed_60"]
# cadrages : (cible du pied, largeur du pied, cible de la tête, largeur de la tête, azimut)
CLOSE = {
    "relay_tower_m12": ((0.6, -0.3, 0.9), 4.2, (0, 0, 10.6), 3.0, -62), "relay_tower_m20": ((0.3, 0, 1.2), 6.5, (0, 0, 18.2), 4.5, -62),
    "relay_rooftop_8": ((0, 0, 1.2), 5.0, (0, 0, 7.2), 5.0, -60), "relay_tower_30": ((2.0, 0, 1.6), 7.0, (0, 0, 28.0), 9.0, -62),
    "relay_monopole_30": ((1.2, 0, 1.6), 8.0, (0, 0, 26.5), 8.0, -55), "relay_tower_40": ((1.5, 1.0, 1.8), 10.0, (0, 0, 37.0), 10.0, -60),
    "relay_tower_50": ((3.0, 0, 2.0), 13.0, (0, 0, 46.5), 11.0, -58), "relay_guyed_60": ((0.8, 0.5, 1.4), 6.5, (0, 0, 57.0), 8.0, -60),
}
SOLDIER = {"relay_tower_m12": (1.0, -2.2), "relay_tower_m20": (2.6, -3.2), "relay_rooftop_8": (2.2, -2.2), "relay_tower_30": (2.5, -3.0),
           "relay_monopole_30": (2.2, -4.6), "relay_tower_40": (3.0, -6.5), "relay_tower_50": (3.5, -7.5), "relay_guyed_60": (2.0, -3.6)}
WALL = {"relay_wall_sector": 3.4, "relay_wall_omni": 2.6, "relay_wall_dish": 1.7, "relay_wall_yagi": 2.2, "relay_wall_radio": 1.5, "relay_kit_mini": 1.2}
GROUND = {"relay_kit_sat": ((0.3, 0, 0.9), 3.6, 115, (-1.4, 1.2)), "relay_kit_field": ((0.2, 0, 2.3), 5.4, -65, (1.6, 1.2)),
          "relay_roof_mast": ((0, 0, 1.7), 4.2, -65, (1.3, -0.9)), "relay_pole_node": ((0, 0.2, 3.3), 2.6, 40, None)}


def old_to_obj(p3d, png_dir, out):
    """Ancien modèle MLOD (première LOD) -> OBJ + json, pour le rendu de comparaison."""
    b = open(p3d, "rb").read()
    o = 12
    assert b[o:o + 4] == b"P3DM"
    _, _, npt, nn, nf, _ = struct.unpack_from("<IIIIII", b, o + 4)
    o += 28
    pts = [struct.unpack_from("<fff", b, o + 16 * i) for i in range(npt)]
    o += 16 * npt
    nrm = [struct.unpack_from("<fff", b, o + 12 * i) for i in range(nn)]
    o += 12 * nn
    groups = {}
    for _ in range(nf):
        n = struct.unpack_from("<I", b, o)[0]
        vs = [struct.unpack_from("<IIff", b, o + 4 + 16 * k) for k in range(n)]
        o += 4 + 64 + 4
        e = b.index(b"\0", o)
        tex = b[o:e].decode("latin1").lower()
        o = e + 1
        o = b.index(b"\0", o) + 1
        groups.setdefault(tex, []).append(vs)
    mats = {}
    with open(out, "w") as f:
        for p in pts:
            f.write("v %.5f %.5f %.5f\n" % (p[0], p[2], p[1]))
        vt = 0
        for gi, (tex, faces) in enumerate(sorted(groups.items())):
            name = "mat%d" % gi
            base = os.path.basename(tex.replace("\\", "/")).replace(".paa", ".png")
            png = os.path.join(png_dir, base)
            if tex.startswith("#"):
                try:
                    col = [float(x) for x in tex[tex.index("color(") + 6:].split(",")[:3]]
                except ValueError:
                    col = [0.5, 0.5, 0.5]
                mats[name] = {"flat": col, "rvmat": "glow" if "light" in tex else ""}
            elif os.path.exists(png):
                mats[name] = {"co": png, "alpha": base.endswith("_ca.png"), "rough": 0.5, "metal": 0.6 if ("galva" in base or "grating" in base) else 0.0}
                if "light" in base:
                    mats[name] = {"flat": [0.9, 0.05, 0.03], "rvmat": "glow"}
            else:
                mats[name] = {}
            f.write("usemtl %s\n" % name)
            for vs in faces:
                for (pi, ni, u, v) in reversed(vs):
                    n = nrm[ni]
                    f.write("vt %.5f %.5f\nvn %.4f %.4f %.4f\n" % (u, 1 - v, n[0], n[2], n[1]))
                f.write("f " + " ".join("%d/%d/%d" % (pi + 1, vt + k + 1, vt + k + 1) for k, (pi, ni, u, v) in enumerate(reversed(vs))) + "\n")
                vt += len(vs)
    with open(out + ".json", "w") as f:
        json.dump({"materials": mats, "memory": {}}, f)


def export_all(work):
    """OBJ de tous les modèles (détail 0), ruines, variantes de peinture et d'équipement du pylône de 30 m."""
    relay_tex.declare_flat()
    png = relay_tex.build_textures(os.path.join(work, "tex"), None, convert=False) if not os.path.exists(os.path.join(work, "tex", "relay_equip_co.png")) else None
    if png is None:
        towers._declare_only()
        t, T = os.path.join(work, "tex"), relay_tex.TEX
        png = {T + "relay_steel_co.paa": {k: os.path.join(t, "relay_steel_%s.png" % k) for k in ("co", "nohq", "smdi", "rm")},
               T + "relay_equip_co.paa": {k: os.path.join(t, "relay_equip_%s.png" % k) for k in ("co", "nohq", "smdi", "rm")},
               T + "relay_mesh_ca.paa": {"co": os.path.join(t, "relay_mesh_ca.png"), "alpha": True}}
    steel = relay_tex.TEX + "relay_steel_co.paa"
    for name, (fn, _) in towers.MODELS.items():
        m = towers.finish(fn(0))
        r3.export_obj(m, os.path.join(work, name + ".obj"), png)
        r3.export_obj(towers.finish(fn(1, ruin=True)), os.path.join(work, name + "_ruin.obj"), png)
        if name in ("relay_tower_30", "relay_tower_m12", "relay_guyed_60", "relay_tower_m20", "relay_tower_50"):
            for v in ("rw", "olive", "sand"):
                p2 = dict(png)
                p2[steel] = dict(png[steel], co=os.path.join(work, "tex", "relay_steel_%s_co.png" % v))
                r3.export_obj(m, os.path.join(work, "%s__%s.obj" % (name, v)), p2)
        if name in ("relay_tower_40",):
            for label, hide in (("leger", ("eq2",)), ("nu", ("eq1", "eq2"))):
                m2 = towers.finish(fn(0))
                m2.faces = [f for f in m2.faces if not any(h in f[4] for h in hide)]
                r3.export_obj(m2, os.path.join(work, "%s__%s.obj" % (name, label)), png)
    for name, (fn, _, ds) in kits.MODELS.items():
        r3.export_obj(towers.finish(fn(0)), os.path.join(work, name + ".obj"), png)
    png = dict(png)
    png.update(trees.build_textures(os.path.join(work, "tex"), None, convert=False))
    for name, (fn, _) in trees.MODELS.items():
        r3.export_obj(towers.finish(fn(0)), os.path.join(work, name + ".obj"), png)
        r3.export_obj(towers.finish(fn(1, ruin=True)), os.path.join(work, name + "_ruin.obj"), png)


def label(path, text):
    im = Image.open(path).convert("RGB")
    d = ImageDraw.Draw(im)
    f = r3.font(max(16, im.height // 34))
    w = d.textlength(text, font=f)
    d.rectangle([0, im.height - f.size - 16, w + 24, im.height], fill=(16, 17, 19))
    d.text((12, im.height - f.size - 10), text, fill=(236, 236, 230), font=f)
    im.save(path)


def sheet(out, paths, cols, h):
    ims = [Image.open(p).convert("RGB") for p in paths if os.path.exists(p)]
    ims = [im.resize((int(im.width * h / im.height), h), Image.LANCZOS) for im in ims]
    rows = [ims[i:i + cols] for i in range(0, len(ims), cols)]
    W = max(sum(im.width for im in r) + 8 * (len(r) - 1) for r in rows)
    sh = Image.new("RGB", (W, len(rows) * (h + 8) - 8), (16, 17, 19))
    y = 0
    for r in rows:
        x = 0
        for im in r:
            sh.paste(im, (x, y))
            x += im.width + 8
        y += h + 8
    sh.save(out)


def main():
    out = os.path.abspath(sys.argv[1])
    old = os.path.abspath(sys.argv[2]) if len(sys.argv) > 2 else ""
    work = os.environ.get("WORK") or tempfile.mkdtemp(prefix="relay_proofs_")
    blender = os.environ.get("BLENDER") or shutil.which("blender")
    only = os.environ.get("ONLY", "")
    os.makedirs(out, exist_ok=True)
    os.makedirs(work, exist_ok=True)
    export_all(work)
    W = work.replace("\\", "/")
    O = out.replace("\\", "/")
    scenes, labels, previews = [], {}, []

    def obj(n):
        return "%s/%s.obj" % (W, n)
    names_fr = {"relay_tower_m12": "Mât tactique 12 m", "relay_tower_m20": "Mât sur remorque 20 m", "relay_rooftop_8": "Pylône de toit 8 m",
                "relay_tower_30": "Pylône treillis 30 m", "relay_monopole_30": "Monopôle 30 m", "relay_tower_40": "Pylône triangulaire 40 m",
                "relay_tower_50": "Pylône lourd 50 m", "relay_guyed_60": "Pylône haubané 60 m"}
    for n in TOWERS:
        pied, wp, tete, wt, az = CLOSE[n]
        sol = [list(SOLDIER[n])]
        rk = "roof" if n == "relay_rooftop_8" else "grass"
        prev = "%s/prev_%s.png" % (W, n)
        previews.append((prev, n))
        shots = [{"out": prev, "az": az, "el": 5, "res": [910, 512], "mode": "day"}]
        if only != "previews":
            shots += [{"out": "%s/%s_jour.png" % (O, n), "az": az, "el": 6, "res": [900, 1400], "mode": "day"},
                      {"out": "%s/%s_crepuscule.png" % (O, n), "az": az + 25, "el": 4, "res": [900, 1400], "mode": "dusk"},
                      {"out": "%s/%s_pied.png" % (O, n), "az": az + 12, "el": 12, "target": list(pied), "span": wp, "res": [1400, 1000], "mode": "day"},
                      {"out": "%s/%s_tete.png" % (O, n), "az": az - 5, "el": -6, "target": list(tete), "span": wt, "res": [1200, 1200], "mode": "day"}]
            for k in ("jour", "crepuscule", "pied", "tete"):
                labels["%s/%s_%s.png" % (O, n, k)] = "%s : %s" % (names_fr[n], {"jour": "jour", "crepuscule": "nuit tombante", "pied": "pied et site", "tete": "tête"}[k])
        scenes.append({"objs": [{"obj": obj(n)}], "soldiers": sol, "ground_kind": rk, "shots": shots})
        if only != "previews":
            scenes.append({"objs": [{"obj": obj(n + "_ruin")}], "soldiers": sol, "ground_kind": rk,
                           "shots": [{"out": "%s/%s_ruine.png" % (O, n), "az": az + 20, "el": 16, "res": [1400, 900], "mode": "day"}]})
            labels["%s/%s_ruine.png" % (O, n)] = "%s : ruine" % names_fr[n]
    for n, span in WALL.items():
        z = 2.6
        prev = "%s/prev_%s.png" % (W, n)
        previews.append((prev, n))
        shots = [{"out": prev, "az": 55, "el": 5, "res": [910, 512], "span": span * 0.75, "target": [0, 0.2, z], "mode": "day"}]
        if only != "previews":
            shots.append({"out": "%s/%s.png" % (O, n), "az": 52, "el": 6, "res": [900, 1100], "span": span, "target": [0, 0.2, z + (0.25 if "omni" in n else -0.2)], "mode": "day"})
            labels["%s/%s.png" % (O, n)] = n.replace("relay_", "").replace("_", " ")
        scenes.append({"objs": [{"obj": obj(n), "loc": [0, 0.05 if n == "relay_kit_mini" else 0, z]}], "wall": {"y": 0, "w": 8, "h": 5.5}, "ground_kind": "floor", "shots": shots})
    for n, (tg, span, az, sol) in GROUND.items():
        prev = "%s/prev_%s.png" % (W, n)
        previews.append((prev, n))
        shots = [{"out": prev, "az": az, "el": 8, "res": [910, 512], "span": span * 0.62, "target": list(tg), "mode": "day"}]
        if only != "previews":
            shots.append({"out": "%s/%s.png" % (O, n), "az": az, "el": 10, "res": [1000, 1100], "span": span, "target": list(tg), "mode": "day"})
            labels["%s/%s.png" % (O, n)] = n.replace("relay_", "").replace("_", " ")
        loc = [0, 0, 3.0] if n == "relay_pole_node" else [0, 0, 0]
        sc = {"objs": [{"obj": obj(n), "loc": loc}], "soldiers": [list(sol)] if sol else [], "ground_kind": "roof" if "roof" in n else "grass", "shots": shots}
        if n == "relay_pole_node":
            sc["pole"] = {"r": 0.08, "h": 6.0}
        scenes.append(sc)
    # pylônes déguisés en arbre : seuls, gros plan du houppier (antennes), pied, ruine
    for n, H, out_name in (("relay_tree_pine_28", 28.0, "arbre_pin"), ("relay_tree_palm_22", 22.0, "arbre_palmier"), ("relay_tree_pine_15", 15.0, "arbre_pin_15")):
        prev = "%s/prev_%s.png" % (W, n)
        previews.append((prev, n))
        shots = [{"out": prev, "az": -60, "el": 5, "res": [910, 512], "mode": "day"}]
        if only != "previews":
            shots.append({"out": "%s/%s.png" % (O, out_name), "az": -62, "el": 5, "res": [900, 1400], "mode": "day"})
            labels["%s/%s.png" % (O, out_name)] = {"arbre_pin": "Faux pin 28 m", "arbre_palmier": "Faux palmier 22 m", "arbre_pin_15": "Faux pin 15 m"}[out_name]
            shots.append({"out": "%s/det_%s_tete.png" % (W, n), "az": -40, "el": -4, "target": [0, 0, H - (5.5 if "pine" in n else 3.0)], "span": 7.5 if H > 20 else 6.0, "res": [1000, 1000], "mode": "day"})
            shots.append({"out": "%s/det_%s_pied.png" % (W, n), "az": -50, "el": 10, "target": [1.2, 0, 1.6], "span": 8.0, "res": [1000, 1000], "mode": "day"})
        scenes.append({"objs": [{"obj": obj(n)}], "soldiers": [[2.0, -4.6]], "shots": shots})
        if only != "previews":
            scenes.append({"objs": [{"obj": obj(n + "_ruin")}], "soldiers": [[2.0, -4.6]],
                           "shots": [{"out": "%s/det_%s_ruine.png" % (W, n), "az": -30, "el": 16, "res": [1000, 1000], "mode": "day"}]})
    if only != "previews":
        # tableau des tailles : tous les pylônes côte à côte, silhouette de 1,80 m
        xs, x = [], 0.0
        gaps = {"relay_tower_m12": 9, "relay_tower_m20": 12, "relay_rooftop_8": 9, "relay_tower_30": 12, "relay_monopole_30": 13, "relay_tower_40": 17, "relay_tower_50": 20, "relay_guyed_60": 12}
        gaps.update({"relay_tree_pine_15": 11, "relay_tree_palm_22": 13, "relay_tree_pine_28": 14})
        for n in TOWERS[:4] + ["relay_tree_pine_15", "relay_tree_palm_22", "relay_tree_pine_28"] + TOWERS[4:]:
            xs.append({"obj": obj(n), "loc": [x, 0, 0], "rot": 0})
            x += gaps[n]
        scenes.append({"objs": xs, "soldiers": [[-4.0, -2.0]] + [[o["loc"][0] + 2.5, -5.0] for o in xs],
                       "shots": [{"out": "%s/_tailles.png" % O, "az": -90, "el": 3, "res": [3000, 1300], "fov": 18, "target": [x / 2 - 6, 0, 31], "span": 68, "mode": "day"}]})
        # variantes de peinture et d'équipement
        scenes.append({"objs": [{"obj": obj("relay_tower_30" + s), "loc": [i * 9.0, 0, 0]} for i, s in enumerate(("", "__rw", "__olive", "__sand"))], "soldiers": [[-3, -3]],
                       "shots": [{"out": "%s/variantes_peinture.png" % O, "az": -75, "el": 5, "res": [1800, 1300], "mode": "day"}]})
        scenes.append({"objs": [{"obj": obj("relay_tower_40" + s), "loc": [i * 14.0, 0, 0]} for i, s in enumerate(("__nu", "__leger", ""))], "soldiers": [[-3, -6]],
                       "shots": [{"out": "%s/variantes_equipement.png" % O, "az": -80, "el": 5, "res": [1800, 1400], "mode": "day"}]})
        scenes.append({"objs": [{"obj": obj("relay_tower_m12" + s), "loc": [i * 16.0, 0, 0]} for i, s in enumerate(("__olive", "__sand", ""))], "soldiers": [[2, -3]],
                       "shots": [{"out": "%s/variantes_mat_tactique.png" % O, "az": -80, "el": 6, "res": [1800, 1100], "mode": "day"}]})
        scenes.append({"objs": [{"obj": obj("relay_tower_50__rw")}], "soldiers": [[3.5, -7.5]],
                       "shots": [{"out": "%s/relay_tower_50_rouge_blanc_crepuscule.png" % O, "az": -30, "el": 4, "res": [900, 1400], "mode": "dusk"}]})
        labels["%s/variantes_peinture.png" % O] = "Peintures : galvanisé, rouge-blanc, vert OTAN, sable"
        labels["%s/variantes_equipement.png" % O] = "Équipement : nu, léger, complet"
        labels["%s/variantes_mat_tactique.png" % O] = "Mât tactique : vert OTAN, sable, galvanisé"
        labels["%s/_tailles.png" % O] = "Tableau des tailles (silhouette : 1,80 m)"
        if old:
            for o_, n in (("relay_tower_m12", "relay_tower_m12"), ("relay_tower_m20", "relay_tower_m20"), ("relay_tower_30", "relay_tower_30"), ("relay_tower_40", "relay_tower_40"), ("relay_tower_50", "relay_tower_50")):
                src = os.path.join(old, o_ + ".p3d")
                if not os.path.exists(src):
                    continue
                old_to_obj(src, old, os.path.join(work, "old_" + o_ + ".obj"))
                dx = {"relay_tower_m12": 18, "relay_tower_m20": 26, "relay_tower_30": 30, "relay_tower_40": 24, "relay_tower_50": 30}[n]
                scenes.append({"objs": [{"obj": obj("old_" + o_), "loc": [0, 0, 0]}, {"obj": obj(n + ("__olive" if "_m" in n else "")), "loc": [dx, 0, 0]}], "soldiers": [[3, -5], [dx + 3, -5]],
                               "shots": [{"out": "%s/comparaison_%s.png" % (O, n), "az": -80, "el": 5, "res": [1700, 1400], "mode": "day"}]})
                labels["%s/comparaison_%s.png" % (O, n)] = "Ancien (gauche) / nouveau (droite) : %s" % names_fr[n]
    # SHOTS=crepuscule,m20 : ne refait que les images dont le nom contient l'un de ces mots.
    pick = [w for w in os.environ.get("SHOTS", "").split(",") if w]
    if pick:
        for sc in scenes:
            sc["shots"] = [sh for sh in sc["shots"] if any(w in os.path.basename(sh["out"]) for w in pick)]
        scenes = [sc for sc in scenes if sc["shots"]]
    job = os.path.join(work, "job.json")
    with open(job, "w") as f:
        json.dump({"samples": int(os.environ.get("SAMPLES", "64")), "scenes": scenes}, f)
    subprocess.run([blender, "-b", "--python", os.path.join(HERE, "render_relay_blender.py"), "--", job], check=True, stdout=subprocess.DEVNULL)
    for p, t in labels.items():
        if os.path.exists(p):
            label(p, t)
    for p, n in previews:
        if os.path.exists(p):
            Image.open(p).convert("RGB").resize((455, 256), Image.LANCZOS).save(os.path.join(towers.DATA, n + "_preview.jpg"), quality=88)
    if only != "previews":
        order = ["%s/%s_jour.png" % (O, n) for n in TOWERS] + ["%s/%s_crepuscule.png" % (O, n) for n in TOWERS] + ["%s/%s_ruine.png" % (O, n) for n in TOWERS]
        sheet(os.path.join(out, "_planche.png"), order[:16], 8, 620)
        sheet(os.path.join(out, "_planche_details.png"), ["%s/%s_pied.png" % (O, n) for n in TOWERS] + ["%s/%s_tete.png" % (O, n) for n in TOWERS] + order[16:], 4, 520)
        det = ["%s/det_%s_%s.png" % (W, n, k) for n in ("relay_tree_pine_28", "relay_tree_palm_22") for k in ("tete", "pied", "ruine")]
        if all(os.path.exists(f) for f in det):
            sheet(os.path.join(out, "arbre_details.png"), det, 3, 760)
        sheet(os.path.join(out, "_planche_antennes.png"), ["%s/%s.png" % (O, n) for n in list(GROUND) + list(WALL)], 5, 620)
    print("rendus :", out)


if __name__ == "__main__":
    main()
