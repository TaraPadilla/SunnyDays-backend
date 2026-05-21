<?php

namespace App\Services;

class ReservasContextService
{
    /**
     * Build the manual formula context from the balance reservation snapshot.
     *
     * Today reservations are stored in balances.json_reservas. When reservations
     * become their own table, this service should be the boundary that changes:
     * it can build the same context from Reserva models instead of JSON.
     */
    public static function fromBalanceJson(array $jsonReservas): array
    {
        $contexto = [
            'reservas_subtotal' => isset($jsonReservas['total']) ? (float) $jsonReservas['total'] : 0.0,
            'reservas_noches' => 0.0,
            'reservas_seguro' => 0.0,
        ];

        $canalesConocidos = ['airbnb', 'booking', 'directos'];
        $metricas = ['ingresos_brutos', 'noches', 'seguro'];

        foreach ($canalesConocidos as $canal) {
            foreach ($metricas as $metrica) {
                $contexto["{$canal}_{$metrica}"] = 0.0;
            }
        }

        if (!isset($jsonReservas['reservas']) || !is_array($jsonReservas['reservas'])) {
            return $contexto;
        }

        foreach ($jsonReservas['reservas'] as $reserva) {
            $canal = self::normalizeCanal($reserva['canal'] ?? '');

            if ($canal === '') {
                continue;
            }

            $ingresosBrutos = isset($reserva['ingresos_brutos']) ? (float) $reserva['ingresos_brutos'] : 0.0;
            $noches = isset($reserva['noches']) ? (float) $reserva['noches'] : 0.0;
            $seguro = isset($reserva['seguro']) ? (float) $reserva['seguro'] : 0.0;

            $contexto['reservas_noches'] += $noches;
            $contexto['reservas_seguro'] += $seguro;
            $contexto["{$canal}_ingresos_brutos"] = ($contexto["{$canal}_ingresos_brutos"] ?? 0.0) + $ingresosBrutos;
            $contexto["{$canal}_noches"] = ($contexto["{$canal}_noches"] ?? 0.0) + $noches;
            $contexto["{$canal}_seguro"] = ($contexto["{$canal}_seguro"] ?? 0.0) + $seguro;
        }

        return $contexto;
    }

    private static function normalizeCanal(string $canal): string
    {
        $normalized = strtolower(trim($canal));
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized);

        return trim($normalized ?? '', '_');
    }
}
