<?php
class Sample11_model extends Model{
    function __construct(){
        parent::Model();
    }
    function get_price(){
        $fruits = array(
            'りんご' => '200円',
            'みかん' => '100円',
            'ぶどう' => '300円'
        );
        return $fruits;
    }
    
}