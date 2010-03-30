<?php
    require_once('/usr/local/lib/smarty/Smarty.class.php');
    
    class Template extends Smarty {
        
        function Template(){
        
            $this->template_dir= "templates/";
            $this->compile_dir= "templates_c/";
            $this->config_dir= "configs/";
            $this->cache_dir= "cache/";
        
        }
        
    }
