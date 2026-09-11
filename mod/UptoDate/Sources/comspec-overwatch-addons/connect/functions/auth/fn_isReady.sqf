/*
    Session Athena prête (compte lié, état READY).
    Les transmissions exigent aussi isC2Ok / canStartSync (canal poste).
*/
(missionNamespace getVariable ["comspec_overwatch_auth_state", ""]) isEqualTo "READY"
