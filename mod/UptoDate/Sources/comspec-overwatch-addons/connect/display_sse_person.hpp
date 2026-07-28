// Renseignement interpersonnel — enrôlement personne (idd 9991)
class COMSPEC_SsePerson_Dialog {
    idd = 9991;
    movingEnable = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_SsePerson_Display', _this select 0]; [] call comspec_overwatch_connect_fnc_ssePersonDialogOnLoad;";
    onUnload = "uiNamespace setVariable ['COMSPEC_SsePerson_Display', displayNull]; uiNamespace setVariable ['COMSPEC_SsePerson_Target', objNull];";

    class Controls {
        class Background: RscText {
            idc = -1;
            x = 0.30 * safezoneW + safezoneX;
            y = 0.08 * safezoneH + safezoneY;
            w = 0.40 * safezoneW;
            h = 0.84 * safezoneH;
            colorBackground[] = {0.015, 0.04, 0.08, 0.96};
        };
        class AccentBar: RscText {
            idc = -1;
            x = 0.30 * safezoneW + safezoneX;
            y = 0.08 * safezoneH + safezoneY;
            w = 0.40 * safezoneW;
            h = 0.004 * safezoneH;
            colorBackground[] = {0.2, 0.85, 0.65, 0.9};
        };
        class Title: RscStructuredText {
            idc = -1;
            text = "<t font='RobotoCondensedBold' size='1' align='center' color='#e8f4f0'>Renseignement interpersonnel</t>";
            x = 0.32 * safezoneW + safezoneX;
            y = 0.095 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.03 * safezoneH;
        };
        class Hint: RscStructuredText {
            idc = 9500;
            text = "<t align='center' size='0.55' color='#8aa0b4'>Enregistrez l’identité et la photo du visage de la personne contrôlée.</t>";
            x = 0.32 * safezoneW + safezoneX;
            y = 0.125 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.028 * safezoneH;
        };

        class LabelLast: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>NOM</t>";
            x = 0.32 * safezoneW + safezoneX; y = 0.16 * safezoneH + safezoneY; w = 0.17 * safezoneW; h = 0.016 * safezoneH;
        };
        class EditLast: RscEdit {
            idc = 9501;
            x = 0.32 * safezoneW + safezoneX; y = 0.176 * safezoneH + safezoneY; w = 0.17 * safezoneW; h = 0.03 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1}; colorText[] = {0.95, 0.98, 0.9, 1}; sizeEx = 0.034; autocomplete = "";
        };
        class LabelFirst: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>PRÉNOM</t>";
            x = 0.51 * safezoneW + safezoneX; y = 0.16 * safezoneH + safezoneY; w = 0.17 * safezoneW; h = 0.016 * safezoneH;
        };
        class EditFirst: RscEdit {
            idc = 9502;
            x = 0.51 * safezoneW + safezoneX; y = 0.176 * safezoneH + safezoneY; w = 0.17 * safezoneW; h = 0.03 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1}; colorText[] = {0.95, 0.98, 0.9, 1}; sizeEx = 0.034; autocomplete = "";
        };

        class LabelAlias: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>ALIAS</t>";
            x = 0.32 * safezoneW + safezoneX; y = 0.216 * safezoneH + safezoneY; w = 0.17 * safezoneW; h = 0.016 * safezoneH;
        };
        class EditAlias: RscEdit {
            idc = 9503;
            x = 0.32 * safezoneW + safezoneX; y = 0.232 * safezoneH + safezoneY; w = 0.17 * safezoneW; h = 0.03 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1}; colorText[] = {0.95, 0.98, 0.9, 1}; sizeEx = 0.034; autocomplete = "";
        };
        class LabelAge: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>ÂGE ESTIMÉ</t>";
            x = 0.51 * safezoneW + safezoneX; y = 0.216 * safezoneH + safezoneY; w = 0.17 * safezoneW; h = 0.016 * safezoneH;
        };
        class EditAge: RscEdit {
            idc = 9504;
            x = 0.51 * safezoneW + safezoneX; y = 0.232 * safezoneH + safezoneY; w = 0.17 * safezoneW; h = 0.03 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1}; colorText[] = {0.95, 0.98, 0.9, 1}; sizeEx = 0.034; autocomplete = "";
        };

        class LabelStatus: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>STATUT</t>";
            x = 0.32 * safezoneW + safezoneX; y = 0.272 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.016 * safezoneH;
        };
        class ComboStatus: RscCombo {
            idc = 9505;
            x = 0.32 * safezoneW + safezoneX; y = 0.288 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.03 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1}; colorText[] = {0.95, 0.98, 0.9, 1}; sizeEx = 0.034;
        };

        class LabelCirc: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>CIRCONSTANCES</t>";
            x = 0.32 * safezoneW + safezoneX; y = 0.328 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.016 * safezoneH;
        };
        class ComboCirc: RscCombo {
            idc = 9506;
            x = 0.32 * safezoneW + safezoneX; y = 0.344 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.03 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1}; colorText[] = {0.95, 0.98, 0.9, 1}; sizeEx = 0.034;
        };

        class LabelNat: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>NATIONALITÉ / LANGUE</t>";
            x = 0.32 * safezoneW + safezoneX; y = 0.384 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.016 * safezoneH;
        };
        class EditNat: RscEdit {
            idc = 9507;
            x = 0.32 * safezoneW + safezoneX; y = 0.40 * safezoneH + safezoneY; w = 0.17 * safezoneW; h = 0.03 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1}; colorText[] = {0.95, 0.98, 0.9, 1}; sizeEx = 0.034; autocomplete = "";
        };
        class EditLang: RscEdit {
            idc = 9508;
            x = 0.51 * safezoneW + safezoneX; y = 0.40 * safezoneH + safezoneY; w = 0.17 * safezoneW; h = 0.03 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1}; colorText[] = {0.95, 0.98, 0.9, 1}; sizeEx = 0.034; autocomplete = "";
        };

        class LabelMarks: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>SIGNES DISTINCTIFS</t>";
            x = 0.32 * safezoneW + safezoneX; y = 0.44 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.016 * safezoneH;
        };
        class EditMarks: RscEdit {
            idc = 9509;
            x = 0.32 * safezoneW + safezoneX; y = 0.456 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.03 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1}; colorText[] = {0.95, 0.98, 0.9, 1}; sizeEx = 0.034; autocomplete = "";
        };

        class LabelAffil: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>AFFILIATION ESTIMÉE</t>";
            x = 0.32 * safezoneW + safezoneX; y = 0.496 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.016 * safezoneH;
        };
        class EditAffil: RscEdit {
            idc = 9510;
            x = 0.32 * safezoneW + safezoneX; y = 0.512 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.03 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1}; colorText[] = {0.95, 0.98, 0.9, 1}; sizeEx = 0.034; autocomplete = "";
        };

        class LabelWeapons: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>ARMEMENT / ÉQUIPEMENT (détecté)</t>";
            x = 0.32 * safezoneW + safezoneX; y = 0.552 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.016 * safezoneH;
        };
        class TextWeapons: RscStructuredText {
            idc = 9511;
            text = "<t size='0.55' color='#8aa0b4'>Aucun inventaire détecté.</t>";
            x = 0.32 * safezoneW + safezoneX; y = 0.568 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.06 * safezoneH;
        };

        class LabelStmt: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>DÉCLARATIONS</t>";
            x = 0.32 * safezoneW + safezoneX; y = 0.638 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.016 * safezoneH;
        };
        class EditStmt: RscEdit {
            idc = 9512;
            x = 0.32 * safezoneW + safezoneX; y = 0.654 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.04 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1}; colorText[] = {0.95, 0.98, 0.9, 1}; sizeEx = 0.034; autocomplete = "";
        };

        class StatusText: RscStructuredText {
            idc = 9513;
            text = "";
            x = 0.32 * safezoneW + safezoneX; y = 0.705 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.035 * safezoneH;
        };

        class BtnBio: RscButton {
            idc = 9514;
            text = "Empreintes (simulation)";
            x = 0.32 * safezoneW + safezoneX; y = 0.75 * safezoneH + safezoneY; w = 0.17 * safezoneW; h = 0.034 * safezoneH;
            colorBackground[] = {0.10, 0.18, 0.28, 0.95};
            colorBackgroundActive[] = {0.14, 0.28, 0.40, 1};
            action = "uiNamespace setVariable ['COMSPEC_SsePerson_BioPending', true]; ['Simulation d’empreintes prête — elle sera jointe à l’enregistrement.', 'tactical', 'info'] call comspec_overwatch_connect_fnc_announce;";
        };
        class BtnPhoto: RscButton {
            idc = 9515;
            text = "Photo du visage";
            x = 0.51 * safezoneW + safezoneX; y = 0.75 * safezoneH + safezoneY; w = 0.17 * safezoneW; h = 0.034 * safezoneH;
            colorBackground[] = {0.10, 0.18, 0.28, 0.95};
            colorBackgroundActive[] = {0.14, 0.28, 0.40, 1};
            action = "uiNamespace setVariable ['COMSPEC_SsePerson_PhotoPending', true]; ['Photo du visage : une capture récente sera jointe à l’enregistrement.', 'tactical', 'info'] call comspec_overwatch_connect_fnc_announce;";
        };

        class BtnSave: RscButton {
            idc = 9516;
            text = "Enregistrer";
            x = 0.32 * safezoneW + safezoneX; y = 0.80 * safezoneH + safezoneY; w = 0.22 * safezoneW; h = 0.038 * safezoneH;
            colorBackground[] = {0.08, 0.32, 0.28, 0.95};
            colorBackgroundActive[] = {0.12, 0.45, 0.38, 1};
            action = "[] call comspec_overwatch_connect_fnc_ssePersonDialogSubmit;";
        };
        class BtnClose: RscButton {
            idc = 9517;
            text = "Annuler";
            x = 0.55 * safezoneW + safezoneX; y = 0.80 * safezoneH + safezoneY; w = 0.13 * safezoneW; h = 0.038 * safezoneH;
            colorBackground[] = {0.12, 0.08, 0.08, 0.95};
            colorBackgroundActive[] = {0.28, 0.12, 0.12, 1};
            action = "private _d = uiNamespace getVariable ['COMSPEC_SsePerson_Display', displayNull]; if (!isNull _d) then { _d closeDisplay 2; } else { closeDialog 0; };";
        };
    };
};
