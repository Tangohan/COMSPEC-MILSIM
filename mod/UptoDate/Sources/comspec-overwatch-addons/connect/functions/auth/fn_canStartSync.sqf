/*
    Session Athena utilisable pour les transmissions (canal poste OK).
    AthenaReady est l’autorité Tx (posé par applyBootstrap sans C2_*).
    isReady seul peut diverger si GetAuthState flicker hors READY alors
    que le canal reste ouvert — d’où le repli sur AthenaReady.
    Pendant HandshakeQuiet : pas de Tx (évite le gel / spam 401 au boot).
*/
(!(missionNamespace getVariable ["COMSPEC_HandshakeQuiet", false]))
&& {
    (missionNamespace getVariable ["COMSPEC_AthenaReady", false])
    || {
        ([] call comspec_overwatch_connect_fnc_isReady)
        && {[] call comspec_overwatch_connect_fnc_isC2Ok}
    }
}
