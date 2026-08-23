<?php

namespace Tests\Feature;

use App\Models\Entidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsvExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_export_sanitizes_formula_injection_and_escapes_quotes(): void
    {
        Entidad::create([
            'entidad' => '=cmd|"/c calc"!A1',
            'observaciones' => 'Cliente con "comillas" y, coma',
        ]);

        ob_start();
        Entidad::query()->toCsv();
        $csv = ob_get_clean();

        $this->assertStringNotContainsString("\n=cmd", $csv);
        $this->assertStringContainsString("'=cmd", $csv);
        $this->assertStringContainsString('"Cliente con ""comillas"" y, coma"', $csv);
    }
}
