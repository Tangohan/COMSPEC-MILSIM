// Connexion compte Athena — code court et/ou Steam ID (idd 9972)
class COMSPEC_AccountLink_Dialog {
    idd = 9972;
    movingEnable = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_AccountLink_Display', _this select 0]; [] call comspec_overwatch_connect_fnc_accountLinkOnLoad;";
    onUnload = "uiNamespace setVariable ['COMSPEC_AccountLink_Display', displayNull];";

    class Controls {
        class Background: RscText {
            idc = -1;
            x = 0.32 * safezoneW + safezoneX;
            y = 0.18 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.56 * safezoneH;
            colorBackground[] = {0.015, 0.04, 0.08, 0.96};
        };

        class AccentBar: RscText {
            idc = -1;
            x = 0.32 * safezoneW + safezoneX;
            y = 0.18 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.004 * safezoneH;
            colorBackground[] = {0.2, 0.85, 0.65, 0.9};
        };

        class Title: RscStructuredText {
            idc = -1;
            text = "<t font='RobotoCondensedBold' size='1' align='center' color='#e8f4f0'>Connect my Athena account</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.195 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.03 * safezoneH;
        };

        class Hint: RscStructuredText {
            idc = -1;
            text = "<t align='center' size='0.55' color='#8aa0b4'>Paste your Steam ID (Athena profile), or leave it empty in multiplayer. Otherwise: In-game connection code (30 min).</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.228 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.048 * safezoneH;
        };

        class UrlLabel: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>PORTAL ADDRESS</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.285 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.018 * safezoneH;
        };

        class UrlEdit: RscEdit {
            idc = 9201;
            x = 0.34 * safezoneW + safezoneX;
            y = 0.305 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.032 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1};
            colorText[] = {0.9, 0.95, 0.95, 1};
            autocomplete = "";
        };

        class SteamLabel: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>STEAM ID (Athena profile)</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.348 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.018 * safezoneH;
        };

        class SteamEdit: RscEdit {
            idc = 9206;
            x = 0.34 * safezoneW + safezoneX;
            y = 0.368 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.032 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1};
            colorText[] = {0.95, 0.98, 0.9, 1};
            autocomplete = "";
        };

        class CodeLabel: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>PAIRING CODE (optional)</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.412 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.018 * safezoneH;
        };

        class CodeEdit: RscEdit {
            idc = 9202;
            x = 0.34 * safezoneW + safezoneX;
            y = 0.432 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1};
            colorText[] = {0.95, 0.98, 0.9, 1};
            sizeEx = 0.04;
            autocomplete = "";
        };

        class StatusText: RscStructuredText {
            idc = 9203;
            text = "";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.48 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.055 * safezoneH;
        };

        class BtnConnect: RscButton {
            idc = 9204;
            text = "Establish connection";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.55 * safezoneH + safezoneY;
            w = 0.195 * safezoneW;
            h = 0.038 * safezoneH;
            colorBackground[] = {0.08, 0.32, 0.28, 0.95};
            colorBackgroundActive[] = {0.12, 0.45, 0.38, 1};
            action = "[] spawn comspec_overwatch_connect_fnc_accountLinkSubmit;";
        };

        class BtnClose: RscButton {
            idc = 9205;
            text = "Close";
            x = 0.545 * safezoneW + safezoneX;
            y = 0.55 * safezoneH + safezoneY;
            w = 0.115 * safezoneW;
            h = 0.038 * safezoneH;
            colorBackground[] = {0.12, 0.08, 0.08, 0.95};
            colorBackgroundActive[] = {0.28, 0.12, 0.12, 1};
            action = "closeDialog 0;";
        };

        class Footer: RscStructuredText {
            idc = -1;
            text = "<t align='center' size='0.48' color='#4a5c6e'>In editor: paste Steam ID from Athena profile. In multiplayer: auto-detection possible.</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.605 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.035 * safezoneH;
        };
    };
};
