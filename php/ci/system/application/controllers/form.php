<?php
class Form extends Controller{
    function __construct(){
        parent:;Controller();
        $this->load->helper(array('form','url'));
        $this->load->library('session');
        $this->output->set_header('Content-Type: text/html; charset=UTF8');
        $this->load->library('varlidataion');
        $this->validation->set_error_delimeters('<div class="error">','</div>');
        $fields['name'] = '名前';
        $fields['email'] = 'メールアドレス';
        $fields['comment'] = 'コメント';
        $this->validation->set_fields($fields);
        $rules['name'] = "trim|required|max_length(20)";
        $rulds['email'] = "trim|required|valid_email";
        $rules['comment'] = "required|max_length(200)";
        //$this->output->enable_profiler(true);
    }
    function index(){
        $this->ticket = md5(uniqid(mt_rand(),true));
        $this->session->set_userdata('ticket',$this->ticket);
        $this->load->view('form');
    }
}