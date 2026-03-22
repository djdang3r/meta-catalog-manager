<?php

namespace ScriptDevelop\MetaCatalogManager\Support;

use InvalidArgumentException;

/**
 * Resuelve clases de modelos desde la configuración del paquete,
 * permitiendo que el proyecto host sobrescriba cualquier modelo.
 */
class MetaCatalogModelResolver
{
    /**
     * Retorna la clase del modelo configurada para la key dada.
     *
     * @param string $key Ej: 'meta_catalog', 'meta_business_account'
     * @return string FQCN de la clase del modelo
     * @throws InvalidArgumentException Si la clase no existe
     */
    public function resolveModel(string $key): string
    {
        $class = config("meta-catalog.models.{$key}");

        if (empty($class)) {
            throw new InvalidArgumentException(
                "No se encontró configuración para el modelo [{$key}]. " .
                "Verifica meta-catalog.models.{$key} en tu configuración."
            );
        }

        if (!class_exists($class)) {
            throw new InvalidArgumentException(
                "La clase para el modelo [{$key}] no existe: [{$class}]"
            );
        }

        return $class;
    }

    /**
     * Crea una nueva instancia del modelo indicado.
     *
     * @param string $key        Clave del modelo en la config
     * @param array  $attributes Atributos iniciales (opcional)
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function make(string $key, array $attributes = []): \Illuminate\Database\Eloquent\Model
    {
        $class = $this->resolveModel($key);

        return new $class($attributes);
    }

    /**
     * Retorna un query builder para el modelo indicado.
     *
     * @param string $key Clave del modelo en la config
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query(string $key): \Illuminate\Database\Eloquent\Builder
    {
        $class = $this->resolveModel($key);

        return $class::query();
    }

    /**
     * Permite sustituir el modelo dinámicamente (útil para testing).
     */
    public function fake(string $key, string $class): void
    {
        config()->set("meta-catalog.models.{$key}", $class);
    }
}
