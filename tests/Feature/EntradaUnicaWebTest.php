<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Entrada única: en un PC (ENTRADA_WEB_URL) todo redirige a la web, salvo las excepciones y las llamadas internas. */
class EntradaUnicaWebTest extends TestCase
{
    public function test_en_un_pc_todas_las_paginas_redirigen_a_la_web(): void
    {
        config(['contabilidad.entrada_web_url' => 'https://appmos.example.com']);
        $this->get('/login')->assertRedirect('https://appmos.example.com/login');
        $this->get('/entidades?x=1')->assertRedirect('https://appmos.example.com/entidades?x=1');
        $this->get('/contabilidad/bancos')->assertRedirect('https://appmos.example.com/contabilidad/bancos');
    }

    public function test_las_excepciones_y_las_llamadas_internas_no_redirigen(): void
    {
        config(['contabilidad.entrada_web_url' => 'https://appmos.example.com', 'contabilidad.entrada_web_excepciones' => ['contabilidad/durcal*']]);
        $this->get('/contabilidad/durcal')->assertRedirectContains('/login');          // pasa al flujo normal (pide login), no sale a la web
        $this->assertNotSame('https://appmos.example.com/livewire/update', $this->post('/livewire/update')->headers->get('Location'));   // AJAX de Livewire: no sale a la web
        $this->getJson('/api/user')->assertStatus(401);
    }

    public function test_sin_entrada_web_url_todo_sigue_igual(): void
    {
        config(['contabilidad.entrada_web_url' => null]);
        $this->get('/login')->assertOk();
    }
}
