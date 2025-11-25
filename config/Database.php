<?php
  namespace Config;

  class Database{

    private static $instance = null;
    private $connection;

    private $host; // 'mysql' est le nom du service dans docker-compose.yml
    private $db;
    private $user;
    private $pass;

    private function __construct(){

        // Valeurs par défaut sensées
        $this->host = getenv('DB_HOST') ?: 'mysql'; //ce n'est pas localhost=> dans le docker-compose.yml c'est mysql!!
        $this->db   = getenv('DB_NAME') ?: 'coffreFort';
        $this->user = getenv('DB_USER') ?: 'coffreFort';
        $this->pass = getenv('DB_PASSWORD') ?: '';

      try {
        //connexion PDO à la base applicative
        $this->connection = new \PDO("mysql:host={$this->host};dbname={$this->db};charset=utf8mb4",
                $this->user,
                $this->pass
            );

        // pour gérer les erreurs, on choisit de lancer des exceptions
        $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // les résultats sont renvoyés en tableaux associatifs (clés = noms de colonnes) => pas besoin d'indices numériques
        $this->connection->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->connection->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
      } catch(\PDOException $e){
         // Ne pas exposer les creds en prod
          die("Connection failed: " . $e->getMessage());
      }
    }

    //pour garantir qu'il y a 1 seul objet de cette classe dans toute l'application
    // static => possible appeler sans créer d'objet
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self(); //=> new NomDeLaClasse()
        }
        return self::$instance;
    }
    
    public function getConnection() {  //son utilisation: $pdo = Database::getInstance()->getConnection();
        return $this->connection;
    }

  }

?>