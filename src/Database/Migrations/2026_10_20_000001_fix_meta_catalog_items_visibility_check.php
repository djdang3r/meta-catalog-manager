<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Meta devuelve `visibility = hidden` para los items variante. El CHECK
     * original solo permitía published/staging, así que el sync reventaba en
     * el primer item oculto (verificado en producción: solo sincronizaba 2 de
     * 14 productos). Se recrea el constraint incluyendo `hidden`.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['mysql', 'pgsql'], true)) {
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE meta_catalog_items DROP CONSTRAINT IF EXISTS chk_visibility');
        } else {
            try {
                DB::statement('ALTER TABLE meta_catalog_items DROP CONSTRAINT chk_visibility');
            } catch (\Throwable $e) {
                // Constraint inexistente o ya recreado: continuar.
            }
        }

        DB::statement("ALTER TABLE meta_catalog_items ADD CONSTRAINT chk_visibility CHECK (visibility IN ('published', 'staging', 'hidden'))");
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['mysql', 'pgsql'], true)) {
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE meta_catalog_items DROP CONSTRAINT IF EXISTS chk_visibility');
        } else {
            try {
                DB::statement('ALTER TABLE meta_catalog_items DROP CONSTRAINT chk_visibility');
            } catch (\Throwable $e) {
                // Constraint inexistente.
            }
        }

        DB::statement("ALTER TABLE meta_catalog_items ADD CONSTRAINT chk_visibility CHECK (visibility IN ('published', 'staging'))");
    }
};
