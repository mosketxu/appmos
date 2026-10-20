<?php

namespace App\Http\Livewire\Contabilidad;

use App\Support\ColaTareas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * WhatsApp (20-oct-2026): conversaciones de clientes exportadas de WhatsApp y guardadas en Appmos, con buscador y adjuntos.
 * Alex deja el .zip exportado en OneDrive/_ClaudeDesarrollo/WhatsApp/<carpeta> y «Importar ahora» pide a un PC trabajador
 * (pc.whatsapp) que lo lea y suba solo lo nuevo. Un chat por cliente (desplegable). Guía: Contabilidad/WhatsApp/PLAN.md.
 */
class WhatsApp extends Component
{
    public string $chat = '';
    public string $buscar = '';
    public int $limite = 200;
    public string $nuevoNombre = '';
    public string $aviso = '';

    public function hydrate(): void
    {
        $this->autorizar();
    }

    protected function autorizar(): void
    {
        abort_unless(auth()->user()?->can('contabilidad.whatsapp'), 403);
    }

    public function mount(): void
    {
        $this->autorizar();
        $claves = DB::table('whatsapp_chats')->orderBy('nombre')->pluck('clave')->all();
        $guardado = (string) session('whatsapp.chat', '');
        $this->chat = in_array($guardado, $claves, true) ? $guardado : ($claves[0] ?? '');
    }

    public function updatedChat(): void
    {
        session(['whatsapp.chat' => $this->chat]);
        $this->limite = 200;
        $this->buscar = '';
    }

    public function updatedBuscar(): void
    {
        $this->limite = 200;
    }

    public function masAntiguos(): void
    {
        $this->limite += 300;
    }

    public function importar(): void
    {
        $chat = DB::table('whatsapp_chats')->where('clave', $this->chat)->first();
        if (! $chat) {
            return;
        }
        if (! $this->colaLista()) {
            $this->aviso = '⚠️ Falta hacer la migración de la cola de tareas en este servidor.';

            return;
        }
        if (DB::table('tareas')->where('proceso', 'pc.whatsapp')->whereIn('estado', ['pendiente', 'en_curso'])->exists()) {
            $this->aviso = 'Ya hay una importación en marcha.';

            return;
        }
        ColaTareas::crear('pc.whatsapp', ['clave' => $chat->clave, 'carpeta' => $chat->carpeta], ColaTareas::pcElegido() ?: null, auth()->id());
        $this->aviso = 'Pedido a un PC: leerá el chat exportado de OneDrive y subirá lo nuevo (suele tardar menos de un minuto).';
    }

    public function crearChat(): void
    {
        $nombre = trim(preg_replace('/\s+/', ' ', $this->nuevoNombre));
        if ($nombre === '' || mb_strlen($nombre) > 60) {
            $this->aviso = 'Escribe el nombre del cliente (hasta 60 letras).';

            return;
        }
        $clave = Str::slug($nombre);
        $carpeta = trim(preg_replace('/[^A-Za-z0-9 _.-]/', '', Str::ascii($nombre)));
        if ($clave === '' || $carpeta === '') {
            $this->aviso = 'Ese nombre no vale: usa letras o números.';

            return;
        }
        if (DB::table('whatsapp_chats')->where('clave', $clave)->exists()) {
            $this->aviso = 'Ya existe un chat con ese nombre.';
            $this->chat = $clave;

            return;
        }
        DB::table('whatsapp_chats')->insert(['clave' => $clave, 'nombre' => $nombre, 'carpeta' => $carpeta, 'yo' => 'Alexander Arregui', 'created_at' => now(), 'updated_at' => now()]);
        $this->chat = $clave;
        session(['whatsapp.chat' => $clave]);
        $this->nuevoNombre = '';
        $this->aviso = "Chat «{$nombre}» creado. Exporta el chat desde el móvil y deja el .zip en OneDrive\\_ClaudeDesarrollo\\WhatsApp\\{$carpeta}; luego pulsa «Importar ahora».";
    }

    protected function colaLista(): bool
    {
        return Schema::hasTable('tareas') && Schema::hasTable('trabajadores');
    }

    public function render()
    {
        $chats = DB::table('whatsapp_chats')->orderBy('nombre')->get();
        $actual = $chats->firstWhere('clave', $this->chat);
        $mensajes = collect();
        $total = 0;
        $hay = false;
        $adjuntos = [];
        $tarea = null;
        if ($actual) {
            $q = DB::table('whatsapp_mensajes')->where('chat_id', $actual->id);
            $busca = trim($this->buscar);
            if ($busca !== '') {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $busca).'%';
                $q->where(fn ($w) => $w->where('texto', 'like', $like)->orWhere('adjunto', 'like', $like)->orWhere('fecha', 'like', $like));
            }
            $total = (clone $q)->count();
            $mensajes = $q->orderByDesc('fecha')->orderByDesc('id')->limit($this->limite)->get()->reverse()->values();
            $hay = $total > $mensajes->count();
            $adjuntos = DB::table('whatsapp_adjuntos')->where('chat_id', $actual->id)->whereIn('nombre', $mensajes->pluck('adjunto')->filter()->unique()->all())->pluck('tam', 'nombre')->all();
            $tarea = DB::table('tareas')->where('proceso', 'pc.whatsapp')->where('parametros', 'like', '%"clave":"'.$actual->clave.'"%')
                ->orderByDesc('id')->first(['id', 'estado', 'resultado', 'terminada_at', 'created_at']);
        }

        return view('livewire.contabilidad.whatsapp', compact('chats', 'actual', 'mensajes', 'total', 'hay', 'adjuntos', 'tarea'));
    }
}
