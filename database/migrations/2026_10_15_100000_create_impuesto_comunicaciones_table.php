<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comunicar impuestos al cliente (Impuestos → Seguimiento, 15-oct-2026): lo que se propuso a cada cliente y periodo
 * (importes, días de cargo, destinatarios, idioma, texto) y cuándo se dejó el borrador en Outlook. Lo último de cada
 * cliente es la base del siguiente. Plantillas ES/EN en plantillas_mail (proceso comunicar_impuestos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impuesto_comunicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entidad_id')->constrained('entidades')->cascadeOnDelete();
            $table->unsignedSmallInteger('ejercicio');
            $table->string('clave', 12);                 // mes de cierre '01'..'12' o 'A'/'D<modelo>' (anuales)
            $table->json('filas')->nullable();           // [{id, cod, per, texto, imp, fecha}]
            $table->string('para', 500)->default('');
            $table->string('cc', 500)->default('');
            $table->string('idioma', 2)->default('ES');
            $table->string('asunto', 255)->default('');
            $table->text('texto')->nullable();
            $table->string('banco', 60)->default('');     // encabezado sobre la columna del día (p. ej. «BBVA DD»)
            $table->timestamp('borrador_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['entidad_id', 'ejercicio', 'clave']);
        });

        $rgpd = "En cumplimiento de lo dispuesto en el RGPD de 25 de mayo 2016, le informamos de que sus datos personales quedarán incorporados a un fichero automatizado de datos de carácter personal del que es responsable Suma Apoyo Empresarial SL. Esta tratará los datos de forma confidencial y exclusivamente con la finalidad de gestionar la relación con sus clientes y promocionar actividades de la sociedad. Asimismo, le informamos de la posibilidad de ejercer los derechos de acceso, rectificación, cancelación y oposición de sus datos de carácter personal, solicitándolo a través del siguiente correo electrónico: info@sumaempresa.com\n"
            ."In compliance with the provisions of the RGPD of May 25, 2016, we inform you that your personal data will be incorporated into an automated file of personal data for which Suma Apoyo Empresarial SL is responsible. This will treat the data confidentially and exclusively with the purpose of managing the relationship with its customers and promoting society's activities. Likewise, we inform you of the possibility of exercising the rights of access, rectification, cancellation and opposition of your personal data, by requesting it through the following email: info@sumaempresa.com";
        $firma = "{nombre}\nSuma Apoyo Empresarial SL\nwww.sumaempresa.com\n{logo}\n\n".$rgpd;
        $ahora = now();
        DB::table('plantillas_mail')->insertOrIgnore([
            ['proceso' => 'comunicar_impuestos', 'idioma' => 'ES', 'asunto' => 'Impuestos {periodo}', 'created_at' => $ahora, 'updated_at' => $ahora, 'texto' =>
                "Hola {contacto}:\n\nAdjunto podéis encontrar el resultado de los impuestos de {periodo}.\nSi estáis de acuerdo, los presentaremos y se cargarán en vuestra cuenta en las fechas siguientes:\n\n{tabla}\n\nAtentamente\n\n".$firma],
            ['proceso' => 'comunicar_impuestos', 'idioma' => 'EN', 'asunto' => '{periodo} taxes', 'created_at' => $ahora, 'updated_at' => $ahora, 'texto' =>
                "Dear {contacto},\n\nPlease find attached the result of {periodo} taxes.\nIf you agree we will submit them and they will be charged to your account as follows:\n\n{tabla}\n\nKind regards\n\n".$firma],
        ]);

        // Fashion IQ: lo de «August Taxes» (Procesos FIQ → Comunicar impuestos a Plein), como base de su primer borrador desde aquí
        $fiq = DB::table('entidades')->where('entidad', 'like', 'FASHION IQ BCN%')->value('id');
        if ($fiq) {
            DB::table('impuesto_comunicaciones')->insertOrIgnore([
                'entidad_id' => $fiq, 'ejercicio' => 2026, 'clave' => '08', 'idioma' => 'EN', 'banco' => 'BBVA DD', 'asunto' => '{mes} Taxes',
                'para' => 'e.guzzardi@plein.com, f.gaivazova@plein.com, l.scibona@plein.com',
                'cc' => 't.dudarevich@plein.com, v.laptukhova@plein.com, k.lemenova@plein.com, marta.ruiz@sumaempresa.com',
                'texto' => "Dear all,\n\nPlease find below the result of {mes} taxes.\nIf you agree we´ll submit them and they will be charged in BBVA as following.\n\n{tabla}\n\nPlease, ensure there are funds enough these days\n\nAtentamente\n\n".$firma,
                'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('impuesto_comunicaciones');
        DB::table('plantillas_mail')->where('proceso', 'comunicar_impuestos')->delete();
    }
};
