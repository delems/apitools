<?php

namespace APITools;

use Pimple\Container;
use Bullet\Request;
use Bullet\Response;
use APITools\Config;
use APITools\Exceptions\LogicException;


class App extends \Bullet\App
{
    protected $modules=null;

    /**
     * New App instance
     *
     * @param array $values Array of config settings and objects to pass into Pimple container
     */
    public function __construct(array $values = array())
    {
        parent::__construct($values);

        //print_r($values);
        $this->setHandlers();
    }

    public function getModule($module) {
        if( isset($this->module[$module])  ){
            return $this->module[$module];
        }

        throw new LogicException(507, 500);
    }


    public function setHandlers() {

        $API = $this;

        // Чего-то не работает
        $this->on('before', function(Request $request, Response $response) {
            $this->prerun($request);
        });


        $this->on('Exception', function(Request $request, Response $response, \Exception $e) {

            $response->status($e->getCode());

            //print_r($e);

            // Код возврата
            $ret = ['status' => false ];
            if( isset($e->getErrorCode) && is_callable($e->getErrorCode)) {
                $ret['error_code'] = $e->getErrorCode();
            }else{
                $ret['error_code'] = $e->getCode();
            }

            if( $this->config['debug'] ) {
                $ret['debug'] = $e->getMessage();
            }


            if($e instanceof \APITools\Exceptions\LogicException) {

                $ret['error_text'] = $e->getErrorText();
                $errors = $e->getErrors();

                if( null !== $errors) {
                    $ret['errors'] = $errors;
                }

            }else{

                // Отправка уведомления об ошибке
                $from = \APITools\Config::getData('Notification.from_name') .  
                        '<' . \APITools\Config::getData('Notification.from_mail') . '>';

                $to = \APITools\Config::getData('Notification.to_fail_mail');
                $subject = 'APIv1: Fail event';
                $message = $e->getMessage() . "\n".
                    //"\n\nQuery:\n". $qdata . 
                    //"\n\nParams:\n". $pdata .
                    "\n\nServer:\n". print_r($_SERVER,1);

                mail($to, $subject, $message,
                    implode("\r\n", ["From: $from"]));


            }

            $response->content($ret);

        });

        $this->removeResponseHandler('array_json');
        $this->registerResponseHandler(
            null,
            function($response) {
                $response->contentType('application/json');
                $content = $response->content();
                if( !isset($content['status']) ) {
                    $content=['status'=>true, 'count'=>count($content), 'data'=>$content];
                }
            
                $response->content(json_encode($content, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ));
            }
        );
   
    }


    /* 
        Все до начала обработки 
    */
    public function prerun(Request $request) {

        $raw = $request->raw();

        if( $raw !== NULL and $raw != '') {
            $json = json_decode($raw, true);

            if($json !== NULL) {
                
                $request->setParams($json);
            }else{
                $ret = [
                    'status' => false,
                    'error_code' => 102,
                    'error_text' => 'POST-параметры должны передаваться в формате JSON',
                ];
                echo json_encode($ret, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
                exit;
            }
        }
    }

}
