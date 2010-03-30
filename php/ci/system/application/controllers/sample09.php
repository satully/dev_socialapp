<?php
class Sample09 extends Controller{
    function index(){
        $data['title'] = 'サンプル';
        $data['contents'] = "CodeIgniterのレイアウトです。";
        $data['header'] = $this->load->view('parts/header',$data,true);
        $data['main'] = $this->load->view('parts/main',$data,true);
        $data['footer'] = $this->load->view('parts/footer',$data,true);
        $this->load->view('layout',$data);
        
    }
}