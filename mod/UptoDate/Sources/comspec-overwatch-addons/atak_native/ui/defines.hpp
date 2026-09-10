#define ATAK_X safeZoneX
#define ATAK_Y safeZoneY
#define ATAK_W safeZoneW
#define ATAK_H safeZoneH
#define ATAK_TOP_H (safeZoneH * 0.055)
#define ATAK_BOTTOM_H (safeZoneH * 0.045)
#define ATAK_RAIL_W (safeZoneW * 0.105)
#define ATAK_INSPECT_W (safeZoneW * 0.225)
#define ATAK_BODY_Y (safeZoneY + ATAK_TOP_H)
#define ATAK_BODY_H (safeZoneH - ATAK_TOP_H - ATAK_BOTTOM_H)
#define ATAK_CENTER_X (safeZoneX + ATAK_RAIL_W)
#define ATAK_CENTER_W (safeZoneW - ATAK_RAIL_W - ATAK_INSPECT_W)
