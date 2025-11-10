<?php
session_start();
require_once "config/database.php";

$database = new Database();
$conn = $database->getConnection();

if (isset($_GET['cadet_id'])) {
    $cadet_id = $_GET['cadet_id'];
    
    // Get cadet materials
    $materials = $conn->query("
        SELECT m.* 
        FROM materials m 
        WHERE m.cadet_id = $cadet_id AND m.status = 'checked_out'
        ORDER BY m.name
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($materials)) {
        echo '<div class="table-container">';
        echo '<table>';
        echo '<thead>';
        echo '<tr><th>Material</th><th>Description</th><th>Code</th><th>Barcode</th><th>Actions</th></tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($materials as $material) {
            echo '<tr>';
            echo '<td><strong>' . $material['name'] . '</strong></td>';
            echo '<td>' . ($material['description'] ?: 'No description') . '</td>';
            echo '<td>' . $material['material_code'] . '</td>';
            echo '<td>' . ($material['barcode'] ?? 'N/A') . '</td>';
            echo '<td>';
            echo '<button class="btn btn-warning btn-sm" onclick="sendMaterialOutside(' . $material['material_id'] . ', \'' . $material['telephone'] . '\')">';
            echo '<i class="fas fa-external-link-alt"></i> Send Outside';
            echo '</button>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    } else {
        echo '<p>No materials found for this cadet.</p>';
    }
}
?>