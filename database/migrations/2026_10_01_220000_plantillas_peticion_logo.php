<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Firma de las plantillas de Pet. Documentación como la de Outlook: web y logo de Suma ({logo}). */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('plantillas_mail')->where('proceso', 'petdocimpuestos')->get() as $p) {
            if (str_contains((string) $p->texto, '{logo}')) {
                continue;
            }
            $texto = preg_replace('/^Suma Apoyo Empresarial SL$/m', "Suma Apoyo Empresarial SL\nwww.sumaempresa.com\n{logo}", (string) $p->texto, 1);
            DB::table('plantillas_mail')->where('id', $p->id)->update(['texto' => $texto, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
    }
};
