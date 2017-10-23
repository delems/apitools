<?php

namespace APITools;

abstract class MysqlDB
{
   
    public static $DEFAULT_INITIAL_QUERIES = [
        'SET NAMES utf8mb4',
        'SET collation_connection = utf8mb4_unicode_ci',
        'SET character_set_client = utf8mb4',
        'SET character_set_connection = utf8mb4',
        'SET character_set_results = utf8mb4',
    ];
    
 
    protected static $query_errors = [];
    protected static $mysql_connections = [];
    protected static $mysql_creds = [];
    private static $_types_int = [MYSQLI_TYPE_INT24, MYSQLI_TYPE_LONG, MYSQLI_TYPE_LONGLONG, MYSQLI_TYPE_SHORT, MYSQLI_TYPE_TINY];

    public static function GetConnection($linkname = 'default')
    {
        return self::$mysql_connections[$linkname];
    }

    public static function AddConnection($host, $base, $user, $password, $initial_requests_array, $linkname = 'default', &$errno = null, &$error = null)
    {

        if( count($initial_requests_array) ==0  ){
            $initial_requests_array=self::$DEFAULT_INITIAL_QUERIES;
        }


        if (!$linkname)
            $linkname = 'connect' . count(self::$mysql_connections);
        self::$mysql_creds[$linkname] = [$host, $base, $user, $password, $initial_requests_array];

        self::$mysql_connections[$linkname] = mysqli_connect($host, $user, $password);
        if ($errno = mysqli_connect_errno()) {
           $error = mysqli_connect_error();
           throw new \Exception("Database error: ".$error, 500);
        }

        if ($base)
            mysqli_select_db(self::$mysql_connections[$linkname], $base);
        if (is_array($initial_requests_array)) {
            for ($i = 0; $i < count($initial_requests_array); $i++)
                self::Q($initial_requests_array[$i], $linkname);
        } elseif ($initial_requests_array) {
            self::Q($initial_requests_array, $linkname);
        }
        return $linkname;
    }

    public static function Ping($linkname = 'default')
    {
        return mysqli_ping(self::$mysql_connections[$linkname]);
    }

    public static function CloseConnection($linkname = 'default')
    {
        if (isset(self::$mysql_connections[$linkname])) {
            mysqli_close(self::$mysql_connections[$linkname]);
            unset(self::$mysql_connections[$linkname]);
        }
    }

    //
    // функции запросов
    //

    //запрос
    public static function Q($query_input, $linkname = 'default', $skip_reconnect = false)
    {
//        echo '.'; //Для проверки работы с кэшем
        $ret = new \stdClass();
        $ret->result = NULL;
        $ret->fields = NULL;
        //echo $query_input."\n";
        self::$query_errors[$linkname] = [];

        if (is_object(self::$mysql_connections[$linkname])) {
            $ret->result = mysqli_query(self::$mysql_connections[$linkname], $query_input);
            if (is_object($ret->result)) {
                $ret->fields = mysqli_fetch_fields($ret->result);
            }
            if ($ret->result === false) {
                $ret = false;
                self::$query_errors[$linkname] = ['erno' => mysqli_errno(self::$mysql_connections[$linkname]), 'error' => mysqli_error(self::$mysql_connections[$linkname]), 'errq' => $query_input];
                //echo \DB::ERR($linkname) . "\n" . \DB::ERRQ($linkname) . "\n";
                if (self::ERNO() == 2006 && !$skip_reconnect) {
                    if (!self::Ping($linkname)) {
                        self::CloseConnection($linkname);
                        self::AddConnection(self::$mysql_creds[$linkname][0], self::$mysql_creds[$linkname][1], self::$mysql_creds[$linkname][2], self::$mysql_creds[$linkname][3], self::$mysql_creds[$linkname][4], $linkname);
                        $ret = self::Q($query_input, $linkname, true);
                    }
                }else{
                    throw new \Exception("Query error: +". self::ERRQ()."+ ($query_input) ", 500);
                }
            }
        } else {
            if (!self::Ping($linkname) && !$skip_reconnect) {
                self::CloseConnection($linkname);
                self::AddConnection(self::$mysql_creds[$linkname][0], self::$mysql_creds[$linkname][1], self::$mysql_creds[$linkname][2], self::$mysql_creds[$linkname][3], self::$mysql_creds[$linkname][4], $linkname);
                $ret = self::Q($query_input, $linkname, true);
            }
        }
        return $ret;
    }

    public static function FreeRes(&$query_result)
    {
        if (is_resource($query_result->result)) {
            mysql_free_result($query_result->result);
            unset($query_result);
        }
    }

    public static function FV(&$query_result, $row = 0, $field_name = NULL)
    {
        if (!$query_result->result)
            return NULL;
        $ret = NULL;
        if (self::NR($query_result) > $row) {
            if ($row == 0 || $row > 0 && mysqli_data_seek($query_result->result, $row)) {
                if (!is_null($field_name) && !is_int($field_name)) {
                    $row = mysqli_fetch_assoc($query_result->result);
                    $ret = $row[$field_name];
                    unset($row);
                } elseif (is_int($field_name)) {
                    $row = mysqli_fetch_row($query_result->result);
                    $ret = $row[$field_name];
                    unset($row);
                } else {
                    $row = mysqli_fetch_array($query_result->result);
                    $ret = $row[0];
                    unset($row);
                }
            }
        }

        return $ret;
    }

    public static function QFV($query_input, $row = 0, $field_name = NULL, $linkname = 'default')
    {
        $qres = self::Q($query_input, $linkname);
        $ret = self::FV($qres, $row, $field_name);
        self::FreeRes($qres);
        return $ret;
    }

    //ряд по запросу
    public static function QFA($query_input, $linkname = 'default')
    {
        $qres = self::Q($query_input, $linkname);
        $ret = self::FA($qres);;
        self::FreeRes($qres);
        return $ret;
    }

    //объект по запросу
    public static function QFO($query_input, $linkname = 'default')
    {
        $ret = NULL;
        $qres = self::Q($query_input, $linkname);
        if ($qres->result) {
            $ret = self::FO($qres);
            self::FreeRes($qres);
        }
        return $ret;
    }

    //ряд по результату запроса
    public static function FA(&$query_result)
    {
        $ret = NULL;
        if ($query_result->result) {
            $ret = mysqli_fetch_assoc($query_result->result);
            if ($ret) {
                foreach ($query_result->fields as &$fld) {
                    if ($ret[$fld->name] == null) {
                        $ret[$fld->name] = null;
                    } elseif ($fld->type == MYSQLI_TYPE_BIT) {
                        $ret[$fld->name] = intval($ret[$fld->name]);
                    } elseif (array_search($fld->type, self::$_types_int) !== false) {
                        $ret[$fld->name] = intval($ret[$fld->name]);
                    }
                }
            }
        }
        return $ret;
    }

    //ряд по результату запроса
    public static function FO(&$query_result)
    {
        $ret = NULL;
        if ($query_result->result) {
            $ret = mysqli_fetch_object($query_result->result);
            if ($ret) {
                foreach ($query_result->fields as &$fld) {
                    if ($fld->type == MYSQLI_TYPE_BIT) {
                        if (!is_null($ret->{$fld->name})) {
                            $ret->{$fld->name} = intval($ret->{$fld->name});
                        }
                    } elseif (array_search($fld->type, self::$_types_int) !== false) {
                        $ret->{$fld->name} = intval($ret->{$fld->name});
                    }
                }
            }
        }
        return $ret;
    }

    public static function FetchFirstField(&$query_result)
    {
        $ret = NULL;
        if ($query_result->result) {
            $row = mysqli_fetch_row($query_result->result);
            $ret = $row[0];
            unset($row);
        }
        return $ret;
    }

    public static function NR(&$query_result)
    {
        if (!$query_result->result)
            return NULL;
        return mysqli_num_rows($query_result->result);
    }

    public static function AR($linkname = 'default')
    {
        return mysqli_affected_rows(self::$mysql_connections[$linkname]);
    }

    public static function II($linkname = 'default')
    {
        return mysqli_insert_id(self::$mysql_connections[$linkname]);
    }

    //массив рядов по запросу. Если не указано количество - все ряды
    public static function QFAL($query_input, $num = 0, $linkname = 'default')
    {
        $ret = NULL;
        $qres = self::Q($query_input, $linkname);
        if ($qres) {
            $ret = self::FAL($qres, $num);
            self::FreeRes($qres);
        }
        return $ret;
    }

    //массив объектов по запросу. Если не указано количество - все ряды
    public static function QFOL($query_input, $num = 0, $linkname = 'default')
    {
        $ret = NULL;
        $qres = self::Q($query_input, $linkname);
        if ($qres) {
            $ret = self::FOL($qres, $num);
            self::FreeRes($qres);
        }
        return $ret;
    }

    public static function QFLF($query_input, $num = 0, $linkname = 'default')
    {
        $ret = NULL;
        $qres = self::Q($query_input, $linkname);
        if ($qres) {
            $ret = self::FetchFirstFieldList($qres, $num);
            self::FreeRes($qres);
        }
        return $ret;
    }

    public static function QFFF($query_input, $linkname = 'default')
    {
        $qres = self::Q($query_input, $linkname);
        $res = self::FetchFirstField($qres);
        self::FreeRes($qres);
        return $res;
    }

    public static function FetchFirstFieldList(&$query_result, $num = 0)
    {
        $tot = self::NR($query_result);
        $tot = ($num > 0 ? ($num > $tot ? $tot : $num) : $tot);
        $out = NULL;
        for ($i = 0; $i < $tot; $i++) {
            $out[] = self::FetchFirstField($query_result);
        }
        return $out;
    }

    //массив рядов по результату запроса. Если не указано количество - все ряды
    public static function FAL(&$query_result, $num = 0)
    {
        $tot = self::NR($query_result);
        $tot = ($num > 0 ? ($num > $tot ? $tot : $num) : $tot);
        $out = NULL;
        for ($i = 0; $i < $tot; $i++) {
            $out[] = self::FA($query_result);
        }
        return $out;
    }

    //массив объектов по результату запроса. Если не указано количество - все ряды
    public static function FOL(&$query_result, $num = 0)
    {
        $out = [];
        $tot = self::NR($query_result);
        $tot = ($num > 0 ? ($num > $tot ? $tot : $num) : $tot);
        for ($i = 0; $i < $tot; $i++) {
            $out[] = self::FO($query_result);
        }
        return $out;
    }

    //описание ошибки последнего запроса
    public static function ERR($linkname = 'default')
    {
        return isset(self::$query_errors[$linkname]['error']) ? self::$query_errors[$linkname]['error'] : '';
    }

    //номер ошибки последнего запроса
    public static function ERNO($linkname = 'default')
    {
        return self::$query_errors[$linkname]['erno'];
    }

    //текст ошибочного запроса
    public static function ERRQ($linkname = 'default')
    {
        return self::$query_errors[$linkname]['error'] . ":" .self::$query_errors[$linkname]['errq'];
    }

    public static function escape($string, $linkname = 'default')
    {
        return mysqli_real_escape_string(self::$mysql_connections[$linkname], $string);
    }

}
