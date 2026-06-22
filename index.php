<?php
// Zorg dat de sessie overal op de website (ook in submappen) gelezen kan worden
session_set_cookie_params(0, '/');
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

// Verbinding met database
$db = new mysqli("localhost", "root", "", "filedrop");

if ($db->connect_error) {
    die("Verbinding mislukt: " . $db->connect_error);
}

// 1. CENTRALE LOG-FUNCTIE TOEGEVOEGD
function logActivity($conn, $username, $action, $details) {
    $stmt = $conn->prepare("INSERT INTO logs (username, action, details) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $action, $details);
    $stmt->execute();
    $stmt->close();
}

// Geheime sleutel voor encryptie (Zelfde als in download.php!)
define('ENCRYPTION_KEY', 'JouwSuperGeheimeSleutel123!#'); 

// Download bestand via ID (Oude fallback - mag blijven of weg, download.php handelt nu de shares af)
if (isset($_GET["id"])) {
    $s = $db->prepare("SELECT filename,mime_type,file_data,recipient FROM uploads WHERE id=?");
    $s->bind_param("s", $_GET["id"]);
    $s->execute();
    if ($f = $s->get_result()->fetch_assoc()) {
        if ($_SESSION["username"] !== $f["recipient"]) {
            exit("You are not allowed to download this file.");
        }
        header("Content-Type: " . $f["mime_type"]);
        header('Content-Disposition: attachment; filename="' . $f["filename"] . '"');
        exit($f["file_data"]);
    }
    exit("File not found");
}

$error = "";

// Upload verwerken
if (!empty($_FILES["filename"]) && $_FILES["filename"]["error"] == 0) {

    $recipient = trim($_POST["recipient"] ?? "");

    // Controleer of gebruiker bestaat
    $check = $db->prepare("SELECT id FROM users WHERE username=?");
    $check->bind_param("s", $recipient);
    $check->execute();

    if (!$check->get_result()->fetch_assoc()) {
        $error = "User does not exist.";
    }

    $ext = strtolower(pathinfo($_FILES["filename"]["name"], PATHINFO_EXTENSION));
    $allowed = ["png", "jpg", "jpeg", "gif"];

    if (!$error) {
        if (!in_array($ext, $allowed)) {
            $error = "Only PNG, JPG, JPEG and GIF allowed.";
        } elseif ($_FILES["filename"]["size"] > 1048576) {
            $error = "Max size is 1 MB.";
        } elseif (!getimagesize($_FILES["filename"]["tmp_name"])) {
            $error = "Invalid image.";
        } else {

            // 1. Genereer een veilige SHA-256 Hash voor de unieke link
            $file_id = hash('sha256', uniqid(rand(), true));
            $name = $_FILES["filename"]["name"];
            $mime = mime_content_type($_FILES["filename"]["tmp_name"]);
            $raw_data = file_get_contents($_FILES["filename"]["tmp_name"]);

            // 2. ENCRYPTIE van de bestandsdata (AES-256)
            $iv_length = openssl_cipher_iv_length('aes-256-cbc');
            $iv = openssl_random_pseudo_bytes($iv_length);
            $encrypted_data = openssl_encrypt($raw_data, 'aes-256-cbc', ENCRYPTION_KEY, 0, $iv);
            
            // Voeg de IV toe aan de payload en encodeer naar Base64 voor veilige database opslag
            $final_payload = base64_encode($iv . $encrypted_data);

            // 3. Opslaan in database (Inclusief sender voor het overzicht)
            $s = $db->prepare(
                "INSERT INTO uploads (id, filename, mime_type, file_data, recipient, sender)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            
            $sender = $_SESSION["username"];
            $s->bind_param("ssssss", $file_id, $name, $mime, $final_payload, $recipient, $sender);
            
            // Zodra de database insert gelukt is, schrijven we het logboek weg
            if ($s->execute()) {
                // --- HIER WORDT DE VERZENDING GELOGD ---
                logActivity($db, $sender, "Upload", $sender . " heeft bestand '" . $name . "' verzonden naar " . $recipient);
                // --- EINDE LOG-LOGICA ---
            }
            $s->close();

            // 4. Maak de link op een schone manier die gegarandeerd naar download.php leidt
            $current_dir = rtrim(dirname($_SERVER["PHP_SELF"]), '/\\');
            $_SESSION["link"] = "https://" . $_SERVER["HTTP_HOST"] . $current_dir . "/download.php?file=" . $file_id;

            header("Location: " . $_SERVER["PHP_SELF"]);
            exit;
        }
    }
}

// Link ophalen en daarna verwijderen uit sessie
$link = $_SESSION["link"] ?? "";
unset($_SESSION["link"]);
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>GelasFileDrop</title>
</head>
<body>

<div class="container">
    <h2>GelasFileDrop</h2>

    <p>Logged in as: <strong><?= htmlspecialchars($_SESSION["username"]) ?></strong></p>

    <p>
        <a href="loginLogic/logout.php">Logout</a>
        <?php if (isset($_SESSION["role"]) && $_SESSION["role"] === "admin"): ?>
            <a href="loginLogic/admin.php" class="admin-btn">Naar Admin Panel</a>
        <?php endif; ?>
    </p>

    <p>Toegestane bestanden: PNG, JPG, JPEG, GIF (Max 1 MB)</p>

    <?php if ($error) echo "<p style='color: red;'>$error</p>"; ?>

    <form method="post" enctype="multipart/form-data">
        <input type="text" name="recipient" placeholder="Send to username" required><br><br>
        <input type="file" name="filename" accept=".png,.jpg,.jpeg,.gif" required><br><br>
        <input type="submit" value="Upload & Versleutel">
    </form>

    <?php if ($link): ?>
        <hr>
        <p style="color: #28a745; font-weight: bold;">Bestand succesvol versleuteld en opgeslagen!</p>
        <p>Stuur de onderstaande link naar de ontvanger:</p>
        <div class="link-box">
            <a href="<?= htmlspecialchars($link) ?>" id="link"><?= htmlspecialchars($link) ?></a>
        </div>
        <br>
        <button onclick="navigator.clipboard.writeText(document.getElementById('link').href)">Kopieer Link</button>
    <?php endif; ?>
</div>

</body>
</html>
<?php $db->close(); ?>