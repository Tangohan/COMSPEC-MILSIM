// Relevé complet du théâtre (idd 9994) — Zeus / Eden, ne pas réutiliser 9993 (SALUTE).
class COMSPEC_TheaterSurvey_Dialog {
    idd = 9994;
    movingEnable = 1;
    enableSimulation = 1;
    onLoad = "_this call comspec_overwatch_connect_fnc_theaterSurveyOnLoad;";
    onUnload = "uiNamespace setVariable ['COMSPEC_TheaterSurvey_Display', displayNull];";

    class Controls {
        class Background: RscText {
            moving = 1;
            idc = -1;
            x = 0.705 * safezoneW + safezoneX;
            y = 0.18 * safezoneH + safezoneY;
            w = 0.275 * safezoneW;
            h = 0.62 * safezoneH;
            colorBackground[] = {0.015, 0.04, 0.08, 0.96};
        };
        class AccentBar: RscText {
            idc = -1;
            x = 0.705 * safezoneW + safezoneX;
            y = 0.18 * safezoneH + safezoneY;
            w = 0.275 * safezoneW;
            h = 0.004 * safezoneH;
            colorBackground[] = {0.2, 0.65, 0.55, 0.9};
        };
        class Title: RscStructuredText {
            moving = 1;
            idc = -1;
            text = "<t font='RobotoCondensedBold' size='1' align='center' color='#e8f4f0'>Relevé de la carte</t>";
            x = 0.715 * safezoneW + safezoneX;
            y = 0.195 * safezoneH + safezoneY;
            w = 0.255 * safezoneW;
            h = 0.032 * safezoneH;
        };
        class Hint: RscStructuredText {
            idc = -1;
            text = "<t align='center' size='0.52' color='#8aa0b4'>Bâtiments, forêts et relief de tout le théâtre. Athena doit être liée. Zeus reste utilisable pendant le parcours.</t>";
            x = 0.715 * safezoneW + safezoneX;
            y = 0.228 * safezoneH + safezoneY;
            w = 0.255 * safezoneW;
            h = 0.048 * safezoneH;
        };

        class LabelDuration: RscStructuredText {
            idc = -1;
            text = "<t size='0.52' color='#5a9e88'>DURÉE DU RELEVÉ</t>";
            x = 0.715 * safezoneW + safezoneX;
            y = 0.282 * safezoneH + safezoneY;
            w = 0.255 * safezoneW;
            h = 0.018 * safezoneH;
        };
        class ValueDuration: RscStructuredText {
            idc = 1101;
            text = "<t size='0.85' color='#e8f4f0'>—</t>";
            x = 0.715 * safezoneW + safezoneX;
            y = 0.300 * safezoneH + safezoneY;
            w = 0.255 * safezoneW;
            h = 0.028 * safezoneH;
        };

        class LabelCount: RscStructuredText {
            idc = -1;
            text = "<t size='0.52' color='#5a9e88'>DONNÉES COLLECTÉES</t>";
            x = 0.715 * safezoneW + safezoneX;
            y = 0.334 * safezoneH + safezoneY;
            w = 0.255 * safezoneW;
            h = 0.018 * safezoneH;
        };
        class ValueCount: RscStructuredText {
            idc = 1102;
            text = "<t size='0.72' color='#e8f4f0'>Bâtiments 0 · Forêts 0 · Relief 0</t>";
            x = 0.715 * safezoneW + safezoneX;
            y = 0.352 * safezoneH + safezoneY;
            w = 0.255 * safezoneW;
            h = 0.042 * safezoneH;
        };

        class LabelCurrent: RscStructuredText {
            idc = -1;
            text = "<t size='0.52' color='#5a9e88'>SECTEUR EN COURS</t>";
            x = 0.715 * safezoneW + safezoneX;
            y = 0.400 * safezoneH + safezoneY;
            w = 0.255 * safezoneW;
            h = 0.018 * safezoneH;
        };
        class ValueCurrent: RscStructuredText {
            idc = 1103;
            text = "<t size='0.70' color='#c8ddd6'>En attente</t>";
            x = 0.715 * safezoneW + safezoneX;
            y = 0.418 * safezoneH + safezoneY;
            w = 0.255 * safezoneW;
            h = 0.048 * safezoneH;
        };

        class TrackBg: RscText {
            idc = -1;
            x = 0.715 * safezoneW + safezoneX;
            y = 0.472 * safezoneH + safezoneY;
            w = 0.255 * safezoneW;
            h = 0.012 * safezoneH;
            colorBackground[] = {0.04, 0.10, 0.12, 1};
        };
        class TrackFill: RscText {
            idc = 1110;
            x = 0.715 * safezoneW + safezoneX;
            y = 0.472 * safezoneH + safezoneY;
            w = 0.01 * safezoneW;
            h = 0.012 * safezoneH;
            colorBackground[] = {0.20, 0.65, 0.55, 0.95};
        };
        class ValueProgress: RscStructuredText {
            idc = 1105;
            text = "<t size='0.55' color='#8aa0b4'></t>";
            x = 0.715 * safezoneW + safezoneX;
            y = 0.488 * safezoneH + safezoneY;
            w = 0.255 * safezoneW;
            h = 0.022 * safezoneH;
        };

        class LabelLast: RscStructuredText {
            idc = -1;
            text = "<t size='0.52' color='#5a9e88'>DERNIER RELEVÉ</t>";
            x = 0.715 * safezoneW + safezoneX;
            y = 0.516 * safezoneH + safezoneY;
            w = 0.255 * safezoneW;
            h = 0.018 * safezoneH;
        };
        class ValueLast: RscStructuredText {
            idc = 1104;
            text = "<t size='0.65' color='#c8ddd6'>Aucun relevé enregistré pour cette carte</t>";
            x = 0.715 * safezoneW + safezoneX;
            y = 0.534 * safezoneH + safezoneY;
            w = 0.255 * safezoneW;
            h = 0.040 * safezoneH;
        };

        class LabelTx: RscStructuredText {
            idc = -1;
            text = "<t size='0.52' color='#5a9e88'>TRANSMISSION AU POSTE</t>";
            x = 0.715 * safezoneW + safezoneX;
            y = 0.578 * safezoneH + safezoneY;
            w = 0.255 * safezoneW;
            h = 0.018 * safezoneH;
        };
        class ValueTx: RscStructuredText {
            idc = 1108;
            text = "<t size='0.58' color='#c8ddd6'>Pas encore vérifié. Compare le relevé local avec ce qui est arrivé au poste.</t>";
            x = 0.715 * safezoneW + safezoneX;
            y = 0.596 * safezoneH + safezoneY;
            w = 0.255 * safezoneW;
            h = 0.044 * safezoneH;
        };

        class BtnVerify: RscButton {
            idc = 1111;
            text = "Vérifier et renvoyer";
            x = 0.715 * safezoneW + safezoneX;
            y = 0.644 * safezoneH + safezoneY;
            w = 0.255 * safezoneW;
            h = 0.032 * safezoneH;
            colorBackground[] = {0.10, 0.22, 0.32, 0.95};
            colorBackgroundActive[] = {0.14, 0.30, 0.42, 1};
            sizeEx = 0.028;
            tooltip = "Vérifie si tout est bien arrivé au poste. Ce qui manque est renvoyé.";
            action = "[] call comspec_overwatch_connect_fnc_theaterSurveyVerify;";
        };
        class BtnStart: RscButton {
            idc = 1106;
            text = "Lancer le relevé";
            x = 0.715 * safezoneW + safezoneX;
            y = 0.682 * safezoneH + safezoneY;
            w = 0.255 * safezoneW;
            h = 0.032 * safezoneH;
            colorBackground[] = {0.08, 0.32, 0.28, 0.95};
            colorBackgroundActive[] = {0.12, 0.42, 0.36, 1};
            sizeEx = 0.030;
            action = "[] call comspec_overwatch_connect_fnc_theaterSurveyToggle;";
        };
        class BtnClose: RscButton {
            idc = 1107;
            text = "Fermer";
            x = 0.715 * safezoneW + safezoneX;
            y = 0.720 * safezoneH + safezoneY;
            w = 0.255 * safezoneW;
            h = 0.030 * safezoneH;
            colorBackground[] = {0.12, 0.08, 0.08, 0.95};
            sizeEx = 0.028;
            tooltip = "Masque la fenêtre. Un relevé déjà lancé continue en arrière-plan.";
            action = "private _d = uiNamespace getVariable ['COMSPEC_TheaterSurvey_Display', displayNull]; if (!isNull _d) then { _d closeDisplay 1; } else { closeDialog 0; };";
        };
    };
};
