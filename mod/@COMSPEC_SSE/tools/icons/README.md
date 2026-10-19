# Icônes d'inventaire SSE

Rendus 3D (Blender / Cycles) des 17 items SSE, utilisés comme `picture` dans `addons/evidence/config.cpp`
(Arsenal ACE, inventaire, éditeur). Fond transparent, 256 x 256, PAA `_ca`.

```
pip install bpy pillow
python3 icon_textures.py                 # écrans SEEK II / terminal, étiquettes ADN, SD, fiche d'empreintes
python3 render_icons.py <hemtt> [nom ...]  # RENDER_SAMPLES=n, défaut 128
python3 ../../../COMSPEC_ATAK_Native/tools/pack_pbo.py ../../addons/evidence ../../addons/comspec_sse_evidence.pbo
```

- `render_items.py`, `gen_textures.py` et `tex/` viennent des previews d'items SSE (modèles repris pour les
  téléphones, supports, disque dur, portable, carnet, documents, sachet de preuve).
- `png/<nom>.png` (512 px) et `png/<nom>_ca.png` (256 px) servent aussi au site.
- HEMTT >= 1.15 pour écrire du PAA (`hemtt utils paa convert`).
