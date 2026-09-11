COMSPEC_ATAK_UI_GENERATION = "native-rsc-v1";
missionNamespace setVariable ["COMSPEC_ATAK_UI_GENERATION", COMSPEC_ATAK_UI_GENERATION, true];
missionNamespace setVariable ["COMSPEC_ATAK_NativeVersion", "1.0.0", true];
diag_log "[COMSPEC ATAK NATIVE][BOOT][CANARY] native_client_v1_0_0_loaded";
diag_log "[COMSPEC ATAK NATIVE][INFO][BOOT] UI generation: native-rsc-v1";
[] call comspec_atak_native_fnc_stateInit;
