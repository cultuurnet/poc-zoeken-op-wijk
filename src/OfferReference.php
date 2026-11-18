<?php

declare(strict_types=1);

namespace PocWijken;

final class OfferReference
{
    private const UUID_PATTERN = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    // Zowel de API-hosts (io*) als de UiTdatabank-frontendhosts worden aanvaard.
    private const HOSTS = [
        'io.uitdatabank.be' => Environment::Prod,
        'uitdatabank.be' => Environment::Prod,
        'www.uitdatabank.be' => Environment::Prod,
        'io-acc.uitdatabank.be' => Environment::Acc,
        'acc.uitdatabank.be' => Environment::Acc,
        'io-test.uitdatabank.be' => Environment::Test,
        'test.uitdatabank.be' => Environment::Test,
    ];

    /**
     * @param list<OfferType> $types De types die geprobeerd worden, in volgorde.
     */
    private function __construct(
        public readonly Environment $environment,
        public readonly string $uuid,
        public readonly array $types,
    ) {
    }

    /**
     * Een losse UUID wordt altijd als productie beschouwd, en eerst als event en dan als place opgezocht.
     *
     * @throws InvalidInput
     */
    public static function fromInput(string $input): self
    {
        $input = strtolower(trim($input));
        if ($input === '') {
            throw new InvalidInput('Vul een event- of place-URL of een UUID in.');
        }

        if (preg_match('#^' . self::UUID_PATTERN . '$#', $input) === 1) {
            return new self(Environment::Prod, $input, [OfferType::Event, OfferType::Place]);
        }

        if (!str_contains($input, '://')) {
            $input = 'https://' . $input;
        }

        $host = parse_url($input, PHP_URL_HOST);
        $path = parse_url($input, PHP_URL_PATH) ?? '';

        if (!is_string($host) || !isset(self::HOSTS[$host])) {
            throw new InvalidInput('Geen geldige UiTdatabank-link of UUID. Aanvaard: (io.)uitdatabank.be, (io-)acc.uitdatabank.be, (io-)test.uitdatabank.be of een losse UUID.');
        }

        if (preg_match('#^/(events|places)/(' . self::UUID_PATTERN . ')(/|$)#', $path, $matches) !== 1) {
            throw new InvalidInput('De link moet naar een event of place wijzen: /events/{uuid} of /places/{uuid}.');
        }

        return new self(self::HOSTS[$host], $matches[2], [OfferType::from($matches[1])]);
    }

    public function ioUrl(OfferType $type): string
    {
        return $this->environment->ioBaseUrl() . '/' . $type->value . '/' . $this->uuid;
    }
}
