<?php
/**
 * Created by PhpStorm.
 * User: a.kalinin
 * Date: 19.03.14
 * Time: 12:10
 */

namespace APITools;
use \Bullet\Request;
use APITools\Exceptions\LogicException;


/**
 * Class Config
 * @package System
 */
class Module
{
    protected $fields=null;
    protected $check=false;
    protected $action=null;
    protected $module=null;
    protected $BulletApp=null;
    protected $values=null;


     /**
     * New App instance
     *
     * @param array $values Array of config settings and objects to pass into Pimple container
     */
    public function __construct(\Bullet\App $BulletApp, array $values = array())
    {
        $this->BulletApp=$BulletApp;
        $this->module=$values;
        $this->request=null;
    }


    protected function GetFields() {

        if( !isset($this->module['fields']) ){
            \APITools\Log::error("Fields not exists!");
            throw new LogicException(502,  500);
        }
        return array_keys( $this->module['fields'] );
    }



    protected function GetParam($param) {

        if( isset($this->module[$param]) ){
            if( is_callable($this->module[$param]) ) {
                return $this->module[$param]();
            }else{
                return $this->module[$param];
            }
        }
        return null;
    }


    protected function GetCodeText($action, $code) {
        if( !isset($this->module['actions'][$action]['codes'][$code]) ){
            return $code;
        }

        return $this->module['actions'][$action]['codes'][$code];
    }


    protected function isFieldParam($field, $param) {
        
        if( !isset($this->module['fields'][$field][$param]) ){
            return false;
        }

        return true;
    }




    protected function GetFieldParam($field, $param, $values=null) {
        
        if( !isset($this->module['fields'][$field][$param]) ){
            /*
            if($requred)  {
                //print_r($this->module);
                throw new LogicException(502,  500);
            }
            */

            return null;
        }


        if( is_callable($this->module['fields'][$field][$param]) ) {
            return $this->module['fields'][$field][$param]($values);
        }


        /* 
        Возможность привязывать классы как тип value
        if( $this->module['fields'][$field][$param] instanceof Module ) {
            return $this->module['fields'][$field][$param]->ValueByKey($values);
        }
        */



        return $this->module['fields'][$field][$param];
    }

    protected function GetViewKey() {
        return $this->GetParam('viewkey');
    }

    protected function isViewField($view, $key=null) {

        $fields = $this->GetParam('output');

        //print_r($fields);
        if( is_array($fields) && isset($fields[$view]) ) {
            if( $key === null ) {
                return true;
            }

            return in_array($key, $fields[$view]);
        }

        return false;
    }

    protected function isSearchField($key) {
        $fields = $this->GetParam('search');
        if( is_array($fields) ) {
            return in_array($key, $fields);
        }
    }

    protected function GetFieldKey() {
        return $this->GetParam('key');
    }

    protected function isKeyField($key) {
        return $key === $this->GetFieldKey();
    }


    public function RunAction(Request $request, $action)
    {
        if( $request->param('help') !== NULL) {
            return $this->Help($action);
        }

        // Если такое действие определено.
        if( isset($this->module['actions'][$action] ) ) {
            
            $this->action=$action;

            if( isset($this->module['actions'][$action]['action'])  && 
                is_callable($this->module['actions'][$action]['action']) ) {
                $ret = $this->module['actions'][$action]['action']($request, $action);
                //print_r($ret);
                return $ret;
            }else{
                switch ($action) {
                    case 'add':
                        return $this->Insert($request->param());
                        break;
                    case 'view':
                        $data = $this->View($request->param());
                        return $this->FormatRow($data);
                        break;
                    case 'list':
                        $data = $this->ViewList($request->param());
                        $rows = $this->FormatArray($data);
                        return $rows;
                        break;
                    case 'search':
                        $data = $this->Search($request->param());
                        $rows = $this->FormatArray($data);
                        return $rows;
                        break;
                    case 'save':
                        return $this->Save($request->param());
                        break;
                    case 'delete':
                        $rec = $this->View($request->param());
                        //print_r($rec);
                        return $this->Delete($request->param());
                        break;
                    case 'deleteall':
                        return $this->DeleteAll($request->param());
                        break;
                    default:
                        throw new LogicException(505,  500);
                        break;
                }
            }
        }else{
            throw new LogicException(505,  500);
        }
    }


    public function CheckFields($values=null, $action=null)
    {
        if( $this->check ) {
            return true;
        }

        $fails=[];
        foreach ($this->GetFields() as $key) {
            $value = null;
            if( isset($values[$key]) ) {
                $value = $values[$key];
            }

            $fail = $this->checkField($key, $value, $action);
            if( !empty($fail) ) {
                $fails[$key] = $this->checkField($key, $value, $action);
            }
        }

        if( count($fails) > 0 ) {
            $e = new LogicException(201, 200); // Неверные пареметры
            $e->setErrors($fails);
            throw $e;
        }

        $this->check = true;
        return true;
    }



    public function CheckField($key, $value, $action=null)
    {
        $error_code=null;
        $checks=$this->GetFieldParam($key, 'check');

        if( $action===null ) {
            $action = $this->action;
        }


        if( isset( $checks[$action] ) ) {

            foreach ($checks[$action] as $check) {

                // Только одна ошибка для поля
                if( !$error_code ) {

                    switch ($check) {

                        case 'require':  //Обязательное поле
                            if( null === $value ) {
                                $error_code = 209;
                            }
                            break;
                        case 'noempty':  //Не пустое поле
                            if( !$value ) {
                                $error_code = 210;
                            }
                            break;
                        case 'user':  //Имя пользователя
                            if( $value == '' ) {  // 
                                $error_code = 210;
                            }
                            break;
                        case 'text': //Текст
                            break;
                        case 'int': // Цифры 
                            if( !$this->is_digits($value) ) {  
                                $error_code = 211;
                            }
                            break;
                        case 'bool': // boolean
                            if( $this->is_bool($value) ) { 
                                $error_code = 212;
                            }
                            break;
                        case 'float': // Число с плавающей точкой
                            if( ! $this->is_float($value) ) {  
                                $error_code = 213;
                            }
                            break;
                        case 'enum': // enum
                            $enum = $this->GetFieldParam($key, 'values');
                            if( !is_array($enum)  ) {  
                                throw new LogicException(517,  500);
                            }

                            if( isset($enum[$value]) ) {  
                                $error_code = 220;
                            }
                            break;
                        case 'email': // e-mail
                            //echo "email $value!";
                            if( !$this->is_email($value) ) {  
                                $error_code = 215;
                            }
                            break;
                        case 'phone': // phone
                            //echo "phone $value!";
                            if( !$this->is_phone($value) ) {  
                                $error_code = 216;
                            }
                            break;
                        case 'email|phone': // e-mail or phone 
                            if( !( $this->is_email($value) or $this->is_phone($value) ) ) { 
                                $error_code = 217;
                            }
                            break;
                        case 'unique': 
                            if( !$this->is_unique($key, $value) ) {  
                                $error_code = 219;
                            }
                            break;
                        case 'date': // date
                            if( !$this->is_date($value) ) {  
                                $error_code = 221;
                            }
                            break;
                    }
                }
            }
        }
        //print_r($fail);
        if( $error_code ) {

            return [
                'error_code'  => $error_code,
                'field' => $key,
                'value' => $value,
            ];
        }


        return [];
    }



    public function Help()
    {
        $data = [
            'info' => $this->GetParam('info'),
        ];

        /*
        $view = $this->GetParam('view');
        foreach ($this->GetFields() as $key) {
            if( $this->isViewField() ) {
                $data['fields'][$key] = [
                    'key' => $key,
                    'info' => $this->GetFieldParam($key, 'name'),
                ];
            }
        }

        foreach ($this->GetParam('actions') as $key => $value) {
            $data['actions'][$key] = [
                'action' => $key,
                'info' => (isset($value['info']))?$value['info']:$key,
            ];
        }
        */

        return $data;
    }


    protected function Insert($values)
    {
        return ["status"=>false];
    }


    protected function Replace($values)
    {

         return ["status"=>false];
    }


    protected function Save($keys, $values)
    {
        return ["status"=>false];
    }


    protected function Delete($keys)
    {
        return ["status"=>false];
    }

    protected function DeleteAll($keys)
    {
        return ["status"=>false];
    }

    protected function View($keys) {
        return [];
    }

    protected function ViewList($keys = []) {
        return [];
    }


    protected function Search($keys = []) {
        return [];
    }


    public function FormatArray($data, $type='list', $key=null)
    {

        if( $key === null ){
            $key = $this->GetViewKey();
        }

        if( $key === null ){
            $key = $this->GetFieldKey();
        }

        $rows=[];
        $count=0;

        //echo "Key = $key\n";
        if( is_array($data) and count($data) > 0 ) {
            foreach ($data as $row) {
                $r = $this->FormatRow($row,$type);

                //print_r($r);
                if( $key && $row[$key] !== '') {
                    if( isset($row[$key]) ) {
                        $rows[$row[$key]]=$r;
                    }else{
                        throw new LogicException([518, "В ответе sql отсутствует или пустое поле ".get_class($this).":$key"],  500);
                    }
                }else{
                    //echo "999($count)\n";
                    $rows[$count++]=$r;
                }
            }
        }

        //print_r($rows);
        return $rows;
    }

    public function FormatRow($data, $type='view')
    {
        $row=[];

        if( !$this->isViewField($type) ){
            throw new LogicException([518, "Отсутствует ключевое поле ".get_class($this).":output->$type"],  500);
        }

        foreach ($this->GetFields() as $key) {
            if( $this->isViewField($type, $key) ) {

                if( isset($data[$key]) ) {
                    if( $this->GetFieldParam($key, 'type')  == 'json' && !is_array($data[$key])) {
                        $row[$key]=json_decode($data[$key],1);
                    }else{
                        $row[$key]=$data[$key];
                    }

                    $values = $this->GetFieldParam($key, 'values', $row[$key]);
                    //print_r($values);
                    if( $values!== NULL ){
                        $row[$key] = $values;
                    }

                        //$row[$key] = $values($row[$key]);

                }else{
                    $row[$key]=null;
                }
            }
        }

        return $row;
    }




    public function ValueByKey($data, $type='view', $key=null)
    {
        if( !$this->values[$type] ) {
            $ret = $this->ViewList();
            $this->values[$type] = $this->FormatArray($ret, $type, $key);
            //print_r($this->values[$type]);
        }

        if( is_array($data) ) {
            $rows=[];

            //print_r($data);
            //print_r($this->values);
            foreach ($data as $k => $value) {
                //echo "Check ++$value++ " ;
                if( isset($this->values) 
                        && isset($this->values[$type]) 
                        && isset($this->values[$type][$value]) ) {
                    $rows[]=$this->values[$type][$value];
                }else{

                    $rows[]=[
                        $key => $value, 
                    ];
                }
            }

            return $rows;

        }else{
            //print_r($this->values[$type]);
            if( isset($this->values[$type][$data]) ) {



                return $this->values[$type][$data];
            }else{
                return null;
            }
        }
    }



    function is_float( $string ){
        
        $IsOK = true;

        for ( $i = 0; $i < strlen( $string ); $i++ ){
            
            if ( 
                ( ( $string{$i} < '0' ) || ( $string{$i} > '9' ) )
                &&  ( $string{$i} <> '.' ) 
                &&  ( $string{$i} <> '-' )
            )
                $IsOK = false;  
        }

        return $IsOK;
    }

    function is_domain( $email ){
        
        $p = '/^([-a-z0-9]+\.)+([a-z]{2,24}';
        $p .= '|info|arpa|aero|coop|name|museum|mobi|club)$/ix';
        
        return preg_match( $p, $email );         
    }


    function is_email( $email ){
        
        $p = '/^[a-z0-9!#$%&*+-=?^_`{|}~]+(\.[a-z0-9!#$%&*+-=?^_`{|}~]+)*';
        $p .= '@([-a-z0-9]+\.)+([a-z]{2,24}';
        $p .= '|info|arpa|aero|coop|name|museum|mobi|club)$/ix';

        return preg_match( $p, $email );         
    }


    function is_date( $date ){
        
        $p = '/^([0-9]{4})-([0-9]{2})-([0-9]{2})';
        $p .= '$/ix';
        
        return preg_match( $p, $date );  
    }


    // Допускается использование * в адресе
    function is_email_mask( $email ){
         
        if( $email == '*' ) 
            return 1;
             
        $p = '/^[a-z0-9!#$%&*\*+-=?^_`{|}~]+(\.[a-z0-9!#$%&*+-=?^_`{|}~]+)*';
        $p .= '@([-a-z0-9]+\.)+([a-z]{2,24}';
        $p .= '|info|arpa|aero|coop|name|museum|mobi|club)$/ix';
            
        return preg_match( $p, $email );         
    }

    // Допускается использование формата @domain.ru 
    function is_address( $email ){
        
        $p = '/^[a-z0-9!#$%&*\*+-=?^_`{|}~]*(\.[a-z0-9!#$%&*+-=?^_`{|}~]*)*';
        $p.= '@([-a-z0-9]+\.)+([a-z]{2,24}';
        $p.= '|info|arpa|aero|coop|name|museum|mobi|club)$/ix';
        
        return preg_match( $p, $email );         
    }

    function is_phone( $phone ){
        $p = '/^\+*7*\d{10}$/ix';
        return preg_match( $p, $phone );
    }


    function is_digits( $value ){
        $p = '/^[0-9]+$/ix';
        return preg_match( $p, $value );
    }

    function is_legal( $value ){
        $p = '/^[0-9A-Za-z_\-\.]+$/ix';
        return preg_match( $p, $value );
    }

    function is_ip( $ip ){
        $p = '/^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$/ix';
        return preg_match( $p, $ip );
    }

    function is_host( $host ){
        return is_domain( $host ) or is_ip( $host );
    }

    function is_unique( $field, $value ){

        //Получение количества записей
        $c = $this->ViewList([$field=>$value]);
        //print_r($c);

        if( $c > 0 ) {
            return false;
        }

        return true;
    }


}

