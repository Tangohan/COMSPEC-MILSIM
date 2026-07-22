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

// Zone écran (hors barre d’état) : sous le header 60 px.
#define COMSPEC_PHONE_SCR_X COMSPEC_PHONE_PX(452)
#define COMSPEC_PHONE_SCR_Y COMSPEC_PHONE_PY(773)
#define COMSPEC_PHONE_SCR_W COMSPEC_PHONE_PW(1098)
#define COMSPEC_PHONE_SCR_H COMSPEC_PHONE_PH(566)

#define COMSPEC_IMG_PHONE_BG "\z\comspec_overwatch\addons\connect\img\device\comspec_phone_bg_ca.paa"
#define COMSPEC_IMG_TABLET_BG "\z\comspec_overwatch\addons\connect\img\device\comspec_tablet_bg_ca.paa"
#define COMSPEC_IMG_ICON_BATTERY "\z\comspec_overwatch\addons\connect\img\device\comspec_icon_battery_ca.paa"
#define COMSPEC_IMG_ICON_SIGNAL "\z\comspec_overwatch\addons\connect\img\device\comspec_icon_signal_ca.paa"
#define COMSPEC_IMG_ICON_PHONE "\z\comspec_overwatch\addons\connect\img\device\comspec_icon_phone.paa"
#define COMSPEC_IMG_ICON_TABLET "\z\comspec_overwatch\addons\connect\img\device\comspec_icon_tablet.paa"
#define COMSPEC_IMG_ICON_MAIL "\z\comspec_overwatch\addons\connect\img\device\comspec_icon_mail_ca.paa"
