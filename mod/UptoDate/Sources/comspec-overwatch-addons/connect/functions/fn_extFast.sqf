/*
    Liste blanche des commandes COMSPEC Link (et de la DLL native) qu'on peut appeler en direct sur le fil du jeu :
    réponse lue en mémoire, dans un cache rafraîchi en tâche de fond (sondages) ou simple mise en file d'envoi.
    Toute autre commande fait de l'HTTP ou du disque (jusqu'à 8 s) : fn_extDirect et fn_extAwait la passent par
    « Async » (rappel de la DLL) et ne l'appellent jamais en direct (voir l'inventaire dans Extension.cs).
    Params : [commande]. Retour : true = rapide.
*/
params [["_cmd", ""]];
private _fast = uiNamespace getVariable "COMSPEC_ExtFastCmds";
if (isNil "_fast") then {
    _fast = createHashMapFromArray ([
        // Mémoire / état local
        "Ping", "Warmup", "GetExtensionVersion", "GetVersion", "GetAtakFeatures", "GetCapabilities", "SendQueue.Status",
        "SyncStats", "MemStats", "Perf.Stats", "GetPendingQueueCount", "GetLastPostError", "GetSessionTenant", "GetAuthState",
        "GetAuthProgress", "SetSteamId", "SetMapId", "SetBloodType", "SetTelemetryBatch", "SetTelemetryMode",
        "GetTelemetryMetrics", "EmitTelemetry", "HashText", "LogWrite", "LogSessionStart", "GetLogTail", "GetCommunityOrbatChunk", "GetYearReviewChunk",
        "OpenAthenaLink", "LoadGoogleDeck", "LoadGoogleSlide", "CancelGoogleDeck", "Logout",
        // Bloquants voulus : fenêtres Windows modales (menu principal) ; Disconnect (3 s au plus) en fin de mission ou
        // à la fermeture du jeu, qui doit partir avant l'arrêt du processus
        "ShowBetaAccessNote", "ShowAthenaLinkHelp", "Disconnect", "ClientDisconnect",
        // Mise en file d'envoi (réponse immédiate, HTTP par la file de la DLL)
        "UpdatePosition", "SendMarker", "SendFlightManifest", "SendWeather", "SendSigint", "SendRfHit",
        "UpdateVehicleTracking", "SendChat", "SendPing", "SendIntel", "SendDesignator", "UpdateRelay", "PublishViewshed",
        "ReportPlaytime", "Logistics.Update", "IFF.Response", "SendVideoFeeds", "SendCASState", "SendCASAck",
        "SendCASCheckLine", "SyncLaserCode", "PilotResponse", "SubmitExplosiveTimer", "ScreenTime.Batch",
        "ScreenTime.Report", "Squad.Sync", "Garmin.Report", "NetConditions.Report", "Distance.Batch", "BriefingPlan.Push",
        "AdminControl.Ack", "Item.Icons", "Terrain.Chunk", "Scene.Ingest", "Geo.Ingest", "NotifyNewPhoto",
        "EnqueueReconImage", "UploadSsePhoto", "UploadSseNoteAttachment",
        // Sondages servis par le cache de la DLL (ServePollGet)
        "GetMarkers", "GetUnits", "GetPings", "GetClientIp", "Server.PublicIp", "GetCASForCallsign", "GetResupplyStatus",
        "GetMapShapes", "GetDataFlows", "GetModModules", "GetExperience", "GetRoleplayConfig", "GetMarkerDetectionRules",
        "GetChatMessages", "GetSigintZones", "GetChatChannels", "GetTacticalAlerts", "GetMedicalAlerts", "GetOrders",
        "GetWaypoints", "GetTacticalZones", "GetMissionPlan", "GetExplosiveCommands", "GetAiOrders",
        // DLL native : lecteur audio (MusicList servi par le cache de la DLL)
        "MusicList", "MusicPlay", "MusicPause", "MusicResume", "MusicStop", "MusicVolume", "MusicStatus", "MusicOpenFolder"
    ] apply { [_x, true] });
    uiNamespace setVariable ["COMSPEC_ExtFastCmds", _fast];
};
_cmd in _fast
