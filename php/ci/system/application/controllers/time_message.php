<?php
    class Time_message extends Controller{
        function index(){
            $data['title'] = '時刻メッセージ';
            $data['now_time'] = date('H時i分s秒');
            $now_hour = date('H');
            if($now_hour==12){
                $data['message'] = 'お昼です';
            }
            elseif($now_hour==3){
                $data['message'] = '3時のおやつです';
            }
            else{
                $data['message'] = '今日も頑張って！';
            }
        $this->load->view('time_message_view',$data);
        }
    }
?>