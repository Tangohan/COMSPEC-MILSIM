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
        };
    };
};
