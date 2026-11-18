<?php

declare(strict_types=1);

namespace PocWijken;

final class UitdatabankClient
{
    /**
     * @return array<string, mixed> De JSON-LD van het event.
     */
    public function fetchEvent(EventReference $reference): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/ld+json, application/json\r\n",
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($reference->ioUrl(), false, $context);
        $status = self::statusCode($http_response_header ?? []);

        if ($body === false || $status === null) {
            throw new UpstreamError('UiTdatabank is niet bereikbaar.');
        }
        if ($status === 404 || $status === 410) {
            throw new EventNotFound(sprintf(
                'Event %s niet gevonden op %s.',
                $reference->uuid,
                $reference->environment->label()
            ));
        }
        if ($status !== 200) {
            throw new UpstreamError(sprintf('UiTdatabank antwoordde met HTTP %d.', $status));
        }

        $event = json_decode($body, true);
        if (!is_array($event)) {
            throw new UpstreamError('UiTdatabank gaf geen geldige JSON terug.');
        }

        return $event;
    }

    /**
     * @return array{latitude: float, longitude: float}
     */
    public static function coordinates(array $event): array
    {
        $geo = $event['location']['geo'] ?? null;

        if (!is_numeric($geo['latitude'] ?? null) || !is_numeric($geo['longitude'] ?? null)) {
            throw new MissingCoordinates(
                'Dit event heeft geen geocoördinaten (location.geo ontbreekt). '
                . 'Dat gebeurt bij online events of bij een locatie zonder (geocodeerbaar) adres. '
                . 'Zonder coördinaten kunnen we geen wijk bepalen.'
            );
        }

        return ['latitude' => (float) $geo['latitude'], 'longitude' => (float) $geo['longitude']];
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
