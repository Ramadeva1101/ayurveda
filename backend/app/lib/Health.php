<?php
declare(strict_types=1);

// Questions on the Book form. Taken from the client's booking demo; the wording
// must be approved by Dr. Komang before going live. Keys are stored in
// health_checks.answers, so do not rename a key once real bookings exist.
final class Health
{
    public const PREGNANT_OPTIONS = [
        'no' => 'No',
        'yes' => 'Yes',
        'possibly' => 'Possibly',
        'na' => 'Not applicable',
    ];

    public const CONDITIONS = [
        'high_bp' => 'High blood pressure',
        'heart' => 'Heart condition',
        'diabetes' => 'Diabetes',
        'epilepsy' => 'Epilepsy or seizures',
        'skin' => 'Skin condition',
        'surgery_8w' => 'Surgery in the last 8 weeks',
        'fever_2w' => 'Fever or infection in the last 2 weeks',
        'dairy_allergy' => 'Dairy allergy',
        'sesame_allergy' => 'Sesame allergy',
    ];

    // Validates raw input. Returns [answers, error].
    public static function parse($raw): array
    {
        if (!is_array($raw)) {
            return [null, 'Please answer the health questions.'];
        }
        $pregnant = $raw['pregnant'] ?? null;
        if (!is_string($pregnant) || !isset(self::PREGNANT_OPTIONS[$pregnant])) {
            return [null, 'Please answer the pregnancy question.'];
        }
        $conditions = [];
        foreach ((array) ($raw['conditions'] ?? []) as $key) {
            if (is_string($key) && isset(self::CONDITIONS[$key])) {
                $conditions[] = $key;
            }
        }
        $other = trim(mb_substr((string) ($raw['other'] ?? ''), 0, 1000));
        return [[
            'pregnant' => $pregnant,
            'conditions' => array_values(array_unique($conditions)),
            'other' => $other,
        ], null];
    }

    public static function needsAttention(array $answers): bool
    {
        return in_array($answers['pregnant'] ?? '', ['yes', 'possibly'], true)
            || !empty($answers['conditions'])
            || ($answers['other'] ?? '') !== '';
    }
}
