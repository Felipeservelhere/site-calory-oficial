<?php
// Conexão exclusiva para Login / Sessões / Controle de Acesso
class DatabaseLogin {
    private $host = '177.107.115.204';
    private $db_name = 'empresaweb';
    private $username = 'EMPRESAWEB';
    private $password = '@CALORY123@';
    private $connlogin;

    public function getConnection() {
        $this->connlogin = null;

        try {
            $this->connlogin = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8",
                $this->username,
                $this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
                )
            );
        } catch(PDOException $exception) {
            echo "Erro de conexão (Login): " . $exception->getMessage();
        }

        return $this->connlogin;
    }
}
?>
