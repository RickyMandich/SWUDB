<?php
    class Utente{
        private $nome;
        private $id;
        private $email;
        private $password;

        function __construct(string $nome, int $id, string $email, string $password){
            $this->nome = $nome;
            $this->id = $id;
            $this->email = $email;
            $this->password = $password;
        }

        public function getNome(): string{
            return $this->nome;
        }
        public function getID(): int{
            return $this->id;
        }
        public function getEmail(): string{
            return $this->email;
        }
        public function getPassword(): string{
            return $this->password;
        }
    }