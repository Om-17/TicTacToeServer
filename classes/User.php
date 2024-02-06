<?php 

require_once __DIR__ . '/../classes/DBconnection.php';

class User extends DBconnection
{
    public function __construct()
    {
      parent::__construct();
    
    }
    public $name;
    public $username;
    public $email;

    public $password;
    public function create(): array
    {
      $context = array();

        if (!isset($this->username) || empty($this->username)) {
            $context["error"] = "Username is required.";
            return $context;
      
          }
        if (!isset($this->email) || empty($this->email)) {
            $context["error"] = "Email is required.";
            return $context;
      
          }
          if (!isset($this->password) || empty($this->password)) {
            $context["error"] = "Password is required.";
            return $context;
      
          }
           // Username already exists

    $usersql = "SELECT * FROM users WHERE username = :username";
    $stmt = $this->conn->prepare($usersql);
    $stmt->bindParam(':username', $this->username);
    $stmt->execute();
    $usernameresult = $stmt->fetch(PDO::FETCH_ASSOC);

    $usersql = "SELECT * FROM users WHERE email = :email";
    $stmt = $this->conn->prepare($usersql);
    $stmt->bindParam(':email', $this->email);
    $stmt->execute();
    $emailresult = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usernameresult) {
      // Username already exists
      $context["error"] = "Username already exists";
      return $context;
    }
    if ($emailresult) {
      // Username already exists
      $context["error"] = "Email already exists";
      return $context;
    }

    $sql = "INSERT INTO users ( username, password,name,email)
            VALUES ( :username, :password, :name, :email )";

    // prepare statement
    $stmt = $this->conn->prepare($sql);

   
    $stmt->bindValue(':username', $this->username);
    $stmt->bindValue(':name', $this->name);
    $stmt->bindValue(':email', $this->email);
    $stmt->bindValue(':password', password_hash($this->password, PASSWORD_DEFAULT));
   
   
    $stmt->execute();
    // Fetch the created user data
    $createdUserData = $this->getUserById(); // Implement a function to fetch user data by ID

    $this->conn = null;

    $context['message'] = "User created successfully";
    $context['user'] = $createdUserData; 
    return $context;
    }

    public  function get($field, $value)
    {
      $sql = "SELECT * FROM users WHERE $field = :value";
  
      $stmt = $this->conn->prepare($sql);
  
      $stmt->bindParam(':value', $value);
  
      $stmt->execute();
      $result = $stmt->fetch(PDO::FETCH_BOTH);
     
      return $result;
  
    }
    private function getUserById()
{
    $userId = $this->conn->lastInsertId();
    $sql = "SELECT * FROM users WHERE id = :id";
    $stmt = $this->conn->prepare($sql);
    $stmt->bindParam(':id', $userId);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_BOTH);

    // If user is not found, return null or handle accordingly
    return $user;
}
    public function getAll()
    {
  
      $sql = "SELECT * FROM users";
      $stmt = $this->conn->prepare($sql);
      $stmt->execute();
      $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
      
      return $result;
    }
    public function update($field, $fieldvalue, $params)
  {

    $sql = "UPDATE users SET ";

    $numParams = count($params);
    $i = 0;
    foreach ($params as $key => $value) {
      $sql .= $key . " = :" . $key;
      if ($i < $numParams - 1) {
        $sql .= ", ";
      }
      $i++;
    }
    $sql .= " WHERE $field = :fieldvalue";
    // echo $sql;
    $stmt = $this->conn->prepare($sql);
    $stmt->bindValue(':fieldvalue', $fieldvalue);

    foreach ($params as $key => $value) {
      $stmt->bindValue(':' . $key, $value);
    }
    $stmt->execute();

    return $stmt;
  }

  public function __destruct()
  {
    $this->conn = null;
  }
}




?>