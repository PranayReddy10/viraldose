<?php

namespace App\Support;

/**
 * Display-time fixes for tables inside article HTML:
 * - wraps every table in <div class="table-wrap"> so wide tables scroll inside the column
 *   instead of pushing the page sideways on phones;
 * - repairs "key facts" tables that an earlier editor bug flattened into ONE row
 *   (label, value, label, value, …) by splitting them back into two-column rows.
 */
class ContentTables
{
    public static function prepare(string $html): string
    {
        if (stripos($html, '<table') === false) {
            return $html;
        }

        return (string) preg_replace_callback(
            '/(<div class="table-wrap">\s*)?(<table\b[^>]*>.*?<\/table>)/is',
            fn (array $m) => $m[1] !== ''
                ? $m[1].static::repairFlattened($m[2])                    // already wrapped
                : '<div class="table-wrap">'.static::repairFlattened($m[2]).'</div>',
            $html
        );
    }

    private static function repairFlattened(string $table): string
    {
        if (preg_match_all('/<tr\b/i', $table) !== 1) {
            return $table;
        }
        preg_match_all('/<td\b[^>]*>.*?<\/td>/is', $table, $cells);
        $cells = $cells[0];
        if (count($cells) < 6 || count($cells) % 2 !== 0 || stripos($table, '<th') !== false) {
            return $table;
        }
        $rows = '';
        foreach (array_chunk($cells, 2) as [$label, $value]) {
            $label = preg_replace('/^<td\b[^>]*>(.*)<\/td>$/is', '<th scope="row">$1</th>', $label);
            $rows .= '<tr>'.$label.$value.'</tr>';
        }

        return '<table><tbody>'.$rows.'</tbody></table>';
    }
}
