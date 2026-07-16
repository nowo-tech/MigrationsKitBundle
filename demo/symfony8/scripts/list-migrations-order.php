#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Print demo migrations execution order and what each migration/validation does.
 * Run from project root: php scripts/list-migrations-order.php
 * Or: make list-migrations (from demo/symfony8).
 *
 * @see docs/DEMO_MIGRATIONS_ORDER.md
 */
$rows = [
    ['#', 'Version', 'Tipo', 'Acción / Validación'],
    ['1', '00000', 'Migración', 'Crear tabla kit_item (id, PK)'],
    ['2', '00000_validation', 'Validación', 'kit_item existe con id y PK'],
    ['3', '00001', 'Migración', 'Crear tabla kit_example (todos los tipos)'],
    ['4', '00001_validation', 'Validación', 'kit_example existe con id y PK (skip si borrada en 00004)'],
    ['5', '00002', 'Migración', 'Crear kit_user + ADD user_id en kit_item'],
    ['6', '00002_validation', 'Validación', 'kit_user y kit_item.user_id existen (skip si borrados)'],
    ['7', '00003', 'Migración', 'ADD FK kit_item.user_id -> kit_user.id (+ índice)'],
    ['8', '00003_validation', 'Validación', 'FK existe → OK; si no (ej. tras 00005) → skip'],
    ['9', '00004', 'Migración', 'DROP TABLE kit_example'],
    ['10', '00004_validation', 'Validación', 'kit_example no existe'],
    ['11', '00005', 'Migración', 'DROP índice y FK en kit_item (user_id)'],
    ['12', '00005_validation', 'Validación', 'FK e índice no existen (skip SQLite)'],
    ['13', '00006', 'Migración', 'DROP TABLE kit_user'],
    ['14', '00006_validation', 'Validación', 'kit_user no existe'],
    ['15', '00007', 'Migración', 'DROP COLUMN user_id en kit_item'],
    ['16', '00007_validation', 'Validación', 'user_id no existe en kit_item'],
    ['17', '00008', 'Migración', 'RENAME col_string -> col_title en kit_example'],
    ['18', '00008_validation', 'Validación', 'col_title existe, col_string no (skip si tabla no existe)'],
    ['19', '00009', 'Migración', 'MODIFY col_string_nullable length 200 en kit_example'],
    ['20', '00009_validation', 'Validación', 'col_string_nullable existe (skip si tabla no existe)'],
    ['21', '00010', 'Migración', 'ADD índice col_title + UNIQUE col_guid en kit_example'],
    ['22', '00010_validation', 'Validación', 'Índice y unique existen (skip si tabla/columnas faltan)'],
    ['23', '00011', 'Migración', 'CREATE TABLE kit_pk_demo (id, code, PK(id))'],
    ['24', '00011_validation', 'Validación', 'kit_pk_demo existe con id, code y PK'],
    ['25', '00012', 'Migración', 'DROP PRIMARY KEY en kit_pk_demo'],
    ['26', '00012_validation', 'Validación', 'kit_pk_demo sin PK (skip SQLite)'],
    ['27', '00013', 'Migración', 'ADD PRIMARY KEY (code) en kit_pk_demo'],
    ['28', '00013_validation', 'Validación', 'kit_pk_demo tiene PK (skip SQLite)'],
];

$widths = [];
foreach ($rows[0] as $i => $_) {
    $widths[$i] = max(array_map(static function ($r) use ($i) {
        return strlen($r[$i]);
    }, $rows));
}

$sep  = '+' . implode('+', array_map(static fn ($w) => str_repeat('-', $w + 2), $widths)) . '+';
$line = static function (array $row) use ($widths) {
    return '| ' . implode(' | ', array_map(static function ($cell, $i) use ($widths) {
        return str_pad($cell, $widths[$i]);
    }, $row, array_keys($row))) . ' |';
};

echo "\nOrden de ejecución — migraciones demo (Version20250223100000–00013)\n";
echo "Doctrine ejecuta por orden alfabético; cada _validation va justo después de su migración.\n\n";
echo $sep . "\n";
foreach ($rows as $i => $row) {
    echo $line($row) . "\n";
    if ($i === 0) {
        echo $sep . "\n";
    }
}
echo $sep . "\n";
echo "\nResumen: 14 migraciones + 14 validaciones = 28 pasos. Cada validación comprueba el efecto de su migración.\n";
echo "Detalle: docs/DEMO_MIGRATIONS_ORDER.md\n\n";
