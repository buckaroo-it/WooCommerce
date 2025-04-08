<?php

namespace Buckaroo\Woocommerce\Traits;

use DateTime;

trait HasDateValidation {

	/**
	 * Check if a user is 18 years or older.
	 *
	 * @param string $birthdate Birthdate expressed as a string
	 * @return bool True if user is 18 years or older, false otherwise
	 */
	public function validateBirthdate( $birthdate ) {
		$birthdate = $this->parseDate( $birthdate );
		if ( ! $birthdate ) {
			return false;
		}

		$currentDate   = new DateTime();
		$userBirthdate = DateTime::createFromFormat( 'd-m-Y', $birthdate );

		if ( ! $userBirthdate ) {
			return false;
		}

		$ageInterval = $currentDate->diff( $userBirthdate )->y;

		return $ageInterval >= 18;
	}

	/**
	 * Parse a date string into 'd-m-Y' format.
	 *
	 * @param string $date Date string
	 * @return string|false Formatted date or false on failure
	 */
	public function parseDate( $date ) {
		if ( $this->validateDate( $date, 'd-m-Y' ) ) {
			return $date;
		}

		$formats = array(
			'dmy',      // e.g., '010190' (01-01-1990)
			'dmY',      // e.g., '01011990' (01-01-1990)
			'd/m/Y',    // e.g., '01/01/1990'
			'j/m/Y',    // e.g., '1/01/1990'
			'j/n/Y',    // e.g., '1/1/1990'
			'd/n/Y',    // e.g., '01/1/1990'
			'd/m/y',    // e.g., '01/01/90'
			'j/m/y',    // e.g., '1/01/90'
			'j/n/y',    // e.g., '1/1/90'
			'd/n/y',    // e.g., '01/1/90'
			'd-m-y',    // e.g., '01-01-90'
			'j-m-Y',    // e.g., '1-01-1990'
			'j-m-y',    // e.g., '1-01-90'
			'd.m.Y',    // e.g., '01.01.1990'
			'j.m.Y',    // e.g., '1.01.1990'
			'd.m.y',    // e.g., '01.01.90'
			'j.m.y',    // e.g., '1.01.90'
		);

		foreach ( $formats as $format ) {
			$dateTime = DateTime::createFromFormat( $format, $date );
			if ( $dateTime && $dateTime->format( $format ) === $date ) {
				return $dateTime->format( 'd-m-Y' );
			}
		}

		// If no formats matched, return the original date (consistent with original behavior)
		return $date;
	}

	/**
	 * Validate a date string against a specified format.
	 *
	 * @param string $date Date string
	 * @param string $format Date format
	 * @return bool True if the date is valid, false otherwise
	 */
	public function validateDate( $date, $format = 'Y-m-d H:i:s' ) {
		if ( $date === null ) {
			return false;
		}

		$d = DateTime::createFromFormat( $format, $date );
		return $d && $d->format( $format ) === $date;
	}
}
