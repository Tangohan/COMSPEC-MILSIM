"""
Config et stringtable de l'addon eyewear (lunettes de sport), d'après eyewear_spec.COLOURS.

    PYTHONUTF8=1 python -I gen_config.py     # écrit addons/eyewear/CfgEyewear.hpp et addons/eyewear/stringtable.xml

Trois façons de porter chaque coloris :
  a) <cls>        CfgGlasses : slot lunettes / visage (remplace une cagoule ou un foulard, comme toute lunette Arma)
  b) <cls>_NVG    CfgWeapons hérité de NVGoggles : slot JVN, aucune vision nocturne (visionMode « Normal » seul)
  c) <cls>_Item   CfgWeapons (objet d'inventaire) : slot virtuel COMSPEC, porté par l'action « Porter les lunettes »
                  (objet 3D COMSPEC_SSE_EyewearHead_<id> accroché à la tête, voir fn_eyewearRender)
"""
import os
import sys
from xml.sax.saxutils import escape

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
import eyewear_spec as S  # noqa: E402

ADDON = os.path.normpath(os.path.join(HERE, "..", "..", "addons", "eyewear"))
D = "\\z\\comspec_sse\\addons\\eyewear\\data\\"


def key(cid, what):
    return "STR_COMSPEC_SSE_EYEWEAR_%s_%s" % (cid.upper(), what)


def strings():
    out = []
    for cid in S.ORDER:
        c = S.COLOURS[cid]
        out += [
            (key(cid, "NAME"), c["en"], c["fr"]),
            (key(cid, "NAME_NVG"), c["en"] + " - NVG slot", c["fr"] + " - slot JVN"),
            (key(cid, "NAME_ITEM"), c["en"] + " - eyewear slot", c["fr"] + " - slot Lunettes"),
            (key(cid, "DESC"), c["desc_en"], c["desc_fr"]),
            (key(cid, "DESC_NVG"), c["desc_en"] + " Worn in the night vision slot: no night vision, keeps the face slot free.",
             c["desc_fr"] + " Portées dans le slot des JVN : aucune vision nocturne, le slot du visage reste libre."),
            (key(cid, "DESC_ITEM"), c["desc_en"] + " Keep it in your inventory and use the self-interaction « Glasses » to put it on: "
             "face and NVG slots stay free (experimental).",
             c["desc_fr"] + " À garder dans l'inventaire : action personnelle « Lunettes » pour les porter ; les slots du visage et des "
             "JVN restent libres (expérimental)."),
        ]
    out += [
        ("STR_COMSPEC_SSE_EYEWEAR_BOX", "Sport glasses crate (COMSPEC)", "Caisse de lunettes de sport (COMSPEC)"),
        ("STR_COMSPEC_SSE_EYEWEAR_HEAD", "Sport glasses (worn)", "Lunettes de sport (portées)"),
    ]
    return out


def stringtable():
    lines = ['<?xml version="1.0" encoding="utf-8"?>',
             "<!-- Généré par tools/eyewear/gen_config.py : ne pas modifier à la main. -->",
             '<Project name="COMSPEC_SSE">', '    <Package name="eyewear">']
    for k, en, fr in strings():
        lines += ['        <Key ID="%s">' % k, "            <Original>%s</Original>" % escape(en),
                  "            <English>%s</English>" % escape(en), "            <French>%s</French>" % escape(fr), "        </Key>"]
    lines += ["    </Package>", "</Project>"]
    return "\n".join(lines) + "\n"


def cfg():
    L = ["// Généré par tools/eyewear/gen_config.py (d'après eyewear_spec.py) : ne pas modifier à la main.",
         "// Inclus à la racine de config.cpp.", ""]
    # Catalogue lu par les fonctions (comspec_sse_fnc_eyewearCatalog).
    L.append("class COMSPEC_SSE_Eyewear {")
    for cid in S.ORDER:
        c = S.COLOURS[cid]
        b, ct, (r, g, bb, a), sat = c["tint"]
        L += ["    class %s {" % cid,
              '        displayName = "$%s";' % key(cid, "NAME"),
              '        glasses = "%s";' % c["cls"],
              '        nvg = "%s_NVG";' % c["cls"],
              '        item = "%s_Item";' % c["cls"],
              '        head = "COMSPEC_SSE_EyewearHead_%s";' % cid,
              '        picture = "%sicon_%s_ca.paa";' % (D, cid),
              "        // ColorCorrections du verre : luminosité, contraste, mélange [r, g, b, part], saturation.",
              "        tint[] = {%g, %g, {%g, %g, %g, %g}, %g};" % (b, ct, r, g, bb, a, sat),
              "    };"]
    L += ["};", ""]
    # a) CfgGlasses
    L += ["class CfgGlasses {", "    class None;",
          "    class COMSPEC_SSE_G_Eyewear_Base: None {",
          '        author = "COMSPEC";', "        scope = 0;", "        mass = 1;",
          "        identityTypes[] = {};  // jamais posées au hasard sur une IA",
          "    };"]
    for cid in S.ORDER:
        c = S.COLOURS[cid]
        L += ["    class %s: COMSPEC_SSE_G_Eyewear_Base {" % c["cls"],
              "        scope = 2;", "        scopeArsenal = 2;",
              '        displayName = "$%s";' % key(cid, "NAME"),
              '        descriptionShort = "$%s";' % key(cid, "DESC"),
              '        picture = "%sicon_%s_ca.paa";' % (D, cid),
              '        model = "%smodels\\ew_%s.p3d";' % (D, cid),
              '        comspec_sse_eyewear = "%s";' % cid,
              "    };"]
    # Calibration TEMPORAIRE (autotest seulement, scope 1) : Radar blanche décalée, voir gen_p3d.CALIBRATION.
    L.append("    // CALIBRATION TEMPORAIRE : variantes de position pour l'autotest (scope 1), à retirer une fois la position choisie.")
    for tag, label, folder in (("p000", "demi-tour à 0", "models"), ("p070", "demi-tour à 0,07", "models"),
                               ("p120", "demi-tour à 0,12", "models"), ("mlod", "non tourné, MLOD", "mlod")):
        L += ["    class COMSPEC_SSE_G_Cal_%s: COMSPEC_SSE_G_Eyewear_Base {" % tag.upper(),
              "        scope = 1;",
              '        displayName = "Calibration lunettes %s (test)";' % label,
              '        picture = "%sicon_radar_white_ca.paa";' % D,
              '        model = "%s%s\\ew_cal_%s.p3d";' % (D, folder, tag),
              "    };"]
    L += ["};", ""]
    # b) et c) CfgWeapons
    L += ["class CfgWeapons {", "    class NVGoggles;", "    class CBA_MiscItem;", "    class CBA_MiscItem_ItemInfo;",
          "    // b) Slot JVN : même modèle (repère du proxy « nvg » = repère des lunettes), aucune vision nocturne.",
          "    class COMSPEC_SSE_G_Eyewear_NVG_Base: NVGoggles {",
          '        author = "COMSPEC";', "        scope = 0;", "        scopeArsenal = 0;", "        scopeCurator = 0;",
          '        modelOptics = "\\A3\\Weapons_F\\empty.p3d";',
          '        visionMode[] = {"Normal"};', "        thermalMode[] = {};",
          "    };",
          "    // c) Slot virtuel COMSPEC : objet d'inventaire, porté par l'action « Porter les lunettes ».",
          "    class COMSPEC_SSE_G_Eyewear_Item_Base: CBA_MiscItem {",
          '        author = "COMSPEC";', "        scope = 0;",
          "        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 1; };",
          "    };"]
    for cid in S.ORDER:
        c = S.COLOURS[cid]
        L += ["    class %s_NVG: COMSPEC_SSE_G_Eyewear_NVG_Base {" % c["cls"],
              "        scope = 2;", "        scopeArsenal = 2;", "        scopeCurator = 2;",
              '        displayName = "$%s";' % key(cid, "NAME_NVG"),
              '        descriptionShort = "$%s";' % key(cid, "DESC_NVG"),
              '        picture = "%sicon_%s_ca.paa";' % (D, cid),
              '        model = "%smodels\\ew_%s_head.p3d";' % (D, cid),
              '        comspec_sse_eyewear = "%s";' % cid,
              "        class ItemInfo {",
              "            type = 616;", "            hmdType = 0;",
              '            uniformModel = "%smodels\\ew_%s_nvg.p3d";' % (D, cid),
              '            modelOff = "%smodels\\ew_%s_nvg.p3d";' % (D, cid),
              "            mass = 1;", "        };", "    };",
              "    class %s_Item: COMSPEC_SSE_G_Eyewear_Item_Base {" % c["cls"],
              "        scope = 2;", "        scopeArsenal = 2;", "        scopeCurator = 2;",
              '        displayName = "$%s";' % key(cid, "NAME_ITEM"),
              '        descriptionShort = "$%s";' % key(cid, "DESC_ITEM"),
              '        picture = "%sicon_%s_ca.paa";' % (D, cid),
              '        model = "%smodels\\ew_%s_head.p3d";' % (D, cid),
              '        comspec_sse_eyewear = "%s";' % cid,
              "    };"]
    L += ["};", ""]
    # CfgVehicles : objets accrochés à la tête (slot virtuel) et caisse.
    L += ["class CfgVehicles {", "    class NonStrategic;", "    class Box_NATO_Support_F;",
          "    // Objet 3D du slot virtuel : créé localement par chaque client (createVehicleLocal) et accroché à la tête du porteur.",
          "    // Suivi de la tête à chaque image (fn_eyewearFollow) : origine du modèle sur eyePos ; réglage fin ici (mètres,",
          "    // repère de la tête : x droite, y avant, z haut).",
          "    class COMSPEC_SSE_EyewearHead_Base: NonStrategic {",
          '        author = "COMSPEC";', "        scope = 1;", "        scopeCurator = 0;",
          '        displayName = "$STR_COMSPEC_SSE_EYEWEAR_HEAD";', '        destrType = "DestructNo";',
          '        model = "\\A3\\Weapons_F\\empty.p3d";',
          "        comspec_sse_eyewearAttachOffset[] = {0, 0, 0};",
          "    };"]
    for cid in S.ORDER:
        L += ["    class COMSPEC_SSE_EyewearHead_%s: COMSPEC_SSE_EyewearHead_Base {" % cid,
              '        model = "%smodels\\ew_%s_head.p3d";' % (D, cid),
              '        comspec_sse_eyewear = "%s";' % cid, "    };"]
    L += ["    class COMSPEC_SSE_Box_Eyewear: Box_NATO_Support_F {",
          '        author = "COMSPEC";', "        scope = 2;", "        scopeCurator = 2;",
          '        displayName = "$STR_COMSPEC_SSE_EYEWEAR_BOX";',
          '        editorCategory = "COMSPEC_SSE_EdKits";', '        editorSubcategory = "COMSPEC_SSE_EdBoxes";',
          "        maximumLoad = 1000;",
          "        class TransportWeapons {};", "        class TransportMagazines {};", "        class TransportBackpacks {};",
          "        class TransportItems {"]
    for cid in S.ORDER:
        cls = S.COLOURS[cid]["cls"]
        for suffix, n in (("", 4), ("_NVG", 2), ("_Item", 2)):
            L.append('            class _xx_%s%s { name = "%s%s"; count = %d; };' % (cls, suffix, cls, suffix, n))
    L += ["        };", "    };", "};", ""]
    return "\n".join(L)


def unit_lists():
    return ([S.COLOURS[c]["cls"] + s for c in S.ORDER for s in ("_NVG", "_Item")],
            ["COMSPEC_SSE_Box_Eyewear"] + ["COMSPEC_SSE_EyewearHead_" + c for c in S.ORDER])


def main():
    with open(os.path.join(ADDON, "CfgEyewear.hpp"), "w", encoding="utf-8", newline="\n") as f:
        f.write(cfg())
    with open(os.path.join(ADDON, "stringtable.xml"), "w", encoding="utf-8", newline="\n") as f:
        f.write(stringtable())
    w, u = unit_lists()
    print("écrit CfgEyewear.hpp, stringtable.xml")
    print("weapons[] =", ", ".join('"%s"' % x for x in w))
    print("units[] =", ", ".join('"%s"' % x for x in u))


if __name__ == "__main__":
    main()
