using System;
using System.Net.Http;
using System.Text;
using System.Threading.Tasks;
using System.Runtime.InteropServices;
using Newtonsoft.Json;

namespace COMSPECExtension
{
    /// <summary>
    /// Extension native COMSPEC pour Arma 3
    /// Point d'entrée DLL appelé par callExtension
    /// </summary>
    public class ExtensionMain
    {
        private static readonly HttpClient httpClient = new HttpClient();
        private static string apiBaseUrl = "";
        private static string atakToken = "";
        
        /// <summary>
        /// Point d'entrée RVExtension appelé par Arma 3
        /// </summary>
        [DllExport("RVExtension", CallingConvention = CallingConvention.Winapi)]
        public static void RVExtension(StringBuilder output, int outputSize, string function)
        {
            try
            {
                var result = ProcessCommand(function, "");
                output.Append(result);
            }
            catch (Exception ex)
            {
                output.Append($"ERROR:{ex.Message}");
            }
        }
        
        /// <summary>
        /// Point d'entrée RVExtensionArgs pour commandes avec arguments
        /// </summary>
        [DllExport("RVExtensionArgs", CallingConvention = CallingConvention.Winapi)]
        public static int RVExtensionArgs(StringBuilder output, int outputSize, string function, string[] args, int argCount)
        {
            try
            {
                string jsonArg = argCount > 0 ? args[0] : "";
                var result = ProcessCommand(function, jsonArg);
                output.Append(result);
                return 0;
            }
            catch (Exception ex)
            {
                output.Append($"ERROR:{ex.Message}");
                return 1;
            }
        }
        
        /// <summary>
        /// Routeur de commandes principal
        /// </summary>
        private static string ProcessCommand(string command, string jsonData)
        {
            switch (command)
            {
                case "GetVersion":
                    return JsonConvert.SerializeObject(new[] { "2.0", "COMSPEC Extension ATAK" });
                
                case "Connect":
                    return Connect(jsonData);
                
                case "SubmitTacticalReport":
                    return SubmitTacticalReport(jsonData);
                
                case "CreatePOI":
                    return CreatePOI(jsonData);
                
                case "RequestMEDEVAC":
                    return RequestMEDEVAC(jsonData);
                
                case "RequestQRF":
                    return RequestQRF(jsonData);
                
                case "UpdateVehicleTracking":
                    return UpdateVehicleTracking(jsonData);
                
                case "RequestVehicleService":
                    return RequestVehicleService(jsonData);
                
                default:
                    return JsonConvert.SerializeObject(new[] { "ERROR", $"Unknown command: {command}" });
            }
        }
        
        /// <summary>
        /// Initialisation connexion API
        /// </summary>
        private static string Connect(string jsonData)
        {
            try
            {
                var config = JsonConvert.DeserializeObject<dynamic>(jsonData);
                apiBaseUrl = config.api_url;
                atakToken = config.token;
                
                httpClient.DefaultRequestHeaders.Clear();
                httpClient.DefaultRequestHeaders.Add("X-ATAK-Token", atakToken);
                httpClient.DefaultRequestHeaders.Add("User-Agent", "COMSPEC-Extension/2.0");
                httpClient.Timeout = TimeSpan.FromSeconds(10);
                
                return JsonConvert.SerializeObject(new[] { "OK", "Connected" });
            }
            catch (Exception ex)
            {
                return JsonConvert.SerializeObject(new[] { "ERROR", ex.Message });
            }
        }
        
        /// <summary>
        /// Soumettre rapport tactique
        /// </summary>
        private static string SubmitTacticalReport(string jsonData)
        {
            return SendHttpRequest("POST", "/api/atak/reports", jsonData).Result;
        }
        
        /// <summary>
        /// Créer Point of Interest
        /// </summary>
        private static string CreatePOI(string jsonData)
        {
            return SendHttpRequest("POST", "/api/atak/poi", jsonData).Result;
        }
        
        /// <summary>
        /// Demander MEDEVAC
        /// </summary>
        private static string RequestMEDEVAC(string jsonData)
        {
            return SendHttpRequest("POST", "/api/atak/medevac", jsonData).Result;
        }
        
        /// <summary>
        /// Demander QRF
        /// </summary>
        private static string RequestQRF(string jsonData)
        {
            return SendHttpRequest("POST", "/api/atak/qrf", jsonData).Result;
        }
        
        /// <summary>
        /// Update tracking véhicule
        /// </summary>
        private static string UpdateVehicleTracking(string jsonData)
        {
            return SendHttpRequest("POST", "/api/atak/vehicles", jsonData).Result;
        }
        
        /// <summary>
        /// Demander service véhicule
        /// </summary>
        private static string RequestVehicleService(string jsonData)
        {
            // NOTE: Nécessite vehicle_id - peut nécessiter lookup préalable
            return SendHttpRequest("POST", "/api/atak/vehicles/service", jsonData).Result;
        }
        
        /// <summary>
        /// Helper générique pour requêtes HTTP
        /// </summary>
        private static async Task<string> SendHttpRequest(string method, string endpoint, string jsonData)
        {
            try
            {
                HttpResponseMessage response;
                var content = new StringContent(jsonData, Encoding.UTF8, "application/json");
                var url = $"{apiBaseUrl}{endpoint}";
                
                if (method == "POST")
                    response = await httpClient.PostAsync(url, content);
                else if (method == "PUT")
                    response = await httpClient.PutAsync(url, content);
                else if (method == "PATCH")
                {
                    var request = new HttpRequestMessage(new HttpMethod("PATCH"), url) { Content = content };
                    response = await httpClient.SendAsync(request);
                }
                else
                    response = await httpClient.GetAsync(url);
                
                if (response.IsSuccessStatusCode)
                {
                    var responseBody = await response.Content.ReadAsStringAsync();
                    return JsonConvert.SerializeObject(new[] { "OK", responseBody });
                }
                else
                {
                    var error = await response.Content.ReadAsStringAsync();
                    return JsonConvert.SerializeObject(new[] { "ERROR", $"HTTP {(int)response.StatusCode}: {error}" });
                }
            }
            catch (TaskCanceledException)
            {
                return JsonConvert.SerializeObject(new[] { "TIMEOUT", "Request timeout" });
            }
            catch (HttpRequestException ex)
            {
                return JsonConvert.SerializeObject(new[] { "NETWORK_ERROR", ex.Message });
            }
            catch (Exception ex)
            {
                return JsonConvert.SerializeObject(new[] { "ERROR", ex.Message });
            }
        }
    }
}
