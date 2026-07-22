class COMSPEC_Chat_Dialog {
    idd = 9999;
    movingEnable = 1;
    onLoad = "(_this select 0) call comspec_overwatch_connect_fnc_chatDialogOnLoad;";
    onUnload = "uiNamespace setVariable ['COMSPEC_Chat_Display', displayNull];";

    class Controls {
        class Background: RscText {
            idc = -1;
            x = 0.25 * safezoneW + safezoneX;
            y = 0.16 * safezoneH + safezoneY;
            w = 0.5 * safezoneW;
            h = 0.66 * safezoneH;
            colorBackground[] = {0.015, 0.04, 0.08, 0.96};
        };

        class AccentBar: RscText {
            idc = -1;
            x = 0.25 * safezoneW + safezoneX;
            y = 0.16 * safezoneH + safezoneY;
            w = 0.5 * safezoneW;
            h = 0.004 * safezoneH;
            colorBackground[] = {0.2, 0.85, 0.65, 0.9};
        };

        class Title: RscStructuredText {
            idc = -1;
            text = "<t font='RobotoCondensedBold' size='0.95' color='#e8f4f0'>Messagerie Overwatch</t>";
            x = 0.26 * safezoneW + safezoneX;
            y = 0.175 * safezoneH + safezoneY;
            w = 0.48 * safezoneW;
            h = 0.028 * safezoneH;
        };

        class VersionBadge: RscStructuredText {
            idc = 1397;
            text = "<t align='left' size='0.62' color='#8aa0b4'>Mod  —</t>";
            x = 0.26 * safezoneW + safezoneX;
            y = 0.205 * safezoneH + safezoneY;
            w = 0.2 * safezoneW;
            h = 0.02 * safezoneH;
        };

        class SyncBadge: RscStructuredText {
            idc = 1396;
            text = "<t align='right' size='0.62' color='#ff8a7a'>●  Hors liaison</t>";
            x = 0.48 * safezoneW + safezoneX;
            y = 0.205 * safezoneH + safezoneY;
            w = 0.26 * safezoneW;
            h = 0.02 * safezoneH;
        };

        class SyncDetail: RscStructuredText {
            idc = 1395;
            text = "<t align='center' size='0.55' color='#7a8c9e'>Position · —</t>";
            x = 0.26 * safezoneW + safezoneX;
            y = 0.226 * safezoneH + safezoneY;
            w = 0.48 * safezoneW;
            h = 0.018 * safezoneH;
        };

        class ServerUrl: RscText {
            idc = 1399;
            text = "Portail : —";
            x = 0.26 * safezoneW + safezoneX;
            y = 0.248 * safezoneH + safezoneY;
            w = 0.3 * safezoneW;
            h = 0.018 * safezoneH;
            sizeEx = 0.026;
            colorText[] = {0.65, 0.75, 0.85, 1};
        };

        class UserIp: RscText {
            idc = 1398;
            text = "Votre adresse : —";
            x = 0.56 * safezoneW + safezoneX;
            y = 0.248 * safezoneH + safezoneY;
            w = 0.18 * safezoneW;
            h = 0.018 * safezoneH;
            sizeEx = 0.026;
            colorText[] = {0.65, 0.75, 0.85, 1};
        };

        class ChatConsoleLabel: RscStructuredText {
            idc = -1;
            text = "<t color='#7dffb3' size='0.65'>Console</t>";
            x = 0.26 * safezoneW + safezoneX;
            y = 0.272 * safezoneH + safezoneY;
            w = 0.2 * safezoneW;
            h = 0.018 * safezoneH;
        };

        // Dépose un instantané technique (URL/tenant/état liaison/version) dans le journal —
        // catégorie "system", jamais masquable via les filtres ci-dessus.
        class DebugButton: RscButton {
            idc = 1414;
            text = "Debug";
            x = 0.65 * safezoneW + safezoneX;
            y = 0.272 * safezoneH + safezoneY;
            w = 0.1 * safezoneW;
            h = 0.018 * safezoneH;
            sizeEx = 0.022;
            colorBackground[] = {0.06, 0.1, 0.14, 0.9};
            action = "[] call comspec_overwatch_connect_fnc_showDebugInfo;";
        };

        class ChatConsole: RscEdit {
            idc = 1401;
            text = "";
            x = 0.26 * safezoneW + safezoneX;
            y = 0.292 * safezoneH + safezoneY;
            w = 0.48 * safezoneW;
            h = 0.16 * safezoneH;
            style = 16;
            colorBackground[] = {0, 0, 0, 0.45};
            colorText[] = {0.9, 0.92, 0.94, 1};
            font = "RobotoCondensed";
            sizeEx = 0.03;
        };

        class LogLabel: RscStructuredText {
            idc = -1;
            text = "<t color='#8aa0d8' size='0.65'>Journal de liaison</t>";
            x = 0.26 * safezoneW + safezoneX;
            y = 0.462 * safezoneH + safezoneY;
            w = 0.3 * safezoneW;
            h = 0.018 * safezoneH;
        };

        // Filtres du journal : masque/affiche une catégorie d'alertes, persistant par profil
        // (voir fn_toggleLogCategory.sqf). Le libellé initial est redessiné par
        // fn_chatDialogOnLoad selon l'état sauvegardé.
        class LogFilterLiaison: RscButton {
            idc = 1411;
            text = "Liaison : affiché";
            x = 0.56 * safezoneW + safezoneX;
            y = 0.462 * safezoneH + safezoneY;
            w = 0.06 * safezoneW;
            h = 0.018 * safezoneH;
            sizeEx = 0.022;
            colorBackground[] = {0.06, 0.1, 0.14, 0.9};
            action = "['liaison', 1411] call comspec_overwatch_connect_fnc_toggleLogCategory;";
        };

        class LogFilterCas: RscButton {
            idc = 1412;
            text = "CAS : affiché";
            x = 0.625 * safezoneW + safezoneX;
            y = 0.462 * safezoneH + safezoneY;
            w = 0.06 * safezoneW;
            h = 0.018 * safezoneH;
            sizeEx = 0.022;
            colorBackground[] = {0.06, 0.1, 0.14, 0.9};
            action = "['cas', 1412] call comspec_overwatch_connect_fnc_toggleLogCategory;";
        };

        class LogFilterMedical: RscButton {
            idc = 1413;
            text = "Médical : affiché";
            x = 0.69 * safezoneW + safezoneX;
            y = 0.462 * safezoneH + safezoneY;
            w = 0.06 * safezoneW;
            h = 0.018 * safezoneH;
            sizeEx = 0.022;
            colorBackground[] = {0.06, 0.1, 0.14, 0.9};
            action = "['medical', 1413] call comspec_overwatch_connect_fnc_toggleLogCategory;";
        };

        class LogWindow: RscEdit {
            idc = 1402;
            text = "";
            x = 0.26 * safezoneW + safezoneX;
            y = 0.482 * safezoneH + safezoneY;
            w = 0.48 * safezoneW;
            h = 0.18 * safezoneH;
            style = 16;
            colorBackground[] = {0, 0, 0, 0.5};
            colorText[] = {0.7, 0.74, 0.82, 1};
            font = "RobotoCondensed";
            sizeEx = 0.028;
        };

        class ChatInput: RscEdit {
            idc = 1400;
            text = "";
            x = 0.26 * safezoneW + safezoneX;
            y = 0.68 * safezoneH + safezoneY;
            w = 0.22 * safezoneW;
            h = 0.038 * safezoneH;
            colorBackground[] = {0.08, 0.1, 0.14, 0.95};
            colorText[] = {1, 1, 1, 1};
            font = "RobotoCondensed";
            sizeEx = 0.036;
            autocomplete = "";
            onKeyDown = "if ((_this select 1) == 28) then { [] call comspec_overwatch_connect_fnc_submitChat; };";
        };

        class PhotoButton: RscButton {
            idc = 1415;
            text = "Photo";
            x = 0.49 * safezoneW + safezoneX;
            y = 0.68 * safezoneH + safezoneY;
            w = 0.07 * safezoneW;
            h = 0.038 * safezoneH;
            colorBackground[] = {0.14, 0.18, 0.28, 0.95};
            action = "[] call comspec_overwatch_connect_fnc_submitChatPhoto;";
        };

        class AirOpsButton: RscButton {
            idc = 1404;
            text = "Ops. aériennes";
            x = 0.57 * safezoneW + safezoneX;
            y = 0.68 * safezoneH + safezoneY;
            w = 0.1 * safezoneW;
            h = 0.038 * safezoneH;
            colorBackground[] = {0.06, 0.14, 0.2, 0.95};
            action = "closeDialog 0; createDialog 'COMSPEC_FlightManifest_Dialog';";
        };

        class SubmitButton: RscButton {
            idc = 1403;
            text = "Envoyer";
            x = 0.68 * safezoneW + safezoneX;
            y = 0.68 * safezoneH + safezoneY;
            w = 0.06 * safezoneW;
            h = 0.038 * safezoneH;
            colorBackground[] = {0.08, 0.32, 0.28, 0.95};
            action = "[] call comspec_overwatch_connect_fnc_submitChat;";
        };
    };
};
