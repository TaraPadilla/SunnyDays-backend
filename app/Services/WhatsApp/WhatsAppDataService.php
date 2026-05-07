<?php

namespace App\Services\WhatsApp;

use App\Http\Controllers\InmuebleController;
use App\Http\Controllers\CategoriaController;
use App\Models\Inmueble;
use App\Models\Categoria;
use App\Models\Subcategoria;
use App\Http\Resources\InmuebleResource;
use App\Http\Resources\CategoriaResource;
use Illuminate\Support\Facades\Log;

class WhatsAppDataService
{
    protected $inmuebleController;
    protected $categoriaController;

    public function __construct(
        InmuebleController $inmuebleController,
        CategoriaController $categoriaController
    ) {
        $this->inmuebleController = $inmuebleController;
        $this->categoriaController = $categoriaController;
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

            // Extraer solo el nombre del inmueble para WhatsApp
            $properties = collect($inmueblesData['data'])->map(function ($inmueble) {
                return [
                    'id' => "PROPERTY_{$inmueble['id']}",
                    'title' => $inmueble['nombre'],
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
     * Obtener categorías activas por tipo para mostrar en WhatsApp usando el controller existente
     */
    public function getActiveCategoriesByType(string $tipo): array
    {
        try {
            // Usar la lógica del CategoriaController existente
            $response = $this->categoriaController->index();
            $categoriasData = $response->getData(true);
            
            if ($categoriasData['status'] !== 'success') {
                Log::error('WhatsApp Data Service - Error del controller de categorías', [
                    'error' => $categoriasData['message'] ?? 'Error desconocido'
                ]);
                return [];
            }

            // Filtrar por tipo y extraer solo el nombre para WhatsApp
            $categories = collect($categoriasData['data'])
                ->filter(function ($categoria) use ($tipo) {
                    return $categoria['tipo'] === $tipo;
                })
                ->sortBy('orden')
                ->sortBy('nombre')
                ->map(function ($categoria) {
                    return [
                        'id' => "CATEGORY_{$categoria['id']}",
                        'title' => $categoria['nombre'],
                        'description' => null
                    ];
                })
                ->values()
                ->toArray();

            Log::info('WhatsApp Data Service - Categorías obtenidas via controller', [
                'tipo' => $tipo,
                'total' => count($categories)
            ]);

            return $categories;
        } catch (\Exception $e) {
            Log::error('WhatsApp Data Service - Error al obtener categorías via controller', [
                'tipo' => $tipo,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Obtener subcategorías activas por categoría para mostrar en WhatsApp
     */
    public function getActiveSubcategoriesByCategory(int $categoriaId): array
    {
        try {
            $subcategorias = Subcategoria::where('categoria_id', $categoriaId)
                ->where('estado', true)
                ->where('visible_combo', true)
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get(['id', 'nombre']);

            Log::info('WhatsApp Data Service - Subcategorías obtenidas', [
                'categoria_id' => $categoriaId,
                'total' => $subcategorias->count()
            ]);

            return $subcategorias->map(function ($subcategoria) {
                return [
                    'id' => "SUBCATEGORY_{$subcategoria->id}",
                    'title' => $subcategoria->nombre,
                    'description' => null
                ];
            })->toArray();
        } catch (\Exception $e) {
            Log::error('WhatsApp Data Service - Error al obtener subcategorías', [
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
            $category = Categoria::find($id);
            
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
            $subcategory = Subcategoria::find($id);
            
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
