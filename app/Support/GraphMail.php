<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Envío de correo con Microsoft Graph (app con permiso Mail.Send, credenciales en el
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
