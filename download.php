<?php
// Zorg dat de sessie overal op de website (ook in submappen) gelezen kan worden
session_set_cookie_params(0, '/');
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: loginLogic/login.php");
    exit;
}

$db = new mysqli("localhost", "root", "", "filedrop");

if ($db->connect_error) {
    die("Verbinding mislukt: " . $db->connect_error);
}

// 1. CENTRALE LOG-FUNCTIE
function logActivity($conn, $username, $action, $details) {
    $stmt = $conn->prepare("INSERT INTO logs (username, action, details) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $action, $details);
    $stmt->execute();
    $stmt->close();
}

// EXACT dezelfde sleutel als in index.php!
define('ENCRYPTION_KEY', 'JouwSuperGeheimeSleutel123!#'); 

$file_info = null;
$decrypted_image_base64 = "";

if (isset($_GET["file"])) {
    $file_hash = $_GET["file"];

    // Haal het bestand op via de id/hash
    $s = $db->prepare("SELECT filename, mime_type, file_data, recipient, sender FROM uploads WHERE id=?");
    $s->bind_param("s", $file_hash);
    $s->execute();
    $file_info = $s->get_result()->fetch_assoc();

    if ($file_info) {
        // Controleer of de ingelogde persoon wel de ontvanger is
        if ($_SESSION["username"] !== $file_info["recipient"]) {
            exit("<h3>Toegang geweigerd. Dit bestand is niet voor jou bestemd.</h3>");
        }

        // DECRYPTIE van de bestandsdata
        $raw_payload = base64_decode($file_info["file_data"]);
        $iv_length = openssl_cipher_iv_length('aes-256-cbc');
        
        // Splits de IV en de gecodeerde data weer op
        $iv = substr($raw_payload, 0, $iv_length);
        $encrypted_data = substr($raw_payload, $iv_length);
        
        $decrypted_data = openssl_decrypt($encrypted_data, 'aes-256-cbc', ENCRYPTION_KEY, 0, $iv);

        if ($decrypted_data === false) {
            exit("<h3>Fout: Decryptie mislukt. Sleutel of data is corrupt.</h3>");
        }

        // --- HIER WORDT DE GEBRUIKERSNAAM ACHTERHAALD EN GELOGD ---
        $username = $_SESSION["username"] ?? '';

        // Als de username leeg is in de sessie, haal hem dan direct op via het user_id uit de database
        if (empty($username) && isset($_SESSION["user_id"])) {
            $u_stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
            $u_stmt->bind_param("i", $_SESSION["user_id"]);
            $u_stmt->execute();
            $u_result = $u_stmt->get_result()->fetch_assoc();
            if ($u_result) {
                $username = $u_result['username'];
                $_SESSION["username"] = $username; 
            }
            $u_stmt->close();
        }
// HIER AANGEPAST: Logt nu zowel de afzender ($afzender) als de downloader ($username) in de details
        $afzender = $file_info['sender'] ?? 'Onbekend';
        logActivity($db, $username, "Download", $username . " heeft bestand '" . $file_info["filename"] . "' gedownload (Verzonden door: " . $afzender . ")");

        // HIER AANGEPAST: De afzender staat nu vooraan in de details-zin
        $afzender = $file_info['sender'] ?? 'Onbekend';
        logActivity($db, $username, "Download", $afzender . " heeft bestand '" . $file_info["filename"] . "' gedownload");
        // --- EINDE LOG-LOGICA ---

        // Zet om naar een schone Base64 string voor de HTML en JavaScript auto-download
        $decrypted_image_base64 = 'data:' . $file_info['mime_type'] . ';base64,' . base64_encode($decrypted_data);
    } else {
        exit("<h3>Bestand niet gevonden of de link is ongeldig.</h3>");
    }
} else {
    exit("<h3>Geen bestand gespecificeerd.</h3>");
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Filedrop - Ontvangen Bestand</title>
</head>
<body>

<div class="box">
    <h2>Je hebt een bestand ontvangen!</h2>
    
    <div class="meta">
        <strong>Afzender:</strong> <?= htmlspecialchars($file_info['sender'] ?? 'Onbekend') ?><br>
        <strong>Bestandsnaam:</strong> <?= htmlspecialchars($file_info['filename']) ?>
    </div>

    <p>Hier is je bestand:</p>
    
    <img src="<?= $decrypted_image_base64 ?>" alt="Ontvangen afbeelding">

    <br>
    <a href="<?= $decrypted_image_base64 ?>" download="<?= htmlspecialchars($file_info['filename']) ?>" class="btn">Klik hier als de download niet start</a>
</div>

<script>
    window.addEventListener('DOMContentLoaded', () => {
        const autoLink = document.createElement('a');
        autoLink.href = "<?= $decrypted_image_base64 ?>";
        autoLink.download = "<?= htmlspecialchars($file_info['filename']) ?>";
        document.body.appendChild(autoLink);
        autoLink.click();
        document.body.removeChild(autoLink);
    });
</script>

</body>
</html>
<?php $db->close(); ?>