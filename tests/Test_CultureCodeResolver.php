<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Services\CultureCodeResolver;
use PHPUnit\Framework\TestCase;

class Test_CultureCodeResolver extends TestCase
{
    public function test_belgium_defaults_to_dutch_belgian_culture(): void
    {
        $this->assertSame('nl-BE', (new CultureCodeResolver())->resolve('BE'));
    }

    public function test_locale_hint_selects_a_supported_belgian_language(): void
    {
        $this->assertSame('fr-BE', (new CultureCodeResolver())->resolve('BE', 'fr_BE'));
    }

    /**
     * @dataProvider primaryCultureProvider
     */
    public function test_country_uses_its_primary_culture(string $country, string $culture): void
    {
        $this->assertSame($culture, (new CultureCodeResolver())->resolve($country));
    }

    public static function primaryCultureProvider(): array
    {
        return [
            'Netherlands' => ['NL', 'nl-NL'],
            'Belgium' => ['BE', 'nl-BE'],
            'Germany' => ['DE', 'de-DE'],
            'Austria' => ['AT', 'de-AT'],
            'Switzerland' => ['CH', 'de-CH'],
            'Sweden' => ['SE', 'sv-SE'],
            'Norway' => ['NO', 'nb-NO'],
            'Denmark' => ['DK', 'da-DK'],
            'Finland' => ['FI', 'fi-FI'],
            'United Kingdom' => ['GB', 'en-GB'],
            'Ireland' => ['IE', 'en-IE'],
            'France' => ['FR', 'fr-FR'],
            'Italy' => ['IT', 'it-IT'],
            'Spain' => ['ES', 'es-ES'],
            'Portugal' => ['PT', 'pt-PT'],
            'Poland' => ['PL', 'pl-PL'],
            'United States' => ['US', 'en-US'],
        ];
    }

    /**
     * @dataProvider localeHintProvider
     */
    public function test_locale_hint_selects_only_a_supported_country_language(
        string $country,
        string $localeHint,
        string $culture
    ): void {
        $this->assertSame($culture, (new CultureCodeResolver())->resolve($country, $localeHint));
    }

    public static function localeHintProvider(): array
    {
        return [
            'unsupported Belgian language' => ['BE', 'en_US', 'nl-BE'],
            'Swedish Finland' => ['FI', 'sv_SE', 'sv-FI'],
            'Italian Switzerland' => ['CH', 'it_IT', 'it-CH'],
        ];
    }

    /**
     * @dataProvider unknownCountryProvider
     */
    public function test_unknown_or_empty_country_uses_the_default_culture(?string $country): void
    {
        $this->assertSame('en-GB', (new CultureCodeResolver())->resolve($country));
    }

    public static function unknownCountryProvider(): array
    {
        return [
            'unknown country' => ['XX'],
            'empty country' => [''],
            'null country' => [null],
        ];
    }

    /**
     * @dataProvider malformedLocaleHintProvider
     */
    public function test_malformed_locale_hint_is_ignored(?string $localeHint): void
    {
        $this->assertSame('nl-BE', (new CultureCodeResolver())->resolve('BE', $localeHint));
    }

    public static function malformedLocaleHintProvider(): array
    {
        return [
            'one-letter hint' => ['x'],
            'numeric hint' => ['123'],
            'empty hint' => [''],
        ];
    }

    public function test_country_is_trimmed_and_case_normalized(): void
    {
        $this->assertSame('nl-BE', (new CultureCodeResolver())->resolve(' be '));
    }
}
