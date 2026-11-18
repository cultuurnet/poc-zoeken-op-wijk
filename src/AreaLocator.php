<?php

declare(strict_types=1);

namespace PocWijken;

/**
 * Zoekt in welk gebied (wijk, deelgemeente, ...) een WGS84-punt valt.
 *
 * Werkt op de JSON die bin/fetch-geoshapes.php aanmaakt: per gebied een code,
 * naam, bounding box en MultiPolygon-coördinaten in [lon, lat]-volgorde.
 */
final class AreaLocator
{
    /** @var list<array{code: string, naam: string, bbox: array{float, float, float, float}, polygons: list<list<list<array{float, float}>>>}> */
    private array $areas;

    public function __construct(string $dataFile)
    {
        if (!is_file($dataFile)) {
            throw new \RuntimeException(sprintf(
                'Geodata ontbreekt (%s). Voer eerst "php bin/fetch-geoshapes.php" uit.',
                $dataFile
            ));
        }

        $this->areas = json_decode((string) file_get_contents($dataFile), true, flags: JSON_THROW_ON_ERROR)['areas'];
    }

    /**
     * @return array{code: string, naam: string}|null Null als het punt in geen enkel gebied valt.
     */
    public function locate(float $latitude, float $longitude): ?array
    {
        $x = $longitude;
        $y = $latitude;

        foreach ($this->areas as $area) {
            [$minX, $minY, $maxX, $maxY] = $area['bbox'];
            if ($x < $minX || $x > $maxX || $y < $minY || $y > $maxY) {
                continue;
            }

            foreach ($area['polygons'] as $rings) {
                if (!self::inRing($x, $y, $rings[0])) {
                    continue;
                }
                // Overige ringen zijn gaten in de polygoon.
                foreach (array_slice($rings, 1) as $hole) {
                    if (self::inRing($x, $y, $hole)) {
                        continue 2;
                    }
                }

                return ['code' => $area['code'], 'naam' => $area['naam']];
            }
        }

        return null;
    }

    /**
     * Ray casting: tel hoe vaak een horizontale straal vanuit het punt de rand kruist.
     *
     * @param list<array{float, float}> $ring
     */
    private static function inRing(float $x, float $y, array $ring): bool
    {
        $inside = false;
        $count = count($ring);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];

            if (($yi > $y) !== ($yj > $y) && $x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi) {
                $inside = !$inside;
            }
        }

        return $inside;
    }
}
