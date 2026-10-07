<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Imprime (no ejecuta) el SQL para dejar de usar un superusuario de PostgreSQL:
 * - un rol DUEÑO de las tablas, solo para correr migraciones;
 * - un rol de la APLICACIÓN con SELECT, INSERT, UPDATE y DELETE, sin permiso de cambiar la estructura,
 *   y sin UPDATE, DELETE ni TRUNCATE sobre la bitácora (solo inserción).
 * Las contraseñas no se escriben aquí: se pasan a psql como variables (-v clave_dueno=... -v clave_app=...).
 */
class UsuarioBase extends Command
{
    protected $signature = 'piscicola:usuario-base
        {--dueno=sas_piscicola_dueno : Rol dueño de las tablas (migraciones)}
        {--app=sas_piscicola_app : Rol con el que se conecta la aplicación}';

    public function __construct()
    {
        parent::__construct();
        $this->description = __('comandos.usuario_base.descripcion');
    }

    public function handle(): int
    {
        $base = (string) config('database.connections.pgsql.database');
        $dueno = $this->identificador((string) $this->option('dueno'));
        $app = $this->identificador((string) $this->option('app'));

        if ($dueno === null || $app === null || $dueno === $app) {
            $this->error(__('comandos.usuario_base.nombres_invalidos'));

            return self::FAILURE;
        }

        // Estado actual (solo lectura): con qué rol se conecta hoy la app y si es superusuario
        $actual = DB::selectOne('SELECT current_user AS rol, (SELECT rolsuper FROM pg_roles WHERE rolname = current_user) AS superusuario');
        $this->comment(__($actual->superusuario ? 'comandos.usuario_base.hoy_superusuario' : 'comandos.usuario_base.hoy_normal', ['rol' => $actual->rol]));
        $this->newLine();

        foreach (explode('
', $this->sql($base, $dueno, $app)) as $linea) {
            $this->line($linea);
        }
        $this->newLine();
        $this->comment(__('comandos.usuario_base.como_ejecutar', ['base' => $base, 'dueno' => $dueno, 'app' => $app]));

        return self::SUCCESS;
    }

    /** Solo letras minúsculas, números y guion bajo: el nombre se escribe tal cual en el SQL. */
    private function identificador(string $nombre): ?string
    {
        return preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $nombre) ? $nombre : null;
    }

    private function sql(string $base, string $dueno, string $app): string
    {
        return <<<SQL
            -- 1. Roles (las contraseñas llegan como variables de psql: no quedan en este archivo)
            CREATE ROLE {$dueno} LOGIN PASSWORD :'clave_dueno' NOSUPERUSER NOCREATEDB NOCREATEROLE;
            CREATE ROLE {$app} LOGIN PASSWORD :'clave_app' NOSUPERUSER NOCREATEDB NOCREATEROLE;

            -- 2. El dueño queda dueño de la base, del esquema, de las tablas, secuencias y funciones (para migrar)
            ALTER DATABASE "{$base}" OWNER TO {$dueno};
            ALTER SCHEMA public OWNER TO {$dueno};
            DO \$\$
            DECLARE objeto record;
            BEGIN
                FOR objeto IN SELECT tablename FROM pg_tables WHERE schemaname = 'public' LOOP
                    EXECUTE format('ALTER TABLE public.%I OWNER TO {$dueno}', objeto.tablename);
                END LOOP;
                FOR objeto IN SELECT sequencename FROM pg_sequences WHERE schemaname = 'public' LOOP
                    EXECUTE format('ALTER SEQUENCE public.%I OWNER TO {$dueno}', objeto.sequencename);
                END LOOP;
                FOR objeto IN SELECT p.oid::regprocedure AS firma FROM pg_proc p JOIN pg_namespace n ON n.oid = p.pronamespace WHERE n.nspname = 'public' LOOP
                    EXECUTE format('ALTER FUNCTION %s OWNER TO {$dueno}', objeto.firma);
                END LOOP;
            END
            \$\$;

            -- 3. La aplicación solo lee y escribe datos: no puede crear, cambiar ni borrar tablas
            REVOKE CREATE ON SCHEMA public FROM PUBLIC;
            GRANT CONNECT ON DATABASE "{$base}" TO {$app};
            GRANT USAGE ON SCHEMA public TO {$app};
            GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO {$app};
            GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO {$app};
            -- Lo mismo para las tablas que creen las migraciones futuras
            ALTER DEFAULT PRIVILEGES FOR ROLE {$dueno} IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO {$app};
            ALTER DEFAULT PRIVILEGES FOR ROLE {$dueno} IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO {$app};

            -- 4. Bitácora: solo inserción también por permisos (además del trigger)
            REVOKE UPDATE, DELETE, TRUNCATE ON public.bitacora FROM {$app};
            SQL;
    }
}
