// Connexion Athena — code du portail (prioritaire) + Steam / URL en secours (idd 9972)
class COMSPEC_AccountLink_Dialog {
    idd = 9972;
    movingEnable = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_AccountLink_Display', _this select 0]; [] call comspec_overwatch_connect_fnc_accountLinkOnLoad;";
    onUnload = "uiNamespace setVariable ['COMSPEC_AccountLink_Display', displayNull]; missionNamespace setVariable ['COMSPEC_AccountLink_StatusToken', -1, false];";

    class Controls {
        class Background: RscText {
            idc = -1;
            x = 0.32 * safezoneW + safezoneX;
            y = 0.14 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.66 * safezoneH;
            colorBackground[] = {0.015, 0.04, 0.08, 0.96};
        };

        class AccentBar: RscText {
            idc = -1;
            x = 0.32 * safezoneW + safezoneX;
            y = 0.14 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.004 * safezoneH;
            colorBackground[] = {0.2, 0.85, 0.65, 0.9};
        };

        class Title: RscStructuredText {
            idc = -1;
            text = "<t font='RobotoCondensedBold' size='1' align='center' color='#e8f4f0'>Lier le jeu à Athena</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.155 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.028 * safezoneH;
        };

        class Hint: RscStructuredText {
            idc = -1;
            text = "<t align='center' size='0.55' color='#8aa0b4'>Sur le portail Athena : Appairer → Générer un code. Collez uniquement ce code ci-dessous (pas l’adresse du site).</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.185 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.042 * safezoneH;
        };

        class StatusBarBg: RscText {
            idc = -1;
            x = 0.34 * safezoneW + safezoneX;
            y = 0.232 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.048 * safezoneH;
            colorBackground[] = {0.03, 0.07, 0.11, 1};
        };

        class StatusBar: RscStructuredText {
            idc = 9207;
            text = "<t align='left' size='0.55' color='#7a8c9e'>Transmission · en attente…</t>";
            x = 0.345 * safezoneW + safezoneX;
            y = 0.236 * safezoneH + safezoneY;
            w = 0.31 * safezoneW;
            h = 0.042 * safezoneH;
        };

        class CodeLabel: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>CODE DU PORTAIL (Appairer → Générer un code)</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.290 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.018 * safezoneH;
        };

        class CodeEdit: RscEdit {
            idc = 9202;
            x = 0.34 * safezoneW + safezoneX;
            y = 0.310 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.034 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1};
            colorText[] = {0.95, 0.98, 0.9, 1};
            sizeEx = 0.04;
            autocomplete = "";
        };

        class SteamLabel: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>IDENTIFIANT STEAM (si déjà lié au profil, code optionnel)</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.355 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.018 * safezoneH;
        };

        class SteamEdit: RscEdit {
            idc = 9206;
            x = 0.34 * safezoneW + safezoneX;
            y = 0.375 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.030 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1};
            colorText[] = {0.95, 0.98, 0.9, 1};
            autocomplete = "";
        };

        class UrlLabel: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>ADRESSE DU PORTAIL (laisser tel quel sauf consigne)</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.415 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.018 * safezoneH;
        };

        class UrlEdit: RscEdit {
            idc = 9201;
            x = 0.34 * safezoneW + safezoneX;
            y = 0.435 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.030 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1};
            colorText[] = {0.9, 0.95, 0.95, 1};
            autocomplete = "";
        };

        class StatusText: RscStructuredText {
            idc = 9203;
            text = "";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.475 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.050 * safezoneH;
        };

        class BtnConnect: RscButton {
            idc = 9204;
            text = "Valider la liaison";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.540 * safezoneH + safezoneY;
            w = 0.195 * safezoneW;
            h = 0.038 * safezoneH;
            colorBackground[] = {0.08, 0.32, 0.28, 0.95};
            colorBackgroundActive[] = {0.12, 0.45, 0.38, 1};
            action = "[] spawn comspec_overwatch_connect_fnc_accountLinkSubmit;";
        };

        class BtnClose: RscButton {
            idc = 9205;
            text = "Fermer";
            x = 0.545 * safezoneW + safezoneX;
            y = 0.540 * safezoneH + safezoneY;
            w = 0.115 * safezoneW;
            h = 0.038 * safezoneH;
            colorBackground[] = {0.12, 0.08, 0.08, 0.95};
            colorBackgroundActive[] = {0.28, 0.12, 0.12, 1};
            action = "private _d = uiNamespace getVariable ['COMSPEC_AccountLink_Display', displayNull]; if (!isNull _d) then { _d closeDisplay 1; } else { closeDialog 0; };";
        };

        class Footer: RscStructuredText {
            idc = -1;
            text = "<t align='center' size='0.48' color='#4a5c6e'>Portail : carte ATAK → Appairer → Générer un code. Si Steam est déjà lié au profil, vous pouvez laisser le code vide.</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.595 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.040 * safezoneH;
        };

        class TxHint: RscStructuredText {
            idc = -1;
            text = "<t align='center' size='0.45' color='#3a4c5e'>La barre du haut indique l’état de transmission et le délai (ms) vers Athena.</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.640 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.028 * safezoneH;
        };
    };
};
