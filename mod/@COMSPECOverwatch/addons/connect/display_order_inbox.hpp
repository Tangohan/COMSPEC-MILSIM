// Boîte de réception des ordres C2 (idd 9975)
class COMSPEC_OrderInbox_Dialog {
    idd = 9975;
    movingEnable = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_OrderInbox_Display', _this select 0]; [] call comspec_overwatch_connect_fnc_orderInboxOnLoad;";
    onUnload = "uiNamespace setVariable ['COMSPEC_OrderInbox_Display', displayNull];";

    class Controls {
        class Background: RscText {
            idc = -1;
            x = 0.28 * safezoneW + safezoneX;
            y = 0.18 * safezoneH + safezoneY;
            w = 0.44 * safezoneW;
            h = 0.52 * safezoneH;
            colorBackground[] = {0.015, 0.04, 0.08, 0.96};
        };

        class AccentBar: RscText {
            idc = -1;
            x = 0.28 * safezoneW + safezoneX;
            y = 0.18 * safezoneH + safezoneY;
            w = 0.44 * safezoneW;
            h = 0.004 * safezoneH;
            colorBackground[] = {0.95, 0.55, 0.15, 0.9};
        };

        class Title: RscStructuredText {
            idc = -1;
            text = "<t font='RobotoCondensedBold' size='1' align='center' color='#e8f4f0'>Ordres reçus</t>";
            x = 0.3 * safezoneW + safezoneX;
            y = 0.195 * safezoneH + safezoneY;
            w = 0.4 * safezoneW;
            h = 0.03 * safezoneH;
        };

        class Hint: RscStructuredText {
            idc = 9402;
            text = "";
            x = 0.3 * safezoneW + safezoneX;
            y = 0.23 * safezoneH + safezoneY;
            w = 0.4 * safezoneW;
            h = 0.028 * safezoneH;
        };

        class OrderList: RscListBox {
            idc = 9401;
            x = 0.3 * safezoneW + safezoneX;
            y = 0.265 * safezoneH + safezoneY;
            w = 0.4 * safezoneW;
            h = 0.28 * safezoneH;
            colorBackground[] = {0, 0, 0, 0.45};
            colorText[] = {0.9, 0.92, 0.94, 1};
            sizeEx = 0.032;
        };

        class BtnAck: RscButton {
            idc = 9403;
            text = "Accuser réception";
            x = 0.3 * safezoneW + safezoneX;
            y = 0.56 * safezoneH + safezoneY;
            w = 0.125 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.08, 0.28, 0.32, 0.95};
            action = "['ACK'] call comspec_overwatch_connect_fnc_orderRespond;";
        };

        class BtnExec: RscButton {
            idc = 9404;
            text = "En cours";
            x = 0.435 * safezoneW + safezoneX;
            y = 0.56 * safezoneH + safezoneY;
            w = 0.1 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.08, 0.32, 0.28, 0.95};
            action = "['EXEC'] call comspec_overwatch_connect_fnc_orderRespond;";
        };

        class BtnFail: RscButton {
            idc = 9405;
            text = "Échec";
            x = 0.545 * safezoneW + safezoneX;
            y = 0.56 * safezoneH + safezoneY;
            w = 0.08 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.32, 0.12, 0.1, 0.95};
            action = "['FAILED'] call comspec_overwatch_connect_fnc_orderRespond;";
        };

        class BtnClose: RscButton {
            idc = 9406;
            text = "Fermer";
            x = 0.635 * safezoneW + safezoneX;
            y = 0.56 * safezoneH + safezoneY;
            w = 0.065 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.12, 0.08, 0.08, 0.95};
            action = "closeDialog 0;";
        };
    };
};
