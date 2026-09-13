// Boîte de réception des ordres C2 (idd 9975) — réponses opérateur
class COMSPEC_OrderInbox_Dialog {
    idd = 9975;
    movingEnable = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_OrderInbox_Display', _this select 0]; [] call comspec_overwatch_connect_fnc_orderInboxOnLoad;";
    onUnload = "uiNamespace setVariable ['COMSPEC_OrderInbox_Display', displayNull];";

    class Controls {
        class Background: RscText {
            idc = -1;
            x = 0.28 * safezoneW + safezoneX;
            y = 0.14 * safezoneH + safezoneY;
            w = 0.44 * safezoneW;
            h = 0.64 * safezoneH;
            colorBackground[] = {0.015, 0.04, 0.08, 0.96};
        };

        class AccentBar: RscText {
            idc = -1;
            x = 0.28 * safezoneW + safezoneX;
            y = 0.14 * safezoneH + safezoneY;
            w = 0.44 * safezoneW;
            h = 0.004 * safezoneH;
            colorBackground[] = {0.2, 0.85, 0.65, 0.9};
        };

        class Title: RscStructuredText {
            idc = -1;
            text = "<t font='RobotoCondensedBold' size='1' align='center' color='#e8f4f0'>Ordres reçus</t>";
            x = 0.3 * safezoneW + safezoneX;
            y = 0.155 * safezoneH + safezoneY;
            w = 0.4 * safezoneW;
            h = 0.03 * safezoneH;
        };

        class Hint: RscStructuredText {
            idc = 9402;
            text = "";
            x = 0.3 * safezoneW + safezoneX;
            y = 0.19 * safezoneH + safezoneY;
            w = 0.4 * safezoneW;
            h = 0.028 * safezoneH;
        };

        class OrderList: RscListBox {
            idc = 9401;
            x = 0.3 * safezoneW + safezoneX;
            y = 0.225 * safezoneH + safezoneY;
            w = 0.4 * safezoneW;
            h = 0.24 * safezoneH;
            colorBackground[] = {0, 0, 0, 0.45};
            colorText[] = {0.9, 0.92, 0.94, 1};
            sizeEx = 0.032;
        };

        class NoteLabel: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>MOTIF OU MESSAGE (obligatoire pour refus et proposition)</t>";
            x = 0.3 * safezoneW + safezoneX;
            y = 0.475 * safezoneH + safezoneY;
            w = 0.4 * safezoneW;
            h = 0.02 * safezoneH;
        };

        class NoteEdit: RscEdit {
            idc = 9403;
            text = "";
            x = 0.3 * safezoneW + safezoneX;
            y = 0.498 * safezoneH + safezoneY;
            w = 0.4 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1};
            colorText[] = {0.95, 0.98, 0.9, 1};
            sizeEx = 0.034;
            autocomplete = "";
        };

        class BtnAccept: RscButton {
            idc = 9407;
            text = "Accepter";
            x = 0.3 * safezoneW + safezoneX;
            y = 0.55 * safezoneH + safezoneY;
            w = 0.19 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.08, 0.32, 0.28, 0.95};
            action = "['ACCEPT'] call comspec_overwatch_connect_fnc_orderRespond;";
        };

        class BtnRefuse: RscButton {
            idc = 9408;
            text = "Refuser";
            x = 0.51 * safezoneW + safezoneX;
            y = 0.55 * safezoneH + safezoneY;
            w = 0.19 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.32, 0.12, 0.1, 0.95};
            action = "['REFUSE'] call comspec_overwatch_connect_fnc_orderRespond;";
        };

        class BtnStandby: RscButton {
            idc = 9409;
            text = "En attente";
            x = 0.3 * safezoneW + safezoneX;
            y = 0.596 * safezoneH + safezoneY;
            w = 0.19 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.22, 0.18, 0.08, 0.95};
            action = "['STANDBY'] call comspec_overwatch_connect_fnc_orderRespond;";
        };

        class BtnCounter: RscButton {
            idc = 9410;
            text = "Proposition de changement";
            x = 0.51 * safezoneW + safezoneX;
            y = 0.596 * safezoneH + safezoneY;
            w = 0.19 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.08, 0.22, 0.32, 0.95};
            action = "['COUNTER'] call comspec_overwatch_connect_fnc_orderRespond;";
        };

        class BtnClose: RscButton {
            idc = 9406;
            text = "Fermer";
            x = 0.3 * safezoneW + safezoneX;
            y = 0.65 * safezoneH + safezoneY;
            w = 0.4 * safezoneW;
            h = 0.034 * safezoneH;
            colorBackground[] = {0.12, 0.08, 0.08, 0.95};
            action = "closeDialog 0;";
        };
    };
};
