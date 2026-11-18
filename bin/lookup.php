<?php

declare(strict_types=1);

/*
 * Zoek de wijk van een event vanaf de command line.
 *
 *   php bin/lookup.php https://io-acc.uitdatabank.be/events/<uuid>
 *   php bin/lookup.php <uuid>
 */

require dirname(__DIR__) . '/bootstrap.php';

use PocWijken\LookupFailed;
use PocWijken\WijkLookup;

if ($argc < 2) {
    fwrite(STDERR, "Gebruik: php bin/lookup.php <event-url|uuid>\n");
    exit(1);
}

try {
    $result = WijkLookup::create()->lookup($argv[1]);
} catch (LookupFailed $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

printf("Omgeving:     %s\n", $result['reference']->environment->label());
printf("Event:        %s\n", $result['name'] ?? '-');
printf("Gemeente:     %s\n", $result['locality'] ?? '-');
printf("Coördinaten:  %F, %F\n", $result['latitude'], $result['longitude']);
printf("Wijk:         %s\n", $result['wijk'] ? "{$result['wijk']['naam']} ({$result['wijk']['code']})" : 'geen wijk');
printf("Deelgemeente: %s\n", $result['deelgemeente'] ? "{$result['deelgemeente']['naam']} ({$result['deelgemeente']['code']})" : '-');
