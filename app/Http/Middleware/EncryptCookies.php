<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array
     */
    protected $except = [
        'appmos_pc',   // PC desde el que trabaja este navegador (selector «Ejecutar en»); lo pone el JS de la barra de menú
    ];
}
