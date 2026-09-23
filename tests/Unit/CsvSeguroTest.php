<?php

namespace Tests\Unit;

use App\Support\CsvSeguro;
use PHPUnit\Framework\TestCase;

class CsvSeguroTest extends TestCase
{
	public function test_neutraliza_celdas_que_parecen_formula(): void
	{
		foreach (['=CMD()', '+1', '-1', '@SUMA(A1)', "\tx", "\rx"] as $peligrosa) {
			$this->assertSame("'" . $peligrosa, CsvSeguro::celda($peligrosa));
		}
	}

	public function test_deja_igual_el_texto_normal_y_serializa_arreglos(): void
	{
		$this->assertSame('Cardiologia', CsvSeguro::celda('Cardiologia'));
		$this->assertSame('', CsvSeguro::celda(null));
		$this->assertSame('{"name":"Cardiologia"}', CsvSeguro::celda(['name' => 'Cardiologia']));
	}
}
