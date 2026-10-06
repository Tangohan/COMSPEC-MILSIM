/*
    Styles / définitions communes UI SSE.

    La charte (palette, polices, grille) vit dans ui_macros.hpp ; les classes
    de contrôles dans dialogs\base.hpp. Les anciens alias SSE_UI_* restent
    définis pour les écrans tiers qui les utiliseraient encore.
*/
#include "\z\comspec_sse\addons\ui\ui_macros.hpp"

#define SSE_UI_BG SSE_C_BG
#define SSE_UI_HDR SSE_C_HEADER
#define SSE_UI_ACCENT SSE_C_ACCENT
#define SSE_UI_BTN SSE_C_BTN
#define SSE_UI_BTN2 SSE_C_BTN2
#define SSE_UI_MUTED SSE_C_BTN_CLOSE

/*
    Gabarit commun des écrans plein format (36 x 23 cases, centré) :
      0.0 – 1.6   bandeau titre (idc titre conservé)
      1.6 – 1.75  filet d'accent
      1.75 – 2.75 sous-titre / fil d'Ariane
      3.0 – 19.5  contenu
      20.0 – 22.6 barre d'actions (boutons 1.4 case de haut)
*/
#define SSE_FRAME_X 2
#define SSE_FRAME_Y 1
#define SSE_FRAME_W 36
#define SSE_FRAME_H 23

// Coordonnées relatives au cadre
#define SSE_FX(N) SSE_X(SSE_FRAME_X + (N))
#define SSE_FY(N) SSE_Y(SSE_FRAME_Y + (N))
