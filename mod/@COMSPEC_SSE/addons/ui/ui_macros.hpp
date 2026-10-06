/*
    COMSPEC SSE — charte UI partagée (macros uniquement, aucune classe).

    Inclus par :
      - addons\ui\dialogs\base.hpp      (classes de contrôles COMSPEC_SSE_Rsc*)
      - addons\zeus\config.cpp          (dialogues Zeus)
    Chemin absolu : #include "\z\comspec_sse\addons\ui\ui_macros.hpp"

    Grille : 40 x 25 cases centrées dans la safeZone, largeur bornée à 1.2
    (même principe que GUI_GRID_CENTER de BI). Elle suit la taille d'interface
    et reste lisible en 4:3, 16:9, 16:10 et 21:9.
    Côté SQF, comspec_sse_fnc_uiGrid renvoie les mêmes valeurs.
*/

#ifndef SSE_UI_MACROS_INCLUDED
#define SSE_UI_MACROS_INCLUDED

// Valeur de config entre guillemets, macros développées (comme QUOTE de CBA).
// Les positions / tailles UI sont des expressions évaluées par le moteur.
#define SSE_Q(VAL) #VAL

// ---------------------------------------------------------------- palette
// Fond « tactique » sombre, accent vert désaturé, ambre réservé à Zeus.
#define SSE_C_DIM           {0.01,0.015,0.02,0.55}
#define SSE_C_BG            {0.055,0.065,0.07,0.97}
#define SSE_C_PANEL         {0.085,0.1,0.105,0.94}
#define SSE_C_PANEL_ALT     {0.11,0.13,0.135,0.96}
#define SSE_C_HEADER        {0.075,0.13,0.095,1}
#define SSE_C_HEADER_ZEUS   {0.2,0.12,0.04,1}
#define SSE_C_ACCENT        {0.45,0.8,0.5,1}
#define SSE_C_ACCENT_LINE   {0.45,0.8,0.5,0.7}
#define SSE_C_ACCENT_SOFT   {0.45,0.8,0.5,0.18}
#define SSE_C_ZEUS          {1,0.72,0.3,1}
#define SSE_C_ZEUS_LINE     {1,0.72,0.3,0.7}
#define SSE_C_TEXT          {0.9,0.93,0.9,1}
#define SSE_C_TEXT_MUTED    {0.62,0.68,0.64,1}
#define SSE_C_WARN          {0.95,0.72,0.28,1}
#define SSE_C_DANGER        {0.85,0.3,0.26,1}
#define SSE_C_NONE          {0,0,0,0}

// Boutons : primaire (action), secondaire (navigation), Zeus, neutre (fermer)
#define SSE_C_BTN           {0.15,0.3,0.19,1}
#define SSE_C_BTN_HOVER     {0.22,0.43,0.27,1}
#define SSE_C_BTN2          {0.16,0.19,0.2,1}
#define SSE_C_BTN2_HOVER    {0.24,0.28,0.29,1}
#define SSE_C_BTN_ZEUS      {0.42,0.26,0.07,1}
#define SSE_C_BTN_ZEUS_HOV  {0.56,0.35,0.1,1}
#define SSE_C_BTN_CLOSE     {0.24,0.12,0.11,1}
#define SSE_C_BTN_CLOSE_HOV {0.36,0.16,0.14,1}
#define SSE_C_BTN_DISABLED  {0.12,0.13,0.14,0.8}

// ---------------------------------------------------------------- polices
#define SSE_FONT            "RobotoCondensed"
#define SSE_FONT_BOLD       "RobotoCondensedBold"
#define SSE_FONT_TITLE      "PuristaMedium"
#define SSE_FONT_MONO       "EtelkaMonospacePro"

// ---------------------------------------------------------------- grille
#define SSE_GRID_WABS       ((safezoneW / safezoneH) min 1.2)
#define SSE_GRID_HABS       (SSE_GRID_WABS / 1.2)
#define SSE_GW              (SSE_GRID_WABS / 40)
#define SSE_GH              (SSE_GRID_HABS / 25)
#define SSE_GX              (safezoneX + (safezoneW - SSE_GRID_WABS) / 2)
#define SSE_GY              (safezoneY + (safezoneH - SSE_GRID_HABS) / 2)

// Positions absolues (cases depuis le coin haut-gauche de la grille centrée)
#define SSE_X(N)            (SSE_GX + (N) * SSE_GW)
#define SSE_Y(N)            (SSE_GY + (N) * SSE_GH)
// Dimensions (cases) — aussi valables à l'intérieur d'un ControlsGroup
#define SSE_W(N)            ((N) * SSE_GW)
#define SSE_H(N)            ((N) * SSE_GH)

// Tailles de texte (valeurs de config déjà entre guillemets)
#define SSE_TXT_S           SSE_Q(SSE_GH * 0.78)
#define SSE_TXT_M           SSE_Q(SSE_GH * 0.9)
#define SSE_TXT_L           SSE_Q(SSE_GH * 1.1)
#define SSE_TXT_XL          SSE_Q(SSE_GH * 1.3)

// Voile plein écran
#define SSE_FULLSCREEN x = "safezoneXAbs"; y = "safezoneY"; w = "safezoneWAbs"; h = "safezoneH"

#endif
