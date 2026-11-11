"""Travaux de rendu finaux de l'infrastructure réseau : final.py S OUT phase (jobs | sheet DATA)."""
import json
import os
import sys

S, OUT, phase = sys.argv[1], sys.argv[2], sys.argv[3]
SINGLES = [("infra_rack_patch", 2.45, 1.02, -112, 8), ("infra_rack_server", 2.45, 1.02, -68, 8), ("infra_crac", 2.5, 1.05, -118, 14),
           ("infra_elec_cabinet", 2.5, 1.05, -118, 14), ("infra_server_tower", 0.75, 0.22, -118, 14), ("infra_ups", 0.6, 0.16, -118, 14),
           ("infra_switch_desk", 0.6, 0.05, -118, 22), ("infra_tray_straight", 2.4, 0.0, -118, 30), ("infra_tray_corner", 1.3, 0.0, -118, 35),
           ("infra_tray_tee", 1.3, 0.0, -118, 35)]
WALLS = [("infra_wall_9u", 1.2, 62), ("infra_wall_12u", 1.3, 115), ("infra_trunk_straight", 2.3, 75), ("infra_trunk_corner", 1.2, 75)]


def o(n, loc=(0, 0, 0), rot=0):
    return {"obj": S + "/obj/" + n + ".obj", "loc": list(loc), "rot": rot}


if phase == "jobs":
    sc = []
    for n, span, tz, az, el in SINGLES:
        sc.append({"objs": [o(n)], "ground_kind": "floor",
                   "shots": [{"out": OUT + "/" + n + ".png", "mode": "studio", "az": az, "el": el, "res": [900, 900], "target": [0, 0, tz], "span": span, "fov": 28}]})
    W = {"y": 0.0, "w": 4, "h": 3.0, "kind": "plaster"}
    for n, span, az in WALLS:
        sc.append({"objs": [o(n, (0, 0, 1.6))], "ground_kind": "floor", "wall": W,
                   "shots": [{"out": OUT + "/" + n + ".png", "mode": "studio", "az": az, "el": 6, "res": [900, 900], "target": [0, 0, 1.6], "span": span, "fov": 28}]})
    sc.append({"objs": [o("infra_rack_patch", (-0.45, 0, 0)), o("infra_rack_server", (0.45, 0, 0))], "ground_kind": "floor",
               "wall": {"y": 0.75, "w": 7, "h": 3.2, "kind": "concrete"},
               "shots": [{"out": OUT + "/gros_plan_brassage.png", "mode": "studio", "az": -100, "el": 4, "res": [1200, 900], "target": [-0.45, -0.4, 1.25], "span": 0.62, "fov": 28},
                         {"out": OUT + "/gros_plan_serveurs.png", "mode": "studio", "az": -78, "el": 4, "res": [1200, 900], "target": [0.45, -0.4, 0.85], "span": 0.7, "fov": 28},
                         {"out": OUT + "/gros_plan_tete_de_baie.png", "mode": "studio", "az": -84, "el": 2, "res": [1200, 900], "target": [0.45, -0.4, 1.72], "span": 0.5, "fov": 28}]})
    json.dump({"samples": 64, "scenes": sc}, open(S + "/final_a.json", "w"))
    room = [o("infra_rack_patch", (-0.6, 1.0, 0), 180), o("infra_rack_server", (0.0, 1.0, 0), 180), o("infra_rack_server", (0.6, 1.0, 0), 180),
            o("infra_crac", (1.75, 0.5, 0), 180), o("infra_elec_cabinet", (-1.75, 0.17, 0), 180), o("infra_wall_9u", (3.0, 0.0, 1.55)),
            o("infra_tray_straight", (-0.9, 1.0, 2.0)), o("infra_tray_straight", (1.1, 1.0, 2.0)), o("infra_tray_tee", (2.6, 1.0, 2.0), 180),
            o("infra_trunk_straight", (-3.0, 0.0, 2.45)), o("infra_trunk_corner", (-1.5, 0.0, 2.45)),
            o("infra_ups", (-2.6, 0.35, 0), 180), o("infra_server_tower", (-3.1, 0.4, 0), 180), o("infra_switch_desk", (-3.1, 0.42, 0.455), 180)]
    sc = [{"objs": room, "ground_kind": "floor", "wall": {"y": 0.0, "w": 9, "h": 3.0, "kind": "plaster"}, "soldiers": [[2.55, 1.35]],
           "shots": [{"out": OUT + "/salle_serveur.png", "mode": "studio", "az": 68, "el": 17, "res": [1600, 900], "target": [0.0, 0.6, 1.1], "span": 3.5, "fov": 34},
                     {"out": OUT + "/salle_serveur_sombre.png", "mode": "dusk", "az": 105, "el": 6, "res": [1600, 900], "target": [0.1, 0.6, 1.1], "span": 2.9, "fov": 34}]}]
    json.dump({"samples": 96, "scenes": sc}, open(S + "/final_b.json", "w"))
else:
    from PIL import Image, ImageDraw, ImageFont
    DATA = sys.argv[4]
    names = [n for n, *_ in SINGLES] + [n for n, *_ in WALLS]
    # aperçus Eden 455 x 256
    for n in names:
        im = Image.open(OUT + "/" + n + ".png").convert("RGB")
        bg = im.resize((455, 455), Image.LANCZOS).crop((0, 100, 455, 356))
        im.thumbnail((256, 256), Image.LANCZOS)
        from PIL import ImageFilter
        bg = bg.filter(ImageFilter.GaussianBlur(14))
        bg.paste(im, ((455 - im.width) // 2, 0))
        bg.save(os.path.join(DATA, n + "_preview.jpg"), quality=88)
    # planche
    try:
        f = ImageFont.truetype("arial.ttf", 22)
    except OSError:
        f = ImageFont.load_default()
    cell = 420
    big = [Image.open(OUT + "/" + n).convert("RGB") for n in ("salle_serveur.png", "salle_serveur_sombre.png")]
    big = [b.resize((cell * 7 // 2 - 3, int((cell * 7 // 2 - 3) * b.height / b.width)), Image.LANCZOS) for b in big]
    close = [Image.open(OUT + "/" + n).convert("RGB") for n in ("gros_plan_brassage.png", "gros_plan_serveurs.png", "gros_plan_tete_de_baie.png")]
    cw = (cell * 7 - 12) // 3
    close = [c.resize((cw, int(cw * c.height / c.width)), Image.LANCZOS) for c in close]
    H = big[0].height + 6 + close[0].height + 6 + 2 * (cell + 34)
    sheet = Image.new("RGB", (cell * 7, H), (24, 24, 26))
    sheet.paste(big[0], (0, 0))
    sheet.paste(big[1], (cell * 7 // 2 + 3, 0))
    y = big[0].height + 6
    for k, c in enumerate(close):
        sheet.paste(c, (k * (cw + 6), y))
    y += close[0].height + 6
    d = ImageDraw.Draw(sheet)
    labels = {"infra_rack_patch": "Baie de brassage 42U", "infra_rack_server": "Baie serveur 42U", "infra_crac": "Climatiseur de salle",
              "infra_elec_cabinet": "Armoire électrique", "infra_server_tower": "Serveur tour", "infra_ups": "Onduleur", "infra_switch_desk": "Switch de table",
              "infra_tray_straight": "Chemin de câbles 2 m", "infra_tray_corner": "Chemin de câbles, angle", "infra_tray_tee": "Chemin de câbles, té",
              "infra_wall_9u": "Coffret mural 9U", "infra_wall_12u": "Coffret mural 12U", "infra_trunk_straight": "Goulotte 2 m", "infra_trunk_corner": "Goulotte, angle"}
    for i, n in enumerate(names):
        im = Image.open(OUT + "/" + n + ".png").convert("RGB").resize((cell - 6, cell - 6), Image.LANCZOS)
        x, yy = (i % 7) * cell, y + (i // 7) * (cell + 34)
        sheet.paste(im, (x, yy))
        d.text((x + 8, yy + cell - 2), labels[n], fill=(230, 230, 226), font=f)
    sheet.save(OUT + "/_planche.png")
    print("planche", sheet.size)
