// Demande d'évacuation médicale rapide (idd 9987)
class COMSPEC_Medevac_Dialog {
    idd = 9987;
    movingEnable = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_Medevac_Display', _this select 0];";
    onUnload = "uiNamespace setVariable ['COMSPEC_Medevac_Display', displayNull];";

    class Controls {
        class Background: RscText {
            idc = -1;
            x = 0.32 * safezoneW + safezoneX;
            y = 0.18 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.52 * safezoneH;
            colorBackground[] = {0.015, 0.04, 0.08, 0.96};
        };
        class AccentBar: RscText {
            idc = -1;
            x = 0.32 * safezoneW + safezoneX;
            y = 0.18 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.004 * safezoneH;
            colorBackground[] = {0.85, 0.25, 0.2, 0.9};
        };
        class Title: RscStructuredText {
            idc = -1;
            text = "<t font='RobotoCondensedBold' size='1' align='center' color='#f8e8e6'>Demande d'évacuation médicale</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.195 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.03 * safezoneH;
        };
        class Hint: RscStructuredText {
            idc = -1;
            text = "<t align='center' size='0.55' color='#8aa0b4'>Indiquez le nombre de blessés avant transmission.</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.225 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.028 * safezoneH;
        };

        class LabelCount: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#c47872'>NOMBRE DE BLESSÉS</t>";
            x = 0.34 * safezoneW + safezoneX; y = 0.265 * safezoneH + safezoneY; w = 0.32 * safezoneW; h = 0.018 * safezoneH;
        };
        class EditCount: RscEdit {
            idc = 9601;
            x = 0.34 * safezoneW + safezoneX; y = 0.283 * safezoneH + safezoneY; w = 0.32 * safezoneW; h = 0.032 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1}; colorText[] = {0.95, 0.98, 0.9, 1}; sizeEx = 0.035; autocomplete = "";
        };

        class LabelSeverity: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#c47872'>GRAVITÉ / URGENCE</t>";
            x = 0.34 * safezoneW + safezoneX; y = 0.325 * safezoneH + safezoneY; w = 0.32 * safezoneW; h = 0.018 * safezoneH;
        };
        class ComboSeverity: RscCombo {
            idc = 9602;
            x = 0.34 * safezoneW + safezoneX; y = 0.343 * safezoneH + safezoneY; w = 0.32 * safezoneW; h = 0.032 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1};
            colorSelectBackground[] = {0.32, 0.12, 0.1, 1};
            colorText[] = {0.95, 0.98, 0.9, 1};
            sizeEx = 0.032;
        };

        class LabelGrid: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#c47872'>EMPLACEMENT (GRILLE)</t>";
            x = 0.34 * safezoneW + safezoneX; y = 0.385 * safezoneH + safezoneY; w = 0.32 * safezoneW; h = 0.018 * safezoneH;
        };
        class EditGrid: RscEdit {
            idc = 9603;
            x = 0.34 * safezoneW + safezoneX; y = 0.403 * safezoneH + safezoneY; w = 0.32 * safezoneW; h = 0.032 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1}; colorText[] = {0.95, 0.98, 0.9, 1}; sizeEx = 0.035; autocomplete = "";
        };

        class LabelNotes: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#c47872'>NOTES COURTES (optionnel)</t>";
            x = 0.34 * safezoneW + safezoneX; y = 0.445 * safezoneH + safezoneY; w = 0.32 * safezoneW; h = 0.018 * safezoneH;
        };
        class EditNotes: RscEdit {
            idc = 9604;
            x = 0.34 * safezoneW + safezoneX; y = 0.463 * safezoneH + safezoneY; w = 0.32 * safezoneW; h = 0.048 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1}; colorText[] = {0.95, 0.98, 0.9, 1}; sizeEx = 0.035; autocomplete = "";
        };

        class BtnSend: RscButton {
            idc = 9605;
            text = "Envoyer";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.54 * safezoneH + safezoneY;
            w = 0.18 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.38, 0.12, 0.1, 0.95};
            colorBackgroundActive[] = {0.52, 0.18, 0.14, 1};
            action = "[] call comspec_overwatch_connect_fnc_medevacDialogSubmit;";
        };
        class BtnClose: RscButton {
            idc = 9606;
            text = "Annuler";
            x = 0.53 * safezoneW + safezoneX;
            y = 0.54 * safezoneH + safezoneY;
            w = 0.13 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.12, 0.08, 0.08, 0.95};
            colorBackgroundActive[] = {0.28, 0.12, 0.12, 1};
            action = "private _d = uiNamespace getVariable ['COMSPEC_Medevac_Display', displayNull]; if (!isNull _d) then { _d closeDisplay 1; } else { closeDialog 0; };";
        };
    };
};
