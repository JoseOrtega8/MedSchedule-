<?php

namespace Tests\Unit\Providers;

use App\Providers\MetricasServiceProvider;
use PHPUnit\Framework\TestCase;

// La conexion a Redis de las metricas debe reutilizarse entre peticiones del
// mismo proceso PHP: abrir un socket nuevo por peticion sumaba latencia medible
class MetricasServiceProviderTest extends TestCase
{
	private function config_redis(array $extra = []): array
	{
		return array_merge([
			'host' => '127.0.0.1',
			'port' => 6380,
			'timeout' => 0.2,
			'read_write_timeout' => 0.2,
		], $extra);
	}

	public function test_conexion_redis_persistente_por_defecto(): void
	{
		$parametros = MetricasServiceProvider::parametros_redis($this->config_redis());

		$this->assertTrue($parametros['persistent']);
		$this->assertSame('127.0.0.1', $parametros['host']);
		$this->assertSame(6380, $parametros['port']);
		$this->assertSame(0.2, $parametros['timeout']);
		$this->assertSame(0.2, $parametros['read_write_timeout']);
	}

	public function test_conexion_persistente_se_puede_desactivar(): void
	{
		$parametros = MetricasServiceProvider::parametros_redis($this->config_redis(['persistente' => false]));

		$this->assertFalse($parametros['persistent']);
	}
}
