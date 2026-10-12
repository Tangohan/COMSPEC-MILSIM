// Premier lancement : « Votre unité utilise-t-elle le service Athéna ? » (idd 9990, FR / EN).
// Oui : COMSPEC Link, synchro et liaison du compte. Non : ATAK local, aucune connexion ni donnée envoyée.
// Rouvert depuis Échap > Service Athéna ou téléphone ATAK > Réglages > Service Athéna.
// Images : img\athena_choice (aigle et fond de l'ATAK natif, icônes COMSPEC Link / hors ligne / réglages).

// Carte entière cliquable : léger éclaircissement au survol.
class COMSPEC_AthenaChoice_CardHit: COMSPEC_RscButton {
    shadow = 0;
    text = "";
    colorText[] = {0, 0, 0, 0};
    colorBackground[] = {0, 0, 0, 0};
    colorBackgroundActive[] = {1, 1, 1, 0.045};
    colorFocused[] = {0, 0, 0, 0};
    colorShadow[] = {0, 0, 0, 0};
};

class COMSPEC_AthenaChoice_Dialog {
    idd = 9990;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_AthenaChoice_Display', _this select 0]; (_this select 0) call comspec_overwatch_connect_fnc_athenaChoiceOnLoad;";
    onUnload = "uiNamespace setVariable ['COMSPEC_AthenaChoice_Display', displayNull];";

    class ControlsBackground {
        class Dimmer: RscText {
            idc = -1;
            x = "safezoneXAbs";
            y = "safezoneY";
            w = "safezoneWAbs";
            h = "safezoneH";
            colorBackground[] = {0.01, 0.02, 0.03, 0.82};
        };
        class PanelBase: RscText {
            idc = -1;
            x = 0.22 * safezoneW + safezoneX;
            y = 0.16 * safezoneH + safezoneY;
            w = 0.56 * safezoneW;
            h = 0.645 * safezoneH;
            colorBackground[] = {0.01, 0.02, 0.02, 1};
        };
        // Fond de l'ATAK (aigle flouté, lueurs vertes).
        class PanelWall: RscPicture {
            idc = -1;
            text = "\z\comspec_overwatch\addons\connect\img\athena_choice\wall_athena_blur_land.paa";
            x = 0.22 * safezoneW + safezoneX;
            y = 0.16 * safezoneH + safezoneY;
            w = 0.56 * safezoneW;
            h = 0.645 * safezoneH;
            colorText[] = {1, 1, 1, 0.9};
        };
        class PanelShade: RscText {
            idc = -1;
            x = 0.22 * safezoneW + safezoneX;
            y = 0.16 * safezoneH + safezoneY;
            w = 0.56 * safezoneW;
            h = 0.645 * safezoneH;
            colorBackground[] = {0.01, 0.025, 0.03, 0.55};
        };
        class AccentBar: RscText {
            idc = -1;
            x = 0.22 * safezoneW + safezoneX;
            y = 0.16 * safezoneH + safezoneY;
            w = 0.56 * safezoneW;
            h = 0.004 * safezoneH;
            colorBackground[] = {0.2, 0.85, 0.65, 0.95};
        };
        class Separator: RscText {
            idc = -1;
            x = 0.235 * safezoneW + safezoneX;
            y = 0.244 * safezoneH + safezoneY;
            w = 0.53 * safezoneW;
            h = 0.0015 * safezoneH;
            colorBackground[] = {0.55, 0.65, 0.7, 0.18};
        };
        class CardYes: RscText {
            idc = -1;
            x = 0.235 * safezoneW + safezoneX;
            y = 0.37 * safezoneH + safezoneY;
            w = 0.26 * safezoneW;
            h = 0.33 * safezoneH;
            colorBackground[] = {0.02, 0.09, 0.08, 0.88};
        };
        class CardYesBar: RscText {
            idc = -1;
            x = 0.235 * safezoneW + safezoneX;
            y = 0.37 * safezoneH + safezoneY;
            w = 0.26 * safezoneW;
            h = 0.004 * safezoneH;
            colorBackground[] = {0.2, 0.85, 0.65, 0.9};
        };
        class CardNo: RscText {
            idc = -1;
            x = 0.505 * safezoneW + safezoneX;
            y = 0.37 * safezoneH + safezoneY;
            w = 0.26 * safezoneW;
            h = 0.33 * safezoneH;
            colorBackground[] = {0.07, 0.065, 0.05, 0.88};
        };
        class CardNoBar: RscText {
            idc = -1;
            x = 0.505 * safezoneW + safezoneX;
            y = 0.37 * safezoneH + safezoneY;
            w = 0.26 * safezoneW;
            h = 0.004 * safezoneH;
            colorBackground[] = {0.91, 0.72, 0.29, 0.9};
        };
    };

    class Controls {
        // En-tête : aigle de l'ATAK, nom, sous-titre, langue.
        class Logo: RscPictureKeepAspect {
            idc = -1;
            text = "\z\comspec_overwatch\addons\connect\img\athena_choice\logo_atak.paa";
            x = 0.235 * safezoneW + safezoneX;
            y = 0.176 * safezoneH + safezoneY;
            w = 0.034 * safezoneW;
            h = 0.058 * safezoneH;
            colorText[] = {0.92, 0.97, 0.95, 1};
        };
        class Title: RscStructuredText {
            idc = 9462;
            text = "";
            x = 0.272 * safezoneW + safezoneX;
            y = 0.177 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.034 * safezoneH;
        };
        class Subtitle: RscStructuredText {
            idc = 9475;
            text = "";
            x = 0.272 * safezoneW + safezoneX;
            y = 0.207 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.028 * safezoneH;
        };
        class BtnLangFR: COMSPEC_RscButton {
            idc = 9463;
            text = "FR";
            x = 0.683 * safezoneW + safezoneX;
            y = 0.19 * safezoneH + safezoneY;
            w = 0.039 * safezoneW;
            h = 0.03 * safezoneH;
            sizeEx = 0.03;
            action = "['fr'] call comspec_overwatch_connect_fnc_athenaChoiceLanguage;";
        };
        class BtnLangEN: BtnLangFR {
            idc = 9464;
            text = "EN";
            x = 0.726 * safezoneW + safezoneX;
            action = "['en'] call comspec_overwatch_connect_fnc_athenaChoiceLanguage;";
        };
        class Question: RscStructuredText {
            idc = 9465;
            text = "";
            x = 0.235 * safezoneW + safezoneX;
            y = 0.258 * safezoneH + safezoneY;
            w = 0.53 * safezoneW;
            h = 0.105 * safezoneH;
        };

        // Carte OUI.
        class YesIcon: RscPictureKeepAspect {
            idc = -1;
            text = "\z\comspec_overwatch\addons\connect\img\athena_choice\ui_comspec_link.paa";
            x = 0.247 * safezoneW + safezoneX;
            y = 0.389 * safezoneH + safezoneY;
            w = 0.025 * safezoneW;
            h = 0.044 * safezoneH;
            colorText[] = {0.36, 0.78, 0.63, 1};
        };
        class YesTitle: RscStructuredText {
            idc = 9473;
            text = "";
            x = 0.278 * safezoneW + safezoneX;
            y = 0.392 * safezoneH + safezoneY;
            w = 0.212 * safezoneW;
            h = 0.04 * safezoneH;
        };
        class YesText: RscStructuredText {
            idc = 9466;
            text = "";
            x = 0.247 * safezoneW + safezoneX;
            y = 0.447 * safezoneH + safezoneY;
            w = 0.24 * safezoneW;
            h = 0.18 * safezoneH;
        };
        class BtnYes: COMSPEC_RscButtonAccent {
            idc = 9467;
            text = "OUI";
            x = 0.247 * safezoneW + safezoneX;
            y = 0.638 * safezoneH + safezoneY;
            w = 0.236 * safezoneW;
            h = 0.048 * safezoneH;
            sizeEx = 0.04;
            colorBackground[] = {0.1, 0.42, 0.33, 0.95};
            colorBackgroundActive[] = {0.14, 0.55, 0.43, 1};
            colorFocused[] = {0.14, 0.55, 0.43, 1};
            action = "['on'] call comspec_overwatch_connect_fnc_athenaChoicePick;";
        };

        // Carte NON.
        class NoIcon: YesIcon {
            text = "\z\comspec_overwatch\addons\connect\img\athena_choice\mode_local.paa";
            x = 0.517 * safezoneW + safezoneX;
            colorText[] = {0.91, 0.72, 0.29, 1};
        };
        class NoTitle: YesTitle {
            idc = 9474;
            x = 0.548 * safezoneW + safezoneX;
        };
        class NoText: YesText {
            idc = 9468;
            x = 0.517 * safezoneW + safezoneX;
        };
        class BtnNo: COMSPEC_RscButton {
            idc = 9469;
            text = "NON";
            x = 0.517 * safezoneW + safezoneX;
            y = 0.638 * safezoneH + safezoneY;
            w = 0.236 * safezoneW;
            h = 0.048 * safezoneH;
            sizeEx = 0.04;
            colorBackground[] = {0.3, 0.23, 0.08, 0.95};
            colorBackgroundActive[] = {0.42, 0.32, 0.1, 1};
            colorFocused[] = {0.42, 0.32, 0.1, 1};
            action = "['off'] call comspec_overwatch_connect_fnc_athenaChoicePick;";
        };
        // Toute la carte répond au clic (posée au-dessus du texte).
        class HitYes: COMSPEC_AthenaChoice_CardHit {
            idc = 9476;
            x = 0.235 * safezoneW + safezoneX;
            y = 0.37 * safezoneH + safezoneY;
            w = 0.26 * safezoneW;
            h = 0.33 * safezoneH;
            action = "['on'] call comspec_overwatch_connect_fnc_athenaChoicePick;";
        };
        class HitNo: HitYes {
            idc = 9477;
            x = 0.505 * safezoneW + safezoneX;
            action = "['off'] call comspec_overwatch_connect_fnc_athenaChoicePick;";
        };

        // Pied : où changer d'avis, choix actuel, Fermer (seulement quand on rouvre l'écran).
        class FooterIcon: RscPictureKeepAspect {
            idc = -1;
            text = "\z\comspec_overwatch\addons\connect\img\athena_choice\app_settings.paa";
            x = 0.235 * safezoneW + safezoneX;
            y = 0.721 * safezoneH + safezoneY;
            w = 0.013 * safezoneW;
            h = 0.023 * safezoneH;
            colorText[] = {0.54, 0.63, 0.71, 1};
        };
        class Footer: RscStructuredText {
            idc = 9470;
            text = "";
            x = 0.252 * safezoneW + safezoneX;
            y = 0.717 * safezoneH + safezoneY;
            w = 0.513 * safezoneW;
            h = 0.032 * safezoneH;
        };
        class Current: RscStructuredText {
            idc = 9471;
            text = "";
            x = 0.235 * safezoneW + safezoneX;
            y = 0.76 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.03 * safezoneH;
        };
        class BtnClose: COMSPEC_RscButton {
            idc = 9472;
            text = "Fermer";
            x = 0.665 * safezoneW + safezoneX;
            y = 0.756 * safezoneH + safezoneY;
            w = 0.1 * safezoneW;
            h = 0.036 * safezoneH;
            sizeEx = 0.032;
            action = "private _d = uiNamespace getVariable ['COMSPEC_AthenaChoice_Display', displayNull]; if (!isNull _d) then { _d closeDisplay 2; };";
        };
    };
};
