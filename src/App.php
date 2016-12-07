<?php

namespace App;

use Pimple\Container;

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

    public function hello() {
        return "Hello World!";
    }

}
