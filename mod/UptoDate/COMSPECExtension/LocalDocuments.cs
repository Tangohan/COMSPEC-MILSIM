using System;
using System.Collections.Generic;
using System.IO;

namespace COMSPECExtension;

/// <summary>
/// Dossier Documents où la DLL écrit : celui du disque local, jamais OneDrive. Windows redirige souvent « Mes documents »
/// vers OneDrive ; tout ce que la DLL y enregistrait partait alors dans le nuage. Ordre : Documents du système s'il n'est
/// pas dans OneDrive, sinon %USERPROFILE%\Documents, sinon le dossier de la DLL (COMSPEC_Documents).
/// Un dossier déjà rangé dans OneDrive est repris une fois (déplacé, ou fichiers légers copiés si le déplacement échoue).
/// La recherche des captures d'écran d'Arma lit toujours OneDrive : c'est Arma qui y écrit, pas la DLL.
/// </summary>
public static partial class Extension
{
    private static readonly object LocalDocsLock = new();
    private static readonly HashSet<string> LocalDocsDone = new(StringComparer.OrdinalIgnoreCase);
    private static string? _localDocsRoot;

    private static bool IsInOneDrive(string path)
    {
        foreach (var v in new[] { "OneDrive", "OneDriveConsumer", "OneDriveCommercial" })
        {
            var root = Environment.GetEnvironmentVariable(v);
            if (!string.IsNullOrWhiteSpace(root) && path.StartsWith(root.TrimEnd('\\', '/'), StringComparison.OrdinalIgnoreCase)) return true;
        }
        foreach (var part in path.Split('\\', '/'))
        {
            if (part.StartsWith("OneDrive", StringComparison.OrdinalIgnoreCase)) return true;
        }
        return false;
    }

    private static string ShellDocuments()
    {
        try { return Environment.GetFolderPath(Environment.SpecialFolder.MyDocuments) ?? ""; }
        catch { return ""; }
    }

    private static string LocalDocumentsRoot()
    {
        lock (LocalDocsLock)
        {
            if (_localDocsRoot != null) return _localDocsRoot;
            var shell = ShellDocuments();
            var root = shell;
            if (string.IsNullOrWhiteSpace(root) || IsInOneDrive(root))
            {
                string profile;
                try { profile = Environment.GetFolderPath(Environment.SpecialFolder.UserProfile) ?? ""; }
                catch { profile = ""; }
                root = string.IsNullOrWhiteSpace(profile) ? "" : Path.Combine(profile, "Documents");
            }
            try
            {
                if (string.IsNullOrWhiteSpace(root)) throw new DirectoryNotFoundException();
                Directory.CreateDirectory(root);
            }
            catch
            {
                root = Path.Combine(AppContext.BaseDirectory, "COMSPEC_Documents");
                try { Directory.CreateDirectory(root); } catch { /* rendu quand même : chaque écriture gère son échec */ }
            }
            _localDocsRoot = root;
            return root;
        }
    }

    /// <summary>Chemin sous les Documents locaux ; reprend une fois ce dossier s'il était resté dans OneDrive.</summary>
    private static string LocalDocumentsPath(params string[] parts)
    {
        var root = LocalDocumentsRoot();
        var rel = Path.Combine(parts);
        var target = Path.Combine(root, rel);
        lock (LocalDocsLock)
        {
            if (!LocalDocsDone.Add(rel)) return target;
            try
            {
                var shell = ShellDocuments();
                if (string.IsNullOrWhiteSpace(shell) || !IsInOneDrive(shell)) return target;
                var old = Path.Combine(shell, rel);
                if (!Directory.Exists(old)) return target;
                Directory.CreateDirectory(Path.GetDirectoryName(target)!);
                if (!Directory.Exists(target))
                {
                    try { Directory.Move(old, target); return target; }
                    catch { Directory.CreateDirectory(target); }
                }
                foreach (var f in Directory.EnumerateFiles(old))
                {
                    var dst = Path.Combine(target, Path.GetFileName(f));
                    if (!File.Exists(dst) && new FileInfo(f).Length < 2_000_000) File.Copy(f, dst);
                }
            }
            catch { /* OneDrive occupé ou dossier illisible : le dossier local sert quand même */ }
        }
        return target;
    }
}
