// Engins au sol pendant les détonations (créés par le serveur, synchronisés, poussés par les explosions).
    class COMSPEC_FB_Device_1: COMSPEC_FB_Device_Base {
        model = QPATHTOF(data\fb_1_spent.p3d);
    };
    class COMSPEC_FB_Device_2: COMSPEC_FB_Device_Base {
        model = QPATHTOF(data\fb_2_spent.p3d);
    };
    class COMSPEC_FB_Device_3: COMSPEC_FB_Device_Base {
        model = QPATHTOF(data\fb_3_spent.p3d);
    };
    class COMSPEC_FB_Device_4: COMSPEC_FB_Device_Base {
        model = QPATHTOF(data\fb_4_spent.p3d);
    };
    class COMSPEC_FB_Device_5: COMSPEC_FB_Device_Base {
        model = QPATHTOF(data\fb_5_spent.p3d);
    };
    class COMSPEC_FB_Device_6: COMSPEC_FB_Device_Base {
        model = QPATHTOF(data\fb_6_spent.p3d);
    };
    class COMSPEC_FB_Device_7: COMSPEC_FB_Device_Base {
        model = QPATHTOF(data\fb_7_spent.p3d);
    };
    class COMSPEC_FB_Device_8: COMSPEC_FB_Device_Base {
        model = QPATHTOF(data\fb_8_spent.p3d);
    };
    class COMSPEC_FB_Device_9: COMSPEC_FB_Device_Base {
        model = QPATHTOF(data\fb_9_spent.p3d);
    };
    class COMSPEC_FB_Device_50: COMSPEC_FB_Device_Base {
        model = QPATHTOF(data\fb_50_spent.p3d);
    };
    class COMSPEC_FB_Device_Drill: COMSPEC_FB_Device_Base {
        model = QPATHTOF(data\fb_drill_spent.p3d);
    };
    // Variantes à délai : même engin, corps à marquage de délai (modèle fb_<V>_d<délai>_spent.p3d).
#define FB_DEVICE_DELAY(V,F,D) class COMSPEC_FB_Device_##V##_D##D: COMSPEC_FB_Device_Base { \
        model = QPATHTOF(data\fb_##F##_d##D##_spent.p3d); \
    }
#define FB_DEVICE_DELAYS(V,F) FB_DEVICE_DELAY(V,F,3); FB_DEVICE_DELAY(V,F,8)
    FB_DEVICE_DELAYS(1,1);
    FB_DEVICE_DELAYS(2,2);
    FB_DEVICE_DELAYS(3,3);
    FB_DEVICE_DELAYS(4,4);
    FB_DEVICE_DELAYS(5,5);
    FB_DEVICE_DELAYS(6,6);
    FB_DEVICE_DELAYS(7,7);
    FB_DEVICE_DELAYS(8,8);
    FB_DEVICE_DELAYS(9,9);
    FB_DEVICE_DELAYS(50,50);
    FB_DEVICE_DELAYS(Drill,drill);
