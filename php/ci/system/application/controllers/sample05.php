<?php
class Sample05 extends Controller{
    function index(){
        echo $this->_private_method();
    }
    function _private_method(){
        echo "プライベートメソッドです";
    }
}