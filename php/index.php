<?php
    require_once('class/Template.php');
    require_once('class/Database.php');
    
    $db = Database::connect();
    $sql = "SELECT * FROM users";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    
    $smarty = new Template();
     
    $smarty->assign("result",$result);
    $smarty->display("index.tpl");
    
