<?php

declare(strict_types=1);

namespace Buckaroo\Woocommerce\Services;

class CultureCodeResolver
{
    public const DEFAULT_CULTURE = 'en-GB';

    public const COUNTRY_CULTURES = [
        'NL' => ['nl-NL'],
        'BE' => ['nl-BE', 'fr-BE'],
        'DE' => ['de-DE'],
        'AT' => ['de-AT'],
        'CH' => ['de-CH', 'fr-CH', 'it-CH'],
        'SE' => ['sv-SE'],
        'NO' => ['nb-NO'],
        'DK' => ['da-DK'],
        'FI' => ['fi-FI', 'sv-FI'],
        'GB' => ['en-GB'],
        'IE' => ['en-IE'],
        'FR' => ['fr-FR'],
        'IT' => ['it-IT'],
        'ES' => ['es-ES'],
        'PT' => ['pt-PT'],
        'PL' => ['pl-PL'],
        'US' => ['en-US'],
    ];

    public function resolve(?string $country, ?string $localeHint = null): string
    {
        $country = strtoupper(trim((string) $country));

        if (! isset(self::COUNTRY_CULTURES[$country])) {
            return self::DEFAULT_CULTURE;
        }

        $cultures = self::COUNTRY_CULTURES[$country];
        $language = strtolower((string) strtok(trim((string) $localeHint), '_-'));

        foreach ($cultures as $culture) {
            if (strpos($culture, $language . '-') === 0) {
                return $culture;
            }
        }

        return $cultures[0];
    }
}
