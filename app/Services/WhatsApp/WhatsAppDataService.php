<?php

namespace App\Services\WhatsApp;

use App\Models\Inmueble;
use App\Models\Categoria;
use App\Models\Subcategoria;
use Illuminate\Support\Facades\Log;

class WhatsAppDataService
{
    /**
     * Obtener inmuebles activos para mostrar en WhatsApp
     */
    public function getActiveProperties(): array
    {
        try {
            $inmuebles = Inmueble::where('estado', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'codigo']);

            Log::info('WhatsApp Data Service - Inmuebles obtenidos', [
                'total' => $inmuebles->count()
            ]);

            return $inmuebles->map(function ($inmueble) {
                return [
                    'id' => "PROPERTY_{$inmueble->id}",
                    'title' => $inmueble->nombre,
                    'description' => $inmueble->codigo ? "Código: {$inmueble->codigo}" : null
                ];
            })->toArray();
        } catch (\Exception $e) {
            Log::error('WhatsApp Data Service - Error al obtener inmuebles', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Obtener categorías activas por tipo para mostrar en WhatsApp
     */
    public function getActiveCategoriesByType(string $tipo): array
    {
        try {
            $categorias = Categoria::where('tipo', $tipo)
                ->where('estado', true)
                ->where('visible_combo', true)
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get(['id', 'nombre']);

            Log::info('WhatsApp Data Service - Categorías obtenidas', [
                'tipo' => $tipo,
                'total' => $categorias->count()
            ]);

            return $categorias->map(function ($categoria) {
                return [
                    'id' => "CATEGORY_{$categoria->id}",
                    'title' => $categoria->nombre,
                    'description' => null
                ];
            })->toArray();
        } catch (\Exception $e) {
            Log::error('WhatsApp Data Service - Error al obtener categorías', [
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
