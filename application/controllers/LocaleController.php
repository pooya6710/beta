<?php

namespace application\controllers;
class LocaleController{
    public $lang;
    public function __construct($lang = ""){
        if ($lang == "") {
            $this->lang = $_ENV['DEFAULT_LANG'];
        }
        else{
            $this->lang = $lang;
        }
    }

    public function trans($path , $vars = [] , $lang = "")
    {
        $base_url = dirname(__DIR__, 2);
        $path = explode('.', $path);
        $index_name = end($path);
        array_pop($path);
        $folder = implode('/', $path);
        $path = $base_url . "/locale/" . $this->lang. "/" . $folder . ".php";
        if (file_exists($path)){
            $values = require($path);
            if (!isset($values[$index_name])){
                return $this->trans('error.default.text_not_found');
            }
            if ($vars){
                $values[$index_name] = json_encode($values[$index_name]);
                foreach ($vars as $key => $var) {
                    $values[$index_name] = str_replace('%' . $key . '%', $var, $values[$index_name]);
                }
                $values[$index_name] = json_decode($values[$index_name]);
            }
            return $values[$index_name];
        }
        else{
            return $this->trans('error.default.file_not_found');
        }
    }
    public function getLocale()
    {
        return $this->lang;
    }

}