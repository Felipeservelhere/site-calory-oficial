<?php
// Configuração do banco de dados
class Database {
    private $host = 'localhost';
    private $db_name = 'frutnorte';
    private $username = 'root';
    private $password = '@@rOOt@cAlOry@1967@@';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
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
            echo "Erro de conexão: " . $exception->getMessage();
        }
        
        return $this->conn;
    }
}
?>