// Manifeste de vol — opérations aériennes (idd 9998)
class COMSPEC_FlightManifest_Dialog {
    idd = 9998;
    movingEnable = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_FlightManifest_Display', _this select 0]; [] call comspec_overwatch_connect_fnc_fillFlightManifest;";
    onUnload = "uiNamespace setVariable ['COMSPEC_FlightManifest_Display', displayNull];";

    class Controls {
        class Background: RscText {
            idc = -1;
            x = 0.28 * safezoneW + safezoneX;
            y = 0.04 * safezoneH + safezoneY;
            w = 0.44 * safezoneW;
            h = 0.92 * safezoneH;
            colorBackground[] = {0.015, 0.04, 0.08, 0.96};
        };
        class AccentBar: RscText {
            idc = -1;
            x = 0.28 * safezoneW + safezoneX;
            y = 0.04 * safezoneH + safezoneY;
            w = 0.44 * safezoneW;
            h = 0.004 * safezoneH;
            colorBackground[] = {0.2, 0.65, 0.55, 0.9};
        };
        class Title: RscStructuredText {
            idc = -1;
            text = "<t font='RobotoCondensedBold' size='1' align='center' color='#e8f4f0'>Manifeste de vol</t>";
            x = 0.30 * safezoneW + safezoneX;
            y = 0.050 * safezoneH + safezoneY;
            w = 0.40 * safezoneW;
            h = 0.026 * safezoneH;
        };
        class Hint: RscStructuredText {
            idc = 1540;
            text = "<t align='center' size='0.52' color='#8aa0b4'>Identité, emport et canevas — à confirmer avant transmission au poste.</t>";
            x = 0.30 * safezoneW + safezoneX;
            y = 0.076 * safezoneH + safezoneY;
            w = 0.40 * safezoneW;
            h = 0.028 * safezoneH;
        };

        class BodyScroll: RscControlsGroup {
            idc = 1599;
            x = 0.30 * safezoneW + safezoneX;
            y = 0.108 * safezoneH + safezoneY;
            w = 0.40 * safezoneW;
            h = 0.530 * safezoneH;
            class VScrollbar {
                width = 0.012;
                autoScrollEnabled = 0;
                color[] = {0.35, 0.72, 0.62, 0.9};
            };
            class HScrollbar { height = 0; };
            class controls {
                class SecIdent: RscStructuredText {
                    idc = -1;
                    text = "<t size='0.55' color='#5a9e88'>I. IDENTITÉ</t>";
                    x = 0; y = 0.000 * safezoneH; w = 0.395 * safezoneW; h = 0.016 * safezoneH;
                };
                class LblCallsign: RscStructuredText {
                    idc = -1; text = "<t size='0.48' color='#8aa0b4'>INDICATIF</t>";
                    x = 0.002 * safezoneW; y = 0.018 * safezoneH; w = 0.185 * safezoneW; h = 0.014 * safezoneH;
                };
                class ValCallsign: RscEdit {
                    idc = 1501; text = "";
                    x = 0.002 * safezoneW; y = 0.032 * safezoneH; w = 0.185 * safezoneW; h = 0.026 * safezoneH;
                    sizeEx = 0.032; colorText[] = {0.95, 0.98, 0.9, 1}; colorBackground[] = {0.04, 0.08, 0.12, 1}; autocomplete = "";
                };
                class LblType: RscStructuredText {
                    idc = -1; text = "<t size='0.48' color='#8aa0b4'>TYPE</t>";
                    x = 0.198 * safezoneW; y = 0.018 * safezoneH; w = 0.185 * safezoneW; h = 0.014 * safezoneH;
                };
                class ValType: RscCombo {
                    idc = 1503;
                    x = 0.198 * safezoneW; y = 0.032 * safezoneH; w = 0.185 * safezoneW; h = 0.026 * safezoneH;
                    sizeEx = 0.030; wholeHeight = 0.28;
                    colorText[] = {0.95, 0.98, 0.9, 1}; colorBackground[] = {0.04, 0.08, 0.12, 1}; colorSelectBackground[] = {0.10, 0.32, 0.28, 1};
                };
                class LblModel: RscStructuredText {
                    idc = -1; text = "<t size='0.48' color='#8aa0b4'>APPAREIL</t>";
                    x = 0.002 * safezoneW; y = 0.062 * safezoneH; w = 0.185 * safezoneW; h = 0.014 * safezoneH;
                };
                class ValModel: RscEdit {
                    idc = 1502; text = "";
                    x = 0.002 * safezoneW; y = 0.076 * safezoneH; w = 0.185 * safezoneW; h = 0.026 * safezoneH;
                    sizeEx = 0.030; colorText[] = {0.95, 0.98, 0.9, 1}; colorBackground[] = {0.04, 0.08, 0.12, 1}; autocomplete = "";
                };
                class LblFreq: RscStructuredText {
                    idc = -1; text = "<t size='0.48' color='#8aa0b4'>FRÉQUENCE</t>";
                    x = 0.198 * safezoneW; y = 0.062 * safezoneH; w = 0.185 * safezoneW; h = 0.014 * safezoneH;
                };
                class ValFreq: RscEdit {
                    idc = 1504; text = "";
                    x = 0.198 * safezoneW; y = 0.076 * safezoneH; w = 0.185 * safezoneW; h = 0.026 * safezoneH;
                    sizeEx = 0.030; colorText[] = {0.95, 0.98, 0.9, 1}; colorBackground[] = {0.04, 0.08, 0.12, 1}; autocomplete = "";
                };
                class LblGrid: RscStructuredText {
                    idc = -1; text = "<t size='0.48' color='#8aa0b4'>POSITION (GRILLE)</t>";
                    x = 0.002 * safezoneW; y = 0.106 * safezoneH; w = 0.185 * safezoneW; h = 0.014 * safezoneH;
                };
                class ValGrid: RscEdit {
                    idc = 1505; text = "";
                    x = 0.002 * safezoneW; y = 0.120 * safezoneH; w = 0.185 * safezoneW; h = 0.026 * safezoneH;
                    sizeEx = 0.030; colorText[] = {0.95, 0.98, 0.9, 1}; colorBackground[] = {0.04, 0.08, 0.12, 1}; autocomplete = "";
                };
                class LblFuel: RscStructuredText {
                    idc = -1; text = "<t size='0.48' color='#8aa0b4'>CARBURANT</t>";
                    x = 0.198 * safezoneW; y = 0.106 * safezoneH; w = 0.185 * safezoneW; h = 0.014 * safezoneH;
                };
                class ValFuel: RscEdit {
                    idc = 1506; text = "";
                    x = 0.198 * safezoneW; y = 0.120 * safezoneH; w = 0.185 * safezoneW; h = 0.026 * safezoneH;
                    sizeEx = 0.030; colorText[] = {0.95, 0.98, 0.9, 1}; colorBackground[] = {0.04, 0.08, 0.12, 1}; autocomplete = "";
                };
                class LblAlt: RscStructuredText {
                    idc = -1; text = "<t size='0.48' color='#8aa0b4'>ALTITUDE</t>";
                    x = 0.002 * safezoneW; y = 0.150 * safezoneH; w = 0.185 * safezoneW; h = 0.014 * safezoneH;
                };
                class ValAlt: RscEdit {
                    idc = 1514; text = "";
                    x = 0.002 * safezoneW; y = 0.164 * safezoneH; w = 0.185 * safezoneW; h = 0.026 * safezoneH;
                    sizeEx = 0.030; colorText[] = {0.95, 0.98, 0.9, 1}; colorBackground[] = {0.04, 0.08, 0.12, 1}; autocomplete = "";
                };
                class LblHdg: RscStructuredText {
                    idc = -1; text = "<t size='0.48' color='#8aa0b4'>CAP</t>";
                    x = 0.198 * safezoneW; y = 0.150 * safezoneH; w = 0.185 * safezoneW; h = 0.014 * safezoneH;
                };
                class ValHdg: RscEdit {
                    idc = 1515; text = "";
                    x = 0.198 * safezoneW; y = 0.164 * safezoneH; w = 0.185 * safezoneW; h = 0.026 * safezoneH;
                    sizeEx = 0.030; colorText[] = {0.95, 0.98, 0.9, 1}; colorBackground[] = {0.04, 0.08, 0.12, 1}; autocomplete = "";
                };
                class LblCount: RscStructuredText {
                    idc = -1; text = "<t size='0.48' color='#8aa0b4'>APPAREILS</t>";
                    x = 0.002 * safezoneW; y = 0.194 * safezoneH; w = 0.185 * safezoneW; h = 0.014 * safezoneH;
                };
                class EditCount: RscEdit {
                    idc = 1512; text = "1";
                    x = 0.002 * safezoneW; y = 0.208 * safezoneH; w = 0.185 * safezoneW; h = 0.026 * safezoneH;
                    sizeEx = 0.030; colorText[] = {0.95, 0.98, 0.9, 1}; colorBackground[] = {0.04, 0.08, 0.12, 1}; autocomplete = "";
                };
                class LblPax: RscStructuredText {
                    idc = -1; text = "<t size='0.48' color='#8aa0b4'>NOMBRE À BORD</t>";
                    x = 0.198 * safezoneW; y = 0.194 * safezoneH; w = 0.185 * safezoneW; h = 0.014 * safezoneH;
                };
                class EditPax: RscEdit {
                    idc = 1513; text = "1";
                    x = 0.198 * safezoneW; y = 0.208 * safezoneH; w = 0.185 * safezoneW; h = 0.026 * safezoneH;
                    sizeEx = 0.030; colorText[] = {0.95, 0.98, 0.9, 1}; colorBackground[] = {0.04, 0.08, 0.12, 1}; autocomplete = "";
                };
                class LblCrew: RscStructuredText {
                    idc = -1; text = "<t size='0.48' color='#8aa0b4'>PERSONNES À BORD</t>";
                    x = 0.002 * safezoneW; y = 0.238 * safezoneH; w = 0.380 * safezoneW; h = 0.014 * safezoneH;
                };
                class ValCrew: RscEdit {
                    idc = 1516; text = ""; style = 16;
                    x = 0.002 * safezoneW; y = 0.252 * safezoneH; w = 0.380 * safezoneW; h = 0.070 * safezoneH;
                    sizeEx = 0.028; colorText[] = {0.95, 0.98, 0.9, 1}; colorBackground[] = {0.04, 0.08, 0.12, 1}; autocomplete = "";
                };

                class SecMission: RscStructuredText {
                    idc = -1; text = "<t size='0.55' color='#5a9e88'>MISSION</t>";
                    x = 0; y = 0.330 * safezoneH; w = 0.395 * safezoneW; h = 0.016 * safezoneH;
                };
                class LblRole: RscStructuredText {
                    idc = -1; text = "<t size='0.48' color='#8aa0b4'>RÔLE</t>";
                    x = 0.002 * safezoneW; y = 0.348 * safezoneH; w = 0.185 * safezoneW; h = 0.014 * safezoneH;
                };
                class ValRole: RscCombo {
                    idc = 1507;
                    x = 0.002 * safezoneW; y = 0.362 * safezoneH; w = 0.185 * safezoneW; h = 0.026 * safezoneH;
                    sizeEx = 0.030; wholeHeight = 0.32;
                    colorText[] = {0.95, 0.98, 0.9, 1}; colorBackground[] = {0.04, 0.08, 0.12, 1}; colorSelectBackground[] = {0.10, 0.32, 0.28, 1};
                };
                class LblDest: RscStructuredText {
                    idc = -1; text = "<t size='0.48' color='#8aa0b4'>DESTINATION / ZONE</t>";
                    x = 0.198 * safezoneW; y = 0.348 * safezoneH; w = 0.185 * safezoneW; h = 0.014 * safezoneH;
                };
                class ValDest: RscEdit {
                    idc = 1508; text = "";
                    x = 0.198 * safezoneW; y = 0.362 * safezoneH; w = 0.185 * safezoneW; h = 0.026 * safezoneH;
                    sizeEx = 0.030; colorText[] = {0.95, 0.98, 0.9, 1}; colorBackground[] = {0.04, 0.08, 0.12, 1}; autocomplete = "";
                };
                class LblAto: RscStructuredText {
                    idc = -1; text = "<t size='0.48' color='#8aa0b4'>NUMÉRO DE MISSION</t>";
                    x = 0.002 * safezoneW; y = 0.392 * safezoneH; w = 0.185 * safezoneW; h = 0.014 * safezoneH;
                };
                class ValAto: RscEdit {
                    idc = 1523; text = "";
                    x = 0.002 * safezoneW; y = 0.406 * safezoneH; w = 0.185 * safezoneW; h = 0.026 * safezoneH;
                    sizeEx = 0.030; colorText[] = {0.95, 0.98, 0.9, 1}; colorBackground[] = {0.04, 0.08, 0.12, 1}; autocomplete = "";
                };
                class LblPlay: RscStructuredText {
                    idc = -1; text = "<t size='0.48' color='#8aa0b4'>AUTONOMIE SUR ZONE</t>";
                    x = 0.198 * safezoneW; y = 0.392 * safezoneH; w = 0.185 * safezoneW; h = 0.014 * safezoneH;
                };
                class ValPlay: RscEdit {
                    idc = 1519; text = "";
                    x = 0.198 * safezoneW; y = 0.406 * safezoneH; w = 0.185 * safezoneW; h = 0.026 * safezoneH;
                    sizeEx = 0.030; colorText[] = {0.95, 0.98, 0.9, 1}; colorBackground[] = {0.04, 0.08, 0.12, 1}; autocomplete = "";
                };
                class LblNotes: RscStructuredText {
                    idc = -1; text = "<t size='0.48' color='#8aa0b4'>NOTES POUR LE POSTE</t>";
                    x = 0.002 * safezoneW; y = 0.436 * safezoneH; w = 0.380 * safezoneW; h = 0.014 * safezoneH;
                };
                class ValNotes: RscEdit {
                    idc = 1509; text = ""; style = 16;
                    x = 0.002 * safezoneW; y = 0.450 * safezoneH; w = 0.380 * safezoneW; h = 0.036 * safezoneH;
                    sizeEx = 0.030; colorText[] = {0.95, 0.98, 0.9, 1}; colorBackground[] = {0.04, 0.08, 0.12, 1}; autocomplete = "";
                };

                class SecSecu: RscStructuredText {
                    idc = -1; text = "<t size='0.55' color='#5a9e88'>II. SÉCURITÉ ET EMPORT</t>";
                    x = 0; y = 0.494 * safezoneH; w = 0.395 * safezoneW; h = 0.016 * safezoneH;
                };
                class LblLaser: RscStructuredText {
                    idc = -1; text = "<t size='0.48' color='#8aa0b4'>CODE LASER</t>";
                    x = 0.002 * safezoneW; y = 0.512 * safezoneH; w = 0.185 * safezoneW; h = 0.014 * safezoneH;
                };
                class EditLaser: RscEdit {
                    idc = 1510; text = "1688";
                    x = 0.002 * safezoneW; y = 0.526 * safezoneH; w = 0.185 * safezoneW; h = 0.026 * safezoneH;
                    sizeEx = 0.030; colorText[] = {0.95, 0.98, 0.9, 1}; colorBackground[] = {0.04, 0.08, 0.12, 1}; autocomplete = "";
                };
                class LblAuth: RscStructuredText {
                    idc = -1; text = "<t size='0.48' color='#8aa0b4'>AUTHENTIFICATION</t>";
                    x = 0.198 * safezoneW; y = 0.512 * safezoneH; w = 0.185 * safezoneW; h = 0.014 * safezoneH;
                };
                class EditAuth: RscEdit {
                    idc = 1511; text = "SIGMA-5";
                    x = 0.198 * safezoneW; y = 0.526 * safezoneH; w = 0.185 * safezoneW; h = 0.026 * safezoneH;
                    sizeEx = 0.030; colorText[] = {0.95, 0.98, 0.9, 1}; colorBackground[] = {0.04, 0.08, 0.12, 1}; autocomplete = "";
                };
                class LblAbort: RscStructuredText {
                    idc = -1; text = "<t size='0.48' color='#8aa0b4'>CODE D'ANNULATION</t>";
                    x = 0.002 * safezoneW; y = 0.556 * safezoneH; w = 0.380 * safezoneW; h = 0.014 * safezoneH;
                };
                class EditAbort: RscEdit {
                    idc = 1522; text = "";
                    x = 0.002 * safezoneW; y = 0.570 * safezoneH; w = 0.380 * safezoneW; h = 0.026 * safezoneH;
                    sizeEx = 0.030; colorText[] = {0.95, 0.98, 0.9, 1}; colorBackground[] = {0.04, 0.08, 0.12, 1}; autocomplete = "";
                };
                class LblOrd: RscStructuredText {
                    idc = -1; text = "<t size='0.48' color='#8aa0b4'>EMPORT / MUNITIONS</t>";
                    x = 0.002 * safezoneW; y = 0.600 * safezoneH; w = 0.380 * safezoneW; h = 0.014 * safezoneH;
                };
                class ValOrd: RscEdit {
                    idc = 1517; text = ""; style = 16;
                    x = 0.002 * safezoneW; y = 0.614 * safezoneH; w = 0.380 * safezoneW; h = 0.056 * safezoneH;
                    sizeEx = 0.028; colorText[] = {0.95, 0.98, 0.9, 1}; colorBackground[] = {0.04, 0.08, 0.12, 1}; autocomplete = "";
                };
                class LblSen: RscStructuredText {
                    idc = -1; text = "<t size='0.48' color='#8aa0b4'>CAPTEURS / PODS</t>";
                    x = 0.002 * safezoneW; y = 0.674 * safezoneH; w = 0.380 * safezoneW; h = 0.014 * safezoneH;
                };
                class ValSen: RscEdit {
                    idc = 1518; text = "";
                    x = 0.002 * safezoneW; y = 0.688 * safezoneH; w = 0.380 * safezoneW; h = 0.026 * safezoneH;
                    sizeEx = 0.030; colorText[] = {0.95, 0.98, 0.9, 1}; colorBackground[] = {0.04, 0.08, 0.12, 1}; autocomplete = "";
                };

                class SecNine: RscStructuredText {
                    idc = -1; text = "<t size='0.55' color='#5a9e88'>III. CANEVAS D'ATTAQUE</t>";
                    x = 0; y = 0.722 * safezoneH; w = 0.395 * safezoneW; h = 0.016 * safezoneH;
                };
                class LblN1: RscStructuredText {
                    idc = -1; text = "<t size='0.48' color='#8aa0b4'>1 — POINT DE DÉPART (IP / BP)</t>";
                    x = 0.002 * safezoneW; y = 0.740 * safezoneH; w = 0.380 * safezoneW; h = 0.014 * safezoneH;
                };
                class EditN1: RscEdit {
                    idc = 1551; text = "";
                    x = 0.002 * safezoneW; y = 0.754 * safezoneH; w = 0.380 * safezoneW; h = 0.024 * safezoneH;
                    sizeEx = 0.028; colorText[] = {0.95, 0.98, 0.9, 1}; colorBackground[] = {0.04, 0.08, 0.12, 1}; autocomplete = "";
                };
                class LblN2: RscStructuredText {
                    idc = -1; text = "<t size='0.48' color='#8aa0b4'>2 — CAP ET DÉVIATION</t>";
                    x = 0.002 * safezoneW; y = 0.780 * safezoneH; w = 0.380 * safezoneW; h = 0.014 * safezoneH;
                };
                class EditN2: RscEdit {
                    idc = 1552; text = "";
                    x = 0.002 * safezoneW; y = 0.794 * safezoneH; w = 0.380 * safezoneW; h = 0.024 * safezoneH;
                    sizeEx = 0.028; colorText[] = {0.95, 0.98, 0.9, 1}; colorBackground[] = {0.04, 0.08, 0.12, 1}; autocomplete = "";
                };
                class LblN3: RscStructuredText {
                    idc = -1; text = "<t size='0.48' color='#8aa0b4'>3 — DISTANCE</t>";
                    x = 0.002 * safezoneW; y = 0.820 * safezoneH; w = 0.380 * safezoneW; h = 0.014 * safezoneH;
                };
                class EditN3: RscEdit {
                    idc = 1553; text = "";
                    x = 0.002 * safezoneW; y = 0.834 * safezoneH; w = 0.380 * safezoneW; h = 0.024 * safezoneH;
                    sizeEx = 0.028; colorText[] = {0.95, 0.98, 0.9, 1}; colorBackground[] = {0.04, 0.08, 0.12, 1}; autocomplete = "";
                };
                class LblN4: RscStructuredText {
                    idc = -1; text = "<t size='0.48' color='#8aa0b4'>4 — ALTITUDE DE LA CIBLE</t>";
                    x = 0.002 * safezoneW; y = 0.860 * safezoneH; w = 0.380 * safezoneW; h = 0.014 * safezoneH;
                };
                class EditN4: RscEdit {
                    idc = 1554; text = "";
                    x = 0.002 * safezoneW; y = 0.874 * safezoneH; w = 0.380 * safezoneW; h = 0.024 * safezoneH;
                    sizeEx = 0.028; colorText[] = {0.95, 0.98, 0.9, 1}; colorBackground[] = {0.04, 0.08, 0.12, 1}; autocomplete = "";
                };
                class LblN5: RscStructuredText {
                    idc = -1; text = "<t size='0.48' color='#8aa0b4'>5 — DESCRIPTION DE LA CIBLE</t>";
                    x = 0.002 * safezoneW; y = 0.900 * safezoneH; w = 0.380 * safezoneW; h = 0.014 * safezoneH;
                };
                class EditN5: RscEdit {
                    idc = 1555; text = "";
                    x = 0.002 * safezoneW; y = 0.914 * safezoneH; w = 0.380 * safezoneW; h = 0.024 * safezoneH;
                    sizeEx = 0.028; colorText[] = {0.95, 0.98, 0.9, 1}; colorBackground[] = {0.04, 0.08, 0.12, 1}; autocomplete = "";
                };
                class LblN6: RscStructuredText {
                    idc = -1; text = "<t size='0.48' color='#8aa0b4'>6 — POSITION DE LA CIBLE</t>";
                    x = 0.002 * safezoneW; y = 0.940 * safezoneH; w = 0.380 * safezoneW; h = 0.014 * safezoneH;
                };
                class EditN6: RscEdit {
                    idc = 1556; text = "";
                    x = 0.002 * safezoneW; y = 0.954 * safezoneH; w = 0.380 * safezoneW; h = 0.024 * safezoneH;
                    sizeEx = 0.028; colorText[] = {0.95, 0.98, 0.9, 1}; colorBackground[] = {0.04, 0.08, 0.12, 1}; autocomplete = "";
                };
                class LblN7: RscStructuredText {
                    idc = -1; text = "<t size='0.48' color='#8aa0b4'>7 — MARQUAGE</t>";
                    x = 0.002 * safezoneW; y = 0.980 * safezoneH; w = 0.380 * safezoneW; h = 0.014 * safezoneH;
                };
                class EditN7: RscEdit {
                    idc = 1557; text = "";
                    x = 0.002 * safezoneW; y = 0.994 * safezoneH; w = 0.380 * safezoneW; h = 0.024 * safezoneH;
                    sizeEx = 0.028; colorText[] = {0.95, 0.98, 0.9, 1}; colorBackground[] = {0.04, 0.08, 0.12, 1}; autocomplete = "";
                };
                class LblN8: RscStructuredText {
                    idc = -1; text = "<t size='0.48' color='#8aa0b4'>8 — ALLIÉS PROCHES</t>";
                    x = 0.002 * safezoneW; y = 1.020 * safezoneH; w = 0.380 * safezoneW; h = 0.014 * safezoneH;
                };
                class EditN8: RscEdit {
                    idc = 1558; text = "";
                    x = 0.002 * safezoneW; y = 1.034 * safezoneH; w = 0.380 * safezoneW; h = 0.024 * safezoneH;
                    sizeEx = 0.028; colorText[] = {0.95, 0.98, 0.9, 1}; colorBackground[] = {0.04, 0.08, 0.12, 1}; autocomplete = "";
                };
                class LblN9: RscStructuredText {
                    idc = -1; text = "<t size='0.48' color='#8aa0b4'>9 — SORTIE</t>";
                    x = 0.002 * safezoneW; y = 1.060 * safezoneH; w = 0.380 * safezoneW; h = 0.014 * safezoneH;
                };
                class EditN9: RscEdit {
                    idc = 1559; text = "";
                    x = 0.002 * safezoneW; y = 1.074 * safezoneH; w = 0.380 * safezoneW; h = 0.024 * safezoneH;
                    sizeEx = 0.028; colorText[] = {0.95, 0.98, 0.9, 1}; colorBackground[] = {0.04, 0.08, 0.12, 1}; autocomplete = "";
                };
                class Tail: RscText {
                    idc = -1;
                    x = 0; y = 1.110 * safezoneH; w = 0.02 * safezoneW; h = 0.012 * safezoneH;
                    colorBackground[] = {0, 0, 0, 0};
                };
            };
        };

        class SubmitButton: RscButton {
            idc = 1520;
            text = "Transmettre";
            x = 0.30 * safezoneW + safezoneX;
            y = 0.646 * safezoneH + safezoneY;
            w = 0.185 * safezoneW;
            h = 0.030 * safezoneH;
            sizeEx = 0.030;
            colorBackground[] = {0.08, 0.32, 0.28, 0.95};
            colorBackgroundActive[] = {0.12, 0.42, 0.36, 1};
            colorFocused[] = {0.10, 0.38, 0.32, 1};
            action = "[] call comspec_overwatch_connect_fnc_submitFlightManifest;";
        };
        class RefreshButton: RscButton {
            idc = 1542;
            text = "Actualiser";
            x = 0.495 * safezoneW + safezoneX;
            y = 0.646 * safezoneH + safezoneY;
            w = 0.100 * safezoneW;
            h = 0.030 * safezoneH;
            sizeEx = 0.026;
            colorBackground[] = {0.10, 0.18, 0.22, 0.95};
            action = "[] call comspec_overwatch_connect_fnc_fillFlightManifest;";
        };
        class CloseButton: RscButton {
            idc = 1521;
            text = "Fermer";
            x = 0.605 * safezoneW + safezoneX;
            y = 0.646 * safezoneH + safezoneY;
            w = 0.095 * safezoneW;
            h = 0.030 * safezoneH;
            sizeEx = 0.026;
            colorBackground[] = {0.12, 0.08, 0.08, 0.95};
            action = "private _d = uiNamespace getVariable ['COMSPEC_FlightManifest_Display', displayNull]; if (!isNull _d) then { _d closeDisplay 1; } else { closeDialog 0; };";
        };

        class SecPilotLabel: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#c4a072'>RÉPONSE PILOTE</t>";
            x = 0.30 * safezoneW + safezoneX;
            y = 0.684 * safezoneH + safezoneY;
            w = 0.40 * safezoneW;
            h = 0.016 * safezoneH;
        };
        class BtnRoger: RscButton {
            idc = 1530; text = "REÇU";
            x = 0.30 * safezoneW + safezoneX; y = 0.704 * safezoneH + safezoneY; w = 0.128 * safezoneW; h = 0.028 * safezoneH;
            sizeEx = 0.024; colorBackground[] = {0.10, 0.22, 0.20, 0.95};
            action = "['ROGER'] call comspec_overwatch_connect_fnc_pilotResponse;";
        };
        class BtnInbound: RscButton {
            idc = 1531; text = "EN APPROCHE";
            x = 0.436 * safezoneW + safezoneX; y = 0.704 * safezoneH + safezoneY; w = 0.128 * safezoneW; h = 0.028 * safezoneH;
            sizeEx = 0.022; colorBackground[] = {0.28, 0.22, 0.08, 0.95};
            action = "['INBOUND'] call comspec_overwatch_connect_fnc_pilotResponse;";
        };
        class BtnOnsta: RscButton {
            idc = 1534; text = "À POSTE";
            x = 0.572 * safezoneW + safezoneX; y = 0.704 * safezoneH + safezoneY; w = 0.128 * safezoneW; h = 0.028 * safezoneH;
            sizeEx = 0.024; colorBackground[] = {0.12, 0.26, 0.24, 0.95};
            action = "['ONSTA'] call comspec_overwatch_connect_fnc_pilotResponse;";
        };
        class BtnEngaged: RscButton {
            idc = 1532; text = "ENGAGÉ";
            x = 0.30 * safezoneW + safezoneX; y = 0.738 * safezoneH + safezoneY; w = 0.195 * safezoneW; h = 0.028 * safezoneH;
            sizeEx = 0.024; colorBackground[] = {0.32, 0.14, 0.10, 0.95};
            action = "['ENGAGED'] call comspec_overwatch_connect_fnc_pilotResponse;";
        };
        class BtnRtb: RscButton {
            idc = 1533; text = "RETOUR";
            x = 0.505 * safezoneW + safezoneX; y = 0.738 * safezoneH + safezoneY; w = 0.195 * safezoneW; h = 0.028 * safezoneH;
            sizeEx = 0.024; colorBackground[] = {0.12, 0.18, 0.28, 0.95};
            action = "['RTB'] call comspec_overwatch_connect_fnc_pilotResponse;";
        };
        class FooterNote: RscStructuredText {
            idc = -1;
            text = "<t align='center' size='0.45' color='#6a7a88'>COMSPEC Overwatch — canal aérien Athena</t>";
            x = 0.30 * safezoneW + safezoneX;
            y = 0.776 * safezoneH + safezoneY;
            w = 0.40 * safezoneW;
            h = 0.016 * safezoneH;
        };
    };
};
