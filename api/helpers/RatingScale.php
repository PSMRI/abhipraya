<?php
declare(strict_types=1);

/**
 * Converts one-based JSON option positions into a canonical 1–5 score.
 *
 * Existing Abhipraya surveys use both best-to-worst and worst-to-best option
 * ordering. This class makes the direction explicit at runtime and ignores
 * non-scoring choices such as "Did not avail the service".
 */
final class RatingScale
{
    /**
     * @return array<int, float|null> Stored option value => canonical score.
     *
     * New survey JSON should declare `scale` and a stable numeric `value` for
     * every option, as documented in survey-question-types-reference.md.
     * Array position is retained only as a compatibility fallback.
     */
    public static function optionScores(array $question): array
    {
        $options = array_values($question['options'] ?? []);
        $scoredPositions = [];
        $storedValues = [];
        foreach ($options as $index => $option) {
            $position = $index + 1;
            $label = self::label($option);
            $storedValues[$position] = self::storedValue($option, $position);
            if (!self::isNonScoring($label)) {
                $scoredPositions[] = $position;
            }
        }

        $scores = [];
        foreach ($storedValues as $storedValue) {
            $scores[$storedValue] = null;
        }
        $count = count($scoredPositions);
        if ($count === 0) {
            return $scores;
        }
        if ($count === 1) {
            $scores[$storedValues[$scoredPositions[0]]] = 5.0;
            return $scores;
        }

        $scale = (float) ($question['scale'] ?? 0);
        $hasExplicitScale = $scale > 1.0;
        if ($hasExplicitScale) {
            foreach ($scoredPositions as $position) {
                $option = $options[$position - 1] ?? [];
                $rawScore = is_array($option) && is_numeric($option['score'] ?? null)
                    ? (float) $option['score']
                    : (float) $storedValues[$position];
                $scores[$storedValues[$position]] = self::normalize($rawScore, $scale);
            }
            return $scores;
        }

        $direction = self::direction($question, $scoredPositions);
        foreach ($scoredPositions as $rank => $position) {
            $fraction = $rank / ($count - 1);
            $scores[$storedValues[$position]] = round(
                $direction === 'worst_to_best'
                    ? 1.0 + (4.0 * $fraction)
                    : 5.0 - (4.0 * $fraction),
                4
            );
        }
        return $scores;
    }

    public static function score(array $question, int $answer): ?float
    {
        $scores = self::optionScores($question);
        return array_key_exists($answer, $scores) ? $scores[$answer] : null;
    }

    /**
     * Returns an SQL CASE expression producing a canonical score or NULL.
     */
    public static function sqlCase(string $column, array $question): string
    {
        if (!preg_match('/^srvy_Q(?:[0-9]|[12][0-9]|30)$/', $column)) {
            throw new InvalidArgumentException('Invalid survey response column.');
        }
        $parts = [];
        foreach (self::optionScores($question) as $position => $score) {
            if ($score === null) {
                continue;
            }
            $parts[] = 'WHEN ' . (int) $position . ' THEN ' . number_format($score, 4, '.', '');
        }
        return 'CASE ' . $column . ' ' . implode(' ', $parts) . ' ELSE NULL END';
    }

    /** @param array<int, int> $scoredPositions */
    private static function direction(array $question, array $scoredPositions): string
    {
        $configured = strtolower(trim((string) ($question['rating_order'] ?? '')));
        if (in_array($configured, ['best_to_worst', 'worst_to_best'], true)) {
            return $configured;
        }

        $options = array_values($question['options'] ?? []);
        $first = self::label($options[$scoredPositions[0] - 1] ?? []);
        $last = self::label($options[$scoredPositions[count($scoredPositions) - 1] - 1] ?? []);
        if (self::isLow($first) && self::isHigh($last)) {
            return 'worst_to_best';
        }
        if (self::isHigh($first) && self::isLow($last)) {
            return 'best_to_worst';
        }

        // Historical non-OPD surveys were authored best-to-worst.
        return 'best_to_worst';
    }

    private static function label(mixed $option): string
    {
        $text = is_array($option) ? ($option['text'] ?? $option['label'] ?? '') : $option;
        return strtolower(trim((string) $text));
    }

    private static function storedValue(mixed $option, int $position): int
    {
        if (is_array($option) && is_numeric($option['value'] ?? null)) {
            return (int) $option['value'];
        }
        return $position;
    }

    private static function normalize(float $value, float $scale): float
    {
        $bounded = min($scale, max(1.0, $value));
        return round(1.0 + (($bounded - 1.0) / ($scale - 1.0)) * 4.0, 4);
    }

    private static function isNonScoring(string $label): bool
    {
        return preg_match(
            '/\b(did not avail|not applicable|not availed|prefer not|do not know|don.t know|unable to say|n\/a)\b/i',
            $label
        ) === 1;
    }

    private static function isHigh(string $label): bool
    {
        return preg_match(
            '/\b(excellent|very good|very satisfied|satisfied|good|less than)\b/i',
            $label
        ) === 1;
    }

    private static function isLow(string $label): bool
    {
        return preg_match(
            '/\b(very poor|poor|very unsatisfied|unsatisfied|more than)\b/i',
            $label
        ) === 1;
    }
}
