// Coordonnées UI téléphone / tablette — calquées sur cTab NSWDG (android / tablet).
// Texture téléphone : cadre plein (réf. 2048×2048), écran utile ≈ (452,713)–(1550,1339).
// Préfixe assets : \z\comspec_overwatch\addons\connect\img\device\comspec_*

#define COMSPEC_PHONE_W (safezoneW * 0.72)
#define COMSPEC_PHONE_H (COMSPEC_PHONE_W * 4/3)
#define COMSPEC_PHONE_X (safezoneX + (safezoneW - COMSPEC_PHONE_W) / 2)
#define COMSPEC_PHONE_Y (safezoneY + (safezoneH - COMSPEC_PHONE_H) / 2)
#define COMSPEC_PHONE_PX(n) ((n) / 2048 * COMSPEC_PHONE_W + COMSPEC_PHONE_X)
#define COMSPEC_PHONE_PY(n) ((n) / 2048 * COMSPEC_PHONE_H + COMSPEC_PHONE_Y)
#define COMSPEC_PHONE_PW(n) ((n) / 2048 * COMSPEC_PHONE_W)
#define COMSPEC_PHONE_PH(n) ((n) / 2048 * COMSPEC_PHONE_H)

#define COMSPEC_IMG_PHONE_BG "\z\comspec_overwatch\addons\connect\img\device\comspec_phone_bg_ca.paa"
#define COMSPEC_IMG_ICON_BATTERY "\z\comspec_overwatch\addons\connect\img\device\comspec_icon_battery_ca.paa"
#define COMSPEC_IMG_ICON_SIGNAL "\z\comspec_overwatch\addons\connect\img\device\comspec_icon_signal_ca.paa"
#define COMSPEC_IMG_ICON_TABLET "\z\comspec_overwatch\addons\connect\img\device\comspec_icon_tablet.paa"

// Retirés le 26/10/2026 (audit DEAD-021) : 17 macros jamais développées. 9 nommaient une
// image sortie du PBO (8 déplacées vers mod/UptoDate/art/connect/, 1 doublon supprimé,
// DEAD-007), 4 une image restée dans l'addon, 4 aucune image (cadre d'écran).
// COMSPEC_PHONE_SCR_X/Y/W/H, COMSPEC_IMG_TABLET_BG, COMSPEC_IMG_ICON_PHONE,
// COMSPEC_IMG_ICON_MAIL, COMSPEC_IMG_OVERLAY_SCREEN_CRACKED / SCREEN_OFF /
// STATIC / NO_SIGNAL / LOW_SIGNAL, COMSPEC_IMG_ICON_JAMMER / NO_COVERAGE /
// DEVICE_DESTROYED / REBOOT / REPAIR.
// Les 3 overlays réellement affichés le sont par chemin littéral dans
// functions/fn_updateDeviceOverlay.sqf, pas par ces macros.
