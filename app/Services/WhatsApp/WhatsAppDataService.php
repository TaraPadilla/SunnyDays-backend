<?php

namespace App\Services\WhatsApp;

use App\Http\Controllers\InmuebleController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\SubcategoriaController;
use App\Models\Inmueble;
use App\Models\Categoria;
use App\Models\Subcategoria;
use App\Http\Resources\InmuebleResource;
use App\Http\Resources\CategoriaResource;
use App\Http\Resources\SubcategoriaResource;
use Illuminate\Support\Facades\Log;

class WhatsAppDataService
{
    protected $inmuebleController;
    protected $categoriaController;
    protected $subcategoriaController;

    public function __construct(
        InmuebleController $inmuebleController,
        CategoriaController $categoriaController,
        SubcategoriaController $subcategoriaController
    ) {
        $this->inmuebleController = $inmuebleController;
        $this->categoriaController = $categoriaController;
        $this->subcategoriaController = $subcategoriaController;
    }

    /**
     * Obtener inmuebles activos para mostrar en WhatsApp usando el controller existente
     */
    public function getActiveProperties(): array
    {
        try {
            // Usar la lógica del InmuebleController existente
            $response = $this->inmuebleController->index();
            $inmueblesData = $response->getData(true);
            
            if ($inmueblesData['status'] !== 'success') {
                Log::error('WhatsApp Data Service - Error del controller', [
                    'error' => $inmueblesData['message'] ?? 'Error desconocido'
                ]);
                return [];
            }

            // Extraer solo el nombre del inmueble para WhatsApp (truncado a 20 chars)
            $properties = collect($inmueblesData['data'])->map(function ($inmueble) {
                $title = $inmueble['nombre'];
                if (strlen($title) > 20) {
                    $title = substr($title, 0, 17) . '...';
                }
                return [
                    'id' => "PROPERTY_{$inmueble['id']}",
                    'title' => $title,
                    'description' => null
                ];
            })->toArray();

            Log::info('WhatsApp Data Service - Inmuebles obtenidos via controller', [
                'total' => count($properties)
            ]);

            return $properties;
        } catch (\Exception $e) {
            Log::error('WhatsApp Data Service - Error al obtener inmuebles via controller', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Obtener categorías activas para mostrar en WhatsApp usando el controller existente
     */
    public function getActiveCategoriesByType(string $tipo = 'Egreso'): array
    {
        try {
            $tipo = 'Egreso';

            // Crear un request simulado para el nuevo método
            $request = new \Illuminate\Http\Request();
            
            // Usar el nuevo método específico getByTipo
            $response = $this->categoriaController->getByTipo($request, $tipo);
            $categoriasData = $response->getData(true);
            
            if ($categoriasData['status'] !== 'success') {
                Log::error('WhatsApp Data Service - Error del controller de categorías', [
                    'error' => $categoriasData['message'] ?? 'Error desconocido'
                ]);
                return [];
            }

            // Filtrar por visible_combo (el controller ya filtra por tipo y estado)
            $categories = collect($categoriasData['data'])
                ->filter(function ($categoria) {
                    return ($categoria['tipo'] ?? null) === 'Egreso'
                        && $categoria['visible_combo'] === true;
                })
                ->sortBy('orden')
                ->sortBy('nombre')
                ->map(function ($categoria) {
                    $title = $categoria['nombre'];
                    if (strlen($title) > 20) {
                        $title = substr($title, 0, 17) . '...';
                    }
                    return [
                        'id' => "CATEGORY_{$categoria['id']}",
                        'title' => $title,
                        'description' => null
                    ];
                })
                ->values()
                ->toArray();

            Log::info('WhatsApp Data Service - Categorías obtenidas via controller (getByTipo)', [
                'tipo' => $tipo,
                'total' => count($categories),
                'filtered_by' => 'tipo Egreso and visible_combo'
            ]);

            return $categories;
        } catch (\Exception $e) {
            Log::error('WhatsApp Data Service - Error al obtener categorías via controller (getByTipo)', [
                'tipo' => $tipo,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Obtener subcategorías activas por categoría para mostrar en WhatsApp usando el controller existente
     */
    public function getActiveSubcategoriesByCategory(int $categoriaId): array
    {
        try {
            // Crear un request simulado para el nuevo método
            $request = new \Illuminate\Http\Request();
            
            // Usar el nuevo método específico getByCategoria
            $response = $this->subcategoriaController->getByCategoria($request, $categoriaId);
            $subcategoriasData = $response->getData(true);
            
            if ($subcategoriasData['status'] !== 'success') {
                Log::error('WhatsApp Data Service - Error del controller de subcategorías', [
                    'error' => $subcategoriasData['message'] ?? 'Error desconocido'
                ]);
                return [];
            }

            // Filtrar por visible_combo (el controller ya filtra por categoría_id y estado)
            $subcategories = collect($subcategoriasData['data'])
                ->filter(function ($subcategoria) {
                    return $subcategoria['visible_combo'] === true
                        && data_get($subcategoria, 'campo.tipo_calculo') === 'SUM';
                })
                ->sortBy('orden')
                ->sortBy('nombre')
                ->map(function ($subcategoria) {
                    $title = $subcategoria['nombre'];
                    if (strlen($title) > 20) {
                        $title = substr($title, 0, 17) . '...';
                    }
                    return [
                        'id' => "SUBCATEGORY_{$subcategoria['id']}",
                        'title' => $title,
                        'description' => null
                    ];
                })
                ->values()
                ->toArray();

            Log::info('WhatsApp Data Service - Subcategorías obtenidas via controller (getByCategoria)', [
                'categoria_id' => $categoriaId,
                'total' => count($subcategories),
                'filtered_by' => 'categoria_id, visible_combo and campo tipo_calculo SUM'
            ]);

            return $subcategories;
        } catch (\Exception $e) {
            Log::error('WhatsApp Data Service - Error al obtener subcategorías via controller (getByCategoria)', [
                'categoria_id' => $categoriaId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Validar y obtener inmueble por ID
     */
    public function getValidProperty(string $propertyId): ?Inmueble
    {
        try {
            $id = str_replace('PROPERTY_', '', $propertyId);
            $property = Inmueble::find($id);
            
            if (!$property) {
                Log::warning('WhatsApp Data Service - Inmueble no encontrado', [
                    'property_id' => $id,
                    'original_id' => $propertyId
                ]);
            }
            
            return $property;
        } catch (\Exception $e) {
            Log::error('WhatsApp Data Service - Error al validar inmueble', [
                'property_id' => $propertyId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Validar y obtener categoría por ID
     */
    public function getValidCategory(string $categoryId): ?Categoria
    {
        try {
            $id = str_replace('CATEGORY_', '', $categoryId);
            $category = Categoria::where('tipo', 'Egreso')
                ->where('estado', true)
                ->where('visible_combo', true)
                ->find($id);
            
            if (!$category) {
                Log::warning('WhatsApp Data Service - Categoría no encontrada', [
                    'category_id' => $id,
                    'original_id' => $categoryId
                ]);
            }
            
            return $category;
        } catch (\Exception $e) {
            Log::error('WhatsApp Data Service - Error al validar categoría', [
                'category_id' => $categoryId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Validar y obtener subcategoría por ID
     */
    public function getValidSubcategory(string $subcategoryId): ?Subcategoria
    {
        try {
            $id = str_replace('SUBCATEGORY_', '', $subcategoryId);
            $subcategory = Subcategoria::where('estado', true)
                ->where('visible_combo', true)
                ->whereHas('campo', function ($query) {
                    $query->where('tipo_calculo', 'SUM');
                })
                ->whereHas('categoria', function ($query) {
                    $query->where('tipo', 'Egreso')
                        ->where('estado', true)
                        ->where('visible_combo', true);
                })
                ->find($id);
            
            if (!$subcategory) {
                Log::warning('WhatsApp Data Service - Subcategoría no encontrada', [
                    'subcategory_id' => $id,
                    'original_id' => $subcategoryId
                ]);
            }
            
            return $subcategory;
        } catch (\Exception $e) {
            Log::error('WhatsApp Data Service - Error al validar subcategoría', [
                'subcategory_id' => $subcategoryId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
