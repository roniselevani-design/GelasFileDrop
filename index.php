<?php 
session_start(); 

// 1. Database verbinding
$conn = new mysqli("localhost", "root", "", "filedrop"); 

if ($conn->connect_error) { 
    die("Connectie mislukt: " . $conn->connect_error); 
} 

// 2. Forceer HTTPS redirect
if (empty($_SERVER["HTTPS"]) || $_SERVER["HTTPS"] === "off") { 
    $redirect = "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]; 
    header("Location: $redirect", true, 301); 
    exit; 
} 

// 3. Werkelijke bestandsoverdracht (wordt getriggerd door de downloadknop)
if (isset($_GET["action"]) && $_GET["action"] === "download" && isset($_GET["id"])) {
    $stmt = $conn->prepare( 
        "SELECT filename, mime_type, file_data 
         FROM uploads 
         WHERE id = ?" 
    ); 
    $stmt->bind_param("s", $_GET["id"]); 
    $stmt->execute(); 
    $result = $stmt->get_result(); 

    if ($file = $result->fetch_assoc()) { 
        header("Content-Type: " . $file["mime_type"]); 
        header("Content-Disposition: attachment; filename=\"" . $file["filename"] . "\""); 
        echo $file["file_data"]; 
        exit; 
    } 
    die("Bestand niet gevonden"); 
}

// 4. Gegevens ophalen voor de speciale landingspagina (als ?id=... in de URL staat)
$previewFile = null;
if (isset($_GET["id"]) && !isset($_GET["action"])) {
    $stmt = $conn->prepare("SELECT filename FROM uploads WHERE id = ?");
    $stmt->bind_param("s", $_GET["id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    $previewFile = $result->fetch_assoc();
}

// 5. Bestand uploaden verwerken
if (isset($_FILES["filename"]) && $_FILES["filename"]["error"] === UPLOAD_ERR_OK) { 
    $uploadOk = 1; 
    $filename = $_FILES["filename"]["name"]; 
    $imageFileType = strtolower(pathinfo($filename, PATHINFO_EXTENSION)); 

    // Controleer bestandstypen
    if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") { 
        echo "Sorry, alleen JPG, JPEG, PNG en GIF bestanden zijn toegestaan."; 
        $uploadOk = 0; 
    } 

    // Controleer bestandsgrootte (max 500KB)
    if ($_FILES["filename"]["size"] > 500000) { 
        echo "Sorry, je bestand is te groot."; 
        $uploadOk = 0; 
    } 

    if ($uploadOk == 1) { 
        $id = md5(uniqid()); 
        $mime = $_FILES["filename"]["type"]; 
        $data = file_get_contents($_FILES["filename"]["tmp_name"]); 

        $stmt = $conn->prepare( 
            "INSERT INTO uploads (id, filename, mime_type, file_data) 
             VALUES (?, ?, ?, ?)" 
        ); 
        
        $stmt->bind_param("ssss", $id, $filename, $mime, $data); 
        $stmt->execute(); 

        // Sla de volledige speciale URL op in de sessie
        $_SESSION["link"] = "https://" . $_SERVER["HTTP_HOST"] . "/indexPHP.php?id=" . $id; 
        header("Location: https://" . $_SERVER["HTTP_HOST"] . "/indexPHP.php"); 
        exit; 
    } 
} 

$uploadedLink = $_SESSION["link"] ?? ""; 
unset($_SESSION["link"]); 
?> 

<!DOCTYPE html> 
<html> 
<head> 
    <meta charset="UTF-8">
    <title>GelasFileDrop</title> 
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .box { border: 1px solid #ccc; padding: 20px; border-radius: 5px; background: #f9f9f9; margin-top: 20px; }
        .download-btn { display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 3px; font-weight: bold; }
    </style>
</head> 
<body> 

<h2>GelasFileDrop</h2> 

<?php if ($previewFile): ?>
    <div class="box">
        <h3>Bestand klaar om te downloaden!</h3>
        <p>Er staat een bestand voor je klaar: <strong><?= htmlspecialchars($previewFile['filename']) ?></strong></p>
        <a href="indexPHP.php?id=<?= htmlspecialchars($_GET['id']) ?>&action=download" class="download-btn">
            Download bestand
        </a>
        <br><br>
        <p><a href="indexPHP.php">Klik hier om zelf een bestand te uploaden</a></p>
    </div>

<?php else: ?>
    <form method="post" enctype="multipart/form-data"> 
        <input type="file" name="filename" required> 
        <input type="submit" value="Uploaden"> 
    </form> 

    <?php if ($uploadedLink): ?> 
        <div class="box" style="border-color: #28a745;">
            <p style="color: green; font-weight: bold;">Upload succesvol!</p> 
            <p>Deel de onderstaande link met anderen zodat zij het bestand kunnen downloaden:</p>
            <input type="text" value="<?= htmlspecialchars($uploadedLink) ?>" readonly style="width: 100%; padding: 8px;">
            <p><a href="<?= htmlspecialchars($uploadedLink) ?>" target="_blank">Bekijk de downloadpagina</a></p>
        </div>
    <?php endif; ?> 

    <h3>Ondersteunde bestandstypen:</h3>
    <ul>
        <li>Afbeeldingen (.jpg, .jpeg, .png, .gif)</li>
    </ul>
<?php endif; ?> 

</body> 
</html>
