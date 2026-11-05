<?php

namespace Tests\Feature;

use App\Http\Livewire\Contabilidad\NuevoCliente;
use App\Models\Entidad;
use App\Models\User;
use App\Support\AltaClienteCorreo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** «Nuevo cliente» (4-nov-2026): lectura del correo «Alta cliente» de Marta y alta de la entidad. El correo es el real de FORZA ANDINA. */
class NuevoClienteTest extends TestCase
{
    use RefreshDatabase;

    private function fila(string $a, string $b): string
    {
        return "<tr><td><p>{$a}</p></td><td><p style=\"text-align:center\">{$b}</p></td></tr>";
    }

    private function correo(): string
    {
        $t = '<tr><td><p>FORZA ANDINA CAPITAL, S.L.</p></td></tr>'
            .$this->fila('Fecha', 'oct-26').$this->fila('Cliente', 'FORZA ANDINA CAPITAL, S.L.').$this->fila('NIF', '<b>B05622055</b>')
            .$this->fila('Dirección ', 'Avda Madrid, 160, 3º 4ª, Barcelona').$this->fila('CP', '8028 BNC').$this->fila('Provincia', 'Barcelona')
            .$this->fila('Cobro', '&nbsp;').$this->fila('IBAN', '&nbsp;').$this->fila('Primera factura', '30/11/2026').$this->fila('Cuota', 'mensual')
            .$this->fila('Importe', '270 €').$this->fila('mail', '&nbsp;').$this->fila('SAGE', 'si').$this->fila('Equipo', 'Marta Carmona');

        return '<div><p>Hola Alex,</p><p>Te envío datos para el alta de un nuevo cliente:</p><table>'.$t.'</table>'
            .'<p>La primera factura será en octubre con la siguiente descripción: Servicios iniciales: 150€</p>'
            .'<p>De momento pon cobro por transferencia y con la primera factura ya le preguntamos al cliente si quiere domiciliar.</p>'
            .'<p>Gracias!</p><p>Marta Ruiz</p><p>En cumplimiento de lo dispuesto en el RGPD de 25 de mayo 2016, le informamos…</p></div>';
    }

    private function datos(): void
    {
        DB::table('provincias')->insert([['id' => '08', 'provincia' => 'BARCELONA'], ['id' => '28', 'provincia' => 'MADRID']]);
        DB::table('sumas')->insert([['id' => 9, 'nombre' => 'Marta Carmona', 'tfno' => '', 'email' => 'a@b.c', 'created_at' => now(), 'updated_at' => now()], ['id' => 1, 'nombre' => 'Marta Ruiz', 'tfno' => '', 'email' => 'b@b.c', 'created_at' => now(), 'updated_at' => now()]]);
    }

    public function test_lee_los_campos_del_correo(): void
    {
        $this->datos();
        $r = AltaClienteCorreo::interpretar($this->correo());
        $c = $r['campos'];
        $this->assertSame('FORZA ANDINA CAPITAL, S.L.', $c['entidad']);
        $this->assertSame('B05622055', $c['nif']);
        $this->assertSame('Avda Madrid, 160, 3º 4ª, Barcelona', $c['direccion']);
        $this->assertSame('08028', $c['codpostal']);
        $this->assertSame('08', $c['provincia_id']);
        $this->assertSame('Barcelona', $c['localidad']);
        $this->assertSame(1, $c['metodopago_id']);
        $this->assertSame(1, $c['ciclo']);
        $this->assertSame(270.0, $c['importe']);
        $this->assertSame(9, $c['suma_id']);
        $this->assertNull($c['emailadm']);
        $this->assertStringContainsString('Servicios iniciales: 150€', $r['extra']);
        $this->assertStringContainsString('cobro por transferencia', $r['extra']);
        $this->assertStringNotContainsString('RGPD', $r['extra']);
        $this->assertStringNotContainsString('Hola Alex', $r['extra']);
        $this->assertNotEmpty(array_filter($r['avisos'], fn ($a) => str_contains($a, 'código postal')));
        $this->assertSame('30/11/2026', $r['nota']['Primera factura']);
    }

    public function test_el_cp_manda_sobre_una_provincia_incoherente(): void
    {
        $this->datos();
        $html = '<table><tr><td>Cliente</td><td>ZURUMANA, S.L.</td></tr><tr><td>NIF</td><td>B-25949769</td></tr><tr><td>CP</td><td>28004 Madrid</td></tr><tr><td>Provincia</td><td>Barcelona</td></tr></table>';
        $i = \App\Support\AltaClienteCorreo::interpretar($html);
        $this->assertSame('28', $i['campos']['provincia_id']);
        \App\Models\Entidad::withoutGlobalScopes()->create(['entidad' => 'X SL', 'nif' => 'B00000001', 'codpostal' => '08010', 'localidad' => 'Barcelona']);
        $j = \App\Support\AltaClienteCorreo::interpretar('<table><tr><td>Cliente</td><td>Y SL</td></tr><tr><td>CP</td><td>08010</td></tr></table>');
        $this->assertSame('Barcelona', $j['campos']['localidad']);
        $this->assertSame('08', $j['campos']['provincia_id']);
        $this->assertNotEmpty(array_filter($i['avisos'], fn ($a) => str_contains($a, 'provincia')));
    }

    public function test_lee_un_eml(): void
    {
        $eml = "Subject: =?UTF-8?Q?Alta_cliente_FORZA?=\r\nContent-Type: multipart/alternative; boundary=\"b1\"\r\n\r\n--b1\r\nContent-Type: text/plain; charset=utf-8\r\n\r\nhola\r\n--b1\r\nContent-Type: text/html; charset=utf-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n<p>Direcci=C3=B3n</p>\r\n--b1--\r\n";
        $r = NuevoCliente::leerEml($eml);
        $this->assertSame('Alta cliente FORZA', $r['asunto']);
        $this->assertStringContainsString('Dirección', $r['html']);
    }

    public function test_crea_la_entidad_y_su_historial(): void
    {
        $this->datos();
        $id = DB::table('users')->insertGetId(['name' => 'Alex', 'email' => 'alex@example.com', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        $u = User::find($id);
        foreach (['contabilidad.procesosmensuales', 'proceso.pm.nuevo_cliente', 'entidades.editar'] as $p) {
            Permission::findOrCreate($p);
            $u->givePermissionTo($p);
        }
        $t = Livewire::actingAs($u)->test(NuevoCliente::class);
        $t->set('asunto', 'Alta cliente FORZA ANDINA')->call('cargar', $this->correo());
        $t->assertSet('campos.nif', 'B05622055')->set('campos.porcentajemarta', '60')->set('campos.porcentajesusana', '40')->call('crear')->assertSet('creada', fn ($v) => $v > 0);
        $e = Entidad::withoutGlobalScopes()->where('nif', 'B05622055')->first();
        $this->assertNotNull($e);
        $this->assertSame(1, (int) $e->cliente);
        $this->assertSame('08028', $e->codpostal);
        $this->assertSame(9, (int) $e->suma_id);
        $this->assertSame(60.0, (float) $e->porcentajemarta);
        $this->assertSame(40.0, (float) $e->porcentajesusana);
        $h = $e->historico()->first();
        $this->assertSame(270.0, (float) $h->importe_facturacion);
        $this->assertStringContainsString('Primera factura: 30/11/2026', $h->comentario);

        // segunda vez: mismo NIF → no duplica
        $t->call('otro')->call('cargar', $this->correo())->call('crear')->assertNotSet('error', '');
        $this->assertSame(1, Entidad::withoutGlobalScopes()->where('nif', 'B05622055')->count());
    }

    public function test_codigo_430_y_excel_para_sage(): void
    {
        $this->datos();
        $suma = \App\Models\Entidad::withoutGlobalScopes()->create(['entidad' => 'Suma Apoyo Empresarial S.L.', 'nif' => \App\Support\AltaClienteSage::NIF_SUMA]);
        $tmp = sys_get_temp_dir().'/nc_'.uniqid();
        mkdir($tmp);
        config(['contabilidad.ficheros_base_dir' => $tmp.'/fb']);
        // listado de clientes (cabecera + 3 filas de descripción + datos; un 7 dígitos que NO cuenta) y plan de cuentas con una 430 más alta que el listado
        $l = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $h = $l->getActiveSheet();
        $cab = ['Código cliente', 'Razón social', 'Nombre fiscal', 'Sigla Nación', 'CIF/DNI', 'Delegación', 'Teléfono', 'Municipio', 'Provincia', 'Código comisionista', 'Código contable', 'Categoría', 'Nación', 'Código Nación'];
        $h->fromArray([$cab, array_fill(0, 14, 'Descripción'), array_fill(0, 14, 'Características'), array_fill(0, 14, 'Valores'),
            ['430000100', 'A', 'A', 'ES', 'B1', '', '', '', '', '0', '430000100', 'CLI', 'ESPAÑA', '108'],
            ['4300123', 'ERROR 7', 'ERROR 7', 'ES', 'B2', '', '', '', '', '0', '4300123', 'CLI', 'ESPAÑA', '108']]);
        $f1 = $tmp.'/EU_Clientes_GRID_01017.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($l))->save($f1);
        $p = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $p->getActiveSheet()->fromArray([['Código cuenta', 'Descripción'], ['430000', 'x'], ['430250', 'cliente'], ['4301000', 'siete'], ['430950', 'excepcion']]);
        $f2 = $tmp.'/Plan.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($p))->save($f2);
        \App\Support\FicherosBase::guardar($suma->id, 'clientes', $f1, 'EU_Clientes_GRID_01017.xlsx');
        \App\Support\FicherosBase::guardar($suma->id, 'plan', $f2, 'Plan.xlsx');
        // (en 6 dígitos los 430xxx son 430250 y 430100; el de 7 dígitos se ignora)
        $r = \App\Support\AltaClienteSage::siguiente();
        $this->assertSame('430251', $r['codigo']);

        $e = \App\Models\Entidad::withoutGlobalScopes()->create(['entidad' => 'FORZA ANDINA CAPITAL, S.L.', 'nif' => 'B05622055', 'codigo_cliente' => '430251', 'cuentacontable' => '430251', 'localidad' => 'Barcelona', 'provincia_id' => '08']);
        $e2 = \App\Models\Entidad::withoutGlobalScopes()->create(['entidad' => 'OTRA SL', 'nif' => 'B11111111', 'codigo_cliente' => '430252', 'cuentacontable' => '430252']);
        $e3 = \App\Models\Entidad::withoutGlobalScopes()->create(['entidad' => 'YA EN SAGE', 'nif' => 'B1', 'codigo_cliente' => '430100', 'cuentacontable' => '430100']);
        foreach ([$e, $e2, $e3] as $x) {
            DB::table('entidad_historico')->insert(['entidad_id' => $x->id, 'tipo' => 'alta', 'fecha' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
        }
        $nombre = \App\Support\AltaClienteSage::excelPendientes();   // 430100 ya está en el listado: no se repite
        $filas = \PhpOffice\PhpSpreadsheet\IOFactory::load(\App\Support\AltaClienteSage::dirSalida().'/'.$nombre)->getActiveSheet()->toArray(null, false, false, false);
        $this->assertSame('430252', end($filas)[0]);
        $ult = $filas[count($filas) - 2];
        $this->assertSame('430251', $ult[0]);
        $this->assertSame('430251', $ult[10]);
        $this->assertSame('B05622055', $ult[4]);
        $this->assertSame('BARCELONA', $ult[8]);
        $this->assertSame('CLI', $ult[11]);
        $this->assertCount(8, $filas);

        // Plantilla de importación «Gestión»: sin sus filas de ejemplo, con las pendientes + una ya creada pedida por código
        $suma->update(['codigo_empresa_sage' => '1017', 'contrapartida' => '705000']);
        $e->update(['retencion' => 15, 'persona_fj' => 'J', 'codpostal' => '08010', 'direccion' => 'C/ Casp 39', 'iban1' => 'ES12 3456', 'metodopago_id' => 2]);
        $g = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $cabG = ['CodigoEmpresa', 'ClienteOProveedor', 'CodigoClienteProveedor', 'SiglaNacion', 'CifDni', 'CodigoCategoriaCliente_', 'CifEuropeo', 'RazonSocial', 'Domicilio', 'CodigoPostal', 'CodigoMunicipio', 'Municipio',
            'CodigoProvincia', 'Provincia', 'IBAN', 'NumeroPlazos', 'DiasFijos1', 'CodigoCuenta', 'CodigoIva', 'CodigoRetencion', 'CodigoCondiciones', 'CodigoTransaccion', 'Contrapartida', 'Deducible', 'PersonaFisicaJuridica'];
        $g->getActiveSheet()->fromArray([$cabG, array_fill(0, count($cabG), 'ejemplo'), array_fill(0, count($cabG), 'ejemplo')]);
        $f3 = $tmp.'/GES.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($g))->save($f3);
        \App\Support\FicherosBase::guardar($suma->id, 'gestion', $f3, 'GES.xlsx');
        $n2 = \App\Support\AltaClienteSage::excelGestion('C', '430100');
        $r = \PhpOffice\PhpSpreadsheet\IOFactory::load(\App\Support\AltaClienteSage::dirSalida().'/'.$n2)->getActiveSheet()->toArray(null, false, false, false);
        $this->assertCount(2, $r);   // cabecera + solo 430100
        $n3 = \App\Support\AltaClienteSage::excelGestion('C');
        $r = \PhpOffice\PhpSpreadsheet\IOFactory::load(\App\Support\AltaClienteSage::dirSalida().'/'.$n3)->getActiveSheet()->toArray(null, false, false, false);
        $this->assertCount(3, $r);   // cabecera + pendientes 430251 y 430252
        $c = array_combine($r[0], $r[1]);
        $this->assertEquals(1017, $c['CodigoEmpresa']);
        $this->assertSame('C', $c['ClienteOProveedor']);
        $this->assertEquals(430251, $c['CodigoClienteProveedor']);
        $this->assertEquals(430251, $c['CodigoCuenta']);
        $this->assertEquals(8019, $c['CodigoMunicipio']);
        $this->assertEquals(8, $c['CodigoProvincia']);
        $this->assertEquals(18, $c['CodigoRetencion']);
        $this->assertEquals(2, $c['CodigoCondiciones']);
        $this->assertEquals(1, $c['CodigoTransaccion']);
        $this->assertEquals(705000, $c['Contrapartida']);
        $this->assertEquals(-1, $c['Deducible']);
        $this->assertSame('CLI', $c['CodigoCategoriaCliente_']);
        $this->assertSame('J', $c['PersonaFisicaJuridica']);
        $this->assertEquals(10, $c['DiasFijos1']);
        $this->assertSame('ESB05622055', $c['CifEuropeo']);
    }

    public function test_sin_permiso_no_hace_nada(): void
    {
        $id = DB::table('users')->insertGetId(['name' => 'X', 'email' => 'x@example.com', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        Livewire::actingAs(User::find($id))->test(NuevoCliente::class)->set('consulta', 'Alta')->call('buscar')->assertSet('resultados', []);
    }
}
