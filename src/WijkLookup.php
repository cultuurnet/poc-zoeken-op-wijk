<?php

declare(strict_types=1);

namespace PocWijken;

final class WijkLookup
{
    public function __construct(
        private readonly UitdatabankClient $client,
        private readonly AreaLocator $wijken,
        private readonly AreaLocator $deelgemeenten,
    ) {
    }

    public static function create(): self
    {
        $dataDir = dirname(__DIR__) . '/data';

        return new self(
            new UitdatabankClient(),
            new AreaLocator($dataDir . '/wijken.json'),
            new AreaLocator($dataDir . '/deelgemeenten.json'),
        );
    }

    /**
     * @return array{
     *     reference: OfferReference,
     *     type: OfferType,
     *     url: string,
     *     name: ?string,
     *     locality: ?string,
     *     latitude: float,
     *     longitude: float,
     *     wijk: ?array{code: string, naam: string},
     *     deelgemeente: ?array{code: string, naam: string}
     * }
     *
     * @throws LookupFailed
     */
    public function lookup(string $input): array
    {
        $reference = OfferReference::fromInput($input);
        ['type' => $type, 'offer' => $offer] = $this->client->fetchOffer($reference);
        $coordinates = UitdatabankClient::coordinates($type, $offer);

        // Bij een event zit het adres in de genestelde location (een place), bij een place op het hoogste niveau.
        $place = $type === OfferType::Event ? ($offer['location'] ?? []) : $offer;

        return [
            'reference' => $reference,
            'type' => $type,
            'url' => $reference->ioUrl($type),
            'name' => self::translated($offer['name'] ?? null, $offer['mainLanguage'] ?? 'nl'),
            'locality' => self::translated(
                $place['address'] ?? null,
                $place['mainLanguage'] ?? 'nl'
            )['addressLocality'] ?? null,
            'latitude' => $coordinates['latitude'],
            'longitude' => $coordinates['longitude'],
            'wijk' => $this->wijken->locate($coordinates['latitude'], $coordinates['longitude']),
            'deelgemeente' => $this->deelgemeenten->locate($coordinates['latitude'], $coordinates['longitude']),
        ];
    }

    /**
     * UiTdatabank-velden zijn per taal genest ({"nl": ...}); neem de hoofdtaal of de eerste beschikbare.
     */
    private static function translated(mixed $value, string $language): mixed
    {
        if (!is_array($value) || $value === []) {
            return null;
        }

        return $value[$language] ?? reset($value);
    }
}
