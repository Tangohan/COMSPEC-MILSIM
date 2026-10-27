// Contrôle de la pause d'envoi commune de la DLL COMSPEC Link (NET-006, TEST-010) — audit COMSPEC ATAK.
//
// Charge la DLL MANAGÉE (dotnet build, pas la .so AOT) par réflexion et rejoue les suites d'échecs et de succès
// que la liaison rencontre : panne (réseau, délai dépassé), maintenance 503 avec Retry-After, frein 429, 403 du site
// entier, refus 403 d'une adresse, erreurs 5xx d'une route ou d'Athena entier. Aucune requête HTTP n'est émise : seules
// les méthodes internes de comptage sont appelées.
//   BACKOFF_DLL=/chemin/COMSPECExtension_x64.dll   (obligatoire)
// Sortie : lignes PASS/FAIL ; code de sortie 0 si tout passe, 1 sinon. Sur la DLL de main (2.0.98), A1 A2 A3 B2 B3 E1
// E2 G H2 H3 H4 I P1 P3 R1 R2 échouent (défaut de NET-006 corrigé par le lot), L1 L2 L3 (NET-020, NET-022) et S4 S5 S6
// S7 S10 (NET-023, NET-015) : 24 en tout ; sur la première version du lot (8faa74ab), H2 H3 H4 échouent (passage panne
// → frein) ; sur le lot avant son point NET-022 (c3d8a85c), L1 L2 L3 et S4 S5 S6 S7 S10 ; avant son point NET-023
// (c81a2db6), S4 S5 S6 S7 S10. U B1 C D F H T P2 P4 R3 R4 L4 S1 S2 S3 S8 S9 S11 passent partout (le frein 429 / 403
// seul, les autres refus et l'erreur d'une seule route doivent rester exactement comme avant).

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

// [L] Refus 403 d'une adresse (NET-020, NET-022), rejoués par HandlePostResponse (aucune requête : l'adresse de base de
// la liaison est vide, le vidage de la file s'arrête donc tout de suite ; la file n'est ni lue ni écrite sur disque).
// Un refus prévu par conception (fonction coupée par la communauté : feature_disabled ; fonction hors offre :
// feature_unavailable) fait taire l'adresse et abandonne l'envoi, sans pause commune ni rappel AccessDenied (pause
// du SQF) ; une fonction coupée se lit encore (sondage GET non verrouillé). Un autre 403 (site entier, pare-feu) garde
// le comportement d'avant : pause de 20 s, rappel AccessDenied, envoi remis en file.
SetF("_diskQueueLoaded", 1);
SetF("_lastPersistTick", long.MaxValue / 4);
var pending = GetF("PendingPosts")!;
int Pending() => (int)pending.GetType().GetProperty("Count")!.GetValue(pending)!;
void Post403(string url, string body) =>
    M("HandlePostResponse").Invoke(null, new object?[] { Resp(403, 0, body), url, "{\"mapId\":1,\"body\":\"essai\"}" });
string Latched(string method, string url)
{
    var mi = ext.GetMethod(method, F);
    return mi == null ? "absent" : ((bool)mi.Invoke(null, new object?[] { url })! ? "oui" : "non");
}
void ClearLatches() => M("ClearRefusalLatches").Invoke(null, null);
string Seen403(List<(string Fn, string Data)> cbs, int before) =>
    $"pause {Fmt(Math.Max(0, PauseS()))} s, rappels [{string.Join(", ", cbs.Select(c => c.Fn + "(" + c.Data + ")"))}], "
    + $"remis en file {Pending() - before}";
const string Base = "http://127.0.0.1:9";
const string Disabled = "{\"error\":\"feature_disabled\",\"feature\":\"chat_enabled\",\"message\":\"Votre communauté a désactivé la messagerie depuis le jeu.\"}";
const string Unavailable = "{\"error\":\"feature_unavailable\",\"message\":\"Fonction réservée aux formules Standard et plus.\"}";

Reset();
ClearLatches();
var l1Before = Pending();
Post403(Base + "/api/chat", Disabled);
var l1Cbs = Cbs();
var l1Send = Latched("IsRefusalLatched", Base + "/api/chat");
var l1Poll = Latched("IsPollRefusalLatched", Base + "/api/chat?mapId=1&limit=25");
Check("L1 403 feature_disabled (tchat coupé) : envois de l'adresse verrouillés, lecture libre, ni pause ni rappel, envoi abandonné",
    l1Send == "oui" && l1Poll == "non" && PauseS() <= 0 && !l1Cbs.Any(c => c.Fn == "AccessDenied") && Pending() == l1Before,
    $"envoi verrouillé {l1Send}, sondage verrouillé {l1Poll} ; " + Seen403(l1Cbs, l1Before));

ClearLatches();
Check("L2 session suivante : le verrou des envois de feature_disabled est levé",
    l1Send == "oui" && Latched("IsRefusalLatched", Base + "/api/chat") == "non",
    $"verrouillé avant {l1Send}, après ClearRefusalLatches {Latched("IsRefusalLatched", Base + "/api/chat")}");

Reset();
var l3Before = Pending();
Post403(Base + "/api/atak/designator", Unavailable);
Post403(Base + "/api/atak/weather", Unavailable);
var l3Cbs = Cbs();
var l3Fifo = Latched("IsRefusalLatched", Base + "/api/atak/designator");
var l3Weather = Latched("IsRefusalLatched", Base + "/api/atak/weather");
Check("L3 403 feature_unavailable (hors offre), file d'envoi et météo : adresses verrouillées, ni pause ni rappel",
    l3Fifo == "oui" && l3Weather == "oui" && PauseS() <= 0 && !l3Cbs.Any(c => c.Fn == "AccessDenied") && Pending() == l3Before,
    $"désignation verrouillée {l3Fifo}, météo verrouillée {l3Weather} ; " + Seen403(l3Cbs, l3Before));

Reset();
ClearLatches();
var l4Before = Pending();
Post403(Base + "/api/chat", "{\"error\":\"forbidden\",\"message\":\"Action non autorisée.\"}");
var l4Pause = PauseS();   // lue tout de suite : Cbs() attend 200 ms
var l4Cbs = Cbs();
Check("L4 autre 403 (site entier) : comme avant, pause de 20 s, rappel AccessDenied, envoi remis en file",
    Latched("IsRefusalLatched", Base + "/api/chat") == "non" && l4Pause is >= 19.5 and <= 20.1
    && l4Cbs.Any(c => c.Fn == "AccessDenied" && c.Data == "20") && Pending() == l4Before + 1,
    $"verrouillé {Latched("IsRefusalLatched", Base + "/api/chat")}, pause juste après le refus {Fmt(l4Pause)} s ; " + Seen403(l4Cbs, l4Before));
ClearLatches();

// [S] Erreurs 5xx hors maintenance (NET-023, NET-015), rejouées par TryNoteEndpointServerError (aucune requête). Une
// erreur propre à une route garde la pause de son adresse (30 s au 1er palier) avec 0 à 20 % de gigue, sans monter
// au-delà du 1er palier pour les adresses de position (S12) ; une panne
// d'Athena entier (5xx sur au moins deux adresses, sondages compris, et aucune réponse acceptée depuis 10 s) pose la
// pause commune des envois, avec sortie au premier succès, et lève les pauses par adresse de cette panne.
var cool = (System.Collections.IDictionary)GetF("EndpointCooldowns")!;
var keyOf = M("EndpointKey");
const string Html502 = "<html><head><title>502 Bad Gateway</title></head><body><center><h1>502 Bad Gateway</h1></center><hr><center>nginx</center></body></html>";
const string Db503 = "{\"ok\":false,\"message\":\"La base de données est momentanément injoignable. L’équipe technique a été prévenue — réessayez dans quelques minutes.\"}";
var batchUrl = Base + "/api/atak/telemetry/batch";
var positionUrl = Base + "/api/atak/position";
var chatUrl = Base + "/api/chat";
void ResetServerErrors(double lastOkAgoSec)
{
    cool.Clear();
    if (HasF("RecentServerErrorTicks")) ((System.Collections.IDictionary)GetF("RecentServerErrorTicks")!).Clear();
    if (HasF("_lastAthenaOkTicks")) SetF("_lastAthenaOkTicks", DateTime.UtcNow.AddSeconds(-lastOkAgoSec).Ticks);
}
bool ServerError(string url, int code, int ra, string body) =>
    (bool)M("TryNoteEndpointServerError").Invoke(null, new object?[] { url, Resp(code, ra, body) })!;
double CoolS(string url)
{
    var key = (string)keyOf.Invoke(null, new object?[] { url })!;
    if (!cool.Contains(key)) return 0;
    var v = ((long Until, int Step))cool[key]!;
    return v.Until <= 0 ? 0 : Math.Max(0, (v.Until - DateTime.UtcNow.Ticks) / (double)TimeSpan.TicksPerSecond);
}
string CbList(List<(string Fn, string Data)> cbs) => "[" + string.Join(", ", cbs.Select(c => c.Fn + "(" + c.Data + ")")) + "]";

Reset();
ResetServerErrors(2);
var s1Handled = ServerError(batchUrl, 502, 0, Html502);
var s1Cool = CoolS(batchUrl);
var s1Cbs = Cbs();
Check("S1 5xx d'une seule route, Athena répond ailleurs : seule l'adresse attend (30 à 36 s), ni pause commune ni rappel",
    s1Handled && s1Cool is >= 29.5 and <= 36.1 && PauseS() <= 0 && s1Cbs.Count == 0,
    $"adresse en pause {Fmt(s1Cool)} s, pause commune {Fmt(Math.Max(0, PauseS()))} s, rappels {CbList(s1Cbs)}");

Reset();
ResetServerErrors(5);
ServerError(positionUrl, 502, 0, Html502);
ServerError(batchUrl, 502, 0, Html502);
var (s2Pos, s2Batch) = (CoolS(positionUrl), CoolS(batchUrl));
var s2Cbs = Cbs();
Check("S2 5xx sur deux adresses, mais une réponse acceptée il y a 5 s : pauses par adresse seulement",
    s2Pos >= 29.5 && s2Batch >= 29.5 && PauseS() <= 0 && s2Cbs.Count == 0,
    $"position {Fmt(s2Pos)} s, lot {Fmt(s2Batch)} s, pause commune {Fmt(Math.Max(0, PauseS()))} s, rappels {CbList(s2Cbs)}");

Reset();
ResetServerErrors(15);
ServerError(chatUrl, 500, 0, "{\"error\":\"server_error\"}");
var s3Chat = CoolS(chatUrl);
var s3Cbs = Cbs();
Check("S3 une seule adresse en 5xx, rien d'accepté depuis 15 s : seule l'adresse attend (une route cassée n'arrête pas la liaison)",
    s3Chat >= 29.5 && PauseS() <= 0 && s3Cbs.Count == 0,
    $"tchat {Fmt(s3Chat)} s, pause commune {Fmt(Math.Max(0, PauseS()))} s, rappels {CbList(s3Cbs)}");

Reset();
ResetServerErrors(15);
ServerError(positionUrl, 502, 0, Html502);
var s4Before = CoolS(positionUrl);
ServerError(batchUrl, 502, 0, Html502);
var (s4Pause, s4Pos, s4Batch) = (PauseS(), CoolS(positionUrl), CoolS(batchUrl));
var s4Cbs = Cbs();
Check("S4 panne d'Athena entier (502 sur deux adresses, rien d'accepté depuis 15 s) : pause commune, pauses par adresse levées, NetworkHiccup",
    s4Before >= 29.5 && s4Pause > 0 && s4Pos == 0 && s4Batch == 0 && s4Cbs.Any(c => c.Fn == "NetworkHiccup")
    && !s4Cbs.Any(c => c.Fn is "AccessDenied" or "RateLimited"),
    $"position en pause avant {Fmt(s4Before)} s ; après le 2e 502 : pause commune {Fmt(Math.Max(0, s4Pause))} s, position {Fmt(s4Pos)} s, lot {Fmt(s4Batch)} s, rappels {CbList(s4Cbs)}");

ServerError(batchUrl, 502, 0, Html502);
ServerError(batchUrl, 502, 0, Html502);
var (s5Step, s5Pause) = (Step(), PauseS());
Ok();
var s5Cbs = Cbs();
Check("S5 panne qui dure : échelle courte (10 s au 3e échec), puis sortie au premier succès, RateLimitClear, aucune pause par adresse",
    s5Step == 1 && s5Pause >= 9.9 && Step() == 0 && PauseS() <= 0 && s5Cbs.Any(c => c.Fn == "RateLimitClear")
    && CoolS(positionUrl) == 0 && CoolS(batchUrl) == 0,
    $"au 3e échec : palier {s5Step}, pause {Fmt(Math.Max(0, s5Pause))} s ; après 1 succès : {PauseFmt()}, position {Fmt(CoolS(positionUrl))} s, lot {Fmt(CoolS(batchUrl))} s, RateLimitClear {s5Cbs.Any(c => c.Fn == "RateLimitClear")}");

Reset();
ResetServerErrors(15);
ServerError(positionUrl, 503, 30, Db503);
ServerError(batchUrl, 503, 30, Db503);
var s6Pause = PauseS();
var s6Cbs = Cbs();
Check("S6 503 sans maintenance (base injoignable, Retry-After 30) sur toute la liaison : pause commune de 30 à 36 s, RateLimited(30)",
    s6Pause is >= 29.5 and <= 36.1 && s6Cbs.Any(c => c.Fn == "RateLimited" && c.Data == "30"),
    $"pause commune {Fmt(Math.Max(0, s6Pause))} s, rappels {CbList(s6Cbs)}");

var s7 = new List<double>();
for (var n = 0; n < 200; n++)
{
    ResetServerErrors(2);
    ServerError(chatUrl, 502, 0, Html502);
    s7.Add(CoolS(chatUrl));
}
var s7Ra = new List<double>();
for (var n = 0; n < 50; n++)
{
    ResetServerErrors(2);
    ServerError(chatUrl, 503, 60, Db503);
    s7Ra.Add(CoolS(chatUrl));
}
Check("S7 gigue de la pause par adresse : 30 à 36 s au 1er palier, écart d'au moins 3 s ; jamais moins que le Retry-After",
    s7.Min() >= 29.9 && s7.Max() <= 36.1 && s7.Max() - s7.Min() >= 3 && s7Ra.Min() >= 59.9 && s7Ra.Max() <= 72.1,
    $"200 tirages : de {Fmt(s7.Min())} à {Fmt(s7.Max())} s ; Retry-After 60, 50 tirages : de {Fmt(s7Ra.Min())} à {Fmt(s7Ra.Max())} s");

Reset();
ResetServerErrors(15);
var s8Handled = ServerError(positionUrl, 503, 120, MaintBody);
Check("S8 503 de maintenance : pas une erreur d'adresse (pause commune par NoteRateLimited, comme avant)",
    !s8Handled && CoolS(positionUrl) == 0, $"traité par adresse : {s8Handled}, position {Fmt(CoolS(positionUrl))} s");

Reset();
ResetServerErrors(30);
var slotPollOk = NewSlot("bench:sondage-ok");
slotType.GetField("Url", I)?.SetValue(slotPollOk, Base + "/api/atak/orders");
notePoll.Invoke(null, new[] { slotPollOk, (object)200 });
ServerError(positionUrl, 502, 0, Html502);
ServerError(batchUrl, 502, 0, Html502);
Check("S9 un sondage accepté à l'instant suffit : deux adresses en 5xx gardent leurs pauses par adresse",
    PauseS() <= 0 && CoolS(positionUrl) >= 29.5 && CoolS(batchUrl) >= 29.5,
    $"pause commune {Fmt(Math.Max(0, PauseS()))} s, position {Fmt(CoolS(positionUrl))} s, lot {Fmt(CoolS(batchUrl))} s");

Reset();
ResetServerErrors(15);
var slotPoll5xx = NewSlot("bench:sondage-5xx");
slotType.GetField("Url", I)?.SetValue(slotPoll5xx, Base + "/api/atak/orders");
notePoll.Invoke(null, new[] { slotPoll5xx, (object)502 });
ServerError(positionUrl, 502, 0, Html502);
Check("S10 un sondage en 5xx compte comme une adresse en panne : un envoi en 5xx suffit alors pour la pause commune",
    PauseS() > 0 && CoolS(positionUrl) == 0,
    $"pause commune {Fmt(Math.Max(0, PauseS()))} s, position {Fmt(CoolS(positionUrl))} s");

Reset();
ResetServerErrors(30);
Ok();
ServerError(positionUrl, 502, 0, Html502);
ServerError(batchUrl, 502, 0, Html502);
Check("S11 un envoi accepté à l'instant suffit aussi : deux adresses en 5xx gardent leurs pauses par adresse",
    PauseS() <= 0 && CoolS(positionUrl) >= 29.5 && CoolS(batchUrl) >= 29.5,
    $"pause commune {Fmt(Math.Max(0, PauseS()))} s, position {Fmt(CoolS(positionUrl))} s, lot {Fmt(CoolS(batchUrl))} s");

Reset();
ResetServerErrors(2);
var s12Pos = new List<double>();
var s12Batch = new List<double>();
var s12Chat = new List<double>();
for (var n = 0; n < 4; n++)
{
    ServerError(positionUrl, 503, 30, Db503);
    s12Pos.Add(CoolS(positionUrl));
    ServerError(batchUrl, 502, 0, Html502);
    s12Batch.Add(CoolS(batchUrl));
}
for (var n = 0; n < 2; n++)
{
    ServerError(chatUrl, 502, 0, Html502);
    s12Chat.Add(CoolS(chatUrl));
}
ResetServerErrors(2);
ServerError(positionUrl, 503, 120, Db503);
ServerError(positionUrl, 503, 120, Db503);
var s12Ra = CoolS(positionUrl);
Check("S12 adresses de position, seule leur route en 5xx : 30 à 36 s à chaque erreur, sans monter l'échelle ; une autre adresse monte (60 s au 2e) ; un Retry-After plus long reste respecté",
    s12Pos.All(x => x is >= 29.5 and <= 36.1) && s12Batch.All(x => x is >= 29.5 and <= 36.1)
    && s12Chat[1] is >= 59.5 and <= 72.1 && s12Ra is >= 119.5 and <= 144.1 && PauseS() <= 0,
    $"position, 4 erreurs : {string.Join(" ", s12Pos.Select(Fmt))} s ; lot : {string.Join(" ", s12Batch.Select(Fmt))} s ; tchat au 2e : {Fmt(s12Chat[1])} s ; position, Retry-After 120 : {Fmt(s12Ra)} s");

foreach (var k in new[] { "bench:sondage-ok", "bench:sondage-5xx" })
    tryRemove.Invoke(cache, new object?[] { k, null });
ResetServerErrors(0);

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
