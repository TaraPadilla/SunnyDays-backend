<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Gasto;
use App\Models\Categoria;
use App\Models\Subcategoria;
use App\Models\Inmueble;
use Illuminate\Support\Str;

class GastosSeeder extends Seeder
{
    public function run()
    {
        // Obtener las categorías, subcategorías e inmuebles existentes
        $categorias = Categoria::all();
        $subcategorias = Subcategoria::all();
        $inmuebles = Inmueble::all();

        // Asegúrate de que haya datos en todas las tablas
        if ($categorias->isEmpty() || $subcategorias->isEmpty() || $inmuebles->isEmpty()) {
            $this->command->info("Debe haber categorías, subcategorías e inmuebles en la base de datos para que funcione el seeder.");
            return;
        }

        // Insertar datos de ejemplo para los gastos
        foreach ($inmuebles as $inmueble) {
            foreach (range(1, 10) as $index) { // Cambié el rango para generar 10 gastos
                // Seleccionar una categoría aleatoria
                $categoria = $categorias->random();
                
                // Obtener subcategorías que pertenecen a esta categoría
                $subcategoriasDeCategoria = $subcategorias->where('categoria_id', $categoria->id);
                
                // Si la categoría no tiene subcategorías, saltar a la siguiente iteración
                if ($subcategoriasDeCategoria->isEmpty()) {
                    continue;
                }
                
                // Seleccionar una subcategoría aleatoria que pertenezca a la categoría seleccionada
                $subcategoria = $subcategoriasDeCategoria->random();
                
                Gasto::create([
                    'fecha' => now(),
                    'monto_sin_iva' => $this->getRoundedAmount(), // Monto aleatorio redondeado
                    'iva' => $this->getRoundedAmount(), // IVA redondeado
                    'monto_total' => $this->getRoundedAmount(), // Monto total redondeado
                    'tipo_soporte' => 'Factura', // Tipo de soporte
                    'descripcion' => 'Gasto de ejemplo ' . $index,
                    'inmueble_id' => $inmueble->id, // Relacionado con el inmueble
                    'categoria_id' => $categoria->id, // Relacionado con la categoría seleccionada
                    'subcategoria_id' => $subcategoria->id, // Relacionado con la subcategoría que pertenece a la categoría
                    'tipo_pago' => 'Transferencia', // Tipo de pago
                    'proveedor' => 'Proveedor de ejemplo', // Proveedor de ejemplo
                    'numero_comprobante' => 'COMPROBANTE-' . Str::random(8), // Número de comprobante aleatorio
                    'observaciones' => 'Observaciones del gasto ' . $index,
                ]);
            }
        }

        $this->command->info('Seeder de gastos ejecutado con éxito.');
    }

    // Función para generar montos redondeados a múltiplos de 5000, 25000, etc.
    private function getRoundedAmount()
    {
        // Seleccionamos un múltiplo aleatorio de 5000, 10000, 25000, 50000
        $multiples = [5000, 10000, 25000, 50000];
        $randomMultiple = $multiples[array_rand($multiples)];

        // Generamos un monto aleatorio, pero redondeado a los múltiplos seleccionados
        return $randomMultiple * rand(1, 10); // Multiplicamos por un número aleatorio entre 1 y 10
    }
}