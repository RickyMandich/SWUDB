<?php
class Card {
    public $unica;
    public $nome;
    public $titolo;
    public $espansione;
    public $uscita;
    public $numero;
    public $aspettoPrimario;
    public $aspettoSecondario;
    public $tipo;
    public $tratti;
    public $descrizione;
    public $arena;
    public $costo;
    public $vita;
    public $potenza;
    public $rarita;
    public $prezzo;
    public $artista;

    public function __construct($jsonData) {
        $this->unica = $jsonData['unica'];
        $this->nome = $jsonData['nome'];
        $this->titolo = $jsonData['titolo'];
        $this->espansione = $jsonData['espansione'];
        $this->uscita = $jsonData['uscita'];
        $this->numero = $jsonData['numero'];
        $this->aspettoPrimario = $jsonData['aspettoPrimario'];
        $this->aspettoSecondario = $jsonData['aspettoSecondario'];
        $this->tipo = $jsonData['tipo'];
        $this->tratti = $jsonData['tratti'];
        $this->descrizione = $jsonData['descrizione'];
        $this->arena = $jsonData['arena'];
        $this->costo = $jsonData['costo'];
        $this->vita = $jsonData['vita'];
        $this->potenza = $jsonData['potenza'];
        $this->rarita = $jsonData['rarita'];
        $this->prezzo = $jsonData['prezzo'];
        $this->artista = $jsonData['artista'];
    }

    public function __toString() {
        $result = '';
        foreach(get_object_vars($this) as $key2=>$value2){
            if(gettype($value2) === "array"){
                $result = $result. "|=>". $key2."<br>";
                foreach($value2 as $value3){
                    $result = $result. "|==>".$value3."<br>";
                }
            }else{
                $result = $result. "|=>". $key2."=>".$value2."<br>";
            }
        }
        $result = $result. "<br>";
        return $result;
    }

    public function getInsertSql(){
        $insert = "insert into carte (";
        foreach(get_object_vars($this) as $key => $value){
            $insert .= $key.", ";
        }
        $insert = substr($insert,0,-2).")\nvalues";
        $insert = $insert."(";
        foreach(get_object_vars($this) as $key=>$value){
            if (!$value) $value = 0;
            if ($key === 'tratti') $value = (str_contains($value, " * ")?join(" * ", $value):$value);
            if(gettype($value) === "string"){
                $insert = $insert.'\'';
            }
            $insert = $insert.str_replace("'", "\'", $value);
            if(gettype($value) === "string"){
                $insert = $insert.'\'';
            }
            $insert = $insert.", ";
        }
        $insert = substr($insert,0,-2);
        $insert = $insert."),\n";
        $insert = substr($insert,0,-2);
        echo str_replace("\n", "<br>", $insert.";");
        return $insert.";";
    }
}
