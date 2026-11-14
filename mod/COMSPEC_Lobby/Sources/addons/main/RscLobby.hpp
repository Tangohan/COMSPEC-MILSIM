// Écran de sélection des rôles (RscDisplayMultiplayerSetup) : même écran, mêmes contrôles et mêmes identifiants que le jeu.
// Ce fichier ne fait que :
//   - restyler (fond noir, filets fins, un accent ambre) ;
//   - descendre le pool de joueurs (idc 114 et son bandeau) pour faire de la place au dossier de l'opérateur ;
//   - ajouter le dossier (idc 9100 et plus) : portrait, nom, prénom, grade, unité, fonction, qualifications, code du poste.
// Rien d'autre n'est déplacé : le chat du moteur, la liste des rôles, les camps, les boutons (paramètres de mission, verrou,
// expulsion, Workshop, continuer, retour) gardent leur place et leur comportement. Le script de l'écran (fn_lobbyOpen.sqf)
// remplit le dossier ; s'il manque un contrôle attendu, il ne touche à rien et l'écran reste celui du jeu.
//
// Unités : GW et GH sont celles du jeu (grille de 40 x 25), pour que tout suive la résolution comme l'écran d'origine.

#define GW (((safezoneW / safezoneH) min 1.2) / 40)
#define GH ((((safezoneW / safezoneH) min 1.2) / 1.2) / 25)
#define EXW (safezoneW - ((safezoneW / safezoneH) min 1.2))
#define XS(e) QUOTE(e)

// Colonne de droite d'origine (pool de joueurs) : le dossier en occupe le haut, le pool descend dessous.
#define DX (safezoneX + (19.8 * GW) + 0.45 * EXW)
#define DW ((19 * GW) + 0.55 * EXW)
#define PORTH (4.4 * GH)
#define PORTW (0.8 * PORTH * pixelW / pixelH)
#define TXTX (DX + PORTW + 0.8 * GW)
#define TXTW (DW - PORTW - 0.8 * GW - 0.4 * GW)
#define POOLTOP 14.7

#define COL_DIM {1,1,1,0.55}
#define COL_ACCENT {0.94,0.65,0.23,1}
#define COL_LINE {1,1,1,0.22}
#define COL_WHITE {1,1,1,1}

// Texte simple du dossier.
#define DTEXT(NAME,IDC,X,Y,W,H,FONT,SIZE,STYLE,COL) \
    class NAME: RscText { idc = IDC; x = XS(X); y = XS(Y); w = XS(W); h = XS(H); font = FONT; sizeEx = XS(SIZE); style = STYLE; colorText[] = COL; shadow = 0; text = ""; }

// Filet plein (fond uni d'un contrôle texte), pour les traits fins et les coins du portrait.
#define DLINE(NAME,X,Y,W,H,COL) \
    class NAME: RscText { idc = -1; x = XS(X); y = XS(Y); w = XS(W); h = XS(H); colorBackground[] = COL; text = ""; }

// Touche du pavé (code du poste) : texte seul, fond léger. TXT et ACT sont donnés en entier (un macro ne se développe pas dans
// une chaîne) ; I est le rang de la touche dans la rangée de onze.
#define DKEY(NAME,IDC,TXT,ACT,I)     class NAME: RscButtonTextOnly {         idc = IDC; style = 2; text = TXT; font = "RobotoCondensed"; action = ACT;         x = XS(DX + (I) * ((DW) / 11)); y = XS(safezoneY + 13.55 * GH); w = XS(((DW) / 11) - 0.12 * GW); h = XS(0.75 * GH);         colorBackground[] = {1,1,1,0.07}; colorBackgroundActive[] = {0.94,0.65,0.23,0.30}; colorBackgroundDisabled[] = {1,1,1,0.03};         colorText[] = {1,1,1,0.9}; colorDisabled[] = {1,1,1,0.25}; colorFocused[] = {1,1,1,0.07};     }

// Déclarations des classes d'ui_f utilisées comme parents (la config du jeu les définit : elles sont fusionnées).
class RscStandardDisplay;
class RscText;
class RscTitle;
class RscPicture;
class RscControlsGroupNoScrollbars;
class RscListBox;
class RscListNBox;
class RscCheckBox;
class RscButtonTextOnly;
class RscInterlacingScreen;

class RscDisplayMultiplayerSetup: RscStandardDisplay {
    // Le onLoad d'origine (BIS_fnc_initDisplay) est gardé tel quel ; notre script se lance ensuite, hors de CBA.
    onLoad = "[""onLoad"",_this,""RscDisplayMultiplayerSetup"",'GUI'] call (uinamespace getvariable 'BIS_fnc_initDisplay'); [_this select 0] spawn (compile preprocessFileLineNumbers '\z\comspec_lobby\addons\main\functions\fn_lobbyOpen.sqf')";

    class controlsBackground {
        class TileGroup: RscControlsGroupNoScrollbars {
            class Controls {
                class Background: RscText { colorBackground[] = {0,0,0,0.80}; };
            };
        };
        // Fonds plus sombres, titre sans bandeau coloré.
        class RscTitleBackground: RscText { colorBackground[] = {0,0,0,0}; };
        class MainBackground: RscText { colorBackground[] = {0,0,0,0.55}; };
        class MissionSettingsBackground: RscText { colorBackground[] = {1,1,1,0.035}; };
        class NumOfPlayersBackground: RscText { colorBackground[] = {1,1,1,0.035}; };
        class SideBackground: RscText { colorBackground[] = {1,1,1,0.035}; };
        class RolesBackground: RscText { colorBackground[] = {1,1,1,0.035}; };
        class ChatBackground: RscText { colorBackground[] = {1,1,1,0.035}; };
        class PlayersPoolHeaderBackground: RscText {
            colorBackground[] = {0,0,0,0.9};
            y = XS(safezoneY + POOLTOP * GH);
        };
        class PlayersPoolBackground: RscText {
            colorBackground[] = {1,1,1,0.035};
            y = XS(safezoneY + (POOLTOP + 0.8) * GH);
            h = XS(SafezoneH - (POOLTOP + 0.8 + 2.2) * GH);
        };
        // Fond du dossier, juste au-dessus du pool.
        class DossierBackground: RscText {
            idc = -1;
            x = XS(DX); y = XS(safezoneY + 6.0 * GH); w = XS(DW); h = XS(8.5 * GH);
            colorBackground[] = {1,1,1,0.035};
            text = "";
        };
        // Filet sous le bandeau du titre.
        DLINE(HeaderRule, safezoneX + 1 * GW, safezoneY + 2.05 * GH, SafezoneW - 2 * GW, pixelH, COL_LINE);
        // Lignes de balayage très discrètes, comme les menus de Contact.
        class Scanlines: RscInterlacingScreen { idc = -1; colorText[] = {1,1,1,0.07}; };
    };

    class controls {
        // En-tête : titre fin, nom de mission en clair.
        class Title: RscTitle { font = "RobotoCondensedLight"; colorText[] = COL_DIM; };
        class ValueMission: RscTitle { font = "RobotoCondensedBold"; };

        // Liste des postes (moteur) : mêmes lignes, couleurs de sélection ambre, police condensée.
        class CA_ValueRoles: RscListBox {
            font = "RobotoCondensed";
            colorBackground[] = {0,0,0,0};
            colorSelect[] = COL_ACCENT;
            colorSelect2[] = COL_ACCENT;
            colorSelectBackground[] = {0.94,0.65,0.23,0.14};
            colorSelectBackground2[] = {0.94,0.65,0.23,0.14};
        };
        class TextRole: RscText { font = "RobotoCondensedBold"; colorBackground[] = {0,0,0,0}; };
        class TextSide: RscText { font = "RobotoCondensedBold"; colorBackground[] = {0,0,0,0}; };

        // Pool de joueurs : descendu sous le dossier (même largeur, même colonne). Rien d'autre ne change.
        class CA_ValuePool: RscListNBox {
            colorSelectBackground[] = {0.94,0.65,0.23,0.14};
            colorSelectBackground2[] = {0.94,0.65,0.23,0.14};
            y = XS(safezoneY + (POOLTOP + 0.8) * GH);
            h = XS(SafezoneH - (POOLTOP + 0.8 + 3.1) * GH);
        };
        class ButtonPlayers: RscButtonTextOnly { y = XS(safezoneY + POOLTOP * GH); };
        class SortPlayers: RscPicture { y = XS(safezoneY + POOLTOP * GH); };
        class ButtonPing: ButtonPlayers { y = XS(safezoneY + POOLTOP * GH); };
        class SortPing: RscPicture { y = XS(safezoneY + POOLTOP * GH); };
        class MuteAll: RscCheckBox { y = XS(safezoneY + POOLTOP * GH); };

        // ---------------------------------------------------------------- dossier de l'opérateur (idc 9100 et plus)
        DTEXT(DossierTitle, 9100, DX + 0.3 * GW, safezoneY + 6.05 * GH, 8 * GW, 0.8 * GH, "RobotoCondensedBold", 0.8 * GH, 0, COL_DIM);
        DTEXT(DossierRef, 9101, DX + DW - 8.3 * GW, safezoneY + 6.05 * GH, 8 * GW, 0.8 * GH, "EtelkaMonospacePro", 0.7 * GH, 1, COL_DIM);

        class Portrait: RscPicture {
            idc = 9102;
            x = XS(DX + 0.3 * GW); y = XS(safezoneY + 6.95 * GH); w = XS(PORTW); h = XS(PORTH);
            text = "#(argb,8,8,3)color(0.04,0.06,0.05,1)";
            colorText[] = {1,1,1,1};
        };
        // Coins marqués autour du portrait.
        DLINE(CornTLh, DX + 0.3 * GW, safezoneY + 6.95 * GH, 0.9 * GW, pixelH, COL_LINE);
        DLINE(CornTLv, DX + 0.3 * GW, safezoneY + 6.95 * GH, pixelW, 0.8 * GH, COL_LINE);
        DLINE(CornTRh, DX + 0.3 * GW + PORTW - 0.9 * GW, safezoneY + 6.95 * GH, 0.9 * GW, pixelH, COL_LINE);
        DLINE(CornTRv, DX + 0.3 * GW + PORTW - pixelW, safezoneY + 6.95 * GH, pixelW, 0.8 * GH, COL_LINE);
        DLINE(CornBLh, DX + 0.3 * GW, safezoneY + 6.95 * GH + PORTH - pixelH, 0.9 * GW, pixelH, COL_LINE);
        DLINE(CornBLv, DX + 0.3 * GW, safezoneY + 6.95 * GH + PORTH - 0.8 * GH, pixelW, 0.8 * GH, COL_LINE);
        DLINE(CornBRh, DX + 0.3 * GW + PORTW - 0.9 * GW, safezoneY + 6.95 * GH + PORTH - pixelH, 0.9 * GW, pixelH, COL_LINE);
        DLINE(CornBRv, DX + 0.3 * GW + PORTW - pixelW, safezoneY + 6.95 * GH + PORTH - 0.8 * GH, pixelW, 0.8 * GH, COL_LINE);
        DTEXT(PortraitSource, 9103, DX + 0.3 * GW, safezoneY + 6.95 * GH + PORTH - 0.7 * GH, PORTW, 0.6 * GH, "EtelkaMonospacePro", 0.5 * GH, 2, COL_DIM);

        DTEXT(Surname, 9104, TXTX, safezoneY + 6.9 * GH, TXTW, 1.6 * GH, "RobotoCondensedBold", 1.5 * GH, 0, COL_WHITE);
        DTEXT(Given, 9105, TXTX, safezoneY + 8.55 * GH, TXTW, 1.0 * GH, "RobotoCondensedLight", 1.0 * GH, 0, COL_DIM);

        DTEXT(LabelGrade, 9110, TXTX, safezoneY + 9.75 * GH, 5 * GW, 0.6 * GH, "EtelkaMonospacePro", 0.5 * GH, 0, COL_DIM);
        DTEXT(ValueGrade, 9114, TXTX + 5 * GW, safezoneY + 9.7 * GH, TXTW - 5 * GW, 0.7 * GH, "RobotoCondensed", 0.7 * GH, 0, COL_WHITE);
        DTEXT(LabelUnit, 9111, TXTX, safezoneY + 10.45 * GH, 5 * GW, 0.6 * GH, "EtelkaMonospacePro", 0.5 * GH, 0, COL_DIM);
        DTEXT(ValueUnit, 9115, TXTX + 5 * GW, safezoneY + 10.4 * GH, TXTW - 5 * GW, 0.7 * GH, "RobotoCondensed", 0.7 * GH, 0, COL_WHITE);
        DTEXT(LabelRole, 9112, TXTX, safezoneY + 11.15 * GH, 5 * GW, 0.6 * GH, "EtelkaMonospacePro", 0.5 * GH, 0, COL_DIM);
        DTEXT(ValueRole, 9116, TXTX + 5 * GW, safezoneY + 11.1 * GH, TXTW - 5 * GW, 0.7 * GH, "RobotoCondensed", 0.7 * GH, 0, COL_WHITE);
        DTEXT(LabelQuals, 9113, TXTX, safezoneY + 11.85 * GH, 5 * GW, 0.6 * GH, "EtelkaMonospacePro", 0.5 * GH, 0, COL_DIM);
        DTEXT(ValueQuals, 9117, TXTX + 5 * GW, safezoneY + 11.8 * GH, TXTW - 5 * GW, 0.7 * GH, "RobotoCondensed", 0.7 * GH, 0, COL_WHITE);

        // Code du poste : quatre chiffres, message, pavé d'une ligne (0 à 9 et effacer).
        DTEXT(PinLabel, 9120, DX + 0.3 * GW, safezoneY + 12.7 * GH, 6 * GW, 0.7 * GH, "RobotoCondensedBold", 0.7 * GH, 0, COL_ACCENT);
        DTEXT(PinD1, 9121, DX + 6.4 * GW, safezoneY + 12.55 * GH, 1.3 * GW, 0.85 * GH, "EtelkaMonospacePro", 0.85 * GH, 2, COL_WHITE);
        DTEXT(PinD2, 9122, DX + 7.9 * GW, safezoneY + 12.55 * GH, 1.3 * GW, 0.85 * GH, "EtelkaMonospacePro", 0.85 * GH, 2, COL_WHITE);
        DTEXT(PinD3, 9123, DX + 9.4 * GW, safezoneY + 12.55 * GH, 1.3 * GW, 0.85 * GH, "EtelkaMonospacePro", 0.85 * GH, 2, COL_WHITE);
        DTEXT(PinD4, 9124, DX + 10.9 * GW, safezoneY + 12.55 * GH, 1.3 * GW, 0.85 * GH, "EtelkaMonospacePro", 0.85 * GH, 2, COL_WHITE);
        DLINE(PinU1, DX + 6.4 * GW, safezoneY + 13.42 * GH, 1.3 * GW, pixelH, COL_LINE);
        DLINE(PinU2, DX + 7.9 * GW, safezoneY + 13.42 * GH, 1.3 * GW, pixelH, COL_LINE);
        DLINE(PinU3, DX + 9.4 * GW, safezoneY + 13.42 * GH, 1.3 * GW, pixelH, COL_LINE);
        DLINE(PinU4, DX + 10.9 * GW, safezoneY + 13.42 * GH, 1.3 * GW, pixelH, COL_LINE);
        DTEXT(PinMessage, 9125, DX + 12.7 * GW, safezoneY + 12.65 * GH, DW - 12.7 * GW - 0.3 * GW, 0.7 * GH, "RobotoCondensed", 0.55 * GH, 0, COL_DIM);

        DKEY(Key1, 9131, "1", "['digit','1'] call (uiNamespace getVariable 'COMSPEC_LOBBY_ui')", 0);
        DKEY(Key2, 9132, "2", "['digit','2'] call (uiNamespace getVariable 'COMSPEC_LOBBY_ui')", 1);
        DKEY(Key3, 9133, "3", "['digit','3'] call (uiNamespace getVariable 'COMSPEC_LOBBY_ui')", 2);
        DKEY(Key4, 9134, "4", "['digit','4'] call (uiNamespace getVariable 'COMSPEC_LOBBY_ui')", 3);
        DKEY(Key5, 9135, "5", "['digit','5'] call (uiNamespace getVariable 'COMSPEC_LOBBY_ui')", 4);
        DKEY(Key6, 9136, "6", "['digit','6'] call (uiNamespace getVariable 'COMSPEC_LOBBY_ui')", 5);
        DKEY(Key7, 9137, "7", "['digit','7'] call (uiNamespace getVariable 'COMSPEC_LOBBY_ui')", 6);
        DKEY(Key8, 9138, "8", "['digit','8'] call (uiNamespace getVariable 'COMSPEC_LOBBY_ui')", 7);
        DKEY(Key9, 9139, "9", "['digit','9'] call (uiNamespace getVariable 'COMSPEC_LOBBY_ui')", 8);
        DKEY(Key0, 9130, "0", "['digit','0'] call (uiNamespace getVariable 'COMSPEC_LOBBY_ui')", 9);
        class KeyBack: RscButtonTextOnly {
            idc = 9140; style = 2; text = "<"; font = "RobotoCondensed";
            action = "['back'] call (uiNamespace getVariable 'COMSPEC_LOBBY_ui')";
            x = XS(DX + 10 * ((DW) / 11)); y = XS(safezoneY + 13.55 * GH); w = XS(((DW) / 11) - 0.12 * GW); h = XS(0.75 * GH);
            colorBackground[] = {1,1,1,0.07}; colorBackgroundActive[] = {0.94,0.65,0.23,0.30}; colorBackgroundDisabled[] = {1,1,1,0.03};
            colorText[] = {1,1,1,0.9}; colorDisabled[] = {1,1,1,0.25}; colorFocused[] = {1,1,1,0.07};
        };
    };
};
