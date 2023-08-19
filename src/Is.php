<?php

namespace vigihdev\utils;

class Is
{

	public static function arrayOrDefauld(mixed $arg, array $defauld = []): array
	{
		return self::arrayNotEmpty($arg) ? $arg : $defauld;
	}

	public static function stringOrDefauld(mixed $arg, string $defauld = ''): bool
	{
		return self::stringNotEmpty($arg) ? $arg : $defauld;
	}

	public static function arrayNotEmpty(mixed $arg): bool
	{
		return self::array($arg) && !empty($arg);
	}

	public static function stringNotEmpty(mixed $arg): bool
	{
		return self::string($arg) && strlen($arg) > 0;
	}

	public static function objectNotNull(mixed $arg): bool
	{
		return self::object($arg) && !is_null($arg);
	}

	public static function array(mixed $arg): bool
	{
		return is_array($arg);
	}

	public static function string(mixed $arg): bool
	{
		return is_string($arg);
	}

	public static function object(mixed $arg): bool
	{
		return is_object($arg);
	}

	public static function dir(mixed $arg): bool
	{
		return is_dir($arg);
	}

	public static function file(mixed $arg): bool
	{
		return is_file($arg);
	}

	public static function propExists($object_or_class, string $property): bool
	{
		return property_exists($object_or_class, $property);
	}

	public static function methodExists($object_or_class, string $method): bool
	{
		return method_exists($object_or_class, $method);
	}
}
