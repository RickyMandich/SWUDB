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
    public $imboscata;
    public $tenacia;
    public $sopraffazione;
    public $sabotatore;
    public $sentinella;
    public $schermata;
    public $incursione;
    public $valoreIncursione;
    public $recupero;
    public $valoreRecupero;
    public $contrabbando;
    public $valoreContrabbando;
    public $quandoGiocata;
    public $valoreQuandoGiocata;
    public $taglia;
    public $valoreTaglia;
    public $quandoSconfitta;
    public $valoreQuandoSconfitta;
    public $quandoAttacca;
    public $valoreQuandoAttacca;
    public $descrizioneEvento;
    public $valoreDescrizioneEvento;
    public $azione;
    public $valoreAzione;
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
        $this->imboscata = $jsonData['imboscata'];
        $this->tenacia = $jsonData['tenacia'];
        $this->sopraffazione = $jsonData['sopraffazione'];
        $this->sabotatore = $jsonData['sabotatore'];
        $this->sentinella = $jsonData['sentinella'];
        $this->schermata = $jsonData['schermata'];
        $this->incursione = $jsonData['incursione'];
        $this->valoreIncursione = $jsonData['valoreIncursione'];
        $this->recupero = $jsonData['recupero'];
        $this->valoreRecupero = $jsonData['valoreRecupero'];
        $this->contrabbando = $jsonData['contrabbando'];
        $this->valoreContrabbando = $jsonData['valoreContrabbando'];
        $this->quandoGiocata = $jsonData['quandoGiocata'];
        $this->valoreQuandoGiocata = $jsonData['valoreQuandoGiocata'];
        $this->taglia = $jsonData['taglia'];
        $this->valoreTaglia = $jsonData['valoreTaglia'];
        $this->quandoSconfitta = $jsonData['quandoSconfitta'];
        $this->valoreQuandoSconfitta = $jsonData['valoreQuandoSconfitta'];
        $this->quandoAttacca = $jsonData['quandoAttacca'];
        $this->valoreQuandoAttacca = $jsonData['valoreQuandoAttacca'];
        $this->descrizioneEvento = $jsonData['descrizioneEvento'];
        $this->valoreDescrizioneEvento = $jsonData['valoreDescrizioneEvento'];
        $this->azione = $jsonData['azione'];
        $this->valoreAzione = $jsonData['valoreAzione'];
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
            if ($key === 'tratti') $value = join(" * ", $value);
            if(gettype($value) === "string"){
                $insert = $insert.'"';
            }
            $insert = $insert.$value;
            if(gettype($value) === "string"){
                $insert = $insert.'"';
            }
            $insert = $insert.", ";
        }
        $insert = substr($insert,0,-2);
        $insert = $insert."),\n";
        $insert = substr($insert,0,-2);
        return $insert.";";
    }
}
