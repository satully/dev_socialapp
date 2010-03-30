<?php
class Session_sample extends Controller{
    function __construct(){
        parent::Controller();
        header("Content-Type: text/html; charset=UTF-8");
        $this->load->library('session');
    }
    function index(){
        if(!$this->session->userdata('count')){
            $this->session->set_userdata('count',1);
        }
        else{
            $count = $this->session->userdata('count');
            $count++;
            $this->session->set_userdata('count',$count);
        }
        echo('訪問回数：'.$this->session->userdata('count'));
    }
    function destroy(){
        $this->session->sess_destroy();
        echo('セッションをクリアしました');        
    }
}