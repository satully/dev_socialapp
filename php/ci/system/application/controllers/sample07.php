<?php
class Sample07 extends Controller{
    function __construct(){
        parent::Controller();
        $this->load->helper('form');
        $this->load->database();
        $this->load->library('user_agent');
        $this->output->enable_profiler(true);
    }
    function index(){
        
    }
}