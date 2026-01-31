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
            ['locality' => 'Usaquén', 'price' => 12000],
            ['locality' => 'Chapinero', 'price' => 10000],
            ['locality' => 'Santa Fe', 'price' => 12000],
            ['locality' => 'San Cristóbal', 'price' => 15000],
            ['locality' => 'Usme', 'price' => 18000],
            ['locality' => 'Tunjuelito', 'price' => 15000],
            ['locality' => 'Bosa', 'price' => 15000],
            ['locality' => 'Kennedy', 'price' => 14000],
            ['locality' => 'Fontibón', 'price' => 14000],
            ['locality' => 'Engativá', 'price' => 12000],
            ['locality' => 'Suba', 'price' => 12000],
            ['locality' => 'Barrios Unidos', 'price' => 11000],
            ['locality' => 'Teusaquillo', 'price' => 11000],
            ['locality' => 'Los Mártires', 'price' => 12000],
            ['locality' => 'Antonio Nariño', 'price' => 12000],
            ['locality' => 'Puente Aranda', 'price' => 13000],
            ['locality' => 'La Candelaria', 'price' => 12000],
            ['locality' => 'Rafael Uribe Uribe', 'price' => 14000],
            ['locality' => 'Ciudad Bolívar', 'price' => 18000], // Zona lejana
            ['locality' => 'Sumapaz', 'price' => 25000], // Zona rural
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
        ];

        foreach ($cities as $city) {
            ShippingZone::updateOrCreate(
                ['city' => $city['city'], 'locality' => null],
                ['price' => $city['price']]
            );
        }
    }
}
