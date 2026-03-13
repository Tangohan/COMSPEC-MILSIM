using System.Runtime.InteropServices;
using System.Text;
using System.Text.Json;

namespace COMSPECExtension;

public static class Extension
{
    private static string _baseUrl = "";
    private static readonly HttpClient HttpClient = new() { Timeout = TimeSpan.FromSeconds(5) };

    [UnmanagedCallersOnly(EntryPoint = "RVExtensionVersion")]
    public static void RvExtensionVersion(nint output, int outputSize)
    {
        Output(output, outputSize, "COMSPECExtension 1.0");
    }

    private static void Output(nint output, int outputSize, string data)
    {
        var bytes = Encoding.UTF8.GetBytes(data);
        Marshal.Copy(bytes, 0, output, Math.Min(bytes.Length, outputSize));
    }

    [UnmanagedCallersOnly(EntryPoint = "RVExtension")]
    public static void RvExtension(nint output, int outputSize, nint function)
    {
        var functionString = Marshal.PtrToStringUTF8(function);
        RvExtensionArgsImpl(functionString, []);
        Output(output, outputSize, "");
    }

    [UnmanagedCallersOnly(EntryPoint = "RVExtensionArgs")]
    public static int RvExtensionArgs(nint output, int outputSize, nint function, nint args, int argCount)
    {
        var functionString = Marshal.PtrToStringUTF8(function);
        var argsString = new string?[argCount];
        for (var i = 0; i < argCount; i++)
            argsString[i] = Marshal.PtrToStringUTF8(Marshal.ReadIntPtr(args + (i * Marshal.SizeOf<nint>())));

        RvExtensionArgsImpl(functionString, argsString);
        Output(output, outputSize, "");
        return 0;
    }

    private static void RvExtensionArgsImpl(string? function, string?[] args)
    {
        try
        {
            if (function == "Connect")
            {
                if (args.Length >= 1 && !string.IsNullOrWhiteSpace(args[0]))
                    _baseUrl = args[0]!.TrimEnd('/');
                return;
            }

            if (function == "UpdatePosition" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 4)
            {
                var posX = double.TryParse(args[0], out var x) ? x : 0;
                var posY = double.TryParse(args[1], out var y) ? y : 0;
                var heading = double.TryParse(args[2], out var h) ? h : (double?)null;
                var callSign = args[3] ?? "Unknown";
                var payload = JsonSerializer.Serialize(new
                {
                    mapId = 1,
                    call_sign = callSign,
                    pos_x = posX,
                    pos_y = posY,
                    heading = heading
                });
                var content = new StringContent(payload, Encoding.UTF8, "application/json");
                _ = HttpClient.PostAsync(_baseUrl + "/api/atak/position", content);
                return;
            }
        }
        catch
        {
            // ignore for now
        }
    }
}
