<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Traits\HasDateValidation;
use PHPUnit\Framework\TestCase;

/**
 * Test HasDateValidation trait
 */
class Test_HasDateValidation extends TestCase
{
    /**
     * Test class using the trait
     */
    private $trait_user;

    /**
     * Set up test fixtures
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->trait_user = new class {
            use HasDateValidation;
        };
    }

    private function yearsAgo(int $years, string $extra = ''): DateTimeImmutable
    {
        return (new DateTimeImmutable('today', wp_timezone()))->modify("-{$years} years {$extra}");
    }

    private function date(string $ymd): DateTimeImmutable
    {
        return new DateTimeImmutable($ymd, wp_timezone());
    }

    public function test_validate_date_with_valid_date()
    {
        $this->assertTrue($this->trait_user->validateDate('2023-01-15', 'Y-m-d'));
        $this->assertTrue($this->trait_user->validateDate('15-01-2023', 'd-m-Y'));
        $this->assertTrue($this->trait_user->validateDate('01/15/2023', 'm/d/Y'));
    }

    public function test_validate_date_with_invalid_date()
    {
        $this->assertFalse($this->trait_user->validateDate('2023-13-01', 'Y-m-d'));
        $this->assertFalse($this->trait_user->validateDate('2023-02-30', 'Y-m-d'));
        $this->assertFalse($this->trait_user->validateDate('invalid', 'Y-m-d'));
    }

    public function test_validate_date_with_non_string()
    {
        $this->assertFalse($this->trait_user->validateDate(null));
        $this->assertFalse($this->trait_user->validateDate(['15-01-2023'], 'd-m-Y'));
    }

    /**
     * @dataProvider supportedFormats
     */
    public function test_parse_birthdate_supported_formats(string $input, string $expected)
    {
        $parsed = $this->trait_user->parseBirthdate($input);

        $this->assertNotNull($parsed, $input);
        $this->assertSame($expected, $parsed->format('Y-m-d'));
    }

    public static function supportedFormats(): array
    {
        return [
            'dashes' => ['15-01-1990', '1990-01-15'],
            'dashes single digits' => ['1-2-1990', '1990-02-01'],
            'slashes' => ['15/01/1990', '1990-01-15'],
            'slashes single digits' => ['5/9/1990', '1990-09-05'],
            'dots' => ['15.01.1990', '1990-01-15'],
            'dots single digits' => ['1.1.1990', '1990-01-01'],
            'compact' => ['15061985', '1985-06-15'],
            'surrounding whitespace' => [' 15-01-1990 ', '1990-01-15'],
            'leap day' => ['29-02-2000', '2000-02-29'],
        ];
    }

    /**
     * @dataProvider unsupportedInput
     *
     * @param  mixed  $input
     */
    public function test_parse_birthdate_rejects_unsupported_input($input)
    {
        $this->assertNull($this->trait_user->parseBirthdate($input));
        $this->assertFalse($this->trait_user->validateBirthdate($input));
    }

    public static function unsupportedInput(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'whitespace' => ['   '],
            'array' => [['15-01-1990']],
            'text' => ['invalid-date'],
            'two-digit year with slashes' => ['15/01/90'],
            'two-digit year with dashes' => ['01-01-50'],
            'six digits' => ['010190'],
            'year first' => ['1990-01-15'],
            'mixed separators' => ['15-01/1990'],
            'invalid day and month' => ['32-13-2020'],
            'impossible day' => ['31-02-1990'],
            'leap day in common year' => ['29-02-2001'],
        ];
    }

    public function test_parse_birthdate_rejects_future_dates()
    {
        $this->assertNull($this->trait_user->parseBirthdate('01-01-2050'));
        $tomorrow = (new DateTimeImmutable('tomorrow', wp_timezone()))->format('d-m-Y');
        $this->assertNull($this->trait_user->parseBirthdate($tomorrow));
        $this->assertFalse($this->trait_user->validateBirthdate($tomorrow));
    }

    public function test_parse_date_returns_normalised_date_or_false()
    {
        $this->assertSame('15-01-1990', $this->trait_user->parseDate('15/01/1990'));
        $this->assertSame('01-01-1990', $this->trait_user->parseDate('1.1.1990'));
        $this->assertSame('01-01-1990', $this->trait_user->parseDate('01011990'));
        $this->assertFalse($this->trait_user->parseDate('invalid-date'));
        $this->assertFalse($this->trait_user->parseDate('15/01/90'));
    }

    public function test_validate_birthdate_over_18()
    {
        $this->assertTrue($this->trait_user->validateBirthdate($this->yearsAgo(25)->format('d-m-Y')));
        $this->assertTrue($this->trait_user->validateBirthdate($this->yearsAgo(25)->format('d/m/Y')));
        $this->assertTrue($this->trait_user->validateBirthdate($this->yearsAgo(25)->format('d.m.Y')));
    }

    public function test_validate_birthdate_exactly_18_today()
    {
        $this->assertTrue($this->trait_user->validateBirthdate($this->yearsAgo(18)->format('d-m-Y')));
    }

    public function test_validate_birthdate_18_tomorrow_is_rejected()
    {
        $this->assertFalse($this->trait_user->validateBirthdate($this->yearsAgo(18, '+1 day')->format('d-m-Y')));
    }

    public function test_validate_birthdate_17_years_old_is_rejected()
    {
        $this->assertFalse($this->trait_user->validateBirthdate($this->yearsAgo(17)->format('d-m-Y')));
    }

    public function test_is_adult_uses_calendar_years()
    {
        $today = $this->date('2026-11-18');

        $this->assertTrue($this->trait_user->isAdult($this->date('2008-11-18'), $today));
        $this->assertTrue($this->trait_user->isAdult($this->date('1960-05-01'), $today));
        $this->assertFalse($this->trait_user->isAdult($this->date('2008-11-19'), $today));
        $this->assertFalse($this->trait_user->isAdult($this->date('2009-01-01'), $today));
    }

    public function test_is_adult_for_birthday_on_leap_day()
    {
        $born = $this->date('2008-02-29');

        $this->assertFalse($this->trait_user->isAdult($born, $this->date('2026-02-28')));
        $this->assertTrue($this->trait_user->isAdult($born, $this->date('2026-03-01')));
    }

    public function test_is_adult_when_today_is_leap_day()
    {
        $today = $this->date('2028-02-29');

        $this->assertTrue($this->trait_user->isAdult($this->date('2010-02-28'), $today));
        $this->assertFalse($this->trait_user->isAdult($this->date('2010-03-01'), $today));
    }
}
