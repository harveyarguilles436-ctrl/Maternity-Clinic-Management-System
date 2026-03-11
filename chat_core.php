<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) die("Access Denied");

$my_id = $_SESSION['user_id'];
$action = isset($_POST['action']) ? $_POST['action'] : '';

// 1. FETCH CONTACT LIST
if ($action == 'get_contacts') {
    // If Client: See Admin/Staff. If Staff: See Everyone except self.
    if ($_SESSION['role'] == 'Client') {
        $sql = "SELECT user_id, name, role FROM users WHERE role != 'Client'";
    } else {
        $sql = "SELECT user_id, name, role FROM users WHERE user_id != '$my_id'";
    }
    
    $result = $conn->query($sql);
    $contacts = [];
    while($row = $result->fetch_assoc()) {
        $uid = $row['user_id'];
        $unread = $conn->query("SELECT COUNT(*) as c FROM messages WHERE sender_id='$uid' AND receiver_id='$my_id' AND is_read=0")->fetch_assoc()['c'];
        $row['unread'] = $unread;
        $contacts[] = $row;
    }
    echo json_encode($contacts);
}

// 2. FETCH MESSAGES
if ($action == 'get_chat') {
    $other_id = $_POST['receiver_id'];
    $conn->query("UPDATE messages SET is_read=1 WHERE sender_id='$other_id' AND receiver_id='$my_id'");

    $sql = "SELECT * FROM messages 
            WHERE (sender_id='$my_id' AND receiver_id='$other_id') 
            OR (sender_id='$other_id' AND receiver_id='$my_id') 
            ORDER BY created_at ASC";
            
    $result = $conn->query($sql);
    $msgs = [];
    while($row = $result->fetch_assoc()) {
        $msgs[] = [
            'type' => ($row['sender_id'] == $my_id) ? 'me' : 'them',
            'msg' => $row['message'],
            'time' => date('h:i A', strtotime($row['created_at']))
        ];
    }
    echo json_encode($msgs);
}

// 3. SEND MESSAGE
if ($action == 'send_msg') {
    $receiver = $_POST['receiver_id'];
    $msg = htmlspecialchars($_POST['message']);
    if(!empty($msg)) {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $my_id, $receiver, $msg);
        $stmt->execute();
    }
}
?>