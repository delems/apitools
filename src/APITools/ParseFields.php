<?php
/**
 * Created by PhpStorm.
 * User: pavelsuslov
 * Date: 30.10.15
 * Time: 2:52
 */


namespace APITools;

class ParseFields 
{
    public function FetchData($url, $post = null, $p = null)
    {
        if( $p === false ) {
            self::LogOut("Fail heads!!!!");
            return false;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);

        if( isset($p['header']) ){
          curl_setopt ($ch, CURLOPT_HTTPHEADER, $p['header']);
        }

        if( isset($p['proxy']) ){
            curl_setopt ($ch, CURLOPT_PROXY, $p['proxy']);
            curl_setopt ($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
        }

        if( $post ){
            curl_setopt ($ch, CURLOPT_POST, true);
            curl_setopt ($ch, CURLOPT_POSTFIELDS, $post);
        }

        $reply_text = curl_exec ($ch);
        curl_close ($ch);

        return $reply_text;

    }



    public function ParseFieldsData($table, $fields, $reply_text)
    {
        try {

            $lines = [];
            $strings = preg_split("/\n/", $reply_text);

            $heads=[];
            $heads_atr=[];
            $count=0;
            $hcount=0;
            foreach($strings as $str){
                $oneline = str_getcsv($str);

                if( $count == 0 ){

                    //echo print_r($oneline,1) ."\n";
                    foreach ($oneline as $field) {
                        if( isset($fields[$field])){
                            $heads[$hcount]=$fields[$field]['name'];
                            $heads_atr[$hcount]=$fields[$field];
                            $heads_atr[$hcount]['field']=$field;
                        }else{
                            self::LogOut("Failed field ". $field);
                            exit;
                        }
                        $hcount++;
                    }

                }else{
                    $row=[];
                    $line=[];
                    if( $oneline [0] != '' ) {
                        foreach ($heads as $key => $name ){
                            if( isset($heads_atr[$key]['split']) ){
                                $split=preg_split($heads_atr[$key]['split'], $oneline[$key]);

                                //print_r($split);
                                foreach ($heads_atr[$key]['split_fields'] as $split_key => $split_row) {
                                   $row[]="`$split_row` = '".\DB::escape($split[$split_key])."'";
                                   //echo "`$split_row` = '".\DB::escape($split[$split_key])."'";
                                   $line[$split_row]=$split[$split_key];
                                }


                            }else{
                                $row[]="`$name` = '".\DB::escape($oneline[$key])."'";
                                $line[$heads_atr[$key]['field']]=$oneline[$key];
                            }


                        }
                    }

                    //print_r($row);
                    if(count($row) >0 and $row[0] != "''") {
                        $this->fields[]=join(",\n", $row);
                    }

                    if(count($line) >0 ) {
                        $lines[]=$line;
                    }



                    if( count($this->fields) > 0 ) {
                        //print_r(join(",\n", $heads));
                        //print_r($this->fields);

                        $q = "insert into $table  
                               SET ". join( $this->fields ) . "
                               on duplicate key update " . 
                               join( $this->fields );
                            

                        //echo $q ."\n";
                        //exit;

                        \DB::Q($q);
                        $this->fields=[];

                    }

                    //exit;
                }
                $count++;

            }

        } catch (LogicException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw $e;
            //throw new LogicException("Service Unavailable", 503);
        }


        self::LogOut("Insert $count \n");
        return($lines);

    }

    public function ParseDate($date)
    {
        if( preg_match("/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/", $date)){
            return substr($date, 0, 10) .  ' ' . substr($date, 11, 8);
        }


        return $date;
    }


    public function InsertFieldsData($table, $fields, $data)
    {
        $count=0;

        foreach ($data as $point) {
            $row=[];

            foreach ($fields as $key => $value) {
                $name=$value['name'];

                if( preg_match('/(.+)\#(.+)/', $key, $m)) {
                    //echo "\n" .$name .' ' . $key ."\n";
                    //print_r($point);
                    if( isset($point[$m[1]][$m[2]]) ) {
                        $v = $this->ParseDate($point[$m[1]][$m[2]]);
                    }else{
                        $v = null;
                    }
                }else{
                    if( isset($point[$key]) ) {
                        $v = $this->ParseDate($point[$key]);
                    }else{
                        $v = null;
                    }
                }

                if( is_array($v)) 
                    $v = json_encode($v);

                if( $v !== null ) {
                    $row[]="`$name` = '".\DB::escape($v)."'";
                }elseif( $value['value'] ) {
                    $row[]="`$name` = " . $value['value'];
                }else{
                    $row[]="`$name` = null ";
                }
            }
            
            $rows = join( ', ', $row );
            $q = "insert into $table  
                   SET $rows ON duplicate key update $rows";


            //echo $q ;
            \DB::Q($q);

            $count++;
        }

        //\DB::CloseConnection();

        return $count;
    }




}