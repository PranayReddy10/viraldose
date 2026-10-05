<?php

namespace App\Support;

use Generator;
use RuntimeException;

/**
 * Streams rows out of a MySQL / MariaDB dump (phpMyAdmin or mysqldump) without
 * needing a database connection: finds `INSERT INTO `table` (cols) VALUES (...)`
 * statements and yields each tuple as an associative array.
 */
class SqlDumpReader
{
    public function __construct(private string $path)
    {
        if (! is_readable($path)) {
            throw new RuntimeException("Cannot read SQL file: {$path}");
        }
    }

    /**
     * Tables that have INSERT statements in the dump.
     *
     * @return array<int, string>
     */
    public function tables(): array
    {
        $sql = file_get_contents($this->path);
        preg_match_all('/INSERT INTO `?([A-Za-z0-9_]+)`?\s*(?:\([^)]*\))?\s*VALUES/i', $sql, $m);

        return array_values(array_unique($m[1]));
    }

    /**
     * @return Generator<int, array<string, string|null>>
     */
    public function rows(string $table): Generator
    {
        $sql = file_get_contents($this->path);
        $pattern = '/INSERT INTO `?'.preg_quote($table, '/').'`?\s*(?:\(([^)]*)\))?\s*VALUES\s*/i';
        $offset = 0;
        $columns = null;

        if (preg_match('/CREATE TABLE `?'.preg_quote($table, '/').'`?\s*\((.*?)\)\s*ENGINE/is', $sql, $create)) {
            preg_match_all('/^\s*`([A-Za-z0-9_]+)`/m', $create[1], $cm);
            $columns = $cm[1] ?: null;
        }

        while (preg_match($pattern, $sql, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $cols = ! empty($m[1][0]) ? array_map(fn ($c) => trim($c, " `\r\n\t"), explode(',', $m[1][0])) : $columns;
            if (! $cols) {
                throw new RuntimeException("Cannot determine the column list for table {$table}.");
            }
            $pos = $m[0][1] + strlen($m[0][0]);
            foreach ($this->tuples($sql, $pos) as $tuple) {
                if (count($tuple) !== count($cols)) {
                    continue;
                }
                yield array_combine($cols, $tuple);
            }
            $offset = $pos;
        }
    }

    /**
     * Parses "(v, v, ...), (v, ...);" starting at $pos and advances $pos past the ';'.
     *
     * @return Generator<int, array<int, string|null>>
     */
    private function tuples(string $s, int &$pos): Generator
    {
        $n = strlen($s);
        while ($pos < $n) {
            while ($pos < $n && strpos(" \r\n\t,", $s[$pos]) !== false) {
                $pos++;
            }
            if ($pos >= $n || $s[$pos] !== '(') {
                break;
            }
            $pos++;
            $row = [];
            while ($pos < $n) {
                while (strpos(" \r\n\t", $s[$pos]) !== false) {
                    $pos++;
                }
                $c = $s[$pos];
                if ($c === "'" || $c === '"') {
                    $quote = $c;
                    $pos++;
                    $buf = '';
                    while ($pos < $n) {
                        $ch = $s[$pos];
                        if ($ch === '\\') {
                            $next = $s[$pos + 1] ?? '';
                            $buf .= match ($next) {
                                'n' => "\n", 'r' => "\r", 't' => "\t", '0' => "\0", 'Z' => "\x1a", 'b' => "\x08",
                                default => $next,
                            };
                            $pos += 2;

                            continue;
                        }
                        if ($ch === $quote) {
                            if (($s[$pos + 1] ?? '') === $quote) {
                                $buf .= $quote;
                                $pos += 2;

                                continue;
                            }
                            $pos++;
                            break;
                        }
                        $buf .= $ch;
                        $pos++;
                    }
                    $row[] = $buf;
                } else {
                    $start = $pos;
                    while ($pos < $n && $s[$pos] !== ',' && $s[$pos] !== ')') {
                        $pos++;
                    }
                    $tok = trim(substr($s, $start, $pos - $start));
                    $row[] = strcasecmp($tok, 'NULL') === 0 ? null : $tok;
                }
                while (strpos(" \r\n\t", $s[$pos]) !== false) {
                    $pos++;
                }
                if ($s[$pos] === ',') {
                    $pos++;

                    continue;
                }
                if ($s[$pos] === ')') {
                    $pos++;
                    break;
                }
            }
            yield $row;
            while ($pos < $n && strpos(" \r\n\t", $s[$pos]) !== false) {
                $pos++;
            }
            if ($pos < $n && $s[$pos] === ';') {
                $pos++;

                return;
            }
        }
    }
}
