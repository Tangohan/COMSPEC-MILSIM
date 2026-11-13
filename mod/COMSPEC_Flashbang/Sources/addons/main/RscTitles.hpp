// Calque d'effet plein écran (aucun texte) : voile blanc, taches d'image rémanente, halo d'optique.
// Affiché par fn_playerLoop avec cutRsc sur un calque dédié, retiré par cutFadeOut (fn_playerReset).
class RscPicture;
class RscTitles {
    class COMSPEC_FB_Overlay {
        idd = -1;
        movingEnable = 0;
        enableSimulation = 1;
        fadeIn = 0;
        fadeOut = 0;
        duration = 1e+011;
        onLoad = "uiNamespace setVariable ['comspec_fb_overlay', _this select 0]";
        onUnload = "uiNamespace setVariable ['comspec_fb_overlay', displayNull]";
        class controls {
            class White: RscPicture {
                idc = 101;
                text = "#(argb,8,8,3)color(1,1,1,1)";
                x = "safezoneX";
                y = "safezoneY";
                w = "safezoneW";
                h = "safezoneH";
                colorText[] = {1, 1, 1, 0};
            };
            class Spot1: RscPicture {
                idc = 111;
                text = QPATHTOF(ui\fb_afterimage_ca.paa);
                x = 0;
                y = 0;
                w = 0;
                h = 0;
                colorText[] = {1, 1, 1, 0};
            };
            class Spot2: Spot1 { idc = 112; };
            class Spot3: Spot1 { idc = 113; };
            class Flare: RscPicture {
                idc = 121;
                text = QPATHTOF(ui\fb_flare_ca.paa);
                x = 0;
                y = 0;
                w = 0;
                h = 0;
                colorText[] = {1, 1, 1, 0};
            };
            // Vignette (fn_playerLoop) : bords sombres, centre clair ; dernier contrôle, donc au-dessus du reste.
            class Vignette: RscPicture {
                idc = 131;
                text = QPATHTOF(ui\fb_vignette_ca.paa);
                x = 0;
                y = 0;
                w = 0;
                h = 0;
                colorText[] = {0, 0, 0, 0};
            };
        };
    };
    // Halos des détonations (fn_halo) : 4 emplacements, chacun une couronne colorée (201-204, modèle tiré parmi
    // ui\fb_halo_1..8_ca.paa) et un cœur blanc (211-214). Aucun texte, invisible hors détonation.
    class COMSPEC_FB_Halo {
        idd = -1;
        movingEnable = 0;
        enableSimulation = 1;
        fadeIn = 0;
        fadeOut = 0;
        duration = 1e+011;
        onLoad = "uiNamespace setVariable ['comspec_fb_haloDisp', _this select 0]";
        onUnload = "uiNamespace setVariable ['comspec_fb_haloDisp', displayNull]";
        class controls {
            class Corona1: RscPicture {
                idc = 201;
                text = QPATHTOF(ui\fb_halo_1_ca.paa);
                x = 0;
                y = 0;
                w = 0;
                h = 0;
                colorText[] = {1, 1, 1, 0};
            };
            class Corona2: Corona1 { idc = 202; };
            class Corona3: Corona1 { idc = 203; };
            class Corona4: Corona1 { idc = 204; };
            class Core1: Corona1 {
                idc = 211;
                text = QPATHTOF(ui\fb_halo_core_ca.paa);
            };
            class Core2: Core1 { idc = 212; };
            class Core3: Core1 { idc = 213; };
            class Core4: Core1 { idc = 214; };
        };
    };
};
