<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Livewire\Livewire;

/**
 * Prueba de humo (4-oct-2026): pinta las pantallas principales de Appmos como Admin, con el guard por defecto «sanctum» como en una petición web
 * real, y dice cuáles fallan. Se pasa después de cada despliegue: `php artisan appmos:humo`. No escribe nada (solo renderiza).
 */
class Humo extends Command
{
    protected $signature = 'appmos:humo';

    protected $description = 'Pinta las pantallas principales y dice si alguna da error (prueba de humo tras desplegar)';

    public function handle(): int
    {
        $admin = User::role('Admin')->orderBy('id')->first() ?? User::orderBy('id')->first();
        if (! $admin) {
            $this->error('No hay usuarios.');

            return 1;
        }
        config(['auth.defaults.guard' => 'sanctum']);
        auth()->setUser($admin);
        $c = 'App\\Http\\Livewire\\';
        $pruebas = [
            'Roles y permisos' => [$c.'Admin\\Roles', []],
            'Usuarios' => [$c.'Admin\\Usuarios', []],
            'TO-DO' => [$c.'Todo', []],
            'Campana' => [$c.'TodoCampana', []],
            'Estado de los PCs' => [$c.'TrabajadoresEstado', []],
            'Procesos FIQ' => [$c.'Contabilidad\\Procesos', []],
            'Facturación PDF' => [$c.'Contabilidad\\FacturacionPdf', []],
            'Durcal' => [$c.'Contabilidad\\Durcal', []],
            'Facturas OCR · revisar' => [$c.'Contabilidad\\FacturasOcr', ['vista' => 'revisar']],
            'Facturas OCR · validadas' => [$c.'Contabilidad\\FacturasOcr', ['vista' => 'historico']],
            'Facturas OCR · proveedores' => [$c.'Contabilidad\\FacturasOcr', ['vista' => 'proveedores']],
            'Facturas OCR · chequeo mayor' => [$c.'Contabilidad\\FacturasOcr', ['vista' => 'chequeo']],
            'Facturas OCR · ordenar sueltas' => [$c.'Contabilidad\\FacturasOcr', ['vista' => 'ordenar']],
            'Bancos' => [$c.'Contabilidad\\Bancos', []],
            'IS' => [$c.'Contabilidad\\Is', []],
            'Neteges' => [$c.'Contabilidad\\Neteges', []],
            'LeoyBra' => [$c.'Contabilidad\\LeoyBra', []],
            'Proc.Mensuales' => [$c.'Contabilidad\\ProcesosMensuales', []],
            'Revisión del mayor' => [$c.'Contabilidad\\RevisionMayor', []],
            'Seguimiento' => [$c.'Contabilidad\\SeguimientoMensual', []],
            'Certificados' => [$c.'Contabilidad\\Certificados', []],
        ];
        $mal = 0;
        foreach ($pruebas as $nombre => [$clase, $props]) {
            try {
                $t = Livewire::test($clase);
                foreach ($props as $k => $v) {
                    $t->set($k, $v);
                }
                $html = $t->html();
                if (str_contains($html, 'Whoops') || str_contains($html, 'Undefined variable')) {
                    throw new \RuntimeException('la pantalla se pinta con un error');
                }
                $this->line("  ✔ {$nombre} (".number_format(strlen($html) / 1024, 0).' KB)');
            } catch (\Throwable $e) {
                $mal++;
                $this->error("  ✖ {$nombre}: ".get_class($e).': '.mb_substr($e->getMessage(), 0, 180));
            }
        }
        $this->newLine();
        $mal ? $this->error("{$mal} pantalla(s) con error.") : $this->info('Todas las pantallas se pintan bien.');

        return $mal ? 1 : 0;
    }
}
