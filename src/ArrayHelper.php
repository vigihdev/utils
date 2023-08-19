<?php

namespace vigihdev\utils;

use ArrayAccess;
use Traversable;
use ArrayIterator;
use CachingIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use Closure;
use DOMDocument;
use DOMNode;
use Exception;

class ArrayHelper
{

    public static function map(array $array, string $from, string  $to, $group = null): array
    {
        $result = [];
        foreach ($array as $element) {
            $key = static::getValue($element, $from);
            $value = static::getValue($element, $to);
            if ($group !== null) {
                $result[static::getValue($element, $group)][$key] = $value;
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    public static function merge($a, $b)
    {
        $args = func_get_args();
        $res = array_shift($args);
        while (!empty($args)) {
            foreach (array_shift($args) as $k => $v) {
                if ($v instanceof UnsetArrayValue) {
                    unset($res[$k]);
                } elseif ($v instanceof ReplaceArrayValue) {
                    $res[$k] = $v->value;
                } elseif (is_int($k)) {
                    if (array_key_exists($k, $res)) {
                        $res[] = $v;
                    } else {
                        $res[$k] = $v;
                    }
                } elseif (is_array($v) && isset($res[$k]) && is_array($res[$k])) {
                    $res[$k] = static::merge($res[$k], $v);
                } else {
                    $res[$k] = $v;
                }
            }
        }

        return $res;
    }

    public static function getValue($array, $key, $default = null)
    {
        if ($key instanceof \Closure) {
            return $key($array, $default);
        }

        if (is_array($key)) {
            $lastKey = array_pop($key);
            foreach ($key as $keyPart) {
                $array = static::getValue($array, $keyPart);
            }
            $key = $lastKey;
        }

        if (is_object($array) && property_exists($array, $key)) {
            return $array->$key;
        }

        if (static::keyExists($key, $array)) {
            return $array[$key];
        }

        if ($key && ($pos = strrpos($key, '.')) !== false) {
            $array = static::getValue($array, substr($key, 0, $pos), $default);
            $key = substr($key, $pos + 1);
        }

        if (static::keyExists($key, $array)) {
            return $array[$key];
        }
        if (is_object($array)) {
            try {
                return $array->$key;
            } catch (\Exception $e) {
                if ($array instanceof ArrayAccess) {
                    return $default;
                }
                throw $e;
            }
        }

        return $default;
    }

    public static function keyExists($key, $array, $caseSensitive = true)
    {
        if ($caseSensitive) {
            if (is_array($array) && (isset($array[$key]) || array_key_exists($key, $array))) {
                return true;
            }
            return $array instanceof ArrayAccess && $array->offsetExists($key);
        }

        if ($array instanceof ArrayAccess) {
            throw new \Exception('Second parameter($array) cannot be ArrayAccess in case insensitive mode');
        }

        foreach (array_keys($array) as $k) {
            if (strcasecmp($key, $k) === 0) {
                return true;
            }
        }

        return false;
    }

    public static function isAssociative($array, $allStrings = true)
    {
        if (empty($array) || !is_array($array)) {
            return false;
        }

        if ($allStrings) {
            foreach ($array as $key => $value) {
                if (!is_string($key)) {
                    return false;
                }
            }

            return true;
        }

        foreach ($array as $key => $value) {
            if (is_string($key)) {
                return true;
            }
        }

        return false;
    }

    public static function isIndexed($array, $consecutive = false)
    {
        if (!is_array($array)) {
            return false;
        }

        if (empty($array)) {
            return true;
        }

        $keys = array_keys($array);

        if ($consecutive) {
            return $keys === array_keys($keys);
        }

        foreach ($keys as $key) {
            if (!is_int($key)) {
                return false;
            }
        }

        return true;
    }

    public static function filter($array, $filters)
    {
        $result = [];
        $excludeFilters = [];

        foreach ($filters as $filter) {
            if (!is_string($filter) && !is_int($filter)) {
                continue;
            }

            if (is_string($filter) && strncmp($filter, '!', 1) === 0) {
                $excludeFilters[] = substr($filter, 1);
                continue;
            }

            $nodeValue = $array; //set $array as root node
            $keys = explode('.', (string) $filter);
            foreach ($keys as $key) {
                if (!array_key_exists($key, $nodeValue)) {
                    continue 2; //Jump to next filter
                }
                $nodeValue = $nodeValue[$key];
            }

            //We've found a value now let's insert it
            $resultNode = &$result;
            foreach ($keys as $key) {
                if (!array_key_exists($key, $resultNode)) {
                    $resultNode[$key] = [];
                }
                $resultNode = &$resultNode[$key];
            }
            $resultNode = $nodeValue;
        }

        foreach ($excludeFilters as $filter) {
            $excludeNode = &$result;
            $keys = explode('.', (string) $filter);
            $numNestedKeys = count($keys) - 1;
            foreach ($keys as $i => $key) {
                if (!array_key_exists($key, $excludeNode)) {
                    continue 2; //Jump to next filter
                }

                if ($i < $numNestedKeys) {
                    $excludeNode = &$excludeNode[$key];
                } else {
                    unset($excludeNode[$key]);
                    break;
                }
            }
        }

        return $result;
    }

    public static function isIn($needle, $haystack, $strict = false)
    {
        if (!static::isTraversable($haystack)) {
            throw new \Exception('Argument $haystack must be an array or implement Traversable');
        }

        if (is_array($haystack)) {
            return in_array($needle, $haystack, $strict);
        }

        foreach ($haystack as $value) {
            if ($strict ? $needle === $value : $needle == $value) {
                return true;
            }
        }

        return false;
    }

    public static function isTraversable($var)
    {
        return is_array($var) || $var instanceof Traversable;
    }


    /**
     * Sorts array recursively.
     *
     * @param array $array An array passing by reference.
     * @param callable|null $sorter The array sorter. If omitted, sort index array by values, sort assoc array by keys.
     * @return array
     */
    public static function recursiveSort(array &$array, $sorter = null)
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                static::recursiveSort($value, $sorter);
            }
        }
        unset($value);

        if ($sorter === null) {
            $sorter = static::isIndexed($array) ? 'sort' : 'ksort';
        }

        call_user_func_array($sorter, [&$array]);

        return $array;
    }

    /**
     * Determine whether the given value is array accessible.
     *
     * @param  mixed $value
     *
     * @return bool
     */
    public static function accessible($value)
    {
        return is_array($value) || $value instanceof ArrayAccess;
    }


    /**
     * Add an element to an array using "dot" notation if it doesn't exist.
     *
     * @param  array $array
     * @param  string $key
     * @param  mixed $value
     *
     * @return array
     */
    public static function add($array, $key, $value)
    {
        if (is_null(static::getValue($array, $key))) {
            static::set($array, $key, $value);
        }

        return $array;
    }

    /**
     * Get the average value of a given key.
     *
     * @param $array
     * @param null $key
     *
     * @return mixed
     */
    public static function average($array, $key = null)
    {
        return Collection::make($array)->avg($key);
    }

    /**
     * Collapse an array of arrays into a single array.
     *
     * @param  array $array
     *
     * @return array
     */
    public static function collapse($array)
    {
        $results = [];

        foreach ($array as $values) {
            if ($values instanceof Collection) {
                $values = $values->all();
            } elseif (!is_array($values)) {
                continue;
            }

            $results = array_merge($results, $values);
        }

        return $results;
    }

    /**
     * Get all of the given array except for a specified array of items.
     *
     * @param  array $array
     * @param  array|string $keys
     *
     * @return array
     */
    public static function except($array, $keys)
    {
        static::forget($array, $keys);

        return $array;
    }

    /**
     * Remove one or many array items from a given array using "dot" notation.
     *
     * @param  array $array
     * @param  array|string $keys
     */
    public static function forget(&$array, $keys)
    {
        $original = &$array;

        $keys = (array)$keys;

        if (count($keys) === 0) {
            return;
        }

        foreach ($keys as $key) {
            $parts = explode('.', $key);

            // clean up before each pass
            $array = &$original;

            while (count($parts) > 1) {
                $part = array_shift($parts);

                if (isset($array[$part]) && is_array($array[$part])) {
                    $array = &$array[$part];
                } else {
                    continue 2;
                }
            }

            unset($array[array_shift($parts)]);
        }
    }

    /**
     * Check if an item exists in an array using "dot" notation.
     *
     * @param  \ArrayAccess|array $array
     * @param  string $key
     *
     * @return bool
     */
    public static function has($array, $key)
    {
        if (!$array) {
            return false;
        }

        if (is_null($key)) {
            return false;
        }

        if (static::keyExists($key, $array)) {
            return true;
        }

        foreach (explode('.', $key) as $segment) {
            if (static::accessible($array) && static::keyExists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return false;
            }
        }

        return true;
    }



    /**
     * Return the first element in an array passing a given truth test.
     *
     * @param  array $array
     * @param  Closure $callback
     * @param  mixed $default
     *
     * @return mixed
     */
    public static function first($array, $callback = null, $default = null)
    {
        if (is_null($callback)) {
            return empty($array) ? static::value($default) : reset($array);
        }

        foreach ($array as $key => $value) {
            if (call_user_func($callback, $key, $value)) {
                return $value;
            }
        }

        return static::value($default);
    }

    /**
     * Flatten a multi-dimensional array into a single level.
     *
     * @param  array $array
     * @param  int $depth
     *
     * @return array
     */
    public static function flatten($array, $depth = INF)
    {
        $result = [];

        foreach ($array as $item) {
            $item = $item instanceof Collection ? $item->all() : $item;

            if (is_array($item)) {
                if ($depth === 1) {
                    $result = array_merge($result, $item);
                    continue;
                }

                $result = array_merge($result, static::flatten($item, $depth - 1));
                continue;
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * Return the last element in an array passing a given truth test.
     *
     * @param $array
     * @param $callback
     * @param null $default
     *
     * @return mixed
     */
    public static function last($array, $callback = null, $default = null)
    {
        if (is_null($callback)) {
            return empty($array) ? static::value($default) : end($array);
        }

        return static::first(array_reverse($array), $callback, $default);
    }

    /**
     * Get a subset of the items from the given array.
     *
     * @param  array $array
     * @param  array|string $keys
     *
     * @return array
     */
    public static function only($array, $keys)
    {
        return array_intersect_key($array, array_flip((array)$keys));
    }

    /**
     * Pluck an array of values from an array.
     *
     * @param  array $array
     * @param  string|array $value
     * @param  string|array|null $key
     *
     * @return array
     */
    public static function pluck($array, $value, $key = null)
    {
        $results = [];

        list($value, $key) = static::explodePluckParameters($value, $key);

        foreach ($array as $item) {
            $itemValue = static::getValue($item, $value);

            // If the key is "null", we will just append the value to the array and keep
            // looping. Otherwise we will key the array using the value of the key we
            // received from the developer. Then we'll return the final array form.
            if (is_null($key)) {
                $results[] = $itemValue;
            } else {
                $itemKey = static::getValue($item, $key);

                $results[$itemKey] = $itemValue;
            }
        }

        return $results;
    }

    /**
     * Explode the "value" and "key" arguments passed to "pluck".
     *
     * @param  string|array $value
     * @param  string|array|null $key
     *
     * @return array
     */
    protected static function explodePluckParameters($value, $key)
    {
        $value = is_string($value) ? explode('.', $value) : $value;

        $key = is_null($key) || is_array($key) ? $key : explode('.', $key);

        return [$value, $key];
    }

    /**
     * @param $value
     *
     * @return mixed
     */
    public static function value($value)
    {
        return $value instanceof \Closure ? $value() : $value;
    }

    /**
     * Push an item onto the beginning of an array.
     *
     * @param  array $array
     * @param  mixed $value
     * @param  mixed $key
     *
     * @return array
     */
    public static function prepend($array, $value, $key = null)
    {
        if (is_null($key)) {
            array_unshift($array, $value);
        } else {
            $array = [$key => $value] + $array;
        }

        return $array;
    }

    /**
     * Get a value from the array, and remove it.
     *
     * @param  array $array
     * @param  string $key
     * @param  mixed $default
     *
     * @return mixed
     */
    public static function pull(&$array, $key, $default = null)
    {
        $value = static::getValue($array, $key, $default);

        static::forget($array, $key);

        return $value;
    }

    /**
     * Set an array item to a given value using "dot" notation.
     *
     * If no key is given to the method, the entire array will be replaced.
     *
     * @param  array $array
     * @param  string $key
     * @param  mixed $value
     *
     * @return array
     */
    public static function set(&$array, $key, $value)
    {
        if (is_null($key)) {
            return $array = $value;
        }

        $keys = explode('.', $key);

        while (count($keys) > 1) {
            $key = array_shift($keys);

            // If the key doesn't exist at this depth, we will just create an empty array
            // to hold the next value, allowing us to create the arrays to hold final
            // values at the correct depth. Then we'll keep digging into the array.
            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;

        return $array;
    }

    /**
     * Sort the array using the given callback.
     *
     * @param  array $array
     * @param  Closure $callback
     *
     * @return array
     */
    public static function sort($array, \Closure $callback)
    {
        return Collection::make($array)->sortBy($callback)->all();
    }

    /**
     * Recursively sort an array by keys and values.
     *
     * @param  array $array
     *
     * @return array
     */
    public static function sortRecursive($array)
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $value = static::sortRecursive($value);
            }
        }

        if (static::isAssociative($array)) {
            ksort($array);
        } else {
            sort($array);
        }

        return $array;
    }

    /**
     * Filter the array using the given Closure.
     *
     * @param  array $array
     * @param  Closure $callback
     *
     * @return array
     */
    public static function where($array, \Closure $callback)
    {
        $filtered = [];

        foreach ($array as $key => $value) {
            if (call_user_func($callback, $key, $value)) {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }
}
