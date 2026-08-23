using System.Runtime.InteropServices;

namespace COMSPECExtension;

/// <summary>
/// Fenêtre Windows (CGU + disclaimer bêta) au lancement d’Arma.
/// Native AOT : Win32 pur, pas de WinForms.
/// </summary>
internal static class BetaNoticeWindow
{
    private const string ClassName = "COMSPEC_BetaNotice";
    private const int WsVisible = 0x10000000;
    private const int WsChild = 0x40000000;
    private const int WsCaption = 0x00C00000;
    private const int WsSysMenu = 0x00080000;
    private const int WsBorder = 0x00800000;
    private const int WsVScroll = 0x00200000;
    private const int WsTabStop = 0x00010000;
    private const int WsExTopmost = 0x00000008;
    private const int WsExDlgModalFrame = 0x00000001;
    private const int WsExAppWindow = 0x00040000;
    private const int EsMultiline = 0x0004;
    private const int EsReadonly = 0x0800;
    private const int EsWantReturn = 0x1000;
    private const int SsCenter = 0x0001;
    private const int BsPushButton = 0x00000000;
    private const int ColorWindow = 5;
    private const int WmDestroy = 0x0002;
    private const int WmClose = 0x0010;
    private const int WmCommand = 0x0111;
    private const int WmCtlColorStatic = 0x0138;
    private const int WmCtlColorEdit = 0x0133;
    private const int WmSetFont = 0x0030;
    private const int IdAccept = 1001;
    private const int IdLater = 1002;
    private const int IdBody = 1003;
    private const int SwShow = 5;
    private const int SwRestore = 9;

    private delegate nint WndProc(nint hWnd, uint msg, nint wParam, nint lParam);

    private static readonly WndProc WndProcKeepAlive = OnWndProc;
    private static bool _classRegistered;
    private static int _result; // 1 = ack, 0 = later
    private static nint _hFontTitle = IntPtr.Zero;
    private static nint _hFontBody = IntPtr.Zero;
    private static nint _hFontBtn = IntPtr.Zero;
    private static nint _hBrushBg = IntPtr.Zero;
    private static nint _hBrushEdit = IntPtr.Zero;

    internal static string ShowModal()
    {
        _result = 0;
        try
        {
            EnsureClass();
            EnsureGdi();

            var screenW = GetSystemMetrics(0);
            var screenH = GetSystemMetrics(1);
            var w = 560;
            var h = 640;
            var x = Math.Max(40, (screenW - w) / 2);
            var y = Math.Max(40, (screenH - h) / 2);

            var hwnd = CreateWindowExW(
                WsExTopmost | WsExDlgModalFrame | WsExAppWindow,
                ClassName,
                "COMSPEC Overwatch",
                WsCaption | WsSysMenu | WsVisible | WsBorder,
                x, y, w, h,
                IntPtr.Zero, IntPtr.Zero, GetThisModule(), IntPtr.Zero);

            if (hwnd == IntPtr.Zero)
                return "ERR|window_create";

            BuildChildren(hwnd, w, h);
            ShowWindow(hwnd, SwShow);
            UpdateWindow(hwnd);
            SetForegroundWindow(hwnd);

            while (GetMessageW(out var msg, IntPtr.Zero, 0, 0) > 0)
            {
                TranslateMessage(ref msg);
                DispatchMessageW(ref msg);
            }

            return _result == 1 ? "OK|ack" : "OK|later";
        }
        catch
        {
            return "ERR|window_failed";
        }
    }

    private static void BuildChildren(nint hwnd, int clientW, int clientH)
    {
        // Client area is slightly smaller than outer size; use generous margins.
        var french = IsFrenchUi();
        var pad = 22;
        var innerW = clientW - 24;

        var title = CreateWindowExW(0, "STATIC", "COMSPEC OVERWATCH",
            WsVisible | WsChild | SsCenter,
            pad, 14, innerW - pad, 28, hwnd, IntPtr.Zero, GetThisModule(), IntPtr.Zero);
        SendMessage(title, WmSetFont, _hFontTitle, 1);

        var badge = CreateWindowExW(0, "STATIC",
            french ? "BÊTA PUBLIQUE  ·  VERSION DE TEST" : "PUBLIC BETA  ·  TEST BUILD",
            WsVisible | WsChild | SsCenter,
            pad, 44, innerW - pad, 22, hwnd, IntPtr.Zero, GetThisModule(), IntPtr.Zero);
        SendMessage(badge, WmSetFont, _hFontBtn, 1);

        var intro = CreateWindowExW(0, "STATIC",
            french
                ? "Lisez les conditions d’utilisation avant de continuer. Ce pack n’est pas une version finale."
                : "Please read the terms of use before continuing. This pack is not a final release.",
            WsVisible | WsChild,
            pad, 70, innerW - pad, 36, hwnd, IntPtr.Zero, GetThisModule(), IntPtr.Zero);
        SendMessage(intro, WmSetFont, _hFontBody, 1);

        var edit = CreateWindowExW(
            0x00000200, // WS_EX_CLIENTEDGE
            "EDIT",
            BodyText(french),
            WsVisible | WsChild | WsVScroll | WsTabStop | EsMultiline | EsReadonly | EsWantReturn,
            pad, 112, innerW - pad, 400, hwnd, IdBody, GetThisModule(), IntPtr.Zero);
        SendMessage(edit, WmSetFont, _hFontBody, 1);

        var btnW = 210;
        var btnH = 36;
        var btnY = 528;
        var accept = CreateWindowExW(0, "BUTTON",
            french ? "J’accepte les conditions" : "I accept the terms",
            WsVisible | WsChild | WsTabStop | BsPushButton,
            pad, btnY, btnW, btnH, hwnd, IdAccept, GetThisModule(), IntPtr.Zero);
        SendMessage(accept, WmSetFont, _hFontBtn, 1);

        var later = CreateWindowExW(0, "BUTTON",
            french ? "Plus tard" : "Later",
            WsVisible | WsChild | WsTabStop | BsPushButton,
            pad + btnW + 16, btnY, 140, btnH, hwnd, IdLater, GetThisModule(), IntPtr.Zero);
        SendMessage(later, WmSetFont, _hFontBtn, 1);

        _ = clientH;
    }

    private static nint OnWndProc(nint hWnd, uint msg, nint wParam, nint lParam)
    {
        switch (msg)
        {
            case WmCtlColorStatic:
            case WmCtlColorEdit:
                SetTextColor(wParam, 0x00F0F4E8u);
                SetBkColor(wParam, msg == WmCtlColorEdit ? 0x00221610u : 0x0022180Eu);
                return msg == WmCtlColorEdit ? _hBrushEdit : _hBrushBg;
            case WmCommand:
                var id = (int)(wParam & 0xFFFF);
                if (id == IdAccept)
                {
                    _result = 1;
                    DestroyWindow(hWnd);
                    return 0;
                }
                if (id == IdLater)
                {
                    _result = 0;
                    DestroyWindow(hWnd);
                    return 0;
                }
                break;
            case WmClose:
                _result = 0;
                DestroyWindow(hWnd);
                return 0;
            case WmDestroy:
                PostQuitMessage(0);
                return 0;
        }
        return DefWindowProcW(hWnd, msg, wParam, lParam);
    }

    private static void EnsureClass()
    {
        if (_classRegistered) return;
        var wc = new WndClassEx
        {
            cbSize = (uint)Marshal.SizeOf<WndClassEx>(),
            style = 0x0003, // CS_HREDRAW | CS_VREDRAW
            lpfnWndProc = Marshal.GetFunctionPointerForDelegate(WndProcKeepAlive),
            hInstance = GetThisModule(),
            hCursor = LoadCursorW(IntPtr.Zero, (nint)32512), // IDC_ARROW
            hbrBackground = CreateSolidBrush(0x0022180E),
            lpszClassName = ClassName
        };
        if (RegisterClassExW(ref wc) == 0)
        {
            var err = Marshal.GetLastWin32Error();
            if (err != 1410) // already exists
                throw new InvalidOperationException("RegisterClassEx " + err);
        }
        _classRegistered = true;
    }

    private static void EnsureGdi()
    {
        if (_hFontTitle == IntPtr.Zero)
            _hFontTitle = CreateFontW(-22, 0, 0, 0, 700, 0, 0, 0, 1, 0, 0, 5, 0, "Segoe UI");
        if (_hFontBody == IntPtr.Zero)
            _hFontBody = CreateFontW(-15, 0, 0, 0, 400, 0, 0, 0, 1, 0, 0, 5, 0, "Segoe UI");
        if (_hFontBtn == IntPtr.Zero)
            _hFontBtn = CreateFontW(-15, 0, 0, 0, 600, 0, 0, 0, 1, 0, 0, 5, 0, "Segoe UI");
        if (_hBrushBg == IntPtr.Zero)
            _hBrushBg = CreateSolidBrush(0x0022180E);
        if (_hBrushEdit == IntPtr.Zero)
            _hBrushEdit = CreateSolidBrush(0x00221610);
    }

    private static nint GetThisModule()
    {
        GetModuleHandleExW(0x00000006, Marshal.GetFunctionPointerForDelegate(WndProcKeepAlive), out var h);
        return h != IntPtr.Zero ? h : GetModuleHandleW(null);
    }

    private static bool IsFrenchUi()
    {
        var lang = GetUserDefaultUILanguage();
        return (lang & 0xFF) == 0x0C; // LANG_FRENCH
    }

    private static string BodyText(bool french)
    {
        if (!french)
        {
            return
                "PUBLIC BETA DISCLAIMER\r\n" +
                "COMSPEC Overwatch (including Athena and companion tools) is a public beta. " +
                "Features may change, break, or be temporarily unavailable. Rough edges are expected.\r\n\r\n" +
                "TERMS OF USE\r\n" +
                "1. Organised play — Use the pack responsibly during milsim sessions. Do not abuse connectivity or tools in ways that harm other players, communities, or COMSPEC services.\r\n" +
                "2. No warranty — The pack is provided as-is, without guarantee of availability, accuracy, or fitness for a particular purpose.\r\n" +
                "3. Updates — Install Workshop updates so you stay compatible with Athena and organised sessions.\r\n" +
                "4. Report issues — In game: Esc → COMSPEC Overwatch — mod manager → Report a problem. Describe what happened. Reports go to the team that maintains the pack (not Discord).\r\n" +
                "5. Limited technical data — Continuing may record limited technical information (Steam identity when available, player name, pack version, related client details) to operate the beta and Athena. This is not used for unrelated advertising.\r\n" +
                "6. Acceptance — “I accept the terms” records this notice on your profile. “Later” closes the window; it may appear again on the next launch.\r\n\r\n" +
                "Thank you for helping improve Overwatch.";
        }

        return
            "AVERTISSEMENT — BÊTA PUBLIQUE\r\n" +
            "COMSPEC Overwatch (y compris Athena et les outils compagnons) est une bêta publique. " +
            "Des fonctions peuvent évoluer, dysfonctionner ou être temporairement indisponibles. " +
            "Des aspérités sont normales à ce stade. Ce n’est pas une version finale.\r\n\r\n" +
            "CONDITIONS D’UTILISATION\r\n" +
            "1. Sessions organisées — Utilisez le pack de façon responsable lors des sessions milsim. N’abusez pas de la liaison ni des outils pour nuire à d’autres joueurs, communautés ou services COMSPEC.\r\n" +
            "2. Absence de garantie — Le pack est fourni en l’état, sans promesse de disponibilité, d’exactitude ou d’adéquation à un usage particulier.\r\n" +
            "3. Mises à jour — Installez les mises à jour Workshop pour rester compatible avec Athena et les sessions organisées.\r\n" +
            "4. Signaler un problème — En jeu : Échap → COMSPEC Overwatch — gestion du mod → Signaler un problème. Décrivez ce qui s’est passé. Les signalements arrivent à l’équipe qui suit le pack (pas sur Discord).\r\n" +
            "5. Données techniques limitées — En continuant, des informations techniques limitées peuvent être enregistrées (identité Steam lorsqu’elle est disponible, nom du joueur, version du pack, détails clients associés) pour faire tourner la bêta et Athena. Elles ne servent pas à de la publicité non liée.\r\n" +
            "6. Acceptation — « J’accepte les conditions » enregistre cette note sur votre profil. « Plus tard » ferme la fenêtre ; l’avis pourra réapparaître au prochain lancement.\r\n\r\n" +
            "Merci d’aider à améliorer Overwatch.";
    }

    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    private struct WndClassEx
    {
        public uint cbSize;
        public uint style;
        public nint lpfnWndProc;
        public int cbClsExtra;
        public int cbWndExtra;
        public nint hInstance;
        public nint hIcon;
        public nint hCursor;
        public nint hbrBackground;
        public string? lpszMenuName;
        public string lpszClassName;
        public nint hIconSm;
    }

    [StructLayout(LayoutKind.Sequential)]
    private struct Point
    {
        public int X;
        public int Y;
    }

    [StructLayout(LayoutKind.Sequential)]
    private struct Msg
    {
        public nint hwnd;
        public uint message;
        public nint wParam;
        public nint lParam;
        public uint time;
        public Point pt;
    }

    [DllImport("user32.dll", CharSet = CharSet.Unicode, SetLastError = true)]
    private static extern ushort RegisterClassExW(ref WndClassEx lpwcx);

    [DllImport("user32.dll", CharSet = CharSet.Unicode, SetLastError = true)]
    private static extern nint CreateWindowExW(int dwExStyle, string lpClassName, string? lpWindowName,
        int dwStyle, int x, int y, int nWidth, int nHeight, nint hWndParent, nint hMenu, nint hInstance, nint lpParam);

    [DllImport("user32.dll", CharSet = CharSet.Unicode)]
    private static extern nint DefWindowProcW(nint hWnd, uint msg, nint wParam, nint lParam);

    [DllImport("user32.dll")]
    private static extern bool DestroyWindow(nint hWnd);

    [DllImport("user32.dll")]
    private static extern bool ShowWindow(nint hWnd, int nCmdShow);

    [DllImport("user32.dll")]
    private static extern bool UpdateWindow(nint hWnd);

    [DllImport("user32.dll")]
    private static extern bool SetForegroundWindow(nint hWnd);

    [DllImport("user32.dll")]
    private static extern void PostQuitMessage(int nExitCode);

    [DllImport("user32.dll", CharSet = CharSet.Unicode)]
    private static extern int GetMessageW(out Msg lpMsg, nint hWnd, uint wMsgFilterMin, uint wMsgFilterMax);

    [DllImport("user32.dll")]
    private static extern bool TranslateMessage(ref Msg lpMsg);

    [DllImport("user32.dll", CharSet = CharSet.Unicode)]
    private static extern nint DispatchMessageW(ref Msg lpMsg);

    [DllImport("user32.dll", CharSet = CharSet.Unicode)]
    private static extern nint SendMessage(nint hWnd, int msg, nint wParam, nint lParam);

    [DllImport("user32.dll")]
    private static extern int GetSystemMetrics(int nIndex);

    [DllImport("user32.dll", CharSet = CharSet.Unicode)]
    private static extern nint LoadCursorW(nint hInstance, nint lpCursorName);

    [DllImport("gdi32.dll")]
    private static extern uint SetTextColor(nint hdc, uint color);

    [DllImport("gdi32.dll")]
    private static extern int SetBkColor(nint hdc, uint color);

    [DllImport("gdi32.dll")]
    private static extern nint CreateSolidBrush(uint color);

    [DllImport("gdi32.dll", CharSet = CharSet.Unicode)]
    private static extern nint CreateFontW(int cHeight, int cWidth, int cEscapement, int cOrientation, int cWeight,
        uint bItalic, uint bUnderline, uint bStrikeOut, uint iCharSet, uint iOutPrecision, uint iClipPrecision,
        uint iQuality, uint iPitchAndFamily, string pszFaceName);

    [DllImport("kernel32.dll", CharSet = CharSet.Unicode)]
    private static extern nint GetModuleHandleW(string? lpModuleName);

    [DllImport("kernel32.dll", CharSet = CharSet.Unicode, SetLastError = true)]
    private static extern bool GetModuleHandleExW(uint dwFlags, nint lpModuleName, out nint phModule);

    [DllImport("kernel32.dll")]
    private static extern ushort GetUserDefaultUILanguage();
}
