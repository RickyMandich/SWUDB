<?php
require_once "/membri/swudb/header.php";
class Cards{
    public $collezione;
    public function __construct(){
        $this->collezione = [];
    }

    public function add(Card $card){
        array_push($this->collezione, $card);
    }

    public function print(){
        foreach($this->collezione as $key => $value){
            echo $key."<br>".$value;
        }
    }
}