class COMSPEC_Chat_Dialog {
    idd = 9999;
    movingEnable = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_Chat_Display', _this select 0]; private _url = missionNamespace getVariable ['comspec_overwatch_api_url', '']; (_this select 0) displayCtrl 1399 ctrlSetText ('Serveur: ' + _url); private _ip = missionNamespace getVariable ['COMSPEC_userIp', '—']; (_this select 0) displayCtrl 1398 ctrlSetText ('Votre IP: ' + _ip); private _log = missionNamespace getVariable ['COMSPEC_Log', '']; if (_log != '') then { (_this select 0) displayCtrl 1402 ctrlSetText _log; };";

    class Controls {
        class Background: RscText {
            idc = -1;
            x = 0.25 * safezoneW + safezoneX;
            y = 0.2 * safezoneH + safezoneY;
            w = 0.5 * safezoneW;
            h = 0.6 * safezoneH;
            colorBackground[] = {0.02, 0.05, 0.1, 0.92};
        };

        class Title: RscStructuredText {
            idc = -1;
            text = "<t font='RobotoCondensedBold' size='0.9'>COMSPEC Overwatch Terminal</t>";
            x = 0.26 * safezoneW + safezoneX;
            y = 0.21 * safezoneH + safezoneY;
            w = 0.48 * safezoneW;
            h = 0.03 * safezoneH;
        };

        class ServerUrl: RscText {
            idc = 1399;
            text = "Serveur: —";
            x = 0.26 * safezoneW + safezoneX;
            y = 0.245 * safezoneH + safezoneY;
            w = 0.48 * safezoneW;
            h = 0.02 * safezoneH;
            sizeEx = 0.028;
            colorText[] = {0.7, 0.8, 0.9, 1};
        };

        class UserIp: RscText {
            idc = 1398;
            text = "Votre IP: —";
            x = 0.26 * safezoneW + safezoneX;
            y = 0.265 * safezoneH + safezoneY;
            w = 0.48 * safezoneW;
            h = 0.02 * safezoneH;
            sizeEx = 0.028;
            colorText[] = {0.7, 0.8, 0.9, 1};
        };

        class ChatConsoleLabel: RscStructuredText {
            idc = -1;
            text = "<t color='#88ff88'>[Console]</t>";
            x = 0.26 * safezoneW + safezoneX;
            y = 0.285 * safezoneH + safezoneY;
            w = 0.2 * safezoneW;
            h = 0.02 * safezoneH;
            size = 0.032;
        };

        class ChatConsole: RscEdit {
            idc = 1401;
            text = "";
            x = 0.26 * safezoneW + safezoneX;
            y = 0.305 * safezoneH + safezoneY;
            w = 0.48 * safezoneW;
            h = 0.18 * safezoneH;
            style = 16;
            colorBackground[] = {0, 0, 0, 0.4};
            colorText[] = {0.9, 0.9, 0.9, 1};
            font = "RobotoCondensed";
            sizeEx = 0.032;
        };

        class LogLabel: RscStructuredText {
            idc = -1;
            text = "<t color='#8888ff'>[Log DLL / API]</t>";
            x = 0.26 * safezoneW + safezoneX;
            y = 0.48 * safezoneH + safezoneY;
            w = 0.2 * safezoneW;
            h = 0.02 * safezoneH;
            size = 0.032;
        };

        class LogWindow: RscEdit {
            idc = 1402;
            text = "";
            x = 0.26 * safezoneW + safezoneX;
            y = 0.51 * safezoneH + safezoneY;
            w = 0.48 * safezoneW;
            h = 0.2 * safezoneH;
            style = 16;
            colorBackground[] = {0, 0, 0, 0.5};
            colorText[] = {0.7, 0.7, 0.8, 1};
            font = "RobotoCondensed";
            sizeEx = 0.03;
        };

        class ChatInput: RscEdit {
            idc = 1400;
            text = "";
            x = 0.26 * safezoneW + safezoneX;
            y = 0.74 * safezoneH + safezoneY;
            w = 0.4 * safezoneW;
            h = 0.04 * safezoneH;
            colorBackground[] = {0.1, 0.1, 0.15, 0.9};
            colorText[] = {1, 1, 1, 1};
            font = "RobotoCondensed";
            sizeEx = 0.04;
            autocomplete = "";
            onKeyDown = "if ((_this select 1) == 28) then { [] call comspec_overwatch_connect_fnc_submitChat; };";
        };

        class AirOpsButton: RscButton {
            idc = 1404;
            text = "Air Operations";
            x = 0.56 * safezoneW + safezoneX;
            y = 0.74 * safezoneH + safezoneY;
            w = 0.1 * safezoneW;
            h = 0.04 * safezoneH;
            action = "closeDialog 0; createDialog 'COMSPEC_FlightManifest_Dialog';";
        };
        class SubmitButton: RscButton {
            idc = 1403;
            text = "Envoyer";
            x = 0.68 * safezoneW + safezoneX;
            y = 0.74 * safezoneH + safezoneY;
            w = 0.06 * safezoneW;
            h = 0.04 * safezoneH;
            action = "[] call comspec_overwatch_connect_fnc_submitChat;";
        };
    };
};
