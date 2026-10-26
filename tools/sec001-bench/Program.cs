// Banc de non-régression SEC-001 — audit COMSPEC ATAK.
//
// But : PROUVER que la DLL COMSPEC Link n'envoie aucun secret Athena (clé communauté X-COMSPEC-KEY,
// Authorization: Bearer, jeton de session X-COMSPEC-SESSION, Steam X-COMSPEC-STEAM, et api_key /
// session_token dans les CORPS) vers une destination non-Athena, et qu'elle continue de les envoyer au
// portail Athena.
//
// HERMÉTIQUE : à lancer dans un espace réseau isolé (`unshare -n`, voir README) ; aucune adresse de
// production n'est écrite dans _baseUrl (adresse morte http://127.0.0.1:9 au repos). Tout tourne sur la
// boucle locale (127.0.0.1 = Athena simulé « dev », 127.0.0.2 = attaquant, joignable sous Linux mais hors
// liste blanche). Secrets FACTICES.
//
// La boucle locale n'est de confiance que si COMSPEC_DEV_TRUST_LOOPBACK=1 (réglage de développement) : le
// banc le pose lui-même. BENCH_PROD=1 ne le pose PAS et vérifie qu'en production la boucle locale ne reçoit
// aucun secret.
//
// Charge la DLL MANAGÉE (dotnet build, pas la .so AOT) par réflexion et appelle ses méthodes internes.
//   BENCH_DLL=/chemin/COMSPECExtension_x64.dll   (obligatoire)
// Sortie : lignes PASS/FAIL ; code de sortie 0 si aucune fuite inattendue, 1 sinon.

using System.Net;
using System.Net.Sockets;
using System.Reflection;
using System.Text;

const string KEY = "ow_FAKEKEY_1111111111111111deadbeef";       // clé communauté factice
const string SESSION = "ffffffffffffffffffffffffffffffff11111111111111111111111111111111"; // jeton session factice
const string STEAM = "76561197960265728";                        // SteamID64 factice (valide en forme)
const string GAMETOK = "GAMEACCESS_FAKE_aaaaaaaaaaaaaaaaaaaaaaaa"; // jeton d'accès de jeu (Bearer du compte) factice
const string DEAD = "http://127.0.0.1:9";                        // adresse morte : jamais de requête utile

var prodMode = Environment.GetEnvironmentVariable("BENCH_PROD") == "1";
if (!prodMode)
    Environment.SetEnvironmentVariable("COMSPEC_DEV_TRUST_LOOPBACK", "1"); // avant tout chargement du type

string dll = Environment.GetEnvironmentVariable("BENCH_DLL")
    ?? throw new Exception("BENCH_DLL manquant : chemin de la DLL managée à charger (voir README)");

var asm = Assembly.LoadFrom(dll);
var ext = asm.GetType("COMSPECExtension.Extension") ?? throw new Exception("type Extension introuvable");
const BindingFlags F = BindingFlags.NonPublic | BindingFlags.Public | BindingFlags.Static;
void SetF(string n, object? v) => (ext.GetField(n, F) ?? throw new Exception("champ " + n)).SetValue(null, v);
object? GetF(string n) => (ext.GetField(n, F) ?? throw new Exception("champ " + n)).GetValue(null);
MethodInfo M(string n, params Type[] sig) =>
    (sig.Length == 0 ? ext.GetMethod(n, F) : ext.GetMethod(n, F, null, sig, null))
    ?? throw new Exception("methode " + n);
object? Call(string n, Type[] sig, params object?[] a) => M(n, sig).Invoke(null, a);
bool Has(string n) => ext.GetMethods(F).Any(m => m.Name == n);

void ResetSecrets()
{
    SetF("_apiKey", KEY);
    SetF("_sessionToken", SESSION);
    SetF("_steamUid", STEAM);
    SetF("_gameAccessToken", "");          // force le chemin clé communauté dans AttachApiKeyHeader
    SetF("_baseUrl", DEAD);                // jamais l'adresse de production : rien ne doit sortir du banc
}

// Un secret factice OU un en-tête porteur de secret, quelle que soit sa valeur (un correctif qui laisserait
// partir un autre secret, ou le SteamID seul, ne passe plus).
bool Leaks(string raw) => Capture.Needles.Any(nd => raw.Contains(nd, StringComparison.Ordinal)) || Capture.HasSecretHeader(raw);

Capture.Needles = new[] { KEY, SESSION, STEAM, GAMETOK };

int failures = 0;
void Check(string label, bool ok, string detail = "")
{
    Console.WriteLine($"  [{(ok ? "PASS" : "FAIL")}] {label}{(detail.Length > 0 ? " — " + detail : "")}");
    if (!ok) failures++;
}

bool isFixed = Has("IsSecretSafeUri");
Console.WriteLine($"=== Banc SEC-001 (DLL = {(isFixed ? "CORRIGÉE" : "BASE 13ad62a9")}{(prodMode ? ", mode PRODUCTION" : "")}) ===");
Console.WriteLine($"    {dll}\n");

string Sync(string fn, params string?[] a)
{
    try { return (string?)Call("TryGetSyncResponse", new[] { typeof(string), typeof(string?[]) }, fn, a) ?? "null"; }
    catch (Exception ex) { return "EXC:" + (ex.InnerException?.Message ?? ex.Message); }
}

// En-têtes réellement posés sur une requête vers cette URL (aucun envoi : AttachApiKeyHeader seul).
string Attach(string url)
{
    ResetSecrets();
    var req = new HttpRequestMessage(HttpMethod.Get, url);
    Call("AttachApiKeyHeader", new[] { typeof(HttpRequestMessage) }, req);
    var sb = new StringBuilder();
    foreach (var h in req.Headers) sb.Append(h.Key).Append(": ").Append(string.Join(",", h.Value)).Append('\n');
    return sb.ToString();
}

// ----- Mode PRODUCTION : la boucle locale n'est PAS de confiance ---------------------------------------
if (prodMode)
{
    Console.WriteLine("[PROD] Sans COMSPEC_DEV_TRUST_LOOPBACK : la boucle locale ne doit rien recevoir");
    var loop = Attach("http://127.0.0.1:9/x");
    Check("127.0.0.1 → aucun secret (réglage de développement absent)", !Leaks(loop),
        Leaks(loop) ? "FUITE : " + loop.Replace("\n", " ") : "seul User-Agent");
    var local = new Capture("prod-local", "127.0.0.1");
    ResetSecrets();
    SetF("_baseUrl", "");
    var r = Sync("Connect", $"http://127.0.0.1:{local.Port}", KEY, "7");
    Thread.Sleep(400);
    Check("Connect vers la boucle locale refusé", r == "ERR|untrusted_host", r);
    Check("aucune requête vers la boucle locale", local.Count == 0, local.Count + " requête(s)");
    var athena = Attach("https://athena.ttrd.fr/api/atak/client-init");
    Check("athena.ttrd.fr → secrets présents (production inchangée)", Leaks(athena));
    Console.WriteLine($"\n=== BILAN : {(failures == 0 ? "aucun échec" : failures + " ÉCHEC(S)")} ===");
    Environment.Exit(failures == 0 ? 0 : 1);
}

// ----- A : filtre d'en-têtes d'AttachApiKeyHeader ------------------------------------------------------
Console.WriteLine("[A] AttachApiKeyHeader : en-têtes selon la destination");
var aEvil = Attach("http://127.0.0.2/x");                     // hôte NON fiable
var aAthena = Attach("https://athena.ttrd.fr/api/atak/client-init");
var aEu = Attach("https://athena-comspec.eu/api/atak/x");     // second portail officiel
var aSub = Attach("https://atak.athena-comspec.eu/api/atak/x"); // sous-domaine : simple redirection web, hors liste
var aLoop = Attach("http://127.0.0.1:8/x");                   // boucle locale (réglage de développement posé)
var aClear = Attach("http://athena.ttrd.fr/api/atak/x");      // bon hôte, http en clair
var aUser = Attach("https://attaquant@athena.ttrd.fr/api/atak/x"); // identifiants dans l'URL
Check("hôte non fiable (127.0.0.2) → aucun secret", !Leaks(aEvil),
    Leaks(aEvil) ? "FUITE : " + aEvil.Replace("\n", " ") : "seul User-Agent");
Check("athena.ttrd.fr (https) → secrets présents (pas de régression)", Leaks(aAthena));
Check("athena-comspec.eu (https) → secrets présents (portail du mod natif)", Leaks(aEu));
Check("127.0.0.1 → secrets présents (banc de développement)", Leaks(aLoop));
if (isFixed)
{
    Check("http:// en clair vers athena.ttrd.fr → aucun secret", !Leaks(aClear),
        Leaks(aClear) ? "FUITE : " + aClear.Replace("\n", " ") : "seul User-Agent");
    Check("URL avec identifiants (x@athena.ttrd.fr) → aucun secret", !Leaks(aUser),
        Leaks(aUser) ? "FUITE : " + aUser.Replace("\n", " ") : "seul User-Agent");
    Check("sous-domaine atak.athena-comspec.eu → aucun secret (hors liste)", !Leaks(aSub),
        Leaks(aSub) ? "FUITE" : "seul User-Agent");
}

// ----- B : Connect vers un hôte hostile (en-têtes ET corps) -------------------------------------------
Console.WriteLine("\n[B] Connect(url hostile) : _baseUrl, en-têtes et CORPS client-init");
var evil = new Capture("evil", "127.0.0.2");
ResetSecrets();
SetF("_baseUrl", "");                                        // repart de zéro
var connect = Sync("Connect", $"http://127.0.0.2:{evil.Port}", KEY, "7");
Thread.Sleep(400);                                           // laisse partir un éventuel envoi
var baseAfter = (string?)GetF("_baseUrl") ?? "";
Console.WriteLine($"      réponse Connect = {connect} | _baseUrl après = \"{baseAfter}\"");
Check("_baseUrl non pointé vers l'hôte hostile", !baseAfter.Contains("127.0.0.2"),
    baseAfter.Contains("127.0.0.2") ? "DÉTOURNÉ" : "refusé/ignoré");
Check("aucun secret (en-tête ni corps) reçu par l'hôte hostile", !evil.AnyLeak(),
    evil.AnyLeak() ? "FUITE dans " + evil.Count + " requête(s)" : evil.Count + " requête(s), aucun secret");
if (isFixed)
    Check("Connect renvoie ERR|untrusted_host", connect == "ERR|untrusted_host", connect);

// ----- C : balayage des commandes qui posent _baseUrl depuis SQF --------------------------------------
Console.WriteLine("\n[C] Toutes les commandes qui posent _baseUrl depuis SQF (URL hostile)");
var sweep = new Capture("sweep", "127.0.0.2");
string H = $"http://127.0.0.2:{sweep.Port}";
(string cmd, string?[] cargs)[] cmds =
{
    ("Connect",            new string?[] { H, KEY, "7" }),
    ("PairStart",          new string?[] { H, STEAM, "mod" }),
    ("RedeemRecoveryCode", new string?[] { H, "ABCDEF", STEAM }),
    ("RedeemGameLink",     new string?[] { H, "ABCD12", STEAM }),
    ("LinkBySteam",        new string?[] { H, STEAM }),
    ("RegisterBeta",       new string?[] { H, STEAM, STEAM, "Joueur" }),
    ("ReportDiag",         new string?[] { H, "error", "Core", "msg" }),
    ("Init",               new string?[] { H }),
    ("RestoreSession",     new string?[] { H, "mod", STEAM }),
    ("AuthPassword",       new string?[] { H, "a@b.c", "pw" }),
    ("RequestOtp",         new string?[] { H, "a@b.c" }),
    ("VerifyOtp",          new string?[] { H, "a@b.c", "123456" }),
    ("AuthSteam",          new string?[] { H, STEAM }),
};
foreach (var (cmd, cargs) in cmds)
{
    ResetSecrets();
    SetF("_baseUrl", "");
    var r = Sync(cmd, cargs);
    var b = (string?)GetF("_baseUrl") ?? "";
    Check($"{cmd,-18} _baseUrl non hostile", !b.Contains("127.0.0.2"),
        (b.Contains("127.0.0.2") ? "DÉTOURNÉ → " : "ok (") + r + (b.Contains("127.0.0.2") ? "" : ")"));
}
// Le Connect « async de secours » (RvExtensionArgsImpl) : second chemin qui pose _baseUrl.
ResetSecrets(); SetF("_baseUrl", "");
try { Call("RvExtensionArgsImpl", new[] { typeof(string), typeof(string?[]) }, "Connect", new string?[] { H, KEY, "7" }); }
catch { /* best effort */ }
var bAsync = (string?)GetF("_baseUrl") ?? "";
Check("Connect (chemin async RvExtensionArgsImpl) _baseUrl non hostile", !bAsync.Contains("127.0.0.2"));
Thread.Sleep(400);
Check("aucun secret reçu par l'hôte hostile sur tout le balayage", !sweep.AnyLeak(),
    sweep.Count + " requête(s)");

// ----- Q : file d'envoi reprise du disque (queue.jsonl / position.json) -------------------------------
Console.WriteLine("\n[Q] File d'envoi rechargée du disque avec une adresse non-Athena (fichier modifié)");
var qEvil = new Capture("queue-evil", "127.0.0.2");
var qAthena = new Capture("queue-athena", "127.0.0.1");
var dir = (string?)Call("SyncStoreDir", Type.EmptyTypes);
if (dir is null)
{
    Check("dossier de file accessible", false, "SyncStoreDir() a renvoyé null (HOME ?)");
}
else
{
    File.WriteAllText(Path.Combine(dir, "queue.jsonl"),
        "{\"u\":\"http://127.0.0.2:" + qEvil.Port + "/api/atak/reports\",\"b\":\"{\\\"note\\\":\\\"q\\\"}\"}\n");
    File.WriteAllText(Path.Combine(dir, "position.json"),
        "{\"u\":\"http://127.0.0.2:" + qEvil.Port + "/api/atak/position\",\"b\":\"{\\\"lat\\\":1}\"}");
    ResetSecrets();
    SetF("_baseUrl", $"http://127.0.0.1:{qAthena.Port}/public");   // liaison Athena (simulée) active
    SetF("_diskQueueLoaded", 0);
    Call("LoadQueueFromDisk", Type.EmptyTypes);
    for (var i = 0; i < 4; i++)
    {
        try { Call("DrainQueue", Type.EmptyTypes); } catch { /* best effort */ }
        Thread.Sleep(300);
    }
    Thread.Sleep(400);
    foreach (var r in qEvil.All())
        Console.WriteLine("      hostile a reçu : " + r.Replace("\r\n", " ").Substring(0, Math.Min(160, r.Length)));
    Check("file disque : aucun secret (corps compris) vers l'hôte hostile", !qEvil.AnyLeak(),
        qEvil.Count == 0 ? "hôte hostile non contacté" : qEvil.Count + " requête(s)"
            + (qEvil.AnyLeak() ? " AVEC SECRET DANS LE CORPS" : ", aucun secret"));
    // Positions et marqueurs ne sont pas suivis par le journal d'envoi : l'abandon doit quand même être compté.
    if (Has("NoteUntrustedQueueDrop"))
    {
        var dropped = (long)(GetF("_untrustedQueueDropped") ?? 0L);
        Check("file disque : abandon compté (signalé au journal de liaison)", dropped >= 1, dropped + " abandon(s)");
    }
    try { File.Delete(Path.Combine(dir, "queue.jsonl")); File.Delete(Path.Combine(dir, "position.json")); } catch { }
}

// ----- E : redirection hôte de confiance → hôte hostile ----------------------------------------------
Console.WriteLine("\n[E] Redirection 127.0.0.1 (confiance) → 127.0.0.2 (hostile)");
var dest = new Capture("redir-dest", "127.0.0.2");
var src = new Capture("redir-src", "127.0.0.1", status: 302, location: $"http://127.0.0.2:{dest.Port}/next");
var http = (HttpClient)(GetF("HttpClient") ?? throw new Exception("HttpClient"));
ResetSecrets();
try
{
    var req = new HttpRequestMessage(HttpMethod.Get, $"http://127.0.0.1:{src.Port}/start");
    Call("AttachApiKeyHeader", new[] { typeof(HttpRequestMessage) }, req);
    using var resp = http.Send(req);
}
catch { /* la cible peut fermer brutalement */ }
Thread.Sleep(300);
Check("l'hôte hostile ne reçoit aucun secret après redirection", !dest.AnyLeak(),
    dest.Count == 0 ? "non contacté (redirection non suivie)" :
        (dest.AnyLeak() ? "FUITE via en-têtes conservés" : dest.Count + " requête(s), aucun secret"));

// ----- E2 : images de briefing (redirections suivies à la main) ---------------------------------------
Console.WriteLine("\n[E2] DownloadBriefingSlideImage : image via redirection confiance → hostile");
var imgDest = new Capture("img-dest", "127.0.0.2", body: "x"); // pas une vraie image : on ne teste que le secret
var imgSrc = new Capture("img-src", "127.0.0.1", status: 302, location: $"http://127.0.0.2:{imgDest.Port}/evil.png");
ResetSecrets();
Sync("DownloadBriefingSlideImage", $"http://127.0.0.1:{imgSrc.Port}/a.png", "slide");
Thread.Sleep(300);
Check("image : aucun secret vers l'hôte hostile (redirection comprise)", !imgDest.AnyLeak(),
    imgDest.Count == 0 ? "non contacté"
        : (imgDest.AnyLeak() ? "FUITE via en-têtes conservés" : imgDest.Count + " requête(s), aucun secret"));
Check("image : la redirection vers l'hôte hostile est bien suivie (CDN externe)", imgDest.Count > 0,
    imgDest.Count + " requête(s) reçue(s) par la cible");

// Image légitime : GET direct vers l'hôte de confiance (doit aboutir — pas de régression de téléchargement).
var imgOk = new Capture("img-ok", "127.0.0.1", body: "\x89PNG----------"); // en-tête PNG factice
ResetSecrets();
var imgRes = Sync("DownloadBriefingSlideImage", $"http://127.0.0.1:{imgOk.Port}/ok.png", "slide");
Check("image : GET direct vers hôte de confiance aboutit (contacté)", imgOk.Count > 0,
    imgRes + " / " + imgOk.Count + " requête(s)");

// ----- R : tuiles du fond PHOTO (sans secret) : redirections suivies ----------------------------------
Console.WriteLine("\n[R] Tuiles /map-photo (client sans secret) : la redirection doit être suivie");
var tiles = new Capture("tiles", "127.0.0.1", body: "jpeg", redirectFrom: "/old/map-photo/a.jpg",
    redirectTo: "/map-photo/x.jpg");
ResetSecrets();
var mp = GetF("MapPhotoHttp");
var mpClient = (HttpClient)(mp?.GetType().GetProperty("Value")?.GetValue(mp) ?? throw new Exception("MapPhotoHttp"));
var tileCode = 0;
try
{
    using var resp = mpClient.GetAsync($"http://127.0.0.1:{tiles.Port}/old/map-photo/a.jpg").GetAwaiter().GetResult();
    tileCode = (int)resp.StatusCode;
}
catch (Exception ex) { Console.WriteLine("      exception : " + ex.Message); }
Check("tuile PHOTO : 301 suivie jusqu'au 200", tileCode == 200, "HTTP " + tileCode);
Check("tuile PHOTO : aucun secret posé sur ces requêtes", !tiles.AnyLeak(), tiles.Count + " requête(s)");

// ----- S : commandes sans secret, mais avec une adresse venue de SQF ----------------------------------
Console.WriteLine("\n[S] StartupCheck / LatestVersions / Server.PublicIp vers un hôte arbitraire");
var sEvil = new Capture("s-evil", "127.0.0.2");
var sUrl = $"http://127.0.0.2:{sEvil.Port}";
ResetSecrets(); SetF("_baseUrl", "");
Console.WriteLine("      StartupCheck    : " + Sync("StartupCheck", sUrl, "mission=x"));
Console.WriteLine("      LatestVersions  : " + Sync("LatestVersions", sUrl));
Console.WriteLine("      Server.PublicIp : " + Sync("Server.PublicIp", sUrl));
Thread.Sleep(300);
if (isFixed)
    Check("aucune requête vers l'hôte arbitraire", sEvil.Count == 0, sEvil.Count + " requête(s)");
Check("aucun secret vers l'hôte arbitraire", !sEvil.AnyLeak(), sEvil.Count + " requête(s)");

// ----- G : GamePostJson / GameGetJson posent eux-mêmes le Bearer du compte ---------------------------
// Défense en profondeur : _baseUrl est contrôlé partout, mais si une régression le reposait sans contrôle,
// ces deux sites (hors AttachApiKeyHeader) ne doivent toujours rien envoyer à un hôte non sûr.
Console.WriteLine("\n[G] Bearer du compte (GameAuth) avec un _baseUrl hostile forcé par réflexion");
var gEvil = new Capture("game-evil", "127.0.0.2");
ResetSecrets();
SetF("_gameAccessToken", GAMETOK);
SetF("_gameAccessExpiresAt", DateTimeOffset.UtcNow.AddHours(2));
SetF("_baseUrl", $"http://127.0.0.2:{gEvil.Port}/public");   // ce que ferait une future régression
string gGet, gPost;
try { gGet = (string?)M("GameGetJson", typeof(string)).Invoke(null, new object?[] { "/api/game/v1/session/me" }) ?? "null"; }
catch (Exception ex) { gGet = "EXC:" + (ex.InnerException?.Message ?? ex.Message); }
try { gPost = (string?)M("GamePostJson", typeof(string), typeof(string), typeof(bool)).Invoke(null, new object?[] { "/api/game/v1/session/refresh", "{}", true }) ?? "null"; }
catch (Exception ex) { gPost = "EXC:" + (ex.InnerException?.Message ?? ex.Message); }
Thread.Sleep(300);
Console.WriteLine("      GameGetJson  : " + gGet);
Console.WriteLine("      GamePostJson : " + gPost);
if (isFixed)
{
    Check("GameGetJson / GamePostJson refusent l'hôte non sûr", gGet == "ERR|untrusted_host" && gPost == "ERR|untrusted_host",
        gGet + " / " + gPost);
    Check("aucune requête GameAuth vers l'hôte hostile", gEvil.Count == 0, gEvil.Count + " requête(s)");
}
Check("aucun Bearer du compte vers l'hôte hostile", !gEvil.AnyLeak(), gEvil.Count + " requête(s)");
SetF("_gameAccessToken", "");
SetF("_baseUrl", DEAD);

// ----- P : analyse des candidats _baseUrl (URL piégées) ----------------------------------------------
if (isFixed)
{
    Console.WriteLine("\n[P] Candidats _baseUrl piégés : accepté ? → hôte réellement visé → de confiance ?");
    string[] cands =
    {
        "https://athena.ttrd.fr/public", "https://ATHENA.TTRD.FR/public", "https://athena.ttrd.fr./public",
        "athena.ttrd.fr/public", "https://athena-comspec.eu/public", "http://athena.ttrd.fr/public",
        "https://athena-comspec.eu.attaquant.tld/public", "https://attaquantathena-comspec.eu/public",
        "https://x.athena-comspec.eu/public", "https://x.athena.ttrd.fr/public", "https://127.0.0.1.nip.io/", "http://2130706433/",
        "http://0x7f000001/", "http://127.1/", "http://017700000001/", "http://127.0.0.2/",
        "http://[::1]:8080/", "http://[0:0:0:0:0:0:0:1]/", "http://[::ffff:127.0.0.1]/", "http://0.0.0.0/",
        "http://localhost./", "http://LocalHost/", "https://athena.ttrd.fr@evil.example/",
        "https://evil.example@athena.ttrd.fr/public", "https://evil.example\\@athena.ttrd.fr/",
        "https://athena.ttrd.fr\\@evil.example/", "https://evil.example#@athena.ttrd.fr/",
        "https://evil.example?@athena.ttrd.fr/", "https://athena.ttrd.fr%2f@evil.example/",
        "https://athena.ttrd.fr%40evil.example/", "https://athena.ttrd.fr:443@evil.example/",
        "https://аthena.ttrd.fr/public", "https://ａthena.ttrd.fr/public", "https://athena.ttrd.fr。evil.example/",
        "https://evil。athena-comspec.eu/", "https://athena.ttrd.fr\t@evil.example/",
        " \"https://evil.example/public\" ", "//evil.example/public", "https://athena.ttrd.fr:8443/public?x=#y",
        "ftp://athena.ttrd.fr/public", "file:///etc/passwd",
    };
    var trySet = M("TrySetSqfBaseUrl", typeof(string), typeof(string).MakeByRefType());
    var build = M("TryBuildRequestUri", typeof(string), typeof(string), typeof(Uri).MakeByRefType(), typeof(string).MakeByRefType());
    var safe = M("IsSecretSafeUri", typeof(Uri));
    var bad = 0;
    var accepted = 0;
    foreach (var c in cands)
    {
        SetF("_baseUrl", "");
        var ok = (bool)trySet.Invoke(null, new object?[] { c, null })!;
        string target = "-", verdict = "-";
        if (ok)
        {
            accepted++;
            var b = new object?[] { (string)GetF("_baseUrl")!, "/api/atak/client-init", null, null };
            var built = (bool)build.Invoke(null, b)!;
            var u = b[2] as Uri;
            target = built && u != null ? u.IdnHost + ":" + u.Port : "build-échec";
            var good = built && u != null && (bool)safe.Invoke(null, new object?[] { u })!;
            verdict = good ? "oui" : "NON";
            if (!good) bad++;
        }
        Console.WriteLine($"      {c.Replace("\t", "\\t"),-50} accepté={(ok ? "OUI" : "non"),-3} cible={target,-28} sûre={verdict}");
    }
    Check("aucun candidat accepté ne mène à une destination non sûre", bad == 0,
        accepted + " accepté(s), " + bad + " non sûr(s)");
}

Console.WriteLine($"\n=== BILAN : {(failures == 0 ? "aucun échec" : failures + " ÉCHEC(S)")} ===");
Environment.Exit(failures == 0 ? 0 : 1);

// --- Serveur de capture local : enregistre chaque requête reçue (en-têtes + corps). -------------------
sealed class Capture
{
    public static string[] Needles = Array.Empty<string>(); // secrets factices à repérer dans les requêtes
    readonly List<string> _requests = new();
    readonly object _sync = new();
    public string Name;
    public int Port;
    readonly TcpListener _l;
    readonly string _body;
    readonly int _status;
    readonly string? _location;
    readonly string? _redirectFrom;
    readonly string? _redirectTo;
    public Capture(string name, string host, string body = "{}", int status = 200, string? location = null,
        string? redirectFrom = null, string? redirectTo = null)
    {
        Name = name; _body = body; _status = status; _location = location;
        _redirectFrom = redirectFrom; _redirectTo = redirectTo;
        _l = new TcpListener(IPAddress.Parse(host), 0);
        _l.Start();
        Port = ((IPEndPoint)_l.LocalEndpoint).Port;
        _ = Task.Run(Loop);
    }
    async Task Loop()
    {
        while (true)
        {
            TcpClient c;
            try { c = await _l.AcceptTcpClientAsync(); } catch { return; }
            _ = Task.Run(async () =>
            {
                try
                {
                    using (c)
                    using (var ns = c.GetStream())
                    {
                        var buf = new byte[32768];
                        var n = await ns.ReadAsync(buf);
                        var head = Encoding.UTF8.GetString(buf, 0, n);
                        lock (_sync) _requests.Add(head);
                        var line = head.Split("\r\n")[0];
                        var path = line.Split(' ').Length > 1 ? line.Split(' ')[1] : "";
                        var status = _status;
                        var location = _location;
                        if (_redirectFrom != null && path == _redirectFrom)
                        {
                            status = 301;
                            location = _redirectTo;
                        }
                        var payload = Encoding.UTF8.GetBytes(_body);
                        var sb = new StringBuilder();
                        sb.Append("HTTP/1.1 ").Append(status)
                          .Append(status is 301 or 302 ? " Found\r\n" : " OK\r\n");
                        if (location != null) sb.Append("Location: ").Append(location).Append("\r\n");
                        sb.Append("Content-Type: application/json\r\nContent-Length: ").Append(payload.Length)
                          .Append("\r\nConnection: close\r\n\r\n");
                        await ns.WriteAsync(Encoding.ASCII.GetBytes(sb.ToString()));
                        await ns.WriteAsync(payload);
                        await ns.FlushAsync();
                    }
                }
                catch { /* best effort */ }
            });
        }
    }
    public List<string> All() { lock (_sync) return _requests.ToList(); }
    public bool AnyLeak()
    {
        lock (_sync) return _requests.Any(r => Needles.Any(nd => r.Contains(nd, StringComparison.Ordinal)) || HasSecretHeader(r));
    }
    static readonly string[] SecretHeaders =
        { "authorization:", "x-comspec-key:", "x-comspec-session:", "x-comspec-steam:", "x-atak-token:" };
    public static bool HasSecretHeader(string raw) =>
        raw.Split('\n').Any(l => SecretHeaders.Any(h => l.TrimStart().StartsWith(h, StringComparison.OrdinalIgnoreCase)));
    public int Count { get { lock (_sync) return _requests.Count; } }
}
