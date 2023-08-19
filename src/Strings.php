<?php

namespace vigihdev\utils;

use Exception;

class Strings
{
	public const TrimCharacters = " \t\n\r\0\x0B\u{A0}";

	/** @deprecated use Strings::TrimCharacters */
	public const TRIM_CHARACTERS = self::TrimCharacters;


	public static function checkEncoding(string $s): bool
	{
		return $s === self::fixEncoding($s);
	}


	/**
	 * Removes all invalid UTF-8 characters from a string.
	 */
	public static function fixEncoding(string $s): string
	{
		// removes xD800-xDFFF, x110000 and higher
		return htmlspecialchars_decode(htmlspecialchars($s, ENT_NOQUOTES | ENT_IGNORE, 'UTF-8'), ENT_NOQUOTES);
	}


	/**
	 * Returns a specific character in UTF-8 from code point (number in range 0x0000..D7FF or 0xE000..10FFFF).
	 * @throws string
	 */
	public static function chr(int $code): string
	{
		if ($code < 0 || ($code >= 0xD800 && $code <= 0xDFFF) || $code > 0x10FFFF) {
			throw new Exception('Code point must be in range 0x0 to 0xD7FF or 0xE000 to 0x10FFFF.');
		} elseif (!extension_loaded('iconv')) {
			throw new Exception(__METHOD__ . '() requires ICONV extension that is not loaded.');
		}

		return iconv('UTF-32BE', 'UTF-8//IGNORE', pack('N', $code));
	}


	/**
	 * Returns a code point of specific character in UTF-8 (number in range 0x0000..D7FF or 0xE000..10FFFF).
	 */
	public static function ord(string $c): int
	{
		if (!extension_loaded('iconv')) {
			throw new Exception(__METHOD__ . '() requires ICONV extension that is not loaded.');
		}

		$tmp = iconv('UTF-8', 'UTF-32BE//IGNORE', $c);
		if (!$tmp) {
			throw new Exception('Invalid UTF-8 character "' . ($c === '' ? '' : '\x' . strtoupper(bin2hex($c))) . '".');
		}

		return unpack('N', $tmp)[1];
	}


	/**
	 * @deprecated use str_starts_with()
	 */
	public static function startsWith(string $haystack, string $needle): bool
	{
		return str_starts_with($haystack, $needle);
	}


	/**
	 * @deprecated use str_ends_with()
	 */
	public static function endsWith(string $haystack, string $needle): bool
	{
		return str_ends_with($haystack, $needle);
	}

	/**
	 * @deprecated use str_contains()
	 */
	public static function contains(string $haystack, string $needle): bool
	{
		return str_contains($haystack, $needle);
	}


	/**
	 * Returns a part of UTF-8 string specified by starting position and length. If start is negative,
	 * the returned string will start at the start'th character from the end of string.
	 */
	public static function substring(string $s, int $start, ?int $length = null): string
	{
		if (function_exists('mb_substr')) {
			return mb_substr($s, $start, $length, 'UTF-8'); // MB is much faster
		} elseif (!extension_loaded('iconv')) {
			throw new Exception(__METHOD__ . '() requires extension ICONV or MBSTRING, neither is loaded.');
		} elseif ($length === null) {
			$length = self::length($s);
		} elseif ($start < 0 && $length < 0) {
			$start += self::length($s); // unifies iconv_substr behavior with mb_substr
		}

		return iconv_substr($s, $start, $length, 'UTF-8');
	}

	/** @deprecated use Strings::unixNewLines() */
	public static function normalizeNewLines(string $s): string
	{
		return self::unixNewLines($s);
	}

	/**
	 * Converts line endings to \n used on Unix-like systems.
	 * Line endings are: \n, \r, \r\n, U+2028 line separator, U+2029 paragraph separator.
	 */
	public static function unixNewLines(string $s): string
	{
		return preg_replace("~\r\n?|\u{2028}|\u{2029}~", "\n", $s);
	}

	/**
	 * Converts line endings to platform-specific, i.e. \r\n on Windows and \n elsewhere.
	 * Line endings are: \n, \r, \r\n, U+2028 line separator, U+2029 paragraph separator.
	 */
	public static function platformNewLines(string $s): string
	{
		return preg_replace("~\r\n?|\n|\u{2028}|\u{2029}~", PHP_EOL, $s);
	}

	/**
	 * Converts all characters of UTF-8 string to lower case.
	 */
	public static function lower(string $s): string
	{
		return mb_strtolower($s, 'UTF-8');
	}

	/**
	 * Converts the first character of a UTF-8 string to lower case and leaves the other characters unchanged.
	 */
	public static function firstLower(string $s): string
	{
		return self::lower(self::substring($s, 0, 1)) . self::substring($s, 1);
	}


	/**
	 * Converts all characters of a UTF-8 string to upper case.
	 */
	public static function upper(string $s): string
	{
		return mb_strtoupper($s, 'UTF-8');
	}


	/**
	 * Converts the first character of a UTF-8 string to upper case and leaves the other characters unchanged.
	 */
	public static function firstUpper(string $s): string
	{
		return self::upper(self::substring($s, 0, 1)) . self::substring($s, 1);
	}


	/**
	 * Converts the first character of every word of a UTF-8 string to upper case and the others to lower case.
	 */
	public static function capitalize(string $s): string
	{
		return mb_convert_case($s, MB_CASE_TITLE, 'UTF-8');
	}


	/**
	 * Compares two UTF-8 strings or their parts, without taking character case into account. If length is null, whole strings are compared,
	 * if it is negative, the corresponding number of characters from the end of the strings is compared,
	 * otherwise the appropriate number of characters from the beginning is compared.
	 */
	public static function compare(string $left, string $right, ?int $length = null): bool
	{
		if (class_exists('Normalizer', false)) {
			$left = \Normalizer::normalize($left, \Normalizer::FORM_D); // form NFD is faster
			$right = \Normalizer::normalize($right, \Normalizer::FORM_D); // form NFD is faster
		}

		if ($length < 0) {
			$left = self::substring($left, $length, -$length);
			$right = self::substring($right, $length, -$length);
		} elseif ($length !== null) {
			$left = self::substring($left, 0, $length);
			$right = self::substring($right, 0, $length);
		}

		return self::lower($left) === self::lower($right);
	}


	/**
	 * Finds the common prefix of strings or returns empty string if the prefix was not found.
	 * @param  string[]  $strings
	 */
	public static function findPrefix(array $strings): string
	{
		$first = array_shift($strings);
		for ($i = 0; $i < strlen($first); $i++) {
			foreach ($strings as $s) {
				if (!isset($s[$i]) || $first[$i] !== $s[$i]) {
					while ($i && $first[$i - 1] >= "\x80" && $first[$i] >= "\x80" && $first[$i] < "\xC0") {
						$i--;
					}

					return substr($first, 0, $i);
				}
			}
		}

		return $first;
	}


	/**
	 * Returns number of characters (not bytes) in UTF-8 string.
	 * That is the number of Unicode code points which may differ from the number of graphemes.
	 */
	public static function length(string $s): int
	{
		return function_exists('mb_strlen')
			? mb_strlen($s, 'UTF-8')
			: strlen(utf8_decode($s));
	}

	/**
	 * Pads a UTF-8 string to given length by prepending the $pad string to the beginning.
	 * @param  non-empty-string  $pad
	 */
	public static function padLeft(string $s, int $length, string $pad = ' '): string
	{
		$length = max(0, $length - self::length($s));
		$padLen = self::length($pad);
		return str_repeat($pad, (int) ($length / $padLen)) . self::substring($pad, 0, $length % $padLen) . $s;
	}


	/**
	 * Pads UTF-8 string to given length by appending the $pad string to the end.
	 * @param  non-empty-string  $pad
	 */
	public static function padRight(string $s, int $length, string $pad = ' '): string
	{
		$length = max(0, $length - self::length($s));
		$padLen = self::length($pad);
		return $s . str_repeat($pad, (int) ($length / $padLen)) . self::substring($pad, 0, $length % $padLen);
	}


	/**
	 * Reverses UTF-8 string.
	 */
	public static function reverse(string $s): string
	{
		if (!extension_loaded('iconv')) {
			throw new Exception(__METHOD__ . '() requires ICONV extension that is not loaded.');
		}

		return iconv('UTF-32LE', 'UTF-8', strrev(iconv('UTF-8', 'UTF-32BE', $s)));
	}


	private static function bytesToChars(string $s, array $groups): array
	{
		$lastBytes = $lastChars = 0;
		foreach ($groups as &$matches) {
			foreach ($matches as &$match) {
				if ($match[1] > $lastBytes) {
					$lastChars += self::length(substr($s, $lastBytes, $match[1] - $lastBytes));
				} elseif ($match[1] < $lastBytes) {
					$lastChars -= self::length(substr($s, $match[1], $lastBytes - $match[1]));
				}

				$lastBytes = $match[1];
				$match[1] = $lastChars;
			}
		}

		return $groups;
	}
}
