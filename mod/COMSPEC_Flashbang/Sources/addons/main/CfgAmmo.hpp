// Projectiles. Base SmokeShell (simulation shotSmokeX) : le moteur ne fait jamais exploser la grenade, ni à l'impact
// (une fumigène n'a pas de détonation d'impact) ni au bout d'un temps (explosionTime = début de la fumée, placé
// au-delà de la durée de vie, effet de fumée vide) ; le script compte la mèche puis remplace le projectile par l'engin
// au sol (CfgVehicles) qui détonne exactement N fois (fn_fuseEnd, fn_serverDetonate, fn_timeline).
//
// Mèche : comspec_fb_fuse = -1 -> réglage CBA « Mèche » (variantes standard, 0,5 à 4 s) ; > 0 -> mèche fixe de la
// variante, en secondes (variantes à délai _D3 / _D8) : le réglage « Mèche » est ignoré, l'« Irrégularité » s'applique.
// timeToLive (durée de vie du projectile avant que le moteur l'efface) doit dépasser la mèche : 6 s pour la mèche
// réglable (4 s au plus), mèche + 7 s pour les variantes à délai (marge pour l'irrégularité et le réseau).
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
        comspec_fb_fuse = -1;
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

    // Variantes à délai (mèche fixe 3 s et 8 s) : COMSPEC_FB_Ammo_<V>_D3 / _D8, héritées de la variante standard.
    // FB_AMMO_DELAY(classe, fichier, délai, durée de vie) ; modèle data\fb_<fichier>_d<délai>_spent.p3d.
#define FB_AMMO_DELAY(V,F,D,TTL) class COMSPEC_FB_Ammo_##V##_D##D: COMSPEC_FB_Ammo_##V { \
        model = QPATHTOF(data\fb_##F##_d##D##_spent.p3d); \
        comspec_fb_fuse = D; \
        timeToLive = TTL; \
        comspec_fb_device = QUOTE(COMSPEC_FB_Device_##V##_D##D); \
    }
#define FB_AMMO_DELAYS(V,F) FB_AMMO_DELAY(V,F,3,10); FB_AMMO_DELAY(V,F,8,15)
    FB_AMMO_DELAYS(1,1);
    FB_AMMO_DELAYS(2,2);
    FB_AMMO_DELAYS(3,3);
    FB_AMMO_DELAYS(4,4);
    FB_AMMO_DELAYS(5,5);
    FB_AMMO_DELAYS(6,6);
    FB_AMMO_DELAYS(7,7);
    FB_AMMO_DELAYS(8,8);
    FB_AMMO_DELAYS(9,9);
    FB_AMMO_DELAYS(Drill,drill);
};
