<?php

use App\Enums\AppointmentType;

it('offers accident and damage as entry types', function () {
    expect(AppointmentType::Accident->getLabel())->toBe('Unfall')
        ->and(AppointmentType::Damage->getLabel())->toBe('Schaden');
});

// Ohne diese Kontrolle ergaeben zwei Typen dieselbe Farbe - dann waeren sie
// im Badge nicht mehr auseinanderzuhalten.
it('gives every entry type its own colour', function () {
    $colors = array_map(fn (AppointmentType $type): string => $type->getColor(), AppointmentType::cases());

    expect($colors)->toHaveCount(count(array_unique($colors)));
});

it('uses danger for an accident, so it stands out', function () {
    expect(AppointmentType::Accident->getColor())->toBe('danger');
});
