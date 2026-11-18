<?php
require_once __DIR__ . '/config.php';

class ConexionPDO
{
    private static $cnn;
    private static $schemaColumns = [];

    public function __construct()
    {
        self::Conexion();
    }

    public static function Conexion()
    {
        if (self::$cnn === null) {
            $dsn = 'mysql:host=' . HOST . ';port=' . PORT . ';dbname=' . DATABASE . ';charset=' . CHARSET;
            self::$cnn = new PDO($dsn, USERNAME, PASSWORD, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }

        return self::$cnn;
    }

    public static function ExecuteQuery($sql, $params = [])
    {
        $statement = self::Conexion()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public static function ExecuteStatement($sql, $params = [])
    {
        $statement = self::Conexion()->prepare($sql);
        $statement->execute($params);
        return $statement->rowCount();
    }

    public static function InsertarSql($sql, $params = [])
    {
        $statement = self::Conexion()->prepare($sql);
        $statement->execute($params);
        return (int) self::Conexion()->lastInsertId();
    }

    public static function updateSql($sql, $params = [])
    {
        return self::ExecuteStatement($sql, $params);
    }

    public static function deleteSql($sql, $params = [])
    {
        return self::ExecuteStatement($sql, $params);
    }

    public static function HasColumn($table, $column)
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)
            || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column)) {
            throw new InvalidArgumentException('Nombre de tabla o columna no válido.');
        }
        if (!isset(self::$schemaColumns[$table])) {
            $statement = self::Conexion()->query("SHOW COLUMNS FROM `{$table}`");
            self::$schemaColumns[$table] = [];
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $definition) {
                self::$schemaColumns[$table][$definition['Field']] = $definition['Extra'];
            }
        }
        return array_key_exists($column, self::$schemaColumns[$table]);
    }

    public static function IsGeneratedColumn($table, $column)
    {
        return self::HasColumn($table, $column)
            && stripos(self::$schemaColumns[$table][$column], 'GENERATED') !== false;
    }
}
