<?php

declare(strict_types=1);

namespace PocWijken;

enum Environment: string
{
    case Prod = 'prod';
    case Acc = 'acc';
    case Test = 'test';

    public function ioBaseUrl(): string
    {
        return match ($this) {
            self::Prod => 'https://io.uitdatabank.be',
            self::Acc => 'https://io-acc.uitdatabank.be',
            self::Test => 'https://io-test.uitdatabank.be',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Prod => 'Productie',
            self::Acc => 'Acceptatie',
            self::Test => 'Test',
        };
    }
}
