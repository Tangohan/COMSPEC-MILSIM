// Signalement joueur d’un problème (idd 9992), couleurs de l'ATAK natif.
// Ne pas réutiliser 9989 : réservé à COMSPEC_OrderCompose_Dialog (écrasement config Arma).
// Remplissage, catégories et envoi : fn_bugReportShow, fn_bugReportSubmit.
#define BRX(v) ((v) * safezoneW + safezoneX)
#define BRY(v) ((v) * safezoneH + safezoneY)
#define BRW(v) ((v) * safezoneW)
#define BRH(v) ((v) * safezoneH)


class COMSPEC_BugReport_Dialog {
    idd = 9992;
    movingEnable = 0;
    onLoad = "(_this select 0) call comspec_overwatch_connect_fnc_trDisplay; uiNamespace setVariable ['COMSPEC_BugReport_Display', _this select 0];";
    onUnload = "uiNamespace setVariable ['COMSPEC_BugReport_Display', displayNull];";

    class ControlsBackground {
        class Shade: RscText {
            idc = -1;
            x = safezoneX; y = safezoneY; w = safezoneW; h = safezoneH;
            colorBackground[] = {0, 0, 0, 0.45};
        };
        class Background: RscText {
            idc = -1;
            x = BRX(0.27); y = BRY(0.16); w = BRW(0.46); h = BRH(0.66);
            colorBackground[] = {0.025, 0.030, 0.027, 0.98};
        };
        class Header: RscText {
            idc = -1;
            x = BRX(0.27); y = BRY(0.16); w = BRW(0.46); h = BRH(0.07);
            colorBackground[] = {0.045, 0.055, 0.048, 1};
        };
        class Accent: RscText {
            idc = -1;
            x = BRX(0.27); y = BRY(0.23); w = BRW(0.46); h = BRH(0.003);
            colorBackground[] = {0.36, 0.78, 0.42, 1};
        };
        class LogPanel: RscText {
            idc = -1;
            x = BRX(0.282); y = BRY(0.598); w = BRW(0.436); h = BRH(0.085);
            colorBackground[] = {0.045, 0.055, 0.048, 1};
        };
    };

    class Controls {
        class Title: RscStructuredText {
            idc = 9806;
            text = "<t font='PuristaSemibold' size='1.25' color='#E6F0E8'>Signaler un problème</t><br/><t font='PuristaMedium' size='0.75' color='#94A399'>Le rapport part à l'équipe COMSPEC qui suit le mod, pas sur Discord.</t>";
            x = BRX(0.282); y = BRY(0.167); w = BRW(0.30); h = BRH(0.058);
        };
        class Who: RscStructuredText {
            idc = 9807;
            text = "";
            x = BRX(0.58); y = BRY(0.172); w = BRW(0.138); h = BRH(0.05);
        };

        class LabelCat: RscStructuredText {
            idc = -1;
            text = "<t font='PuristaSemibold' size='0.8' color='#94A399'>1. QUEL TYPE DE PROBLÈME ?</t>";
            x = BRX(0.282); y = BRY(0.245); w = BRW(0.436); h = BRH(0.025);
        };
        // Catégories : six boutons, celui choisi passe en vert (fn_bugReportShow, fn_bugReportPick).
        class Cat1: COMSPEC_RscButtonAtak { idc = 9811; text = "Liaison Athena"; x = BRX(0.282); y = BRY(0.272); w = BRW(0.142); h = BRH(0.04); action = "[0] call comspec_overwatch_connect_fnc_bugReportPick;"; };
        class Cat2: COMSPEC_RscButtonAtak { idc = 9812; text = "Téléphone ATAK"; x = BRX(0.429); y = BRY(0.272); w = BRW(0.142); h = BRH(0.04); action = "[1] call comspec_overwatch_connect_fnc_bugReportPick;"; };
        class Cat3: COMSPEC_RscButtonAtak { idc = 9813; text = "Carte / repères"; x = BRX(0.576); y = BRY(0.272); w = BRW(0.142); h = BRH(0.04); action = "[2] call comspec_overwatch_connect_fnc_bugReportPick;"; };
        class Cat4: COMSPEC_RscButtonAtak { idc = 9814; text = "Photos / messages"; x = BRX(0.282); y = BRY(0.317); w = BRW(0.142); h = BRH(0.04); action = "[3] call comspec_overwatch_connect_fnc_bugReportPick;"; };
        class Cat5: COMSPEC_RscButtonAtak { idc = 9815; text = "Gel / plantage"; x = BRX(0.429); y = BRY(0.317); w = BRW(0.142); h = BRH(0.04); action = "[4] call comspec_overwatch_connect_fnc_bugReportPick;"; };
        class Cat6: COMSPEC_RscButtonAtak { idc = 9816; text = "Autre"; x = BRX(0.576); y = BRY(0.317); w = BRW(0.142); h = BRH(0.04); action = "[5] call comspec_overwatch_connect_fnc_bugReportPick;"; };

        class LabelMsg: RscStructuredText {
            idc = -1;
            text = "<t font='PuristaSemibold' size='0.8' color='#94A399'>2. QUE S'EST-IL PASSÉ ?</t>  <t font='PuristaLight' size='0.72' color='#94A399'>ce que vous faisiez, ce que vous attendiez, ce qui s'est passé</t>";
            x = BRX(0.282); y = BRY(0.37); w = BRW(0.436); h = BRH(0.025);
        };
        class EditMsg: RscEdit {
            idc = 9802;
            x = BRX(0.282); y = BRY(0.397); w = BRW(0.436); h = BRH(0.19);
            style = 16; // plusieurs lignes
            colorBackground[] = {0.045, 0.055, 0.048, 1};
            colorText[] = {0.90, 0.94, 0.91, 1};
            colorSelection[] = {0.36, 0.78, 0.42, 0.45};
            colorBorder[] = {0.18, 0.38, 0.21, 1};
            font = "PuristaMedium";
            sizeEx = "(((((safezoneW / safezoneH) min 1.2) / 1.2) / 25) * 0.9)";
            autocomplete = "";
            maxChars = 1500;
        };

        class ChkLog: RscCheckBox {
            idc = 9805;
            x = BRX(0.29); y = BRY(0.607); w = BRW(0.016); h = BRH(0.028);
            checked = 1;
        };
        class LabelLog: RscStructuredText {
            idc = 9808;
            text = "";
            x = BRX(0.31); y = BRY(0.603); w = BRW(0.403); h = BRH(0.078);
        };

        class Status: RscStructuredText {
            idc = 9820;
            text = "";
            x = BRX(0.282); y = BRY(0.692); w = BRW(0.436); h = BRH(0.05);
        };

        class BtnSend: COMSPEC_RscButtonAtakPrimary {
            idc = 9803;
            text = "Envoyer le signalement";
            x = BRX(0.282); y = BRY(0.755); w = BRW(0.27); h = BRH(0.048);
            action = "[] spawn comspec_overwatch_connect_fnc_bugReportSubmit;";
        };
        class BtnClose: COMSPEC_RscButtonAtak {
            idc = 9804;
            text = "Annuler";
            x = BRX(0.558); y = BRY(0.755); w = BRW(0.16); h = BRH(0.048);
            action = "private _d = uiNamespace getVariable ['COMSPEC_BugReport_Display', displayNull]; if (!isNull _d) then { _d closeDisplay 2; } else { closeDialog 0; };";
        };
    };
};

#undef BRX
#undef BRY
#undef BRW
#undef BRH
