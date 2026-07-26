class COMSPEC_FlightManifest_Dialog {
    idd = 9998;
    movingEnable = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_FlightManifest_Display', _this select 0]; [] call comspec_overwatch_connect_fnc_fillFlightManifest;";

    class Controls {
        class Background: RscText {
            idc = -1;
            x = 0.28 * safezoneW + safezoneX;
            y = 0.18 * safezoneH + safezoneY;
            w = 0.44 * safezoneW;
            h = 0.64 * safezoneH;
            colorBackground[] = {0.02, 0.05, 0.12, 0.94};
        };

        class Title: RscStructuredText {
            idc = -1;
            text = "<t font='RobotoCondensedBold' size='0.95'>Flight Manifest — Air Operations</t>";
            x = 0.29 * safezoneW + safezoneX;
            y = 0.19 * safezoneH + safezoneY;
            w = 0.42 * safezoneW;
            h = 0.035 * safezoneH;
        };

        class LblCallsign: RscText { idc = -1; text = "Callsign:"; x = 0.29 * safezoneW + safezoneX; y = 0.24 * safezoneH + safezoneY; w = 0.12 * safezoneW; h = 0.03 * safezoneH; sizeEx = 0.032; };
        class ValCallsign: RscText { idc = 1501; text = ""; x = 0.42 * safezoneW + safezoneX; y = 0.24 * safezoneH + safezoneY; w = 0.28 * safezoneW; h = 0.03 * safezoneH; sizeEx = 0.032; colorText[] = {0.9,0.9,0.95,1}; };
        class LblModel: RscText { idc = -1; text = "Aircraft:"; x = 0.29 * safezoneW + safezoneX; y = 0.28 * safezoneH + safezoneY; w = 0.12 * safezoneW; h = 0.03 * safezoneH; sizeEx = 0.032; };
        class ValModel: RscText { idc = 1502; text = ""; x = 0.42 * safezoneW + safezoneX; y = 0.28 * safezoneH + safezoneY; w = 0.28 * safezoneW; h = 0.03 * safezoneH; sizeEx = 0.032; colorText[] = {0.9,0.9,0.95,1}; };
        class LblType: RscText { idc = -1; text = "Type:"; x = 0.29 * safezoneW + safezoneX; y = 0.32 * safezoneH + safezoneY; w = 0.12 * safezoneW; h = 0.03 * safezoneH; sizeEx = 0.032; };
        class ValType: RscText { idc = 1503; text = ""; x = 0.42 * safezoneW + safezoneX; y = 0.32 * safezoneH + safezoneY; w = 0.28 * safezoneW; h = 0.03 * safezoneH; sizeEx = 0.032; colorText[] = {0.9,0.9,0.95,1}; };
        class LblFreq: RscText { idc = -1; text = "Freq:"; x = 0.29 * safezoneW + safezoneX; y = 0.36 * safezoneH + safezoneY; w = 0.12 * safezoneW; h = 0.03 * safezoneH; sizeEx = 0.032; };
        class ValFreq: RscText { idc = 1504; text = ""; x = 0.42 * safezoneW + safezoneX; y = 0.36 * safezoneH + safezoneY; w = 0.28 * safezoneW; h = 0.03 * safezoneH; sizeEx = 0.032; colorText[] = {0.9,0.9,0.95,1}; };

        class LblLaser: RscText { idc = -1; text = "Laser code:"; x = 0.29 * safezoneW + safezoneX; y = 0.42 * safezoneH + safezoneY; w = 0.12 * safezoneW; h = 0.03 * safezoneH; sizeEx = 0.032; };
        class EditLaser: RscEdit { idc = 1510; text = "1688"; x = 0.42 * safezoneW + safezoneX; y = 0.42 * safezoneH + safezoneY; w = 0.12 * safezoneW; h = 0.035 * safezoneH; sizeEx = 0.034; colorBackground[] = {0.1,0.1,0.15,0.9}; colorText[] = {1,1,1,1}; };
        class LblAuth: RscText { idc = -1; text = "Auth code:"; x = 0.29 * safezoneW + safezoneX; y = 0.47 * safezoneH + safezoneY; w = 0.12 * safezoneW; h = 0.03 * safezoneH; sizeEx = 0.032; };
        class EditAuth: RscEdit { idc = 1511; text = "SIGMA-5"; x = 0.42 * safezoneW + safezoneX; y = 0.47 * safezoneH + safezoneY; w = 0.28 * safezoneW; h = 0.035 * safezoneH; sizeEx = 0.034; colorBackground[] = {0.1,0.1,0.15,0.9}; colorText[] = {1,1,1,1}; };
        class LblCount: RscText { idc = -1; text = "Aircraft count:"; x = 0.29 * safezoneW + safezoneX; y = 0.52 * safezoneH + safezoneY; w = 0.12 * safezoneW; h = 0.03 * safezoneH; sizeEx = 0.032; };
        class EditCount: RscEdit { idc = 1512; text = "1"; x = 0.42 * safezoneW + safezoneX; y = 0.52 * safezoneH + safezoneY; w = 0.08 * safezoneW; h = 0.035 * safezoneH; sizeEx = 0.034; colorBackground[] = {0.1,0.1,0.15,0.9}; colorText[] = {1,1,1,1}; };

        class SubmitButton: RscButton {
            idc = 1520;
            text = "Submit Flight Manifest";
            x = 0.29 * safezoneW + safezoneX;
            y = 0.6 * safezoneH + safezoneY;
            w = 0.2 * safezoneW;
            h = 0.045 * safezoneH;
            action = "[] call comspec_overwatch_connect_fnc_submitFlightManifest;";
        };
        class CloseButton: RscButton {
            idc = 1521;
            text = "Close";
            x = 0.52 * safezoneW + safezoneX;
            y = 0.6 * safezoneH + safezoneY;
            w = 0.1 * safezoneW;
            h = 0.045 * safezoneH;
            action = "closeDialog 0;";
        };
        class PilotLabel: RscText { idc = -1; text = "CAS response:"; x = 0.29 * safezoneW + safezoneX; y = 0.655 * safezoneH + safezoneY; w = 0.15 * safezoneW; h = 0.025 * safezoneH; sizeEx = 0.028; };
        class BtnRoger: RscButton { idc = 1530; text = "ROGER"; x = 0.29 * safezoneW + safezoneX; y = 0.685 * safezoneH + safezoneY; w = 0.09 * safezoneW; h = 0.032 * safezoneH; sizeEx = 0.028; action = "['ROGER'] call comspec_overwatch_connect_fnc_pilotResponse;"; };
        class BtnInbound: RscButton { idc = 1531; text = "INBOUND"; x = 0.39 * safezoneW + safezoneX; y = 0.685 * safezoneH + safezoneY; w = 0.09 * safezoneW; h = 0.032 * safezoneH; sizeEx = 0.028; action = "['INBOUND'] call comspec_overwatch_connect_fnc_pilotResponse;"; };
        class BtnEngaged: RscButton { idc = 1532; text = "ENGAGED"; x = 0.49 * safezoneW + safezoneX; y = 0.685 * safezoneH + safezoneY; w = 0.09 * safezoneW; h = 0.032 * safezoneH; sizeEx = 0.028; action = "['ENGAGED'] call comspec_overwatch_connect_fnc_pilotResponse;"; };
        class BtnRtb: RscButton { idc = 1533; text = "RTB"; x = 0.59 * safezoneW + safezoneX; y = 0.685 * safezoneH + safezoneY; w = 0.06 * safezoneW; h = 0.032 * safezoneH; sizeEx = 0.028; action = "['RTB'] call comspec_overwatch_connect_fnc_pilotResponse;"; };
    };
};
