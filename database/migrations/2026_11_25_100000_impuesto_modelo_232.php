<?php

use App\Support\Impuestos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Modelo 232 (operaciones vinculadas), 25-nov-2026, pedido de Alex: anual, se presenta en noviembre del año siguiente
 * (232 de 2025 → noviembre 2026, como el 200: desfase 1, mes 11). Se da de alta a TODAS las entidades activas (estado 1, cliente 1)
 * y en noviembre 2026 queda «no se presenta» salvo Yinmal, Durcal, Buxdel y Fashion IQ; del 2027 en adelante sale pendiente para todas.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ahora = now();
        $modelo = DB::table('impuesto_modelos')->where('codigo', '232')->first();
        if (! $modelo) {
            DB::table('impuesto_modelos')->insert([
                'codigo' => '232', 'nombre' => 'Operaciones vinculadas', 'periodicidad' => 'A', 'desfase' => 1, 'mes_anual' => 11,
                'automatico' => 0, 'orden' => 125, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
            $modelo = DB::table('impuesto_modelos')->where('codigo', '232')->first();
        }

        $activas = DB::table('entidades')->where('estado', 1)->where('cliente', 1)->whereNull('deleted_at')->get(['id', 'entidad', 'alias']);
        foreach ($activas as $e) {
            DB::table('entidad_impuestos')->insertOrIgnore([
                'entidad_id' => $e->id, 'modelo_id' => $modelo->id, 'periodicidad' => 'A', 'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
        }

        // Crea las casillas (pendiente) de los ejercicios con datos + 2027
        foreach (array_unique(array_merge(Impuestos::ejercicios(), [2027])) as $ej) {
            Impuestos::asegurarEjercicio((int) $ej);
        }

        // Noviembre 2026 (ejercicio 2026, periodo A): no se presenta salvo Yinmal, Durcal, Buxdel y Fashion IQ
        $presentan = $activas->filter(fn ($e) => preg_match('/yinmal|durcal|buxdel|fashion\s*iq/i', $e->entidad.' '.$e->alias))->pluck('id')->all();
        $noPresentan = DB::table('entidad_impuestos')->where('modelo_id', $modelo->id)->whereIn('entidad_id', $activas->pluck('id'))
            ->whereNotIn('entidad_id', $presentan)->pluck('id');
        DB::table('impuesto_estados')->where('ejercicio', 2026)->where('periodo', 'A')->where('estado', 'pendiente')
            ->whereIn('entidad_impuesto_id', $noPresentan)->update(['estado' => 'nopresenta', 'updated_at' => $ahora]);
    }

    public function down(): void
    {
        $id = DB::table('impuesto_modelos')->where('codigo', '232')->value('id');
        if ($id) {
            DB::table('entidad_impuestos')->where('modelo_id', $id)->delete();   // cascada a impuesto_estados
            DB::table('impuesto_modelos')->where('id', $id)->delete();
        }
    }
};
