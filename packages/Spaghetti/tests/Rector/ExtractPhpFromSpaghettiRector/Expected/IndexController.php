<?php

class IndexController
{
    public function render()
    {
        $url = $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
        require_once __DIR__ . '/index_template.php';
    }
}
