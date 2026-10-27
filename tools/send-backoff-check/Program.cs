// Contrôle de la pause d'envoi commune de la DLL COMSPEC Link (NET-006, TEST-010) — audit COMSPEC ATAK.
//
// Charge la DLL MANAGÉE (dotnet build, pas la .so AOT) par réflexion et rejoue les suites d'échecs et de succès
// que la liaison rencontre : panne (réseau, délai dépassé), maintenance 503 avec Retry-After, frein 429, 403 du site
// entier. Aucune requête HTTP n'est émise : seules les méthodes internes de comptage sont appelées.
//   BACKOFF_DLL=/chemin/COMSPECExtension_x64.dll   (obligatoire)
// Sortie : lignes PASS/FAIL ; code de sortie 0 si tout passe, 1 sinon. Sur la DLL de main, A1 A2 A3 B2 B3 E1 E2 G H2
// H3 H4 I P1 P3 R1 R2 échouent (16 : c'est le défaut corrigé) ; sur la première version du lot (8faa74ab), H2 H3 H4
// échouent (passage panne → frein). U B1 C D F H T P2 P4 R3 R4 passent partout (le frein 429 / 403 seul et les refus
// doivent rester exactement comme avant).

using System.Net;
using System.Net.Http.Headers;
using System.Reflection;

string dll = Environment.GetEnvironmentVariable("BACKOFF_DLL")
    ?? throw new Exception("BACKOFF_DLL manquant : chemin de la DLL managée à charger");
var asm = Assembly.LoadFrom(dll);
var ext = asm.GetType("COMSPECExtension.Extension") ?? throw new Exception("type Extension introuvable");
const BindingFlags F = BindingFlags.NonPublic | BindingFlags.Public | BindingFlags.Static;
const BindingFlags I = BindingFlags.NonPublic | BindingFlags.Public | BindingFlags.Instance;
var failures = 0;

FieldInfo Fld(string n) => ext.GetField(n, F) ?? throw new MissingFieldException(n);
object? GetF(string n) => Fld(n).GetValue(null);
void SetF(string n, object? v) => Fld(n).SetValue(null, v);
bool HasF(string n) => ext.GetField(n, F) != null;
MethodInfo M(string n) => ext.GetMethod(n, F) ?? throw new MissingMethodException(n);
void Check(string name, bool ok, string detail)
{
    Console.WriteLine($"{(ok ? "PASS" : "FAIL")} {name} : {detail}");
    if (!ok) failures++;
}

// Rappels vers le SQF captés (InvokeCallback appelle _callback dans une tâche, nul hors du jeu).
var callbacks = new List<(string Fn, string Data)>();
var cbField = Fld("_callback");
Hook.Sink = (fn, data) => { lock (callbacks) callbacks.Add((fn, data)); };
cbField.SetValue(null, Delegate.CreateDelegate(cbField.FieldType, typeof(Hook).GetMethod(nameof(Hook.On))!));

void Reset()
{
    SetF("_sendBackoffStep", 0);
    SetF("_sendFailStreak", 0);
    SetF("_sendOkStreak", 0);
    if (HasF("_sendBackoffThrottled")) SetF("_sendBackoffThrottled", false);
    SetF("_rateLimitUntilTicks", 0L);
    SetF("_networkBackoffUntilTicks", 0L);
    SetF("_lastBackoffEscalateTicks", 0L);
    SetF("_lastSendBackoffCbTicks", 0L);
    SetF("_lastNotifiedBackoffSec", -1);
    SetF("_networkBackoffSec", 1);
    SetF("_lastRateLimitCbTicks", 0L);
    SetF("_lastAccessDeniedCbTicks", 0L);
    SetF("_lastNetworkHiccupCbTicks", 0L);
    Thread.Sleep(60);
    lock (callbacks) callbacks.Clear();
}

int Step() => (int)GetF("_sendBackoffStep")!;
int Rung() => (int)M("CurrentSendBackoffSec").Invoke(null, null)!;
double PauseS()
{
    var until = Math.Max((long)GetF("_rateLimitUntilTicks")!, (long)GetF("_networkBackoffUntilTicks")!);
    return until <= 0 ? 0 : (until - DateTime.UtcNow.Ticks) / (double)TimeSpan.TicksPerSecond;
}
void Fail(int code) => M("NoteSendFailure").Invoke(null, new object?[] { code, "", null });
void Ok() => M("NoteSendSuccess").Invoke(null, null);
void Limited(HttpResponseMessage r) => M("NoteRateLimited").Invoke(null, new object?[] { r });
void PretendWindowElapsed() => SetF("_lastBackoffEscalateTicks", DateTime.UtcNow.AddMinutes(-15).Ticks);
HttpResponseMessage Resp(int code, int retryAfter, string body)
{
    var r = new HttpResponseMessage((HttpStatusCode)code) { Content = new StringContent(body) };
    if (retryAfter > 0) r.Headers.RetryAfter = new RetryConditionHeaderValue(TimeSpan.FromSeconds(retryAfter));
    return r;
}
List<(string Fn, string Data)> Cbs()
{
    Thread.Sleep(200);
    lock (callbacks) return callbacks.ToList();
}
string Fmt(double s) => s.ToString("0.0", System.Globalization.CultureInfo.InvariantCulture);
const string MaintBody = "{\"ok\":false,\"error\":\"maintenance\",\"retry_after\":120}";

Console.WriteLine("DLL : " + dll);

// [U] Avant l'échelle (1 et 2 échecs réseau) : petites pauses de 1 puis 2 s, comme avant.
Reset();
Fail(0);
var u1 = PauseS();
Fail(0);
var u2 = PauseS();
Check("U  pauses avant l'échelle", Step() == 0 && u1 is > 0.9 and < 1.3 && u2 is > 1.9 and < 2.5,
    $"palier {Step()}, pauses {Fmt(u1)} s puis {Fmt(u2)} s (attendu 0, ~1 s puis ~2 s)");

// [A] Panne : entrée au 3e échec sur un palier court, sortie au premier envoi réussi, le SQF est prévenu.
Fail(0);
var aStep = Step();
var aPause = PauseS();
Check("A1 panne : entrée au 3e échec sur 10 s (+ gigue 0-5 s)", aStep == 1 && aPause is >= 9.9 and <= 15.1,
    $"palier {aStep}, pause {Fmt(aPause)} s");
Thread.Sleep(250);
lock (callbacks) callbacks.Clear();
Ok();
var aCbs = Cbs();
Check("A2 panne : sortie au premier succès", Step() == 0 && PauseS() <= 0,
    $"après 1 succès : palier {Step()}, pause {Fmt(Math.Max(0, PauseS()))} s (attendu 0 et 0)");
Check("A3 panne : le SQF reprend (RateLimitClear)", aCbs.Any(c => c.Fn == "RateLimitClear"),
    "rappels : " + string.Join(", ", aCbs.Select(c => c.Fn + "(" + c.Data + ")")));

// [B] Maintenance 503 + Retry-After 120 : délai respecté (gigue 15 s au plus), puis sortie au premier succès.
Reset();
for (var i = 0; i < 3; i++) Limited(Resp(503, 120, MaintBody));
var bPause = PauseS();
var bCbs = Cbs();
Check("B1 maintenance : Retry-After 120 respecté", Step() == 1 && bPause is >= 119.9 and <= 135.1,
    $"palier {Step()}, pause {Fmt(bPause)} s, rappels {string.Join(", ", bCbs.Select(c => c.Fn + "(" + c.Data + ")"))}");
Ok();
Check("B2 maintenance : sortie au premier succès après la fin", Step() == 0 && PauseS() <= 0,
    $"après 1 succès : palier {Step()}, pause {Fmt(Math.Max(0, PauseS()))} s");
// Un seul tirage ne voit une gigue trop large qu'une fois sur huit : 100 tirages.
var bSamples = new List<double>();
for (var n = 0; n < 100; n++)
{
    Reset();
    for (var i = 0; i < 3; i++) Limited(Resp(503, 120, MaintBody));
    bSamples.Add(PauseS());
}
Check("B3 maintenance : gigue du Retry-After bornée à 15 s", bSamples.Min() >= 119.9 && bSamples.Max() <= 135.1
    && bSamples.Max() - bSamples.Min() >= 2,
    $"100 tirages : de {Fmt(bSamples.Min())} à {Fmt(bSamples.Max())} s (attendu 120 à 135 s, écart ≥ 2 s)");

// [C] Frein 429 + Retry-After 60 : protection inchangée (60 s exactement, deux succès pour sortir).
Reset();
const string TooMany = "{\"error\":\"too_many_requests\",\"retry_after\":60}";
for (var i = 0; i < 3; i++) Limited(Resp(429, 60, TooMany));
var cPause = PauseS();
Ok();
var cStep1 = Step();
var cPause1 = PauseS();
Ok();
Check("C  429 : 60 s exactement, puis un palier de moins tous les deux succès",
    cPause is >= 59.9 and <= 60.1 && cStep1 == 1 && cPause1 is >= 44.9 and <= 45.1 && Step() == 0,
    $"pause {Fmt(cPause)} s ; après 1 succès palier {cStep1} pause {Fmt(cPause1)} s ; après 2 succès palier {Step()}");

// [D] 403 du site entier : échelle longue, deux succès pour sortir.
Reset();
for (var i = 0; i < 3; i++) Fail(403);
var dPause = PauseS();
Ok();
var dStep1 = Step();
Ok();
Check("D  403 : échelle longue (45 s exactement), deux succès pour sortir", dPause is >= 44.9 and <= 45.1 && dStep1 == 1 && Step() == 0,
    $"pause {Fmt(dPause)} s ; palier après 1 succès {dStep1}, après 2 succès {Step()}");

// [E] Panne longue : paliers 10, 20, 30, 45 s, jamais plus de 60 s de pause avec la gigue.
Reset();
for (var i = 0; i < 3; i++) Fail(0);
var rungs = new List<int> { Rung() };
var maxPause = PauseS();
for (var i = 0; i < 5; i++)
{
    PretendWindowElapsed();
    Fail(0);
    rungs.Add(Rung());
    maxPause = Math.Max(maxPause, PauseS());
}
Check("E1 panne longue : paliers courts plafonnés", string.Join(",", rungs) == "10,20,30,45,45,45" && maxPause <= 60.1,
    $"paliers {string.Join(", ", rungs)} s, pause la plus longue {Fmt(maxPause)} s");
Ok();
Check("E2 panne longue : sortie au premier succès, même au dernier palier", Step() == 0 && PauseS() <= 0,
    $"après 1 succès : palier {Step()}");

// [F] Frein 429 répété : échelle longue inchangée (45, 75, 150, 300, 600 s).
Reset();
for (var i = 0; i < 3; i++) Fail(429);
var tr = new List<int> { Rung() };
for (var i = 0; i < 5; i++)
{
    PretendWindowElapsed();
    Fail(429);
    tr.Add(Rung());
}
Check("F  429 répété : échelle longue inchangée", string.Join(",", tr) == "45,75,150,300,600,600",
    $"paliers {string.Join(", ", tr)} s");

// [H] Panne puis 429 dans le même épisode : le frein l'emporte jusqu'à la sortie.
Reset();
for (var i = 0; i < 3; i++) Fail(0);
Limited(Resp(429, 30, TooMany));
var hPause = PauseS();
Ok();
var hStep1 = Step();
Ok();
Check("H  panne puis 429 : échelle longue, deux succès pour sortir", hPause >= 44.9 && hStep1 == 1 && Step() == 0,
    $"pause {Fmt(hPause)} s ; palier après 1 succès {hStep1}, après 2 succès {Step()}");

// [H2] [H3] [H4] Panne déjà montée sur l'échelle courte, puis 429 ou 403 : le frein repart du premier palier de
// l'échelle longue (45 s exactement, ou le Retry-After s'il est plus long), comme un frein qui commence, puis deux
// succès pour sortir. Avant ce correctif, le palier de la panne était relu sur l'échelle longue : palier 3 = 150 s,
// palier 4 = 300 s, et 600 s si la fenêtre d'escalade était passée (B1 de la contre-expertise v1). Le 429 arrive ici
// après la fenêtre d'escalade (cas réel : la pause de panne dure plus que la fenêtre) ; un second 429 juste après ne
// doit pas monter d'un palier (l'heure d'escalade est notée au passage panne → frein). H2 essaie aussi une panne restée
// au palier 1 (10 s) : un passage réservé aux paliers 2 et plus y donnait 75 s, sans échec avant (R1 de la v2).
(int Rung, int St, double P) OutageThen(int outageRungs, Action brake)
{
    Reset();
    for (var i = 0; i < 3; i++) Fail(0);
    for (var i = 1; i < outageRungs; i++)
    {
        PretendWindowElapsed();
        Fail(0);
    }
    var rung = Rung();
    PretendWindowElapsed();
    brake();
    return (rung, Step(), PauseS());
}
string Seen((int Rung, int St, double P) s) => $"panne à {s.Rung} s → palier {s.St}, pause {Fmt(s.P)} s";
bool Exactly45(int st, double p) => st == 1 && p is >= 44.9 and <= 45.1;

var h2Low = new[] { 1, 2, 3 }.Select(r => OutageThen(r, () => Limited(Resp(429, 30, TooMany)))).ToList();
var h2 = OutageThen(4, () => Limited(Resp(429, 30, TooMany)));
Limited(Resp(429, 30, TooMany));
var (h2AgainSt, h2AgainP) = (Step(), PauseS());
Ok();
var (h2Ok1St, h2Ok1P) = (Step(), PauseS());
Ok();
var h2Out = Step();
Check("H2 panne au palier 1, 2, 3 ou 4 puis 429 : 1er palier de l'échelle longue (45 s exactement), deux succès pour sortir",
    h2.Rung == 45 && h2Low.All(s => Exactly45(s.St, s.P)) && Exactly45(h2.St, h2.P) && Exactly45(h2AgainSt, h2AgainP)
    && Exactly45(h2Ok1St, h2Ok1P) && h2Out == 0,
    string.Join(" ; ", h2Low.Append(h2).Select(Seen)) + $" ; 2e 429 aussitôt : palier {h2AgainSt}, pause {Fmt(h2AgainP)} s ;"
    + $" après 1 succès palier {h2Ok1St}, pause {Fmt(h2Ok1P)} s ; après 2 succès palier {h2Out}"
    + " (attendu : panne à 45 s, puis palier 1 et 45 s partout, sortie au 2e succès)");

var h3 = OutageThen(4, () => Fail(403));
Ok();
var (h3Ok1St, h3Ok1P) = (Step(), PauseS());
Ok();
var h3Out = Step();
Check("H3 panne montée au palier 4 puis 403 : 1er palier de l'échelle longue (45 s exactement), deux succès pour sortir",
    h3.Rung == 45 && Exactly45(h3.St, h3.P) && Exactly45(h3Ok1St, h3Ok1P) && h3Out == 0,
    Seen(h3) + $" ; après 1 succès palier {h3Ok1St}, pause {Fmt(h3Ok1P)} s ; après 2 succès palier {h3Out}"
    + " (attendu : panne à 45 s, palier 1, 45 s, sortie au 2e succès)");

var h4 = OutageThen(4, () => Limited(Resp(429, 60, TooMany)));
Check("H4 panne montée au palier 4 puis 429 + Retry-After 60 : le Retry-After, plus long que 45 s, est respecté exactement",
    h4.Rung == 45 && h4.St == 1 && h4.P is >= 59.9 and <= 60.1,
    Seen(h4) + " (attendu : panne à 45 s, puis palier 1, 60 s)");

// [T] Frein (429, 403) : délais exacts, sans gigue, comme avant (Athena limite chaque compte ou chaque adresse à part :
// les joueurs ne sont pas freinés ensemble). 30 tirages par cas : une gigue de 20 % se verrait à coup sûr.
var tPre429 = new List<double>();
var tPre403 = new List<double>();
var tLadder = new List<double>();
for (var n = 0; n < 30; n++)
{
    Reset();
    Limited(Resp(429, 20, TooMany));
    tPre429.Add(PauseS());
    Reset();
    Fail(403);
    tPre403.Add(PauseS());
    Reset();
    for (var i = 0; i < 3; i++) Limited(Resp(429, 60, TooMany));
    tLadder.Add(PauseS());
}
bool Exact(List<double> l, double v) => l.Min() >= v - 0.1 && l.Max() <= v + 0.1;
Check("T  frein sans gigue", Exact(tPre429, 20) && Exact(tPre403, 20) && Exact(tLadder, 60),
    $"30 tirages : 429 isolé {Fmt(tPre429.Min())} à {Fmt(tPre429.Max())} s, 403 isolé {Fmt(tPre403.Min())} à "
    + $"{Fmt(tPre403.Max())} s, 429 au 3e échec {Fmt(tLadder.Min())} à {Fmt(tLadder.Max())} s (attendu 20, 20 et 60 s)");

// [I] Un frein terminé est oublié : une panne qui suit, sans succès entre les deux, repart sur l'échelle courte.
Reset();
for (var i = 0; i < 3; i++) Limited(Resp(429, 30, TooMany));
Ok();
Ok();
var iOut = Step() == 0;
for (var i = 0; i < 3; i++) Fail(0);
Check("I  après un frein, une panne repart sur l'échelle courte", iOut && Rung() == 10,
    $"sorti du frein : {iOut}, palier de la panne suivante {Rung()} s");

// [G] Gigue : 200 joueurs coupés en même temps ne reprennent plus à la même seconde.
var samples = new List<double>();
for (var n = 0; n < 200; n++)
{
    Reset();
    for (var i = 0; i < 3; i++) Fail(0);
    samples.Add(PauseS());
}
var spread = samples.Max() - samples.Min();
Check("G  gigue de la pause de panne", samples.Min() >= 9.9 && samples.Max() <= 15.1 && spread >= 3,
    $"200 tirages : de {Fmt(samples.Min())} à {Fmt(samples.Max())} s (écart {Fmt(spread)} s, attendu ≥ 3 s)");

// [P] Canaux sondés : une attente due à la panne est levée à la sortie de pause et reçoit une gigue ; un refus 403 est
// gardé, sans gigue, comme avant.
var slotType = ext.GetNestedType("PollGetSlot", BindingFlags.NonPublic) ?? throw new Exception("PollGetSlot introuvable");
var cache = GetF("PollGetCache")!;
var tryAdd = cache.GetType().GetMethod("TryAdd")!;
var tryRemove = cache.GetType().GetMethods().First(m => m.Name == "TryRemove" && m.GetParameters().Length == 2);
object NewSlot(string key)
{
    var s = Activator.CreateInstance(slotType, nonPublic: true)!;
    tryAdd.Invoke(cache, new[] { key, s });
    return s;
}
long Until(object s) => (long)slotType.GetField("RefusedUntilMs", I)!.GetValue(s)!;
var notePoll = M("NotePollResult");
var slotNet = NewSlot("bench:panne");
var slotDenied = NewSlot("bench:refus");
notePoll.Invoke(null, new[] { slotNet, (object)(-1) });
notePoll.Invoke(null, new[] { slotDenied, (object)403 });
Reset();
for (var i = 0; i < 3; i++) Fail(0);
Ok();
Check("P1 sondage en attente de panne : relancé à la sortie de pause", Until(slotNet) == 0,
    $"attente restante {(Until(slotNet) == 0 ? 0 : Math.Max(0, Until(slotNet) - Environment.TickCount64)) / 1000} s (attendu 0)");
Check("P2 sondage refusé (403) : attente gardée", Until(slotDenied) > Environment.TickCount64,
    $"attente restante {Math.Max(0, Until(slotDenied) - Environment.TickCount64) / 1000} s");
var waits = new List<long>();
for (var n = 0; n < 100; n++)
{
    slotType.GetField("RefusedUntilMs", I)!.SetValue(slotNet, 0L);
    var before = Environment.TickCount64;
    notePoll.Invoke(null, new[] { slotNet, (object)(-1) });
    waits.Add(Until(slotNet) - before);
}
Check("P3 gigue de l'attente des sondages en panne", waits.Min() >= 14_990 && waits.Max() <= 18_100 && waits.Max() - waits.Min() >= 1_000,
    $"100 tirages au 1er palier : de {waits.Min()} à {waits.Max()} ms");
var deniedWaits = new List<long>();
for (var n = 0; n < 30; n++)
{
    slotType.GetField("RefusedUntilMs", I)!.SetValue(slotDenied, 0L);
    var before = Environment.TickCount64;
    notePoll.Invoke(null, new[] { slotDenied, (object)403 });
    deniedWaits.Add(Until(slotDenied) - before);
}
Check("P4 sondage refusé (403) : attente exacte, sans gigue", deniedWaits.Min() >= 14_990 && deniedWaits.Max() <= 15_100,
    $"30 tirages au 1er palier : de {deniedWaits.Min()} à {deniedWaits.Max()} ms (attendu 15 000 ms)");

// [R] Un canal sondé qui revient d'une panne lève la pause d'envoi due à la panne, pas un frein ni sans preuve.
string PauseFmt() => $"palier {Step()}, pause {Fmt(Math.Max(0, PauseS()))} s";
Reset();
for (var i = 0; i < 3; i++) Fail(0);
notePoll.Invoke(null, new[] { slotNet, (object)(-1) });
notePoll.Invoke(null, new[] { slotNet, (object)200 });
var r1Cbs = Cbs();
Check("R1 panne : un sondage qui revient lève la pause d'envoi", Step() == 0 && PauseS() <= 0
    && r1Cbs.Any(c => c.Fn == "RateLimitClear"), PauseFmt() + (r1Cbs.Any(c => c.Fn == "RateLimitClear") ? ", RateLimitClear" : ""));
Reset();
for (var i = 0; i < 3; i++) Limited(Resp(503, 900, MaintBody));
var r2Before = PauseS();
notePoll.Invoke(null, new[] { slotNet, (object)503 });
notePoll.Invoke(null, new[] { slotNet, (object)200 });
Check("R2 maintenance sans fin annoncée (Retry-After 900, plafonné à 600 s) : levée au retour d'un sondage", r2Before >= 599 && Step() == 0 && PauseS() <= 0,
    $"pause avant {Fmt(r2Before)} s, après : " + PauseFmt());
Reset();
for (var i = 0; i < 3; i++) Limited(Resp(429, 60, "{\"error\":\"too_many_requests\"}"));
notePoll.Invoke(null, new[] { slotNet, (object)(-1) });
notePoll.Invoke(null, new[] { slotNet, (object)200 });
Check("R3 frein 429 : un sondage qui revient ne lève rien", Step() == 1 && PauseS() >= 59, PauseFmt());
Reset();
for (var i = 0; i < 3; i++) Fail(0);
var slotFresh = NewSlot("bench:sain");
notePoll.Invoke(null, new[] { slotFresh, (object)200 });
notePoll.Invoke(null, new[] { slotDenied, (object)403 });
notePoll.Invoke(null, new[] { slotDenied, (object)200 });
Check("R4 panne : un sondage qui n'a pas vu la panne (sain, ou refus 403) ne lève rien", Step() == 1 && PauseS() >= 9.9, PauseFmt());
foreach (var k in new[] { "bench:panne", "bench:refus", "bench:sain" })
    tryRemove.Invoke(cache, new object?[] { k, null });

Reset();
Console.WriteLine($"\n=== BILAN : {(failures == 0 ? "aucun échec" : failures + " ÉCHEC(S)")} ===");
Environment.Exit(failures == 0 ? 0 : 1);

// Rappel de la DLL vers le SQF, capté ici (même signature qu'ExtensionCallback).
static class Hook
{
    public static Action<string, string> Sink = (_, _) => { };
    public static int On(nint name, nint function, nint data)
    {
        Sink(System.Runtime.InteropServices.Marshal.PtrToStringUTF8(function) ?? "",
            System.Runtime.InteropServices.Marshal.PtrToStringUTF8(data) ?? "");
        return 0;
    }
}
