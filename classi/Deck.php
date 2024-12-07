<?php
    require_once "header.php";
    class Deck{
        public $nome;
        public $id;
        public $leader;
        public $base;
        public $deck;
        public $sideboard;
        public $carte;

        function __construct(array $json){
            $this->nome = $json["metadata"]["name"];
            $this->leader = new DeckCard($json["leader"]["id"]);
            $this->base = new DeckCard($json["base"]["id"]);
            $this->deck = [];
            foreach($json["deck"] as $card){
                for($i=0;$i<$card["count"];$i++){
                    array_push( $this->deck, new DeckCard($card["id"]));
                }
            }
            $this->sideboard = [];
            foreach($json["sideboard"] as $card){
                for($i=0;$i<$card["count"];$i++){
                    array_push( $this->sideboard, new DeckCard($card["id"]));
                }
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

        function createDeck($public){
            require_once "header.php";
            $id = $GLOBALS["conn"]->query("select id from mazzi where nome = '".$this->nome."'");
            if(!$id->fetch_assoc()){
                $GLOBALS["conn"]->query("insert into mazzi (nome, public, codUtente) values('".$this->nome."', ".$public.",".unserialize($_SESSION["user"])->getID().");");
            }
        }

        function getInsertSql(){
            $id = $GLOBALS["conn"]->query("select id from mazzi where nome = '".$this->nome."'");
            try{
                $this->id = $id->fetch_assoc()["id"];
            }catch(Error $e){
                $this->createDeck("0");
                $id = $GLOBALS["conn"]->query("select id from mazzi where nome = '".$this->nome."'");
                $this->id = $id->fetch_assoc()["id"];
            }
            $insert = "insert into composizione\nvalues";
            foreach( $this->carte as $card){
                $insert = $insert."('".$this->id."','". $card->espansione."',". $card->numero .","."0"."),";
            }
            $insert = substr($insert,0,-1);
            return $insert.";";
        }
    }
    class DeckCard{
        public $espansione;
        public $numero;

        public function __construct($id){
            $this->espansione = explode("_", $id)[0];
            $this->numero = explode("_", $id)[1];
        }

        function output(){
            return $this->espansione."_".$this->numero;
        }
    }