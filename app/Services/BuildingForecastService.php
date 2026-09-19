<?php

namespace App\Services;

class BuildingForecastService
{
    /**
     * Stub — the forward projection (survival · production · earnings) waits
     * on the Animal Science formula and is a later prototype. Returns null so
     * the dashboard renders its forecast panel as a disabled placeholder;
     * when the real model lands, this method fills in and no UI rework is
     * needed.
     */
    public function forecast(int $buildingId): ?array
    {
        return null;
    }
}
