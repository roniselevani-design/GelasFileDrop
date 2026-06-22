<?php
// Database configuratie
$host = "localhost";
$db_user = "root"; 
$db_pass = "";     
$db_name = "filedrop";

// Verbinding maken met de database
$conn = new mysqli($host, $db_user, $db_pass, $db_name);

// Controleer de verbinding
if ($conn->connect_error) {
    die("Verbinding mislukt: " . $conn->connect_error);
}

$message = "";

// Controleren of het formulier is verzonden
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Validatie (alleen gebruikersnaam en wachtwoord zijn nu verplicht)
    if (!empty($username) && !empty($password)) {
        
        // Wachtwoord veilig hashen
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Prepared statement aangepast (email is weggelaten)
        $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->bind_param("ss", $username, $hashed_password);

        if ($stmt->execute()) {
            $message = "<p style='color: green;'>Registratie succesvol! Je kunt nu inloggen.</p>";
        } else {
            if ($conn->errno == 1062) { 
                $message = "<p style='color: red;'>Gebruikersnaam bestaat al.</p>";
            } else {
                $message = "<p style='color: red;'>Er ging iets mis. Probeer het opnieuw.</p>";
            }
        }
        $stmt->close();
    } else {
        $message = "<p style='color: red;'>Vul alle velden in.</p>";
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filedrop - Registreren</title>
</head>
<body>

<div class="register-container">
    <h2>Filedrop Registratie</h2>
    
    <?php echo $message; ?>

    <form action="register.php" method="POST">
        <label for="username">Gebruikersnaam</label>
        <input type="text" id="username" name="username" required>

        <label for="password">Wachtwoord</label>
        <input type="password" id="password" name="password" required>

        <button type="submit">Registreren</button>
    </form>

    <a href="login.php" class="login-link">Al een account? Log in</a>
</div>

</body>
</html>