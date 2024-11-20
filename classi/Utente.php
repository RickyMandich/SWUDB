<?php
    class Utente{
        private $nome;
        private $id;
        private $email;
        private $password;
        private $admin;

        function __construct(string $nome, int $id, string $email, string $password, bool $admin){
            $this->nome = $nome;
            $this->id = $id;
            $this->email = $email;
            $this->password = $password;
            $this->admin = $admin
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
        public function isAdmin(): bool{
            return $this->admin;
        }
    }