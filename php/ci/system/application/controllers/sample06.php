<?php
class Sample06 extends Controller{
    function _remap($method){
        if($method == 'test'){
            $this->_private_method();
        }
        else{
            $this->other_method();
        }
    }
    function _private_method(){
        echo "_private_methodが実行されました。";
    }
    function other_method(){
        echo "other_methodが実行されました";
    }
}
