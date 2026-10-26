using System.Globalization;
using System.Runtime.InteropServices;
using System.Text;

namespace COMSPECExtension;

/// <summary>
/// Presse-papiers Windows pour le téléphone ATAK natif (export / import des plans de l'app Plan, copie d'une grille).
/// En multijoueur, copyToClipboard / copyFromClipboard d'Arma sont sans effet : la DLL lit et écrit le texte Unicode.
/// Commandes SQF (retour "OK|..." ou "ERR|...") :
///   ClipBegin                 → vide le tampon d'écriture
///   ClipAppend [morceau]      → ajoute au tampon (morceaux de 4000 caractères : la ligne d'appel Arma est bornée)
///   ClipCommit                → écrit le tampon dans le presse-papiers
///   ClipGet [décalage]        → OK|longueur totale|morceau (lecture par morceaux, la réponse Arma est bornée)
/// </summary>
public static partial class Extension
{
    private const uint CfUnicodeText = 13;
    private const uint GmemMoveable = 0x0002;
    private static readonly StringBuilder _clipBuffer = new();
    private static string _clipRead = "";

    [DllImport("user32.dll", SetLastError = true)] private static extern bool OpenClipboard(nint hWndNewOwner);
    [DllImport("user32.dll", SetLastError = true)] private static extern bool CloseClipboard();
    [DllImport("user32.dll", SetLastError = true)] private static extern bool EmptyClipboard();
    [DllImport("user32.dll", SetLastError = true)] private static extern nint SetClipboardData(uint uFormat, nint hMem);
    [DllImport("user32.dll", SetLastError = true)] private static extern nint GetClipboardData(uint uFormat);
    [DllImport("user32.dll")] private static extern nint GetForegroundWindow();
    [DllImport("kernel32.dll", SetLastError = true)] private static extern nint GlobalAlloc(uint uFlags, nuint dwBytes);
    [DllImport("kernel32.dll", SetLastError = true)] private static extern nint GlobalLock(nint hMem);
    [DllImport("kernel32.dll", SetLastError = true)] private static extern bool GlobalUnlock(nint hMem);
    [DllImport("kernel32.dll", SetLastError = true)] private static extern nint GlobalFree(nint hMem);

    // Le presse-papiers peut être tenu un instant par une autre application : quelques essais.
    private static bool ClipOpen()
    {
        // Propriétaire = fenêtre d'Arma (au premier plan) : avec NULL, SetClipboardData échouerait après EmptyClipboard.
        var owner = GetForegroundWindow();
        for (var i = 0; i < 10; i++)
        {
            if (OpenClipboard(owner)) return true;
            Thread.Sleep(15);
        }
        return false;
    }

    private static string ClipWrite(string text)
    {
        if (!ClipOpen()) return "ERR|presse-papiers occupé";
        try
        {
            if (!EmptyClipboard()) return "ERR|presse-papiers refusé";
            var bytes = (text.Length + 1) * 2;
            var h = GlobalAlloc(GmemMoveable, (nuint)bytes);
            if (h == 0) return "ERR|mémoire";
            var p = GlobalLock(h);
            if (p == 0) { GlobalFree(h); return "ERR|mémoire"; }
            try
            {
                var chars = (text + "\0").ToCharArray();
                Marshal.Copy(chars, 0, p, chars.Length);
            }
            finally { GlobalUnlock(h); }
            // En cas de succès, le système devient propriétaire de la mémoire.
            if (SetClipboardData(CfUnicodeText, h) == 0) { GlobalFree(h); return "ERR|écriture refusée"; }
            return "OK|" + text.Length.ToString(CultureInfo.InvariantCulture);
        }
        finally { CloseClipboard(); }
    }

    private static string ClipReadAll()
    {
        if (!ClipOpen()) return "";
        try
        {
            var h = GetClipboardData(CfUnicodeText);
            if (h == 0) return "";
            var p = GlobalLock(h);
            if (p == 0) return "";
            try { return Marshal.PtrToStringUni(p) ?? ""; }
            finally { GlobalUnlock(h); }
        }
        finally { CloseClipboard(); }
    }

    private static string ClipCommand(string function, string?[] args)
    {
        string Arg(int i) => args.Length > i ? (args[i] ?? "") : "";
        try
        {
            switch (function)
            {
                case "ClipBegin":
                    lock (_clipBuffer) _clipBuffer.Clear();
                    return "OK|0";
                case "ClipAppend":
                {
                    // Guillemets extérieurs déjà retirés (ArmaString) ; les guillemets internes arrivent doublés.
                    var chunk = Arg(0).Replace("\"\"", "\"");
                    lock (_clipBuffer)
                    {
                        if (_clipBuffer.Length + chunk.Length > 2_000_000) return "ERR|trop long";
                        _clipBuffer.Append(chunk);
                        return "OK|" + _clipBuffer.Length.ToString(CultureInfo.InvariantCulture);
                    }
                }
                case "ClipCommit":
                {
                    string text;
                    lock (_clipBuffer) text = _clipBuffer.ToString();
                    return ClipWrite(text);
                }
                case "ClipGet":
                {
                    var offset = int.TryParse(Arg(0).Trim('"'), NumberStyles.Integer, CultureInfo.InvariantCulture, out var o) ? Math.Max(0, o) : 0;
                    // Décalage 0 : nouvelle lecture du presse-papiers ; les morceaux suivants viennent de la même copie.
                    if (offset == 0) _clipRead = ClipReadAll();
                    var total = _clipRead.Length;
                    var head = "OK|" + total.ToString(CultureInfo.InvariantCulture) + "|";
                    var room = Math.Max(0, MaxOutputBytes - head.Length - 64);
                    // Réponse bornée en octets : prudence pour l'UTF-8 (un caractère peut en prendre jusqu'à trois).
                    var take = Math.Min(Math.Max(0, total - offset), room / 3);
                    // Jamais de coupure au milieu d'une paire de substitution (emoji…) : chaque moitié deviendrait U+FFFD.
                    if (take > 0 && offset + take < total && char.IsHighSurrogate(_clipRead[offset + take - 1])) take--;
                    return head + (offset < total ? _clipRead.Substring(offset, take) : "");
                }
            }
        }
        catch (Exception ex)
        {
            return "ERR|" + ex.Message;
        }
        return "ERR|unknown_command";
    }
}
