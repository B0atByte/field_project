<?php
include '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    $stmt = $conn->prepare("INSERT INTO users (name, username, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $username, $password, $role);
    $stmt->execute();

    echo "สร้างผู้ใช้สำเร็จ <a href='../index.php'>กลับไปล็อกอิน</a>";
}
?>

<form method="post">
  <h2>สร้างผู้ใช้งาน</h2>
  <input type="text" name="name" placeholder="ชื่อ" required><br>
  <input type="text" name="username" placeholder="Username" required><br>
  <input type="password" name="password" placeholder="Password" required><br>
  <select name="role">
    <option value="admin">Admin</option>
    <option value="field">Field</option>
  </select><br>
  <button type="submit">สร้างผู้ใช้</button>
</form>
