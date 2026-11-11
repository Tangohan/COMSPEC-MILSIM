// Généré par tools/gen_relay_config.py : ne pas modifier à la main.
class COMSPEC_ATAK_RelayTower_M12: COMSPEC_ATAK_RelayTower_Base {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_REL_TOWERM12";
    model="\z\comspec_atak_native\addons\towers\data\relay_tower_m12.p3d";
    editorPreview="\z\comspec_atak_native\addons\towers\data\relay_tower_m12_preview.jpg";
    comspec_atak_relayRange=3000; comspec_atak_relayHeight=11; comspec_atak_relayThroughput=10; comspec_atak_relaySlots=32;
    armor=300; mapSize=2;
    hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_olive_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"};
    class DestructionEffects: DestructionEffects {
        class Ruin1 { simulation="ruin"; type="\z\comspec_atak_native\addons\towers\data\relay_tower_m12_ruin.p3d"; position=""; intensity=1; interval=1; lifeTime=1; };
    };
};
class COMSPEC_ATAK_RelayTower_M12_ruins: Ruins_F { scope=1; scopeCurator=0; author="COMSPEC"; displayName="$STR_COMSPEC_ATAK_REL_TOWERM12_RUIN"; model="\z\comspec_atak_native\addons\towers\data\relay_tower_m12_ruin.p3d"; };
class COMSPEC_ATAK_RelayTower_M12_Galva: COMSPEC_ATAK_RelayTower_M12 { displayName="$STR_COMSPEC_ATAK_REL_TOWERM12GALVA"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayTower_M12_RW: COMSPEC_ATAK_RelayTower_M12 { displayName="$STR_COMSPEC_ATAK_REL_TOWERM12RW"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_rw_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayTower_M12_Sand: COMSPEC_ATAK_RelayTower_M12 { displayName="$STR_COMSPEC_ATAK_REL_TOWERM12SAND"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_sand_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayTower_M20: COMSPEC_ATAK_RelayTower_Base {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_REL_TOWERM20";
    model="\z\comspec_atak_native\addons\towers\data\relay_tower_m20.p3d";
    editorPreview="\z\comspec_atak_native\addons\towers\data\relay_tower_m20_preview.jpg";
    comspec_atak_relayRange=4500; comspec_atak_relayHeight=18; comspec_atak_relayThroughput=15; comspec_atak_relaySlots=48;
    armor=400; mapSize=3;
    hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_olive_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"};
    class DestructionEffects: DestructionEffects {
        class Ruin1 { simulation="ruin"; type="\z\comspec_atak_native\addons\towers\data\relay_tower_m20_ruin.p3d"; position=""; intensity=1; interval=1; lifeTime=1; };
    };
};
class COMSPEC_ATAK_RelayTower_M20_ruins: Ruins_F { scope=1; scopeCurator=0; author="COMSPEC"; displayName="$STR_COMSPEC_ATAK_REL_TOWERM20_RUIN"; model="\z\comspec_atak_native\addons\towers\data\relay_tower_m20_ruin.p3d"; };
class COMSPEC_ATAK_RelayTower_M20_Galva: COMSPEC_ATAK_RelayTower_M20 { displayName="$STR_COMSPEC_ATAK_REL_TOWERM20GALVA"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayTower_M20_RW: COMSPEC_ATAK_RelayTower_M20 { displayName="$STR_COMSPEC_ATAK_REL_TOWERM20RW"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_rw_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayTower_M20_Sand: COMSPEC_ATAK_RelayTower_M20 { displayName="$STR_COMSPEC_ATAK_REL_TOWERM20SAND"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_sand_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayTower_30: COMSPEC_ATAK_RelayTower_Base {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_REL_TOWER30";
    model="\z\comspec_atak_native\addons\towers\data\relay_tower_30.p3d";
    editorPreview="\z\comspec_atak_native\addons\towers\data\relay_tower_30_preview.jpg";
    comspec_atak_relayRange=6000; comspec_atak_relayHeight=28; comspec_atak_relayThroughput=20; comspec_atak_relaySlots=64;
    armor=500; mapSize=4;
    hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"};
    class DestructionEffects: DestructionEffects {
        class Ruin1 { simulation="ruin"; type="\z\comspec_atak_native\addons\towers\data\relay_tower_30_ruin.p3d"; position=""; intensity=1; interval=1; lifeTime=1; };
    };
};
class COMSPEC_ATAK_RelayTower_30_ruins: Ruins_F { scope=1; scopeCurator=0; author="COMSPEC"; displayName="$STR_COMSPEC_ATAK_REL_TOWER30_RUIN"; model="\z\comspec_atak_native\addons\towers\data\relay_tower_30_ruin.p3d"; };
class COMSPEC_ATAK_RelayTower_30_RW: COMSPEC_ATAK_RelayTower_30 { displayName="$STR_COMSPEC_ATAK_REL_TOWER30RW"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_rw_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayTower_30_Olive: COMSPEC_ATAK_RelayTower_30 { displayName="$STR_COMSPEC_ATAK_REL_TOWER30OLIVE"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_olive_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayTower_30_Sand: COMSPEC_ATAK_RelayTower_30 { displayName="$STR_COMSPEC_ATAK_REL_TOWER30SAND"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_sand_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayTower_30_Light: COMSPEC_ATAK_RelayTower_30 { displayName="$STR_COMSPEC_ATAK_REL_TOWER30LIGHT"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants";
    class AnimationSources: AnimationSources { class eq1: eq1 { initPhase=0; }; class eq2: eq2 { initPhase=1; }; };
};
class COMSPEC_ATAK_RelayTower_30_Bare: COMSPEC_ATAK_RelayTower_30 { displayName="$STR_COMSPEC_ATAK_REL_TOWER30BARE"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants";
    class AnimationSources: AnimationSources { class eq1: eq1 { initPhase=1; }; class eq2: eq2 { initPhase=1; }; };
};
class COMSPEC_ATAK_RelayTower_40: COMSPEC_ATAK_RelayTower_Base {
    scope=2; scopeCurator=2;
    class MarkerLights: MarkerLights {
        class Mid1: Top { name="light_mid1"; blinking=0; intensity=40; drawLightSize=0.25; };
        class Mid2: Mid1 { name="light_mid2"; };
    };
    displayName="$STR_COMSPEC_ATAK_REL_TOWER40";
    model="\z\comspec_atak_native\addons\towers\data\relay_tower_40.p3d";
    editorPreview="\z\comspec_atak_native\addons\towers\data\relay_tower_40_preview.jpg";
    comspec_atak_relayRange=9000; comspec_atak_relayHeight=38; comspec_atak_relayThroughput=30; comspec_atak_relaySlots=96;
    armor=700; mapSize=5;
    hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"};
    class DestructionEffects: DestructionEffects {
        class Ruin1 { simulation="ruin"; type="\z\comspec_atak_native\addons\towers\data\relay_tower_40_ruin.p3d"; position=""; intensity=1; interval=1; lifeTime=1; };
    };
};
class COMSPEC_ATAK_RelayTower_40_ruins: Ruins_F { scope=1; scopeCurator=0; author="COMSPEC"; displayName="$STR_COMSPEC_ATAK_REL_TOWER40_RUIN"; model="\z\comspec_atak_native\addons\towers\data\relay_tower_40_ruin.p3d"; };
class COMSPEC_ATAK_RelayTower_40_RW: COMSPEC_ATAK_RelayTower_40 { displayName="$STR_COMSPEC_ATAK_REL_TOWER40RW"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_rw_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayTower_40_Olive: COMSPEC_ATAK_RelayTower_40 { displayName="$STR_COMSPEC_ATAK_REL_TOWER40OLIVE"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_olive_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayTower_40_Sand: COMSPEC_ATAK_RelayTower_40 { displayName="$STR_COMSPEC_ATAK_REL_TOWER40SAND"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_sand_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayTower_40_Light: COMSPEC_ATAK_RelayTower_40 { displayName="$STR_COMSPEC_ATAK_REL_TOWER40LIGHT"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants";
    class AnimationSources: AnimationSources { class eq1: eq1 { initPhase=0; }; class eq2: eq2 { initPhase=1; }; };
};
class COMSPEC_ATAK_RelayTower_40_Bare: COMSPEC_ATAK_RelayTower_40 { displayName="$STR_COMSPEC_ATAK_REL_TOWER40BARE"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants";
    class AnimationSources: AnimationSources { class eq1: eq1 { initPhase=1; }; class eq2: eq2 { initPhase=1; }; };
};
class COMSPEC_ATAK_RelayTower_50: COMSPEC_ATAK_RelayTower_Base {
    scope=2; scopeCurator=2;
    class MarkerLights: MarkerLights {
        class Mid1: Top { name="light_mid1"; blinking=0; intensity=40; drawLightSize=0.25; };
        class Mid2: Mid1 { name="light_mid2"; };
    };
    displayName="$STR_COMSPEC_ATAK_REL_TOWER50";
    model="\z\comspec_atak_native\addons\towers\data\relay_tower_50.p3d";
    editorPreview="\z\comspec_atak_native\addons\towers\data\relay_tower_50_preview.jpg";
    comspec_atak_relayRange=12000; comspec_atak_relayHeight=48; comspec_atak_relayThroughput=40; comspec_atak_relaySlots=128;
    armor=900; mapSize=6;
    hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"};
    class DestructionEffects: DestructionEffects {
        class Ruin1 { simulation="ruin"; type="\z\comspec_atak_native\addons\towers\data\relay_tower_50_ruin.p3d"; position=""; intensity=1; interval=1; lifeTime=1; };
    };
};
class COMSPEC_ATAK_RelayTower_50_ruins: Ruins_F { scope=1; scopeCurator=0; author="COMSPEC"; displayName="$STR_COMSPEC_ATAK_REL_TOWER50_RUIN"; model="\z\comspec_atak_native\addons\towers\data\relay_tower_50_ruin.p3d"; };
class COMSPEC_ATAK_RelayTower_50_RW: COMSPEC_ATAK_RelayTower_50 { displayName="$STR_COMSPEC_ATAK_REL_TOWER50RW"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_rw_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayTower_50_Olive: COMSPEC_ATAK_RelayTower_50 { displayName="$STR_COMSPEC_ATAK_REL_TOWER50OLIVE"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_olive_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayTower_50_Sand: COMSPEC_ATAK_RelayTower_50 { displayName="$STR_COMSPEC_ATAK_REL_TOWER50SAND"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_sand_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayTower_50_Light: COMSPEC_ATAK_RelayTower_50 { displayName="$STR_COMSPEC_ATAK_REL_TOWER50LIGHT"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants";
    class AnimationSources: AnimationSources { class eq1: eq1 { initPhase=0; }; class eq2: eq2 { initPhase=1; }; };
};
class COMSPEC_ATAK_RelayTower_50_Bare: COMSPEC_ATAK_RelayTower_50 { displayName="$STR_COMSPEC_ATAK_REL_TOWER50BARE"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants";
    class AnimationSources: AnimationSources { class eq1: eq1 { initPhase=1; }; class eq2: eq2 { initPhase=1; }; };
};
class COMSPEC_ATAK_RelayTower_Mono30: COMSPEC_ATAK_RelayTower_Base {
    scope=2; scopeCurator=2;
    class MarkerLights: MarkerLights {
        class Mid1: Top { name="light_mid1"; blinking=0; intensity=40; drawLightSize=0.25; };
        class Mid2: Mid1 { name="light_mid2"; };
    };
    displayName="$STR_COMSPEC_ATAK_REL_TOWERMONO30";
    model="\z\comspec_atak_native\addons\towers\data\relay_monopole_30.p3d";
    editorPreview="\z\comspec_atak_native\addons\towers\data\relay_monopole_30_preview.jpg";
    comspec_atak_relayRange=7000; comspec_atak_relayHeight=28; comspec_atak_relayThroughput=25; comspec_atak_relaySlots=80;
    armor=600; mapSize=4;
    hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"};
    class DestructionEffects: DestructionEffects {
        class Ruin1 { simulation="ruin"; type="\z\comspec_atak_native\addons\towers\data\relay_monopole_30_ruin.p3d"; position=""; intensity=1; interval=1; lifeTime=1; };
    };
};
class COMSPEC_ATAK_RelayTower_Mono30_ruins: Ruins_F { scope=1; scopeCurator=0; author="COMSPEC"; displayName="$STR_COMSPEC_ATAK_REL_TOWERMONO30_RUIN"; model="\z\comspec_atak_native\addons\towers\data\relay_monopole_30_ruin.p3d"; };
class COMSPEC_ATAK_RelayTower_Mono30_RW: COMSPEC_ATAK_RelayTower_Mono30 { displayName="$STR_COMSPEC_ATAK_REL_TOWERMONO30RW"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_rw_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayTower_Mono30_Olive: COMSPEC_ATAK_RelayTower_Mono30 { displayName="$STR_COMSPEC_ATAK_REL_TOWERMONO30OLIVE"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_olive_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayTower_Mono30_Sand: COMSPEC_ATAK_RelayTower_Mono30 { displayName="$STR_COMSPEC_ATAK_REL_TOWERMONO30SAND"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_sand_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayTower_Mono30_Light: COMSPEC_ATAK_RelayTower_Mono30 { displayName="$STR_COMSPEC_ATAK_REL_TOWERMONO30LIGHT"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants";
    class AnimationSources: AnimationSources { class eq1: eq1 { initPhase=0; }; class eq2: eq2 { initPhase=1; }; };
};
class COMSPEC_ATAK_RelayTower_Mono30_Bare: COMSPEC_ATAK_RelayTower_Mono30 { displayName="$STR_COMSPEC_ATAK_REL_TOWERMONO30BARE"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants";
    class AnimationSources: AnimationSources { class eq1: eq1 { initPhase=1; }; class eq2: eq2 { initPhase=1; }; };
};
class COMSPEC_ATAK_RelayTower_Guyed60: COMSPEC_ATAK_RelayTower_Base {
    scope=2; scopeCurator=2;
    class MarkerLights: MarkerLights {
        class Mid1: Top { name="light_mid1"; blinking=0; intensity=40; drawLightSize=0.25; };
        class Mid2: Mid1 { name="light_mid2"; };
    };
    displayName="$STR_COMSPEC_ATAK_REL_TOWERGUYED60";
    model="\z\comspec_atak_native\addons\towers\data\relay_guyed_60.p3d";
    editorPreview="\z\comspec_atak_native\addons\towers\data\relay_guyed_60_preview.jpg";
    comspec_atak_relayRange=15000; comspec_atak_relayHeight=58; comspec_atak_relayThroughput=40; comspec_atak_relaySlots=128;
    armor=500; mapSize=8;
    hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"};
    class DestructionEffects: DestructionEffects {
        class Ruin1 { simulation="ruin"; type="\z\comspec_atak_native\addons\towers\data\relay_guyed_60_ruin.p3d"; position=""; intensity=1; interval=1; lifeTime=1; };
    };
};
class COMSPEC_ATAK_RelayTower_Guyed60_ruins: Ruins_F { scope=1; scopeCurator=0; author="COMSPEC"; displayName="$STR_COMSPEC_ATAK_REL_TOWERGUYED60_RUIN"; model="\z\comspec_atak_native\addons\towers\data\relay_guyed_60_ruin.p3d"; };
class COMSPEC_ATAK_RelayTower_Guyed60_RW: COMSPEC_ATAK_RelayTower_Guyed60 { displayName="$STR_COMSPEC_ATAK_REL_TOWERGUYED60RW"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_rw_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayTower_Guyed60_Olive: COMSPEC_ATAK_RelayTower_Guyed60 { displayName="$STR_COMSPEC_ATAK_REL_TOWERGUYED60OLIVE"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_olive_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayTower_Guyed60_Sand: COMSPEC_ATAK_RelayTower_Guyed60 { displayName="$STR_COMSPEC_ATAK_REL_TOWERGUYED60SAND"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_sand_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayTower_Guyed60_Light: COMSPEC_ATAK_RelayTower_Guyed60 { displayName="$STR_COMSPEC_ATAK_REL_TOWERGUYED60LIGHT"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants";
    class AnimationSources: AnimationSources { class eq1: eq1 { initPhase=0; }; class eq2: eq2 { initPhase=1; }; };
};
class COMSPEC_ATAK_RelayTower_Guyed60_Bare: COMSPEC_ATAK_RelayTower_Guyed60 { displayName="$STR_COMSPEC_ATAK_REL_TOWERGUYED60BARE"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants";
    class AnimationSources: AnimationSources { class eq1: eq1 { initPhase=1; }; class eq2: eq2 { initPhase=1; }; };
};
class COMSPEC_ATAK_RelayTower_Pine28: COMSPEC_ATAK_RelayTower_Base {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_REL_TOWERPINE28";
    model="\z\comspec_atak_native\addons\towers\data\relay_tree_pine_28.p3d";
    editorPreview="\z\comspec_atak_native\addons\towers\data\relay_tree_pine_28_preview.jpg";
    comspec_atak_relayRange=7000; comspec_atak_relayHeight=22; comspec_atak_relayThroughput=25; comspec_atak_relaySlots=80;
    armor=600; mapSize=4;
    hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"};
    class DestructionEffects: DestructionEffects {
        class Ruin1 { simulation="ruin"; type="\z\comspec_atak_native\addons\towers\data\relay_tree_pine_28_ruin.p3d"; position=""; intensity=1; interval=1; lifeTime=1; };
    };
};
class COMSPEC_ATAK_RelayTower_Pine28_ruins: Ruins_F { scope=1; scopeCurator=0; author="COMSPEC"; displayName="$STR_COMSPEC_ATAK_REL_TOWERPINE28_RUIN"; model="\z\comspec_atak_native\addons\towers\data\relay_tree_pine_28_ruin.p3d"; };
class COMSPEC_ATAK_RelayTower_Pine15: COMSPEC_ATAK_RelayTower_Base {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_REL_TOWERPINE15";
    model="\z\comspec_atak_native\addons\towers\data\relay_tree_pine_15.p3d";
    editorPreview="\z\comspec_atak_native\addons\towers\data\relay_tree_pine_15_preview.jpg";
    comspec_atak_relayRange=4000; comspec_atak_relayHeight=11; comspec_atak_relayThroughput=15; comspec_atak_relaySlots=48;
    armor=450; mapSize=3;
    hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"};
    class DestructionEffects: DestructionEffects {
        class Ruin1 { simulation="ruin"; type="\z\comspec_atak_native\addons\towers\data\relay_tree_pine_15_ruin.p3d"; position=""; intensity=1; interval=1; lifeTime=1; };
    };
};
class COMSPEC_ATAK_RelayTower_Pine15_ruins: Ruins_F { scope=1; scopeCurator=0; author="COMSPEC"; displayName="$STR_COMSPEC_ATAK_REL_TOWERPINE15_RUIN"; model="\z\comspec_atak_native\addons\towers\data\relay_tree_pine_15_ruin.p3d"; };
class COMSPEC_ATAK_RelayTower_Palm22: COMSPEC_ATAK_RelayTower_Base {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_REL_TOWERPALM22";
    model="\z\comspec_atak_native\addons\towers\data\relay_tree_palm_22.p3d";
    editorPreview="\z\comspec_atak_native\addons\towers\data\relay_tree_palm_22_preview.jpg";
    comspec_atak_relayRange=6000; comspec_atak_relayHeight=18; comspec_atak_relayThroughput=20; comspec_atak_relaySlots=64;
    armor=550; mapSize=4;
    hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"};
    class DestructionEffects: DestructionEffects {
        class Ruin1 { simulation="ruin"; type="\z\comspec_atak_native\addons\towers\data\relay_tree_palm_22_ruin.p3d"; position=""; intensity=1; interval=1; lifeTime=1; };
    };
};
class COMSPEC_ATAK_RelayTower_Palm22_ruins: Ruins_F { scope=1; scopeCurator=0; author="COMSPEC"; displayName="$STR_COMSPEC_ATAK_REL_TOWERPALM22_RUIN"; model="\z\comspec_atak_native\addons\towers\data\relay_tree_palm_22_ruin.p3d"; };
class COMSPEC_ATAK_RelayTower_Roof8: COMSPEC_ATAK_RelayTower_Base {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_REL_TOWERROOF8";
    model="\z\comspec_atak_native\addons\towers\data\relay_rooftop_8.p3d";
    editorPreview="\z\comspec_atak_native\addons\towers\data\relay_rooftop_8_preview.jpg";
    comspec_atak_relayRange=3500; comspec_atak_relayHeight=8; comspec_atak_relayThroughput=15; comspec_atak_relaySlots=48;
    armor=250; mapSize=2;
    hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"};
    class DestructionEffects: DestructionEffects {
        class Ruin1 { simulation="ruin"; type="\z\comspec_atak_native\addons\towers\data\relay_rooftop_8_ruin.p3d"; position=""; intensity=1; interval=1; lifeTime=1; };
    };
};
class COMSPEC_ATAK_RelayTower_Roof8_ruins: Ruins_F { scope=1; scopeCurator=0; author="COMSPEC"; displayName="$STR_COMSPEC_ATAK_REL_TOWERROOF8_RUIN"; model="\z\comspec_atak_native\addons\towers\data\relay_rooftop_8_ruin.p3d"; };
class COMSPEC_ATAK_RelayTower_Roof8_RW: COMSPEC_ATAK_RelayTower_Roof8 { displayName="$STR_COMSPEC_ATAK_REL_TOWERROOF8RW"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_rw_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayTower_Roof8_Olive: COMSPEC_ATAK_RelayTower_Roof8 { displayName="$STR_COMSPEC_ATAK_REL_TOWERROOF8OLIVE"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_olive_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayTower_Roof8_Sand: COMSPEC_ATAK_RelayTower_Roof8 { displayName="$STR_COMSPEC_ATAK_REL_TOWERROOF8SAND"; editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayVariants"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_sand_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
// Antennes murales, de poteau et de toit : relais du réseau dès la pose (même enregistrement que les pylônes).
// Origine au plan du mur (Y = 0), +Y sortant du mur ; poteau : origine sur l'axe ; mât de toit : origine au toit.
class COMSPEC_ATAK_RelayAntenna_Base: COMSPEC_ATAK_RelayTower_Base {
    editorSubcategory="COMSPEC_ATAK_EdSubcat_RelayAntennas"; icon="iconObject_circle"; mapSize=1; armor=40; cost=5000;
    comspec_atak_relayNoun="Antenne";
    class MarkerLights {};
    class AnimationSources {};
    class DestructionEffects {};
};
class COMSPEC_ATAK_RelayAntenna_WallSector: COMSPEC_ATAK_RelayAntenna_Base {
    scope=2; scopeCurator=2; displayName="$STR_COMSPEC_ATAK_REL_ANTENNAWALLSECTOR";
    model="\z\comspec_atak_native\addons\towers\data\relay_wall_sector.p3d"; editorPreview="\z\comspec_atak_native\addons\towers\data\relay_wall_sector_preview.jpg";
    comspec_atak_relayRange=2500; comspec_atak_relayHeight=3; comspec_atak_relayThroughput=15; comspec_atak_relaySlots=32;
};
class COMSPEC_ATAK_RelayAntenna_WallSector_Olive: COMSPEC_ATAK_RelayAntenna_WallSector { displayName="$STR_COMSPEC_ATAK_REL_ANTENNAWALLSECTOROLIVE"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_olive_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayAntenna_WallSector_Sand: COMSPEC_ATAK_RelayAntenna_WallSector { displayName="$STR_COMSPEC_ATAK_REL_ANTENNAWALLSECTORSAND"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_sand_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayAntenna_WallOmni: COMSPEC_ATAK_RelayAntenna_Base {
    scope=2; scopeCurator=2; displayName="$STR_COMSPEC_ATAK_REL_ANTENNAWALLOMNI";
    model="\z\comspec_atak_native\addons\towers\data\relay_wall_omni.p3d"; editorPreview="\z\comspec_atak_native\addons\towers\data\relay_wall_omni_preview.jpg";
    comspec_atak_relayRange=1500; comspec_atak_relayHeight=2; comspec_atak_relayThroughput=10; comspec_atak_relaySlots=24;
};
class COMSPEC_ATAK_RelayAntenna_WallOmni_Olive: COMSPEC_ATAK_RelayAntenna_WallOmni { displayName="$STR_COMSPEC_ATAK_REL_ANTENNAWALLOMNIOLIVE"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_olive_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayAntenna_WallOmni_Sand: COMSPEC_ATAK_RelayAntenna_WallOmni { displayName="$STR_COMSPEC_ATAK_REL_ANTENNAWALLOMNISAND"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_sand_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayAntenna_WallDish: COMSPEC_ATAK_RelayAntenna_Base {
    scope=2; scopeCurator=2; displayName="$STR_COMSPEC_ATAK_REL_ANTENNAWALLDISH";
    model="\z\comspec_atak_native\addons\towers\data\relay_wall_dish.p3d"; editorPreview="\z\comspec_atak_native\addons\towers\data\relay_wall_dish_preview.jpg";
    comspec_atak_relayRange=5000; comspec_atak_relayHeight=2; comspec_atak_relayThroughput=30; comspec_atak_relaySlots=16;
};
class COMSPEC_ATAK_RelayAntenna_WallDish_Olive: COMSPEC_ATAK_RelayAntenna_WallDish { displayName="$STR_COMSPEC_ATAK_REL_ANTENNAWALLDISHOLIVE"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_olive_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayAntenna_WallDish_Sand: COMSPEC_ATAK_RelayAntenna_WallDish { displayName="$STR_COMSPEC_ATAK_REL_ANTENNAWALLDISHSAND"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_sand_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayAntenna_WallYagi: COMSPEC_ATAK_RelayAntenna_Base {
    scope=2; scopeCurator=2; displayName="$STR_COMSPEC_ATAK_REL_ANTENNAWALLYAGI";
    model="\z\comspec_atak_native\addons\towers\data\relay_wall_yagi.p3d"; editorPreview="\z\comspec_atak_native\addons\towers\data\relay_wall_yagi_preview.jpg";
    comspec_atak_relayRange=3500; comspec_atak_relayHeight=2; comspec_atak_relayThroughput=8; comspec_atak_relaySlots=16;
};
class COMSPEC_ATAK_RelayAntenna_WallYagi_Olive: COMSPEC_ATAK_RelayAntenna_WallYagi { displayName="$STR_COMSPEC_ATAK_REL_ANTENNAWALLYAGIOLIVE"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_olive_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayAntenna_WallYagi_Sand: COMSPEC_ATAK_RelayAntenna_WallYagi { displayName="$STR_COMSPEC_ATAK_REL_ANTENNAWALLYAGISAND"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_sand_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayAntenna_WallRadio: COMSPEC_ATAK_RelayAntenna_Base {
    scope=2; scopeCurator=2; displayName="$STR_COMSPEC_ATAK_REL_ANTENNAWALLRADIO";
    model="\z\comspec_atak_native\addons\towers\data\relay_wall_radio.p3d"; editorPreview="\z\comspec_atak_native\addons\towers\data\relay_wall_radio_preview.jpg";
    comspec_atak_relayRange=800; comspec_atak_relayHeight=1; comspec_atak_relayThroughput=8; comspec_atak_relaySlots=16;
};
class COMSPEC_ATAK_RelayAntenna_WallRadio_Olive: COMSPEC_ATAK_RelayAntenna_WallRadio { displayName="$STR_COMSPEC_ATAK_REL_ANTENNAWALLRADIOOLIVE"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_olive_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayAntenna_WallRadio_Sand: COMSPEC_ATAK_RelayAntenna_WallRadio { displayName="$STR_COMSPEC_ATAK_REL_ANTENNAWALLRADIOSAND"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_sand_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayAntenna_Pole: COMSPEC_ATAK_RelayAntenna_Base {
    scope=2; scopeCurator=2; displayName="$STR_COMSPEC_ATAK_REL_ANTENNAPOLE";
    model="\z\comspec_atak_native\addons\towers\data\relay_pole_node.p3d"; editorPreview="\z\comspec_atak_native\addons\towers\data\relay_pole_node_preview.jpg";
    comspec_atak_relayRange=1200; comspec_atak_relayHeight=2; comspec_atak_relayThroughput=8; comspec_atak_relaySlots=24;
};
class COMSPEC_ATAK_RelayAntenna_Pole_Olive: COMSPEC_ATAK_RelayAntenna_Pole { displayName="$STR_COMSPEC_ATAK_REL_ANTENNAPOLEOLIVE"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_olive_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayAntenna_Pole_Sand: COMSPEC_ATAK_RelayAntenna_Pole { displayName="$STR_COMSPEC_ATAK_REL_ANTENNAPOLESAND"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_sand_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayAntenna_RoofMast: COMSPEC_ATAK_RelayAntenna_Base {
    scope=2; scopeCurator=2; displayName="$STR_COMSPEC_ATAK_REL_ANTENNAROOFMAST";
    model="\z\comspec_atak_native\addons\towers\data\relay_roof_mast.p3d"; editorPreview="\z\comspec_atak_native\addons\towers\data\relay_roof_mast_preview.jpg";
    comspec_atak_relayRange=2000; comspec_atak_relayHeight=3; comspec_atak_relayThroughput=12; comspec_atak_relaySlots=32;
};
class COMSPEC_ATAK_RelayAntenna_RoofMast_Olive: COMSPEC_ATAK_RelayAntenna_RoofMast { displayName="$STR_COMSPEC_ATAK_REL_ANTENNAROOFMASTOLIVE"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_olive_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
class COMSPEC_ATAK_RelayAntenna_RoofMast_Sand: COMSPEC_ATAK_RelayAntenna_RoofMast { displayName="$STR_COMSPEC_ATAK_REL_ANTENNAROOFMASTSAND"; hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_sand_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"}; };
