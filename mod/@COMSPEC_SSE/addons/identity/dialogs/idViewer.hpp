// Visionneuse de document (fn_idViewerOpen / fn_idViewerOnLoad). RscText, RscButton, RscStructuredText, RscPicture : config.cpp.
// Le gabarit (PAA 1024 x 512, puissance de 2) est étiré au format réel du document ; les champs, la photo, l'hologramme
// et la MRZ (police à chasse fixe) sont posés par-dessus au chargement, aux coordonnées du gabarit (tools/gen_id_assets.py).

class COMSPEC_SSE_IdViewer {
    idd = 93700;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "(_this select 0) call comspec_sse_fnc_trDisplay; ['load', _this select 0] call comspec_sse_fnc_idViewerOnLoad";
    onUnload = "['stop'] call comspec_sse_fnc_idFace; uiNamespace setVariable ['comspec_sse_idViewerDisplay', displayNull]";

    class controlsBackground {
        class Dim: RscText {
            idc = -1;
            x = "safezoneX"; y = "safezoneY"; w = "safezoneW"; h = "safezoneH";
            colorBackground[] = {0, 0, 0, 0.72};
        };
        class Card: RscPicture {
            idc = 93720;
            text = "";
            x = 0.2; y = 0.2; w = 0.6; h = 0.4;
        };
    };

    class controls {
        class Title: RscStructuredText {
            idc = 93704;
            x = 0.2; y = 0.1; w = 0.6; h = 0.05;
            colorBackground[] = {0, 0, 0, 0};
        };
        class Remark: RscStructuredText {
            idc = 93705;
            x = 0.2; y = 0.65; w = 0.6; h = 0.06;
            colorBackground[] = {0, 0, 0, 0.4};
        };
        // Ligne d'état de la fenêtre (relevé noté, téléphone absent…) : jamais une alerte Arma.
        class Status: RscStructuredText {
            idc = 93706;
            x = 0.2; y = 0.71; w = 0.6; h = 0.03;
            colorBackground[] = {0, 0, 0, 0.4};
        };
        class BtnSide: RscButton {
            idc = 93710;
            text = "RECTO / VERSO";
            x = 0.2; y = 0.75; w = 0.12; h = 0.045;
            action = "['side'] call comspec_sse_fnc_idViewerOnLoad";
            colorBackground[] = {0.12, 0.16, 0.22, 1};
        };
        class BtnZoom: RscButton {
            idc = 93711;
            text = "ZOOM";
            x = 0.33; y = 0.75; w = 0.09; h = 0.045;
            action = "['zoom'] call comspec_sse_fnc_idViewerOnLoad";
            colorBackground[] = {0.12, 0.16, 0.22, 1};
        };
        class BtnScan: RscButton {
            idc = 93712;
            text = "SCANNER (ATAK)";
            x = 0.43; y = 0.75; w = 0.15; h = 0.045;
            action = "['scan'] call comspec_sse_fnc_idViewerOnLoad";
            colorBackground[] = {0.1, 0.3, 0.5, 1};
        };
        class BtnPhoto: RscButton {
            idc = 93714;
            text = "RELEVER";
            tooltip = "Champs du document notés au relevé de contrôle (téléphone › Contrôles)";
            x = 0.59; y = 0.75; w = 0.1; h = 0.045;
            action = "['shoot'] call comspec_sse_fnc_idPhoto";
            colorBackground[] = {0.16, 0.28, 0.2, 1};
        };
        class BtnClose: RscButton {
            idc = 93713;
            text = "FERMER";
            x = 0.7; y = 0.75; w = 0.1; h = 0.045;
            action = "closeDialog 2";
            colorBackground[] = {0.3, 0.1, 0.1, 1};
        };
    };
};
