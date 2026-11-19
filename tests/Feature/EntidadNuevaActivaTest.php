<?php

use App\Models\Entidad;

it('una entidad nueva nace activa', function () {
    expect((new Entidad)->estado)->toBe(1);
});
