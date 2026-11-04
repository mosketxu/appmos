<?php

namespace App\Console\Commands;

use App\Models\TodoAviso;
use App\Models\TodoTarea;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Tarea de TO-DO de principios de mes para los informes de Balerga (4-nov-2026): "sube el mayor y el sumas y saldos del mes anterior"
 * (Proc.Mensuales → Informes de Balerga). La lanza el cron del VPS el día 1 (/etc/cron.d/todo-informes-balerga; fichero en
 * Contabilidad/TrabajadorWeb/todo-informes-balerga.cron). No duplica: si ya hay una tarea con el mismo título, no hace nada.
 * `--mes=AAAA-MM` fuerza el mes que se informa; `--simula` solo dice qué haría.
 */
class TodoInformesBalerga extends Command
{
    protected $signature = 'todo:informes-balerga {--mes= : AAAA-MM del mes que se informa (por defecto el anterior)} {--simula}';

    protected $description = 'Crea en el TO-DO de Alex la tarea mensual de subir los ficheros de SAGE de Balerga para el informe de Albena';

    public function handle(): int
    {
        $mes = $this->option('mes') ?: now()->subMonthNoOverflow()->format('Y-m');
        if (! preg_match('/^(\d{4})-(\d{2})$/', $mes, $m) || (int) $m[2] < 1 || (int) $m[2] > 12) {
            $this->error('Mes no válido (AAAA-MM).');

            return 1;
        }
        $nombre = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'][(int) $m[2] - 1];
        $trim = (int) $m[2] % 3 === 0 ? intdiv((int) $m[2], 3) : null;
        $titulo = "Balerga: subir mayor y sumas y saldos de {$nombre} {$m[1]} (informe para Albena)";
        if (TodoTarea::where('titulo', $titulo)->exists()) {
            $this->info('Ya existe: '.$titulo);

            return 0;
        }
        $alex = User::where('email', 'alex.arregui@sumaempresa.com')->first();
        if (! $alex) {
            $this->error('No encuentro al usuario de Alex.');

            return 1;
        }
        $texto = "Proc.Mensuales → Informes de Balerga, mes {$mes}. Sube el Excel de SAGE de Balerga Digital SLU: 1) Mayor de todas las cuentas (incluidos bancos) del 1 de enero al último día de {$nombre}; "
            ."2) Sumas y saldos acumulado a fin de {$nombre}".($trim ? "; 3) Pérdidas y ganancias del año hasta {$nombre} (cierre del {$trim}.º trimestre: toca también el informe trimestral)" : '')
            .'. El balance de situación es opcional. Luego pide a Claude el informe del mes (resumen de alquileres facturados, cobrados y pendientes, y principales gastos) y revisa los recibos de la comunidad.';
        if ($this->option('simula')) {
            $this->line($titulo."\n".$texto);

            return 0;
        }
        $t = TodoTarea::create(['titulo' => $titulo, 'descripcion' => $texto, 'creador_id' => $alex->id, 'estado' => 'pendiente', 'prioridad' => 'normal',
            'fecha_limite' => now()->startOfMonth()->addDays(4)]);
        $t->asignados()->attach($alex->id, ['orden' => TodoTarea::siguienteOrden($alex->id)]);
        TodoAviso::create(['user_id' => $alex->id, 'tarea_id' => $t->id, 'origen_id' => $alex->id, 'texto' => 'Nueva tarea mensual: '.$titulo]);
        $this->info('Creada: '.$titulo);

        return 0;
    }
}
