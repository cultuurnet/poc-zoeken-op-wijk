<?php

declare(strict_types=1);

namespace PocWijken;

enum OfferType: string
{
    case Event = 'events';
    case Place = 'places';

    public function label(): string
    {
        return match ($this) {
            self::Event => 'Event',
            self::Place => 'Place',
        };
    }
}
