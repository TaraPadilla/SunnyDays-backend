<?php

namespace App\Http\Controllers;

use App\Models\Gasto;
use App\Models\Inmueble;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Obtener datos del dashboard
     */
    public function index(Request $request)
    {
        try {
            // Obtener fecha actual y del mes anterior
            $now = Carbon::now();
            $currentMonth = $now->format('Y-m');
            $lastMonth = $now->subMonth()->format('Y-m');

            // KPI 1: Total Gastos (Mes actual)
            $totalGastosMes = Gasto::whereYear('fecha', $now->year)
                ->whereMonth('fecha', $now->month)
                ->sum('monto_total');

            // KPI 2: Total General (todos los gastos)
            $totalGeneral = Gasto::sum('monto_total');

            // KPI 3: Cantidad de Inmuebles
            $cantidadInmuebles = Inmueble::count();

            // Calcular tendencias
            $totalGastosMesPasado = Gasto::whereYear('fecha', $now->copy()->subMonth()->year)
                ->whereMonth('fecha', $now->copy()->subMonth()->month)
                ->sum('monto_total');

            $totalGeneralMesPasado = Gasto::whereYear('fecha', $now->copy()->subMonth()->year)
                ->whereMonth('fecha', $now->copy()->subMonth()->month)
                ->sum('monto_total');

            $cantidadInmueblesMesPasado = Inmueble::where('created_at', '<', $now->copy()->subMonth()->endOfMonth())
                ->count();

            // Calcular porcentajes de tendencia
            $gastosTrend = $totalGastosMesPasado > 0 
                ? round((($totalGastosMes - $totalGastosMesPasado) / $totalGastosMesPasado) * 100, 1)
                : 0;

            $generalTrend = $totalGeneralMesPasado > 0
                ? round((($totalGeneral - $totalGeneralMesPasado) / $totalGeneralMesPasado) * 100, 1)
                : 0;

            $inmueblesTrend = $cantidadInmueblesMesPasado > 0
                ? $cantidadInmuebles - $cantidadInmueblesMesPasado
                : 0;

            // Formatear KPIs
            $kpis = [
                [
                    'label' => 'Total Gastos (Mes)',
                    'value' => '$' . number_format($totalGastosMes, 0, ',', '.'),
                    'icon' => 'Receipt',
                    'trend' => ($gastosTrend >= 0 ? '+' : '') . $gastosTrend . '%'
                ],
                [
                    'label' => 'Total General',
                    'value' => '$' . number_format($totalGeneral, 0, ',', '.'),
                    'icon' => 'DollarSign',
                    'trend' => ($generalTrend >= 0 ? '+' : '') . $generalTrend . '%'
                ],
                [
                    'label' => 'Cantidad de Inmuebles',
                    'value' => (string) $cantidadInmuebles,
                    'icon' => 'Building2',
                    'trend' => ($inmueblesTrend >= 0 ? '+' : '') . $inmueblesTrend
                ]
            ];

            // Obtener últimos gastos (limitado a 5)
            $recentExpenses = Gasto::with(['inmueble', 'subcategoria.categoria'])
                ->orderBy('fecha', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($gasto) {
                    return [
                        'fecha' => $gasto->fecha->format('Y-m-d'),
                        'inmueble' => $gasto->inmueble ? $gasto->inmueble->nombre : 'Sin inmueble',
                        'categoria' => $gasto->subcategoria && $gasto->subcategoria->categoria 
                            ? $gasto->subcategoria->categoria->nombre 
                            : 'Sin categoría',
                        'monto' => '$' . number_format($gasto->monto_total, 0, ',', '.')
                    ];
                });

            return response()->json([
                'kpis' => $kpis,
                'recent_expenses' => $recentExpenses
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener datos del dashboard',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
