<?php
session_start();

// 1. DATABASE VERBINDING
$host = "localhost";
$db_user = "root"; 
$db_pass = "";     
$db_name = "filedrop";

$conn = new mysqli($host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Verbinding mislukt: " . $conn->connect_error);
}

// 2. BEVEILIGING: Alleen voor admins
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}

// 3. CENTRALE LOG-FUNCTIE
// Tip: Zet deze functie samen met de databaseverbinding in een apart bestand (bijv. config.php)
// zodat je deze ook in login.php en download.php kunt aanroepen!
function logActivity($conn, $username, $action, $details) {
    $stmt = $conn->prepare("INSERT INTO logs (username, action, details) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $action, $details);
    $stmt->execute();
    $stmt->close();
}

// 4. VERWERK ACTIE: Rol aanpassen van een gebruiker
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action_type']) && $_POST['action_type'] == 'change_role') {
    $target_user_id = intval($_POST['user_id']);
    $new_role = $_POST['new_role'];

    if ($target_user_id === intval($_SESSION["user_id"])) {
        $msg = "<p style='color: red; font-weight: bold;'>Je kunt je eigen admin-rol niet aanpassen!</p>";
    } else if ($new_role === 'admin' || $new_role === 'user') {
        
        $name_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        $name_stmt->bind_param("i", $target_user_id);
        $name_stmt->execute();
        $target_username = $name_stmt->get_result()->fetch_assoc()['username'] ?? 'Onbekend';
        $name_stmt->close();

        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $new_role, $target_user_id);
        
        if ($stmt->execute()) {
            $msg = "<p style='color: green; font-weight: bold;'>Rol van " . htmlspecialchars($target_username) . " succesvol gewijzigd naar " . $new_role . ".</p>";
            logActivity($conn, $_SESSION['username'], "Role Change", "Rol van " . $target_username . " gewijzigd naar " . $new_role);
        } else {
            $msg = "<p style='color: red;'>Er ging iets mis bij het updaten.</p>";
        }
        $stmt->close();
    }
}

// 5. DATA OPHALEN UIT DE DRIE TABELLEN
// A. Alle Gebruikers / Accounts
$users_result = $conn->query("SELECT id, username, role, created_at FROM users ORDER BY username ASC");

// B. Alle Bestanden (Uploads)
$columns_check = $conn->query("SHOW COLUMNS FROM uploads");
$columns = [];
if ($columns_check) {
    while ($col = $columns_check->fetch_assoc()) {
        $columns[] = $col['Field'];
    }
}

$file_col = in_array('filename', $columns) ? 'filename' : (in_array('bestandsnaam', $columns) ? 'bestandsnaam' : '');
$user_col = in_array('username', $columns) ? 'username' : (in_array('user_id', $columns) ? 'user_id' : '');
$date_col = in_array('uploaded_at', $columns) ? 'uploaded_at' : (in_array('datum', $columns) ? 'datum' : '');

$select_fields = [];
if ($file_col) $select_fields[] = $file_col;
if ($user_col) $select_fields[] = $user_col;
if ($date_col) $select_fields[] = $date_col;

if (!empty($select_fields)) {
    $query_str = "SELECT " . implode(", ", $select_fields) . " FROM uploads";
    if ($date_col) $query_str .= " ORDER BY $date_col DESC";
    $uploads_result = $conn->query($query_str);
} else {
    $uploads_result = false;
}

// C. Alle Acties (Logs) - Haalt nu ook 'Download' en 'Failed Login' op!
$logs_result = $conn->query("SELECT username, action, details, created_at FROM logs ORDER BY created_at DESC LIMIT 100");
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filedrop - Complete Admin Panel</title>
</head>
<body>

<div class="container">
    <h2>Admin Dashboard - Overzicht</h2>
    <div class="meta-info">
        Ingelogd als: <span class="badge badge-admin"><?= htmlspecialchars($_SESSION["username"] ?? 'Admin') ?></span> | 
        <a href="../index.php">Naar Applicatie (Home)</a>
    </div>

    <?php if (isset($msg)) echo $msg; ?>

    <div class="section">
        <h3>Geregistreerde Accounts & Rechten</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Gebruikersnaam</th>
                    <th>Registratiedatum</th>
                    <th>Huidige Rol</th>
                    <th>Actie (Rol Wijzigen)</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($users_result && $users_result->num_rows > 0): ?>
                    <?php while($user = $users_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                            <td><?= htmlspecialchars($user['created_at'] ?? '-') ?></td>
                            <td>
                                <span class="badge <?= $user['role'] === 'admin' ? 'badge-admin' : 'badge-user' ?>">
                                    <?= htmlspecialchars($user['role']) ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" style="display:inline-flex; gap: 5px;">
                                    <input type="hidden" name="action_type" value="change_role">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <select name="new_role" class="role-select">
                                        <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                        <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                    <button type="submit" class="save-btn">Opslaan</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align: center;">Geen gebruikers gevonden.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3>Geüploade Bestanden</h3>
        <table>
            <thead>
                <tr>
                    <th>Bestandsnaam</th>
                    <th>Geüpload Door</th>
                    <th>Tijdstip van Upload</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($uploads_result && $uploads_result->num_rows > 0): ?>
                    <?php while($file = $uploads_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($file['filename'] ?? $file['bestandsnaam'] ?? 'Onbekend bestand') ?></td>
                            <td><?= htmlspecialchars($file['username'] ?? $file['user_id'] ?? 'Onbekend') ?></td>
                            <td><?= htmlspecialchars($file['uploaded_at'] ?? $file['datum'] ?? '-') ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="text-align: center; color: #999;">Er zijn nog geen bestanden geüpload in het systeem.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3>Systeem Acties & Logs (Laatste 100)</h3>
        <table>
            <thead>
                <tr>
                    <th>Tijdstip</th>
                    <th>Gebruiker</th>
                    <th>Actie</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs_result && $logs_result->num_rows > 0): ?>
                    <?php while($log = $logs_result->fetch_assoc()): ?>
                        <?php 
                            $badgeClass = "";
                            if ($log['action'] == 'Upload') $badgeClass = 'badge-upload';
                            elseif ($log['action'] == 'Download') $badgeClass = 'badge-download';
                            elseif ($log['action'] == 'Failed Login') $badgeClass = 'badge-failed';
                            elseif ($log['action'] == 'Role Change') $badgeClass = 'badge-admin';
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($log['created_at']) ?></td>
                            <td><?= htmlspecialchars($log['username']) ?></td>
                            <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($log['action']) ?></span></td>
                            <td><?= htmlspecialchars($log['details']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: #999;">Er zijn nog geen acties gelogd.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
<?php $conn->close(); ?>