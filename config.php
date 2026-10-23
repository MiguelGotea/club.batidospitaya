<?php
// config.php - Configuración de base de datos
date_default_timezone_set('America/Managua');

class Database {
    private $host = "localhost";
    private $db_name = "u839374897_erp";
    private $username = "u839374897_erp";
    private $password = "ERpPitHay2025$";
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";

            $options = array(
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            );

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            $this->conn->exec("SET time_zone = '-06:00'");

        } catch(PDOException $exception) {
            error_log("Error de conexión PDO: " . $exception->getMessage());
            die("Error de conexión. Por favor intente más tarde.");
        }

        return $this->conn;
    }
}
?>
