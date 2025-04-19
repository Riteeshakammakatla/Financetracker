<?php
require_once 'config.php';
requireLogin();

$conn = connectDB();
$user_id = $_SESSION['user_id'];

// Get investment distribution by type
$sql = "SELECT t.type_name, 
        SUM(i.amount) as total_invested, 
        SUM(i.current_value - i.amount) as total_returns
        FROM investments i
        JOIN investment_types t ON i.type_id = t.id
        WHERE i.user_id = ?
        GROUP BY t.type_name
        ORDER BY t.type_name";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$labels = [];
$invested = [];
$returns = [];

while ($row = $result->fetch_assoc()) {
    $labels[] = $row['type_name'];
    $invested[] = $row['total_invested'];
    $returns[] = $row['total_returns'];
}

$response = [
    'labels' => $labels,
    'invested' => $invested,
    'returns' => $returns
];

header('Content-Type: application/json');
echo json_encode($response);
?>