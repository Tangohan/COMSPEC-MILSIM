// Signalement joueur d’un problème Overwatch (idd 9989)
class COMSPEC_BugReport_Dialog {
    idd = 9989;
    movingEnable = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_BugReport_Display', _this select 0];";
    onUnload = "uiNamespace setVariable ['COMSPEC_BugReport_Display', displayNull];";

    class Controls {
        class Background: RscText {
            idc = -1;
            x = 0.30 * safezoneW + safezoneX;
            y = 0.22 * safezoneH + safezoneY;
            w = 0.40 * safezoneW;
            h = 0.48 * safezoneH;
            colorBackground[] = {0.015, 0.04, 0.08, 0.96};
        };
        class AccentBar: RscText {
            idc = -1;
            x = 0.30 * safezoneW + safezoneX;
            y = 0.22 * safezoneH + safezoneY;
            w = 0.40 * safezoneW;
            h = 0.004 * safezoneH;
            colorBackground[] = {0.2, 0.65, 0.55, 0.9};
        };
        class Title: RscStructuredText {
            idc = -1;
            text = "<t font='RobotoCondensedBold' size='1' align='center' color='#e8f4f0'>Signaler un problème</t>";
            x = 0.32 * safezoneW + safezoneX;
            y = 0.235 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.03 * safezoneH;
        };
        class Hint: RscStructuredText {
            idc = -1;
            text = "<t align='center' size='0.55' color='#8aa0b4'>Décrivez ce qui ne fonctionne pas. Le rapport est envoyé à l’équipe Athena.</t>";
            x = 0.32 * safezoneW + safezoneX;
            y = 0.270 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.035 * safezoneH;
        };
        class LabelCat: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>CATÉGORIE</t>";
            x = 0.32 * safezoneW + safezoneX; y = 0.315 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.018 * safezoneH;
        };
        class ComboCat: RscCombo {
            idc = 9801;
            x = 0.32 * safezoneW + safezoneX; y = 0.333 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.032 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1};
            colorSelectBackground[] = {0.08, 0.28, 0.26, 1};
            colorText[] = {0.95, 0.98, 0.9, 1};
            sizeEx = 0.032;
        };
        class LabelMsg: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>DESCRIPTION</t>";
            x = 0.32 * safezoneW + safezoneX; y = 0.380 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.018 * safezoneH;
        };
        class EditMsg: RscEdit {
            idc = 9802;
            x = 0.32 * safezoneW + safezoneX; y = 0.398 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.090 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1}; colorText[] = {0.95, 0.98, 0.9, 1}; sizeEx = 0.032; autocomplete = "";
            style = 16; // multi-line
        };
        class ChkLog: RscButton {
            idc = 9805;
            text = "Journal de session : oui";
            x = 0.32 * safezoneW + safezoneX;
            y = 0.495 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.028 * safezoneH;
            colorBackground[] = {0.04, 0.10, 0.14, 0.85};
            colorBackgroundActive[] = {0.08, 0.28, 0.24, 1};
            sizeEx = 0.030;
            tooltip = "Envoie les dernières lignes du journal Overwatch (sans clé ni mot de passe).";
            action = "private _d = uiNamespace getVariable ['COMSPEC_BugReport_Display', displayNull]; if (isNull _d) exitWith {}; private _c = _d displayCtrl 9805; private _on = _c getVariable ['COMSPEC_LogAttach', true]; _on = !_on; _c setVariable ['COMSPEC_LogAttach', _on]; _c ctrlSetText (if (_on) then {'Journal de session : oui'} else {'Journal de session : non'});";
        };
        class BtnSend: RscButton {
            idc = 9803;
            text = "Envoyer";
            x = 0.32 * safezoneW + safezoneX;
            y = 0.545 * safezoneH + safezoneY;
            w = 0.20 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.08, 0.32, 0.28, 0.95};
            colorBackgroundActive[] = {0.12, 0.42, 0.36, 1};
            action = "[] call comspec_overwatch_connect_fnc_bugReportSubmit;";
        };
        class BtnClose: RscButton {
            idc = 9804;
            text = "Annuler";
            x = 0.54 * safezoneW + safezoneX;
            y = 0.545 * safezoneH + safezoneY;
            w = 0.14 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.12, 0.08, 0.08, 0.95};
            action = "private _d = uiNamespace getVariable ['COMSPEC_BugReport_Display', displayNull]; if (!isNull _d) then { _d closeDisplay 1; } else { closeDialog 0; };";
        };
    };
};
