<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Envío de correo con Microsoft Graph (app con permisos Mail.Send y, desde 2026-10-02,
 * Mail.ReadWrite para mover los enviados a su carpeta; credenciales en el
 * .env: GRAPH_TENANT_ID, GRAPH_CLIENT_ID, GRAPH_CLIENT_SECRET; no van por git).
 * Mismo sistema que Contabilidad/FacturacionPDFyMail/envio_mail.py.
 */
class GraphMail
{
    public static function configurado(): bool
    {
        return (bool) (config('contabilidad.graph.tenant_id') && config('contabilidad.graph.client_id') && config('contabilidad.graph.client_secret'));
    }

    protected static function token(): string
    {
        return Cache::remember('graph_token', 3000, function () {
            $r = Http::asForm()->timeout(30)->post('https://login.microsoftonline.com/'.config('contabilidad.graph.tenant_id').'/oauth2/v2.0/token', [
                'client_id' => config('contabilidad.graph.client_id'),
                'client_secret' => config('contabilidad.graph.client_secret'),
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ]);
            if (! $r->successful() || ! $r->json('access_token')) {
                throw new RuntimeException('No se ha podido conectar con Microsoft (token): '.mb_substr($r->body(), 0, 200));
            }
            return $r->json('access_token');
        });
    }

    /**
     * Pasa el texto a HTML con la firma de Suma: {logo} (o, si no está, justo debajo de la línea
     * «Suma Apoyo Empresarial SL») se cambia por el logo incrustado (cid:logo_suma), como en Outlook.
     */
    public static function html(string $texto): string
    {
        if (! str_contains($texto, '{logo}')) {
            $texto = preg_match('/^Suma Apoyo Empresarial S\.?L\.?\s*$/mi', $texto)
                ? preg_replace('/^(Suma Apoyo Empresarial S\.?L\.?)\s*$/mi', "$1\n{logo}", $texto, 1)
                : $texto."\n\n{logo}";
        }
        $h = nl2br(e($texto), false);
        $h = preg_replace('/www\.sumaempresa\.com/', '<a href="https://www.sumaempresa.com">www.sumaempresa.com</a>', $h, 1);
        $logo = '<img src="cid:logo_suma" alt="Suma Apoyo Empresarial S.L." width="118" height="71" style="display:block;margin-top:6px;border:0;">';
        return '<div style="font-family:Calibri,Arial,sans-serif;font-size:11pt">'.str_replace('{logo}', $logo, $h).'</div>';
    }

    protected static function api(string $metodo, string $url, array $datos = [])
    {
        // en GET no se pasa $datos: un array vacío como «query» borraría el ?$top=… de la URL
        $http = Http::withToken(self::token())->timeout(60);
        $r = $metodo === 'get' ? $http->get('https://graph.microsoft.com/v1.0/'.$url) : $http->{$metodo}('https://graph.microsoft.com/v1.0/'.$url, $datos);
        if (! $r->successful()) {
            throw new RuntimeException('Microsoft Graph (HTTP '.$r->status().'): '.mb_substr($r->body(), 0, 200));
        }
        return $r->json();
    }

    /** Id de la carpeta $ruta (['2026', '___Suma 2026', 'Eric 2026']) del buzón, o null. */
    public static function carpetaId(string $buzon, array $ruta): ?string
    {
        $base = 'users/'.rawurlencode($buzon).'/mailFolders';
        $id = null;
        foreach ($ruta as $nombre) {
            $url = ($id ? $base.'/'.$id.'/childFolders' : $base).'?$top=250&$select=id,displayName';
            $hijos = self::api('get', $url)['value'] ?? [];
            $hit = collect($hijos)->first(fn ($f) => mb_strtolower(trim($f['displayName'])) === mb_strtolower(trim($nombre)));
            if (! $hit) {
                return null;
            }
            $id = $hit['id'];
        }
        return $id;
    }

    /** Nombres (sin el año) de las carpetas de cliente de «<año>\___Suma <año>» del buzón. */
    public static function carpetasCliente(string $buzon, int $anio): array
    {
        return Cache::remember("graph_carpetas_{$buzon}_{$anio}", 600, function () use ($buzon, $anio) {
            $id = self::carpetaId($buzon, [(string) $anio, "___Suma {$anio}"]);
            if (! $id) {
                return [];
            }
            $hijos = self::api('get', 'users/'.rawurlencode($buzon)."/mailFolders/{$id}/childFolders?\$top=250&\$select=displayName")['value'] ?? [];
            return collect($hijos)->pluck('displayName')
                ->map(fn ($n) => trim(preg_replace('/\s+'.$anio.'$/', '', $n)))->sort()->values()->all();
        });
    }

    /**
     * Mueve de Enviados a «<año>\___Suma <año>\<carpeta> <año>» (o «<carpeta>» si no lleva año)
     * el correo con ese asunto enviado desde $desde (pedido 2026-10-02). Reintenta unos segundos
     * porque tras sendMail tarda un poco en aparecer en Enviados. Devuelve la ruta o lanza error.
     */
    public static function moverEnviado(string $buzon, string $asunto, \DateTimeInterface $desde, string $carpeta, int $anio): string
    {
        $ruta = [(string) $anio, "___Suma {$anio}", "{$carpeta} {$anio}"];
        $destino = self::carpetaId($buzon, $ruta);
        if (! $destino) {
            $ruta[2] = $carpeta;
            $destino = self::carpetaId($buzon, $ruta);
        }
        if (! $destino) {
            throw new RuntimeException("no existe la carpeta «{$anio}\\___Suma {$anio}\\{$carpeta} {$anio}» en Outlook");
        }
        $desdeUtc = \Carbon\Carbon::instance(\DateTime::createFromInterface($desde))->utc()->subMinutes(2)->format('Y-m-d\TH:i:s\Z');
        for ($i = 0; $i < 6; $i++) {
            $msgs = self::api('get', 'users/'.rawurlencode($buzon)."/mailFolders/sentitems/messages?\$top=50&\$select=id,subject,sentDateTime"
                ."&\$filter=sentDateTime ge {$desdeUtc}&\$orderby=sentDateTime asc")['value'] ?? [];
            $m = collect($msgs)->first(fn ($x) => trim($x['subject'] ?? '') === trim($asunto));
            if ($m) {
                self::api('post', 'users/'.rawurlencode($buzon)."/messages/{$m['id']}/move", ['destinationId' => $destino]);
                return implode('\\', $ruta);
            }
            sleep(3);
        }
        throw new RuntimeException("no encuentro en Enviados «{$asunto}»");
    }

    /** Envía un correo desde $de (HTML con el logo de Suma). Si GRAPH_REDIRECT está puesto, todo va a esa dirección (pruebas). */
    public static function enviar(string $de, array $para, array $cc, string $asunto, string $texto): void
    {
        if (! self::configurado()) {
            throw new RuntimeException('Faltan las credenciales de Microsoft Graph en el .env de este servidor.');
        }
        if ($redirigir = config('contabilidad.graph.redirect')) {
            $texto = "[PRUEBA: iba para ".implode(', ', $para).($cc ? ' / CC '.implode(', ', $cc) : '')."]\n\n".$texto;
            [$para, $cc] = [[$redirigir], []];
        }
        $dir = fn ($l) => array_map(fn ($a) => ['emailAddress' => ['address' => $a]], array_values($l));
        $r = Http::withToken(self::token())->timeout(60)->post('https://graph.microsoft.com/v1.0/users/'.rawurlencode($de).'/sendMail', [
            'message' => [
                'subject' => $asunto,
                'body' => ['contentType' => 'HTML', 'content' => self::html($texto)],
                'toRecipients' => $dir($para),
                'ccRecipients' => $dir($cc),
                'attachments' => [[
                    '@odata.type' => '#microsoft.graph.fileAttachment',
                    'name' => 'logo_suma.gif',
                    'contentType' => 'image/gif',
                    'contentBytes' => base64_encode(file_get_contents(resource_path('mail/logo_suma.gif'))),
                    'isInline' => true,
                    'contentId' => 'logo_suma',
                ]],
            ],
            'saveToSentItems' => true,
        ]);
        if (! in_array($r->status(), [200, 202], true)) {
            throw new RuntimeException('Microsoft no ha aceptado el correo (HTTP '.$r->status().'): '.mb_substr($r->body(), 0, 200));
        }
    }
}
