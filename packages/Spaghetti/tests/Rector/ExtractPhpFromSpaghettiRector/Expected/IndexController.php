<?php

class IndexController
{
    public function render()
    {
        global $variable1;
        $variable1 = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    }
}