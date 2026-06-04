using System;
using System.Net;
using System.Net.Http;
using System.Text;
using System.Threading.Tasks;

class Program
{
    static async Task Main()
    {
        string ip = "192.168.0.184";
        string usuario = "admin";
        string password = "H.246810";

        string url = $"http://{ip}/ISAPI/AccessControl/AcsEvent?format=json";

        string json = @"{
  ""AcsEventCond"": {
    ""searchID"": ""1"",
    ""searchResultPosition"": 0,
    ""maxResults"": 100,
    ""major"": 5,
    ""minor"": 38,
    ""startTime"": ""2026-06-03T00:00:00-06:00"",
    ""endTime"": ""2026-06-03T23:59:59-06:00""
  }
}";

        var cache = new CredentialCache();
        cache.Add(
            new Uri($"http://{ip}"),
            "Digest",
            new NetworkCredential(usuario, password)
        );

        var handler = new HttpClientHandler
        {
            Credentials = cache,
            PreAuthenticate = true
        };

        using var client = new HttpClient(handler);

        var content = new StringContent(
            json,
            Encoding.UTF8,
            "application/json"
        );

        try
        {
            var response = await client.PostAsync(url, content);

            string resultado = await response.Content.ReadAsStringAsync();

            Console.WriteLine($"Status: {response.StatusCode}");
            Console.WriteLine(resultado);
        }
        catch (Exception ex)
        {
            Console.WriteLine(ex.ToString());
        }
    }
}