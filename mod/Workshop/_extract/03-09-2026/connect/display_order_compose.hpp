// Mini-fenêtre d’émission d’ordre / FRAGO (idd 9989) — chefs d’unité
class COMSPEC_OrderCompose_Dialog {
    idd = 9989;
    movingEnable = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_OrderCompose_Display', _this select 0]; [] call comspec_overwatch_connect_fnc_orderComposeOnLoad;";
    onUnload = "uiNamespace setVariable ['COMSPEC_OrderCompose_Display', displayNull]; uiNamespace setVariable ['COMSPEC_OrderCompose_StatusToken', -1];";

    class Controls {
        class Background: RscText {
            idc = -1;
            x = 0.30 * safezoneW + safezoneX;
            y = 0.12 * safezoneH + safezoneY;
            w = 0.40 * safezoneW;
            h = 0.72 * safezoneH;
            colorBackground[] = {0.015, 0.04, 0.08, 0.97};
        };
        class AccentBar: RscText {
            idc = -1;
            x = 0.30 * safezoneW + safezoneX;
            y = 0.12 * safezoneH + safezoneY;
            w = 0.40 * safezoneW;
            h = 0.004 * safezoneH;
            colorBackground[] = {0.2, 0.85, 0.65, 0.9};
        };
        class Title: RscStructuredText {
            idc = 9500;
            text = "<t font='RobotoCondensedBold' size='1' align='center' color='#e8f4f0'>Fenêtre rapide d’émission</t>";
            x = 0.32 * safezoneW + safezoneX;
            y = 0.135 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.028 * safezoneH;
        };
        class Hint: RscStructuredText {
            idc = 9501;
            text = "<t align='center' size='0.5' color='#8aa0b4'>Chef d’unité — rédaction in-game</t>";
            x = 0.32 * safezoneW + safezoneX;
            y = 0.162 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.020 * safezoneH;
        };
        class LinkStatus: RscStructuredText {
            idc = 9550;
            text = "<t align='left' size='0.52' color='#8aa0b4'>ÉTAT ATAK — mesure…</t>";
            x = 0.32 * safezoneW + safezoneX;
            y = 0.185 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.048 * safezoneH;
            colorBackground[] = {0.03, 0.07, 0.1, 0.95};
        };

        class LabelKind: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>NATURE</t>";
            x = 0.32 * safezoneW + safezoneX; y = 0.242 * safezoneH + safezoneY; w = 0.17 * safezoneW; h = 0.018 * safezoneH;
        };
        class ComboKind: RscCombo {
            idc = 9502;
            x = 0.32 * safezoneW + safezoneX; y = 0.260 * safezoneH + safezoneY; w = 0.17 * safezoneW; h = 0.030 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1};
            colorSelectBackground[] = {0.08, 0.28, 0.24, 1};
            colorText[] = {0.95, 0.98, 0.9, 1};
            sizeEx = 0.032;
            onLBSelChanged = "[] call comspec_overwatch_connect_fnc_orderComposeRefreshMode;";
        };

        class LabelPrio: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>PRIORITÉ</t>";
            x = 0.51 * safezoneW + safezoneX; y = 0.242 * safezoneH + safezoneY; w = 0.17 * safezoneW; h = 0.018 * safezoneH;
        };
        class ComboPrio: RscCombo {
            idc = 9503;
            x = 0.51 * safezoneW + safezoneX; y = 0.260 * safezoneH + safezoneY; w = 0.17 * safezoneW; h = 0.030 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1};
            colorSelectBackground[] = {0.08, 0.28, 0.24, 1};
            colorText[] = {0.95, 0.98, 0.9, 1};
            sizeEx = 0.032;
        };

        class LabelTarget: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>DESTINATAIRE</t>";
            x = 0.32 * safezoneW + safezoneX; y = 0.298 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.018 * safezoneH;
        };
        class ComboTarget: RscCombo {
            idc = 9504;
            x = 0.32 * safezoneW + safezoneX; y = 0.316 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.030 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1};
            colorSelectBackground[] = {0.08, 0.28, 0.24, 1};
            colorText[] = {0.95, 0.98, 0.9, 1};
            sizeEx = 0.032;
        };

        // Mode ordre simple — consignes libres
        class LabelPayload: RscStructuredText {
            idc = 9510;
            text = "<t size='0.55' color='#5a9e88'>CONSIGNES</t>";
            x = 0.32 * safezoneW + safezoneX; y = 0.356 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.018 * safezoneH;
        };
        class EditPayload: RscEdit {
            idc = 9511;
            x = 0.32 * safezoneW + safezoneX; y = 0.374 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.070 * safezoneH;
            style = 16; // multi-line
            colorBackground[] = {0.04, 0.08, 0.12, 1};
            colorText[] = {0.95, 0.98, 0.9, 1};
            sizeEx = 0.032;
            autocomplete = "";
        };

        // Mode FRAGO — SMEAC
        class LabelSit: RscStructuredText {
            idc = 9520;
            text = "<t size='0.52' color='#5a9e88'>SITUATION</t>";
            x = 0.32 * safezoneW + safezoneX; y = 0.356 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.016 * safezoneH;
        };
        class EditSit: RscEdit {
            idc = 9521;
            x = 0.32 * safezoneW + safezoneX; y = 0.372 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.028 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1}; colorText[] = {0.95, 0.98, 0.9, 1}; sizeEx = 0.030; autocomplete = "";
        };
        class LabelMis: RscStructuredText {
            idc = 9522;
            text = "<t size='0.52' color='#5a9e88'>MISSION</t>";
            x = 0.32 * safezoneW + safezoneX; y = 0.406 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.016 * safezoneH;
        };
        class EditMis: RscEdit {
            idc = 9523;
            x = 0.32 * safezoneW + safezoneX; y = 0.422 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.028 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1}; colorText[] = {0.95, 0.98, 0.9, 1}; sizeEx = 0.030; autocomplete = "";
        };
        class LabelExe: RscStructuredText {
            idc = 9524;
            text = "<t size='0.52' color='#5a9e88'>EXÉCUTION</t>";
            x = 0.32 * safezoneW + safezoneX; y = 0.456 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.016 * safezoneH;
        };
        class EditExe: RscEdit {
            idc = 9525;
            x = 0.32 * safezoneW + safezoneX; y = 0.472 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.028 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1}; colorText[] = {0.95, 0.98, 0.9, 1}; sizeEx = 0.030; autocomplete = "";
        };
        class LabelSup: RscStructuredText {
            idc = 9526;
            text = "<t size='0.52' color='#5a9e88'>SOUTIEN</t>";
            x = 0.32 * safezoneW + safezoneX; y = 0.506 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.016 * safezoneH;
        };
        class EditSup: RscEdit {
            idc = 9527;
            x = 0.32 * safezoneW + safezoneX; y = 0.522 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.028 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1}; colorText[] = {0.95, 0.98, 0.9, 1}; sizeEx = 0.030; autocomplete = "";
        };
        class LabelCmd: RscStructuredText {
            idc = 9528;
            text = "<t size='0.52' color='#5a9e88'>COMMANDEMENT</t>";
            x = 0.32 * safezoneW + safezoneX; y = 0.556 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.016 * safezoneH;
        };
        class EditCmd: RscEdit {
            idc = 9529;
            x = 0.32 * safezoneW + safezoneX; y = 0.572 * safezoneH + safezoneY; w = 0.36 * safezoneW; h = 0.028 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1}; colorText[] = {0.95, 0.98, 0.9, 1}; sizeEx = 0.030; autocomplete = "";
        };

        class TargetHint: RscStructuredText {
            idc = 9530;
            text = "<t align='center' size='0.5' color='#8aa0b4'></t>";
            x = 0.32 * safezoneW + safezoneX;
            y = 0.612 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.020 * safezoneH;
        };

        class BtnSend: RscButton {
            idc = 9540;
            text = "Envoyer";
            x = 0.32 * safezoneW + safezoneX;
            y = 0.668 * safezoneH + safezoneY;
            w = 0.20 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.08, 0.32, 0.28, 0.95};
            colorBackgroundActive[] = {0.12, 0.45, 0.38, 1};
            action = "[] call comspec_overwatch_connect_fnc_orderComposeSubmit;";
        };
        class BtnClose: RscButton {
            idc = 9541;
            text = "Fermer";
            x = 0.53 * safezoneW + safezoneX;
            y = 0.668 * safezoneH + safezoneY;
            w = 0.15 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.12, 0.08, 0.08, 0.95};
            colorBackgroundActive[] = {0.28, 0.12, 0.12, 1};
            action = "closeDialog 0;";
        };
    };
};
