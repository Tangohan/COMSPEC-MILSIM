// Manifeste de vol — opérations aériennes (idd 9998)
// Style Athena / cTab : fond tactique sombre, accent teal, sections alignées.
class COMSPEC_FlightManifest_Dialog {
    idd = 9998;
    movingEnable = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_FlightManifest_Display', _this select 0]; [] call comspec_overwatch_connect_fnc_fillFlightManifest;";
    onUnload = "uiNamespace setVariable ['COMSPEC_FlightManifest_Display', displayNull];";

    class Controls {
        // --- Cadre ---
        class Background: RscText {
            idc = -1;
            x = 0.30 * safezoneW + safezoneX;
            y = 0.14 * safezoneH + safezoneY;
            w = 0.40 * safezoneW;
            h = 0.72 * safezoneH;
            colorBackground[] = {0.015, 0.04, 0.08, 0.96};
        };
        class AccentBar: RscText {
            idc = -1;
            x = 0.30 * safezoneW + safezoneX;
            y = 0.14 * safezoneH + safezoneY;
            w = 0.40 * safezoneW;
            h = 0.004 * safezoneH;
            colorBackground[] = {0.2, 0.65, 0.55, 0.9};
        };

        // --- En-tête ---
        class Title: RscStructuredText {
            idc = -1;
            text = "<t font='RobotoCondensedBold' size='1' align='center' color='#e8f4f0'>Manifeste de vol</t>";
            x = 0.32 * safezoneW + safezoneX;
            y = 0.155 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.028 * safezoneH;
        };
        class Hint: RscStructuredText {
            idc = -1;
            text = "<t align='center' size='0.52' color='#8aa0b4'>Opérations aériennes — identité détectée, codes à confirmer avant transmission.</t>";
            x = 0.32 * safezoneW + safezoneX;
            y = 0.185 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.028 * safezoneH;
        };

        // --- Section identité ---
        class SecIdentLabel: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>IDENTITÉ AÉRONEF</t>";
            x = 0.32 * safezoneW + safezoneX;
            y = 0.220 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.018 * safezoneH;
        };
        class IdentPanel: RscText {
            idc = -1;
            x = 0.32 * safezoneW + safezoneX;
            y = 0.240 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.168 * safezoneH;
            colorBackground[] = {0.03, 0.07, 0.10, 0.95};
        };

        // Ligne 1 : Indicatif | Type
        class LblCallsign: RscStructuredText {
            idc = -1;
            text = "<t size='0.48' color='#8aa0b4'>INDICATIF</t>";
            x = 0.330 * safezoneW + safezoneX; y = 0.248 * safezoneH + safezoneY; w = 0.165 * safezoneW; h = 0.016 * safezoneH;
        };
        class ValCallsign: RscEdit {
            idc = 1501;
            text = "";
            x = 0.330 * safezoneW + safezoneX; y = 0.264 * safezoneH + safezoneY; w = 0.165 * safezoneW; h = 0.028 * safezoneH;
            sizeEx = 0.034;
            colorText[] = {0.95, 0.98, 0.9, 1};
            colorBackground[] = {0.04, 0.08, 0.12, 1};
            autocomplete = "";
        };
        class LblType: RscStructuredText {
            idc = -1;
            text = "<t size='0.48' color='#8aa0b4'>TYPE</t>";
            x = 0.505 * safezoneW + safezoneX; y = 0.248 * safezoneH + safezoneY; w = 0.165 * safezoneW; h = 0.016 * safezoneH;
        };
        class ValType: RscText {
            idc = 1503;
            text = "";
            x = 0.505 * safezoneW + safezoneX; y = 0.264 * safezoneH + safezoneY; w = 0.165 * safezoneW; h = 0.028 * safezoneH;
            sizeEx = 0.034;
            colorText[] = {0.95, 0.98, 0.9, 1};
            colorBackground[] = {0.04, 0.08, 0.12, 1};
        };

        // Ligne 2 : Aéronef | Fréquence
        class LblModel: RscStructuredText {
            idc = -1;
            text = "<t size='0.48' color='#8aa0b4'>AÉRONEF</t>";
            x = 0.330 * safezoneW + safezoneX; y = 0.302 * safezoneH + safezoneY; w = 0.165 * safezoneW; h = 0.016 * safezoneH;
        };
        class ValModel: RscEdit {
            idc = 1502;
            text = "";
            x = 0.330 * safezoneW + safezoneX; y = 0.318 * safezoneH + safezoneY; w = 0.165 * safezoneW; h = 0.028 * safezoneH;
            sizeEx = 0.032;
            colorText[] = {0.95, 0.98, 0.9, 1};
            colorBackground[] = {0.04, 0.08, 0.12, 1};
            autocomplete = "";
        };
        class LblFreq: RscStructuredText {
            idc = -1;
            text = "<t size='0.48' color='#8aa0b4'>FRÉQUENCE</t>";
            x = 0.505 * safezoneW + safezoneX; y = 0.302 * safezoneH + safezoneY; w = 0.165 * safezoneW; h = 0.016 * safezoneH;
        };
        class ValFreq: RscText {
            idc = 1504;
            text = "";
            x = 0.505 * safezoneW + safezoneX; y = 0.318 * safezoneH + safezoneY; w = 0.165 * safezoneW; h = 0.028 * safezoneH;
            sizeEx = 0.034;
            colorText[] = {0.95, 0.98, 0.9, 1};
            colorBackground[] = {0.04, 0.08, 0.12, 1};
        };

        // Ligne 3 aide
        class IdentNote: RscStructuredText {
            idc = -1;
            text = "<t size='0.45' color='#6a7a88'>Indicatif et aéronef sont modifiables (utile en déclaration depuis le sol). En aéronef, le modèle est détecté automatiquement.</t>";
            x = 0.330 * safezoneW + safezoneX; y = 0.358 * safezoneH + safezoneY; w = 0.340 * safezoneW; h = 0.036 * safezoneH;
        };

        // --- Section codes ---
        class SecCodesLabel: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#5a9e88'>CODES ET EFFECTIF</t>";
            x = 0.32 * safezoneW + safezoneX;
            y = 0.425 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.018 * safezoneH;
        };

        class LblLaser: RscStructuredText {
            idc = -1;
            text = "<t size='0.48' color='#8aa0b4'>CODE LASER</t>";
            x = 0.32 * safezoneW + safezoneX; y = 0.448 * safezoneH + safezoneY; w = 0.115 * safezoneW; h = 0.016 * safezoneH;
        };
        class EditLaser: RscEdit {
            idc = 1510;
            text = "1688";
            x = 0.32 * safezoneW + safezoneX; y = 0.466 * safezoneH + safezoneY; w = 0.115 * safezoneW; h = 0.032 * safezoneH;
            sizeEx = 0.034;
            colorBackground[] = {0.04, 0.08, 0.12, 1};
            colorText[] = {0.95, 0.98, 0.9, 1};
            autocomplete = "";
        };
        class LblAuth: RscStructuredText {
            idc = -1;
            text = "<t size='0.48' color='#8aa0b4'>CODE D’AUTHENTIFICATION</t>";
            x = 0.445 * safezoneW + safezoneX; y = 0.448 * safezoneH + safezoneY; w = 0.235 * safezoneW; h = 0.016 * safezoneH;
        };
        class EditAuth: RscEdit {
            idc = 1511;
            text = "SIGMA-5";
            x = 0.445 * safezoneW + safezoneX; y = 0.466 * safezoneH + safezoneY; w = 0.235 * safezoneW; h = 0.032 * safezoneH;
            sizeEx = 0.034;
            colorBackground[] = {0.04, 0.08, 0.12, 1};
            colorText[] = {0.95, 0.98, 0.9, 1};
            autocomplete = "";
        };

        class LblCount: RscStructuredText {
            idc = -1;
            text = "<t size='0.48' color='#8aa0b4'>NOMBRE D’APPAREILS</t>";
            x = 0.32 * safezoneW + safezoneX; y = 0.508 * safezoneH + safezoneY; w = 0.20 * safezoneW; h = 0.016 * safezoneH;
        };
        class EditCount: RscEdit {
            idc = 1512;
            text = "1";
            x = 0.32 * safezoneW + safezoneX; y = 0.526 * safezoneH + safezoneY; w = 0.08 * safezoneW; h = 0.032 * safezoneH;
            sizeEx = 0.034;
            colorBackground[] = {0.04, 0.08, 0.12, 1};
            colorText[] = {0.95, 0.98, 0.9, 1};
            autocomplete = "";
        };

        // --- Actions principales ---
        class SubmitButton: RscButton {
            idc = 1520;
            text = "Transmettre";
            x = 0.32 * safezoneW + safezoneX;
            y = 0.580 * safezoneH + safezoneY;
            w = 0.22 * safezoneW;
            h = 0.036 * safezoneH;
            sizeEx = 0.034;
            colorBackground[] = {0.08, 0.32, 0.28, 0.95};
            colorBackgroundActive[] = {0.12, 0.42, 0.36, 1};
            colorFocused[] = {0.10, 0.38, 0.32, 1};
            action = "[] call comspec_overwatch_connect_fnc_submitFlightManifest;";
        };
        class CloseButton: RscButton {
            idc = 1521;
            text = "Fermer";
            x = 0.55 * safezoneW + safezoneX;
            y = 0.580 * safezoneH + safezoneY;
            w = 0.13 * safezoneW;
            h = 0.036 * safezoneH;
            sizeEx = 0.034;
            colorBackground[] = {0.12, 0.08, 0.08, 0.95};
            colorBackgroundActive[] = {0.28, 0.12, 0.12, 1};
            colorFocused[] = {0.18, 0.10, 0.10, 1};
            action = "private _d = uiNamespace getVariable ['COMSPEC_FlightManifest_Display', displayNull]; if (!isNull _d) then { _d closeDisplay 1; } else { closeDialog 0; };";
        };

        // --- Réponse pilote ---
        class SecPilotLabel: RscStructuredText {
            idc = -1;
            text = "<t size='0.55' color='#c4a072'>RÉPONSE PILOTE</t>";
            x = 0.32 * safezoneW + safezoneX;
            y = 0.635 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.018 * safezoneH;
        };
        class PilotHint: RscStructuredText {
            idc = -1;
            text = "<t size='0.45' color='#8aa0b4'>Statut court transmis vers Athena (sans fermer le manifeste).</t>";
            x = 0.32 * safezoneW + safezoneX;
            y = 0.654 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.018 * safezoneH;
        };

        class BtnRoger: RscButton {
            idc = 1530;
            text = "REÇU";
            x = 0.32 * safezoneW + safezoneX;
            y = 0.680 * safezoneH + safezoneY;
            w = 0.085 * safezoneW;
            h = 0.034 * safezoneH;
            sizeEx = 0.028;
            colorBackground[] = {0.10, 0.22, 0.20, 0.95};
            colorBackgroundActive[] = {0.14, 0.36, 0.30, 1};
            colorFocused[] = {0.12, 0.30, 0.26, 1};
            action = "['ROGER'] call comspec_overwatch_connect_fnc_pilotResponse;";
        };
        class BtnInbound: RscButton {
            idc = 1531;
            text = "EN APPROCHE";
            x = 0.412 * safezoneW + safezoneX;
            y = 0.680 * safezoneH + safezoneY;
            w = 0.085 * safezoneW;
            h = 0.034 * safezoneH;
            sizeEx = 0.026;
            colorBackground[] = {0.28, 0.22, 0.08, 0.95};
            colorBackgroundActive[] = {0.42, 0.32, 0.12, 1};
            colorFocused[] = {0.34, 0.26, 0.10, 1};
            action = "['INBOUND'] call comspec_overwatch_connect_fnc_pilotResponse;";
        };
        class BtnEngaged: RscButton {
            idc = 1532;
            text = "ENGAGÉ";
            x = 0.504 * safezoneW + safezoneX;
            y = 0.680 * safezoneH + safezoneY;
            w = 0.085 * safezoneW;
            h = 0.034 * safezoneH;
            sizeEx = 0.028;
            colorBackground[] = {0.32, 0.14, 0.10, 0.95};
            colorBackgroundActive[] = {0.48, 0.20, 0.14, 1};
            colorFocused[] = {0.40, 0.16, 0.12, 1};
            action = "['ENGAGED'] call comspec_overwatch_connect_fnc_pilotResponse;";
        };
        class BtnRtb: RscButton {
            idc = 1533;
            text = "RETOUR";
            x = 0.596 * safezoneW + safezoneX;
            y = 0.680 * safezoneH + safezoneY;
            w = 0.084 * safezoneW;
            h = 0.034 * safezoneH;
            sizeEx = 0.028;
            colorBackground[] = {0.12, 0.18, 0.28, 0.95};
            colorBackgroundActive[] = {0.18, 0.28, 0.42, 1};
            colorFocused[] = {0.15, 0.22, 0.34, 1};
            action = "['RTB'] call comspec_overwatch_connect_fnc_pilotResponse;";
        };

        class FooterLine: RscText {
            idc = -1;
            x = 0.32 * safezoneW + safezoneX;
            y = 0.730 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.0015 * safezoneH;
            colorBackground[] = {0.2, 0.65, 0.55, 0.35};
        };
        class FooterNote: RscStructuredText {
            idc = -1;
            text = "<t align='center' size='0.45' color='#6a7a88'>COMSPEC Overwatch — canal aérien Athena</t>";
            x = 0.32 * safezoneW + safezoneX;
            y = 0.738 * safezoneH + safezoneY;
            w = 0.36 * safezoneW;
            h = 0.020 * safezoneH;
        };
    };
};
