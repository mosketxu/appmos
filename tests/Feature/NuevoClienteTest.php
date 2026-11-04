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
        $t->assertSet('campos.nif', 'B05622055')->set('campos.porcentajemarta', '60')->set('campos.porcentajesusana', '40')->call('crear')->assertSet('error', '');
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

    public function test_sin_permiso_no_hace_nada(): void
    {
        $id = DB::table('users')->insertGetId(['name' => 'X', 'email' => 'x@example.com', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        Livewire::actingAs(User::find($id))->test(NuevoCliente::class)->set('consulta', 'Alta')->call('buscar')->assertSet('resultados', []);
    }
}
