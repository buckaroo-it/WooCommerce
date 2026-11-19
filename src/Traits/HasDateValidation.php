<?php

namespace Buckaroo\Woocommerce\Traits;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;

trait HasDateValidation
{
    /**
     * Check if a user is 18 years or older.
     *
     * @param  mixed  $birthdate  Birthdate as entered in the checkout
     * @return bool True if the date is valid and the user is 18 years or older
     */
    public function validateBirthdate($birthdate)
    {
        $date = $this->parseBirthdate($birthdate);

        return $date !== null && $this->isAdult($date);
    }

    /**
     * Parse a birthdate entered in the checkout.
     *
     * Accepted: day first, then month, then a four-digit year, separated by
     * "-", "/" or "." (day and month with one or two digits), or as eight
     * digits (DDMMYYYY). Dates in the future are not accepted.
     *
     * @param  mixed  $date  Date as entered in the checkout
     */
    public function parseBirthdate($date): ?DateTimeImmutable
    {
        if (! is_string($date)) {
            return null;
        }

        $date = trim($date);

        if (preg_match('/^(\d{1,2})([-\/.])(\d{1,2})\2(\d{4})$/', $date, $matches)) {
            [$day, $month, $year] = [(int) $matches[1], (int) $matches[3], (int) $matches[4]];
        } elseif (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $date, $matches)) {
            [$day, $month, $year] = [(int) $matches[1], (int) $matches[2], (int) $matches[3]];
        } else {
            return null;
        }

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        $parsed = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day), $this->getBirthdateTimezone());

        if ($parsed->format('Y-m-d') > $this->getToday()->format('Y-m-d')) {
            return null;
        }

        return $parsed;
    }

    /**
     * Whether a customer born on the given date has reached the minimum age
     * today: birthdate <= today minus 18 calendar years. For a birthday on
     * 29 February the age is reached on 1 March in non-leap years.
     */
    public function isAdult(DateTimeImmutable $birthdate, ?DateTimeImmutable $today = null): bool
    {
        $today = $today ?? $this->getToday();

        $year = (int) $today->format('Y') - 18;
        $month = (int) $today->format('n');
        $day = (int) $today->format('j');

        // Today minus 18 years can fall on 29 February of a non-leap year;
        // use the last day of that month instead.
        while (! checkdate($month, $day, $year)) {
            $day--;
        }

        return $birthdate->format('Y-m-d') <= sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * Parse a date string into 'd-m-Y' format.
     *
     * @param  mixed  $date  Date string
     * @return string|false Formatted date or false on failure
     */
    public function parseDate($date)
    {
        $parsed = $this->parseBirthdate($date);

        return $parsed !== null ? $parsed->format('d-m-Y') : false;
    }

    /**
     * Validate a date string against a specified format.
     *
     * @param  string  $date  Date string
     * @param  string  $format  Date format
     * @return bool True if the date is valid, false otherwise
     */
    public function validateDate($date, $format = 'Y-m-d H:i:s')
    {
        if (! is_string($date)) {
            return false;
        }

        $d = DateTime::createFromFormat($format, $date);

        return $d && $d->format($format) === $date;
    }

    protected function getToday(): DateTimeImmutable
    {
        return new DateTimeImmutable('today', $this->getBirthdateTimezone());
    }

    private function getBirthdateTimezone(): DateTimeZone
    {
        return function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
    }
}
