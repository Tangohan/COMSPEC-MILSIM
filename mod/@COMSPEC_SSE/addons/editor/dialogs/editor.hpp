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
// Position relative (contrôle d'un ControlsGroup).
#define ED_REL(X,Y,W,H) x = SSE_Q(SSE_W(X)); y = SSE_Q(SSE_H(Y)); w = SSE_Q(SSE_W(W)); h = SSE_Q(SSE_H(H))
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
// Titre de section : taille de titre, même couleur que le texte, un filet dessous (jamais un petit libellé gris en capitales).
class COMSPEC_SSE_EdHeading: COMSPEC_SSE_EdText { font = "PuristaSemibold"; ED_TXT(0.95); };
class COMSPEC_SSE_EdRule: COMSPEC_SSE_EdText { colorBackground[] = ED_LINE; };
// Phrase d'aide sous un réglage (chaque option expliquée avec un exemple).
class COMSPEC_SSE_EdHelp: COMSPEC_SSE_EdText { style = 16; lineSpacing = 1; colorText[] = ED_MUTED; ED_TXT(0.52); };
class COMSPEC_SSE_EdColHead: COMSPEC_SSE_EdText { colorText[] = ED_MUTED; ED_TXT(0.56); };
class COMSPEC_SSE_EdList: RscListNBox {
    idc = -1;
    rowHeight = SSE_Q(SSE_GH * 1.0);
    font = "RobotoCondensed";
    shadow = 0;
    drawSideArrows = 0;
    colorText[] = ED_TEXT;
    colorBackground[] = ED_FIELD;
    colorSelect[] = ED_TEXT;
    colorSelect2[] = ED_TEXT;
    colorSelectBackground[] = {0.09,0.157,0.122,1};
    colorSelectBackground2[] = {0.09,0.157,0.122,1};
    colorPicture[] = {1, 1, 1, 1};
    colorPictureSelected[] = {1, 1, 1, 1};
    colorPictureDisabled[] = {1, 1, 1, 0.4};
    ED_TXT(0.66);
};
#define ED_SECTION(NAME,IDC1,IDC2,X,Y,W,TEXT) \
    class NAME##_Title: COMSPEC_SSE_EdHeading { idc = IDC1; text = TEXT; ED_POS(X,Y,W,0.85); }; \
    class NAME##_Rule: COMSPEC_SSE_EdRule { idc = IDC2; ED_POS(X,(Y) + 0.82,W,0.05); }

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
    onLoad = "(_this select 0) call comspec_sse_fnc_trDisplay; ['load', _this select 0] call comspec_sse_fnc_editor; if (!isNil 'comspec_sse_fnc_guideButton') then { [_this select 0, 'Editor'] call comspec_sse_fnc_guideButton; };";
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
        // Papier d'un renseignement (comspec_sse_fnc_paperSkin) : chaque ligne montre sa feuille en vignette.
        class SkinLabel: COMSPEC_SSE_EdText { idc = 94056; text = "Papier"; ED_POS(20.9,5.15,4,0.8); ED_TXT(0.72); };
        class SkinCombo: COMSPEC_SSE_EdCombo { idc = 94057; ED_POS(20.9,5.9,4,1.15); onLBSelChanged = "['preview'] call comspec_sse_fnc_editor"; };
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
        class PaperSkin: COMSPEC_SSE_EdPicture { idc = 94058; style = 48; ED_POS(25.7,3.1,13.8,17.5); };
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
        // Trois zones : la liste des IA (cases à cocher, recherche, filtres, colonnes), la fiche (sections à vrai titre),
        // les papiers et l'aperçu ; barre d'actions fixe en bas. Textes : stringtable.xml de l'addon (STR_COMSPEC_SSE_ED_*).
        // Largeurs : RobotoCondensed ~0.45 em par caractère, Purista ~0.5 em ; les aides sous les cases tiennent en deux
        // lignes, l'explication complète (avec exemple) est en info-bulle et dans le manuel (« ? »).
        class AiFill: COMSPEC_SSE_EdFill { idc = 94500; ED_POS(0,1.8,40,23.2); };
        // --- zone A : liste (x 0.3 .. 19.7)
        class AiRadius: COMSPEC_SSE_EdCombo { idc = 94502; tooltip = "$STR_COMSPEC_SSE_ED_Radius_D"; ED_POS(0.3,2.05,2.6,1.1); };
        class AiScan: COMSPEC_SSE_EdButton { idc = 94503; text = "$STR_COMSPEC_SSE_ED_Around"; tooltip = "$STR_COMSPEC_SSE_ED_Around_D"; ED_POS(3.0,2.05,4.2,1.1); action = "['scan'] call comspec_sse_fnc_editorAi"; };
        class AiSel: COMSPEC_SSE_EdButton { idc = 94504; text = "$STR_COMSPEC_SSE_ED_ZeusSel"; tooltip = "$STR_COMSPEC_SSE_ED_ZeusSel_D"; ED_POS(7.3,2.05,4.2,1.1); action = "['scanZeus'] call comspec_sse_fnc_editorAi"; };
        class AiCount: COMSPEC_SSE_EdText { idc = 94505; style = 1; colorText[] = ED_MUTED; ED_POS(11.6,2.15,6.8,0.9); };
        class AiHelp: COMSPEC_SSE_EdButton { idc = 94506; text = "$STR_COMSPEC_SSE_ED_Help"; tooltip = "$STR_COMSPEC_SSE_ED_Help_D"; font = "PuristaBold"; ED_POS(18.6,2.05,1.1,1.1); action = "['help'] call comspec_sse_fnc_editorAi"; };
        class AiSearchLabel: COMSPEC_SSE_EdText { idc = 94576; text = "$STR_COMSPEC_SSE_ED_Search"; ED_POS(0.3,3.4,2.7,1.1); ED_TXT(0.72); };
        class AiSearch: COMSPEC_SSE_EdEdit { idc = 94507; tooltip = "$STR_COMSPEC_SSE_ED_Search_D"; ED_POS(3.0,3.35,16.7,1.1); onKeyUp = "['filter'] call comspec_sse_fnc_editorAi"; };
        class AiFSide: COMSPEC_SSE_EdCombo { idc = 94508; tooltip = "$STR_COMSPEC_SSE_ED_Filters_D"; ED_POS(0.3,4.65,6.2,1.1); onLBSelChanged = "['filter'] call comspec_sse_fnc_editorAi"; };
        class AiFRole: COMSPEC_SSE_EdCombo { idc = 94509; tooltip = "$STR_COMSPEC_SSE_ED_Filters_D"; ED_POS(6.9,4.65,6.2,1.1); onLBSelChanged = "['filter'] call comspec_sse_fnc_editorAi"; };
        class AiFState: COMSPEC_SSE_EdCombo { idc = 94522; tooltip = "$STR_COMSPEC_SSE_ED_Filters_D"; ED_POS(13.5,4.65,6.2,1.1); onLBSelChanged = "['filter'] call comspec_sse_fnc_editorAi"; };
        // En-têtes des colonnes : mêmes positions que columns[] de la liste (fractions de 19.4 cases, depuis x 0.3).
        class AiH1: COMSPEC_SSE_EdColHead { idc = 94577; text = "$STR_COMSPEC_SSE_ED_Col_Name"; ED_POS(0.88,5.95,4.2,0.65); };
        class AiH2: COMSPEC_SSE_EdColHead { idc = 94578; text = "$STR_COMSPEC_SSE_ED_Col_True"; ED_POS(5.15,5.95,3.2,0.65); };
        class AiH3: COMSPEC_SSE_EdColHead { idc = 94579; text = "$STR_COMSPEC_SSE_ED_Col_Truth"; ED_POS(8.45,5.95,1.9,0.65); };
        class AiH4: COMSPEC_SSE_EdColHead { idc = 94580; text = "$STR_COMSPEC_SSE_ED_Col_Side"; ED_POS(10.39,5.95,1,0.65); };
        class AiH5: COMSPEC_SSE_EdColHead { idc = 94581; text = "$STR_COMSPEC_SSE_ED_Col_Role"; ED_POS(11.46,5.95,1.6,0.65); };
        class AiH6: COMSPEC_SSE_EdColHead { idc = 94582; text = "$STR_COMSPEC_SSE_ED_Col_Profile"; ED_POS(13.1,5.95,2.3,0.65); };
        class AiH7: COMSPEC_SSE_EdColHead { idc = 94583; text = "$STR_COMSPEC_SSE_ED_Col_Docs"; ED_POS(15.43,5.95,1.2,0.65); };
        class AiH8: COMSPEC_SSE_EdColHead { idc = 94584; text = "$STR_COMSPEC_SSE_ED_Col_Group"; ED_POS(16.69,5.95,3,0.65); };
        class AiList: COMSPEC_SSE_EdList {
            idc = 94510;
            tooltip = "$STR_COMSPEC_SSE_ED_List_D";
            ED_POS(0.3,6.65,19.4,10.2);
            columns[] = {0, 0.03, 0.25, 0.42, 0.52, 0.575, 0.66, 0.78, 0.845};
            onLBSelChanged = "['click'] call comspec_sse_fnc_editorAi";
            ED_TXT(0.6);
        };
        class AiEmpty: COMSPEC_SSE_EdText { idc = 94528; style = 16; lineSpacing = 1; colorText[] = ED_MUTED; ED_POS(1.5,10.5,17,2); ED_TXT(0.8); };
        class AiCheckAll: COMSPEC_SSE_EdButton { idc = 94523; text = "$STR_COMSPEC_SSE_ED_CheckAll"; tooltip = "$STR_COMSPEC_SSE_ED_CheckAll_D"; ED_POS(0.3,17,3.8,1); action = "['checkAll'] call comspec_sse_fnc_editorAi"; };
        class AiCheckNone: COMSPEC_SSE_EdButton { idc = 94524; text = "$STR_COMSPEC_SSE_ED_CheckNone"; ED_POS(4.3,17,3.8,1); action = "['checkNone'] call comspec_sse_fnc_editorAi"; };
        class AiChecked: COMSPEC_SSE_EdText { idc = 94525; colorText[] = ED_GREEN; ED_POS(8.3,17.05,11.4,0.9); ED_TXT(0.68); };
        ED_SECTION(AiKept,94518,94526,0.3,18.3,19.4,"$STR_COMSPEC_SSE_ED_Kept");
        class AiKept: COMSPEC_SSE_EdCombo { idc = 94519; tooltip = "$STR_COMSPEC_SSE_ED_KeptNote_D"; ED_POS(0.3,19.3,19.4,1.1); onLBSelChanged = "['keptSel'] call comspec_sse_fnc_editorAi"; };
        class AiReload: COMSPEC_SSE_EdButton { idc = 94521; text = "$STR_COMSPEC_SSE_ED_KeptReload"; tooltip = "$STR_COMSPEC_SSE_ED_KeptReload_D"; ED_POS(0.3,20.5,9.5,1.1); action = "['keptReload'] call comspec_sse_fnc_editorAi"; };
        class AiForget: COMSPEC_SSE_EdButton { idc = 94520; text = "$STR_COMSPEC_SSE_ED_KeptForget"; tooltip = "$STR_COMSPEC_SSE_ED_KeptForget_D"; ED_POS(10.2,20.5,9.5,1.1); action = "['keptForget'] call comspec_sse_fnc_editorAi"; };
        class AiKeptNote: COMSPEC_SSE_EdHelp { idc = 94527; text = "$STR_COMSPEC_SSE_ED_KeptNote"; tooltip = "$STR_COMSPEC_SSE_ED_KeptNote_D"; ED_POS(0.3,21.7,19.4,1.4); };
        // --- zone B : fiche (x 20.0 .. 29.8)
        class AiSheet: COMSPEC_SSE_EdText { idc = 94530; font = "PuristaBold"; ED_POS(20,2.05,9.8,1); ED_TXT(0.95); };
        class AiSheetSub: COMSPEC_SSE_EdHelp { idc = 94549; ED_POS(20,3.0,9.8,1.7); };
        ED_SECTION(AiProf,94550,94551,20,4.8,9.8,"$STR_COMSPEC_SSE_ED_Profile");
        class AiProfStatus: COMSPEC_SSE_EdHelp { idc = 94552; tooltip = "$STR_COMSPEC_SSE_ED_ProfStatus_D"; ED_POS(20,5.75,9.8,1.2); };
        class AiProfSearch: COMSPEC_SSE_EdEdit { idc = 94554; tooltip = "$STR_COMSPEC_SSE_ED_ProfSearch_D"; ED_POS(20,7.05,6.6,1.05); onKeyUp = "['profFilter'] call comspec_sse_fnc_editorAi"; };
        class AiRefresh: COMSPEC_SSE_EdButton { idc = 94553; text = "$STR_COMSPEC_SSE_ED_Refresh"; tooltip = "$STR_COMSPEC_SSE_ED_Refresh_D"; ED_POS(26.8,7.05,3,1.05); ED_TXT(0.7); action = "['refresh'] call comspec_sse_fnc_editorAi"; };
        class AiProfile: COMSPEC_SSE_EdCombo { idc = 94532; tooltip = "$STR_COMSPEC_SSE_ED_ProfHelp_D"; ED_POS(20,8.2,9.8,1.1); onLBSelChanged = "['preview'] call comspec_sse_fnc_editorAi"; };
        class AiProfHelp: COMSPEC_SSE_EdHelp { idc = 94555; text = "$STR_COMSPEC_SSE_ED_ProfHelp"; tooltip = "$STR_COMSPEC_SSE_ED_ProfHelp_D"; ED_POS(20,9.35,9.8,1.2); };
        ED_SECTION(AiId,94556,94557,20,10.7,9.8,"$STR_COMSPEC_SSE_ED_Identity");
        class AiL2: COMSPEC_SSE_EdText { idc = 94533; text = "$STR_COMSPEC_SSE_ED_Carry"; ED_POS(20,11.65,4.75,0.7); ED_TXT(0.62); };
        class AiPapers: COMSPEC_SSE_EdCombo { idc = 94534; tooltip = "$STR_COMSPEC_SSE_ED_IdentityHelp_D"; ED_POS(20,12.35,4.75,1.1); onLBSelChanged = "['preview'] call comspec_sse_fnc_editorAi"; };
        class AiL3: COMSPEC_SSE_EdText { idc = 94535; text = "$STR_COMSPEC_SSE_ED_Kind"; ED_POS(25.05,11.65,4.75,0.7); ED_TXT(0.62); };
        class AiKind: COMSPEC_SSE_EdCombo { idc = 94536; tooltip = "$STR_COMSPEC_SSE_ED_IdentityHelp_D"; ED_POS(25.05,12.35,4.75,1.1); onLBSelChanged = "['preview'] call comspec_sse_fnc_editorAi"; };
        class AiIdHelp: COMSPEC_SSE_EdHelp { idc = 94558; text = "$STR_COMSPEC_SSE_ED_IdentityHelp"; tooltip = "$STR_COMSPEC_SSE_ED_IdentityHelp_D"; ED_POS(20,13.5,9.8,1.2); };
        ED_SECTION(AiBeh,94559,94560,20,14.85,9.8,"$STR_COMSPEC_SSE_ED_Behaviour");
        class AiBehav: COMSPEC_SSE_EdCombo { idc = 94538; tooltip = "$STR_COMSPEC_SSE_ED_BehHelp_D"; ED_POS(20,15.8,9.8,1.1); onLBSelChanged = "['preview'] call comspec_sse_fnc_editorAi"; };
        class AiBehHelp: COMSPEC_SSE_EdHelp { idc = 94561; text = "$STR_COMSPEC_SSE_ED_BehHelp"; tooltip = "$STR_COMSPEC_SSE_ED_BehHelp_D"; ED_POS(20,16.95,9.8,1.2); };
        ED_SECTION(AiSide,94562,94563,20,18.3,9.8,"$STR_COMSPEC_SSE_ED_SideTitle");
        class AiSide: COMSPEC_SSE_EdCombo { idc = 94540; tooltip = "$STR_COMSPEC_SSE_ED_SideHelp_D"; ED_POS(20,19.25,9.8,1.1); onLBSelChanged = "['preview'] call comspec_sse_fnc_editorAi"; };
        class AiSideHelp: COMSPEC_SSE_EdHelp { idc = 94564; text = "$STR_COMSPEC_SSE_ED_SideHelp"; tooltip = "$STR_COMSPEC_SSE_ED_SideHelp_D"; ED_POS(20,20.4,9.8,1.2); };
        class AiNote: COMSPEC_SSE_EdText { idc = 94548; style = 16; lineSpacing = 1; colorText[] = ED_GREEN; ED_POS(20,21.7,9.8,1.4); ED_TXT(0.55); };
        // --- zone C : papiers et aperçu (x 30.1 .. 39.7)
        ED_SECTION(AiDocs,94511,94565,30.1,2.05,9.6,"$STR_COMSPEC_SSE_ED_Docs");
        class AiDH1: COMSPEC_SSE_EdColHead { idc = 94566; text = "$STR_COMSPEC_SSE_ED_Col_Type"; ED_POS(30.2,3.0,2.8,0.65); };
        class AiDH2: COMSPEC_SSE_EdColHead { idc = 94567; text = "$STR_COMSPEC_SSE_ED_Col_Country"; ED_POS(33.08,3.0,1.6,0.65); };
        class AiDH3: COMSPEC_SSE_EdColHead { idc = 94568; text = "$STR_COMSPEC_SSE_ED_Col_Number"; ED_POS(34.8,3.0,2.8,0.65); };
        class AiDH4: COMSPEC_SSE_EdColHead { idc = 94569; text = "$STR_COMSPEC_SSE_ED_Col_Truth"; ED_POS(37.68,3.0,1.9,0.65); };
        class AiDocs: COMSPEC_SSE_EdList {
            idc = 94512;
            tooltip = "$STR_COMSPEC_SSE_ED_Docs_D";
            ED_POS(30.1,3.65,9.6,3.3);
            columns[] = {0.01, 0.31, 0.49, 0.79};
            ED_TXT(0.6);
            onLBSelChanged = "['docSel'] call comspec_sse_fnc_editorAi";
            onLBDblClick = "['docOpen'] call comspec_sse_fnc_editorAi";
        };
        class AiDocsEmpty: COMSPEC_SSE_EdHelp { idc = 94529; text = "$STR_COMSPEC_SSE_ED_DocsNone"; ED_POS(30.4,4.2,9,1.8); ED_TXT(0.66); };
        class AiDocOpen: COMSPEC_SSE_EdButton { idc = 94513; text = "$STR_COMSPEC_SSE_ED_DocOpen"; tooltip = "$STR_COMSPEC_SSE_ED_DocOpen_D"; ED_POS(30.1,7.05,9.6,1); action = "['docOpen'] call comspec_sse_fnc_editorAi"; };
        class AiDocTrue: COMSPEC_SSE_EdButton { idc = 94514; text = "$STR_COMSPEC_SSE_ED_DocTrue"; tooltip = "$STR_COMSPEC_SSE_ED_DocTrue_D"; ED_POS(30.1,8.15,4.7,1); action = "['docTrue'] call comspec_sse_fnc_editorAi"; };
        class AiDocFalse: COMSPEC_SSE_EdButton { idc = 94515; text = "$STR_COMSPEC_SSE_ED_DocFalse"; tooltip = "$STR_COMSPEC_SSE_ED_DocFalse_D"; ED_POS(35,8.15,4.7,1); action = "['docFalse'] call comspec_sse_fnc_editorAi"; };
        class AiDocType: COMSPEC_SSE_EdCombo { idc = 94516; tooltip = "$STR_COMSPEC_SSE_ED_DocType_D"; ED_POS(30.1,9.25,9.6,1); };
        class AiDocAdd: COMSPEC_SSE_EdButton { idc = 94517; text = "$STR_COMSPEC_SSE_ED_DocGive"; tooltip = "$STR_COMSPEC_SSE_ED_DocGive_D"; ED_POS(30.1,10.3,9.6,1); action = "['docGive'] call comspec_sse_fnc_editorAi"; };
        class AiDocsHelp: COMSPEC_SSE_EdHelp { idc = 94571; text = "$STR_COMSPEC_SSE_ED_DocsHelp"; tooltip = "$STR_COMSPEC_SSE_ED_DocsHelp_D"; ED_POS(30.1,11.4,9.6,1.25); };
        ED_SECTION(AiPrev,94541,94572,30.1,12.75,9.6,"$STR_COMSPEC_SSE_ED_Preview");
        class AiPrevBg: COMSPEC_SSE_EdText { idc = 94542; colorBackground[] = {0.91,0.894,0.847,1}; ED_POS(30.1,13.7,9.6,9.3); };
        // Aperçu entier : texte structuré dans un groupe qui défile quand il dépasse (hauteur posée par fn_editorAi « preview »).
        class AiPrevGrp: COMSPEC_SSE_RscControlsGroup {
            idc = 94573;
            ED_POS(30.25,13.85,9.3,9);
            class controls {
                class AiPrev: RscStructuredText { idc = 94543; colorBackground[] = {0, 0, 0, 0}; ED_REL(0,0,8.8,9); size = SSE_Q(SSE_GH * 0.72); };
            };
        };
        // --- barre d'actions fixe
        class AiBar: COMSPEC_SSE_EdText { idc = 94574; ED_POS(0,23.2,40,1.8); colorBackground[] = ED_BAR; };
        class AiBarNote: COMSPEC_SSE_EdHelp { idc = 94575; text = "$STR_COMSPEC_SSE_ED_BarNote"; ED_POS(0.3,23.4,10.4,1.45); };
        class AiKeep: COMSPEC_SSE_EdButton { idc = 94544; text = "$STR_COMSPEC_SSE_ED_Keep"; tooltip = "$STR_COMSPEC_SSE_ED_Keep_D"; ED_POS(11,23.5,12,1.25); action = "['keepChecked'] call comspec_sse_fnc_editorAi"; };
        class AiApply2: COMSPEC_SSE_EdButton { idc = 94546; text = "$STR_COMSPEC_SSE_ED_ApplyGroup"; tooltip = "$STR_COMSPEC_SSE_ED_ApplyGroup_D"; ED_POS(23.4,23.5,7.6,1.25); action = "['applyGroup'] call comspec_sse_fnc_editorAi"; };
        class AiApply1: COMSPEC_SSE_EdPrimary { idc = 94545; text = "$STR_COMSPEC_SSE_ED_Apply"; tooltip = "$STR_COMSPEC_SSE_ED_Apply_D"; ED_POS(31.4,23.5,8.3,1.25); action = "['applyChecked'] call comspec_sse_fnc_editorAi"; };

        // ------------------------------------------------------------ guidage au premier lancement
        class GuideDim: COMSPEC_SSE_EdText { idc = 94400; ED_POS(0,0,40,25); colorBackground[] = {0, 0, 0, 0.72}; };
        class GuideBox: COMSPEC_SSE_EdText { idc = 94401; ED_POS(10,6,20,12); colorBackground[] = ED_BAR; };
        class GuideText: RscStructuredText { idc = 94402; colorBackground[] = {0, 0, 0, 0}; ED_POS(10.6,6.4,18.8,9.2); size = SSE_Q(SSE_GH * 0.9); };
        class GuideNext: COMSPEC_SSE_EdPrimary { idc = 94403; text = "SUIVANT"; ED_POS(24,16.2,5.4,1.3); action = "['guideNext'] call comspec_sse_fnc_editor"; };
    };
};
