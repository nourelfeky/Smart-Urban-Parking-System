<?php

declare(strict_types=1);

final class CsvFixtureReader
{
    public static function catalogPath(): string
    {
        return dirname(__DIR__) . '/data/black_white_box_test_report.csv';
    }

    /**
     * @return list<array<string,string>>
     */
    public static function allRows(): array
    {
        $path = self::catalogPath();
        if (!is_readable($path)) {
            return [];
        }
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            return [];
        }
        $delimiter = "\t";
        $header = fgetcsv($fh, 0, $delimiter);
        if ($header === false) {
            fclose($fh);

            return [];
        }
        $header = array_map(static fn (string $h): string => trim($h), $header);
        $rows = [];
        while (($data = fgetcsv($fh, 0, $delimiter)) !== false) {
            if (count($data) === 1 && trim((string)$data[0]) === '') {
                continue;
            }
            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = isset($data[$i]) ? trim((string)$data[$i]) : '';
            }
            if (($row['test_id'] ?? '') !== '') {
                $rows[] = $row;
            }
        }
        fclose($fh);

        return $rows;
    }

    /**
     * @return list<array<string,string>>
     */
    public static function rowsForFunction(string $functionKey): array
    {
        return array_values(array_filter(
            self::allRows(),
            static fn (array $r): bool => ($r['function_key'] ?? '') === $functionKey
        ));
    }
}
