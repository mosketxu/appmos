<?php

namespace App\Http\Controllers;

use App\Support\ColaTareas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * API de los PCs trabajadores (X-Token) para la pestaña WhatsApp: el PC lee el chat exportado de OneDrive y manda los mensajes
 * (mensajes), pregunta qué adjuntos faltan (manifest) y sube los que faltan (adjunto). Ver trabajador.py → h_whatsapp y
 * Contabilidad/WhatsApp/whatsapp_chat.py.
 */
class WhatsappSyncController extends Controller
{
    public const OMITIDO = '<Multimedia omitido>';
    public const MAX_ADJUNTO = 26 * 1024 * 1024;

    protected function chat(Request $r, string $clave): object
    {
        abort_unless(ColaTareas::autenticar($r->header('X-Token')), 403);

        return DB::table('whatsapp_chats')->where('clave', $clave)->first() ?? abort(404, 'Chat desconocido');
    }

    /** [{fecha, autor, texto, adjunto, n}] → alta o actualización por huella. Nunca se pierde un adjunto ya conocido por volver a leer un export sin archivos. */
    public function mensajes(Request $r, string $clave)
    {
        $chat = $this->chat($r, $clave);
        $lote = array_values(array_filter((array) $r->input('mensajes', []), fn ($m) => is_array($m) && preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', (string) ($m['fecha'] ?? ''))));
        $huella = fn (array $m) => sha1($m['fecha'].'|'.($m['autor'] ?? '').'|'.(int) ($m['n'] ?? 0));
        $existentes = DB::table('whatsapp_mensajes')->where('chat_id', $chat->id)->whereIn('huella', array_map($huella, $lote))->get(['id', 'huella', 'texto', 'adjunto'])->keyBy('huella');
        $n = ['nuevos' => 0, 'actualizados' => 0, 'iguales' => 0];
        $nuevos = [];
        foreach ($lote as $m) {
            $h = $huella($m);
            $texto = (string) ($m['texto'] ?? '');
            $adjunto = isset($m['adjunto']) && $m['adjunto'] !== '' ? mb_substr((string) $m['adjunto'], 0, 255) : null;
            $e = $existentes->get($h);
            if (! $e) {
                $nuevos[] = ['chat_id' => $chat->id, 'huella' => $h, 'fecha' => $m['fecha'], 'autor' => isset($m['autor']) ? mb_substr((string) $m['autor'], 0, 190) : null,
                    'texto' => $texto, 'adjunto' => $adjunto, 'created_at' => now(), 'updated_at' => now()];
                $n['nuevos']++;
                continue;
            }
            if ($adjunto === null && $texto === self::OMITIDO && $e->adjunto !== null) {
                $n['iguales']++;   // export sin archivos: no se pisa el adjunto que ya teníamos
                continue;
            }
            if ($e->texto === $texto && $e->adjunto === $adjunto) {
                $n['iguales']++;
                continue;
            }
            DB::table('whatsapp_mensajes')->where('id', $e->id)->update(['texto' => $texto, 'adjunto' => $adjunto, 'updated_at' => now()]);
            $n['actualizados']++;
        }
        foreach (array_chunk($nuevos, 200) as $trozo) {
            DB::table('whatsapp_mensajes')->insert($trozo);
        }
        DB::table('whatsapp_chats')->where('id', $chat->id)->update(['importado_at' => now(), 'updated_at' => now()]);

        return response()->json($n + ['ok' => true]);
    }

    /** [{nombre, tam}] → nombres que hay que subir (no están o han cambiado de tamaño). */
    public function manifest(Request $r, string $clave)
    {
        $chat = $this->chat($r, $clave);
        $tengo = DB::table('whatsapp_adjuntos')->where('chat_id', $chat->id)->pluck('tam', 'nombre');
        $subir = [];
        foreach ((array) $r->input('adjuntos', []) as $a) {
            $nombre = (string) ($a['nombre'] ?? '');
            if ($this->nombreValido($nombre) && (! isset($tengo[$nombre]) || (int) $tengo[$nombre] !== (int) ($a['tam'] ?? -1))) {
                $subir[] = $nombre;
            }
        }

        return response()->json(['subir' => $subir]);
    }

    /** Cuerpo = el fichero; nombre en la query. */
    public function adjunto(Request $r, string $clave)
    {
        $chat = $this->chat($r, $clave);
        $nombre = (string) $r->query('nombre');
        abort_unless($this->nombreValido($nombre), 422, 'Nombre no válido');
        $cuerpo = $r->getContent();
        abort_if($cuerpo === '' || strlen($cuerpo) > self::MAX_ADJUNTO, 422, 'Fichero vacío o demasiado grande');
        $almacen = 'whatsapp/'.$chat->id.'/'.$nombre;
        Storage::disk('local')->put($almacen, $cuerpo);
        DB::table('whatsapp_adjuntos')->updateOrInsert(['chat_id' => $chat->id, 'nombre' => $nombre],
            ['tam' => strlen($cuerpo), 'almacen' => $almacen, 'updated_at' => now(), 'created_at' => now()]);

        return response()->json(['ok' => true]);
    }

    /** Un solo nivel de carpeta, sin barras ni «..» ni caracteres de control. */
    public static function nombreValido(string $n): bool
    {
        return $n !== '' && mb_strlen($n) <= 190 && ! preg_match('#[/\\\\\x00-\x1f]#', $n) && ! in_array($n, ['.', '..'], true);
    }
}
