<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plantillas de correo de Proc.Mensuales (una por proceso e idioma). El texto de
 * cada entidad (entidades.mail_peticion) parte de la de su idioma y luego se
 * personaliza. {empresa} se cambia al aplicar la plantilla; {mes} y {año} al enviar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantillas_mail', function (Blueprint $table) {
            $table->id();
            $table->string('proceso', 50);
            $table->string('idioma', 2);
            $table->text('texto')->nullable();
            $table->timestamps();
            $table->unique(['proceso', 'idioma']);
        });

        DB::table('plantillas_mail')->insert([
            ['proceso' => 'petdocimpuestos', 'idioma' => 'ES', 'created_at' => now(), 'updated_at' => now(), 'texto' =>
                "Hola,\n\nPara preparar los impuestos de {empresa} correspondientes a {mes} de {año}, ¿nos podéis enviar la siguiente documentación?\n\n"
                ."- Facturas emitidas\n- Facturas recibidas\n- Extractos bancarios\n\nMuchas gracias,\nUn saludo"],
            ['proceso' => 'petdocimpuestos', 'idioma' => 'EN', 'created_at' => now(), 'updated_at' => now(), 'texto' =>
                "Hello,\n\nIn order to prepare the {mes} {año} tax returns for {empresa}, could you please send us the following documents?\n\n"
                ."- Issued invoices\n- Received invoices\n- Bank statements\n\nThank you very much,\nKind regards"],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('plantillas_mail');
    }
};
