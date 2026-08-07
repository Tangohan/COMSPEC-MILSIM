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

// Overlays roleplay (textures originales COMSPEC — PNG packés tant que TexView n’a pas produit les .paa)
#define COMSPEC_IMG_OVERLAY_SCREEN_CRACKED "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_screen_cracked_ca.png"
#define COMSPEC_IMG_OVERLAY_SCREEN_OFF "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_screen_off_ca.png"
#define COMSPEC_IMG_OVERLAY_STATIC "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_static_noise_ca.png"
#define COMSPEC_IMG_OVERLAY_NO_SIGNAL "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_no_signal_ca.png"
#define COMSPEC_IMG_OVERLAY_LOW_SIGNAL "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_low_signal_ca.png"
#define COMSPEC_IMG_ICON_JAMMER "\z\comspec_overwatch\addons\connect\img\overlays\comspec_icon_jammer_ca.png"
#define COMSPEC_IMG_ICON_NO_COVERAGE "\z\comspec_overwatch\addons\connect\img\overlays\comspec_icon_no_coverage_ca.png"
#define COMSPEC_IMG_ICON_DEVICE_DESTROYED "\z\comspec_overwatch\addons\connect\img\overlays\comspec_icon_device_destroyed_ca.png"
#define COMSPEC_IMG_ICON_REBOOT "\z\comspec_overwatch\addons\connect\img\overlays\comspec_icon_reboot_ca.png"
#define COMSPEC_IMG_ICON_REPAIR "\z\comspec_overwatch\addons\connect\img\overlays\comspec_icon_repair_ca.png"
