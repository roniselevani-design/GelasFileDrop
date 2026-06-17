<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: loginLogic/login.php");
    exit;
}

// Forceer HTTPS
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == 'off') {
    header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}

// Database (PDO)
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

// Download bestand via ID
if (isset($_GET["id"])) {

    $s = $db->prepare(
        "SELECT filename, mime_type, file_data
         FROM uploads
         WHERE id = ?"
    );

    $s->execute([$_GET["id"]]);

    if ($f = $s->fetch(PDO::FETCH_ASSOC)) {

        header("Content-Type: " . $f["mime_type"]);
        header(
            'Content-Disposition: attachment; filename="' .
            $f["filename"] .
            '"'
        );

        exit($f["file_data"]);
    }

    exit("File not found");
}

$error = "";

// Upload verwerken
if (!empty($_FILES["filename"]) && $_FILES["filename"]["error"] == 0) {

    $ext = strtolower(
        pathinfo($_FILES["filename"]["name"], PATHINFO_EXTENSION)
    );

    $allowed = ["png", "jpg", "jpeg", "gif"];

    // Controleer extensie
    if (!in_array($ext, $allowed))
        $error = "Only PNG, JPG, JPEG and GIF allowed.";

    // Controleer grootte
    elseif ($_FILES["filename"]["size"] > 1048576)
        $error = "Max size is 1 MB.";

    // Controleer image
    elseif (!getimagesize($_FILES["filename"]["tmp_name"]))
        $error = "Invalid image.";

    else {

        $id = bin2hex(random_bytes(16));
        $name = $_FILES["filename"]["name"];
        $mime = mime_content_type($_FILES["filename"]["tmp_name"]);
        $data = file_get_contents($_FILES["filename"]["tmp_name"]);

        $s = $db->prepare(
            "INSERT INTO uploads
            (id, filename, mime_type, file_data)
            VALUES (?, ?, ?, ?)"
        );

        $s->execute([
            $id,
            $name,
            $mime,
            $data
        ]);

        $_SESSION["link"] =
            "https://" .
            $_SERVER["HTTP_HOST"] .
            strtok($_SERVER["REQUEST_URI"], '?') .
            "?id=" . $id;

        header("Location: " . $_SERVER["PHP_SELF"]);
        exit;
    }
}

// Link ophalen
$link = $_SESSION["link"] ?? "";
unset($_SESSION["link"]);
?>

<!DOCTYPE html>
<html>
<head>
    <title>GelasFileDrop</title>
</head>
<body>

<h2>GelasFileDrop</h2>

<p>
    Logged in as:
    <?= htmlspecialchars($_SESSION["username"] ?? "Unknown") ?>
</p>

<p>
    <a href="loginLogic/logout.php">Logout</a>
</p>

<ul>
    <li>PNG</li>
    <li>JPG</li>
    <li>JPEG</li>
    <li>GIF</li>
</ul>

<p>Max size: 1 MB</p>

<?php if ($error): ?>
<p><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">

    <input
        type="file"
        name="filename"
        accept=".png,.jpg,.jpeg,.gif"
        required
    >

    <input type="submit" value="Upload">

</form>

<?php if ($link): ?>

<p>Upload successful!</p>

<a href="<?= htmlspecialchars($link) ?>" id="link">
    <?= htmlspecialchars($link) ?>
</a>

<button onclick="navigator.clipboard.writeText(document.getElementById('link').href)">
    Copy
</button>

<?php endif; ?>

</body>
</html>