// Projectiles. Base SmokeShell (simulation shotSmokeX) : le moteur ne fait jamais exploser la grenade (explosionTime
// au-delà de la durée de vie) ; le script compte la mèche puis remplace le projectile par l'engin au sol (CfgVehicles)
// qui détonne exactement N fois (fn_fuseEnd, fn_serverDetonate, fn_timeline).
class CfgAmmo {
    class SmokeShell;
    class COMSPEC_FB_Ammo_Base: SmokeShell {
        model = QPATHTOF(data\fb_1_spent.p3d);
        hit = 0;
        indirectHit = 0;
        indirectHitRange = 0;
        explosive = 0;
        dangerRadiusHit = -1;
        suppressionRadiusHit = 6;
        typicalSpeed = 18;
        deflecting = 15;
        explosionTime = 30;
        timeToLive = 6;
        cost = 40;
        effectsSmoke = "COMSPEC_FB_NoEffect";
        smokeColor[] = {0, 0, 0, 0};
        grenadeFireSound[] = {};
        grenadeBurningSound[] = {};
        aiAmmoUsageFlags = "64";
        whistleDist = 0;
        comspec_fb_bangs = 1;
        comspec_fb_drill = 0;
        comspec_fb_device = "COMSPEC_FB_Device_1";
    };
    class COMSPEC_FB_Ammo_1: COMSPEC_FB_Ammo_Base {
        model = QPATHTOF(data\fb_1_spent.p3d);
        comspec_fb_bangs = 1;
        comspec_fb_device = "COMSPEC_FB_Device_1";
    };
    class COMSPEC_FB_Ammo_2: COMSPEC_FB_Ammo_Base {
        model = QPATHTOF(data\fb_2_spent.p3d);
        comspec_fb_bangs = 2;
        comspec_fb_device = "COMSPEC_FB_Device_2";
    };
    class COMSPEC_FB_Ammo_3: COMSPEC_FB_Ammo_Base {
        model = QPATHTOF(data\fb_3_spent.p3d);
        comspec_fb_bangs = 3;
        comspec_fb_device = "COMSPEC_FB_Device_3";
    };
    class COMSPEC_FB_Ammo_4: COMSPEC_FB_Ammo_Base {
        model = QPATHTOF(data\fb_4_spent.p3d);
        comspec_fb_bangs = 4;
        comspec_fb_device = "COMSPEC_FB_Device_4";
    };
    class COMSPEC_FB_Ammo_5: COMSPEC_FB_Ammo_Base {
        model = QPATHTOF(data\fb_5_spent.p3d);
        comspec_fb_bangs = 5;
        comspec_fb_device = "COMSPEC_FB_Device_5";
    };
    class COMSPEC_FB_Ammo_6: COMSPEC_FB_Ammo_Base {
        model = QPATHTOF(data\fb_6_spent.p3d);
        comspec_fb_bangs = 6;
        comspec_fb_device = "COMSPEC_FB_Device_6";
    };
    class COMSPEC_FB_Ammo_7: COMSPEC_FB_Ammo_Base {
        model = QPATHTOF(data\fb_7_spent.p3d);
        comspec_fb_bangs = 7;
        comspec_fb_device = "COMSPEC_FB_Device_7";
    };
    class COMSPEC_FB_Ammo_8: COMSPEC_FB_Ammo_Base {
        model = QPATHTOF(data\fb_8_spent.p3d);
        comspec_fb_bangs = 8;
        comspec_fb_device = "COMSPEC_FB_Device_8";
    };
    class COMSPEC_FB_Ammo_9: COMSPEC_FB_Ammo_Base {
        model = QPATHTOF(data\fb_9_spent.p3d);
        comspec_fb_bangs = 9;
        comspec_fb_device = "COMSPEC_FB_Device_9";
    };
    class COMSPEC_FB_Ammo_Drill: COMSPEC_FB_Ammo_Base {
        model = QPATHTOF(data\fb_drill_spent.p3d);
        comspec_fb_bangs = 1;
        comspec_fb_device = "COMSPEC_FB_Device_Drill";
        comspec_fb_drill = 1;
        suppressionRadiusHit = -1;
        aiAmmoUsageFlags = "0";
    };
};
