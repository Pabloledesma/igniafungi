<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Coupon::create([
            'code' => 'IGNIAFRESCA2026',
            'discount_type' => 'percentage',
            'discount_value' => 15.00,
            'active' => true,
        ]);
    }
}
