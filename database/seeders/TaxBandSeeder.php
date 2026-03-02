<?php

namespace Database\Seeders;

use App\Models\TaxBand;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TaxBandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing tax bands
        TaxBand::truncate();

        // Kenya tax bands based on the provided data
        $taxBands = [
            [
                'range_from' => 0,
                'range_to' => 24000,
                'tax_rate' => 10.00,
                'description' => '0 – 24,000',
                'sort_order' => 1,
                'is_active' => true,
                'added_by' => 'system'
            ],
            [
                'range_from' => 24001,
                'range_to' => 32333,
                'tax_rate' => 25.00,
                'description' => 'On the next 8,333',
                'sort_order' => 2,
                'is_active' => true,
                'added_by' => 'system'
            ],
            [
                'range_from' => 32334,
                'range_to' => 500000,
                'tax_rate' => 30.00,
                'description' => 'On the next 467,667',
                'sort_order' => 3,
                'is_active' => true,
                'added_by' => 'system'
            ],
            [
                'range_from' => 500001,
                'range_to' => 800000,
                'tax_rate' => 32.50,
                'description' => 'On the next 300,000',
                'sort_order' => 4,
                'is_active' => true,
                'added_by' => 'system'
            ],
            [
                'range_from' => 800001,
                'range_to' => null,
                'tax_rate' => 35.00,
                'description' => 'On amounts over 800,000',
                'sort_order' => 5,
                'is_active' => true,
                'added_by' => 'system'
            ]
        ];

        foreach ($taxBands as $band) {
            TaxBand::create($band);
        }
    }
}
