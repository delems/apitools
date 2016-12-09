<?php
/**
 * Created by PhpStorm.
 * User: a.kalinin
 * Date: 19.03.14
 * Time: 12:10
 */

namespace APITools;

/**
 * Class Config
 * @package System
 */
class Config
{
    protected static $debug = false;
    protected static $path = null;
    protected static $values = [];

    /**
     * Set configName.key - value pair into $values property
     * @param string $name
     * @param array $data
     */
    public static function setPath($path)
    {
        if (!file_exists($path) ){
            \APITools\Log::error("Path [" . $path . "] not exists!");
        }else{
            static::$path = $path;
        }
    }


    /**
     * Set configName.key - value pair into $values property
     * @param string $name
     * @param array $data
     */
    public static function setDebug($debug)
    {
        static::$debug = $debug;
    }

    /**
     * Set configName.key - value pair into $values property
     * @param string $name
     * @param array $data
     */
    public static function isDebug($debug)
    {
        return static::$debug;
    }


    /**
     * Set configName.key - value pair into $values property
     * @param string $name
     * @param array $data
     */
    public static function setData($name, $data)
    {
        foreach ($data as $key => $value) {
            static::$values[$name . '.' . $key] = $value;
        }
    }

    /**
     * Get value by configName.key . If it is not set in $values property - trying to get it from config file.
     * @param string $name
     * @return mixed|null
     */
    public static function getData($name)
    {
        if (!isset(static::$values[$name])) {
            $request = explode('.', $name);
            static::loadData($request[0]);
        }

        if( isset(static::$values[$name])) {
            return static::$values[$name];
        }else{
            return null;
        }
    }

    /**
     * @param $configName
     */
    public static function loadData($configName)
    {
        if (!file_exists(static::$path) ){
            \APITools\Log::error("Path [" . static::$path . "] not exists!");
        }else{
            static::$values[$configName] = [];
            if (!file_exists($path = static::$path . '/' . $configName . '.php')){
                \APITools\Log::error("Config [" . $configName . "] not found!");
                return;
            }
            static::setData($configName, include $path);
        }
    }

}

