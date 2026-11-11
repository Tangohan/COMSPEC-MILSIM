// Autorisations remoteExec (utiles si la mission n'a pas de liste blanche propre ; sinon les y ajouter, voir README).
class CfgRemoteExec {
    class Functions {
        class comspec_fb_fnc_serverDetonate { allowedTargets = 2; };
        class comspec_fb_fnc_serverArea { allowedTargets = 2; };
        class comspec_fb_fnc_timeline { allowedTargets = 0; };
        class comspec_fb_fnc_cancel { allowedTargets = 0; };
        class comspec_fb_fnc_aiRestore { allowedTargets = 0; };
    };
};
