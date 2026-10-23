<?php

namespace Tests\Feature;

use App\Models\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockBatchNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_stock_receives_a_dynamic_batch_number(): void
    {
        $firstStock = Stock::create([
            'batch_no' => 'CLIENT-SUPPLIED',
        ]);
        $secondStock = Stock::create();

        $this->assertMatchesRegularExpression(
            '/^\d{6}-\d{4}$/',
            $firstStock->batch_no
        );
        $this->assertNotSame($firstStock->batch_no, $secondStock->batch_no);
        $this->assertDatabaseHas('stocks', [
            'id' => $firstStock->id,
            'batch_no' => $firstStock->batch_no,
        ]);
    }
}
