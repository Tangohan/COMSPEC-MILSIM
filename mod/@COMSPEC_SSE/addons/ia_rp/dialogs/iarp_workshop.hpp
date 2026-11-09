/*
    Atelier IA (fn_iarpWorkshop) : même fenêtre en Eden (par-dessus les attributs) et en Zeus. Chaque champ a son aide
    et un exemple (infobulle, et panneau d'aide au survol). Case à droite d'un champ : verrou (Générer n'y touche pas).
    Positions sur la grille 40 x 25 de la charte SSE.
*/
#define WS_POS(X,Y,W,H) x = SSE_Q(SSE_X(X)); y = SSE_Q(SSE_Y(Y)); w = SSE_Q(SSE_W(W)); h = SSE_Q(SSE_H(H))

class COMSPEC_SSE_IarpWorkshop {
    idd = 94700;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "['onLoad', [_this select 0]] call comspec_sse_fnc_iarpWorkshop";
    onUnload = "['onUnload'] call comspec_sse_fnc_iarpWorkshop";

    class controlsBackground {
        class Back: COMSPEC_SSE_RscBackground { WS_POS(1,0.5,38,24); };
        class Head: COMSPEC_SSE_RscHeader { WS_POS(1,0.5,38,1.4); };
        class Line: COMSPEC_SSE_RscAccentLine { WS_POS(1,1.9,38,0.06); };
        class Split: COMSPEC_SSE_RscPanel { WS_POS(19.7,2.2,0.08,21.8); };
    };
    class controls {
        class Title: COMSPEC_SSE_RscText { idc = 94701; font = SSE_FONT_TITLE; colorText[] = SSE_C_ACCENT; WS_POS(1.3,0.65,24,1.1); };
        class Status: COMSPEC_SSE_RscLabel { idc = 94702; style = 1; WS_POS(25.5,0.75,13.2,0.9); };
        class LPreset: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_W_Preset"; WS_POS(1.3,2.25,6,0.9); };
        class Preset: COMSPEC_SSE_RscCombo { idc = 94710; tooltip = "$STR_COMSPEC_IARP_W_Preset_D"; onMouseEnter = "['help', ['Preset']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(7.4,2.2,6.6,1); };
        class PresetLoad: COMSPEC_SSE_RscButtonNav { idc = 94711; text = "$STR_COMSPEC_IARP_W_Load"; tooltip = "$STR_COMSPEC_IARP_W_Preset_D"; onMouseEnter = "['help', ['Preset']] call comspec_sse_fnc_iarpWorkshop"; action = "['preset'] call comspec_sse_fnc_iarpWorkshop"; WS_POS(14.1,2.2,2.4,1); };
        class Generate: COMSPEC_SSE_RscButton { idc = 94712; text = "$STR_COMSPEC_IARP_W_GenerateBtn"; tooltip = "$STR_COMSPEC_IARP_W_Generate_D"; onMouseEnter = "['help', ['Generate']] call comspec_sse_fnc_iarpWorkshop"; action = "['generate'] call comspec_sse_fnc_iarpWorkshop"; WS_POS(16.6,2.2,2.7,1); };
        class LName: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_W_Name"; WS_POS(1.3,3.55,6,0.9); };
        class Name: COMSPEC_SSE_RscEdit { idc = 94720; tooltip = "$STR_COMSPEC_IARP_W_Name_D"; onMouseEnter = "['help', ['Name']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(7.4,3.5,10.8,1); autocomplete = ""; };
        class LockName: COMSPEC_SSE_RscCheckBox { idc = 94760; tooltip = "$STR_COMSPEC_IARP_W_Lock_D"; onMouseEnter = "['help', ['Lock']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(18.4,3.55,0.9,0.9); };
        class LRole: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_W_Role"; WS_POS(1.3,4.75,6,0.9); };
        class Role: COMSPEC_SSE_RscCombo { idc = 94721; tooltip = "$STR_COMSPEC_IARP_W_Role_D"; onMouseEnter = "['help', ['Role']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(7.4,4.7,10.8,1); };
        class LockRole: COMSPEC_SSE_RscCheckBox { idc = 94761; tooltip = "$STR_COMSPEC_IARP_W_Lock_D"; onMouseEnter = "['help', ['Lock']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(18.4,4.75,0.9,0.9); };
        class LLang: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_W_Lang"; WS_POS(1.3,5.95,6,0.9); };
        class Lang: COMSPEC_SSE_RscCombo { idc = 94722; tooltip = "$STR_COMSPEC_IARP_W_Lang_D"; onMouseEnter = "['help', ['Lang']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(7.4,5.9,5.3,1); };
        class Country: COMSPEC_SSE_RscCombo { idc = 94723; tooltip = "$STR_COMSPEC_IARP_W_Lang_D"; onMouseEnter = "['help', ['Lang']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(12.9,5.9,5.3,1); };
        class LockLang: COMSPEC_SSE_RscCheckBox { idc = 94762; tooltip = "$STR_COMSPEC_IARP_W_Lock_D"; onMouseEnter = "['help', ['Lock']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(18.4,5.95,0.9,0.9); };
        class LNerve: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_W_Nerve"; WS_POS(1.3,7.2,6,0.9); };
        class SNerve: COMSPEC_SSE_RscSlider { idc = 94724; tooltip = "$STR_COMSPEC_IARP_W_Nerve_D"; onMouseEnter = "['help', ['Nerve']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(7.4,7.2,9.4,0.9); onSliderPosChanged = "['slider', [ctrlIDC (_this select 0), _this select 1]] call comspec_sse_fnc_iarpWorkshop"; };
        class VNerve: COMSPEC_SSE_RscLabel { idc = 94780; style = 1; WS_POS(16.9,7.2,1.3,0.9); };
        class LockNerve: COMSPEC_SSE_RscCheckBox { idc = 94763; tooltip = "$STR_COMSPEC_IARP_W_Lock_D"; onMouseEnter = "['help', ['Lock']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(18.4,7.2,0.9,0.9); };
        class LTalk: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_W_Talk"; WS_POS(1.3,8.3,6,0.9); };
        class STalk: COMSPEC_SSE_RscSlider { idc = 94725; tooltip = "$STR_COMSPEC_IARP_W_Talk_D"; onMouseEnter = "['help', ['Talk']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(7.4,8.3,9.4,0.9); onSliderPosChanged = "['slider', [ctrlIDC (_this select 0), _this select 1]] call comspec_sse_fnc_iarpWorkshop"; };
        class VTalk: COMSPEC_SSE_RscLabel { idc = 94781; style = 1; WS_POS(16.9,8.3,1.3,0.9); };
        class LockTalk: COMSPEC_SSE_RscCheckBox { idc = 94764; tooltip = "$STR_COMSPEC_IARP_W_Lock_D"; onMouseEnter = "['help', ['Lock']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(18.4,8.3,0.9,0.9); };
        class LAggro: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_W_Aggro"; WS_POS(1.3,9.4,6,0.9); };
        class SAggro: COMSPEC_SSE_RscSlider { idc = 94726; tooltip = "$STR_COMSPEC_IARP_W_Aggro_D"; onMouseEnter = "['help', ['Aggro']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(7.4,9.4,9.4,0.9); onSliderPosChanged = "['slider', [ctrlIDC (_this select 0), _this select 1]] call comspec_sse_fnc_iarpWorkshop"; };
        class VAggro: COMSPEC_SSE_RscLabel { idc = 94782; style = 1; WS_POS(16.9,9.4,1.3,0.9); };
        class LockAggro: COMSPEC_SSE_RscCheckBox { idc = 94765; tooltip = "$STR_COMSPEC_IARP_W_Lock_D"; onMouseEnter = "['help', ['Lock']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(18.4,9.4,0.9,0.9); };
        class LHonesty: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_W_Honesty"; WS_POS(1.3,10.5,6,0.9); };
        class SHonesty: COMSPEC_SSE_RscSlider { idc = 94727; tooltip = "$STR_COMSPEC_IARP_W_Honesty_D"; onMouseEnter = "['help', ['Honesty']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(7.4,10.5,9.4,0.9); onSliderPosChanged = "['slider', [ctrlIDC (_this select 0), _this select 1]] call comspec_sse_fnc_iarpWorkshop"; };
        class VHonesty: COMSPEC_SSE_RscLabel { idc = 94783; style = 1; WS_POS(16.9,10.5,1.3,0.9); };
        class LockHonesty: COMSPEC_SSE_RscCheckBox { idc = 94766; tooltip = "$STR_COMSPEC_IARP_W_Lock_D"; onMouseEnter = "['help', ['Lock']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(18.4,10.5,0.9,0.9); };
        class LBribe: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_W_Bribe"; WS_POS(1.3,11.6,6,0.9); };
        class SBribe: COMSPEC_SSE_RscSlider { idc = 94728; tooltip = "$STR_COMSPEC_IARP_W_Bribe_D"; onMouseEnter = "['help', ['Bribe']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(7.4,11.6,9.4,0.9); onSliderPosChanged = "['slider', [ctrlIDC (_this select 0), _this select 1]] call comspec_sse_fnc_iarpWorkshop"; };
        class VBribe: COMSPEC_SSE_RscLabel { idc = 94784; style = 1; WS_POS(16.9,11.6,1.3,0.9); };
        class LockBribe: COMSPEC_SSE_RscCheckBox { idc = 94767; tooltip = "$STR_COMSPEC_IARP_W_Lock_D"; onMouseEnter = "['help', ['Lock']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(18.4,11.6,0.9,0.9); };
        class LFear: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_W_Fear"; WS_POS(1.3,12.7,6,0.9); };
        class SFear: COMSPEC_SSE_RscSlider { idc = 94729; tooltip = "$STR_COMSPEC_IARP_W_Fear_D"; onMouseEnter = "['help', ['Fear']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(7.4,12.7,9.4,0.9); onSliderPosChanged = "['slider', [ctrlIDC (_this select 0), _this select 1]] call comspec_sse_fnc_iarpWorkshop"; };
        class VFear: COMSPEC_SSE_RscLabel { idc = 94785; style = 1; WS_POS(16.9,12.7,1.3,0.9); };
        class LockFear: COMSPEC_SSE_RscCheckBox { idc = 94768; tooltip = "$STR_COMSPEC_IARP_W_Lock_D"; onMouseEnter = "['help', ['Lock']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(18.4,12.7,0.9,0.9); };
        class LDrunk: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_W_Drunk"; WS_POS(1.3,13.8,6,0.9); };
        class SDrunk: COMSPEC_SSE_RscSlider { idc = 94730; tooltip = "$STR_COMSPEC_IARP_W_Drunk_D"; onMouseEnter = "['help', ['Drunk']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(7.4,13.8,9.4,0.9); onSliderPosChanged = "['slider', [ctrlIDC (_this select 0), _this select 1]] call comspec_sse_fnc_iarpWorkshop"; };
        class VDrunk: COMSPEC_SSE_RscLabel { idc = 94786; style = 1; WS_POS(16.9,13.8,1.3,0.9); };
        class LockDrunk: COMSPEC_SSE_RscCheckBox { idc = 94769; tooltip = "$STR_COMSPEC_IARP_W_Lock_D"; onMouseEnter = "['help', ['Lock']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(18.4,13.8,0.9,0.9); };
        class LRSearch: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_W_ReactSearch"; WS_POS(1.3,15.0,6,0.9); };
        class RSearch: COMSPEC_SSE_RscCombo { idc = 94731; tooltip = "$STR_COMSPEC_IARP_W_React_D"; onMouseEnter = "['help', ['React']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(7.4,15.0,10.8,0.95); };
        class LRThreat: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_W_ReactThreat"; WS_POS(1.3,16.05,6,0.9); };
        class RThreat: COMSPEC_SSE_RscCombo { idc = 94732; tooltip = "$STR_COMSPEC_IARP_W_React_D"; onMouseEnter = "['help', ['React']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(7.4,16.05,10.8,0.95); };
        class LRBribeR: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_W_ReactBribeR"; WS_POS(1.3,17.1,6,0.9); };
        class RBribeR: COMSPEC_SSE_RscCombo { idc = 94733; tooltip = "$STR_COMSPEC_IARP_W_React_D"; onMouseEnter = "['help', ['React']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(7.4,17.1,10.8,0.95); };
        class LROrder: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_W_ReactOrder"; WS_POS(1.3,18.15,6,0.9); };
        class ROrder: COMSPEC_SSE_RscCombo { idc = 94734; tooltip = "$STR_COMSPEC_IARP_W_React_D"; onMouseEnter = "['help', ['React']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(7.4,18.15,10.8,0.95); };
        class LockReact: COMSPEC_SSE_RscCheckBox { idc = 94770; tooltip = "$STR_COMSPEC_IARP_W_Lock_D"; onMouseEnter = "['help', ['Lock']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(18.4,15.0,0.9,0.9); };
        class LKnow: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_W_Know"; WS_POS(1.3,19.3,6,0.9); };
        class Know: COMSPEC_SSE_RscEdit { idc = 94735; tooltip = "$STR_COMSPEC_IARP_W_Know_D"; onMouseEnter = "['help', ['Know']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(7.4,19.3,10.8,1.5); style = 16; autocomplete = ""; maxChars = 4000; };
        class LockKnow: COMSPEC_SSE_RscCheckBox { idc = 94771; tooltip = "$STR_COMSPEC_IARP_W_Lock_D"; onMouseEnter = "['help', ['Lock']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(18.4,19.35,0.9,0.9); };
        class LUnlock: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_W_Unlock"; WS_POS(1.3,21.0,6,0.9); };
        class Unlock: COMSPEC_SSE_RscCombo { idc = 94736; tooltip = "$STR_COMSPEC_IARP_W_Unlock_D"; onMouseEnter = "['help', ['Unlock']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(7.4,21.0,5.3,1); };
        class UnlockWord: COMSPEC_SSE_RscEdit { idc = 94737; tooltip = "$STR_COMSPEC_IARP_W_Unlock_D"; onMouseEnter = "['help', ['Unlock']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(12.9,21.0,5.3,1); autocomplete = ""; };
        class LockUnlock: COMSPEC_SSE_RscCheckBox { idc = 94772; tooltip = "$STR_COMSPEC_IARP_W_Lock_D"; onMouseEnter = "['help', ['Lock']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(18.4,21.05,0.9,0.9); };
        class LStory: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_W_Story"; WS_POS(20.2,2.25,5,0.9); };
        class Story: COMSPEC_SSE_RscEdit { idc = 94740; tooltip = "$STR_COMSPEC_IARP_W_Story_D"; onMouseEnter = "['help', ['Story']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(25.4,2.2,12.2,3.4); style = 16; autocomplete = ""; maxChars = 4000; };
        class LockStory: COMSPEC_SSE_RscCheckBox { idc = 94773; tooltip = "$STR_COMSPEC_IARP_W_Lock_D"; onMouseEnter = "['help', ['Lock']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(37.8,2.25,0.9,0.9); };
        class LPhrases: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_W_Phrases"; WS_POS(20.2,5.85,5,0.9); };
        class Phrases: COMSPEC_SSE_RscEdit { idc = 94741; tooltip = "$STR_COMSPEC_IARP_W_Phrases_D"; onMouseEnter = "['help', ['Phrases']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(25.4,5.8,12.2,2.2); style = 16; autocomplete = ""; maxChars = 4000; };
        class LockPhrases: COMSPEC_SSE_RscCheckBox { idc = 94774; tooltip = "$STR_COMSPEC_IARP_W_Lock_D"; onMouseEnter = "['help', ['Lock']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(37.8,5.85,0.9,0.9); };
        class LSensitive: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_W_Sensitive"; WS_POS(20.2,8.25,5,0.9); };
        class Sensitive: COMSPEC_SSE_RscEdit { idc = 94742; tooltip = "$STR_COMSPEC_IARP_W_Sensitive_D"; onMouseEnter = "['help', ['Sensitive']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(25.4,8.2,12.2,1); autocomplete = ""; };
        class LockSensitive: COMSPEC_SSE_RscCheckBox { idc = 94775; tooltip = "$STR_COMSPEC_IARP_W_Lock_D"; onMouseEnter = "['help', ['Lock']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(37.8,8.25,0.9,0.9); };
        class LSecrets: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_W_Secrets"; WS_POS(20.2,9.45,5,0.9); };
        class Secrets: COMSPEC_SSE_RscEdit { idc = 94743; tooltip = "$STR_COMSPEC_IARP_W_Secrets_D"; onMouseEnter = "['help', ['Secrets']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(25.4,9.4,12.2,1.6); style = 16; autocomplete = ""; maxChars = 4000; };
        class LockSecrets: COMSPEC_SSE_RscCheckBox { idc = 94776; tooltip = "$STR_COMSPEC_IARP_W_Lock_D"; onMouseEnter = "['help', ['Lock']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(37.8,9.45,0.9,0.9); };
        class LTest: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_W_Test"; WS_POS(20.2,11.35,5,0.9); };
        class TestIn: COMSPEC_SSE_RscEdit { idc = 94744; tooltip = "$STR_COMSPEC_IARP_W_Test_D"; onMouseEnter = "['help', ['Test']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(25.4,11.3,9.8,1); autocomplete = ""; };
        class TestGo: COMSPEC_SSE_RscButton { idc = 94745; text = "$STR_COMSPEC_IARP_W_TestGo"; tooltip = "$STR_COMSPEC_IARP_W_Test_D"; onMouseEnter = "['help', ['Test']] call comspec_sse_fnc_iarpWorkshop"; action = "['test'] call comspec_sse_fnc_iarpWorkshop"; WS_POS(35.3,11.3,3.4,1); };
        class TestOut: COMSPEC_SSE_RscStructuredText { idc = 94746; WS_POS(20.2,12.4,18.5,2.6); };
        class LSay: COMSPEC_SSE_RscLabel { idc = 94791; text = "$STR_COMSPEC_IARP_W_Say"; WS_POS(20.2,15.25,5,0.9); };
        class SayIn: COMSPEC_SSE_RscEdit { idc = 94747; tooltip = "$STR_COMSPEC_IARP_W_Say_D"; onMouseEnter = "['help', ['Say']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(25.4,15.2,9.8,1); autocomplete = ""; };
        class SayGo: COMSPEC_SSE_RscButtonZeus { idc = 94748; text = "$STR_COMSPEC_IARP_W_SayGo"; tooltip = "$STR_COMSPEC_IARP_W_Say_D"; onMouseEnter = "['help', ['Say']] call comspec_sse_fnc_iarpWorkshop"; action = "['say'] call comspec_sse_fnc_iarpWorkshop"; WS_POS(35.3,15.2,3.4,1); };
        class LIo: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_W_ImportExport"; WS_POS(20.2,16.55,5,0.9); };
        class IoText: COMSPEC_SSE_RscEdit { idc = 94749; tooltip = "$STR_COMSPEC_IARP_W_ImportExport_D"; onMouseEnter = "['help', ['ImportExport']] call comspec_sse_fnc_iarpWorkshop"; WS_POS(25.4,16.5,12.2,1); autocomplete = ""; maxChars = 8000; };
        class Import: COMSPEC_SSE_RscButtonNav { idc = 94750; text = "$STR_COMSPEC_IARP_W_Import"; tooltip = "$STR_COMSPEC_IARP_W_ImportExport_D"; onMouseEnter = "['help', ['ImportExport']] call comspec_sse_fnc_iarpWorkshop"; action = "['import'] call comspec_sse_fnc_iarpWorkshop"; WS_POS(25.4,17.6,6,1); };
        class Export: COMSPEC_SSE_RscButtonNav { idc = 94751; text = "$STR_COMSPEC_IARP_W_Export"; tooltip = "$STR_COMSPEC_IARP_W_ImportExport_D"; onMouseEnter = "['help', ['ImportExport']] call comspec_sse_fnc_iarpWorkshop"; action = "['export'] call comspec_sse_fnc_iarpWorkshop"; WS_POS(31.6,17.6,7.1,1); };
        class Help: COMSPEC_SSE_RscStructuredText { idc = 94752; WS_POS(20.2,18.9,18.5,3.7); size = SSE_Q(SSE_GH * 0.78); };
        class Apply: COMSPEC_SSE_RscButton { idc = 94753; text = "$STR_COMSPEC_IARP_W_ApplyBtn"; tooltip = "$STR_COMSPEC_IARP_W_Apply_D"; onMouseEnter = "['help', ['Apply']] call comspec_sse_fnc_iarpWorkshop"; action = "['apply'] call comspec_sse_fnc_iarpWorkshop"; WS_POS(20.2,22.9,6,1.2); };
        class ApplyGen: COMSPEC_SSE_RscButtonZeus { idc = 94754; text = "$STR_COMSPEC_IARP_W_ApplyGenBtn"; tooltip = "$STR_COMSPEC_IARP_W_ApplyGen_D"; onMouseEnter = "['help', ['ApplyGen']] call comspec_sse_fnc_iarpWorkshop"; action = "['applyGen'] call comspec_sse_fnc_iarpWorkshop"; WS_POS(26.4,22.9,7.6,1.2); };
        class Close: COMSPEC_SSE_RscButtonClose { idc = 94755; text = "$STR_COMSPEC_IARP_W_CloseBtn"; tooltip = "$STR_COMSPEC_IARP_W_CloseBtn_D"; action = "(ctrlParent (_this select 0)) closeDisplay 2"; WS_POS(34.2,22.9,4.5,1.2); };
    };
};
