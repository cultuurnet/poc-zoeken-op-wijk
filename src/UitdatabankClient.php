<?php

declare(strict_types=1);

namespace PocWijken;

final class UitdatabankClient
{
    /**
     * Probeert de types van de referentie in volgorde en geeft het eerste gevonden aanbod terug.
     *
     * @return array{type: OfferType, offer: array<string, mixed>} De JSON-LD van het event of de place.
     */
    public function fetchOffer(OfferReference $reference): array
    {
        foreach ($reference->types as $type) {
            $offer = $this->fetch($reference->ioUrl($type));
            if ($offer !== null) {
                return ['type' => $type, 'offer' => $offer];
            }
        }

        throw new OfferNotFound(sprintf(
            '%s %s niet gevonden op %s.',
            implode(' of ', array_map(static fn (OfferType $type) => $type->label(), $reference->types)),
            $reference->uuid,
            $reference->environment->label()
        ));
    }

    /**
     * Events hebben hun coördinaten in location.geo, places rechtstreeks in geo.
     *
     * @return array{latitude: float, longitude: float}
     */
    public static function coordinates(OfferType $type, array $offer): array
    {
        $geo = $type === OfferType::Event ? ($offer['location']['geo'] ?? null) : ($offer['geo'] ?? null);

        if (!is_numeric($geo['latitude'] ?? null) || !is_numeric($geo['longitude'] ?? null)) {
            throw new MissingCoordinates($type === OfferType::Event
                ? 'Dit event heeft geen geocoördinaten (location.geo ontbreekt). '
                    . 'Dat gebeurt bij online events of bij een locatie zonder (geocodeerbaar) adres. '
                    . 'Zonder coördinaten kunnen we geen wijk bepalen.'
                : 'Deze place heeft geen geocoördinaten (geo ontbreekt). '
                    . 'Dat gebeurt bij een place zonder (geocodeerbaar) adres. '
                    . 'Zonder coördinaten kunnen we geen wijk bepalen.');
        }

        return ['latitude' => (float) $geo['latitude'], 'longitude' => (float) $geo['longitude']];
    }

    /**
     * @return array<string, mixed>|null Null als het aanbod niet bestaat.
     */
    private function fetch(string $url): ?array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/ld+json, application/json\r\n",
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $status = self::statusCode($http_response_header ?? []);

        if ($body === false || $status === null) {
            throw new UpstreamError('UiTdatabank is niet bereikbaar.');
        }
        if ($status === 404 || $status === 410) {
            return null;
        }
        if ($status !== 200) {
            throw new UpstreamError(sprintf('UiTdatabank antwoordde met HTTP %d.', $status));
        }

        $offer = json_decode($body, true);
        if (!is_array($offer)) {
            throw new UpstreamError('UiTdatabank gaf geen geldige JSON terug.');
        }

        return $offer;
    }

    /**
     * @param list<string> $headers
     */
    private static function statusCode(array $headers): ?int
    {
        // Bij redirects staan er meerdere statusregels in; de laatste telt.
        $status = null;
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                $status = (int) $matches[1];
            }
        }

        return $status;
    }
}
