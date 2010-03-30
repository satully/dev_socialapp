<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>時刻メッセージ</title>
</head>
<body>
<?php
    $now_time = date("H時i分s秒");
    echo $now_time;
    echo '<br />';
    $now_hour = date('H');
    if($now_hour==12){
        echo('お昼です');
    }
    elseif($now_hour==3){
        echo('3時のおやつです');
    }
    else{
        echo('今日も頑張って！');
    }
    ?>
</body>
</html>