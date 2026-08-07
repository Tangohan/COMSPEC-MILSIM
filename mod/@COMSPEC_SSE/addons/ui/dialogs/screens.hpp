#include "common.hpp"

// ============================================================
// TERMINAL SSE TERRAIN — hub principal (idd 93200)
// ============================================================
class COMSPEC_SSE_TerminalDialog {
    idd = 93200;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "['terminal'] call comspec_sse_fnc_uiOnLoad";

    class controlsBackground {
        class BG: RscText {
            idc = -1;
            x = 0.08; y = 0.06; w = 0.84; h = 0.88;
            colorBackground[] = SSE_UI_BG;
        };
        class Title: RscText {
            idc = 93201;
            text = "TERMINAL SSE — TERRAIN";
            x = 0.08; y = 0.06; w = 0.84; h = 0.045;
            colorBackground[] = SSE_UI_HDR;
            colorText[] = SSE_UI_ACCENT;
        };
        class Sub: RscText {
            idc = 93202;
            text = "Record lié · collecte · transmission";
            x = 0.08; y = 0.105; w = 0.84; h = 0.028;
            colorBackground[] = {0.04,0.1,0.05,1};
            colorText[] = {0.7,0.9,0.7,1};
        };
    };

    class controls {
        class NavBar: RscStructuredText {
            idc = 93210;
            x = 0.1; y = 0.145; w = 0.8; h = 0.04;
            colorBackground[] = {0,0,0,0.2};
        };
        class Summary: RscStructuredText {
            idc = 93211;
            x = 0.1; y = 0.195; w = 0.38; h = 0.28;
            colorBackground[] = {0,0,0,0.25};
        };
        class ListTitle: RscText {
            idc = -1;
            text = "Éléments / dossiers";
            x = 0.5; y = 0.195; w = 0.4; h = 0.03;
            colorText[] = SSE_UI_ACCENT;
        };
        class List: RscListBox {
            idc = 93212;
            x = 0.5; y = 0.225; w = 0.4; h = 0.4;
            colorBackground[] = {0,0,0,0.35};
        };
        class Detail: RscStructuredText {
            idc = 93213;
            x = 0.1; y = 0.49; w = 0.38; h = 0.28;
            colorBackground[] = {0,0,0,0.25};
        };

        class BtnDigital: RscButton {
            idc = 93220; text = "DIGITAL";
            x = 0.1; y = 0.8; w = 0.11; h = 0.04;
            action = "['digital'] call comspec_sse_fnc_uiOpenScreen";
            colorBackground[] = SSE_UI_BTN;
        };
        class BtnSeek: RscButton {
            idc = 93221; text = "SEEK II";
            x = 0.22; y = 0.8; w = 0.11; h = 0.04;
            action = "['seek'] call comspec_sse_fnc_uiOpenScreen";
            colorBackground[] = SSE_UI_BTN;
        };
        class BtnSite: RscButton {
            idc = 93222; text = "SITE";
            x = 0.34; y = 0.8; w = 0.11; h = 0.04;
            action = "['site'] call comspec_sse_fnc_uiOpenScreen";
            colorBackground[] = SSE_UI_BTN;
        };
        class BtnGraph: RscButton {
            idc = 93223; text = "GRAPH";
            x = 0.46; y = 0.8; w = 0.11; h = 0.04;
            action = "['graph'] call comspec_sse_fnc_uiOpenScreen";
            colorBackground[] = SSE_UI_BTN;
        };
        class BtnEvidence: RscButton {
            idc = 93224; text = "PREUVES";
            x = 0.58; y = 0.8; w = 0.11; h = 0.04;
            action = "['evidence'] call comspec_sse_fnc_uiOpenScreen";
            colorBackground[] = SSE_UI_BTN;
        };
        class BtnMission: RscButton {
            idc = 93225; text = "MISSION";
            x = 0.7; y = 0.8; w = 0.11; h = 0.04;
            action = "['mission'] call comspec_sse_fnc_uiOpenScreen";
            colorBackground[] = SSE_UI_BTN;
        };
        class BtnRefresh: RscButton {
            idc = 93226; text = "RAFRAÎCHIR";
            x = 0.1; y = 0.86; w = 0.14; h = 0.04;
            action = "['terminal'] call comspec_sse_fnc_uiRefresh";
            colorBackground[] = SSE_UI_BTN2;
        };
        class BtnTx: RscButton {
            idc = 93227; text = "TRANSMETTRE";
            x = 0.26; y = 0.86; w = 0.16; h = 0.04;
            action = "[] call comspec_sse_fnc_uiTransmitRecord";
            colorBackground[] = SSE_UI_BTN2;
        };
        class BtnZeus: RscButton {
            idc = 93229; text = "ZEUS";
            x = 0.44; y = 0.86; w = 0.12; h = 0.04;
            action = "['zeus'] call comspec_sse_fnc_uiOpenScreen";
            colorBackground[] = {0.35,0.2,0.05,1};
        };
        class BtnClose: RscButton {
            idc = 93228; text = "FERMER";
            x = 0.72; y = 0.86; w = 0.14; h = 0.04;
            action = "closeDialog 0";
            colorBackground[] = SSE_UI_MUTED;
        };
    };
};

// ============================================================
// DIGITAL EXPLOITATION — onglets (idd 93250)
// ============================================================
class COMSPEC_SSE_DigitalDialog {
    idd = 93250;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "['digital'] call comspec_sse_fnc_uiOnLoad";

    class controlsBackground {
        class BG: RscText {
            idc = -1;
            x = 0.1; y = 0.06; w = 0.8; h = 0.88;
            colorBackground[] = SSE_UI_BG;
        };
        class Title: RscText {
            idc = 93251;
            text = "DIGITAL EXPLOITATION";
            x = 0.1; y = 0.06; w = 0.8; h = 0.045;
            colorBackground[] = SSE_UI_HDR;
            colorText[] = SSE_UI_ACCENT;
        };
    };

    class controls {
        class Tabs: RscStructuredText {
            idc = 93252;
            x = 0.12; y = 0.12; w = 0.76; h = 0.035;
            colorBackground[] = {0,0,0,0.2};
        };
        class Body: RscStructuredText {
            idc = 93253;
            x = 0.12; y = 0.17; w = 0.46; h = 0.62;
            colorBackground[] = {0,0,0,0.25};
        };
        class List: RscListBox {
            idc = 93254;
            x = 0.6; y = 0.17; w = 0.28; h = 0.62;
            colorBackground[] = {0,0,0,0.35};
        };

        class BtnOV: RscButton { idc=93260; text="OVERVIEW"; x=0.12; y=0.82; w=0.09; h=0.035; action="['overview'] call comspec_sse_fnc_uiDigitalTab"; colorBackground[]=SSE_UI_BTN; };
        class BtnCT: RscButton { idc=93261; text="CONTACTS"; x=0.215; y=0.82; w=0.09; h=0.035; action="['contacts'] call comspec_sse_fnc_uiDigitalTab"; colorBackground[]=SSE_UI_BTN; };
        class BtnMSG: RscButton { idc=93262; text="MSG"; x=0.31; y=0.82; w=0.07; h=0.035; action="['messages'] call comspec_sse_fnc_uiDigitalTab"; colorBackground[]=SSE_UI_BTN; };
        class BtnCALL: RscButton { idc=93263; text="APPELS"; x=0.385; y=0.82; w=0.08; h=0.035; action="['calls'] call comspec_sse_fnc_uiDigitalTab"; colorBackground[]=SSE_UI_BTN; };
        class BtnFILE: RscButton { idc=93264; text="FICHIERS"; x=0.47; y=0.82; w=0.09; h=0.035; action="['files'] call comspec_sse_fnc_uiDigitalTab"; colorBackground[]=SSE_UI_BTN; };
        class BtnPIC: RscButton { idc=93265; text="PHOTOS"; x=0.565; y=0.82; w=0.08; h=0.035; action="['photos'] call comspec_sse_fnc_uiDigitalTab"; colorBackground[]=SSE_UI_BTN; };
        class BtnLOC: RscButton { idc=93266; text="LOCS"; x=0.65; y=0.82; w=0.07; h=0.035; action="['locations'] call comspec_sse_fnc_uiDigitalTab"; colorBackground[]=SSE_UI_BTN; };
        class BtnDEL: RscButton { idc=93267; text="DELETED"; x=0.725; y=0.82; w=0.085; h=0.035; action="['deleted'] call comspec_sse_fnc_uiDigitalTab"; colorBackground[]=SSE_UI_BTN; };
        class BtnNET: RscButton { idc=93268; text="NETWORK"; x=0.12; y=0.865; w=0.1; h=0.035; action="['network'] call comspec_sse_fnc_uiDigitalTab"; colorBackground[]=SSE_UI_BTN2; };
        class BtnBack: RscButton { idc=93269; text="TERMINAL"; x=0.6; y=0.865; w=0.12; h=0.035; action="['terminal'] call comspec_sse_fnc_uiOpenScreen"; colorBackground[]=SSE_UI_BTN2; };
        class BtnClose: RscButton { idc=93270; text="FERMER"; x=0.74; y=0.865; w=0.12; h=0.035; action="closeDialog 0"; colorBackground[]=SSE_UI_MUTED; };
    };
};

// ============================================================
// SITE EXPLOITATION (idd 93300)
// ============================================================
class COMSPEC_SSE_SiteDialog {
    idd = 93300;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "['site'] call comspec_sse_fnc_uiOnLoad";

    class controlsBackground {
        class BG: RscText { idc=-1; x=0.12; y=0.08; w=0.76; h=0.84; colorBackground[]=SSE_UI_BG; };
        class Title: RscText {
            idc=93301; text="SITE EXPLOITATION";
            x=0.12; y=0.08; w=0.76; h=0.045;
            colorBackground[]=SSE_UI_HDR; colorText[]=SSE_UI_ACCENT;
        };
    };
    class controls {
        class Summary: RscStructuredText { idc=93310; x=0.14; y=0.14; w=0.72; h=0.16; colorBackground[]={0,0,0,0.25}; };
        class List: RscListBox { idc=93311; x=0.14; y=0.32; w=0.35; h=0.45; colorBackground[]={0,0,0,0.35}; };
        class Detail: RscStructuredText { idc=93312; x=0.51; y=0.32; w=0.35; h=0.45; colorBackground[]={0,0,0,0.25}; };
        class BtnTriage: RscButton { idc=93320; text="TRIAGE"; x=0.14; y=0.8; w=0.12; h=0.04; action="[] call comspec_sse_fnc_uiSiteTriage"; colorBackground[]=SSE_UI_BTN; };
        class BtnRefresh: RscButton { idc=93321; text="RAFRAÎCHIR"; x=0.28; y=0.8; w=0.14; h=0.04; action="['site'] call comspec_sse_fnc_uiRefresh"; colorBackground[]=SSE_UI_BTN2; };
        class BtnBack: RscButton { idc=93322; text="TERMINAL"; x=0.58; y=0.8; w=0.12; h=0.04; action="['terminal'] call comspec_sse_fnc_uiOpenScreen"; colorBackground[]=SSE_UI_BTN2; };
        class BtnClose: RscButton { idc=93323; text="FERMER"; x=0.72; y=0.8; w=0.12; h=0.04; action="closeDialog 0"; colorBackground[]=SSE_UI_MUTED; };
    };
};

// ============================================================
// INTELLIGENCE GRAPH (idd 93350)
// ============================================================
class COMSPEC_SSE_GraphDialog {
    idd = 93350;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "['graph'] call comspec_sse_fnc_uiOnLoad";

    class controlsBackground {
        class BG: RscText { idc=-1; x=0.08; y=0.06; w=0.84; h=0.88; colorBackground[]=SSE_UI_BG; };
        class Title: RscText {
            idc=93351; text="INTELLIGENCE GRAPH";
            x=0.08; y=0.06; w=0.84; h=0.045;
            colorBackground[]=SSE_UI_HDR; colorText[]=SSE_UI_ACCENT;
        };
    };
    class controls {
        class Nodes: RscListBox { idc=93360; x=0.1; y=0.13; w=0.28; h=0.65; colorBackground[]={0,0,0,0.35}; };
        class Edges: RscStructuredText { idc=93361; x=0.4; y=0.13; w=0.5; h=0.65; colorBackground[]={0,0,0,0.25}; };
        class BtnPivot: RscButton { idc=93370; text="PIVOT"; x=0.1; y=0.82; w=0.12; h=0.04; action="[] call comspec_sse_fnc_uiGraphPivot"; colorBackground[]=SSE_UI_BTN; };
        class BtnBack: RscButton { idc=93371; text="TERMINAL"; x=0.6; y=0.82; w=0.14; h=0.04; action="['terminal'] call comspec_sse_fnc_uiOpenScreen"; colorBackground[]=SSE_UI_BTN2; };
        class BtnClose: RscButton { idc=93372; text="FERMER"; x=0.76; y=0.82; w=0.12; h=0.04; action="closeDialog 0"; colorBackground[]=SSE_UI_MUTED; };
    };
};

// ============================================================
// EVIDENCE / CHAIN OF CUSTODY (idd 93400)
// ============================================================
class COMSPEC_SSE_EvidenceDialog {
    idd = 93400;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "['evidence'] call comspec_sse_fnc_uiOnLoad";

    class controlsBackground {
        class BG: RscText { idc=-1; x=0.1; y=0.08; w=0.8; h=0.84; colorBackground[]=SSE_UI_BG; };
        class Title: RscText {
            idc=93401; text="EVIDENCE / CHAIN OF CUSTODY";
            x=0.1; y=0.08; w=0.8; h=0.045;
            colorBackground[]=SSE_UI_HDR; colorText[]=SSE_UI_ACCENT;
        };
    };
    class controls {
        class List: RscListBox { idc=93410; x=0.12; y=0.15; w=0.36; h=0.6; colorBackground[]={0,0,0,0.35}; };
        class Detail: RscStructuredText { idc=93411; x=0.5; y=0.15; w=0.38; h=0.6; colorBackground[]={0,0,0,0.25}; };
        class BtnBag: RscButton { idc=93420; text="SOUS SCELLÉ"; x=0.12; y=0.78; w=0.14; h=0.04; action="[] call comspec_sse_fnc_uiBagSelected"; colorBackground[]=SSE_UI_BTN; };
        class BtnBack: RscButton { idc=93421; text="TERMINAL"; x=0.6; y=0.78; w=0.12; h=0.04; action="['terminal'] call comspec_sse_fnc_uiOpenScreen"; colorBackground[]=SSE_UI_BTN2; };
        class BtnClose: RscButton { idc=93422; text="FERMER"; x=0.74; y=0.78; w=0.12; h=0.04; action="closeDialog 0"; colorBackground[]=SSE_UI_MUTED; };
    };
};

// ============================================================
// MISSION INTEL (idd 93450)
// ============================================================
class COMSPEC_SSE_MissionIntelDialog {
    idd = 93450;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "['mission'] call comspec_sse_fnc_uiOnLoad";

    class controlsBackground {
        class BG: RscText { idc=-1; x=0.08; y=0.06; w=0.84; h=0.88; colorBackground[]=SSE_UI_BG; };
        class Title: RscText {
            idc=93451; text="MISSION INTEL — FUSION";
            x=0.08; y=0.06; w=0.84; h=0.045;
            colorBackground[]=SSE_UI_HDR; colorText[]=SSE_UI_ACCENT;
        };
    };
    class controls {
        class Filter: RscStructuredText { idc=93452; x=0.1; y=0.12; w=0.8; h=0.04; colorBackground[]={0,0,0,0.2}; };
        class List: RscListBox { idc=93453; x=0.1; y=0.18; w=0.8; h=0.58; colorBackground[]={0,0,0,0.35}; };
        class BtnAll: RscButton { idc=93460; text="TOUS"; x=0.1; y=0.8; w=0.1; h=0.04; action="['ALL'] call comspec_sse_fnc_uiMissionFilter"; colorBackground[]=SSE_UI_BTN; };
        class BtnObs: RscButton { idc=93461; text="OBSERVED"; x=0.21; y=0.8; w=0.12; h=0.04; action="['OBSERVED'] call comspec_sse_fnc_uiMissionFilter"; colorBackground[]=SSE_UI_BTN; };
        class BtnRep: RscButton { idc=93462; text="REPORTED"; x=0.34; y=0.8; w=0.12; h=0.04; action="['REPORTED'] call comspec_sse_fnc_uiMissionFilter"; colorBackground[]=SSE_UI_BTN; };
        class BtnAss: RscButton { idc=93463; text="ASSESSED"; x=0.47; y=0.8; w=0.12; h=0.04; action="['ASSESSED'] call comspec_sse_fnc_uiMissionFilter"; colorBackground[]=SSE_UI_BTN; };
        class BtnConf: RscButton { idc=93464; text="CONFIRMED"; x=0.6; y=0.8; w=0.13; h=0.04; action="['CONFIRMED'] call comspec_sse_fnc_uiMissionFilter"; colorBackground[]=SSE_UI_BTN; };
        class BtnBack: RscButton { idc=93465; text="TERMINAL"; x=0.1; y=0.86; w=0.14; h=0.04; action="['terminal'] call comspec_sse_fnc_uiOpenScreen"; colorBackground[]=SSE_UI_BTN2; };
        class BtnClose: RscButton { idc=93466; text="FERMER"; x=0.76; y=0.86; w=0.12; h=0.04; action="closeDialog 0"; colorBackground[]=SSE_UI_MUTED; };
    };
};

// ============================================================
// ZEUS SSE CONTROL (idd 93500)
// ============================================================
class COMSPEC_SSE_ZeusControlDialog {
    idd = 93500;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "['zeus'] call comspec_sse_fnc_uiOnLoad";

    class controlsBackground {
        class BG: RscText { idc=-1; x=0.05; y=0.04; w=0.9; h=0.92; colorBackground[]={0.05,0.05,0.08,0.97}; };
        class Title: RscText {
            idc=93501; text="ZEUS SSE CONTROL — VÉRITÉ / CONNU JOUEURS";
            x=0.05; y=0.04; w=0.9; h=0.045;
            colorBackground[]={0.25,0.12,0.05,1}; colorText[]={1,0.85,0.4,1};
        };
    };
    class controls {
        class Known: RscStructuredText { idc=93510; x=0.07; y=0.11; w=0.42; h=0.35; colorBackground[]={0,0,0,0.3}; };
        class Truth: RscStructuredText { idc=93511; x=0.51; y=0.11; w=0.42; h=0.35; colorBackground[]={0.1,0.02,0.02,0.35}; };
        class List: RscListBox { idc=93512; x=0.07; y=0.48; w=0.86; h=0.32; colorBackground[]={0,0,0,0.35}; };
        class BtnGen: RscButton { idc=93520; text="BRIEF / GÉNÉRER"; x=0.07; y=0.84; w=0.16; h=0.04; action="[] call comspec_sse_fnc_uiZeusGenerate"; colorBackground[]={0.35,0.2,0.05,1}; };
        class BtnLink: RscButton { idc=93521; text="LIER SÉLECTION"; x=0.25; y=0.84; w=0.16; h=0.04; action="[] call comspec_sse_fnc_uiZeusLink"; colorBackground[]={0.35,0.2,0.05,1}; };
        class BtnExport: RscButton { idc=93522; text="EXPORT GRAPHE"; x=0.43; y=0.84; w=0.16; h=0.04; action="[] call comspec_sse_fnc_uiZeusExport"; colorBackground[]={0.35,0.2,0.05,1}; };
        class BtnAAR: RscButton { idc=93523; text="AAR"; x=0.61; y=0.84; w=0.1; h=0.04; action="[] call comspec_sse_fnc_uiZeusAAR"; colorBackground[]={0.35,0.2,0.05,1}; };
        class BtnClose: RscButton { idc=93524; text="FERMER"; x=0.8; y=0.84; w=0.12; h=0.04; action="closeDialog 0"; colorBackground[]=SSE_UI_MUTED; };
    };
};
