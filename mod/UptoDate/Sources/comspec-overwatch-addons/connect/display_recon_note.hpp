// Note de reconnaissance rapide (idd 9966).
// IDC 9762 texte · 9763 compteur · 9764–9770 tags · 9771 confiance · 9772 envoyer · 9773 annuler
class COMSPEC_ReconNote_Dialog {
    idd = 9966;
    movingEnable = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_ReconNote_Display', _this select 0];";
    onUnload = "uiNamespace setVariable ['COMSPEC_ReconNote_Display', displayNull];";

    class Controls {
        class Background: RscText {
            idc = -1;
            x = 0.32 * safezoneW + safezoneX;
            y = 0.20 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.56 * safezoneH;
            colorBackground[] = {0.015, 0.04, 0.08, 0.96};
        };
        class AccentBar: RscText {
            idc = -1;
            x = 0.32 * safezoneW + safezoneX;
            y = 0.20 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.004 * safezoneH;
            colorBackground[] = {0.2, 0.65, 0.55, 0.9};
        };
        class Title: RscStructuredText {
            idc = -1;
            text = "<t font='RobotoCondensedBold' size='1' align='center' color='#e8f4f0'>Reco : noter</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.214 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.028 * safezoneH;
        };
        class Hint: RscStructuredText {
            idc = 9761;
            text = "<t align='center' size='0.55' color='#8aa0b4'>Pointé sous le regard. 140 caractères max.</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.244 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.028 * safezoneH;
        };
        class LabelMsg: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>OBSERVATION</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.276 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.016 * safezoneH;
        };
        class EditMsg: RscEdit {
            idc = 9762;
            x = 0.34 * safezoneW + safezoneX;
            y = 0.294 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.072 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1};
            colorText[] = {0.95, 0.98, 0.9, 1};
            sizeEx = 0.032;
            autocomplete = "";
            style = 16;
            maxChars = 140;
        };
        class Counter: RscStructuredText {
            idc = 9763;
            text = "<t align='right' size='0.5' color='#6a8498'>0 / 140</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.368 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.016 * safezoneH;
        };
        class LabelTag: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>TYPE</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.388 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.016 * safezoneH;
        };
        class Tag0: RscButton {
            idc = 9764;
            text = "Véhicule";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.406 * safezoneH + safezoneY;
            w = 0.076 * safezoneW;
            h = 0.030 * safezoneH;
            sizeEx = 0.026;
            colorBackground[] = {0.04, 0.10, 0.14, 0.9};
            colorBackgroundActive[] = {0.08, 0.28, 0.24, 1};
        };
        class Tag1: RscButton {
            idc = 9765;
            text = "Groupe armé";
            x = 0.420 * safezoneW + safezoneX;
            y = 0.406 * safezoneH + safezoneY;
            w = 0.076 * safezoneW;
            h = 0.030 * safezoneH;
            sizeEx = 0.026;
            colorBackground[] = {0.04, 0.10, 0.14, 0.9};
            colorBackgroundActive[] = {0.08, 0.28, 0.24, 1};
        };
        class Tag2: RscButton {
            idc = 9766;
            text = "Statique";
            x = 0.500 * safezoneW + safezoneX;
            y = 0.406 * safezoneH + safezoneY;
            w = 0.076 * safezoneW;
            h = 0.030 * safezoneH;
            sizeEx = 0.026;
            colorBackground[] = {0.04, 0.10, 0.14, 0.9};
            colorBackgroundActive[] = {0.08, 0.28, 0.24, 1};
        };
        class Tag3: RscButton {
            idc = 9767;
            text = "Mine";
            x = 0.580 * safezoneW + safezoneX;
            y = 0.406 * safezoneH + safezoneY;
            w = 0.080 * safezoneW;
            h = 0.030 * safezoneH;
            sizeEx = 0.026;
            colorBackground[] = {0.04, 0.10, 0.14, 0.9};
            colorBackgroundActive[] = {0.08, 0.28, 0.24, 1};
        };
        class Tag4: RscButton {
            idc = 9768;
            text = "Civil";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.440 * safezoneH + safezoneY;
            w = 0.102 * safezoneW;
            h = 0.030 * safezoneH;
            sizeEx = 0.026;
            colorBackground[] = {0.04, 0.10, 0.14, 0.9};
            colorBackgroundActive[] = {0.08, 0.28, 0.24, 1};
        };
        class Tag5: RscButton {
            idc = 9769;
            text = "Infrastructure";
            x = 0.446 * safezoneW + safezoneX;
            y = 0.440 * safezoneH + safezoneY;
            w = 0.106 * safezoneW;
            h = 0.030 * safezoneH;
            sizeEx = 0.026;
            colorBackground[] = {0.04, 0.10, 0.14, 0.9};
            colorBackgroundActive[] = {0.08, 0.28, 0.24, 1};
        };
        class Tag6: RscButton {
            idc = 9770;
            text = "Autre";
            x = 0.556 * safezoneW + safezoneX;
            y = 0.440 * safezoneH + safezoneY;
            w = 0.104 * safezoneW;
            h = 0.030 * safezoneH;
            sizeEx = 0.026;
            colorBackground[] = {0.04, 0.10, 0.14, 0.9};
            colorBackgroundActive[] = {0.08, 0.28, 0.24, 1};
        };
        class Confidence: RscButton {
            idc = 9771;
            text = "Confiance : vu direct";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.484 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.030 * safezoneH;
            sizeEx = 0.030;
            colorBackground[] = {0.04, 0.10, 0.14, 0.85};
            colorBackgroundActive[] = {0.08, 0.28, 0.24, 1};
            tooltip = "Vu de vos yeux, ou simplement rapporté.";
        };
        class BtnSend: RscButton {
            idc = 9772;
            text = "Envoyer";
            x = 0.50 * safezoneW + safezoneX;
            y = 0.530 * safezoneH + safezoneY;
            w = 0.160 * safezoneW;
            h = 0.038 * safezoneH;
            colorBackground[] = {0.12, 0.42, 0.36, 1};
            colorBackgroundActive[] = {0.16, 0.52, 0.44, 1};
            sizeEx = 0.034;
            action = "[] call comspec_overwatch_connect_fnc_reconNoteSubmit;";
        };
        class BtnCancel: RscButton {
            idc = 9773;
            text = "Annuler";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.530 * safezoneH + safezoneY;
            w = 0.150 * safezoneW;
            h = 0.038 * safezoneH;
            colorBackground[] = {0.10, 0.10, 0.12, 1};
            colorBackgroundActive[] = {0.18, 0.18, 0.20, 1};
            sizeEx = 0.034;
            action = "private _d = uiNamespace getVariable ['COMSPEC_ReconNote_Display', displayNull]; if (!isNull _d) then { _d closeDisplay 2; } else { closeDialog 2; };";
        };
        class Footer: RscStructuredText {
            idc = -1;
            text = "<t align='center' size='0.48' color='#6a8498'>Visible au poste et sur la carte de l’équipe.</t>";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.576 * safezoneH + safezoneY;
            w = 0.32 * safezoneW;
            h = 0.022 * safezoneH;
        };
    };
};
