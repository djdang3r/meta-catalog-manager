<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega campos ausentes a meta_catalog_items identificados por best practices:
 *
 * - google_product_category : requerido en prácticas recomendadas, debe tener ≥2 niveles
 * - rich_text_description   : preferido sobre description para contenido HTML
 * - product_type            : categorización interna del vendedor
 * - internal_label          : alternativa recomendada a custom_label para filtrado de product sets
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_catalog_items', function (Blueprint $table) {
            // Categoría de producto según taxonomía de Google.
            // Puede ser nombre (ej: "Apparel & Accessories > Clothing") o ID numérico.
            // Recomendación: mínimo 2 niveles de profundidad.
            $table->string('google_product_category', 500)
                  ->nullable()
                  ->after('fb_product_category');

            // Descripción en formato HTML (rich text).
            // Si se proporciona, Meta la usa en lugar de description (que actúa como fallback).
            // Etiquetas admitidas: <p>, <ul>, <li>, <ol>, <b>, <em>, <strong>, <br>, tablas, etc.
            $table->text('rich_text_description')
                  ->nullable()
                  ->after('description');

            // Categoría del producto según el sistema interno del vendedor.
            // Puede ser una ruta (ej: "Home & Garden > Kitchen > Appliances") o ID de GPC.
            $table->string('product_type', 750)
                  ->nullable()
                  ->after('category');

            // Etiquetas internas para filtrado de product sets.
            // Alternativa recomendada a custom_label_[0-4]: se pueden actualizar sin
            // enviar el producto por revisión de política.
            // Formato: ['summer', 'trending', 'sale_q1'] — almacenado como JSON array.
            $table->json('internal_label')
                  ->nullable()
                  ->after('custom_label_4');
        });
    }

    public function down(): void
    {
        Schema::table('meta_catalog_items', function (Blueprint $table) {
            $table->dropColumn([
                'google_product_category',
                'rich_text_description',
                'product_type',
                'internal_label',
            ]);
        });
    }
};
