<?php

declare(strict_types=1);

namespace PocWijken;

/** Basisklasse: de boodschap mag rechtstreeks aan de gebruiker getoond worden. */
class LookupFailed extends \RuntimeException
{
}

final class InvalidInput extends LookupFailed
{
}

final class EventNotFound extends LookupFailed
{
}

final class UpstreamError extends LookupFailed
{
}

final class MissingCoordinates extends LookupFailed
{
}
