// Premier lancement : « Votre unité utilise-t-elle le service Athéna ? » (idd 9990, FR / EN).
// Oui : COMSPEC Link, synchro et liaison du compte. Non : ATAK local, aucune connexion ni donnée envoyée.
// Rouvert depuis Échap > Service Athéna ou téléphone ATAK > Réglages > Service Athéna.
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
            colorBackground[] = {0.01, 0.02, 0.04, 0.78};
        };
        class Panel: RscText {
            idc = -1;
            x = 0.2 * safezoneW + safezoneX;
            y = 0.17 * safezoneH + safezoneY;
            w = 0.6 * safezoneW;
            h = 0.66 * safezoneH;
            colorBackground[] = {0.015, 0.04, 0.08, 0.97};
        };
        class AccentBar: RscText {
            idc = -1;
            x = 0.2 * safezoneW + safezoneX;
            y = 0.17 * safezoneH + safezoneY;
            w = 0.6 * safezoneW;
            h = 0.004 * safezoneH;
            colorBackground[] = {0.2, 0.85, 0.65, 0.9};
        };
        class CardYes: RscText {
            idc = -1;
            x = 0.22 * safezoneW + safezoneX;
            y = 0.33 * safezoneH + safezoneY;
            w = 0.275 * safezoneW;
            h = 0.33 * safezoneH;
            colorBackground[] = {0.03, 0.09, 0.1, 1};
        };
        class CardYesBar: RscText {
            idc = -1;
            x = 0.22 * safezoneW + safezoneX;
            y = 0.33 * safezoneH + safezoneY;
            w = 0.275 * safezoneW;
            h = 0.003 * safezoneH;
            colorBackground[] = {0.2, 0.85, 0.65, 0.8};
        };
        class CardNo: RscText {
            idc = -1;
            x = 0.505 * safezoneW + safezoneX;
            y = 0.33 * safezoneH + safezoneY;
            w = 0.275 * safezoneW;
            h = 0.33 * safezoneH;
            colorBackground[] = {0.05, 0.06, 0.08, 1};
        };
        class CardNoBar: RscText {
            idc = -1;
            x = 0.505 * safezoneW + safezoneX;
            y = 0.33 * safezoneH + safezoneY;
            w = 0.275 * safezoneW;
            h = 0.003 * safezoneH;
            colorBackground[] = {0.91, 0.72, 0.29, 0.8};
        };
    };

    class Controls {
        class Title: RscStructuredText {
            idc = 9462;
            text = "";
            x = 0.22 * safezoneW + safezoneX;
            y = 0.185 * safezoneH + safezoneY;
            w = 0.44 * safezoneW;
            h = 0.036 * safezoneH;
        };
        class BtnLangFR: COMSPEC_RscButton {
            idc = 9463;
            text = "FR";
            x = 0.685 * safezoneW + safezoneX;
            y = 0.187 * safezoneH + safezoneY;
            w = 0.045 * safezoneW;
            h = 0.028 * safezoneH;
            sizeEx = 0.032;
            action = "['fr'] call comspec_overwatch_connect_fnc_athenaChoiceLanguage;";
        };
        class BtnLangEN: COMSPEC_RscButton {
            idc = 9464;
            text = "EN";
            x = 0.735 * safezoneW + safezoneX;
            y = 0.187 * safezoneH + safezoneY;
            w = 0.045 * safezoneW;
            h = 0.028 * safezoneH;
            sizeEx = 0.032;
            action = "['en'] call comspec_overwatch_connect_fnc_athenaChoiceLanguage;";
        };
        class Question: RscStructuredText {
            idc = 9465;
            text = "";
            x = 0.22 * safezoneW + safezoneX;
            y = 0.235 * safezoneH + safezoneY;
            w = 0.56 * safezoneW;
            h = 0.09 * safezoneH;
        };
        class YesText: RscStructuredText {
            idc = 9466;
            text = "";
            x = 0.228 * safezoneW + safezoneX;
            y = 0.342 * safezoneH + safezoneY;
            w = 0.26 * safezoneW;
            h = 0.25 * safezoneH;
            size = "(((((safezoneW / safezoneH) min 1.2) / 1.2) / 25) * 0.9)";
        };
        class BtnYes: COMSPEC_RscButtonAccent {
            idc = 9467;
            text = "OUI";
            x = 0.228 * safezoneW + safezoneX;
            y = 0.6 * safezoneH + safezoneY;
            w = 0.259 * safezoneW;
            h = 0.048 * safezoneH;
            sizeEx = 0.038;
            action = "['on'] call comspec_overwatch_connect_fnc_athenaChoicePick;";
        };
        class NoText: RscStructuredText {
            idc = 9468;
            text = "";
            x = 0.513 * safezoneW + safezoneX;
            y = 0.342 * safezoneH + safezoneY;
            w = 0.26 * safezoneW;
            h = 0.25 * safezoneH;
            size = "(((((safezoneW / safezoneH) min 1.2) / 1.2) / 25) * 0.9)";
        };
        class BtnNo: COMSPEC_RscButton {
            idc = 9469;
            text = "NON";
            x = 0.513 * safezoneW + safezoneX;
            y = 0.6 * safezoneH + safezoneY;
            w = 0.259 * safezoneW;
            h = 0.048 * safezoneH;
            sizeEx = 0.038;
            action = "['off'] call comspec_overwatch_connect_fnc_athenaChoicePick;";
        };
        class Footer: RscStructuredText {
            idc = 9470;
            text = "";
            x = 0.22 * safezoneW + safezoneX;
            y = 0.675 * safezoneH + safezoneY;
            w = 0.56 * safezoneW;
            h = 0.06 * safezoneH;
        };
        class Current: RscStructuredText {
            idc = 9471;
            text = "";
            x = 0.22 * safezoneW + safezoneX;
            y = 0.765 * safezoneH + safezoneY;
            w = 0.38 * safezoneW;
            h = 0.03 * safezoneH;
        };
        class BtnClose: COMSPEC_RscButton {
            idc = 9472;
            text = "Fermer";
            x = 0.64 * safezoneW + safezoneX;
            y = 0.76 * safezoneH + safezoneY;
            w = 0.14 * safezoneW;
            h = 0.04 * safezoneH;
            sizeEx = 0.034;
            action = "private _d = uiNamespace getVariable ['COMSPEC_AthenaChoice_Display', displayNull]; if (!isNull _d) then { _d closeDisplay 2; };";
        };
    };
};
