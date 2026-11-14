"""Rafraîchit database/data/atlas_maps.json : le catalogue des cartes d'Arma 3 de https://atlas.plan-ops.fr (289 cartes au
14/11/2026) avec leur pyramide de tuiles et leur repère (CRS). Deux fichiers publics du site :
  /data/1/maps/all.json   une fiche par carte (nom, taille, tuiles, zooms, DLC, atelier)
  /data/1/maps/all.js     le repère de chaque carte : MGRS_CRS(facteur x, facteur y, largeur de tuile)
Le résultat est versionné : la migration n'a pas besoin du réseau. Les villes et les vignettes ne sont pas gardées.
Usage : python tools/atlas_refresh_maps.py   puis relancer les migrations (étape atak_maps_atlas)."""
import json
import os
import re
import urllib.request

BASE = "https://atlas.plan-ops.fr/data/1/maps/"
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "database", "data", "atlas_maps.json")


def get(name):
    req = urllib.request.Request(BASE + name, headers={"User-Agent": "Athena-catalogue/1.0"})
    with urllib.request.urlopen(req, timeout=60) as r:
        return r.read().decode("utf-8-sig")


cat = json.loads(get("all.json"))
crs = {k: [float(x) for x in v.replace(" ", "").split(",")]
       for k, v in re.findall(r"Arma3Map\.Maps\['([^']+)'\] = \{\s*CRS: MGRS_CRS\(([^)]*)\)", get("all.js"))}
rows = []
for key, m in cat.items():
    c = crs.get(key)
    mm = re.match(r"^/maps/(\d+)/\d+/\{z\}/\{x\}/\{y\}\.(png|webp|jpg)$", str(m.get("tilePattern", "")))
    if not c or len(c) != 3 or not mm or not m.get("worldName"):
        print("ignorée :", key)
        continue
    rows.append({
        "slug": key, "world": str(m["worldName"]).lower(), "title": m.get("title") or key,
        "atlas_id": int(mm.group(1)), "tile_pattern": m["tilePattern"],
        "min_zoom": int(m.get("minZoom", 0)), "max_zoom": int(m["maxZoom"]), "default_zoom": int(m.get("defaultZoom", 2)),
        "tile_size": int(m["tileSize"]), "world_size": int(m["worldSize"]), "center": [m["center"][0], m["center"][1]],
        "crs": c, "attribution": m.get("attribution") or "", "dlc": m.get("dlc"), "workshop": m.get("steamWorkshop"),
    })
rows.sort(key=lambda r: r["title"].lower())
os.makedirs(os.path.dirname(OUT), exist_ok=True)
with open(OUT, "w", encoding="utf-8", newline="\n") as f:
    json.dump({"source": "https://atlas.plan-ops.fr/maps/arma3", "tile_base": "https://atlas.plan-ops.fr/data/1", "maps": rows}, f, ensure_ascii=False, separators=(",", ":"))
print(len(rows), "cartes écrites :", os.path.getsize(OUT) // 1024, "Ko")
