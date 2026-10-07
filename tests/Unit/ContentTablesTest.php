<?php

namespace Tests\Unit;

use App\Support\ContentTables;
use PHPUnit\Framework\TestCase;

class ContentTablesTest extends TestCase
{
    public function test_tables_are_wrapped_once(): void
    {
        $html = '<p>a</p><table><tbody><tr><td>x</td><td>y</td></tr><tr><td>1</td><td>2</td></tr></tbody></table>';
        $out = ContentTables::prepare($html);
        $this->assertSame(1, substr_count($out, 'table-wrap'));
        $this->assertStringContainsString('<div class="table-wrap"><table>', $out);

        $already = '<div class="table-wrap"><table><tr><td>a</td></tr></table></div>';
        $this->assertSame($already, ContentTables::prepare($already));
        $this->assertSame('<p>no tables</p>', ContentTables::prepare('<p>no tables</p>'));
    }

    public function test_flattened_key_fact_table_is_split_into_rows(): void
    {
        $flat = '<table><tbody><tr><td>Date</td><td>Oct 6</td><td>FIR</td><td>Oct 7</td><td>Detained</td><td>260</td></tr></tbody></table>';
        $out = ContentTables::prepare($flat);
        $this->assertSame(3, substr_count($out, '<tr>'));
        $this->assertStringContainsString('<tr><th scope="row">Date</th><td>Oct 6</td></tr>', $out);
    }
}
