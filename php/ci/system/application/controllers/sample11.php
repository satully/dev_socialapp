<?php
class Sample11 extends Controller{
    function index(){
        $this->load->model('sample11_model');
        $fruits = $this->sample11_model->get_price();
        foreach($fruits as $key => $value){
            echo $key . $value . '<br />';
        }
    }
}