class COMSPEC_Briefing_Dialog {
    idd = 9970;
    movingEnable = 1;
    onLoad = "[0] call comspec_overwatch_connect_fnc_briefingBoardShow;";

    class Controls {
        class Background: RscText {
            idc = -1;
            x = 0.12 * safezoneW + safezoneX;
            y = 0.08 * safezoneH + safezoneY;
            w = 0.76 * safezoneW;
            h = 0.84 * safezoneH;
            colorBackground[] = {0.02, 0.05, 0.1, 0.94};
        };

        class Title: RscStructuredText {
            idc = -1;
            text = "<t font='RobotoCondensedBold' size='0.9'>Tableau de briefing</t>";
            x = 0.14 * safezoneW + safezoneX;
            y = 0.095 * safezoneH + safezoneY;
            w = 0.5 * safezoneW;
            h = 0.03 * safezoneH;
        };

        class SlideIndex: RscText {
            idc = 9003;
            text = "— / —";
            x = 0.78 * safezoneW + safezoneX;
            y = 0.095 * safezoneH + safezoneY;
            w = 0.08 * safezoneW;
            h = 0.03 * safezoneH;
            sizeEx = 0.032;
            colorText[] = {0.7, 0.8, 0.9, 1};
        };

        class SlidePicture: RscPicture {
            idc = 9001;
            text = "";
            x = 0.14 * safezoneW + safezoneX;
            y = 0.135 * safezoneH + safezoneY;
            w = 0.72 * safezoneW;
            h = 0.68 * safezoneH;
            colorBackground[] = {0, 0, 0, 0.6};
        };

        class SlideCaption: RscStructuredText {
            idc = 9002;
            text = "";
            x = 0.14 * safezoneW + safezoneX;
            y = 0.825 * safezoneH + safezoneY;
            w = 0.72 * safezoneW;
            h = 0.03 * safezoneH;
            size = 0.9;
        };

        class PrevButton: RscButton {
            idc = 9010;
            text = "< Précédente";
            x = 0.14 * safezoneW + safezoneX;
            y = 0.865 * safezoneH + safezoneY;
            w = 0.14 * safezoneW;
            h = 0.04 * safezoneH;
            action = "[-1] call comspec_overwatch_connect_fnc_briefingBoardStep;";
        };

        class NextButton: RscButton {
            idc = 9011;
            text = "Suivante >";
            x = 0.29 * safezoneW + safezoneX;
            y = 0.865 * safezoneH + safezoneY;
            w = 0.14 * safezoneW;
            h = 0.04 * safezoneH;
            action = "[1] call comspec_overwatch_connect_fnc_briefingBoardStep;";
        };

        class RefreshButton: RscButton {
            idc = 9012;
            text = "Actualiser";
            x = 0.62 * safezoneW + safezoneX;
            y = 0.865 * safezoneH + safezoneY;
            w = 0.1 * safezoneW;
            h = 0.04 * safezoneH;
            action = "[] call comspec_overwatch_connect_fnc_refreshBriefingSlides;";
        };

        class CloseButton: RscButton {
            idc = 9013;
            text = "Fermer";
            x = 0.74 * safezoneW + safezoneX;
            y = 0.865 * safezoneH + safezoneY;
            w = 0.12 * safezoneW;
            h = 0.04 * safezoneH;
            action = "closeDialog 0;";
        };
    };
};
