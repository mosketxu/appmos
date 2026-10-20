<?php

namespace Tests\Unit;

use App\Support\Impuestos;
use PHPUnit\Framework\TestCase;

class AlarmaCasillaTest extends TestCase
{
    public function test_alarma_solo_en_espiral_m202_p3_2026(): void
    {
        $this->assertNotNull(Impuestos::alarmaCasilla('ESPIRAL SL', '202', 'P3', 2026));
        $this->assertNull(Impuestos::alarmaCasilla('ESPIRAL SL', '202', 'P2', 2026));
        $this->assertNull(Impuestos::alarmaCasilla('Otra SL', '202', 'P3', 2026));
        $this->assertNull(Impuestos::alarmaCasilla('ESPIRAL SL', '200', 'P3', 2026));
    }
}
