<?php
/**
 * Service to calculate weighted scores for evaluations.
 */

namespace Olama\Services;

if (!defined('ABSPATH')) {
    exit;
}

class EvaluationScoringService
{
    /**
     * Calculates the total weighted score and percentage for an evaluation.
     * 
     * @param array $responses Array of objects/arrays containing rating and indicator metadata
     *                        Expected keys: rating (1-5), weight (decimal), is_critical (bool)
     * @return array [ 'total_score', 'max_possible', 'percentage' ]
     */
    public static function calculate_score($responses)
    {
        $total_weighted_score = 0;
        $max_weighted_score = 0;
        $max_rating = 5; // Standard 1-5 scale

        if (empty($responses)) {
            return [
                'total_score' => 0,
                'max_possible' => 0,
                'percentage' => 0
            ];
        }

        foreach ($responses as $response) {
            $rating = (float) $response['rating'];
            $weight = (float) ($response['weight'] ?? 1.00);
            $is_critical = (bool) ($response['is_critical'] ?? false);

            $effective_weight = $weight;
            if ($is_critical) {
                $effective_weight *= 2.0;
            }

            // Non-linear formula: (Rating^2 / Max_Rating) * Effective_Weight
            // This ensures Rating 3/5 gives 1.8 points instead of 3.0
            $safe_rating = min($max_rating, max(0, $rating));
            $points = ($safe_rating * $safe_rating) / $max_rating;

            $total_weighted_score += ($points * $effective_weight);
            $max_weighted_score += ($max_rating * $effective_weight);
        }

        $percentage = ($max_weighted_score > 0) ? round(($total_weighted_score / $max_weighted_score) * 100, 2) : 0;

        return [
            'total_score' => round($total_weighted_score, 2),
            'max_possible' => round($max_weighted_score, 2),
            'percentage' => $percentage
        ];
    }
}
