<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Batch;
use App\Models\Phase;
use App\Models\Recipe;
use App\Models\Strain;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
   public function run(): void
    {
        $pablo = User::factory()->create(['name' => 'Pablo', 'email' => 'gerencia@igniafungi.com', 'password' => Hash::make('password')]);
        $alexa = User::factory()->create(['name' => 'Alexa', 'email' => 'comercial@igniafungi.com', 'password' => Hash::make('password')]);

        $catGourmet = Category::factory()->create(['name' => 'Hongos Gourmet', 'slug' => 'hongos-gourmet', 'is_active' => true]);
        $catMedicina = Category::factory()->create(['name' => 'Medicina Ancestral', 'slug' => 'medicina-ancestral', 'is_active' => true]);
        $catSagradas = Category::factory()->create(['name' => 'Especies Sagradas', 'slug' => 'especies-sagradas', 'is_active' => true]);
        $catGenetica = Category::factory()->create(['name' => 'Genética', 'slug' => 'genetica', 'is_active' => true]);
        $catSuministros = Category::factory()->create(['name' => 'Suministros', 'slug' => 'suministros', 'is_active' => true]);

        $strainMelena = Strain::factory()->create(['name' => 'Melena de león', 'slug' => 'melena-de-leon', 'incubation_days' => 15]);
        $strainRosada = Strain::factory()->create(['name' => 'Orellana rosada', 'slug' => 'orellana-rosada', 'incubation_days' => 15]);
        $strainGolden = Strain::factory()->create(['name' => 'Golden Teacher', 'slug' => 'golden-teacher', 'incubation_days' => 20]);
        $strainPioppino = Strain::factory()->create(['name' => 'Pioppino', 'slug' => 'pioppino', 'incubation_days' => 20]);
        $strainFlorida = Strain::factory()->create(['name' => 'Orellana Florida', 'slug' => 'orellana-florida', 'incubation_days' => 15]);
        $strainEryngii = Strain::factory()->create(['name' => 'Eryngii', 'slug' => 'eryngii', 'incubation_days' => 30]);
        $strainShiitake = Strain::factory()->create(['name' => 'Shiitake', 'slug' => 'shiitake', 'incubation_days' => 60]);
        $strainReishii = Strain::factory()->create(['name' => 'Reishii', 'slug' => 'reishii', 'incubation_days' => 30]);

        // 1. Crear Fases maestras
        $phasesData = [
            ['name' => 'Inoculación', 'slug' => 'inoculation', 'order' => 1],
            ['name' => 'Incubación', 'slug' => 'incubation', 'order' => 2],
            ['name' => 'Fructificación', 'slug' => 'fruiting', 'order' => 3],
            ['name' => 'Cosecha', 'slug' => 'harvest', 'order' => 4],
        ];

        foreach ($phasesData as $data) {
            Phase::updateOrCreate(['slug' => $data['slug']], $data);
        }

        $phases = Phase::orderBy('order')->get();
        $recipe = Recipe::factory()->create(['name' => 'Receta Base Estándar']);

        // 2. Crear un Lote de ejemplo en cada fase para probar el Kanban
        foreach ($phases as $phase) {
            $batch = Batch::create([
                'user_id' => $pablo->id,
                'recipe_id' => $recipe->id,
                'code' => "Lote " . Str::upper(Str::random(5)),
                'strain_id' => Strain::inRandomOrder()->first()->id ?? 1,
                'recipe_id' => 1, // Asumiendo que existe una receta base
                'quantity' => rand(50, 100),
                'weigth_dry' => rand(50, 100),
                'inoculation_date' => now(),
                'status' => 'active',
            ]);

            // Asociar la fase actual al lote
            $batch->phases()->attach($phase->id, [
                'user_id' => $pablo->id,
                'started_at' => now()->subDays(rand(1, 10)),
                'notes' => 'Lote generado por el seeder para pruebas de UI.'
            ]);

            // Agregar una merma aleatoria a un lote de Incubación
            if ($phase->slug === 'incubation') {
                $batch->recordLoss(5, 'Contaminación detectada', $pablo->id, 'Pequeña mancha de moho verde.');
            }
        }

        Product::factory()->create([
            'name' => 'Melena de León fresca',
            'description' => 'La Melena de León no solo cautiva por su exótica apariencia de cascada blanca; es uno de los nootrópicos más poderosos de la naturaleza. Utilizada históricamente en la medicina ancestral, la ciencia moderna ha confirmado su capacidad para estimular el Factor de Crecimiento Nervioso (NGF). Es el complemento ideal para quienes buscan potenciar su enfoque, memoria y salud del sistema nervioso, alineándose con la fuerza transformadora de Ignia Fungi.',
            'slug' => 'melena-de-leon-fresca',
            'price' => 35000,
            'in_stock' => true,
            'category_id' => $catMedicina->id, 
            'strain_id' => $strainMelena->id,   
            'is_active' => true,
        ]);

        Product::factory()->create([
            'name' => 'Melena de León deshidratada',
            'slug' => 'melena-de-leon-deshidratada',
            'description' => 'La Melena de León no solo cautiva por su exótica apariencia de cascada blanca; es uno de los nootrópicos más poderosos de la naturaleza. Utilizada históricamente en la medicina ancestral, la ciencia moderna ha confirmado su capacidad para estimular el Factor de Crecimiento Nervioso (NGF). Es el complemento ideal para quienes buscan potenciar su enfoque, memoria y salud del sistema nervioso, alineándose con la fuerza transformadora de Ignia Fungi.',
            'price' => 90000,
            'in_stock' => true,
            'category_id' => $catMedicina->id, 
            'strain_id' => $strainMelena->id,   
            'is_active' => true,
        ]);

        Product::factory()->create([
            'name' => 'Orellana Rosada fresca',
            'slug' => 'orellana-rosada-fresca',
            'description' => 'Conocida como el (Hongo de los Trópicos), la Orellana Rosada es una de las joyas más hermosas del reino fungi. Su intenso color rosado y su forma de abanico no solo la convierten en un espectáculo visual, sino también en una delicia culinaria. Al ser cocinada, desarrolla un sabor robusto y una textura carnosa que recuerda ligeramente al tocino o al salmón. Es una fuente excelente de proteínas y antioxidantes, cultivada con la dedicación y el respeto por los ciclos naturales que definen a Ignia Fungi.',
            'price' => 22000,
            'in_stock' => true,
            'category_id' => $catGourmet->id,
            'strain_id' => $strainRosada->id,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'name' => 'Pioppino',
            'slug' => 'pioppino',
            'description' => 'El Pioppino',
            'price' => 30000,
            'in_stock' => true,
            'category_id' => $catGourmet->id,
            'strain_id' => $strainPioppino->id,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'name' => 'Eryngii',
            'slug' => 'eryngii',
            'short_description' => 'El rey de las orellanas. De textura densa y sabor umami que recuerda a la carne, es el favorito de los chefs para asar y sellar.',
            'description' => 'El Pleurotus eryngii se distingue por su tallo grueso y carnoso, el cual es la parte más apreciada. A diferencia de otras orellanas, su textura se mantiene firme tras la cocción, permitiendo cortes similares a medallones. Es una fuente rica en ergotioneína, un potente antioxidante que apoya la salud celular.',
            'price' => 32000,
            'in_stock' => true,
            'category_id' => $catGourmet->id,
            'strain_id' => $strainEryngii->id,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'name' => 'Orellana Florida',
            'slug' => 'orellana-florida',
            'short_description' => 'Delicada, versátil y nutritiva. Una cepa clásica de sabor suave que se adapta perfectamente a salteados, pastas y sopas.',
            'description' => 'La Orellana Florida (Pleurotus ostreatus) es reconocida por sus sombreros en forma de abanico y su color blanquecino. Es el hongo ideal para quienes se inician en la gastronomía fungi debido a su sabor sutil y su capacidad para absorber los aromas de los condimentos. Alta en proteínas y fibra, es un aliado esencial en dietas vegetales.',
            'price' => 20000,
            'in_stock' => true,
            'category_id' => $catGourmet->id,
            'strain_id' => $strainFlorida->id,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'name' => 'Shiitake',
            'slug' => 'shiitake',
            'short_description' => 'El hongo de la longevidad. Sabor intenso amaderado con propiedades que fortalecen el sistema inmunológico y la vitalidad corporal.',
            'description' => 'Originario del este de Asia, el Shiitake (Lentinula edodes) es valorado tanto por su sabor profundo como por su compuesto lentinano. En la cocina, aporta una profundidad de sabor umami inigualable. Sus beneficios medicinales incluyen el apoyo a la salud cardiovascular y la mejora de la respuesta defensiva del organismo.',
            'price' => 30000,
            'in_stock' => true,
            'category_id' => $catGourmet->id,
            'strain_id' => $strainFlorida->id,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'name' => 'Vial Golden Teacher 10ml',
            'slug' => 'vial-golden-teacher',
            'price' => 30.00,
            'in_stock' => true,
            'category_id' => $catSagradas->id,
            'strain_id' => $strainGolden->id,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'name' => 'Geringa Hericium 10ml',
            'slug' => 'geringa-hericium',
            'price' => 25000,
            'in_stock' => true,
            'category_id' => $catGenetica->id,
            'strain_id' => $strainMelena->id,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'name' => 'Geringa Orellana Rosada 10ml',
            'slug' => 'geringa-pink',
            'price' => 25000,
            'in_stock' => true,
            'category_id' => $catGenetica->id,
            'strain_id' => $strainRosada->id,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'name' => 'Geringa Orellana Florida 10ml',
            'slug' => 'geringa-florida',
            'price' => 25000,
            'in_stock' => true,
            'category_id' => $catGenetica->id,
            'strain_id' => $strainFlorida->id,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'name' => 'Geringa Eryngii 10ml',
            'slug' => 'geringa-eryngii',
            'price' => 25000,
            'in_stock' => true,
            'category_id' => $catGenetica->id,
            'strain_id' => $strainEryngii->id,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'name' => 'Geringa Shiitake 10ml',
            'slug' => 'geringa-shiitake',
            'price' => 25000,
            'in_stock' => true,
            'category_id' => $catGenetica->id,
            'strain_id' => $strainShiitake->id,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'name' => 'Geringa Reishii 10ml',
            'slug' => 'geringa-reishii',
            'price' => 25000,
            'in_stock' => true,
            'category_id' => $catGenetica->id,
            'strain_id' => $strainReishii->id,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'name' => 'Sustrato esteril para hongos gurmet 3.5 kilos',
            'slug' => 'sustrato-esteril-para-hongos-gourmet-3.5-kilos',
            'short_description' => 'La base del éxito para tu cultivo. Mezclas balanceadas y esterilizadas, listas para recibir el micelio de tus cepas favoritas.',
            'description' => 'Nuestros sustratos están formulados con una mezcla óptima de aserrín de maderas duras, salvados y suplementos orgánicos. Cada bolsa pasa por un proceso de esterilización de 4 horas a 15 psi, garantizando un medio libre de contaminantes para que tu cepa de Ignia Fungi se desarrolle con máxima vigorosidad.',
            'price' => 25000,
            'in_stock' => true,
            'category_id' => $catSuministros->id,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'name' => 'Sustrato pasteurizado para hongos mágicos 2 kilos',
            'slug' => 'sustrato-pasteurizado-para-hongos-magicos-2-kilos',
            'price' => 25000,
            'in_stock' => true,
            'category_id' => $catSuministros->id,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'name' => 'Semilla de Melena de león 350 gr',
            'slug' => 'semilla-de-melena-de-leon-350-gr',
            'price' => 15000,
            'in_stock' => true,
            'category_id' => $catSuministros->id,
            'strain_id' => $strainMelena->id,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'name' => 'Semilla de Pioppino 350 gr',
            'slug' => 'semilla-de-pioppino-350-gr',
            'price' => 15000,
            'in_stock' => true,
            'category_id' => $catSuministros->id,
            'strain_id' => $strainPioppino->id,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'name' => 'Semilla de Orellana Florida 350 gr',
            'slug' => 'semilla-de-orellana-florida-350-gr',
            'price' => 15000,
            'in_stock' => true,
            'category_id' => $catSuministros->id,
            'strain_id' => $strainFlorida->id,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'name' => 'Semilla de Orellana Rosada 350 gr',
            'slug' => 'semilla-de-orellana-rosada-350-gr',
            'price' => 15000,
            'in_stock' => true,
            'category_id' => $catSuministros->id,
            'strain_id' => $strainRosada->id,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'name' => 'Semilla de Shiitake 350 gr',
            'slug' => 'semilla-de-shiitake-350-gr',
            'price' => 15000,
            'in_stock' => true,
            'category_id' => $catSuministros->id,
            'strain_id' => $strainShiitake->id,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'name' => 'Semilla de Reishii 350 gr',
            'slug' => 'semilla-de-reishii-350-gr',
            'short_description' => 'El hongo de la inmortalidad. Un pilar de la medicina oriental conocido por ser el adaptógeno definitivo contra el estrés y el insomnio.',
            'description' => 'El Reishi (Ganoderma lucidum) es un hongo leñoso no comestible en su forma fresca, pero invaluable en extractos. Actúa como un regulador del sistema nervioso, ayudando al cuerpo a adaptarse al estrés físico y emocional. Nuestra genética garantiza una alta concentración de triterpenos y polisacáridos.',
            'price' => 15000,
            'in_stock' => true,
            'category_id' => $catSuministros->id,
            'strain_id' => $strainReishii->id,
            'is_active' => true,
        ]);
    }
}
