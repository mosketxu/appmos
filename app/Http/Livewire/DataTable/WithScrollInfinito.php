<?php

namespace App\Http\Livewire\DataTable;

/** Scroll infinito para listados con `rowsQuery`: crece por lotes al llegar al final (sustituye a la paginación). */
trait WithScrollInfinito
{
    public int $limite = 100;
    public bool $hayMas = false;

    public function cargarMas()
    {
        $this->limite += 100;
    }

    /** Al cambiar búsqueda o filtros, se vuelve al primer lote. */
    public function updatingSearch() { $this->limite = 100; }
    public function updatingFiltroanyo() { $this->limite = 100; }
    public function updatingFiltromes() { $this->limite = 100; }

    public function getRowsProperty()
    {
        // Se pide una de más para saber si quedan filas por cargar
        $filas = $this->rowsQuery->limit($this->limite + 1)->get();
        $this->hayMas = $filas->count() > $this->limite;

        return $filas->take($this->limite);
    }

    /** Total de filas (solo hace falta al seleccionar «todas»). */
    public function getTotalFilasProperty()
    {
        return $this->rowsQuery->toBase()->getCountForPagination();
    }
}
