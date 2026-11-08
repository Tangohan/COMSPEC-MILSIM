// Éditeur SSE et bibliothèque (maquette Main.dc.html, Bibliotheque.dc.html). Mêmes couleurs que la maquette, grille 40 x 25
// de la charte (ui_macros.hpp). Textes, sélection et aperçu posés par comspec_sse_fnc_editor, sans aucun script par image.

#define ED_BG      {0.039,0.059,0.051,1}
#define ED_PANEL   {0.047,0.071,0.063,1}
#define ED_BAR     {0.055,0.082,0.071,1}
#define ED_FIELD   {0.067,0.094,0.082,1}
#define ED_LINE    {0.133,0.188,0.165,1}
#define ED_TEXT    {0.914,0.941,0.925,1}
#define ED_MUTED   {0.624,0.690,0.659,1}
#define ED_GREEN   {0.361,0.780,0.420,1}
#define ED_INK     {0.027,0.063,0.043,1}

#define ED_POS(X,Y,W,H) x = SSE_Q(SSE_X(X)); y = SSE_Q(SSE_Y(Y)); w = SSE_Q(SSE_W(W)); h = SSE_Q(SSE_H(H))
#define ED_TXT(N) sizeEx = SSE_Q(SSE_GH * N)

class COMSPEC_SSE_EdText: RscText {
    idc = -1;
    style = 0;
    shadow = 0;
    font = "RobotoCondensed";
    colorText[] = ED_TEXT;
    colorBackground[] = {0, 0, 0, 0};
    ED_TXT(0.85);
};
class COMSPEC_SSE_EdMono: COMSPEC_SSE_EdText {
    font = "EtelkaMonospacePro";
    colorText[] = ED_MUTED;
    ED_TXT(0.62);
};
class COMSPEC_SSE_EdFill: COMSPEC_SSE_EdText {
    colorBackground[] = ED_PANEL;
};
class COMSPEC_SSE_EdPicture: RscPicture {
    idc = -1;
    style = 2096;
    colorText[] = ED_TEXT;
    colorBackground[] = {0, 0, 0, 0};
};
class COMSPEC_SSE_EdButton: RscButton {
    idc = -1;
    shadow = 0;
    style = 2;
    font = "PuristaSemibold";
    colorText[] = ED_TEXT;
    colorDisabled[] = ED_MUTED;
    colorBackground[] = ED_FIELD;
    colorBackgroundActive[] = {0.09,0.157,0.122,1};
    colorBackgroundDisabled[] = ED_FIELD;
    colorFocused[] = ED_FIELD;
    colorShadow[] = {0, 0, 0, 0};
    colorBorder[] = {0, 0, 0, 0};
    offsetPressedX = 0;
    offsetPressedY = 0;
    ED_TXT(0.8);
};
class COMSPEC_SSE_EdPrimary: COMSPEC_SSE_EdButton {
    colorText[] = ED_INK;
    colorBackground[] = ED_GREEN;
    colorBackgroundActive[] = {0.48,0.86,0.53,1};
    colorFocused[] = ED_GREEN;
};
// Carte cliquable : bouton transparent par-dessus l'icône et les textes, le fond suit la sélection.
class COMSPEC_SSE_EdCardHit: COMSPEC_SSE_EdButton {
    colorText[] = {0, 0, 0, 0};
    colorBackground[] = {0, 0, 0, 0};
    colorBackgroundActive[] = {1, 1, 1, 0.05};
    colorBackgroundDisabled[] = {0, 0, 0, 0};
    colorFocused[] = {1, 1, 1, 0.03};
};
class COMSPEC_SSE_EdEdit: RscEdit {
    idc = -1;
    shadow = 0;
    font = "RobotoCondensed";
    colorText[] = ED_TEXT;
    colorBackground[] = ED_FIELD;
    colorSelection[] = {0.361,0.780,0.420,0.45};
    autocomplete = "";
    ED_TXT(0.85);
};
class COMSPEC_SSE_EdCombo: RscCombo {
    idc = -1;
    font = "RobotoCondensed";
    colorText[] = ED_TEXT;
    colorBackground[] = ED_FIELD;
    colorSelect[] = ED_TEXT;
    colorSelectBackground[] = {0.09,0.157,0.122,1};
    ED_TXT(0.85);
};

// PICK : argument de macro. Une virgule, même dans une chaîne, y est perdue au préprocesseur : ['type', 1] devenait
// ['type' 1], erreur de script à chaque clic (RPT). D'où (['type'] + [1]) ; test : tests/Unit/SseDialogMacroCommaTest.php.
// Carte : fond (IDC0), icône (IDC1), titre (IDC2), aide (IDC3), zone cliquable (IDC4). IDC : préfixe à 4 chiffres.
#define ED_CARD(NAME,IDC,X,Y,W,H,PICK) \
    class NAME##_Bg: COMSPEC_SSE_EdText { idc = IDC##0; ED_POS(X,Y,W,H); colorBackground[] = ED_FIELD; }; \
    class NAME##_Ico: COMSPEC_SSE_EdPicture { idc = IDC##1; ED_POS((X) + 0.3,(Y) + 0.35,1.1,1.1); }; \
    class NAME##_Tit: COMSPEC_SSE_EdText { idc = IDC##2; ED_POS((X) + 1.5,(Y) + 0.1,(W) - 1.6,1); font = "PuristaSemibold"; }; \
    class NAME##_Hint: COMSPEC_SSE_EdText { idc = IDC##3; style = 16; lineSpacing = 1; ED_POS((X) + 1.5,(Y) + 1,(W) - 1.6,1.5); colorText[] = ED_MUTED; ED_TXT(0.62); }; \
    class NAME##_Hit: COMSPEC_SSE_EdCardHit { idc = IDC##4; ED_POS(X,Y,W,H); action = PICK; }

class COMSPEC_SSE_Editor {
    idd = 94000;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "(_this select 0) call comspec_sse_fnc_trDisplay; ['load', _this select 0] call comspec_sse_fnc_editor";
    onUnload = "['unload'] call comspec_sse_fnc_editor";

    class controlsBackground {
        class Back: COMSPEC_SSE_EdText { ED_POS(0,0,40,25); colorBackground[] = ED_BG; };
    };

    class controls {
        // ------------------------------------------------------------ en-tête commun
        class HeadBar: COMSPEC_SSE_EdText { ED_POS(0,0,40,1.8); colorBackground[] = ED_BAR; };
        class Logo: COMSPEC_SSE_EdPicture { idc = 94001; text = "\z\comspec_sse\addons\editor\data\logo_athena_ca.paa"; ED_POS(0.4,0.2,2.8,1.4); };
        class HeadTitle: COMSPEC_SSE_EdMono { idc = 94002; text = "SSE / ÉDITEUR"; ED_POS(3.4,0.4,10,1); };
        class HeadStatus: COMSPEC_SSE_EdMono { idc = 94003; style = 1; ED_POS(10,0.4,15.8,1); };
        class HeadAi: COMSPEC_SSE_EdButton { idc = 94006; text = "IA DE LA ZONE"; ED_POS(26,0.35,4.8,1.1); action = "['aiPage'] call comspec_sse_fnc_editor"; };
        class HeadNav: COMSPEC_SSE_EdButton { idc = 94004; text = "BIBLIOTHÈQUE"; ED_POS(31,0.35,6,1.1); action = "['toggleLibrary'] call comspec_sse_fnc_editor"; };
        class HeadClose: COMSPEC_SSE_EdButton { idc = 94005; text = "FERMER"; ED_POS(37.2,0.35,2.4,1.1); action = "['close'] call comspec_sse_fnc_editor"; };

        // ------------------------------------------------------------ éditeur : 1. que créer ?
        class NavFill: COMSPEC_SSE_EdFill { idc = 94010; ED_POS(0,1.8,8.2,21.4); };
        class NavTitle: COMSPEC_SSE_EdMono { idc = 94011; text = "1. QUE CRÉER ?"; ED_POS(0.3,2.1,7.6,0.9); };
        ED_CARD(Type0,9410,0.3,3.1,7.6,2.6,"(['type'] + [0]) call comspec_sse_fnc_editor");
        ED_CARD(Type1,9411,0.3,5.8,7.6,2.6,"(['type'] + [1]) call comspec_sse_fnc_editor");
        ED_CARD(Type2,9412,0.3,8.5,7.6,2.6,"(['type'] + [2]) call comspec_sse_fnc_editor");
        ED_CARD(Type3,9413,0.3,11.2,7.6,2.6,"(['type'] + [3]) call comspec_sse_fnc_editor");
        ED_CARD(Type4,9414,0.3,13.9,7.6,2.6,"(['type'] + [4]) call comspec_sse_fnc_editor");

        // ------------------------------------------------------------ éditeur : formulaire
        class Heading: COMSPEC_SSE_EdText { idc = 94020; font = "PuristaBold"; ED_POS(8.7,1.95,16.2,1.2); ED_TXT(1.1); };
        class SubHeading: COMSPEC_SSE_EdText { idc = 94021; ED_POS(8.7,3.05,16.2,0.85); colorText[] = ED_MUTED; ED_TXT(0.68); };
        class ModelLabel: COMSPEC_SSE_EdMono { idc = 94022; text = "PARTIR DE"; ED_POS(8.7,4.0,3,1); };
        class Model0: COMSPEC_SSE_EdButton { idc = 94030; ED_POS(11.8,3.95,4.25,1.05); action = "['model', 0] call comspec_sse_fnc_editor"; font = "RobotoCondensed"; };
        class Model1: COMSPEC_SSE_EdButton { idc = 94031; ED_POS(16.2,3.95,4.25,1.05); action = "['model', 1] call comspec_sse_fnc_editor"; font = "RobotoCondensed"; };
        class Model2: COMSPEC_SSE_EdButton { idc = 94032; ED_POS(20.65,3.95,4.25,1.05); action = "['model', 2] call comspec_sse_fnc_editor"; font = "RobotoCondensed"; };
        class TitleLabel: COMSPEC_SSE_EdText { idc = 94040; text = "Titre"; ED_POS(8.7,5.15,8,0.8); ED_TXT(0.72); };
        class TitleEdit: COMSPEC_SSE_EdEdit { idc = 94041; ED_POS(8.7,5.9,8,1.15); onKeyUp = "['preview'] call comspec_sse_fnc_editor"; };
        class ComboLabel: COMSPEC_SSE_EdText { idc = 94042; text = "Classification"; ED_POS(16.9,5.15,8,0.8); ED_TXT(0.72); };
        class ComboMain: COMSPEC_SSE_EdCombo { idc = 94043; ED_POS(16.9,5.9,8,1.15); onLBSelChanged = "['preview'] call comspec_sse_fnc_editor"; };
        class Combo2Label: COMSPEC_SSE_EdText { idc = 94044; text = "Nature"; ED_POS(20.9,5.15,4,0.8); ED_TXT(0.72); };
        class Combo2: COMSPEC_SSE_EdCombo { idc = 94045; ED_POS(20.9,5.9,4,1.15); onLBSelChanged = "['preview'] call comspec_sse_fnc_editor"; };
        class BodyLabel: COMSPEC_SSE_EdText { idc = 94046; text = "Contenu"; ED_POS(8.7,7.15,16.2,0.8); ED_TXT(0.72); };
        class BodyEdit: COMSPEC_SSE_EdEdit { idc = 94047; style = 16; lineSpacing = 1; ED_POS(8.7,7.9,16.2,8.6); onKeyUp = "['preview'] call comspec_sse_fnc_editor"; };
        class DeliveryTitle: COMSPEC_SSE_EdMono { idc = 94048; text = "2. COMMENT LES JOUEURS L'OBTIENNENT"; ED_POS(8.7,16.7,16.2,0.9); };
        ED_CARD(Del0,9420,8.7,17.6,5.3,2.8,"(['delivery'] + [0]) call comspec_sse_fnc_editor");
        ED_CARD(Del1,9421,14.15,17.6,5.3,2.8,"(['delivery'] + [1]) call comspec_sse_fnc_editor");
        ED_CARD(Del2,9422,19.6,17.6,5.3,2.8,"(['delivery'] + [2]) call comspec_sse_fnc_editor");

        // ------------------------------------------------------------ éditeur : aperçu en direct
        class SideFill: COMSPEC_SSE_EdFill { idc = 94050; ED_POS(25.2,1.8,14.8,21.4); };
        class SideTitle: COMSPEC_SSE_EdMono { idc = 94051; text = "APERÇU EN JEU"; ED_POS(25.7,2.1,6,0.9); };
        class SideWho: COMSPEC_SSE_EdText { idc = 94052; style = 1; text = "Vu comme : le joueur qui le trouve"; colorText[] = ED_GREEN; ED_POS(31,2.1,8.5,0.9); ED_TXT(0.68); };
        class Paper: COMSPEC_SSE_EdText { idc = 94053; colorBackground[] = {0.91,0.894,0.847,1}; ED_POS(25.7,3.1,13.8,17.5); };
        class PaperText: RscStructuredText { idc = 94054; colorBackground[] = {0, 0, 0, 0}; ED_POS(26.2,3.5,12.8,16.7); size = SSE_Q(SSE_GH * 0.8); };
        class SideNote: COMSPEC_SSE_EdText { idc = 94055; colorText[] = ED_MUTED; ED_POS(25.7,20.9,13.8,1.8); ED_TXT(0.68); };

        // ------------------------------------------------------------ éditeur : pied
        class FootBar: COMSPEC_SSE_EdText { idc = 94063; ED_POS(0,23.2,40,1.8); colorBackground[] = ED_BAR; };
        class FootNote: COMSPEC_SSE_EdText { idc = 94060; colorText[] = ED_MUTED; text = "Rien n'est envoyé aux joueurs avant « Placer et publier ». Le contenu reste enregistré sur ce poste."; ED_POS(0.5,23.6,23,1); ED_TXT(0.68); };
        class FootSave: COMSPEC_SSE_EdButton { idc = 94061; text = "ENREGISTRER"; ED_POS(25.2,23.5,6.4,1.25); action = "['save'] call comspec_sse_fnc_editor"; };
        class FootPlace: COMSPEC_SSE_EdPrimary { idc = 94062; text = "PLACER ET PUBLIER"; ED_POS(31.8,23.5,7.8,1.25); action = "['publish'] call comspec_sse_fnc_editor"; };

        // ------------------------------------------------------------ bibliothèque
        class LibNavFill: COMSPEC_SSE_EdFill { idc = 94300; ED_POS(0,1.8,6.2,23.2); };
        class LibF0: COMSPEC_SSE_EdButton { idc = 94310; text = "Tout"; style = 0; font = "RobotoCondensed"; ED_POS(0.3,2.2,5.6,1.2); action = "['filter', 0] call comspec_sse_fnc_editor"; };
        class LibF1: LibF0 { idc = 94311; text = "Renseignements"; ED_POS(0.3,3.5,5.6,1.2); action = "['filter', 1] call comspec_sse_fnc_editor"; };
        class LibF2: LibF0 { idc = 94312; text = "Identités"; ED_POS(0.3,4.8,5.6,1.2); action = "['filter', 2] call comspec_sse_fnc_editor"; };
        class LibF3: LibF0 { idc = 94313; text = "Papiers"; ED_POS(0.3,6.1,5.6,1.2); action = "['filter', 3] call comspec_sse_fnc_editor"; };
        class LibF4: LibF0 { idc = 94314; text = "Dossiers"; ED_POS(0.3,7.4,5.6,1.2); action = "['filter', 4] call comspec_sse_fnc_editor"; };
        class LibF5: LibF0 { idc = 94315; text = "Kits"; ED_POS(0.3,8.7,5.6,1.2); action = "['filter', 5] call comspec_sse_fnc_editor"; };
        class LibF6: LibF0 { idc = 94316; text = "Modèles"; ED_POS(0.3,10.4,5.6,1.2); action = "['filter', 6] call comspec_sse_fnc_editor"; };
        class LibF7: LibF0 { idc = 94317; text = "Archives"; ED_POS(0.3,11.7,5.6,1.2); action = "['filter', 7] call comspec_sse_fnc_editor"; };
        class LibSearchLabel: COMSPEC_SSE_EdText { idc = 94320; text = "Rechercher : titre, personne, opération, numéro de papier"; ED_POS(6.7,2.1,22,0.9); ED_TXT(0.72); };
        class LibSearch: COMSPEC_SSE_EdEdit { idc = 94321; ED_POS(6.7,2.9,22,1.2); onKeyUp = "['libFill'] call comspec_sse_fnc_editor"; };
        class LibList: RscListNBox {
            idc = 94322;
            ED_POS(6.7,4.4,22,18.2);
            rowHeight = SSE_Q(SSE_GH * 1.4);
            font = "RobotoCondensed";
            shadow = 0;
            drawSideArrows = 0;
            columns[] = {0, 0.05, 0.5, 0.66, 0.84};
            colorText[] = ED_TEXT;
            colorBackground[] = ED_FIELD;
            colorSelect[] = ED_TEXT;
            colorSelect2[] = ED_TEXT;
            colorSelectBackground[] = {0.09,0.157,0.122,1};
            colorSelectBackground2[] = {0.09,0.157,0.122,1};
            onLBSelChanged = "['libSelect'] call comspec_sse_fnc_editor";
            ED_TXT(0.8);
        };
        class LibEmpty: COMSPEC_SSE_EdText { idc = 94323; style = 2; colorText[] = ED_MUTED; ED_POS(6.7,10,22,1.2); };
        class LibSideFill: COMSPEC_SSE_EdFill { idc = 94330; ED_POS(29.2,1.8,10.8,23.2); };
        class LibSideTitle: COMSPEC_SSE_EdMono { idc = 94331; text = "SÉLECTION"; ED_POS(29.6,2.1,10,0.9); };
        class LibSelName: RscStructuredText { idc = 94332; colorBackground[] = {0, 0, 0, 0}; ED_POS(29.6,3,10,2.7); size = SSE_Q(SSE_GH * 1.05); };
        class LibEditBtn: COMSPEC_SSE_EdButton { idc = 94333; text = "Modifier"; font = "RobotoCondensed"; ED_POS(29.6,5.9,4.9,1.3); action = "['libEdit'] call comspec_sse_fnc_editor"; };
        class LibDupBtn: LibEditBtn { idc = 94334; text = "Dupliquer"; ED_POS(34.7,5.9,4.9,1.3); action = "['libDuplicate'] call comspec_sse_fnc_editor"; };
        class LibPlaceBtn: LibEditBtn { idc = 94335; text = "Placer dans le monde"; ED_POS(29.6,7.3,4.9,1.3); action = "['libPlace'] call comspec_sse_fnc_editor"; };
        class LibArchBtn: LibEditBtn { idc = 94336; text = "Archiver"; ED_POS(34.7,7.3,4.9,1.3); action = "['libArchive'] call comspec_sse_fnc_editor"; };
        class LibHistTitle: COMSPEC_SSE_EdMono { idc = 94337; text = "HISTORIQUE"; ED_POS(29.6,8.9,10,0.9); };
        class LibHist: RscStructuredText { idc = 94338; colorBackground[] = {0, 0, 0, 0}; ED_POS(29.6,9.8,10,10.4); size = SSE_Q(SSE_GH * 0.72); };
        class LibLinkTitle: COMSPEC_SSE_EdMono { idc = 94339; text = "LIÉ À"; ED_POS(29.6,20.4,10,0.9); };
        class LibLink: COMSPEC_SSE_EdEdit { idc = 94340; ED_POS(29.6,21.3,10,1.2); onKeyUp = "['libLink'] call comspec_sse_fnc_editor"; };

        // ------------------------------------------------------------ IA de la zone (fn_editorAi)
        class AiFill: COMSPEC_SSE_EdFill { idc = 94500; ED_POS(0,1.8,40,23.2); };
        class AiLabel: COMSPEC_SSE_EdMono { idc = 94501; text = "DÉTECTER"; ED_POS(0.4,2.1,3,0.9); };
        class AiRadius: COMSPEC_SSE_EdCombo { idc = 94502; ED_POS(3.2,2.05,4,1.15); };
        class AiScan: COMSPEC_SSE_EdButton { idc = 94503; text = "AUTOUR"; ED_POS(7.4,2.05,3.2,1.15); action = "['scan'] call comspec_sse_fnc_editorAi"; };
        class AiSel: COMSPEC_SSE_EdButton { idc = 94504; text = "SÉLECTION ZEUS"; ED_POS(10.8,2.05,5.2,1.15); action = "['scanZeus'] call comspec_sse_fnc_editorAi"; };
        class AiCount: COMSPEC_SSE_EdText { idc = 94505; colorText[] = ED_MUTED; ED_POS(16.2,2.1,6.3,1); };
        class AiList: RscListNBox {
            idc = 94510;
            ED_POS(0.4,3.4,22.1,12.2);
            rowHeight = SSE_Q(SSE_GH * 1.2);
            font = "RobotoCondensed";
            shadow = 0;
            drawSideArrows = 0;
            columns[] = {0, 0.36, 0.48, 0.68, 0.84};
            colorText[] = ED_TEXT;
            colorBackground[] = ED_FIELD;
            colorSelect[] = ED_TEXT;
            colorSelect2[] = ED_TEXT;
            colorSelectBackground[] = {0.09,0.157,0.122,1};
            colorSelectBackground2[] = {0.09,0.157,0.122,1};
            onLBSelChanged = "['select'] call comspec_sse_fnc_editorAi";
            ED_TXT(0.74);
        };
        class AiDocsTitle: COMSPEC_SSE_EdMono { idc = 94511; text = "SES PAPIERS"; ED_POS(0.4,15.8,8,0.9); };
        class AiDocs: COMSPEC_SSE_EdCombo { idc = 94512; ED_POS(0.4,16.7,22.1,1.15); };
        class AiDocOpen: COMSPEC_SSE_EdButton { idc = 94513; text = "OUVRIR"; ED_POS(0.4,18,5.3,1.15); action = "['docOpen'] call comspec_sse_fnc_editorAi"; };
        class AiDocTrue: COMSPEC_SSE_EdButton { idc = 94514; text = "MARQUER VRAI"; ED_POS(5.85,18,5.3,1.15); action = "['docMark'] call comspec_sse_fnc_editorAi"; };
        class AiDocFalse: COMSPEC_SSE_EdButton { idc = 94515; text = "MARQUER FAUX"; ED_POS(11.3,18,5.3,1.15); action = "['docMarkFalse'] call comspec_sse_fnc_editorAi"; };
        class AiDocType: COMSPEC_SSE_EdCombo { idc = 94516; ED_POS(0.4,19.3,10.7,1.15); };
        class AiDocAdd: COMSPEC_SSE_EdButton { idc = 94517; text = "AJOUTER CE PAPIER"; ED_POS(11.3,19.3,5.3,1.15); action = "['docAdd'] call comspec_sse_fnc_editorAi"; };
        class AiKeptTitle: COMSPEC_SSE_EdMono { idc = 94518; text = "GARDÉS POUR LES PROCHAINES PARTIES"; ED_POS(0.4,20.6,16,0.9); };
        class AiKept: COMSPEC_SSE_EdCombo { idc = 94519; ED_POS(0.4,21.5,11,1.15); };
        class AiForget: COMSPEC_SSE_EdButton { idc = 94520; text = "RETIRER"; ED_POS(11.6,21.5,2.6,1.15); action = "['forget'] call comspec_sse_fnc_editorAi"; };
        class AiReload: COMSPEC_SSE_EdButton { idc = 94521; text = "RECHARGER"; ED_POS(14.4,21.5,3.6,1.15); action = "['reload'] call comspec_sse_fnc_editorAi"; };
        class AiFormTitle: COMSPEC_SSE_EdMono { idc = 94530; text = "CONFIGURATION"; ED_POS(23,2.1,16,0.9); };
        class AiL1: COMSPEC_SSE_EdText { idc = 94531; text = "Profil Athena"; ED_POS(23,3,16.6,0.8); ED_TXT(0.72); };
        class AiProfile: COMSPEC_SSE_EdCombo { idc = 94532; ED_POS(23,3.75,16.6,1.15); onLBSelChanged = "['preview'] call comspec_sse_fnc_editorAi"; };
        class AiL2: COMSPEC_SSE_EdText { idc = 94533; text = "Papiers"; ED_POS(23,5,8.2,0.8); ED_TXT(0.72); };
        class AiPapers: COMSPEC_SSE_EdCombo { idc = 94534; ED_POS(23,5.75,8.2,1.15); onLBSelChanged = "['preview'] call comspec_sse_fnc_editorAi"; };
        class AiL3: COMSPEC_SSE_EdText { idc = 94535; text = "Nature"; ED_POS(31.4,5,8.2,0.8); ED_TXT(0.72); };
        class AiKind: COMSPEC_SSE_EdCombo { idc = 94536; ED_POS(31.4,5.75,8.2,1.15); onLBSelChanged = "['preview'] call comspec_sse_fnc_editorAi"; };
        class AiL4: COMSPEC_SSE_EdText { idc = 94537; text = "Comportement au contrôle"; ED_POS(23,7,8.2,0.8); ED_TXT(0.72); };
        class AiBehav: COMSPEC_SSE_EdCombo { idc = 94538; ED_POS(23,7.75,8.2,1.15); onLBSelChanged = "['preview'] call comspec_sse_fnc_editorAi"; };
        class AiL5: COMSPEC_SSE_EdText { idc = 94539; text = "Camp"; ED_POS(31.4,7,8.2,0.8); ED_TXT(0.72); };
        class AiSide: COMSPEC_SSE_EdCombo { idc = 94540; ED_POS(31.4,7.75,8.2,1.15); onLBSelChanged = "['preview'] call comspec_sse_fnc_editorAi"; };
        class AiPrevTitle: COMSPEC_SSE_EdMono { idc = 94541; text = "CE QUE VERRA LE JOUEUR"; ED_POS(23,9.1,16,0.9); };
        class AiPrevBg: COMSPEC_SSE_EdText { idc = 94542; colorBackground[] = {0.91,0.894,0.847,1}; ED_POS(23,10,16.6,8); };
        class AiPrev: RscStructuredText { idc = 94543; colorBackground[] = {0, 0, 0, 0}; ED_POS(23.3,10.2,16,7.6); size = SSE_Q(SSE_GH * 0.78); };
        class AiKeep: COMSPEC_SSE_EdButton { idc = 94544; text = "GARDER POUR LES PROCHAINES PARTIES : NON"; font = "RobotoCondensed"; ED_POS(23,18.3,16.6,1.15); action = "['keep'] call comspec_sse_fnc_editorAi"; };
        class AiApply1: COMSPEC_SSE_EdPrimary { idc = 94545; text = "APPLIQUER"; ED_POS(23,19.7,5.4,1.25); action = "['applyOne'] call comspec_sse_fnc_editorAi"; };
        class AiApply2: COMSPEC_SSE_EdButton { idc = 94546; text = "À SON GROUPE"; ED_POS(28.6,19.7,5.4,1.25); action = "['applyGroup'] call comspec_sse_fnc_editorAi"; };
        class AiApply3: COMSPEC_SSE_EdButton { idc = 94547; text = "À TOUTE LA LISTE"; ED_POS(34.2,19.7,5.4,1.25); action = "['applyAll'] call comspec_sse_fnc_editorAi"; };
        class AiNote: COMSPEC_SSE_EdText { idc = 94548; style = 16; lineSpacing = 1; colorText[] = ED_MUTED; ED_POS(23,21.1,16.6,1.7); ED_TXT(0.62); };

        // ------------------------------------------------------------ guidage au premier lancement
        class GuideDim: COMSPEC_SSE_EdText { idc = 94400; ED_POS(0,0,40,25); colorBackground[] = {0, 0, 0, 0.72}; };
        class GuideBox: COMSPEC_SSE_EdText { idc = 94401; ED_POS(10,6,20,12); colorBackground[] = ED_BAR; };
        class GuideText: RscStructuredText { idc = 94402; colorBackground[] = {0, 0, 0, 0}; ED_POS(10.6,6.4,18.8,9.2); size = SSE_Q(SSE_GH * 0.9); };
        class GuideNext: COMSPEC_SSE_EdPrimary { idc = 94403; text = "SUIVANT"; ED_POS(24,16.2,5.4,1.3); action = "['guideNext'] call comspec_sse_fnc_editor"; };
    };
};
