#!/usr/bin/env python3
"""Met a jour la description Steam Workshop de l'ATAK natif (PublisherCmd ne le fait pas).

Passe par l'API flat Steamworks de steam_api64.dll (celle d'Arma 3), au nom du compte
Steam connecte sur ce PC (il doit etre proprietaire ou contributeur de l'objet).

  py -3 tools/steam_set_description.py --dry-run
  py -3 tools/steam_set_description.py --note "Description mise a jour (2.9.34)"

Options : --file (defaut publisher/STEAM_DESCRIPTION.txt), --item (3684656708),
--dll (steam_api64.dll), --language (french, english... : description propre a une langue
Steam ; sans l'option, c'est la description par defaut).
Steam doit etre lance et connecte. Pendant l'envoi, Steam vous affiche "en jeu : Arma 3".
"""
import argparse
import ctypes as C
import os
import sys
import tempfile
import time
from pathlib import Path

APPID = 107410
ITEM = 3684656708
DESC_MAX = 8000  # k_cchPublishedDocumentDescriptionMax
CB_SUBMIT_ITEM_UPDATE_RESULT = 3404  # k_iSteamUGCCallbacks + 4
HERE = Path(__file__).resolve().parent
DEFAULT_FILE = HERE.parent / "publisher" / "STEAM_DESCRIPTION.txt"
DLL_CANDIDATES = [
    Path(r"F:\SteamLibrary\steamapps\common\Arma 3\steam_api64.dll"),
    Path(r"C:\Program Files (x86)\Steam\steamapps\common\Arma 3\steam_api64.dll"),
    Path(r"F:\SteamLibrary\steamapps\common\Arma 3 Tools\Publisher\steam_api64.dll"),
]
STATUS = {0: "invalide", 1: "preparation", 2: "preparation du contenu", 3: "envoi du contenu",
          4: "envoi de l'apercu", 5: "validation"}
ERESULT = {1: "OK", 2: "echec", 3: "pas de connexion a Steam", 8: "parametre invalide",
           9: "objet introuvable", 15: "acces refuse", 16: "delai depasse", 25: "limite depassee",
           44: "objet verrouille", 84: "trop de requetes (reessayer plus tard)"}


class SubmitItemUpdateResult(C.Structure):
    _pack_ = 8
    _fields_ = [("eResult", C.c_int32), ("bNeedsLegal", C.c_bool), ("fileId", C.c_uint64)]


def backup_current(item):
    """Sauve la description en ligne (API web publique) avant de la remplacer."""
    import json
    import urllib.parse
    import urllib.request
    data = urllib.parse.urlencode({"itemcount": 1, "publishedfileids[0]": str(item)}).encode()
    url = "https://api.steampowered.com/ISteamRemoteStorage/GetPublishedFileDetails/v1/"
    try:
        with urllib.request.urlopen(url, data, timeout=15) as r:
            d = json.load(r)["response"]["publishedfiledetails"][0]
    except Exception as e:  # noqa: BLE001
        print("Sauvegarde de la description en ligne impossible (%s) : on continue." % e)
        return
    out = HERE.parent / "publisher" / ("_backup_ws_description_%d_%s.txt" % (item, time.strftime("%Y%m%d-%H%M%S")))
    out.write_text(d.get("description", ""), encoding="utf-8")
    print("Objet : %s (createur %s) ; description en ligne sauvee dans %s" % (d.get("title"), d.get("creator"), out))


def die(msg, code=1):
    print("ERREUR : " + msg)
    sys.exit(code)


def load_api(dll_path):
    if C.sizeof(C.c_void_p) != 8:
        die("il faut un Python 64 bits (steam_api64.dll).")
    api = C.CDLL(str(dll_path))
    exports = [n for n in ("SteamAPI_SteamUGC_v021", "SteamAPI_SteamUGC_v020", "SteamAPI_SteamUGC_v019",
                           "SteamAPI_SteamUGC_v018", "SteamAPI_SteamUGC_v017", "SteamAPI_SteamUGC_v016")
               if hasattr(api, n)]
    if not exports:
        die("aucun accesseur SteamAPI_SteamUGC_v0XX dans " + str(dll_path))
    utils = [n for n in ("SteamAPI_SteamUtils_v010", "SteamAPI_SteamUtils_v009") if hasattr(api, n)]
    if not utils:
        die("aucun accesseur SteamAPI_SteamUtils_v0XX dans " + str(dll_path))
    return api, exports[0], utils[0]


def steam_init(api):
    if hasattr(api, "SteamAPI_InitFlat"):
        err = C.create_string_buffer(1024)
        api.SteamAPI_InitFlat.restype = C.c_int
        api.SteamAPI_InitFlat.argtypes = [C.c_char_p]
        r = api.SteamAPI_InitFlat(err)
        if r != 0:
            die("SteamAPI_InitFlat = %d : %s (Steam lance et connecte ?)" % (r, err.value.decode("utf-8", "replace")))
        return "SteamAPI_InitFlat"
    api.SteamAPI_Init.restype = C.c_bool
    if not api.SteamAPI_Init():
        die("SteamAPI_Init a echoue (Steam lance et connecte ? Arma 3 possede par ce compte ?)")
    return "SteamAPI_Init"


def main():
    ap = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    ap.add_argument("--file", type=Path, default=DEFAULT_FILE)
    ap.add_argument("--item", type=int, default=ITEM)
    ap.add_argument("--dll", type=Path)
    ap.add_argument("--note", default="", help="note de modification (historique Workshop)")
    ap.add_argument("--language", help="langue Steam de la description (ex. french) ; defaut : description par defaut")
    ap.add_argument("--dry-run", action="store_true", help="init + StartItemUpdate + SetItemDescription, sans envoi")
    ap.add_argument("--timeout", type=int, default=120)
    a = ap.parse_args()

    text = a.file.read_text(encoding="utf-8").replace("\r\n", "\n").strip() + "\n"
    nbytes = len(text.encode("utf-8"))
    print("Description : %s (%d caracteres, %d octets UTF-8, limite %d)" % (a.file, len(text), nbytes, DESC_MAX))
    if nbytes > DESC_MAX:
        die("description trop longue de %d octets." % (nbytes - DESC_MAX))

    backup_current(a.item)
    dll = a.dll or next((p for p in DLL_CANDIDATES if p.is_file()), None)
    if not dll or not dll.is_file():
        die("steam_api64.dll introuvable (option --dll).")
    api, ugc_name, utils_name = load_api(dll)
    print("DLL : %s (%s, %s)" % (dll, ugc_name, utils_name))

    # steam_appid.txt dans un dossier temporaire servant de dossier courant
    work = tempfile.mkdtemp(prefix="comspec_steamdesc_")
    (Path(work) / "steam_appid.txt").write_text(str(APPID), encoding="ascii")
    old_cwd = os.getcwd()
    os.chdir(work)
    os.environ["SteamAppId"] = os.environ["SteamGameId"] = str(APPID)
    how = steam_init(api)
    print("Steam initialise (%s, appid %d)" % (how, APPID))
    try:
        getattr(api, ugc_name).restype = C.c_void_p
        getattr(api, utils_name).restype = C.c_void_p
        ugc = getattr(api, ugc_name)()
        utils = getattr(api, utils_name)()
        if not ugc or not utils:
            die("interface ISteamUGC / ISteamUtils indisponible.")

        f = api.SteamAPI_ISteamUGC_StartItemUpdate
        f.restype, f.argtypes = C.c_uint64, [C.c_void_p, C.c_uint32, C.c_uint64]
        handle = f(ugc, APPID, a.item)
        if handle in (0, 0xFFFFFFFFFFFFFFFF):
            die("StartItemUpdate a refuse l'objet %d." % a.item)
        print("StartItemUpdate OK (objet %d, handle %#x)" % (a.item, handle))

        if a.language:
            f = api.SteamAPI_ISteamUGC_SetItemUpdateLanguage
            f.restype, f.argtypes = C.c_bool, [C.c_void_p, C.c_uint64, C.c_char_p]
            if not f(ugc, handle, a.language.encode("ascii")):
                die("SetItemUpdateLanguage(%s) refuse." % a.language)
        f = api.SteamAPI_ISteamUGC_SetItemDescription
        f.restype, f.argtypes = C.c_bool, [C.c_void_p, C.c_uint64, C.c_char_p]
        if not f(ugc, handle, text.encode("utf-8")):
            die("SetItemDescription refuse (texte trop long ?).")
        print("SetItemDescription OK")

        if a.dry_run:
            print("DRY-RUN : rien n'a ete envoye a Steam (pas de SubmitItemUpdate).")
            return 0

        f = api.SteamAPI_ISteamUGC_SubmitItemUpdate
        f.restype, f.argtypes = C.c_uint64, [C.c_void_p, C.c_uint64, C.c_char_p]
        call = f(ugc, handle, a.note.encode("utf-8") if a.note else None)
        if not call:
            die("SubmitItemUpdate n'a pas demarre.")
        print("Envoi en cours...")

        prog = api.SteamAPI_ISteamUGC_GetItemUpdateProgress
        prog.restype, prog.argtypes = C.c_int, [C.c_void_p, C.c_uint64, C.POINTER(C.c_uint64), C.POINTER(C.c_uint64)]
        done = api.SteamAPI_ISteamUtils_IsAPICallCompleted
        done.restype, done.argtypes = C.c_bool, [C.c_void_p, C.c_uint64, C.POINTER(C.c_bool)]
        get = api.SteamAPI_ISteamUtils_GetAPICallResult
        get.restype = C.c_bool
        get.argtypes = [C.c_void_p, C.c_uint64, C.c_void_p, C.c_int, C.c_int, C.POINTER(C.c_bool)]

        failed, last, t0 = C.c_bool(False), None, time.time()
        while not done(utils, call, C.byref(failed)):
            api.SteamAPI_RunCallbacks()
            p, t = C.c_uint64(0), C.c_uint64(0)
            st = prog(ugc, handle, C.byref(p), C.byref(t))
            if st != last:
                print("  etat : " + STATUS.get(st, str(st)))
                last = st
            if time.time() - t0 > a.timeout:
                die("pas de reponse de Steam apres %d s." % a.timeout)
            time.sleep(0.1)

        res = SubmitItemUpdateResult()
        if failed.value or not get(utils, call, C.byref(res), C.sizeof(res), CB_SUBMIT_ITEM_UPDATE_RESULT, C.byref(failed)) or failed.value:
            die("resultat de SubmitItemUpdate illisible (appel echoue).")
        if res.eResult != 1:
            die("Steam a refuse la mise a jour : EResult %d (%s)." % (res.eResult, ERESULT.get(res.eResult, "voir EResult")))
        print("OK : description publiee sur https://steamcommunity.com/sharedfiles/filedetails/?id=%d" % res.fileId)
        if res.bNeedsLegal:
            print("Attention : le compte doit accepter l'accord juridique du Workshop pour que l'objet soit visible.")
        return 0
    finally:
        api.SteamAPI_Shutdown()
        os.chdir(old_cwd)


if __name__ == "__main__":
    sys.exit(main())
