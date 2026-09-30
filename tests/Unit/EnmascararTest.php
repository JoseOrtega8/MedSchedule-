<?php

namespace Tests\Unit;

use App\Support\Enmascarar;
use PHPUnit\Framework\TestCase;

class EnmascararTest extends TestCase
{
	public function test_enmascara_la_parte_local_del_correo(): void
	{
		$this->assertSame('j***@gmail.com', Enmascarar::correo('juan.perez@gmail.com'));
	}

	public function test_valor_que_no_es_correo_se_oculta_completo(): void
	{
		$this->assertSame('***', Enmascarar::correo('no-es-correo'));
		$this->assertSame('***', Enmascarar::correo(''));
	}
}
