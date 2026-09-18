// Dépannage liaison : une fonction toutes les 55 s (idd 9995)
class COMSPEC_DiagIsolate_Dialog {
    idd = 9995;
    movingEnable = 1;
    onLoad = "(_this select 0) call comspec_overwatch_connect_fnc_diagIsolateOnLoad;";
    onUnload = "uiNamespace setVariable ['COMSPEC_DiagIsolate_Display', displayNull];";

    class Controls {
        class Background: RscText {
            idc = -1;
            x = 0.28 * safezoneW + safezoneX;
            y = 0.24 * safezoneH + safezoneY;
            w = 0.44 * safezoneW;
            h = 0.42 * safezoneH;
            colorBackground[] = {0.015, 0.04, 0.08, 0.96};
        };
        class AccentBar: RscText {
            idc = -1;
            x = 0.28 * safezoneW + safezoneX;
            y = 0.24 * safezoneH + safezoneY;
            w = 0.44 * safezoneW;
            h = 0.004 * safezoneH;
            colorBackground[] = {0.2, 0.85, 0.65, 0.9};
        };
        class Title: RscStructuredText {
            idc = -1;
            text = "<t font='RobotoCondensedBold' size='1' align='center' color='#e8f4f0'>Dépannage liaison</t>";
            x = 0.30 * safezoneW + safezoneX;
            y = 0.255 * safezoneH + safezoneY;
            w = 0.40 * safezoneW;
            h = 0.032 * safezoneH;
        };
        class Hint: RscStructuredText {
            idc = 20;
            text = "";
            x = 0.30 * safezoneW + safezoneX;
            y = 0.295 * safezoneH + safezoneY;
            w = 0.40 * safezoneW;
            h = 0.22 * safezoneH;
        };
        class BtnStart: RscButton {
            idc = 21;
            text = "Lancer (55 s par fonction)";
            x = 0.30 * safezoneW + safezoneX;
            y = 0.53 * safezoneH + safezoneY;
            w = 0.19 * safezoneW;
            h = 0.04 * safezoneH;
            colorBackground[] = {0.02, 0.28, 0.22, 0.95};
            action = "[] call comspec_overwatch_connect_fnc_diagIsolateStart;";
        };
        class BtnStop: RscButton {
            idc = 22;
            text = "Arrêter";
            x = 0.51 * safezoneW + safezoneX;
            y = 0.53 * safezoneH + safezoneY;
            w = 0.09 * safezoneW;
            h = 0.04 * safezoneH;
            colorBackground[] = {0.28, 0.08, 0.08, 0.95};
            action = "[] call comspec_overwatch_connect_fnc_diagIsolateStop; closeDialog 0;";
        };
        class BtnClose: RscButton {
            idc = 23;
            text = "Fermer";
            x = 0.61 * safezoneW + safezoneX;
            y = 0.53 * safezoneH + safezoneY;
            w = 0.09 * safezoneW;
            h = 0.04 * safezoneH;
            colorBackground[] = {0.06, 0.1, 0.14, 0.9};
            action = "closeDialog 0;";
        };
    };
};
