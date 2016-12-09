<?php

namespace APITools;

use Pimple\Container;
use Bullet\Request;
use Bullet\Response;
use APITools\Config;
use APITools\DB;


class App extends Container
{
    /**
     * New App instance
     *
     * @param array $values Array of config settings and objects to pass into Pimple container
     */
    public function __construct(array $values = array())
    {
        parent::__construct($values);
    }

    public function run($request) {
        return ['status'=>true] ;
    }

    public function sendMail ($from, $to, $subject, $message){

            mail($to, $subject, $message,
                implode("\r\n", ["From: $from"]));

    }

}
