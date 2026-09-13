// Boîte de triage des alertes médicales reçues (idd 9976)
class COMSPEC_MedicalInbox_Dialog {
    idd = 9976;
    movingEnable = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_MedicalInbox_Display', _this select 0]; [] call comspec_overwatch_connect_fnc_medicalInboxOnLoad;";
    onUnload = "uiNamespace setVariable ['COMSPEC_MedicalInbox_Display', displayNull];";

    class Controls {
        class Background: RscText {
            idc = -1;
            x = 0.26 * safezoneW + safezoneX;
            y = 0.16 * safezoneH + safezoneY;
            w = 0.48 * safezoneW;
            h = 0.56 * safezoneH;
            colorBackground[] = {0.015, 0.04, 0.08, 0.96};
        };

        class AccentBar: RscText {
            idc = -1;
            x = 0.26 * safezoneW + safezoneX;
            y = 0.16 * safezoneH + safezoneY;
            w = 0.48 * safezoneW;
            h = 0.004 * safezoneH;
            colorBackground[] = {0.85, 0.2, 0.18, 0.9};
        };

        class Title: RscStructuredText {
            idc = -1;
            text = "<t font='RobotoCondensedBold' size='1' align='center' color='#e8f4f0'>Medical alerts received</t>";
            x = 0.28 * safezoneW + safezoneX;
            y = 0.175 * safezoneH + safezoneY;
            w = 0.44 * safezoneW;
            h = 0.03 * safezoneH;
        };

        class Hint: RscStructuredText {
            idc = 9502;
            text = "";
            x = 0.28 * safezoneW + safezoneX;
            y = 0.21 * safezoneH + safezoneY;
            w = 0.44 * safezoneW;
            h = 0.028 * safezoneH;
        };

        class AlertList: RscListBox {
            idc = 9501;
            x = 0.28 * safezoneW + safezoneX;
            y = 0.245 * safezoneH + safezoneY;
            w = 0.44 * safezoneW;
            h = 0.28 * safezoneH;
            colorBackground[] = {0, 0, 0, 0.45};
            colorText[] = {0.9, 0.92, 0.94, 1};
            sizeEx = 0.032;
        };

        class BtnEnCours: RscButton {
            idc = 9503;
            text = "In progress";
            x = 0.28 * safezoneW + safezoneX;
            y = 0.54 * safezoneH + safezoneY;
            w = 0.09 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.08, 0.28, 0.36, 0.95};
            action = "['en_cours'] call comspec_overwatch_connect_fnc_medicalTriage;";
        };

        class BtnTraite: RscButton {
            idc = 9504;
            text = "Treated";
            x = 0.38 * safezoneW + safezoneX;
            y = 0.54 * safezoneH + safezoneY;
            w = 0.08 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.08, 0.32, 0.22, 0.95};
            action = "['traite'] call comspec_overwatch_connect_fnc_medicalTriage;";
        };

        class BtnKia: RscButton {
            idc = 9505;
            text = "KIA";
            x = 0.47 * safezoneW + safezoneX;
            y = 0.54 * safezoneH + safezoneY;
            w = 0.07 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.36, 0.12, 0.1, 0.95};
            action = "['kia'] call comspec_overwatch_connect_fnc_medicalTriage;";
        };

        class BtnAnnule: RscButton {
            idc = 9506;
            text = "Cancelled";
            x = 0.55 * safezoneW + safezoneX;
            y = 0.54 * safezoneH + safezoneY;
            w = 0.08 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.22, 0.22, 0.26, 0.95};
            action = "['annule'] call comspec_overwatch_connect_fnc_medicalTriage;";
        };

        class BtnRefresh: RscButton {
            idc = 9507;
            text = "Refresh";
            x = 0.64 * safezoneW + safezoneX;
            y = 0.54 * safezoneH + safezoneY;
            w = 0.08 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.1, 0.18, 0.28, 0.95};
            action = "[] call comspec_overwatch_connect_fnc_medicalInboxOnLoad;";
        };

        class BtnClose: RscButton {
            idc = 9508;
            text = "Close";
            x = 0.28 * safezoneW + safezoneX;
            y = 0.59 * safezoneH + safezoneY;
            w = 0.08 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.12, 0.08, 0.08, 0.95};
            action = "closeDialog 0;";
        };
    };
};
