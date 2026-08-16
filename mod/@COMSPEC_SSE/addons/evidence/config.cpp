#include "script_component.hpp"

class CfgPatches {
    class comspec_sse_evidence {
        name = "COMSPEC SSE - Evidence Items";
        units[] = {};
        weapons[] = {
            "COMSPEC_SSE_EvidenceBag",
            "COMSPEC_SSE_Gloves",
            "COMSPEC_SSE_Camera",
            "COMSPEC_SSE_FingerprintKit",
            "COMSPEC_SSE_DNKit",
            "COMSPEC_SSE_SEEKII",
            "COMSPEC_SSE_USB",
            "COMSPEC_SSE_SDCard",
            "COMSPEC_SSE_HardDrive",
            "COMSPEC_SSE_Laptop",
            "COMSPEC_SSE_Phone",
            "COMSPEC_SSE_Smartphone",
            "COMSPEC_SSE_DocumentBag",
            "COMSPEC_SSE_Notebook",
            "COMSPEC_SSE_SatPhone",
            "COMSPEC_SSE_Terminal"
        };
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {
            "comspec_sse_main",
            "A3_Weapons_F",
            "cba_main",
            "cba_common",
            "ace_common"
        };
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgWeapons {
    // Compatible inventaire ACE / Arsenal (CBA MiscItem)
    class CBA_MiscItem;
    class CBA_MiscItem_ItemInfo;

    class COMSPEC_SSE_ItemBase: CBA_MiscItem {
        scope = 1;
        author = "COMSPEC";
        descriptionShort = "Matériel SSE COMSPEC";
        picture = "\A3\Weapons_F\Data\placeholder_co.paa";
        model = "\A3\weapons_F\ammo\mag_univ.p3d";
        class ItemInfo: CBA_MiscItem_ItemInfo {
            mass = 1;
        };
    };

    class COMSPEC_SSE_EvidenceBag: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "Sachet de preuve SSE";
        descriptionShort = "Sachet stérile pour collecte et mise sous scellé d'éléments de preuve.";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 1; };
    };

    class COMSPEC_SSE_Gloves: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "Gants d'examen SSE";
        descriptionShort = "Gants jetables pour manipulation de preuves.";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 0.5; };
    };

    class COMSPEC_SSE_Camera: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "Appareil photo SSE";
        descriptionShort = "Appareil photo pour documentation photographique de site.";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 3; };
    };

    class COMSPEC_SSE_FingerprintKit: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "Kit d'empreintes SSE";
        descriptionShort = "Kit de relevé d'empreintes digitales.";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 4; };
    };

    class COMSPEC_SSE_DNKit: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "Kit ADN SSE";
        descriptionShort = "Kit de prélèvement ADN terrain.";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 4; };
    };

    class COMSPEC_SSE_SEEKII: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "Terminal SEEK II";
        descriptionShort = "Terminal biométrique SEEK II (identité, empreintes, iris, photo).";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 8; };
    };

    class COMSPEC_SSE_USB: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "Clé USB saisie";
        descriptionShort = "Support numérique USB collecté sur site.";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 0.5; };
    };

    class COMSPEC_SSE_SDCard: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "Carte mémoire saisie";
        descriptionShort = "Carte SD / microSD collectée sur site.";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 0.2; };
    };

    class COMSPEC_SSE_HardDrive: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "Disque dur saisi";
        descriptionShort = "Disque dur extrait pour exploitation numérique.";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 6; };
    };

    class COMSPEC_SSE_Laptop: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "Ordinateur portable saisi";
        descriptionShort = "Laptop collecté pour exploitation SSE.";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 25; };
    };

    class COMSPEC_SSE_Phone: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "Téléphone saisi";
        descriptionShort = "Téléphone mobile collecté.";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 2; };
    };

    class COMSPEC_SSE_Smartphone: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "Smartphone saisi";
        descriptionShort = "Smartphone collecté pour extraction numérique.";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 2; };
    };

    class COMSPEC_SSE_DocumentBag: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "Pochette documents SSE";
        descriptionShort = "Pochette pour documents papier saisis.";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 1; };
    };

    class COMSPEC_SSE_Notebook: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "Carnet saisi";
        descriptionShort = "Carnet / notes manuscrites collectées.";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 2; };
    };

    class COMSPEC_SSE_SatPhone: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "Téléphone satellite saisi";
        descriptionShort = "Téléphone satellitaire collecté pour exploitation.";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 5; };
    };

    class COMSPEC_SSE_Terminal: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "Terminal SSE terrain";
        descriptionShort = "Terminal d'exploitation SSE : dossiers, digital, preuves, graphe et mission intel.";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 10; };
    };
};
