<?php

class Language {
    private $lang_content;
    private $lang_folder;

    function __construct($lang_folder, $lang_content = ''){
        $this->lang_folder = $lang_folder;
        $this->lang_content = $lang_content;
        
    }

    public function set($lang_content) {
        $this->lang_content = $lang_content;
    }

    public function get($var) {
        $lang = $this->load_lang_content();
        echo $lang[$var];
    }

    private function load_lang_content() {
        include($this->lang_folder.DIRECTORY_SEPARATOR.$this->lang_content);
        return $lang;
    }
}
?>