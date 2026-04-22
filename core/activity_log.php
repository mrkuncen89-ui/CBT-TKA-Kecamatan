<?php
// ============================================================
// core/activity_log.php
// Log aktivitas user — TKA Kecamatan
// ============================================================

function logActivity($conn, $user_id, $username, $action, $detail = '') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $stmt = $conn->prepare("INSERT INTO activity_log 
        (user_id, username, action, detail, ip_address, user_agent, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())");
    
    if ($stmt) {
        $stmt->bind_param("isssss", 
            $user_id, $username, $action, $detail, $ip, $user_agent);
        $stmt->execute();
        $stmt->close();
    }
}

function getActivityLog($conn, $limit = 100) {
    $result = $conn->query("SELECT * FROM activity_log 
        ORDER BY created_at DESC LIMIT $limit");
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}
