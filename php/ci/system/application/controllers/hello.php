<?php
class Hello extends Controller{
    function index(){
        $this->load->view("hello_view.php");
    }
}
