<?php
    class Database
    {
        private static $dsn = "mysql:host=localhost;dbname=test";
        private static $user = 'yamada';
        private static $password = 'xxxxxxx';
        private static $options = array();
        
        public static function connect(){
            $pdo = new PDO(self::$dsn,self::$user,self::$password,self::$options);
            $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
            return $pdo;
        }
    
    }