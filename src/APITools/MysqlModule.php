<?php
/**
 * Created by PhpStorm.
 * User: a.kalinin
 * Date: 19.03.14
 * Time: 12:10
 */

namespace APITools;
use \Bullet\Request;
use APITools\DB;
use APITools\Exceptions\LogicException;


/**
 * Class Config
 * @package System
 */
class MysqlModule  extends Module
{
    public function escape($str)
    {
        return \APITools\MysqlDB::escape($str);
    }

    public function Insert($values)
    {
        $this->CheckFields($values);

        $q = "insert into `" .$this->DbTable()."`
               SET " . $this->DbValues($values);

        \APITools\MysqlDB::Q($q);

        return ["status"=>true, "status_text" => $this->GetCodeText('add', 'success')];
    }

    public function Replace($values)
    {
        $this->CheckFields($values);
        $rows = $this->DbValues($values);

        if( empty($rows) ) {
            throw new LogicException(515,  500);
        }

        $q = "insert into `" .$this->DbTable()."`
               SET $rows on duplicate key update $rows ";

        //echo $q;
        \APITools\MysqlDB::Q($q);
        return ["status"=>true];
    }


    public function Save($keys, $values)
    {

        $rec = $this->View($keys);
        if( empty($rec) ) {
            throw new LogicException(301,  200);
        }

        //print_r($rec);
        $rows = $this->DbValues($values);

        $q = "update `" .$this->DbTable()."`
                SET $rows
                where " . $this->DbWhere($keys);

        //echo $q;
        \APITools\MysqlDB::Q($q);
        return ["status"=>true];

        throw new LogicException(514,  500);
    }


    public function Delete($keys)
    {
        $rec = $this->View($keys);
        if( empty($rec) ) {
            throw new LogicException(301,  200);
        }

        $where = $this->DbWhere($keys);
        if( $where === '1' ) {
            // защита от случйного  удаления всех записей
            throw new LogicException([519,'Не определены ограничения для запроса '.get_class($this).':Delete'],  500);
        }

        $q = "delete
                from  " . $this->DbTable()        . "  
                where " . $where;

        //echo $q;
        \APITools\MysqlDB::Q($q);
        return ["status"=>true];
    }

    public function DeleteAll($keys)
    {
        $this->CheckFields($keys);

        $where = $this->DbWhere($keys);
        
        if( $where === '1' ) {
            // защита от случйного  удаления всех записей
            throw new LogicException([519,'Не определены ограничения для запроса '.get_class($this).':DeleteAll'],  500);
        }

        $q = "delete
                from  " . $this->DbTable()        . "  
                where " . $where;

        //echo $q;
        \APITools\MysqlDB::Q($q);
        return ["status"=>true];
    }

    public function View($keys) {

        //print_r($keys);
        $this->CheckFields($keys, 'view'); 

        $where = $this->DbWhere($keys);

        if( $where === '1' ) {
            // Защита от просмотра всех записей
            throw new LogicException([519,'Не определены ограничения для запроса '.get_class($this).':View'],  500);
        }

        $q = "select  " . $this->DbFields()       . " 
                from  " . $this->DbTable()        . "  
                      " . $this->DbJoin()         . "
                where " . $where  . "
                limit 1";
       
        //echo "View: $q\n";
        return \APITools\MysqlDB::QFA($q);
    }

    public function ViewList($keys = []) {

        $this->CheckFields($keys,'list');

        $q = "select  " . $this->DbFields()       . " 
                from  " . $this->DbTable()        . "  
                      " . $this->DbJoin()         . "
                where " . $this->DbWhere($keys)   . "
                      " . $this->DbOrder();
        
        //$this->debug[] = $q;
        //echo "LIST: \n" . $q ."\n";

        return \APITools\MysqlDB::QFAL($q);;
    }


    public function Search($keys = []) {

        $this->CheckFields($keys);     

        $q = "select  " . $this->DbFields()       . " 
                from  " . $this->DbTable()        . "  
                      " . $this->DbJoin()         . "
                where " . $this->DbWhere($keys)   . "
                      " . $this->DbOrder()        . "
                      " . $this->DbLimit();
          
        //$this->debug[] = $q;
        //echo "Search: " . $q."\n";

        return \APITools\MysqlDB::QFAL($q);;
    }

    protected function DbWhere($keys) {

        //print_r($keys);
        $where = null;
        $wkeys = $this->GetParam('filter');

        foreach ($this->GetFields() as $key) {

            //echo "Check : ". $key . "\n";
            $field=$this->GetFieldParam($key,'dbfield');
            // Поле авторизации - ключ
            if( isset($wkeys[$key]) ) {
                if( $field !== null ) {
                    if( is_array($wkeys[$key]) ){
                        foreach ($wkeys[$key] as $search) {
                            $where[]=$search;
                        }
                    }else{
                        $where[]="`$field` = '".$this->escape($wkeys[$key])."'";
                    }
                }else{
                    throw new LogicException([519,"Отсетствует field для ".get_class($this).":filter->$key", ],  500);
                }

                unset($wkeys[$key]);
            }

            
            // Жестко установленные значения полей
            if( $this->isFieldParam($key,'value') ) {
                $value=$this->GetFieldParam($key,'value');
                if( $value === null) {
                    $where[]="isnull(`$field`)";
                }else{
                    //$where[]="`$field` = '".$this->escape($value)."'";
                    // Вот тут надо явно контролировать формат данных
                    // Так не очень безопасно
                    $where[]="`$field` = '".$this->escape($value)."'";
                }
            }


            if( $field !== null && ( $this->isSearchField($key) || $this->isKeyField($key) ) ) {

                //echo "Check1 : ". $key . "\n";
                if( isset($keys[$key]) && $keys[$key] !== null && $keys[$key] !== '' ) {

                    if( is_array($keys[$key]) ) {
                        $values='';
                        foreach ($keys[$key] as $value) {
                            if( $values ) {$values.=',';}
                            $values.="'".$this->escape($value)."'";
                        }

                        if( $values ) {
                            $where[]="`$field` in (".$values.")";
                        }
                    }else{
                        $search = $this->GetFieldParam($key,'search');
                        //echo "S: $key ($field) = $search \n";
                        switch($search) {
                            case 'eq':
                                //echo "eq";
                                $where[]="`$field` = '".$this->escape($keys[$key])."'";
                                break;
                            case 'ge':
                                $where[]="`$field` >= '".$this->escape($keys[$key])."'";
                                break;
                            case 'gt':
                                $where[]="`$field` > '".$this->escape($keys[$key])."'";
                                break;
                            case 'le':
                                $where[]="`$field` <= '".$this->escape($keys[$key])."'";
                                break;
                            case 'lt':
                                $where[]="`$field` < '".$this->escape($keys[$key])."'";
                                break;
                            case 'like':
                                $where[]="`$field` like '%".$this->escape($keys[$key])."%'";
                                break;
                            default:
                                $where[]="`$field` = '".$this->escape($keys[$key])."'";
                                //throw new LogicException([519, "Неизвестный тип поиска $search"], 500);

                                break;
                        }
                    }
                }
            }else{
            //    throw new LogicException(519,  500);
            }
        }


        if( !empty($wkeys) ) {
            foreach ($wkeys as $key => $search) {
                if( is_array($search) ) {
                    foreach ($wkeys[$key] as $search) {
                        $where[]=$search;
                    }
                }else{
                    throw new LogicException([519,"Отсетствует field для ".get_class($this).":filter->$key", ],  500);
                }
            }
        }

        //print_r($where);
        //echo "\n\n";

        if( $where !== null and !empty($where) ) {
            return join( ' and ', $where );
        }
      
        //echo "RQ";
        return '1';
    }


    protected function DbOrder() {

        $order = $this->GetParam('order');

        if( !$order ){
            return '';
        }

        $orders='';

        if( is_array($order) ) {
            foreach ($order as $field) {

                $dbfield=$this->GetFieldParam($field,'dbfield');
                if( $dbfield ) {
                    $field = $dbfield; 
                }

                if( $orders ) 
                    $orders .= ',';
                $orders .= $field;
            }
        }else{
            $orders = $order;
            $dbfield=$this->GetFieldParam($order,'dbfield');
            if( $dbfield ) {
                $orders = $dbfield;
            }
        }

        if( !$order ){
            return '';
        }


        return ' Order by ' . $orders;
    }

    protected function DbTable() {
        if( !isset($this->module['table']) ){
            //print_r($this->module);
            \APITools\Log::error("Table not exists!".print_r($this->module,1));
            throw new LogicException(503,  500);
        }

        return $this->module['table'];
    }

    protected function DbJoin() {
        if( !isset($this->module['join']) ){
            return '';
        }

        return $this->module['join'];
    }


    protected function DbLimit() {
        if( !isset($this->module['limit']) ){
            return '';
        }

        return ' limit ' . $this->module['limit'];
    }


    protected function DbValues($values) {

        $fields=[];
        foreach ($this->GetFields() as $key) {

            $field=$this->GetFieldParam($key,'dbfield');
            if( $field !== null ) {
                // не ключ
                if( isset( $values[$key] ) && $values[$key]!==null ) {
                    if( is_array($values[$key]) ){
                        $fields[$key]="`$field` = '".$this->escape(json_encode($values[$key]))."'";
                    }else{
                        $fields[$key]="`$field` = '".$this->escape($values[$key])."'";
                    }
                }else{

                    // Жестко установленные значения полей
                    if( $this->isFieldParam($key,'value') ) {
                        $value=$this->GetFieldParam($key,'value');
                        if( $value === null) {
                            $fields[$key]="`$field` = NULL";
                        }else{
                            //$where[]="`$field` = '".$this->escape($value)."'";
                            // Вот тут надо явно контролировать формат данных
                            // Так не очень безопасно
                            $fields[$key]="`$field` = '".$this->escape($value)."'";
                        }
                    }

                    /*
                    // Втакает везде. Save, Insert
                    $default = $this->GetFieldParam($key, 'default');
                    if( $default !== null ) {
                        if( is_callable($default) ) {
                            $fields[$key]="`$field` = '".$this->escape($default())."'";
                         }else{
                            $fields[$key]="`$field` = '".$this->escape($default)."'";
                        }
                    }
                    */
                }
            }
        }

        if( empty($fields) ) {
            throw new LogicException(515,  500);
        }

        return join( ' , ', $fields );
    }


    protected function DbFields() {
        $fields = [];
        foreach ($this->GetFields() as $key) {
            $field=$this->GetFieldParam($key,'dbfield');
            //echo "$field \n";

            if( $field !== null ) {
                //$fields[$key] = "`$field` as '$key'";
                $fields[$key] = "$field as '$key'";
            }

        }

        if( empty($fields) ) {
            throw new LogicException(502,  500);
        }

        return join( ', ', $fields );
    }




}

