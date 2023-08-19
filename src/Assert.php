<?php

namespace vigihdev\utils;

class Assert
{

	public static function ok($param1, $param2)
	{
		return $param1 === $param2;
	}

	public static function logs(array $array): void
	{
		echo "<pre>";
		foreach ($array as $key => $value) {
			var_dump($value);
		}
		echo "</pre>";
	}

	public static function log(mixed $array): void
	{
		echo "<pre>";
		var_dump($array);
		echo "</pre>";
	}
}
