<?php

namespace App\Support;

/** Fichero ya en disco con la misma interfaz que una subida de Livewire (lo que espera crear()). */
class ArchivoLocal
{
    public function __construct(private string $ruta, private string $nombre)
    {
    }

    public function getRealPath(): string
    {
        return $this->ruta;
    }

    public function getClientOriginalName(): string
    {
        return $this->nombre;
    }

    public function getClientOriginalExtension(): string
    {
        return pathinfo($this->nombre, PATHINFO_EXTENSION);
    }

    public function delete(): void
    {
        @unlink($this->ruta);
        @rmdir(dirname($this->ruta));
    }
}
