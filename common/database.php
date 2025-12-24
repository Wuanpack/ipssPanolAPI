<?php
class Conexion{
    private $connection;
    private $host;
    private $username;
    private $password;
    private $db;
    private $port;

    public function __construct()
    {
        $this->connection = null;
        $this->host = '127.0.0.1';
        $this->port = 3306;
        $this->db = 'panol_dev';
        $this->username = 'ipssTests';
        $this->password = 'ipss_12345';
    }

    public function getConnection(){
        try{
            $this->connection = mysqli_connect($this->host, $this->username, $this->password, $this->db, $this->port);
            mysqli_set_charset($this->connection, 'utf8');
            if(!$this->connection){
                throw new Exception(":( Error en la conexion: " . mysqli_connect_error());
            }
            return $this->connection;
        }catch (Exception $ex) {
            http_response_code(500);
            die("Error en la conexión: " . $ex->getMessage());
        }
    }

    public function closeConnection(){
        if($this->connection){
            mysqli_close($this->connection);
        }
    }
}

?>
