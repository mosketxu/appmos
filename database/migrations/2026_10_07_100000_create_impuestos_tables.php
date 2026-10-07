<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Impuestos (7-oct-2026): pestaña «Impuestos» del TO-DO. Qué impuestos presenta cada cliente (y cada cuánto),
 * el estado de cada periodo (pendiente → revisión → revisado → presentado) y los PDF de cada uno.
 *  - impuesto_modelos:    catálogo (303, 111, 115, 202, 349, 200...). Se pueden añadir más desde Entidades.
 *  - entidad_impuestos:   qué modelo presenta cada entidad y con qué periodicidad (M mensual, T trimestral, A anual, P pagos fraccionados 202).
 *  - impuesto_estados:    una fila por obligación, ejercicio y periodo. Sin fila = no existe ese periodo; estado «no» = no tiene que presentarlo.
 *  - impuesto_documentos: PDF (los de OneDrive los sube un PC trabajador; los borradores, el usuario).
 *  - impuesto_alias:      nombre con el que aparece la entidad en los nombres de fichero de OneDrive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impuesto_modelos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique();
            $table->string('nombre', 120);
            $table->char('periodicidad', 1)->default('T');       // M, T, A, P
            $table->unsignedTinyInteger('desfase')->default(0);  // 1: el ejercicio del seguimiento es el año siguiente al del impuesto (IS 2025 se ve en 2026)
            $table->unsignedTinyInteger('mes_anual')->nullable(); // mes en que se ve un impuesto anual en la vista mensual
            $table->boolean('automatico')->default(false);       // Appmos lo prepara (hoy: 200 en la pestaña IS)
            $table->unsignedSmallInteger('orden')->default(100);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('entidad_impuestos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entidad_id');
            $table->unsignedBigInteger('modelo_id');
            $table->char('periodicidad', 1)->default('T');
            $table->unsignedBigInteger('user_id')->nullable();   // responsable de este impuesto; sin él, los de la entidad (Rpble. Suma y co-responsables)
            $table->string('observaciones', 255)->nullable();
            $table->timestamps();
            $table->unique(['entidad_id', 'modelo_id']);
            $table->foreign('entidad_id')->references('id')->on('entidades')->cascadeOnDelete();
            $table->foreign('modelo_id')->references('id')->on('impuesto_modelos')->cascadeOnDelete();
        });

        Schema::create('impuesto_estados', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entidad_impuesto_id');
            $table->unsignedSmallInteger('ejercicio');
            $table->string('periodo', 3);                         // 01..12, T1..T4, A, P1..P3
            $table->string('estado', 12)->default('pendiente');   // no | pendiente | revision | revisado | presentado
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->unique(['entidad_impuesto_id', 'ejercicio', 'periodo'], 'impuesto_estados_unico');
            $table->foreign('entidad_impuesto_id')->references('id')->on('entidad_impuestos')->cascadeOnDelete();
        });

        Schema::create('impuesto_documentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entidad_id')->nullable()->index();   // null = sin asignar a ninguna entidad
            $table->string('modelo', 10)->nullable();
            $table->unsignedSmallInteger('ejercicio')->nullable();
            $table->string('periodo', 3)->nullable();
            $table->string('tipo', 12)->default('presentado');    // presentado | borrador | otro (justificantes, aplazamientos...)
            $table->string('nombre', 255);
            $table->string('cliente_texto', 255)->nullable();     // lo que había en el nombre del fichero antes del modelo
            $table->string('ruta_origen', 700)->nullable()->unique();   // ruta relativa en OneDrive (null si lo subió un usuario)
            $table->string('almacen', 255);                       // ruta dentro de storage/app
            $table->unsignedBigInteger('tam')->default(0);
            $table->unsignedBigInteger('mtime')->default(0);
            $table->string('sha256', 64)->nullable();
            $table->string('origen', 10)->default('onedrive');    // onedrive | web
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->index(['entidad_id', 'modelo', 'ejercicio', 'periodo'], 'impuesto_docs_celda');
        });

        Schema::create('impuesto_alias', function (Blueprint $table) {
            $table->id();
            $table->string('alias', 190)->unique();   // normalizado (minúsculas, sin acentos ni S.L.)
            $table->unsignedBigInteger('entidad_id');
            $table->timestamps();
            $table->foreign('entidad_id')->references('id')->on('entidades')->cascadeOnDelete();
        });

        // Catálogo inicial: [código, nombre, periodicidad, desfase, mes en la vista mensual (anuales), automático, orden]
        $ahora = now();
        $modelos = [
            ['303', 'IVA', 'T', 0, null, 0, 10],
            ['111', 'Retenciones trabajo y profesionales', 'T', 0, null, 0, 20],
            ['115', 'Retenciones alquileres', 'T', 0, null, 0, 30],
            ['123', 'Retenciones capital mobiliario', 'T', 0, null, 0, 40],
            ['130', 'Pago fraccionado IRPF', 'T', 0, null, 0, 50],
            ['202', 'Pago fraccionado IS', 'P', 0, null, 0, 60],
            ['210', 'IRNR (no residentes)', 'T', 0, null, 0, 70],
            ['216', 'Retenciones no residentes', 'T', 0, null, 0, 80],
            ['349', 'Operaciones intracomunitarias', 'T', 0, null, 0, 90],
            ['200', 'Impuesto sobre Sociedades', 'A', 1, 7, 1, 100],
            ['D2', 'Depósito de cuentas', 'A', 1, 7, 0, 110],
            ['LIB', 'Legalización de libros', 'A', 1, 4, 0, 120],
            ['390', 'Resumen anual IVA', 'A', 0, 12, 0, 130],
            ['190', 'Resumen anual retenciones trabajo', 'A', 0, 12, 0, 140],
            ['180', 'Resumen anual retenciones alquileres', 'A', 0, 12, 0, 150],
            ['193', 'Resumen anual capital mobiliario', 'A', 0, 12, 0, 160],
            ['296', 'Resumen anual no residentes', 'A', 0, 12, 0, 170],
            ['347', 'Operaciones con terceros', 'A', 0, 3, 0, 180],
        ];
        foreach ($modelos as [$codigo, $nombre, $per, $desfase, $mes, $auto, $orden]) {
            DB::table('impuesto_modelos')->insert([
                'codigo' => $codigo, 'nombre' => $nombre, 'periodicidad' => $per, 'desfase' => $desfase, 'mes_anual' => $mes,
                'automatico' => $auto, 'orden' => $orden, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
        }

        // Permiso de la pestaña: lo tienen todos los roles; cada usuario solo ve sus impuestos (Admin y Suma pueden pedir verlos todos)
        $permiso = \Spatie\Permission\Models\Permission::findOrCreate('impuestos.ver', 'web');
        foreach (['Admin', 'Suma', 'Usuario'] as $rol) {
            $r = \Spatie\Permission\Models\Role::where('name', $rol)->where('guard_name', 'web')->first();
            $r?->givePermissionTo($permiso);
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('impuesto_alias');
        Schema::dropIfExists('impuesto_documentos');
        Schema::dropIfExists('impuesto_estados');
        Schema::dropIfExists('entidad_impuestos');
        Schema::dropIfExists('impuesto_modelos');
    }
};
