// Demande d'appui aérien rapide (idd 9988)
class COMSPEC_CasRequest_Dialog {
    idd = 9988;
    movingEnable = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_CasRequest_Display', _this select 0];";
    onUnload = "uiNamespace setVariable ['COMSPEC_CasRequest_Display', displayNull];";

    class Controls {
        class Background: RscText {
            idc = -1;
            x = 0.32 * safezoneW + safezoneX;
            y = 0.20 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.46 * safezoneH;
            colorBackground[] = {0.015, 0.04, 0.08, 0.96};
        };
        class AccentBar: RscText {
            idc = -1;
            x = 0.32 * safezoneW + safezoneX;
            y = 0.20 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.004 * safezoneH;
            colorBackground[] = {0.85, 0.55, 0.15, 0.9};
        };
        class Title: RscStructuredText {
            idc = -1;
            text = "<t font='RobotoCondensedBold' size='1' align='center' color='#f8f0e6'>Demande d'appui aérien</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.215 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.03 * safezoneH;
        };
        class Hint: RscStructuredText {
            idc = -1;
            text = "<t align='center' size='0.55' color='#8aa0b4'>Précisez le type d’appui et l’emplacement avant transmission.</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.245 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.028 * safezoneH;
        };

        class LabelType: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#c4a072'>TYPE D’APPUI</t>";
            x = 0.34 * safezoneW + safezoneX; y = 0.285 * safezoneH + safezoneY; w = 0.32 * safezoneW; h = 0.018 * safezoneH;
        };
        class ComboType: RscCombo {
            idc = 9701;
            x = 0.34 * safezoneW + safezoneX; y = 0.303 * safezoneH + safezoneY; w = 0.32 * safezoneW; h = 0.032 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1};
            colorSelectBackground[] = {0.32, 0.22, 0.08, 1};
            colorText[] = {0.95, 0.98, 0.9, 1};
            sizeEx = 0.032;
        };

        class LabelGrid: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#c4a072'>EMPLACEMENT (GRILLE)</t>";
            x = 0.34 * safezoneW + safezoneX; y = 0.350 * safezoneH + safezoneY; w = 0.32 * safezoneW; h = 0.018 * safezoneH;
        };
        class EditGrid: RscEdit {
            idc = 9702;
            x = 0.34 * safezoneW + safezoneX; y = 0.368 * safezoneH + safezoneY; w = 0.32 * safezoneW; h = 0.032 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1}; colorText[] = {0.95, 0.98, 0.9, 1}; sizeEx = 0.035; autocomplete = "";
        };

        class LabelNotes: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#c4a072'>NOTES COURTES (optionnel)</t>";
            x = 0.34 * safezoneW + safezoneX; y = 0.415 * safezoneH + safezoneY; w = 0.32 * safezoneW; h = 0.018 * safezoneH;
        };
        class EditNotes: RscEdit {
            idc = 9703;
            x = 0.34 * safezoneW + safezoneX; y = 0.433 * safezoneH + safezoneY; w = 0.32 * safezoneW; h = 0.048 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1}; colorText[] = {0.95, 0.98, 0.9, 1}; sizeEx = 0.035; autocomplete = "";
        };

        class BtnSend: RscButton {
            idc = 9704;
            text = "Envoyer";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.505 * safezoneH + safezoneY;
            w = 0.18 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.42, 0.28, 0.08, 0.95};
            colorBackgroundActive[] = {0.55, 0.38, 0.12, 1};
            action = "[] call comspec_overwatch_connect_fnc_casRequestSubmit;";
        };
        class BtnClose: RscButton {
            idc = 9705;
            text = "Annuler";
            x = 0.53 * safezoneW + safezoneX;
            y = 0.505 * safezoneH + safezoneY;
            w = 0.13 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.12, 0.08, 0.08, 0.95};
            colorBackgroundActive[] = {0.28, 0.12, 0.12, 1};
            action = "private _d = uiNamespace getVariable ['COMSPEC_CasRequest_Display', displayNull]; if (!isNull _d) then { _d closeDisplay 1; } else { closeDialog 0; };";
        };
    };
};
