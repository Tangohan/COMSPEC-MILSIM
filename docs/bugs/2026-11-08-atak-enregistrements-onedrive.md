# Enregistrements du mod dans OneDrive

- **Contexte** : retour du propriétaire : enregistrer seulement dans les documents du disque C et le dossier du mod.
- **Symptôme** : cartes, captures et réglages du mod apparaissent dans OneDrive.
- **Cause prouvée par lecture** : les DLL écrivaient sous `SpecialFolder.MyDocuments`, que Windows redirige vers OneDrive quand la sauvegarde des Documents est active. COMSPEC Link : `Arma 3 - COMSPEC\Sync` (file d'envoi, effectif médical), `Maps` (tuiles), `Captures`, `COMSPEC_MapCapture`. DLL native : `Arma 3\COMSPEC_Local`, `Arma 3\COMSPEC_Music` (Bluetooth compris). Le SQF n'écrit aucun fichier lui-même.
- **Correctif** : `LocalDocuments.cs` (mêmes règles dans les deux DLL) : Documents du système s'il est hors OneDrive, sinon `%USERPROFILE%\Documents`, sinon `COMSPEC_Documents` à côté de la DLL. Un dossier resté dans OneDrive est déplacé une fois vers le disque local (fichiers de moins de 2 Mo copiés si le déplacement échoue).
- **Limite, hors de portée du mod** : Arma écrit lui-même son profil et le dossier `Screenshots` dans les Documents de Windows. La lecture des captures dans OneDrive reste donc nécessaire tant que Windows redirige. Pour en sortir : option de lancement `-profiles=`, ou désactiver la sauvegarde des Documents dans OneDrive.
- **Fichiers touchés** : `COMSPECATAKNativeExtension/LocalDocuments.cs`, `LocalConfig.cs`, `MusicPlayer.cs` ; `COMSPECExtension/LocalDocuments.cs`, `Extension.cs`, `Extension_MapCapture.cs`.
- **Vérification** : sources compilées (`dotnet build`, sortie hors dépôt). DLL à reconstruire par `build_mod.bat`. En jeu sur un PC à Documents redirigés : réglages, musique, cartes et captures sous `C:\Users\<nom>\Documents`, rien de neuf dans OneDrive, anciens fichiers repris.
- **Statut** : corrigé à vérifier en jeu.
