#include "script_component.hpp"

/*
    COMSPEC SSE : lunettes de sport (Oakley Radar EV Path, D.Franklin Hurricane et Wind), cinq coloris.

    Arma 3 n'a pas de slot d'inventaire personnalisable : chaque coloris existe de trois façons, pour ne remplacer
    ni les JVN ni les cagoules quand on n'en a pas envie :
      a) slot lunettes / visage  : CfgGlasses <classe>
      b) slot JVN                : CfgWeapons <classe>_NVG (hérite de NVGoggles, aucune vision nocturne)
      c) slot virtuel COMSPEC    : CfgWeapons <classe>_Item, porté par l'action « Lunettes » (ACE, sinon action
                                   du menu molette) : un objet 3D local est accroché à la tête (EXPÉRIMENTAL, fn_eyewearRender)
    Option (réglages CBA › COMSPEC SSE › Lunettes) : teinte de l'écran selon le verre porté (fn_eyewearTint).

    Modèles MLOD (P3D non binarisés, comme les objets de l'ATAK natif) générés par tools/eyewear/gen_p3d.py,
    textures et icônes par tools/eyewear/gen_textures.py, CfgEyewear.hpp et stringtable.xml par tools/eyewear/gen_config.py.
*/
class CfgPatches {
    class comspec_sse_eyewear {
        name = "COMSPEC SSE - Eyewear";
        units[] = {"COMSPEC_SSE_Box_Eyewear", "COMSPEC_SSE_EyewearHead_radar_white", "COMSPEC_SSE_EyewearHead_radar_black", "COMSPEC_SSE_EyewearHead_hurricane_black", "COMSPEC_SSE_EyewearHead_wind_rainbow", "COMSPEC_SSE_EyewearHead_wind_orange"};
        weapons[] = {"COMSPEC_SSE_G_Oakley_RadarEV_White_NVG", "COMSPEC_SSE_G_Oakley_RadarEV_White_Item", "COMSPEC_SSE_G_Oakley_RadarEV_Black_NVG", "COMSPEC_SSE_G_Oakley_RadarEV_Black_Item", "COMSPEC_SSE_G_DFranklin_Hurricane_Black_NVG", "COMSPEC_SSE_G_DFranklin_Hurricane_Black_Item", "COMSPEC_SSE_G_DFranklin_Wind_Rainbow_NVG", "COMSPEC_SSE_G_DFranklin_Wind_Rainbow_Item", "COMSPEC_SSE_G_DFranklin_Wind_Orange_NVG", "COMSPEC_SSE_G_DFranklin_Wind_Orange_Item"};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {
            "comspec_sse_main",
            "comspec_sse_core",
            "comspec_sse_evidence",
            "A3_Weapons_F",
            "A3_Characters_F",
            "A3_Structures_F",
            "cba_main",
            "cba_common",
            "cba_xeh",
            "cba_settings"
        };
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgFunctions {
    class comspec_sse {
        tag = "comspec_sse";
        class eyewear {
            file = "z\comspec_sse\addons\eyewear\functions";
            class eyewearInitSettings {};
            class eyewearCatalog {};
            class eyewearOf {};
            class eyewearWear {};
            class eyewearRemove {};
            class eyewearRender {};
            class eyewearFollow {};
            class eyewearTint {};
            class eyewearActions {};
        };
    };
};

class Extended_PreInit_EventHandlers {
    class comspec_sse_eyewear {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\eyewear\XEH_preInit.sqf'";
    };
};

class Extended_PostInit_EventHandlers {
    class comspec_sse_eyewear {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\eyewear\XEH_postInit.sqf'";
        clientInit = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\eyewear\XEH_postInitClient.sqf'";
    };
};

#include "CfgEyewear.hpp"
