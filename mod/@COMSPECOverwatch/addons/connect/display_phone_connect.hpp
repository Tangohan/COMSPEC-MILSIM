class COMSPEC_PhoneConnect_Dialog {
    idd = 9971;
    movingEnable = 1;
    onLoad = "";

    class Controls {
        class Background: RscText {
            idc = -1;
            x = 0.32 * safezoneW + safezoneX;
            y = 0.14 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.62 * safezoneH;
            colorBackground[] = {0.02, 0.05, 0.1, 0.96};
        };

        class Title: RscStructuredText {
            idc = -1;
            text = "<t font='RobotoCondensedBold' size='0.85' align='center'>Connexion téléphone</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.155 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.03 * safezoneH;
        };

        class Hint: RscStructuredText {
            idc = -1;
            text = "<t align='center' size='0.65'>Scannez le QR avec votre téléphone, ou saisissez le code sur la page de connexion.</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.185 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.045 * safezoneH;
            colorText[] = {0.7, 0.8, 0.9, 1};
        };

        class QrPicture: RscPicture {
            idc = 9021;
            text = "";
            x = 0.42 * safezoneW + safezoneX;
            y = 0.235 * safezoneH + safezoneY;
            w = 0.16 * safezoneW;
            h = 0.32 * safezoneH;
            colorBackground[] = {1, 1, 1, 1};
        };

        class CodeLabel: RscStructuredText {
            idc = -1;
            text = "<t align='center' size='0.55'>CODE</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.565 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.02 * safezoneH;
            colorText[] = {0.55, 0.68, 0.6, 1};
        };

        class CodeText: RscStructuredText {
            idc = 9022;
            text = "——————";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.585 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.045 * safezoneH;
            size = 1.1;
        };

        class UrlText: RscStructuredText {
            idc = 9023;
            text = "";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.635 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.03 * safezoneH;
            size = 0.55;
            colorText[] = {0.6, 0.7, 0.8, 1};
        };

        class RefreshButton: RscButton {
            idc = 9024;
            text = "Nouveau code";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.685 * safezoneH + safezoneY;
            w = 0.15 * safezoneW;
            h = 0.04 * safezoneH;
            action = "[] call comspec_overwatch_connect_fnc_phoneConnectShow;";
        };

        class CloseButton: RscButton {
            idc = 9025;
            text = "Fermer";
            x = 0.51 * safezoneW + safezoneX;
            y = 0.685 * safezoneH + safezoneY;
            w = 0.15 * safezoneW;
            h = 0.04 * safezoneH;
            action = "closeDialog 0;";
        };
    };
};
