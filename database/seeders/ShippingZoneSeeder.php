<?php

namespace Database\Seeders;

use App\Models\ShippingZone;
use Illuminate\Database\Seeder;

class ShippingZoneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Bogotá Localities
        // Precios estimados para ejemplo
        $bogotaZones = [
            ['locality' => 'Engativá', 'price' => 9000],
            ['locality' => 'Fontibón', 'price' => 9500],
            ['locality' => 'Barrios Unidos', 'price' => 10000],
            ['locality' => 'Teusaquillo', 'price' => 10500],
            ['locality' => 'Suba', 'price' => 11000],
            ['locality' => 'Puente Aranda', 'price' => 12000],
            ['locality' => 'Chapinero', 'price' => 12500],
            ['locality' => 'Los Mártires', 'price' => 13000],
            ['locality' => 'Usaquén', 'price' => 13500],
            ['locality' => 'Kennedy', 'price' => 14000],
            ['locality' => 'Santa Fe', 'price' => 14500],
            ['locality' => 'La Candelaria', 'price' => 15000],
            ['locality' => 'Antonio Nariño', 'price' => 15500],
            ['locality' => 'Rafael Uribe Uribe', 'price' => 16000],
            ['locality' => 'Tunjuelito', 'price' => 16500],
            ['locality' => 'San Cristóbal', 'price' => 17500],
            ['locality' => 'Bosa', 'price' => 18000],
            ['locality' => 'Ciudad Bolívar', 'price' => 18500],
            ['locality' => 'Usme', 'price' => 19500],
            ['locality' => 'Sumapaz', 'price' => 20000],
        ];

        foreach ($bogotaZones as $zone) {
            ShippingZone::updateOrCreate(
                ['city' => 'Bogotá', 'locality' => $zone['locality']],
                ['price' => $zone['price']]
            );
        }

        // 2. Ciudades Principales (Envío nacional base)
        $cities = [
            ['city' => 'Medellín', 'price' => 22000],
            ['city' => 'Cali', 'price' => 22000],
            ['city' => 'Barranquilla', 'price' => 25000],
            ['city' => 'Cartagena', 'price' => 25000],
            ['city' => 'Bucaramanga', 'price' => 22000],
            ['city' => 'Pereira', 'price' => 20000],
            ['city' => 'Manizales', 'price' => 20000],
            ['city' => 'Cúcuta', 'price' => 25000],
            ['city' => 'Ibagué', 'price' => 18000],
            ['city' => 'Santa Marta', 'price' => 26000],
            ['city' => 'Villavicencio', 'price' => 18000],
            ['city' => 'Popayán', 'price' => 23000],
            ['city' => 'Pasto', 'price' => 24000],
            ['city' => 'Neiva', 'price' => 20000],
            ['city' => 'Armenia', 'price' => 20000],
            ['city' => 'Tunja', 'price' => 18000],
            ['city' => 'Montería', 'price' => 25000],
            ['city' => 'Sincelejo', 'price' => 25000],
            ['city' => 'Valledupar', 'price' => 25000],
            ['city' => 'Riohacha', 'price' => 26000],
            ['city' => 'Leticia', 'price' => 35000],
        ];

        foreach ($cities as $city) {
            ShippingZone::updateOrCreate(
                ['city' => $city['city'], 'locality' => null],
                ['price' => $city['price']]
            );
        }
    }
}
