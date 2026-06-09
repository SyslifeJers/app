using System.Globalization;
using System.Net;
using System.Text;
using System.Text.Json;
using System.Text.Json.Serialization;

internal sealed class Program
{
    private const int MaxResults = 100;

    private static readonly JsonSerializerOptions JsonOptions = new()
    {
        PropertyNamingPolicy = JsonNamingPolicy.CamelCase,
        DefaultIgnoreCondition = JsonIgnoreCondition.WhenWritingNull,
        WriteIndented = true
    };

    private static async Task<int> Main()
    {
        var configuracion = Configuracion.Cargar();
        Directory.CreateDirectory(configuracion.CarpetaPendientes);
        Directory.CreateDirectory(configuracion.CarpetaEnviados);

        try
        {
            var eventos = await ObtenerEventosAsync(configuracion);
            if (eventos.Count > 0)
            {
                var paquete = new PaqueteEventos(
                    DateTime.Now.ToString("yyyy-MM-ddTHH:mm:ss", CultureInfo.InvariantCulture),
                    configuracion.Equipo,
                    configuracion.Sucursal,
                    eventos);

                var archivo = Path.Combine(configuracion.CarpetaPendientes, $"eventos_{DateTime.Now:yyyyMMdd_HHmmss}.json");
                await File.WriteAllTextAsync(archivo, JsonSerializer.Serialize(paquete, JsonOptions), Encoding.UTF8);
                Console.WriteLine($"Eventos validos guardados: {eventos.Count} ({archivo})");
            }
            else
            {
                Console.WriteLine("No se encontraron eventos validos para guardar.");
            }

            await EnviarPendientesAsync(configuracion);
            return 0;
        }
        catch (Exception ex)
        {
            Console.Error.WriteLine(ex);
            return 1;
        }
    }

    private static async Task<List<EventoChecador>> ObtenerEventosAsync(Configuracion configuracion)
    {
        using var client = CrearClienteHikvision(configuracion);
        var eventos = new List<EventoChecador>();
        var seriales = new HashSet<int>();
        var posicion = 0;
        var searchId = Guid.NewGuid().ToString("N");

        while (true)
        {
            var solicitud = CrearSolicitudEventos(searchId, posicion, configuracion.Inicio, configuracion.Fin);
            using var content = new StringContent(solicitud, Encoding.UTF8, "application/json");
            using var response = await client.PostAsync(configuracion.UrlEventos, content);
            var json = await response.Content.ReadAsStringAsync();

            if (!response.IsSuccessStatusCode)
            {
                throw new InvalidOperationException($"Error consultando checador: {(int)response.StatusCode} {response.ReasonPhrase}\n{json}");
            }

            var pagina = ExtraerEventos(json, seriales);
            eventos.AddRange(pagina.Eventos);

            if (pagina.RegistrosRecibidos < MaxResults)
            {
                break;
            }

            posicion += MaxResults;
        }

        return eventos.OrderBy(e => e.FechaHora).ThenBy(e => e.SerialNo).ToList();
    }

    private static HttpClient CrearClienteHikvision(Configuracion configuracion)
    {
        var cache = new CredentialCache();
        cache.Add(
            new Uri($"http://{configuracion.Ip}"),
            "Digest",
            new NetworkCredential(configuracion.Usuario, configuracion.Password));

        var handler = new HttpClientHandler
        {
            Credentials = cache,
            PreAuthenticate = true
        };

        return new HttpClient(handler) { Timeout = TimeSpan.FromSeconds(60) };
    }

    private static string CrearSolicitudEventos(string searchId, int posicion, DateTimeOffset inicio, DateTimeOffset fin)
    {
        var payload = new
        {
            AcsEventCond = new
            {
                searchID = searchId,
                searchResultPosition = posicion,
                maxResults = MaxResults,
                major = 5,
                minor = 38,
                startTime = inicio.ToString("yyyy-MM-ddTHH:mm:sszzz", CultureInfo.InvariantCulture),
                endTime = fin.ToString("yyyy-MM-ddTHH:mm:sszzz", CultureInfo.InvariantCulture)
            }
        };

        return JsonSerializer.Serialize(payload);
    }

    private static PaginaEventos ExtraerEventos(string json, HashSet<int> seriales)
    {
        using var document = JsonDocument.Parse(json);
        var root = document.RootElement;
        if (!root.TryGetProperty("AcsEvent", out var acsEvent) || !acsEvent.TryGetProperty("InfoList", out var infoList) || infoList.ValueKind != JsonValueKind.Array)
        {
            return new PaginaEventos([], 0);
        }

        var eventos = new List<EventoChecador>();
        var recibidos = 0;
        foreach (var item in infoList.EnumerateArray())
        {
            recibidos++;
            var serialNo = ObtenerInt(item, "serialNo");
            var empleado = ObtenerString(item, "employeeNoString") ?? ObtenerString(item, "employeeNo");
            var fechaHora = ObtenerString(item, "time");

            if (serialNo is null or <= 0 || string.IsNullOrWhiteSpace(empleado) || string.IsNullOrWhiteSpace(fechaHora))
            {
                continue;
            }
            if (!seriales.Add(serialNo.Value))
            {
                continue;
            }

            eventos.Add(new EventoChecador(
                serialNo.Value,
                empleado.Trim(),
                ObtenerString(item, "name"),
                fechaHora.Trim(),
                ObtenerInt(item, "doorNo")));
        }

        return new PaginaEventos(eventos, recibidos);
    }

    private static async Task EnviarPendientesAsync(Configuracion configuracion)
    {
        var archivos = Directory.GetFiles(configuracion.CarpetaPendientes, "*.json").OrderBy(x => x).ToArray();
        if (archivos.Length == 0)
        {
            Console.WriteLine("No hay archivos pendientes por enviar.");
            return;
        }

        using var client = new HttpClient { Timeout = TimeSpan.FromSeconds(60) };
        foreach (var archivo in archivos)
        {
            var json = await File.ReadAllTextAsync(archivo, Encoding.UTF8);
            using var content = new StringContent(json, Encoding.UTF8, "application/json");
            using var response = await client.PostAsync(configuracion.UrlApi, content);
            var respuesta = await response.Content.ReadAsStringAsync();

            if (!response.IsSuccessStatusCode)
            {
                Console.WriteLine($"No se pudo enviar {Path.GetFileName(archivo)}: {(int)response.StatusCode} {respuesta}");
                continue;
            }

            var destino = Path.Combine(configuracion.CarpetaEnviados, Path.GetFileName(archivo));
            if (File.Exists(destino))
            {
                destino = Path.Combine(configuracion.CarpetaEnviados, $"{Path.GetFileNameWithoutExtension(archivo)}_{DateTime.Now:HHmmss}{Path.GetExtension(archivo)}");
            }

            File.Move(archivo, destino);
            Console.WriteLine($"Enviado {Path.GetFileName(archivo)}: {respuesta}");
        }
    }

    private static string? ObtenerString(JsonElement item, string propiedad)
    {
        if (!item.TryGetProperty(propiedad, out var valor) || valor.ValueKind is JsonValueKind.Null or JsonValueKind.Undefined)
        {
            return null;
        }

        return valor.ValueKind == JsonValueKind.String ? valor.GetString() : valor.ToString();
    }

    private static int? ObtenerInt(JsonElement item, string propiedad)
    {
        if (!item.TryGetProperty(propiedad, out var valor) || valor.ValueKind is JsonValueKind.Null or JsonValueKind.Undefined)
        {
            return null;
        }
        if (valor.ValueKind == JsonValueKind.Number && valor.TryGetInt32(out var numero))
        {
            return numero;
        }
        if (valor.ValueKind == JsonValueKind.String && int.TryParse(valor.GetString(), NumberStyles.Integer, CultureInfo.InvariantCulture, out numero))
        {
            return numero;
        }

        return null;
    }
}

internal sealed record EventoChecador(
    int SerialNo,
    string Empleado,
    string? Nombre,
    string FechaHora,
    int? DoorNo);

internal sealed record PaqueteEventos(
    string FechaSincronizacion,
    string Equipo,
    string Sucursal,
    IReadOnlyList<EventoChecador> Eventos);

internal sealed record PaginaEventos(
    IReadOnlyList<EventoChecador> Eventos,
    int RegistrosRecibidos);

internal sealed class Configuracion
{
    public required string Ip { get; init; }
    public required string Usuario { get; init; }
    public required string Password { get; init; }
    public required string Equipo { get; init; }
    public required string Sucursal { get; init; }
    public required Uri UrlEventos { get; init; }
    public required Uri UrlApi { get; init; }
    public required string CarpetaPendientes { get; init; }
    public required string CarpetaEnviados { get; init; }
    public required DateTimeOffset Inicio { get; init; }
    public required DateTimeOffset Fin { get; init; }

    public static Configuracion Cargar()
    {
        var hoy = DateTimeOffset.Now;
        var inicioDefault = new DateTimeOffset(hoy.Year, hoy.Month, hoy.Day, 0, 0, 0, hoy.Offset);
        var finDefault = new DateTimeOffset(hoy.Year, hoy.Month, hoy.Day, 23, 59, 59, hoy.Offset);
        var basePath = AppContext.BaseDirectory;
        var accesos = Accesos.Cargar(Path.Combine(basePath, "accesos.json"));
        var ip = Leer("HIKVISION_IP", accesos.Ip);
        var inicio = DateTimeOffset.Parse(Leer("CHECADOR_INICIO", inicioDefault.ToString("yyyy-MM-ddTHH:mm:sszzz", CultureInfo.InvariantCulture)), CultureInfo.InvariantCulture);
        var fin = DateTimeOffset.Parse(Leer("CHECADOR_FIN", finDefault.ToString("yyyy-MM-ddTHH:mm:sszzz", CultureInfo.InvariantCulture)), CultureInfo.InvariantCulture);

        return new Configuracion
        {
            Ip = ip,
            Usuario = Leer("HIKVISION_USER", accesos.Usuario),
            Password = Leer("HIKVISION_PASSWORD", accesos.Password),
            Equipo = Leer("CHECADOR_EQUIPO", accesos.Equipo),
            Sucursal = Leer("CHECADOR_SUCURSAL", accesos.Sucursal),
            UrlEventos = new Uri($"http://{ip}/ISAPI/AccessControl/AcsEvent?format=json"),
            UrlApi = new Uri(Leer("CHECADOR_API_URL", accesos.UrlApi)),
            CarpetaPendientes = Path.Combine(basePath, "logs", "pendientes"),
            CarpetaEnviados = Path.Combine(basePath, "logs", "enviados"),
            Inicio = inicio,
            Fin = fin
        };
    }

    private static string Leer(string nombre, string valorDefault)
    {
        var valor = Environment.GetEnvironmentVariable(nombre);
        return string.IsNullOrWhiteSpace(valor) ? valorDefault : valor.Trim();
    }
}

internal sealed class Accesos
{
    public string Ip { get; set; } = "192.168.0.184";
    public string Usuario { get; set; } = "admin";
    public string Password { get; set; } = "H.246810";
    public string Equipo { get; set; } = "DS-K1T320MFWX-B";
    public string Sucursal { get; set; } = "Puebla Centro";
    public string UrlApi { get; set; } = "https://app.clinicacerene.com/api/checador/subir_log.php";

    public static Accesos Cargar(string archivo)
    {
        var accesos = new Accesos();
        if (!File.Exists(archivo))
        {
            File.WriteAllText(archivo, JsonSerializer.Serialize(accesos, ProgramJsonOptions.Value), Encoding.UTF8);
            return accesos;
        }

        var json = File.ReadAllText(archivo, Encoding.UTF8);
        return JsonSerializer.Deserialize<Accesos>(json, ProgramJsonOptions.Value) ?? accesos;
    }
}

internal static class ProgramJsonOptions
{
    public static readonly JsonSerializerOptions Value = new()
    {
        PropertyNamingPolicy = JsonNamingPolicy.CamelCase,
        WriteIndented = true
    };
}
