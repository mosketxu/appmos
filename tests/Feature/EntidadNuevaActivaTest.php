<?php

namespace Tests\Feature;

use App\Models\Entidad;
use Tests\TestCase;

class EntidadNuevaActivaTest extends TestCase
{
    public function test_una_entidad_nueva_nace_activa(): void
    {
        $this->assertSame(1, (new Entidad)->estado);
    }
}
