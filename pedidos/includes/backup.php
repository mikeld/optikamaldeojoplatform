<?php

function backup_resolver_tablas_existentes(PDO $pdo, array $tablas_permitidas): array {
    $stmt = $pdo->query('SHOW TABLES');
    $existentes = [];
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $existentes[$row[0]] = true;
    }

    $out = [];
    foreach ($tablas_permitidas as $t) {
        if (isset($existentes[$t])) $out[] = $t;
    }
    return $out;
}

function backup_columnas_tabla(PDO $pdo, string $tabla): array {
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$tabla}`");
    $cols = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['Field'])) $cols[] = $row['Field'];
    }
    return $cols;
}

function backup_escribir_csv_tabla(PDO $pdo, string $tabla, $fh, string $delimiter = ';'): void {
    $cols = backup_columnas_tabla($pdo, $tabla);
    fputcsv($fh, $cols, $delimiter);

    $stmt = $pdo->query("SELECT * FROM `{$tabla}`");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $line = [];
        foreach ($cols as $c) {
            $line[] = $row[$c] ?? null;
        }
        fputcsv($fh, $line, $delimiter);
    }
}

function backup_generar_sql(PDO $pdo, array $tablas, $fh): void {
    fwrite($fh, "-- Optikamaldeojo - Backup SQL\n");
    fwrite($fh, "-- Generado: " . date('Y-m-d H:i:s') . "\n\n");
    fwrite($fh, "SET NAMES utf8mb4;\n");
    fwrite($fh, "SET FOREIGN_KEY_CHECKS=0;\n\n");

    foreach ($tablas as $tabla) {
        fwrite($fh, "-- ----------------------------\n");
        fwrite($fh, "-- Estructura de `{$tabla}`\n");
        fwrite($fh, "-- ----------------------------\n");

        $createRow = $pdo->query("SHOW CREATE TABLE `{$tabla}`")->fetch(PDO::FETCH_NUM);
        $createSql = $createRow[1] ?? '';

        if ($createSql === '') {
            fwrite($fh, "-- (No se pudo obtener CREATE TABLE)\n\n");
            continue;
        }

        fwrite($fh, "DROP TABLE IF EXISTS `{$tabla}`;\n");
        fwrite($fh, $createSql . ";\n\n");

        fwrite($fh, "-- ----------------------------\n");
        fwrite($fh, "-- Datos de `{$tabla}`\n");
        fwrite($fh, "-- ----------------------------\n");

        $cols = backup_columnas_tabla($pdo, $tabla);
        $colsSql = implode(',', array_map(function($c) { return "`{$c}`"; }, $cols));

        $stmt = $pdo->query("SELECT * FROM `{$tabla}`");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $valsSql = [];
            foreach ($cols as $c) {
                $val = $row[$c] ?? null;
                if ($val === null) {
                    $valsSql[] = 'NULL';
                } else {
                    $valsSql[] = $pdo->quote((string)$val);
                }
            }
            fwrite($fh, "INSERT INTO `{$tabla}` ({$colsSql}) VALUES (" . implode(',', $valsSql) . ");\n");
        }

        fwrite($fh, "\n");
    }

    fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
}

function backup_crear_zip(PDO $pdo, array $tablas, bool $incluir_sql, string $zip_path, string $delimiter = ';'): void {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive no está disponible en el servidor.');
    }

    $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'optika_backup_' . bin2hex(random_bytes(6));
    if (!mkdir($tmpDir, 0700, true) && !is_dir($tmpDir)) {
        throw new RuntimeException('No se pudo crear directorio temporal para el backup.');
    }

    $filesToCleanup = [];
    try {
        // CSVs
        foreach ($tablas as $tabla) {
            $csvPath = $tmpDir . DIRECTORY_SEPARATOR . $tabla . '.csv';
            $fh = fopen($csvPath, 'w');
            if (!$fh) throw new RuntimeException("No se pudo crear el CSV de {$tabla}.");

            // BOM UTF-8 para Excel
            fwrite($fh, "\xEF\xBB\xBF");
            backup_escribir_csv_tabla($pdo, $tabla, $fh, $delimiter);
            fclose($fh);

            $filesToCleanup[] = $csvPath;
        }

        // SQL (opcional)
        if ($incluir_sql) {
            $sqlPath = $tmpDir . DIRECTORY_SEPARATOR . 'backup.sql';
            $fh = fopen($sqlPath, 'w');
            if (!$fh) throw new RuntimeException('No se pudo crear el backup SQL.');
            backup_generar_sql($pdo, $tablas, $fh);
            fclose($fh);
            $filesToCleanup[] = $sqlPath;
        }

        // README
        $readmePath = $tmpDir . DIRECTORY_SEPARATOR . 'README.txt';
        file_put_contents(
            $readmePath,
            "Backup Optikamaldeojo\n" .
            "Generado: " . date('Y-m-d H:i:s') . "\n" .
            "Incluye: " . implode(', ', $tablas) . ($incluir_sql ? " + SQL\n" : "\n")
        );
        $filesToCleanup[] = $readmePath;

        // ZIP
        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el ZIP del backup.');
        }

        foreach ($filesToCleanup as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();
    } finally {
        foreach ($filesToCleanup as $f) {
            if (is_file($f)) @unlink($f);
        }
        if (is_dir($tmpDir)) @rmdir($tmpDir);
    }
}

