<?php
/**
 * Strapi schema.json to SQL DDL Generator
 * Compatible with PHP 7.4
 */

$srcApiDir = dirname(__DIR__, 3) . '/src/api';
$migrationsDir = __DIR__;
$outputSqlFile = $migrationsDir . '/001_initial_schema.sql';

if (!is_dir($srcApiDir)) {
    die("Error: Source API directory not found at $srcApiDir\n");
}

function pluralizeToSnakeCase($string) {
    $string = trim($string);
    $string = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $string));
    $string = str_replace('-', '_', $string);
    
    if (substr($string, -1) === 'y') {
        $string = substr($string, 0, -1) . 'ies';
    } elseif (substr($string, -1) !== 's') {
        $string .= 's';
    }
    return $string;
}

function mapStrapiTypeToSql($attributeMeta) {
    if (!isset($attributeMeta['type'])) {
        return "VARCHAR(255) NULL";
    }
    $type = $attributeMeta['type'];
    $isRequired = !empty($attributeMeta['required']) ? 'NOT NULL' : 'NULL';
    
    switch ($type) {
        case 'string':
        case 'uid':
        case 'email':
        case 'password':
        case 'enumeration':
            return "VARCHAR(255) {$isRequired}";
        case 'text':
        case 'richtext':
        case 'json':
        case 'component':
        case 'dynamiczone':
            return "TEXT {$isRequired}";
        case 'integer':
        case 'relation':
        case 'media':
            return "INT {$isRequired}";
        case 'biginteger':
            return "BIGINT {$isRequired}";
        case 'float':
        case 'decimal':
            return "DECIMAL(10,2) {$isRequired}";
        case 'boolean':
            $default = !empty($attributeMeta['default']) ? '1' : '0';
            return "TINYINT(1) DEFAULT {$default}";
        case 'date':
            return "DATE {$isRequired}";
        case 'datetime':
            return "DATETIME {$isRequired}";
        case 'time':
            return "TIME {$isRequired}";
        default:
            return "VARCHAR(255) {$isRequired}";
    }
}

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcApiDir));
$schemaFiles = [];

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getFilename() === 'schema.json') {
        $schemaFiles[] = $file->getPathname();
    }
}

$sqlQueries = [];

// Base users table
$sqlQueries[] = "CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(255) NULL,
    `email` VARCHAR(255) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `provider` VARCHAR(255) DEFAULT 'local',
    `confirmed` TINYINT(1) DEFAULT 0,
    `blocked` TINYINT(1) DEFAULT 0,
    `role_id` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);";

foreach ($schemaFiles as $file) {
    $content = file_get_contents($file);
    if (!$content) continue;
    $schema = json_decode($content, true);
    if (!$schema) continue;

    $info = $schema['info'] ?? [];
    $attributes = $schema['attributes'] ?? [];

    $singularName = $info['singularName'] ?? basename(dirname($file));
    $tableName = pluralizeToSnakeCase($singularName);
    $tableName = preg_replace('/[^a-z0-9_]/', '', $tableName);

    if (empty($tableName)) continue;

    $columns = [];
    $columns[] = "`id` INT AUTO_INCREMENT PRIMARY KEY";
    
    foreach ($attributes as $attrName => $attrMeta) {
        $attrNameClean = preg_replace('/[^a-zA-Z0-9_]/', '', str_replace('-', '_', $attrName));
        
        if (isset($attrMeta['type']) && $attrMeta['type'] === 'relation') {
            if (isset($attrMeta['relation'])) {
                // Skip relationships that need mapping tables for simplicity of this base schema
                if (strpos($attrMeta['relation'], 'manyToMany') !== false || strpos($attrMeta['relation'], 'oneToMany') !== false) {
                    continue; 
                }
            }
            if (substr($attrNameClean, -3) !== '_id') {
                $attrNameClean .= '_id';
            }
        }
        
        if (isset($attrMeta['type']) && $attrMeta['type'] === 'media') {
            if (substr($attrNameClean, -3) !== '_id') {
                $attrNameClean .= '_id'; // treating media as foreign keys to files table
            }
        }

        $sqlType = mapStrapiTypeToSql($attrMeta);
        $columns[] = "`{$attrNameClean}` {$sqlType}";
    }
    
    $columns[] = "`is_deleted` TINYINT(1) DEFAULT 0";
    $columns[] = "`created_at` DATETIME DEFAULT CURRENT_TIMESTAMP";
    $columns[] = "`updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";

    $columnsString = implode(",\n    ", $columns);
    $createTableQuery = "CREATE TABLE IF NOT EXISTS `{$tableName}` (\n    {$columnsString}\n);";
    $sqlQueries[] = $createTableQuery;
}

$finalSql = implode("\n\n", $sqlQueries);

if (file_put_contents($outputSqlFile, $finalSql)) {
    echo "Successfully generated initial database schema at: $outputSqlFile\n";
    echo "Total tables mapped: " . count($schemaFiles) . "\n";
} else {
    echo "Failed to write SQL file.\n";
}
