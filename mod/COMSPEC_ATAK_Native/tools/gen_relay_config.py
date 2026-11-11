"""
Classes et noms des relais 3D (pylônes, mâts, antennes murales, kits) : écrit, à partir des tableaux ci-dessous,
  Sources/addons/towers/cfg_relays.hpp     classes CfgVehicles (incluses par towers/config.cpp)
  Sources/addons/towers/cfg_units.hpp      liste des classes pour units[] (CfgPatches)
  Sources/addons/towers/stringtable.xml    noms français / anglais (clés STR_COMSPEC_ATAK_REL_*, CAM_*, INFRA_*)
Les noms des caméras et des baies (EXTRA_STRINGS) sont tenus ici aussi : un seul stringtable pour l'addon.

    python gen_relay_config.py
"""
import os
from xml.sax.saxutils import escape

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "..", "Sources", "addons", "towers")
P = "\\z\\comspec_atak_native\\addons\\towers\\data\\"
PAINT = {"tree": P + "relay_steel_co.paa", "galva": P + "relay_steel_co.paa", "rw": P + "relay_steel_rw_co.paa", "olive": P + "relay_steel_olive_co.paa", "sand": P + "relay_steel_sand_co.paa"}
LED_OFF = "#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"

# classe, modèle, français, anglais, portée m, hauteur m, Mbit/s, places, blindage, taille carte, feux de mi-hauteur, peinture
TOWERS = [
    ("COMSPEC_ATAK_RelayTower_M12", "relay_tower_m12", "Mât tactique relais 12 m", "Tactical relay mast 12 m", 3000, 11, 10, 32, 300, 2, False, "olive"),
    ("COMSPEC_ATAK_RelayTower_M20", "relay_tower_m20", "Mât relais sur remorque 20 m", "Trailer relay mast 20 m", 4500, 18, 15, 48, 400, 3, False, "olive"),
    ("COMSPEC_ATAK_RelayTower_30", "relay_tower_30", "Pylône relais treillis 30 m", "Lattice relay tower 30 m", 6000, 28, 20, 64, 500, 4, False, "galva"),
    ("COMSPEC_ATAK_RelayTower_40", "relay_tower_40", "Pylône relais triangulaire 40 m", "Triangular relay tower 40 m", 9000, 38, 30, 96, 700, 5, True, "galva"),
    ("COMSPEC_ATAK_RelayTower_50", "relay_tower_50", "Pylône relais lourd 50 m", "Heavy relay tower 50 m", 12000, 48, 40, 128, 900, 6, True, "galva"),
    ("COMSPEC_ATAK_RelayTower_Mono30", "relay_monopole_30", "Monopôle relais 30 m", "Relay monopole 30 m", 7000, 28, 25, 80, 600, 4, True, "galva"),
    ("COMSPEC_ATAK_RelayTower_Guyed60", "relay_guyed_60", "Pylône relais haubané 60 m", "Guyed relay mast 60 m", 15000, 58, 40, 128, 500, 8, True, "galva"),
    ("COMSPEC_ATAK_RelayTower_Pine28", "relay_tree_pine_28", "Relais faux pin 28 m", "Pine tree relay 28 m", 7000, 22, 25, 80, 600, 4, False, "tree"),
    ("COMSPEC_ATAK_RelayTower_Pine15", "relay_tree_pine_15", "Relais faux pin 15 m", "Pine tree relay 15 m", 4000, 11, 15, 48, 450, 3, False, "tree"),
    ("COMSPEC_ATAK_RelayTower_Palm22", "relay_tree_palm_22", "Relais faux palmier 22 m", "Palm tree relay 22 m", 6000, 18, 20, 64, 550, 4, False, "tree"),
    ("COMSPEC_ATAK_RelayTower_Roof8", "relay_rooftop_8", "Pylône relais de toit 8 m", "Rooftop relay tower 8 m", 3500, 8, 15, 48, 250, 2, False, "galva"),
]
PAINT_NAMES = {"galva": ("galvanisé", "galvanised"), "rw": ("rouge-blanc", "red-white"), "olive": ("vert OTAN", "NATO green"), "sand": ("sable", "sand")}
PAINT_SUFFIX = {"galva": "Galva", "rw": "RW", "olive": "Olive", "sand": "Sand"}
EQUIP = {"Light": (("équipement léger", "light equipment"), 0, 1), "Bare": (("nu", "bare"), 1, 1)}
EQUIP_FOR = ("COMSPEC_ATAK_RelayTower_30", "COMSPEC_ATAK_RelayTower_40", "COMSPEC_ATAK_RelayTower_50", "COMSPEC_ATAK_RelayTower_Mono30", "COMSPEC_ATAK_RelayTower_Guyed60")
# classe, modèle, français, anglais, portée, hauteur, Mbit/s, places
ANTENNAS = [
    ("COMSPEC_ATAK_RelayAntenna_WallSector", "relay_wall_sector", "Antenne murale de secteur", "Wall sector antenna", 2500, 3, 15, 32),
    ("COMSPEC_ATAK_RelayAntenna_WallOmni", "relay_wall_omni", "Antenne murale fouet", "Wall whip antenna", 1500, 2, 10, 24),
    ("COMSPEC_ATAK_RelayAntenna_WallDish", "relay_wall_dish", "Parabole murale", "Wall microwave dish", 5000, 2, 30, 16),
    ("COMSPEC_ATAK_RelayAntenna_WallYagi", "relay_wall_yagi", "Antenne murale Yagi", "Wall Yagi antenna", 3500, 2, 8, 16),
    ("COMSPEC_ATAK_RelayAntenna_WallRadio", "relay_wall_radio", "Boîtier radio mural", "Wall radio unit", 800, 1, 8, 16),
    ("COMSPEC_ATAK_RelayAntenna_Pole", "relay_pole_node", "Nœud radio sur poteau", "Pole-mounted radio node", 1200, 2, 8, 24),
    ("COMSPEC_ATAK_RelayAntenna_RoofMast", "relay_roof_mast", "Mât de toit lesté", "Ballasted roof mast", 2000, 3, 12, 32),
]
# Autres noms de l'addon (caméras IP, infrastructure réseau, catégories, attributs) : clé, français, anglais
EXTRA_STRINGS = [
    ("STR_COMSPEC_ATAK_REL_SUB_VARIANTS", "Relais ATAK (variantes)", "ATAK relays (variants)"),
    ("STR_COMSPEC_ATAK_REL_SUB_ANTENNAS", "Antennes murales et de toit", "Wall and roof antennas"),
    ("STR_COMSPEC_ATAK_REL_SUB_CAMERAS", "Caméras IP", "IP cameras"),
    ("STR_COMSPEC_ATAK_REL_SUB_INFRA", "Infrastructure réseau", "Network infrastructure"),
    ("STR_COMSPEC_ATAK_REL_PAINT", "Peinture", "Paint"),
    ("STR_COMSPEC_ATAK_REL_PAINT_TIP", "Couleur de la structure. Par défaut : celle de la classe.", "Structure colour. Default: the colour of the class."),
    ("STR_COMSPEC_ATAK_REL_PAINT_DEF", "Par défaut", "Default"),
    ("STR_COMSPEC_ATAK_REL_PAINT_GALVA", "Galvanisé", "Galvanised"),
    ("STR_COMSPEC_ATAK_REL_PAINT_RW", "Rouge-blanc (balisage)", "Red-white (aviation)"),
    ("STR_COMSPEC_ATAK_REL_PAINT_OLIVE", "Vert OTAN", "NATO green"),
    ("STR_COMSPEC_ATAK_REL_PAINT_SAND", "Sable", "Sand"),
    ("STR_COMSPEC_ATAK_REL_EQUIP", "Équipement", "Equipment"),
    ("STR_COMSPEC_ATAK_REL_EQUIP_TIP", "Antennes montées sur la structure. N'agit que sur l'aspect, pas sur la portée.", "Antennas fitted to the structure. Changes the look only, not the range."),
    ("STR_COMSPEC_ATAK_REL_EQUIP_FULL", "Complet", "Full"),
    ("STR_COMSPEC_ATAK_REL_EQUIP_LIGHT", "Léger", "Light"),
    ("STR_COMSPEC_ATAK_REL_EQUIP_BARE", "Nu", "Bare"),
    ("STR_COMSPEC_ATAK_REL_KIT_SAT", "Terminal satellite de campagne", "Field satellite terminal"),
    ("STR_COMSPEC_ATAK_REL_KIT_MINI", "Mini-antenne de bâtiment", "Building mini antenna"),
    ("STR_COMSPEC_ATAK_REL_KIT_FIELD", "Relais de terrain portable", "Portable field relay"),
]
EXTRA_FILE = os.path.join(HERE, "relay_strings_extra.tsv")   # clé<TAB>français<TAB>anglais (caméras, infrastructure)


def key(cls, suffix=""):
    return "STR_COMSPEC_ATAK_REL_" + cls.replace("COMSPEC_ATAK_Relay", "").replace("_", "").upper() + suffix


def main():
    strings = list(EXTRA_STRINGS)
    if os.path.exists(EXTRA_FILE):
        with open(EXTRA_FILE, encoding="utf-8") as f:
            for line in f:
                parts = line.rstrip("\n").split("\t")
                if len(parts) == 3 and parts[0].startswith("STR_"):
                    strings.append(tuple(parts))
    units, L = [], []
    A = L.append
    A("// Généré par tools/gen_relay_config.py : ne pas modifier à la main.")
    for cls, model, fr, en, rng, h, mb, slots, armor, msize, mid, paint in TOWERS:
        strings.append((key(cls), fr, en))
        strings.append((key(cls, "_RUIN"), fr + " (ruine)", en + " (ruin)"))
        units.append(cls)
        A("class %s: COMSPEC_ATAK_RelayTower_Base {" % cls)
        A("    scope=2; scopeCurator=2;")
        if mid:
            A("    class MarkerLights: MarkerLights {")
            A("        class Mid1: Top { name=\"light_mid1\"; blinking=0; intensity=40; drawLightSize=0.25; };")
            A("        class Mid2: Mid1 { name=\"light_mid2\"; };")
            A("    };")
        A("    displayName=\"$%s\";" % key(cls))
        A("    model=\"%s%s.p3d\";" % (P, model))
        A("    editorPreview=\"%s%s_preview.jpg\";" % (P, model))
        A("    comspec_atak_relayRange=%d; comspec_atak_relayHeight=%d; comspec_atak_relayThroughput=%d; comspec_atak_relaySlots=%d;" % (rng, h, mb, slots))
        A("    armor=%d; mapSize=%d;" % (armor, msize))
        A("    hiddenSelectionsTextures[]={\"%s\",\"%s\"};" % (PAINT[paint], LED_OFF))
        A("    class DestructionEffects: DestructionEffects {")
        A("        class Ruin1 { simulation=\"ruin\"; type=\"%s%s_ruin.p3d\"; position=\"\"; intensity=1; interval=1; lifeTime=1; };" % (P, model))
        A("    };")
        A("};")
        A("class %s_ruins: Ruins_F { scope=1; scopeCurator=0; author=\"COMSPEC\"; displayName=\"$%s\"; model=\"%s%s_ruin.p3d\"; };" % (cls, key(cls, "_RUIN"), P, model))
        for pv in ("galva", "rw", "olive", "sand"):
            if pv == paint or paint == "tree":      # un arbre n'a pas de variante de peinture
                continue
            vc = "%s_%s" % (cls, PAINT_SUFFIX[pv])
            strings.append((key(vc), "%s (%s)" % (fr, PAINT_NAMES[pv][0]), "%s (%s)" % (en, PAINT_NAMES[pv][1])))
            units.append(vc)
            A("class %s: %s { displayName=\"$%s\"; editorSubcategory=\"COMSPEC_ATAK_EdSubcat_RelayVariants\"; hiddenSelectionsTextures[]={\"%s\",\"%s\"}; };"
              % (vc, cls, key(vc), PAINT[pv], LED_OFF))
        if cls in EQUIP_FOR:
            for suf, ((efr, een), e1, e2) in EQUIP.items():
                vc = "%s_%s" % (cls, suf)
                strings.append((key(vc), "%s (%s)" % (fr, efr), "%s (%s)" % (en, een)))
                units.append(vc)
                A("class %s: %s { displayName=\"$%s\"; editorSubcategory=\"COMSPEC_ATAK_EdSubcat_RelayVariants\";" % (vc, cls, key(vc)))
                A("    class AnimationSources: AnimationSources { class eq1: eq1 { initPhase=%d; }; class eq2: eq2 { initPhase=%d; }; };" % (e1, e2))
                A("};")
    A("// Antennes murales, de poteau et de toit : relais du réseau dès la pose (même enregistrement que les pylônes).")
    A("// Origine au plan du mur (Y = 0), +Y sortant du mur ; poteau : origine sur l'axe ; mât de toit : origine au toit.")
    A("class COMSPEC_ATAK_RelayAntenna_Base: COMSPEC_ATAK_RelayTower_Base {")
    A("    editorSubcategory=\"COMSPEC_ATAK_EdSubcat_RelayAntennas\"; icon=\"iconObject_circle\"; mapSize=1; armor=40; cost=5000;")
    A("    comspec_atak_relayNoun=\"Antenne\";")
    A("    class MarkerLights {};")
    A("    class AnimationSources {};")
    A("    class DestructionEffects {};")
    A("};")
    for cls, model, fr, en, rng, h, mb, slots in ANTENNAS:
        strings.append((key(cls), fr, en))
        units.append(cls)
        A("class %s: COMSPEC_ATAK_RelayAntenna_Base {" % cls)
        A("    scope=2; scopeCurator=2; displayName=\"$%s\";" % key(cls))
        A("    model=\"%s%s.p3d\"; editorPreview=\"%s%s_preview.jpg\";" % (P, model, P, model))
        A("    comspec_atak_relayRange=%d; comspec_atak_relayHeight=%d; comspec_atak_relayThroughput=%d; comspec_atak_relaySlots=%d;" % (rng, h, mb, slots))
        A("};")
        for pv in ("olive", "sand"):
            vc = "%s_%s" % (cls, PAINT_SUFFIX[pv])
            strings.append((key(vc), "%s (%s)" % (fr, PAINT_NAMES[pv][0]), "%s (%s)" % (en, PAINT_NAMES[pv][1])))
            units.append(vc)
            A("class %s: %s { displayName=\"$%s\"; hiddenSelectionsTextures[]={\"%s\",\"%s\"}; };" % (vc, cls, key(vc), PAINT[pv], LED_OFF))
    with open(os.path.join(OUT, "cfg_relays.hpp"), "w", encoding="utf-8", newline="\n") as f:
        f.write("\n".join(L) + "\n")
    with open(os.path.join(OUT, "cfg_units.hpp"), "w", encoding="utf-8", newline="\n") as f:
        f.write("// Généré par tools/gen_relay_config.py.\n#define COMSPEC_RELAY_UNITS " + ",".join('"%s"' % u for u in units) + "\n")
    seen = set()
    with open(os.path.join(OUT, "stringtable.xml"), "w", encoding="utf-8", newline="\n") as f:
        f.write("<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<!-- Généré par tools/gen_relay_config.py : relais 3D, caméras IP, infrastructure réseau. -->\n")
        f.write("<Project name=\"COMSPEC ATAK Native\">\n    <Package name=\"Towers\">\n")
        for k, fr, en in strings:
            if k in seen:
                raise SystemExit("clé en double : " + k)
            seen.add(k)
            f.write("        <Key ID=\"%s\">\n            <Original>%s</Original>\n            <English>%s</English>\n            <French>%s</French>\n        </Key>\n"
                    % (k, escape(en), escape(en), escape(fr)))
        f.write("    </Package>\n</Project>\n")
    print("%d classes, %d noms" % (len(units), len(strings)))


if __name__ == "__main__":
    main()
