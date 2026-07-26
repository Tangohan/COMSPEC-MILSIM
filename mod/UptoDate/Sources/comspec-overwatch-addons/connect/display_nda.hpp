// Note bêta publique — premier lancement COMSPEC Overwatch (idd 9978)
class COMSPEC_NDA_Dialog {
    idd = 9978;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_NDA_Display', _this select 0]; (_this select 0) call comspec_overwatch_connect_fnc_ndaOnLoad;";
    onUnload = "uiNamespace setVariable ['COMSPEC_NDA_Display', displayNull];";

    class ControlsBackground {
        class Dimmer: RscText {
            idc = -1;
            x = "safezoneXAbs";
            y = "safezoneY";
            w = "safezoneWAbs";
            h = "safezoneH";
            colorBackground[] = {0.01, 0.02, 0.04, 0.72};
        };
    };

    class Controls {
        class Panel: RscText {
            idc = -1;
            x = 0.22 * safezoneW + safezoneX;
            y = 0.08 * safezoneH + safezoneY;
            w = 0.56 * safezoneW;
            h = 0.84 * safezoneH;
            colorBackground[] = {0.015, 0.04, 0.08, 0.97};
        };

        class AccentBar: RscText {
            idc = -1;
            x = 0.22 * safezoneW + safezoneX;
            y = 0.08 * safezoneH + safezoneY;
            w = 0.56 * safezoneW;
            h = 0.004 * safezoneH;
            colorBackground[] = {0.2, 0.85, 0.65, 0.9};
        };

        class SideRail: RscText {
            idc = -1;
            x = 0.22 * safezoneW + safezoneX;
            y = 0.084 * safezoneH + safezoneY;
            w = 0.003 * safezoneW;
            h = 0.836 * safezoneH;
            colorBackground[] = {0.2, 0.85, 0.65, 0.35};
        };

        class Title: RscStructuredText {
            idc = 9510;
            text = "";
            x = 0.24 * safezoneW + safezoneX;
            y = 0.095 * safezoneH + safezoneY;
            w = 0.42 * safezoneW;
            h = 0.034 * safezoneH;
        };

        class BtnLangFR: COMSPEC_RscButton {
            idc = 9505;
            text = "FR";
            x = 0.665 * safezoneW + safezoneX;
            y = 0.095 * safezoneH + safezoneY;
            w = 0.045 * safezoneW;
            h = 0.028 * safezoneH;
            sizeEx = 0.032;
            action = "['fr'] call comspec_overwatch_connect_fnc_ndaSetLanguage;";
        };

        class BtnLangEN: COMSPEC_RscButton {
            idc = 9506;
            text = "EN";
            x = 0.715 * safezoneW + safezoneX;
            y = 0.095 * safezoneH + safezoneY;
            w = 0.045 * safezoneW;
            h = 0.028 * safezoneH;
            sizeEx = 0.032;
            action = "['en'] call comspec_overwatch_connect_fnc_ndaSetLanguage;";
        };

        class Subtitle: RscStructuredText {
            idc = 9511;
            text = "";
            x = 0.24 * safezoneW + safezoneX;
            y = 0.132 * safezoneH + safezoneY;
            w = 0.52 * safezoneW;
            h = 0.036 * safezoneH;
        };

        class BodyFrame: RscText {
            idc = -1;
            x = 0.24 * safezoneW + safezoneX;
            y = 0.175 * safezoneH + safezoneY;
            w = 0.52 * safezoneW;
            h = 0.58 * safezoneH;
            colorBackground[] = {0.03, 0.07, 0.11, 1};
        };

        class BodyScroll: RscControlsGroup {
            idc = 9508;
            x = 0.245 * safezoneW + safezoneX;
            y = 0.182 * safezoneH + safezoneY;
            w = 0.51 * safezoneW;
            h = 0.566 * safezoneH;

            class VScrollbar {
                width = 0.018;
                autoScrollEnabled = 1;
                color[] = {0.2, 0.85, 0.65, 0.75};
                colorActive[] = {0.3, 0.95, 0.75, 1};
                colorDisabled[] = {0.3, 0.4, 0.45, 0.4};
                shadow = 0;
                scrollSpeed = 0.06;
            };
            class HScrollbar {
                height = 0;
                color[] = {0, 0, 0, 0};
            };
            class ScrollBar {
                color[] = {0.2, 0.85, 0.65, 0.75};
                colorActive[] = {0.3, 0.95, 0.75, 1};
                colorDisabled[] = {0.3, 0.4, 0.45, 0.4};
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
                    w = 0.49 * safezoneW;
                    h = 2.8 * safezoneH;
                    colorBackground[] = {0, 0, 0, 0};
                    size = "(((((safezoneW / safezoneH) min 1.2) / 1.2) / 25) * 0.9)";
                };
            };
        };

        class Footer: RscStructuredText {
            idc = 9512;
            text = "";
            x = 0.24 * safezoneW + safezoneX;
            y = 0.765 * safezoneH + safezoneY;
            w = 0.52 * safezoneW;
            h = 0.040 * safezoneH;
        };

        class BtnAccept: COMSPEC_RscButtonAccent {
            idc = 9501;
            text = "Compris";
            x = 0.24 * safezoneW + safezoneX;
            y = 0.820 * safezoneH + safezoneY;
            w = 0.28 * safezoneW;
            h = 0.048 * safezoneH;
            sizeEx = 0.038;
            action = "[] call comspec_overwatch_connect_fnc_ndaAccept;";
        };

        class BtnDecline: COMSPEC_RscButtonDanger {
            idc = 9502;
            text = "Plus tard";
            x = 0.535 * safezoneW + safezoneX;
            y = 0.820 * safezoneH + safezoneY;
            w = 0.225 * safezoneW;
            h = 0.048 * safezoneH;
            sizeEx = 0.038;
            action = "[] call comspec_overwatch_connect_fnc_ndaDecline;";
        };

        class LegalNote: RscStructuredText {
            idc = 9513;
            text = "";
            x = 0.24 * safezoneW + safezoneX;
            y = 0.875 * safezoneH + safezoneY;
            w = 0.52 * safezoneW;
            h = 0.028 * safezoneH;
        };
    };
};
