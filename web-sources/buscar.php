<?php
// ==========================================================
// VULNERABLE A PROPÓSITO: SQL Injection clásica.
// El input del usuario se concatena directo en la query, sin
// prepared statements ni sanitización.
// ==========================================================

$conn = new mysqli("localhost", "juan", "chocolate", "kalinasa_db");

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

$nombre = isset($_GET['nombre']) ? $_GET['nombre'] : '';

// Vulnerable: concatenación directa del input en la query
$query = "SELECT user, email FROM users WHERE user = '$nombre'";

echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'>
<title>Resultados</title>
<style>body{font-family:Arial;max-width:700px;margin:40px auto;}
table{border-collapse:collapse;width:100%;}
td,th{border:1px solid #ccc;padding:8px;}
.error{color:#b00;font-family:monospace;background:#fee;padding:10px;}</style></head><body>";

echo "<h2>Resultados de busqueda</h2>";
echo "<p><a href='index.html'>&larr; Volver</a></p>";

$result = $conn->query($query);

if ($result === false) {
    // Vulnerable: expone el error SQL completo (facilita error-based SQLi)
    echo "<div class='error'>Error en la consulta: " . $conn->error . "</div>";
} elseif ($result->num_rows > 0) {
    echo "<table><tr><th>Usuario</th><th>Email</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>" . $row['user'] . "</td><td>" . $row['email'] . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p>No se encontraron resultados.</p>";
}

$conn->close();
echo "</body></html>";
?>
