<?php
    class Deck{
        private $nome;
        private $leader;
        private $base;
        private $deck;
        private $sideboard;
        private $carte;

        function __construct(array $json){
            $this->nome = $json["metadata"]["name"];
            $this->leader = new Card($json["leader"]["id"]);
            $this->base = new Card($json["base"]["id"]);
            $this->deck = [];
            foreach($json["deck"] as $card){
                for($i=0;$i<$card["count"];$i++){
                    array_push( $this->deck, new Card($card["id"]));
                }
            }
            $this->sideboard = [];
            foreach($json["sideboard"] as $card){
                array_push( $this->sideboard, new Card($card["id"]));
            }
            $this->carte = [];
            array_push($this->carte, $this->leader);
            array_push($this->carte, $this->base);
            foreach($this->deck as $card){
                array_push( $this->carte, $card);
            }
            foreach($this->sideboard as $card){
                array_push( $this->carte, $card);
            }
        }

        function output(){
            echo $this->nome."<br>";
            echo $this->leader->output()."<br>";
            echo $this->base->output()."<br>";
            echo "deck:<br>";
            foreach($this->deck as $card){
                echo "----".$card->output()."<br>";
            }
            echo "sideboard:<br>";
            foreach($this->sideboard as $card){
                echo "----".$card->output()."<br>";
            }
            echo "carte:<br>";
            foreach($this->carte as $card){
                echo "----".$card->output()."<br>";
            }
        }

        function getInsertSql(){
            $insert = "insert into mazzi\nvalues";
            foreach( $this->carte as $card){
                $insert = $insert."('".$this->nome."','". $card->espansione."',". $card->numero .",". unserialize($_SESSION["user"])->getID()."),";
            }
            $insert = substr($insert,0,-1);
            return $insert.";";
        }
    }
    class Card{
        public $espansione;
        public $numero;
/*
        public function __construct($espansione, $numero){
            $this->espansione = $espansione;
            $this->numero = $numero;
        }/**/

        public function __construct($id){
            $this->espansione = explode("_", $id)[0];
            $this->numero = explode("_", $id)[1];
        }

        function output(){
            return $this->espansione."_".$this->numero;
        }
    }