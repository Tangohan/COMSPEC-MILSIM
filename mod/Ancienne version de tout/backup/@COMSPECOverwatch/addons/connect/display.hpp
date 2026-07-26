class COMSPEC_Chat_Dialog {
    idd = 9999;
    movingEnable = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_Chat_Display', _this select 0];";

    class Controls {
        class Background: RscText {
            idc = -1;
            x = 0.35 * safezoneW + safezoneX;
            y = 0.45 * safezoneH + safezoneY;
            w = 0.3 * safezoneW;
            h = 0.1 * safezoneH;
            colorBackground[] = {0.02, 0.05, 0.1, 0.85};
        };

        class Title: RscStructuredText {
            idc = -1;
            text = "<t font='RobotoCondensedBold' size='0.8'>COMSPEC Chat</t>";
            x = 0.355 * safezoneW + safezoneX;
            y = 0.46 * safezoneH + safezoneY;
            w = 0.2 * safezoneW;
            h = 0.02 * safezoneH;
        };

        class ChatInput: RscEdit {
            idc = 1400;
            text = "";
            x = 0.36 * safezoneW + safezoneX;
            y = 0.49 * safezoneH + safezoneY;
            w = 0.28 * safezoneW;
            h = 0.03 * safezoneH;
            colorBackground[] = {1, 1, 1, 0.05};
            colorText[] = {1, 1, 1, 1};
            font = "RobotoCondensed";
            sizeEx = 0.04;
            autocomplete = "";
            onKeyDown = "if ((_this select 1) == 28) then { [] call comspec_overwatch_connect_fnc_submitChat; };";
        };
    };
};
