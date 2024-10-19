<?php
    class Utente{
        private string $nome;
        private int $id;
        private string $email;
        private string $password;

        function __construct(string $nome, int $id, string $email, string $password){
            $this->nome = $nome;
            $this->id = $id;
            $this->email = $email;
            $this->password = $password;
        }

        public function getNome(): string{
            return $this->nome;
        }
        public function getId(): int{
            return $this->id;
        }
        public function getEmail(): string{
            return $this->email;
        }
        public function getPassword(): string{
            return $this->password;
        }
    }