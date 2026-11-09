// Généré par tools/eyewear/gen_config.py (d'après eyewear_spec.py) : ne pas modifier à la main.
// Inclus à la racine de config.cpp.

class COMSPEC_SSE_Eyewear {
    class radar_white {
        displayName = "$STR_COMSPEC_SSE_EYEWEAR_RADAR_WHITE_NAME";
        glasses = "COMSPEC_SSE_G_Oakley_RadarEV_White";
        nvg = "COMSPEC_SSE_G_Oakley_RadarEV_White_NVG";
        item = "COMSPEC_SSE_G_Oakley_RadarEV_White_Item";
        head = "COMSPEC_SSE_EyewearHead_radar_white";
        picture = "\z\comspec_sse\addons\eyewear\data\icon_radar_white_ca.paa";
        // ColorCorrections du verre : luminosité, contraste, mélange [r, g, b, part], saturation.
        tint[] = {0.98, 1.1, {1, 0.45, 0.55, 0.16}, 1.3};
    };
    class radar_black {
        displayName = "$STR_COMSPEC_SSE_EYEWEAR_RADAR_BLACK_NAME";
        glasses = "COMSPEC_SSE_G_Oakley_RadarEV_Black";
        nvg = "COMSPEC_SSE_G_Oakley_RadarEV_Black_NVG";
        item = "COMSPEC_SSE_G_Oakley_RadarEV_Black_Item";
        head = "COMSPEC_SSE_EyewearHead_radar_black";
        picture = "\z\comspec_sse\addons\eyewear\data\icon_radar_black_ca.paa";
        // ColorCorrections du verre : luminosité, contraste, mélange [r, g, b, part], saturation.
        tint[] = {0.95, 1.12, {0.3, 0.85, 0.55, 0.16}, 1.1};
    };
    class hurricane_black {
        displayName = "$STR_COMSPEC_SSE_EYEWEAR_HURRICANE_BLACK_NAME";
        glasses = "COMSPEC_SSE_G_DFranklin_Hurricane_Black";
        nvg = "COMSPEC_SSE_G_DFranklin_Hurricane_Black_NVG";
        item = "COMSPEC_SSE_G_DFranklin_Hurricane_Black_Item";
        head = "COMSPEC_SSE_EyewearHead_hurricane_black";
        picture = "\z\comspec_sse\addons\eyewear\data\icon_hurricane_black_ca.paa";
        // ColorCorrections du verre : luminosité, contraste, mélange [r, g, b, part], saturation.
        tint[] = {0.72, 1.02, {0.5, 0.5, 0.5, 0}, 0.92};
    };
    class wind_rainbow {
        displayName = "$STR_COMSPEC_SSE_EYEWEAR_WIND_RAINBOW_NAME";
        glasses = "COMSPEC_SSE_G_DFranklin_Wind_Rainbow";
        nvg = "COMSPEC_SSE_G_DFranklin_Wind_Rainbow_NVG";
        item = "COMSPEC_SSE_G_DFranklin_Wind_Rainbow_Item";
        head = "COMSPEC_SSE_EyewearHead_wind_rainbow";
        picture = "\z\comspec_sse\addons\eyewear\data\icon_wind_rainbow_ca.paa";
        // ColorCorrections du verre : luminosité, contraste, mélange [r, g, b, part], saturation.
        tint[] = {0.88, 1.04, {0.6, 0.35, 1, 0.18}, 1.05};
    };
    class wind_orange {
        displayName = "$STR_COMSPEC_SSE_EYEWEAR_WIND_ORANGE_NAME";
        glasses = "COMSPEC_SSE_G_DFranklin_Wind_Orange";
        nvg = "COMSPEC_SSE_G_DFranklin_Wind_Orange_NVG";
        item = "COMSPEC_SSE_G_DFranklin_Wind_Orange_Item";
        head = "COMSPEC_SSE_EyewearHead_wind_orange";
        picture = "\z\comspec_sse\addons\eyewear\data\icon_wind_orange_ca.paa";
        // ColorCorrections du verre : luminosité, contraste, mélange [r, g, b, part], saturation.
        tint[] = {0.85, 1.06, {1, 0.55, 0.1, 0.22}, 1.12};
    };
};

class CfgGlasses {
    class None;
    class COMSPEC_SSE_G_Eyewear_Base: None {
        author = "COMSPEC";
        scope = 0;
        mass = 1;
        identityTypes[] = {};  // jamais posées au hasard sur une IA
    };
    class COMSPEC_SSE_G_Oakley_RadarEV_White: COMSPEC_SSE_G_Eyewear_Base {
        scope = 2;
        scopeArsenal = 2;
        displayName = "$STR_COMSPEC_SSE_EYEWEAR_RADAR_WHITE_NAME";
        descriptionShort = "$STR_COMSPEC_SSE_EYEWEAR_RADAR_WHITE_DESC";
        picture = "\z\comspec_sse\addons\eyewear\data\icon_radar_white_ca.paa";
        model = "\z\comspec_sse\addons\eyewear\data\models\ew_radar_white.p3d";
        comspec_sse_eyewear = "radar_white";
    };
    class COMSPEC_SSE_G_Oakley_RadarEV_Black: COMSPEC_SSE_G_Eyewear_Base {
        scope = 2;
        scopeArsenal = 2;
        displayName = "$STR_COMSPEC_SSE_EYEWEAR_RADAR_BLACK_NAME";
        descriptionShort = "$STR_COMSPEC_SSE_EYEWEAR_RADAR_BLACK_DESC";
        picture = "\z\comspec_sse\addons\eyewear\data\icon_radar_black_ca.paa";
        model = "\z\comspec_sse\addons\eyewear\data\models\ew_radar_black.p3d";
        comspec_sse_eyewear = "radar_black";
    };
    class COMSPEC_SSE_G_DFranklin_Hurricane_Black: COMSPEC_SSE_G_Eyewear_Base {
        scope = 2;
        scopeArsenal = 2;
        displayName = "$STR_COMSPEC_SSE_EYEWEAR_HURRICANE_BLACK_NAME";
        descriptionShort = "$STR_COMSPEC_SSE_EYEWEAR_HURRICANE_BLACK_DESC";
        picture = "\z\comspec_sse\addons\eyewear\data\icon_hurricane_black_ca.paa";
        model = "\z\comspec_sse\addons\eyewear\data\models\ew_hurricane_black.p3d";
        comspec_sse_eyewear = "hurricane_black";
    };
    class COMSPEC_SSE_G_DFranklin_Wind_Rainbow: COMSPEC_SSE_G_Eyewear_Base {
        scope = 2;
        scopeArsenal = 2;
        displayName = "$STR_COMSPEC_SSE_EYEWEAR_WIND_RAINBOW_NAME";
        descriptionShort = "$STR_COMSPEC_SSE_EYEWEAR_WIND_RAINBOW_DESC";
        picture = "\z\comspec_sse\addons\eyewear\data\icon_wind_rainbow_ca.paa";
        model = "\z\comspec_sse\addons\eyewear\data\models\ew_wind_rainbow.p3d";
        comspec_sse_eyewear = "wind_rainbow";
    };
    class COMSPEC_SSE_G_DFranklin_Wind_Orange: COMSPEC_SSE_G_Eyewear_Base {
        scope = 2;
        scopeArsenal = 2;
        displayName = "$STR_COMSPEC_SSE_EYEWEAR_WIND_ORANGE_NAME";
        descriptionShort = "$STR_COMSPEC_SSE_EYEWEAR_WIND_ORANGE_DESC";
        picture = "\z\comspec_sse\addons\eyewear\data\icon_wind_orange_ca.paa";
        model = "\z\comspec_sse\addons\eyewear\data\models\ew_wind_orange.p3d";
        comspec_sse_eyewear = "wind_orange";
    };
};

class CfgWeapons {
    class NVGoggles;
    class CBA_MiscItem;
    class CBA_MiscItem_ItemInfo;
    // b) Slot JVN : même modèle (repère du proxy « nvg » = repère des lunettes), aucune vision nocturne.
    class COMSPEC_SSE_G_Eyewear_NVG_Base: NVGoggles {
        author = "COMSPEC";
        scope = 0;
        scopeArsenal = 0;
        scopeCurator = 0;
        modelOptics = "\A3\Weapons_F\empty.p3d";
        visionMode[] = {"Normal"};
        thermalMode[] = {};
    };
    // c) Slot virtuel COMSPEC : objet d'inventaire, porté par l'action « Porter les lunettes ».
    class COMSPEC_SSE_G_Eyewear_Item_Base: CBA_MiscItem {
        author = "COMSPEC";
        scope = 0;
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 1; };
    };
    class COMSPEC_SSE_G_Oakley_RadarEV_White_NVG: COMSPEC_SSE_G_Eyewear_NVG_Base {
        scope = 2;
        scopeArsenal = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_EYEWEAR_RADAR_WHITE_NAME_NVG";
        descriptionShort = "$STR_COMSPEC_SSE_EYEWEAR_RADAR_WHITE_DESC_NVG";
        picture = "\z\comspec_sse\addons\eyewear\data\icon_radar_white_ca.paa";
        model = "\z\comspec_sse\addons\eyewear\data\models\ew_radar_white_head.p3d";
        comspec_sse_eyewear = "radar_white";
        class ItemInfo {
            type = 616;
            hmdType = 0;
            uniformModel = "\z\comspec_sse\addons\eyewear\data\models\ew_radar_white_nvg.p3d";
            modelOff = "\z\comspec_sse\addons\eyewear\data\models\ew_radar_white_nvg.p3d";
            mass = 1;
        };
    };
    class COMSPEC_SSE_G_Oakley_RadarEV_White_Item: COMSPEC_SSE_G_Eyewear_Item_Base {
        scope = 2;
        scopeArsenal = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_EYEWEAR_RADAR_WHITE_NAME_ITEM";
        descriptionShort = "$STR_COMSPEC_SSE_EYEWEAR_RADAR_WHITE_DESC_ITEM";
        picture = "\z\comspec_sse\addons\eyewear\data\icon_radar_white_ca.paa";
        model = "\z\comspec_sse\addons\eyewear\data\models\ew_radar_white_head.p3d";
        comspec_sse_eyewear = "radar_white";
    };
    class COMSPEC_SSE_G_Oakley_RadarEV_Black_NVG: COMSPEC_SSE_G_Eyewear_NVG_Base {
        scope = 2;
        scopeArsenal = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_EYEWEAR_RADAR_BLACK_NAME_NVG";
        descriptionShort = "$STR_COMSPEC_SSE_EYEWEAR_RADAR_BLACK_DESC_NVG";
        picture = "\z\comspec_sse\addons\eyewear\data\icon_radar_black_ca.paa";
        model = "\z\comspec_sse\addons\eyewear\data\models\ew_radar_black_head.p3d";
        comspec_sse_eyewear = "radar_black";
        class ItemInfo {
            type = 616;
            hmdType = 0;
            uniformModel = "\z\comspec_sse\addons\eyewear\data\models\ew_radar_black_nvg.p3d";
            modelOff = "\z\comspec_sse\addons\eyewear\data\models\ew_radar_black_nvg.p3d";
            mass = 1;
        };
    };
    class COMSPEC_SSE_G_Oakley_RadarEV_Black_Item: COMSPEC_SSE_G_Eyewear_Item_Base {
        scope = 2;
        scopeArsenal = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_EYEWEAR_RADAR_BLACK_NAME_ITEM";
        descriptionShort = "$STR_COMSPEC_SSE_EYEWEAR_RADAR_BLACK_DESC_ITEM";
        picture = "\z\comspec_sse\addons\eyewear\data\icon_radar_black_ca.paa";
        model = "\z\comspec_sse\addons\eyewear\data\models\ew_radar_black_head.p3d";
        comspec_sse_eyewear = "radar_black";
    };
    class COMSPEC_SSE_G_DFranklin_Hurricane_Black_NVG: COMSPEC_SSE_G_Eyewear_NVG_Base {
        scope = 2;
        scopeArsenal = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_EYEWEAR_HURRICANE_BLACK_NAME_NVG";
        descriptionShort = "$STR_COMSPEC_SSE_EYEWEAR_HURRICANE_BLACK_DESC_NVG";
        picture = "\z\comspec_sse\addons\eyewear\data\icon_hurricane_black_ca.paa";
        model = "\z\comspec_sse\addons\eyewear\data\models\ew_hurricane_black_head.p3d";
        comspec_sse_eyewear = "hurricane_black";
        class ItemInfo {
            type = 616;
            hmdType = 0;
            uniformModel = "\z\comspec_sse\addons\eyewear\data\models\ew_hurricane_black_nvg.p3d";
            modelOff = "\z\comspec_sse\addons\eyewear\data\models\ew_hurricane_black_nvg.p3d";
            mass = 1;
        };
    };
    class COMSPEC_SSE_G_DFranklin_Hurricane_Black_Item: COMSPEC_SSE_G_Eyewear_Item_Base {
        scope = 2;
        scopeArsenal = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_EYEWEAR_HURRICANE_BLACK_NAME_ITEM";
        descriptionShort = "$STR_COMSPEC_SSE_EYEWEAR_HURRICANE_BLACK_DESC_ITEM";
        picture = "\z\comspec_sse\addons\eyewear\data\icon_hurricane_black_ca.paa";
        model = "\z\comspec_sse\addons\eyewear\data\models\ew_hurricane_black_head.p3d";
        comspec_sse_eyewear = "hurricane_black";
    };
    class COMSPEC_SSE_G_DFranklin_Wind_Rainbow_NVG: COMSPEC_SSE_G_Eyewear_NVG_Base {
        scope = 2;
        scopeArsenal = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_EYEWEAR_WIND_RAINBOW_NAME_NVG";
        descriptionShort = "$STR_COMSPEC_SSE_EYEWEAR_WIND_RAINBOW_DESC_NVG";
        picture = "\z\comspec_sse\addons\eyewear\data\icon_wind_rainbow_ca.paa";
        model = "\z\comspec_sse\addons\eyewear\data\models\ew_wind_rainbow_head.p3d";
        comspec_sse_eyewear = "wind_rainbow";
        class ItemInfo {
            type = 616;
            hmdType = 0;
            uniformModel = "\z\comspec_sse\addons\eyewear\data\models\ew_wind_rainbow_nvg.p3d";
            modelOff = "\z\comspec_sse\addons\eyewear\data\models\ew_wind_rainbow_nvg.p3d";
            mass = 1;
        };
    };
    class COMSPEC_SSE_G_DFranklin_Wind_Rainbow_Item: COMSPEC_SSE_G_Eyewear_Item_Base {
        scope = 2;
        scopeArsenal = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_EYEWEAR_WIND_RAINBOW_NAME_ITEM";
        descriptionShort = "$STR_COMSPEC_SSE_EYEWEAR_WIND_RAINBOW_DESC_ITEM";
        picture = "\z\comspec_sse\addons\eyewear\data\icon_wind_rainbow_ca.paa";
        model = "\z\comspec_sse\addons\eyewear\data\models\ew_wind_rainbow_head.p3d";
        comspec_sse_eyewear = "wind_rainbow";
    };
    class COMSPEC_SSE_G_DFranklin_Wind_Orange_NVG: COMSPEC_SSE_G_Eyewear_NVG_Base {
        scope = 2;
        scopeArsenal = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_EYEWEAR_WIND_ORANGE_NAME_NVG";
        descriptionShort = "$STR_COMSPEC_SSE_EYEWEAR_WIND_ORANGE_DESC_NVG";
        picture = "\z\comspec_sse\addons\eyewear\data\icon_wind_orange_ca.paa";
        model = "\z\comspec_sse\addons\eyewear\data\models\ew_wind_orange_head.p3d";
        comspec_sse_eyewear = "wind_orange";
        class ItemInfo {
            type = 616;
            hmdType = 0;
            uniformModel = "\z\comspec_sse\addons\eyewear\data\models\ew_wind_orange_nvg.p3d";
            modelOff = "\z\comspec_sse\addons\eyewear\data\models\ew_wind_orange_nvg.p3d";
            mass = 1;
        };
    };
    class COMSPEC_SSE_G_DFranklin_Wind_Orange_Item: COMSPEC_SSE_G_Eyewear_Item_Base {
        scope = 2;
        scopeArsenal = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_EYEWEAR_WIND_ORANGE_NAME_ITEM";
        descriptionShort = "$STR_COMSPEC_SSE_EYEWEAR_WIND_ORANGE_DESC_ITEM";
        picture = "\z\comspec_sse\addons\eyewear\data\icon_wind_orange_ca.paa";
        model = "\z\comspec_sse\addons\eyewear\data\models\ew_wind_orange_head.p3d";
        comspec_sse_eyewear = "wind_orange";
    };
};

class CfgVehicles {
    class NonStrategic;
    class Box_NATO_Support_F;
    // Objet 3D du slot virtuel : créé localement par chaque client (createVehicleLocal) et accroché à la tête du porteur.
    // Suivi de la tête à chaque image (fn_eyewearFollow) : origine du modèle = point « pilot » + ce décalage (mètres,
    // repère du regard : x droite du personnage, y avant, z haut ; relevé en jeu : « pilot » ~5 cm au-dessus des
    // yeux et ~2 cm sur la gauche ; 5 cm devant pour que le verre passe devant le nez).
    class COMSPEC_SSE_EyewearHead_Base: NonStrategic {
        author = "COMSPEC";
        scope = 1;
        scopeCurator = 0;
        displayName = "$STR_COMSPEC_SSE_EYEWEAR_HEAD";
        destrType = "DestructNo";
        model = "\A3\Weapons_F\empty.p3d";
        comspec_sse_eyewearAttachOffset[] = {0.02, 0.05, -0.055};
    };
    class COMSPEC_SSE_EyewearHead_radar_white: COMSPEC_SSE_EyewearHead_Base {
        model = "\z\comspec_sse\addons\eyewear\data\models\ew_radar_white_head.p3d";
        comspec_sse_eyewear = "radar_white";
    };
    class COMSPEC_SSE_EyewearHead_radar_black: COMSPEC_SSE_EyewearHead_Base {
        model = "\z\comspec_sse\addons\eyewear\data\models\ew_radar_black_head.p3d";
        comspec_sse_eyewear = "radar_black";
    };
    class COMSPEC_SSE_EyewearHead_hurricane_black: COMSPEC_SSE_EyewearHead_Base {
        model = "\z\comspec_sse\addons\eyewear\data\models\ew_hurricane_black_head.p3d";
        comspec_sse_eyewear = "hurricane_black";
    };
    class COMSPEC_SSE_EyewearHead_wind_rainbow: COMSPEC_SSE_EyewearHead_Base {
        model = "\z\comspec_sse\addons\eyewear\data\models\ew_wind_rainbow_head.p3d";
        comspec_sse_eyewear = "wind_rainbow";
    };
    class COMSPEC_SSE_EyewearHead_wind_orange: COMSPEC_SSE_EyewearHead_Base {
        model = "\z\comspec_sse\addons\eyewear\data\models\ew_wind_orange_head.p3d";
        comspec_sse_eyewear = "wind_orange";
    };
    class COMSPEC_SSE_Box_Eyewear: Box_NATO_Support_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_EYEWEAR_BOX";
        editorCategory = "COMSPEC_SSE_EdKits";
        editorSubcategory = "COMSPEC_SSE_EdBoxes";
        maximumLoad = 1000;
        class TransportWeapons {};
        class TransportMagazines {};
        class TransportBackpacks {};
        class TransportItems {
            class _xx_COMSPEC_SSE_G_Oakley_RadarEV_White { name = "COMSPEC_SSE_G_Oakley_RadarEV_White"; count = 4; };
            class _xx_COMSPEC_SSE_G_Oakley_RadarEV_White_NVG { name = "COMSPEC_SSE_G_Oakley_RadarEV_White_NVG"; count = 2; };
            class _xx_COMSPEC_SSE_G_Oakley_RadarEV_White_Item { name = "COMSPEC_SSE_G_Oakley_RadarEV_White_Item"; count = 2; };
            class _xx_COMSPEC_SSE_G_Oakley_RadarEV_Black { name = "COMSPEC_SSE_G_Oakley_RadarEV_Black"; count = 4; };
            class _xx_COMSPEC_SSE_G_Oakley_RadarEV_Black_NVG { name = "COMSPEC_SSE_G_Oakley_RadarEV_Black_NVG"; count = 2; };
            class _xx_COMSPEC_SSE_G_Oakley_RadarEV_Black_Item { name = "COMSPEC_SSE_G_Oakley_RadarEV_Black_Item"; count = 2; };
            class _xx_COMSPEC_SSE_G_DFranklin_Hurricane_Black { name = "COMSPEC_SSE_G_DFranklin_Hurricane_Black"; count = 4; };
            class _xx_COMSPEC_SSE_G_DFranklin_Hurricane_Black_NVG { name = "COMSPEC_SSE_G_DFranklin_Hurricane_Black_NVG"; count = 2; };
            class _xx_COMSPEC_SSE_G_DFranklin_Hurricane_Black_Item { name = "COMSPEC_SSE_G_DFranklin_Hurricane_Black_Item"; count = 2; };
            class _xx_COMSPEC_SSE_G_DFranklin_Wind_Rainbow { name = "COMSPEC_SSE_G_DFranklin_Wind_Rainbow"; count = 4; };
            class _xx_COMSPEC_SSE_G_DFranklin_Wind_Rainbow_NVG { name = "COMSPEC_SSE_G_DFranklin_Wind_Rainbow_NVG"; count = 2; };
            class _xx_COMSPEC_SSE_G_DFranklin_Wind_Rainbow_Item { name = "COMSPEC_SSE_G_DFranklin_Wind_Rainbow_Item"; count = 2; };
            class _xx_COMSPEC_SSE_G_DFranklin_Wind_Orange { name = "COMSPEC_SSE_G_DFranklin_Wind_Orange"; count = 4; };
            class _xx_COMSPEC_SSE_G_DFranklin_Wind_Orange_NVG { name = "COMSPEC_SSE_G_DFranklin_Wind_Orange_NVG"; count = 2; };
            class _xx_COMSPEC_SSE_G_DFranklin_Wind_Orange_Item { name = "COMSPEC_SSE_G_DFranklin_Wind_Orange_Item"; count = 2; };
        };
    };
};
