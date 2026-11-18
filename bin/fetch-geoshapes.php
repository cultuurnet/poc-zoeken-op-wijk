<?php

declare(strict_types=1);

/*
 * Haalt de wijk- en deelgemeentegrenzen op uit github.com/provinciesincijfers/gebiedsniveaus
 * en zet ze om naar compacte JSON in data/ (met een bounding box per gebied).
 *
 * Gebruik:
 *   php bin/fetch-geoshapes.php                     # laatste versie van master
 *   php bin/fetch-geoshapes.php --ref=<commit-sha>  # vaste versie
 *   php bin/fetch-geoshapes.php --source=/pad/naar/lokale/clone/gebiedsniveaus
 */

require dirname(__DIR__) . '/bootstrap.php';

const REPOSITORY = 'provinciesincijfers/gebiedsniveaus';
const SHAPES_PATH = 'data_voor_swing/shapefiles';

// De .txt-bestanden zijn GeoJSON in WGS84 (CRS84); de .shp-varianten staan in Lambert 72.
const LAYERS = [
    'wijken' => 'ggw7.txt',
    'deelgemeenten' => 'deelgemeente.txt',
];

$options = getopt('', ['ref:', 'source:', 'help']);

if (isset($options['help'])) {
    fwrite(STDOUT, "php bin/fetch-geoshapes.php [--ref=master|<sha>] [--source=<lokale clone>]\n");
    exit(0);
}

$ref = $options['ref'] ?? 'master';
$source = $options['source'] ?? null;
$dataDir = dirname(__DIR__) . '/data';

if (!is_dir($dataDir) && !mkdir($dataDir, 0775, true)) {
    fwrite(STDERR, "Kan $dataDir niet aanmaken.\n");
    exit(1);
}

$meta = [
    'repository' => 'https://github.com/' . REPOSITORY,
    'ref' => $source === null ? $ref : 'lokale clone: ' . $source,
    'fetched_at' => date(DATE_ATOM),
    'license' => 'Modellicentie gratis hergebruik (Vlaanderen)',
    'layers' => [],
];

try {
    foreach (LAYERS as $layer => $file) {
        $location = $source === null
            ? sprintf('https://raw.githubusercontent.com/%s/%s/%s/%s', REPOSITORY, rawurlencode($ref), SHAPES_PATH, $file)
            : rtrim($source, '/') . '/' . SHAPES_PATH . '/' . $file;

        fwrite(STDOUT, "Ophalen: $location\n");
        $geojson = @file_get_contents($location);
        if ($geojson === false) {
            throw new RuntimeException("Kon $location niet lezen.");
        }

        $areas = convert(json_decode($geojson, true, flags: JSON_THROW_ON_ERROR));

        $target = "$dataDir/$layer.json";
        $json = json_encode(['areas' => $areas], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        if (file_put_contents("$target.tmp", $json) === false || !rename("$target.tmp", $target)) {
            throw new RuntimeException("Kon $target niet schrijven.");
        }

        $meta['layers'][$layer] = ['source_file' => SHAPES_PATH . '/' . $file, 'areas' => count($areas)];
        fwrite(STDOUT, sprintf("  %d gebieden -> %s\n", count($areas), $target));
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Fout: ' . $e->getMessage() . "\n");
    exit(1);
}

file_put_contents("$dataDir/source.json", json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
fwrite(STDOUT, "Klaar.\n");

/**
 * @return list<array{code: string, naam: string, bbox: array{float, float, float, float}, polygons: list<mixed>}>
 */
function convert(array $featureCollection): array
{
    $crs = $featureCollection['crs']['properties']['name'] ?? 'urn:ogc:def:crs:OGC:1.3:CRS84';
    if (!in_array($crs, ['urn:ogc:def:crs:OGC:1.3:CRS84', 'EPSG:4326', 'urn:ogc:def:crs:EPSG::4326'], true)) {
        throw new RuntimeException("Onverwacht coördinatenstelsel: $crs (WGS84 verwacht).");
    }

    $areas = [];
    foreach ($featureCollection['features'] as $feature) {
        $geometry = $feature['geometry'] ?? null;
        // Gebieden zonder geometrie (bv. "niet te lokaliseren") kunnen nooit gevonden worden.
        if ($geometry === null) {
            continue;
        }

        $polygons = match ($geometry['type']) {
            'MultiPolygon' => $geometry['coordinates'],
            'Polygon' => [$geometry['coordinates']],
            default => throw new RuntimeException('Niet-ondersteund geometrietype: ' . $geometry['type']),
        };

        $minX = $minY = INF;
        $maxX = $maxY = -INF;
        foreach ($polygons as $rings) {
            foreach ($rings[0] as [$x, $y]) {
                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }

        $areas[] = [
            'code' => (string) $feature['properties']['geoitem'],
            'naam' => (string) $feature['properties']['naam'],
            'bbox' => [(float) $minX, (float) $minY, (float) $maxX, (float) $maxY],
            'polygons' => $polygons,
        ];
    }

    return $areas;
}
