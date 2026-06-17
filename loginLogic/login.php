<?php
session_start();

try {
    $db = new PDO(
        "mysql:host=localhost;dbname=filedrop;charset=utf8mb4",
        "root",
        ""
    );

    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("Database connection failed.");
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    $s = $db->prepare(
        "SELECT id, password
         FROM users
         WHERE username = ?"
    );

    $s->execute([$username]);

    $user = $s->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user["password"])) {

        session_regenerate_id(true);

        $_SESSION["user_id"] = $user["id"];
        $_SESSION["username"] = $username;

        header("Location: ../index.php");
        exit;
    }

    $error = "Wrong username or password";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
</head>
<body>

<h2>Login</h2>

<?php if ($error): ?>
<p><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="post">

    <input
        type="text"
        name="username"
        placeholder="Username"
        required
    >

    <input
        type="password"
        name="password"
        placeholder="Password"
        required
    >

    <input type="submit" value="Login">

</form>

</body>
</html>