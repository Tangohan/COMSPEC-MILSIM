// Styles de la page « IA de la zone » et de la visite guidée (classes de base, hors de la classe du dialogue).
// Taille de texte : jamais sous AZ_TXT_MIN (= SSE_TXT_S de la charte, 0.78 case). Contrôles : aizone.hpp, coach.hpp.

#define AZ_TXT_MIN  0.78
#define AZ_TXT_BODY 0.85
#define AZ_TXT_HEAD 1.0
#define AZ_POPUP    {0.071,0.106,0.09,1}
#define AZ_FIELD    {0.067,0.094,0.082,1}

class COMSPEC_SSE_AzText: COMSPEC_SSE_EdText { ED_TXT(AZ_TXT_BODY); };
class COMSPEC_SSE_AzMuted: COMSPEC_SSE_AzText { colorText[] = ED_MUTED; };
class COMSPEC_SSE_AzHeading: COMSPEC_SSE_EdText { font = "RobotoCondensedBold"; ED_TXT(AZ_TXT_HEAD); };
// Aide sous un réglage : texte structuré (hauteur mesurable, retour à la ligne), gris, taille minimale.
class COMSPEC_SSE_AzHelp: RscStructuredText {
    idc = -1;
    colorBackground[] = {0, 0, 0, 0};
    size = SSE_Q(SSE_GH * AZ_TXT_MIN);
    class Attributes { font = "RobotoCondensed"; color = "#9fb0a8"; align = "left"; shadow = 0; };
};
class COMSPEC_SSE_AzButton: COMSPEC_SSE_EdButton { font = "RobotoCondensed"; ED_TXT(AZ_TXT_BODY); };
class COMSPEC_SSE_AzPrimary: COMSPEC_SSE_EdPrimary { font = "RobotoCondensedBold"; ED_TXT(AZ_TXT_BODY); };
class COMSPEC_SSE_AzTab: COMSPEC_SSE_AzButton { colorBackground[] = ED_PANEL; colorText[] = ED_MUTED; ED_TXT(0.8); };
// Bouton d'en-tête de colonne (tri) : texte à gauche, taille minimale.
class COMSPEC_SSE_AzColHead: COMSPEC_SSE_AzButton { style = 0; colorBackground[] = {0, 0, 0, 0}; colorText[] = ED_MUTED; ED_TXT(AZ_TXT_MIN); };
class COMSPEC_SSE_AzColText: COMSPEC_SSE_AzMuted { ED_TXT(AZ_TXT_MIN); };
class COMSPEC_SSE_AzCombo: COMSPEC_SSE_EdCombo { ED_TXT(AZ_TXT_BODY); };
class COMSPEC_SSE_AzEdit: COMSPEC_SSE_EdEdit { ED_TXT(AZ_TXT_BODY); };
// Champ sans fond : posé sur un fond et une invite grise (« Chercher un nom… »), masquée dès qu'on tape.
class COMSPEC_SSE_AzEditClear: COMSPEC_SSE_AzEdit { colorBackground[] = {0, 0, 0, 0}; };
class COMSPEC_SSE_AzList: COMSPEC_SSE_EdList { rowHeight = SSE_Q(SSE_GH * 1.25); ED_TXT(0.8); };
class COMSPEC_SSE_AzPopup: COMSPEC_SSE_EdText { colorBackground[] = AZ_POPUP; };
class COMSPEC_SSE_AzAccent: COMSPEC_SSE_EdText { colorBackground[] = ED_GREEN; };
// Voile sous un volet ouvert : assombrit la page autour du volet et le ferme d'un clic à côté (fn_editorAi « voletClose »).
#define AZ_SCRIM    {0.012,0.02,0.016,0.62}
class COMSPEC_SSE_AzScrim: COMSPEC_SSE_EdButton {
    text = "";
    colorText[] = {0, 0, 0, 0};
    colorBackground[] = AZ_SCRIM;
    colorBackgroundActive[] = AZ_SCRIM;
    colorBackgroundDisabled[] = AZ_SCRIM;
    colorFocused[] = AZ_SCRIM;
};
