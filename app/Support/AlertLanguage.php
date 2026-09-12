<?php

namespace App\Support;

/**
 * Plain-language translations of RecommendationService's alert conditions,
 * for a farm manager with no technical background. Display layer only — the
 * precise technical sentence (RecommendationService's own $rec['recommendation'])
 * stays available alongside this, never replaced by it.
 */
class AlertLanguage
{
    /** Returns null when no plain-language translation exists yet for this condition. */
    public static function forCondition(string $condition, ?string $building): ?string
    {
        return match ($condition) {
            'Low Production Rate' => sprintf(
                'Building %s has been laying fewer eggs than expected for its age, several days in a row. Worth a closer look on-site.',
                $building ?? '?'
            ),
            'Peer Deviation' => sprintf(
                'Building %s is producing noticeably less than the other buildings today. This usually points to something specific to that building rather than the weather.',
                $building ?? '?'
            ),
            'Rising Mortality (Farm-wide)' =>
                'More hens are dying across the whole farm than usual this week. Since it\'s farm-wide, consider shared causes like heat, disease, or feed/water quality rather than one building.',
            'Rising Mortality (Building)' => sprintf(
                'Building %s has seen a jump in deaths compared to its own recent average. A farm-wide average can hide a problem in a single building — check this one specifically.',
                $building ?? '?'
            ),
            'Declining Revenue' =>
                'Sales revenue has dropped compared to the prior week. Check whether it\'s lower prices, fewer eggs sold, or both, before assuming it\'s routine.',
            'High Culling Rate' =>
                'More birds have been culled this month than is typical, outside of any planned cull window. Confirm these culls were necessary and not a sign of an underlying health issue.',
            'Flock Decline & Low Production' =>
                'Hen count is dropping while production is also below normal, several days in a row. This combination points to a flock in real decline, not just a temporary dip — worth planning for replacement stock.',
            'Unexplained Hen Decrease' =>
                'The recorded hen count dropped without a matching mortality or cull entry. This is most likely a data-entry issue — double check the livestock records for that date.',
            'Unsold Egg Accumulation' =>
                'Eggs are piling up unsold, several days in a row. Sales pace may need attention before eggs age out or have to be sold at a discount.',
            'Feed Intake Drop' => sprintf(
                'Building %s\'s hens are eating noticeably less than usual, even though egg production still looks normal. Hens often go off their feed before production drops — an early warning worth checking today.',
                $building ?? '?'
            ),
            'Feed Conversion Worsening' => sprintf(
                'Building %s is using more feed while producing fewer eggs than a few days ago. Check for feed spillage, pests, or early signs of illness.',
                $building ?? '?'
            ),
            'Margin Warning (Farm-wide)' =>
                'Feed costs are eating into farm-wide profit margins more than usual — the gap between what feed costs and what eggs sell for has narrowed. (Feed cost is currently an estimate — confirm the real cost before acting.)',
            'Margin Warning (Building)' => sprintf(
                'Building %s\'s feed cost per egg is getting close to what the farm earns per egg sold — profit margin for this building is thin. (Feed cost is currently an estimate.)',
                $building ?? '?'
            ),
            'Count Mismatch (Building)' => sprintf(
                'Building %s\'s egg count at the house doesn\'t match its count at the egg room by more than the normal tolerance — worth checking for a counting or logging error.',
                $building ?? '?'
            ),
            'Count Mismatch (Farm-wide)' =>
                'The farm\'s total house count and grading room count don\'t match closely enough — check for a counting or data-entry discrepancy somewhere in the pipeline.',
            'Cull Readiness' => sprintf(
                'Building %s\'s flock is approaching the farm\'s target cull age. Start arranging replacement pullets now so there\'s no gap in production when this batch is culled.',
                $building ?? '?'
            ),
            // Unrecognized/future condition types: no plain-language translation
            // exists yet — the view falls back to the technical text rather than
            // hiding the alert or showing a blank line.
            default => null,
        };
    }
}
