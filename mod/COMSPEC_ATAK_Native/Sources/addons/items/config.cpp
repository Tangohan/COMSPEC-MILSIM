// Objets d'inventaire ATAK en vraie 3D (tools/model_items.py) : modèles item3d_*.p3d, caméra posée camera_ip.p3d,
// atlas items_co / _nohq / _smdi. PBO à part de main (taille des modèles MLOD) ; les classes sont dans main/config.cpp.
class CfgPatches {
    class comspec_atak_native_items {
        name="COMSPEC ATAK Native - Objets 3D"; author="COMSPEC"; requiredVersion=2.14;
        requiredAddons[]={"comspec_atak_native_main"};
        units[]={}; weapons[]={};
    };
};
