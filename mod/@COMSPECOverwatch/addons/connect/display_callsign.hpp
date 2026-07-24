// Indicatif + rôle tactique (idd 9974)
class COMSPEC_Callsign_Dialog {
    idd = 9974;
    movingEnable = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_Callsign_Display', _this select 0]; [] call comspec_overwatch_connect_fnc_callsignDialogOnLoad;";
    onUnload = "uiNamespace setVariable ['COMSPEC_Callsign_Display', displayNull];";

    class Controls {
        class Background: RscText {
            idc = -1;
            x = 0.34 * safezoneW + safezoneX;
            y = 0.24 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.38 * safezoneH;
            colorBackground[] = {0.015, 0.04, 0.08, 0.96};
        };

        class AccentBar: RscText {
            idc = -1;
            x = 0.34 * safezoneW + safezoneX;
            y = 0.24 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.004 * safezoneH;
            colorBackground[] = {0.2, 0.85, 0.65, 0.9};
        };

        class Title: RscStructuredText {
            idc = -1;
            text = "<t font='RobotoCondensedBold' size='1' align='center' color='#e8f4f0'>My tactical identity</t>";
            x = 0.36 * safezoneW + safezoneX;
            y = 0.255 * safezoneH + safezoneY;
            w = 0.28 * safezoneW;
            h = 0.03 * safezoneH;
        };

        class Hint: RscStructuredText {
            idc = -1;
            text = "<t align='center' size='0.55' color='#8aa0b4'>Callsign and role as they appear to the team.</t>";
            x = 0.36 * safezoneW + safezoneX;
            y = 0.29 * safezoneH + safezoneY;
            w = 0.28 * safezoneW;
            h = 0.032 * safezoneH;
        };

        class LabelCs: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>CALLSIGN</t>";
            x = 0.36 * safezoneW + safezoneX;
            y = 0.33 * safezoneH + safezoneY;
            w = 0.28 * safezoneW;
            h = 0.018 * safezoneH;
        };

        class CallsignEdit: RscEdit {
            idc = 9301;
            x = 0.36 * safezoneW + safezoneX;
            y = 0.35 * safezoneH + safezoneY;
            w = 0.28 * safezoneW;
            h = 0.034 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1};
            colorText[] = {0.95, 0.98, 0.9, 1};
            sizeEx = 0.04;
            autocomplete = "";
        };

        class LabelRole: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>ROLE</t>";
            x = 0.36 * safezoneW + safezoneX;
            y = 0.395 * safezoneH + safezoneY;
            w = 0.28 * safezoneW;
            h = 0.018 * safezoneH;
        };

        class RoleEdit: RscEdit {
            idc = 9305;
            x = 0.36 * safezoneW + safezoneX;
            y = 0.415 * safezoneH + safezoneY;
            w = 0.28 * safezoneW;
            h = 0.034 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1};
            colorText[] = {0.95, 0.98, 0.9, 1};
            sizeEx = 0.038;
            autocomplete = "";
        };

        class StatusText: RscStructuredText {
            idc = 9302;
            text = "";
            x = 0.36 * safezoneW + safezoneX;
            y = 0.46 * safezoneH + safezoneY;
            w = 0.28 * safezoneW;
            h = 0.05 * safezoneH;
        };

        class BtnSave: RscButton {
            idc = 9303;
            text = "Save";
            x = 0.36 * safezoneW + safezoneX;
            y = 0.53 * safezoneH + safezoneY;
            w = 0.16 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.08, 0.32, 0.28, 0.95};
            colorBackgroundActive[] = {0.12, 0.45, 0.38, 1};
            action = "[] call comspec_overwatch_connect_fnc_callsignDialogSubmit;";
        };

        class BtnClose: RscButton {
            idc = 9304;
            text = "Close";
            x = 0.53 * safezoneW + safezoneX;
            y = 0.53 * safezoneH + safezoneY;
            w = 0.11 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.12, 0.08, 0.08, 0.95};
            colorBackgroundActive[] = {0.28, 0.12, 0.12, 1};
            action = "closeDialog 0;";
        };
    };
};
