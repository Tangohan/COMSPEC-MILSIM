// Connexion compte Athena — saisie du code de liaison (idd 9972)
class COMSPEC_AccountLink_Dialog {
    idd = 9972;
    movingEnable = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_AccountLink_Display', _this select 0]; [] call comspec_overwatch_connect_fnc_accountLinkOnLoad;";
    onUnload = "uiNamespace setVariable ['COMSPEC_AccountLink_Display', displayNull];";

    class Controls {
        class Background: RscText {
            idc = -1;
            x = 0.32 * safezoneW + safezoneX;
            y = 0.22 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.48 * safezoneH;
            colorBackground[] = {0.015, 0.04, 0.08, 0.96};
        };

        class AccentBar: RscText {
            idc = -1;
            x = 0.32 * safezoneW + safezoneX;
            y = 0.22 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.004 * safezoneH;
            colorBackground[] = {0.2, 0.85, 0.65, 0.9};
        };

        class Title: RscStructuredText {
            idc = -1;
            text = "<t font='RobotoCondensedBold' size='1' align='center' color='#e8f4f0'>Connecter mon compte Athena</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.24 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.032 * safezoneH;
        };

        class Hint: RscStructuredText {
            idc = -1;
            text = "<t align='center' size='0.62' color='#8aa0b4'>Sur Athena, ouvrez ATAK → Connexion en jeu → Générer un code. Saisissez-le ci-dessous (valable 15 min).</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.278 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.055 * safezoneH;
        };

        class UrlLabel: RscStructuredText {
            idc = -1;
            text = "<t size='0.58' color='#5a9e88'>ADRESSE DU PORTAIL</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.345 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.02 * safezoneH;
        };

        class UrlEdit: RscEdit {
            idc = 9201;
            x = 0.34 * safezoneW + safezoneX;
            y = 0.368 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1};
            colorText[] = {0.9, 0.95, 0.95, 1};
            autocomplete = "";
        };

        class CodeLabel: RscStructuredText {
            idc = -1;
            text = "<t size='0.58' color='#5a9e88'>CODE DE LIAISON</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.42 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.02 * safezoneH;
        };

        class CodeEdit: RscEdit {
            idc = 9202;
            x = 0.34 * safezoneW + safezoneX;
            y = 0.443 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.04 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1};
            colorText[] = {0.95, 0.98, 0.9, 1};
            sizeEx = 0.045;
            autocomplete = "";
        };

        class StatusText: RscStructuredText {
            idc = 9203;
            text = "";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.5 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.05 * safezoneH;
        };

        class BtnConnect: RscButton {
            idc = 9204;
            text = "Établir la liaison";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.57 * safezoneH + safezoneY;
            w = 0.195 * safezoneW;
            h = 0.04 * safezoneH;
            colorBackground[] = {0.08, 0.32, 0.28, 0.95};
            colorBackgroundActive[] = {0.12, 0.45, 0.38, 1};
            action = "[] spawn comspec_overwatch_connect_fnc_accountLinkSubmit;";
        };

        class BtnClose: RscButton {
            idc = 9205;
            text = "Fermer";
            x = 0.545 * safezoneW + safezoneX;
            y = 0.57 * safezoneH + safezoneY;
            w = 0.115 * safezoneW;
            h = 0.04 * safezoneH;
            colorBackground[] = {0.12, 0.08, 0.08, 0.95};
            colorBackgroundActive[] = {0.28, 0.12, 0.12, 1};
            action = "closeDialog 0;";
        };

        class Footer: RscStructuredText {
            idc = -1;
            text = "<t align='center' size='0.5' color='#4a5c6e'>Aucun mot de passe saisi en jeu — code à usage unique uniquement.</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.63 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.03 * safezoneH;
        };
    };
};
