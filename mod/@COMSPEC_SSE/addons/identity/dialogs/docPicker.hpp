// Sélecteur de document (fn_idDocPicker) : Zeus (module « Donner des papiers ») et Eden (menu COMSPEC SSE).
// Pays à gauche, types au milieu (par catégorie, extensions nommées), aperçu à droite, puis nature, qualité, nom
// imprimé, champs propres au document, cible (personne visée ou « Poser au sol »). Positions en fractions de la zone sûre.
// RscText, RscButton, RscStructuredText, RscPicture, RscListBox, RscEdit, RscCombo, RscXSliderH, RscCheckBox : config.cpp.
#define DP_X(F) "safezoneX + safezoneW * (F)"
#define DP_Y(F) "safezoneY + safezoneH * (F)"
#define DP_W(F) "safezoneW * (F)"
#define DP_H(F) "safezoneH * (F)"
#define DP_POS(X,Y,W,H) x = DP_X(X); y = DP_Y(Y); w = DP_W(W); h = DP_H(H)
#define DP_LABEL(NAME,IDC,X,Y,W,TXT) class NAME: RscText { idc = IDC; text = TXT; DP_POS(X,Y,W,0.028); colorText[] = {0.72, 0.76, 0.8, 1}; sizeEx = "safezoneH * 0.022"; }

class COMSPEC_SSE_DocPicker {
    idd = 93800;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "(_this select 0) call comspec_sse_fnc_trDisplay; ['load', _this select 0] call comspec_sse_fnc_idDocPicker";
    onUnload = "['unload'] call comspec_sse_fnc_idDocPicker";

    class controlsBackground {
        class Dim: RscText { idc = -1; x = "safezoneX"; y = "safezoneY"; w = "safezoneW"; h = "safezoneH"; colorBackground[] = {0, 0, 0, 0.6}; };
        class Back: RscText { idc = -1; DP_POS(0.1,0.08,0.8,0.84); colorBackground[] = {0.1, 0.11, 0.12, 0.97}; };
        class TitleBar: RscText { idc = -1; DP_POS(0.1,0.08,0.8,0.05); colorBackground[] = {0.12, 0.2, 0.3, 1}; };
    };

    class controls {
        class Title: RscText { idc = 93801; text = "CHOISIR UN DOCUMENT"; DP_POS(0.115,0.08,0.6,0.05); font = "PuristaSemibold"; sizeEx = "safezoneH * 0.03"; };
        class Target: RscText { idc = 93802; text = ""; DP_POS(0.5,0.08,0.38,0.05); style = 1; sizeEx = "safezoneH * 0.022"; colorText[] = {0.8, 0.85, 0.9, 1}; };

        DP_LABEL(LblCountry, -1, 0.115, 0.14, 0.17, "Pays");
        class Countries: RscListBox { idc = 93810; DP_POS(0.115,0.17,0.17,0.40); sizeEx = "safezoneH * 0.024"; colorBackground[] = {0.07, 0.08, 0.09, 1}; };
        DP_LABEL(LblType, -1, 0.295, 0.14, 0.22, "Type de document");
        class Types: RscListBox { idc = 93811; DP_POS(0.295,0.17,0.22,0.40); sizeEx = "safezoneH * 0.024"; colorBackground[] = {0.07, 0.08, 0.09, 1}; };
        DP_LABEL(LblPreview, -1, 0.53, 0.14, 0.35, "Aperçu");
        class PreviewBack: RscText { idc = 93821; DP_POS(0.53,0.17,0.355,0.33); colorBackground[] = {0.05, 0.06, 0.07, 1}; };
        class Preview: RscPicture { idc = 93820; text = ""; DP_POS(0.54,0.18,0.335,0.31); };
        class Info: RscStructuredText { idc = 93822; DP_POS(0.53,0.505,0.355,0.065); colorBackground[] = {0, 0, 0, 0}; size = "safezoneH * 0.021"; };

        DP_LABEL(LblKind, -1, 0.115, 0.585, 0.17, "Nature");
        class Kind: RscCombo { idc = 93830; DP_POS(0.115,0.615,0.17,0.032); sizeEx = "safezoneH * 0.022"; };
        DP_LABEL(LblForgery, -1, 0.295, 0.585, 0.22, "Procédé du faux");
        class Forgery: RscCombo { idc = 93831; DP_POS(0.295,0.615,0.22,0.032); sizeEx = "safezoneH * 0.022"; };
        DP_LABEL(LblQuality, 93833, 0.53, 0.585, 0.355, "Qualité du faux : 50");
        class Quality: RscXSliderH { idc = 93832; DP_POS(0.53,0.615,0.355,0.032); };

        DP_LABEL(LblLast, -1, 0.115, 0.66, 0.17, "Nom imprimé");
        class Last: RscEdit { idc = 93834; text = ""; DP_POS(0.115,0.69,0.17,0.032); sizeEx = "safezoneH * 0.022"; };
        DP_LABEL(LblFirst, -1, 0.295, 0.66, 0.22, "Prénom imprimé");
        class First: RscEdit { idc = 93835; text = ""; DP_POS(0.295,0.69,0.22,0.032); sizeEx = "safezoneH * 0.022"; };
        DP_LABEL(LblAthena, -1, 0.53, 0.66, 0.355, "Numéro Athena (facultatif)");
        class Athena: RscEdit { idc = 93836; text = ""; DP_POS(0.53,0.69,0.355,0.032); sizeEx = "safezoneH * 0.022"; };

        // Champs propres au document (input = 1 dans CfgComspecDocuments) : trois au plus, masqués sinon.
        DP_LABEL(LblIn0, 93845, 0.115, 0.735, 0.17, "");
        class In0: RscEdit { idc = 93840; text = ""; DP_POS(0.115,0.765,0.17,0.032); sizeEx = "safezoneH * 0.022"; };
        DP_LABEL(LblIn1, 93846, 0.295, 0.735, 0.22, "");
        class In1: RscEdit { idc = 93841; text = ""; DP_POS(0.295,0.765,0.22,0.032); sizeEx = "safezoneH * 0.022"; };
        DP_LABEL(LblIn2, 93847, 0.53, 0.735, 0.355, "");
        class In2: RscEdit { idc = 93842; text = ""; DP_POS(0.53,0.765,0.355,0.032); sizeEx = "safezoneH * 0.022"; };

        class Ground: RscCheckBox { idc = 93851; DP_POS(0.115,0.815,0.025,0.03); };
        class GroundLbl: RscText { idc = 93852; text = "Poser au sol (à l'endroit choisi), au lieu de le remettre à la personne"; DP_POS(0.145,0.815,0.5,0.03); sizeEx = "safezoneH * 0.022"; };
        class Status: RscStructuredText { idc = 93862; DP_POS(0.115,0.855,0.5,0.05); colorBackground[] = {0, 0, 0, 0}; size = "safezoneH * 0.021"; };
        class BtnGive: RscButton { idc = 93860; text = "DONNER"; DP_POS(0.66,0.86,0.11,0.04); action = "['give'] call comspec_sse_fnc_idDocPicker"; colorBackground[] = {0.16, 0.28, 0.2, 1}; };
        class BtnClose: RscButton { idc = 93861; text = "FERMER"; DP_POS(0.78,0.86,0.105,0.04); action = "closeDialog 2"; colorBackground[] = {0.3, 0.1, 0.1, 1}; };
    };
};
