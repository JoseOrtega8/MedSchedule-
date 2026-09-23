<?php

namespace App\Models\Concerns;

use App\Services\Auditoria\RegistradorAuditoria;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

// Audita altas, cambios y bajas del modelo con el diff de los campos cambiados
trait Auditable
{
	// Cambios que no aportan a la auditoria
	private const CAMPOS_EXCLUIDOS_AUDITORIA = ['remember_token', 'created_at', 'updated_at'];

	// Se registra que cambiaron, nunca su valor
	private const CAMPOS_SIEMPRE_PROTEGIDOS = ['password'];

	public const VALOR_PROTEGIDO = '[protegido]';

	public static function bootAuditable(): void
	{
		static::created(function (Model $modelo) {
			$modelo->auditar('creado', [], $modelo->getAttributes());
		});

		static::updated(function (Model $modelo) {
			$cambios = $modelo->getChanges();
			$modelo->auditar('actualizado', array_intersect_key($modelo->getRawOriginal(), $cambios), $cambios);
		});

		static::deleted(function (Model $modelo) {
			$modelo->auditar('eliminado', $modelo->getRawOriginal(), []);
		});
	}

	// Cada modelo con datos sensibles sobreescribe este metodo
	public function campos_protegidos_auditoria(): array
	{
		return [];
	}

	protected function auditar(string $accion, array $anteriores, array $nuevos): void
	{
		$anteriores = $this->limpiar_para_auditoria($anteriores);
		$nuevos = $this->limpiar_para_auditoria($nuevos);

		// Un cambio que solo toco campos excluidos no se registra
		if ($accion === 'actualizado' && $nuevos === []) {
			return;
		}

		app(RegistradorAuditoria::class)->registrar($accion, $this, $anteriores, $nuevos);
	}

	private function limpiar_para_auditoria(array $valores): array
	{
		$protegidos = array_merge(self::CAMPOS_SIEMPRE_PROTEGIDOS, $this->campos_protegidos_auditoria());
		$limpios = [];

		foreach ($valores as $campo => $valor) {
			if (in_array($campo, self::CAMPOS_EXCLUIDOS_AUDITORIA, true)) {
				continue;
			}
			if (in_array($campo, $protegidos, true)) {
				$limpios[$campo] = self::VALOR_PROTEGIDO;
				continue;
			}
			$limpios[$campo] = $valor instanceof DateTimeInterface ? $valor->format('Y-m-d H:i:s') : $valor;
		}

		return $limpios;
	}
}
