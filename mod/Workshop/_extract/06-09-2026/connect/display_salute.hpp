// Compte rendu SALUTE structuré (idd 9993)
// Ne pas réutiliser 9988 : réservé à COMSPEC_CasRequest_Dialog.
class COMSPEC_Salute_Dialog {
    idd = 9993;
    movingEnable = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_Salute_Display', _this select 0];";
    onUnload = "uiNamespace setVariable ['COMSPEC_Salute_Display', displayNull];";

    class Controls {
        class Background: RscText {
            idc = -1;
            x = 0.32 * safezoneW + safezoneX;
            y = 0.16 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.62 * safezoneH;
            colorBackground[] = {0.015, 0.04, 0.08, 0.96};
        };
        class AccentBar: RscText {
            idc = -1;
            x = 0.32 * safezoneW + safezoneX;
            y = 0.16 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.004 * safezoneH;
            colorBackground[] = {0.2, 0.85, 0.65, 0.9};
        };
        class Title: RscStructuredText {
            idc = -1;
            text = "<t font='RobotoCondensedBold' size='1' align='center' color='#e8f4f0'>Compte rendu SALUTE</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.175 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.03 * safezoneH;
        };
        class Hint: RscStructuredText {
            idc = -1;
            text = "<t align='center' size='0.55' color='#8aa0b4'>Renseignez les rubriques avant transmission vers Athena.</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.205 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.028 * safezoneH;
        };

        class LabelS: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>S — EFFECTIF / TAILLE</t>";
            x = 0.34 * safezoneW + safezoneX; y = 0.24 * safezoneH + safezoneY; w = 0.32 * safezoneW; h = 0.018 * safezoneH;
        };
        class EditS: RscEdit {
            idc = 9401;
            x = 0.34 * safezoneW + safezoneX; y = 0.258 * safezoneH + safezoneY; w = 0.32 * safezoneW; h = 0.032 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1}; colorText[] = {0.95, 0.98, 0.9, 1}; sizeEx = 0.035; autocomplete = "";
        };
        class LabelA: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>A — ACTIVITÉ</t>";
            x = 0.34 * safezoneW + safezoneX; y = 0.30 * safezoneH + safezoneY; w = 0.32 * safezoneW; h = 0.018 * safezoneH;
        };
        class EditA: RscEdit {
            idc = 9402;
            x = 0.34 * safezoneW + safezoneX; y = 0.318 * safezoneH + safezoneY; w = 0.32 * safezoneW; h = 0.032 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1}; colorText[] = {0.95, 0.98, 0.9, 1}; sizeEx = 0.035; autocomplete = "";
        };
        class LabelL: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>L — EMPLACEMENT</t>";
            x = 0.34 * safezoneW + safezoneX; y = 0.36 * safezoneH + safezoneY; w = 0.32 * safezoneW; h = 0.018 * safezoneH;
        };
        class EditL: RscEdit {
            idc = 9403;
            x = 0.34 * safezoneW + safezoneX; y = 0.378 * safezoneH + safezoneY; w = 0.32 * safezoneW; h = 0.032 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1}; colorText[] = {0.95, 0.98, 0.9, 1}; sizeEx = 0.035; autocomplete = "";
        };
        class LabelU: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>U — UNITÉ / IDENTIFICATION</t>";
            x = 0.34 * safezoneW + safezoneX; y = 0.42 * safezoneH + safezoneY; w = 0.32 * safezoneW; h = 0.018 * safezoneH;
        };
        class EditU: RscEdit {
            idc = 9404;
            x = 0.34 * safezoneW + safezoneX; y = 0.438 * safezoneH + safezoneY; w = 0.32 * safezoneW; h = 0.032 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1}; colorText[] = {0.95, 0.98, 0.9, 1}; sizeEx = 0.035; autocomplete = "";
        };
        class LabelT: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>T — HEURE</t>";
            x = 0.34 * safezoneW + safezoneX; y = 0.48 * safezoneH + safezoneY; w = 0.32 * safezoneW; h = 0.018 * safezoneH;
        };
        class EditT: RscEdit {
            idc = 9405;
            x = 0.34 * safezoneW + safezoneX; y = 0.498 * safezoneH + safezoneY; w = 0.32 * safezoneW; h = 0.032 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1}; colorText[] = {0.95, 0.98, 0.9, 1}; sizeEx = 0.035; autocomplete = "";
        };
        class LabelE: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>E — ÉQUIPEMENT / ARMEMENT</t>";
            x = 0.34 * safezoneW + safezoneX; y = 0.54 * safezoneH + safezoneY; w = 0.32 * safezoneW; h = 0.018 * safezoneH;
        };
        class EditE: RscEdit {
            idc = 9406;
            x = 0.34 * safezoneW + safezoneX; y = 0.558 * safezoneH + safezoneY; w = 0.32 * safezoneW; h = 0.032 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1}; colorText[] = {0.95, 0.98, 0.9, 1}; sizeEx = 0.035; autocomplete = "";
        };

        class BtnSend: RscButton {
            idc = 9407;
            text = "Envoyer";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.62 * safezoneH + safezoneY;
            w = 0.18 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.08, 0.32, 0.28, 0.95};
            colorBackgroundActive[] = {0.12, 0.45, 0.38, 1};
            action = "[] call comspec_overwatch_connect_fnc_saluteDialogSubmit;";
        };
        class BtnClose: RscButton {
            idc = 9408;
            text = "Annuler";
            x = 0.53 * safezoneW + safezoneX;
            y = 0.62 * safezoneH + safezoneY;
            w = 0.13 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.12, 0.08, 0.08, 0.95};
            colorBackgroundActive[] = {0.28, 0.12, 0.12, 1};
            action = "private _d = uiNamespace getVariable ['COMSPEC_Salute_Display', displayNull]; if (!isNull _d) then { _d closeDisplay 1; } else { closeDialog 0; };";
        };
    };
};
