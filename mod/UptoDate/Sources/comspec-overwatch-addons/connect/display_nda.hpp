// Note de service : accès au mod, licence, données, bêta (idd 9978). Couleurs et logo du site Athena, comme le cartouche de chargement (fn_loadingBadge).
// Textes : fn_ndaTexts. Défilement, barre de lecture et activation de « J'accepte » : fn_ndaWatch.
class COMSPEC_NDA_Dialog {
    idd = 9978;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "(_this select 0) call comspec_overwatch_connect_fnc_trDisplay; uiNamespace setVariable ['COMSPEC_NDA_Display', _this select 0]; (_this select 0) call comspec_overwatch_connect_fnc_ndaOnLoad;";
    onUnload = "uiNamespace setVariable ['COMSPEC_NDA_Display', displayNull];";

    class ControlsBackground {
        class Dimmer: RscText {
            idc = -1;
            x = "safezoneXAbs";
            y = "safezoneY";
            w = "safezoneWAbs";
            h = "safezoneH";
            colorBackground[] = {0.01, 0.015, 0.012, 0.82};
        };
    };

    class Controls {
        class Panel: RscText {
            idc = -1;
            x = 0.17 * safezoneW + safezoneX;
            y = 0.04 * safezoneH + safezoneY;
            w = 0.66 * safezoneW;
            h = 0.92 * safezoneH;
            colorBackground[] = {0.035, 0.05, 0.045, 0.99};
        };

        class AccentBar: RscText {
            idc = -1;
            x = 0.17 * safezoneW + safezoneX;
            y = 0.04 * safezoneH + safezoneY;
            w = 0.66 * safezoneW;
            h = 0.004 * safezoneH;
            colorBackground[] = {0.36, 0.78, 0.42, 0.95};
        };

        // Largeur recalculée par fn_ndaOnLoad : logo carré.
        class Logo: RscPicture {
            idc = 9517;
            text = "\z\comspec_atak_native\addons\main\data\brand_athena_ca.paa";
            x = 0.185 * safezoneW + safezoneX;
            y = 0.055 * safezoneH + safezoneY;
            w = 0.04 * safezoneW;
            h = 0.07 * safezoneH;
        };

        class Title: RscStructuredText {
            idc = 9510;
            text = "";
            x = 0.232 * safezoneW + safezoneX;
            y = 0.055 * safezoneH + safezoneY;
            w = 0.46 * safezoneW;
            h = 0.036 * safezoneH;
        };

        class BtnLangFR: COMSPEC_RscButton {
            idc = 9505;
            text = "FR";
            x = 0.70 * safezoneW + safezoneX;
            y = 0.058 * safezoneH + safezoneY;
            w = 0.05 * safezoneW;
            h = 0.03 * safezoneH;
            sizeEx = 0.032;
            action = "['fr'] call comspec_overwatch_connect_fnc_ndaSetLanguage;";
        };

        class BtnLangEN: COMSPEC_RscButton {
            idc = 9506;
            text = "EN";
            x = 0.755 * safezoneW + safezoneX;
            y = 0.058 * safezoneH + safezoneY;
            w = 0.05 * safezoneW;
            h = 0.03 * safezoneH;
            sizeEx = 0.032;
            action = "['en'] call comspec_overwatch_connect_fnc_ndaSetLanguage;";
        };

        class Subtitle: RscStructuredText {
            idc = 9511;
            text = "";
            x = 0.232 * safezoneW + safezoneX;
            y = 0.092 * safezoneH + safezoneY;
            w = 0.573 * safezoneW;
            h = 0.034 * safezoneH;
        };

        // L'essentiel : toujours visible, hors du texte qui défile.
        class SummaryFrame: RscText {
            idc = -1;
            x = 0.185 * safezoneW + safezoneX;
            y = 0.135 * safezoneH + safezoneY;
            w = 0.62 * safezoneW;
            h = 0.125 * safezoneH;
            colorBackground[] = {0.07, 0.125, 0.09, 1};
        };

        class SummaryRail: RscText {
            idc = -1;
            x = 0.185 * safezoneW + safezoneX;
            y = 0.135 * safezoneH + safezoneY;
            w = 0.003 * safezoneW;
            h = 0.125 * safezoneH;
            colorBackground[] = {0.36, 0.78, 0.42, 0.95};
        };

        class Summary: RscStructuredText {
            idc = 9518;
            text = "";
            x = 0.192 * safezoneW + safezoneX;
            y = 0.138 * safezoneH + safezoneY;
            w = 0.61 * safezoneW;
            h = 0.12 * safezoneH;
        };

        class BodyFrame: RscText {
            idc = -1;
            x = 0.185 * safezoneW + safezoneX;
            y = 0.27 * safezoneH + safezoneY;
            w = 0.62 * safezoneW;
            h = 0.50 * safezoneH;
            colorBackground[] = {0.055, 0.075, 0.065, 1};
        };

        class BodyScroll: RscControlsGroup {
            idc = 9508;
            x = 0.19 * safezoneW + safezoneX;
            y = 0.276 * safezoneH + safezoneY;
            w = 0.61 * safezoneW;
            h = 0.488 * safezoneH;

            class VScrollbar {
                width = 0.018;
                autoScrollEnabled = 0;
                color[] = {0.36, 0.78, 0.42, 0.8};
                colorActive[] = {0.45, 0.88, 0.5, 1};
                colorDisabled[] = {0.3, 0.4, 0.35, 0.4};
                shadow = 0;
                scrollSpeed = 0.06;
            };
            class HScrollbar {
                height = 0;
                color[] = {0, 0, 0, 0};
            };
            class ScrollBar {
                color[] = {0.36, 0.78, 0.42, 0.8};
                colorActive[] = {0.45, 0.88, 0.5, 1};
                colorDisabled[] = {0.3, 0.4, 0.35, 0.4};
                shadow = 0;
                thumb = "\A3\ui_f\data\gui\cfg\scrollbar\thumb_ca.paa";
                arrowFull = "\A3\ui_f\data\gui\cfg\scrollbar\arrowFull_ca.paa";
                arrowEmpty = "\A3\ui_f\data\gui\cfg\scrollbar\arrowEmpty_ca.paa";
                border = "\A3\ui_f\data\gui\cfg\scrollbar\border_ca.paa";
            };

            class Controls {
                class Body: RscStructuredText {
                    idc = 9500;
                    text = "";
                    x = 0;
                    y = 0;
                    w = 0.59 * safezoneW;
                    h = 2.8 * safezoneH;
                    colorBackground[] = {0, 0, 0, 0};
                    size = "(((((safezoneW / safezoneH) min 1.2) / 1.2) / 25) * 0.9)";
                };
            };
        };

        // Barre de lecture : fond et remplissage (largeur tenue par fn_ndaWatch).
        class ProgressTrack: RscText {
            idc = 9514;
            x = 0.185 * safezoneW + safezoneX;
            y = 0.774 * safezoneH + safezoneY;
            w = 0.62 * safezoneW;
            h = 0.005 * safezoneH;
            colorBackground[] = {0.13, 0.18, 0.155, 1};
        };

        class ProgressFill: RscText {
            idc = 9515;
            x = 0.185 * safezoneW + safezoneX;
            y = 0.774 * safezoneH + safezoneY;
            w = 0;
            h = 0.005 * safezoneH;
            colorBackground[] = {0.36, 0.78, 0.42, 1};
        };

        class ProgressLabel: RscStructuredText {
            idc = 9516;
            text = "";
            x = 0.185 * safezoneW + safezoneX;
            y = 0.782 * safezoneH + safezoneY;
            w = 0.62 * safezoneW;
            h = 0.026 * safezoneH;
        };

        class Footer: RscStructuredText {
            idc = 9512;
            text = "";
            x = 0.185 * safezoneW + safezoneX;
            y = 0.809 * safezoneH + safezoneY;
            w = 0.62 * safezoneW;
            h = 0.034 * safezoneH;
        };

        class BtnAccept: COMSPEC_RscButtonAccent {
            idc = 9501;
            text = "";
            x = 0.185 * safezoneW + safezoneX;
            y = 0.847 * safezoneH + safezoneY;
            w = 0.37 * safezoneW;
            h = 0.05 * safezoneH;
            sizeEx = 0.038;
            colorBackground[] = {0.18, 0.46, 0.24, 1};
            colorBackgroundActive[] = {0.24, 0.58, 0.3, 1};
            colorFocused[] = {0.24, 0.58, 0.3, 1};
            colorBackgroundDisabled[] = {0.11, 0.15, 0.13, 1};
            colorDisabled[] = {0.68, 0.74, 0.7, 1};
            colorText[] = {0.96, 0.98, 0.96, 1};
            action = "[] call comspec_overwatch_connect_fnc_ndaAccept;";
        };

        class BtnDecline: COMSPEC_RscButtonDanger {
            idc = 9502;
            text = "";
            x = 0.565 * safezoneW + safezoneX;
            y = 0.847 * safezoneH + safezoneY;
            w = 0.24 * safezoneW;
            h = 0.05 * safezoneH;
            sizeEx = 0.038;
            action = "[] call comspec_overwatch_connect_fnc_ndaDecline;";
        };

        class LegalNote: RscStructuredText {
            idc = 9513;
            text = "";
            x = 0.185 * safezoneW + safezoneX;
            y = 0.905 * safezoneH + safezoneY;
            w = 0.62 * safezoneW;
            h = 0.028 * safezoneH;
        };
    };
};
