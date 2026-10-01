<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Plantillas de Pet. Documentación Impuestos según el texto de Alex (1-oct-2026),
 * con su firma. {periodo} se cambia al enviar por el mes o el trimestre, según el
 * ciclo de impuestos de la entidad (cicloimpuesto_id).
 */
return new class extends Migration
{
    private const RGPD = "En cumplimiento de lo dispuesto en el RGPD de 25 de mayo 2016, le informamos de que sus datos personales quedarán incorporados a un fichero automatizado de datos de carácter personal del que es responsable Suma Apoyo Empresarial SL. Esta tratará los datos de forma confidencial y exclusivamente con la finalidad de gestionar la relación con sus clientes y promocionar actividades de la sociedad. Asimismo, le informamos de la posibilidad de ejercer los derechos de acceso, rectificación, cancelación y oposición de sus datos de carácter personal, solicitándolo a través del siguiente correo electrónico: info@sumaempresa.com\n"
        ."In compliance with the provisions of the RGPD of May 25, 2016, we inform you that your personal data will be incorporated into an automated file of personal data for which Suma Apoyo Empresarial SL is responsible. This will treat the data confidentially and exclusively with the purpose of managing the relationship with its customers and promoting society's activities. Likewise, we inform you of the possibility of exercising the rights of access, rectification, cancellation and opposition of your personal data, by requesting it through the following email: info@sumaempresa.com";

    public function up(): void
    {
        $textos = [
            'ES' => "Buenos días:\n\n"
                ."Entramos de nuevo en el periodo de impuestos correspondiente a {periodo}.\n\n"
                ."Te agradeceríamos que nos enviaras todas las facturas recibidas y emitidas de este periodo que todavía no nos hayas hecho llegar.\n\n"
                ."Muchas gracias.\n\n"
                ."Atentamente\n\nAlexander Arregui\nTel. 638 12 26 14\nSuma Apoyo Empresarial SL\n\n".self::RGPD,
            'EN' => "Good morning,\n\n"
                ."We are once again entering the tax period for {periodo}.\n\n"
                ."We would be grateful if you could send us all the invoices received and issued in this period that you have not yet sent us.\n\n"
                ."Thank you very much.\n\n"
                ."Kind regards\n\nAlexander Arregui\nTel. +34 638 12 26 14\nSuma Apoyo Empresarial SL\n\n".self::RGPD,
        ];
        foreach ($textos as $idioma => $texto) {
            DB::table('plantillas_mail')->updateOrInsert(['proceso' => 'petdocimpuestos', 'idioma' => $idioma],
                ['texto' => $texto, 'updated_at' => now(), 'created_at' => now()]);
        }
    }

    public function down(): void
    {
    }
};
