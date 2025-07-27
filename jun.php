<?php
$dsn = 'mysql:host=localhost;dbname=sample_db;charset=utf8mb4';
$user = 'root';
$pass = 'root'; // MAMPなら通常 root

try {
  $pdo = new PDO($dsn, $user, $pass);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
  die('DB接続失敗: '. e->getMessage());
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_class'])) {
    $name = $_POST['class_name'] ?? '';
    $password = $_POST['class_password'] ?? '';
    if ($name && $password) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO classes (name, password_hash) VALUES (?, ?)');
        $stmt->execute([$name, $hash]);
    }
}

// コメント送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['encrypted_comment'])) {
    $class_id = intval($_POST['class_id']);
    $content = $_POST['encrypted_comment'] ?? '';
    if ($class_id && $content) {
        $stmt = $pdo->prepare('INSERT INTO comments (class_id, content) VALUES (?, ?)');
        $stmt->execute([$class_id, $content]);
    }
}

// クラス一覧取得
$classes = $pdo->query('SELECT * FROM classes')->fetchAll(PDO::FETCH_ASSOC);

// コメント取得（特定クラスのみ）
$selected_class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : null;
$comments = [];
if ($selected_class_id) {
    $stmt = $pdo->prepare('SELECT * FROM comments WHERE class_id = ? ORDER BY created_at DESC');
    $stmt->execute([$selected_class_id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
// クラスを登録
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_class'])) {
    $name = $_POST['class_name'];
    $pass = $_POST['class_password'];
    $hash = password_hash($pass, PASSWORD_DEFAULT);
  
    $stmt = $pdo->prepare('INSERT INTO classes (name, password_hash) VALUES (?, ?)');
    $stmt->execute([$name, $hash]);
  }
  
  // コメント投稿（暗号化されたもの）
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['encrypted_comment'])) {
    $class_id = intval($_POST['class_id']);
    $encrypted = $_POST['encrypted_comment'];
    $stmt = $pdo->prepare('INSERT INTO comments (class_id, content) VALUES (?, ?)');
    $stmt->execute([$class_id, $encrypted]);
  }

  // 投稿処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['username']) && !empty($_POST['content'])) {
    $username = $_POST['username'];
    $content = $_POST['content'];
    $stmt = $pdo->prepare('INSERT INTO messages (username, content) VALUES (?, ?)');
    $stmt->execute([$username, $content]);
    header('Location: ' . $_SERVER['PHP_SELF']); // 投稿後リダイレクト（ページリロード防止）
    exit();
}

// 投稿取得（最新100件）
$stmt = $pdo->query('SELECT * FROM messages ORDER BY created_at DESC LIMIT 100');
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8" />
<title>みんなで話せる掲示板</title>
<style>
body {
  font-family: 'Segoe UI', sans-serif;
  max-width: 720px;
  margin: 40px auto;
  padding: 20px;
  background-color: #f9f9f9;
  color: #333;
}

h1 {
  text-align: center;
  color: #444;
  margin-bottom: 30px;
}

form {
  background-color: #fff;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  margin-bottom: 30px;
}

input[type="text"],
textarea {
  width: 100%;
  padding: 10px 12px;
  margin-bottom: 12px;
  border: 1px solid #ccc;
  border-radius: 8px;
  font-size: 1em;
}

textarea {
  height: 100px;
  resize: vertical;
}

button,
input[type="submit"] {
  background-color: #007bff;
  color: white;
  border: none;
  padding: 10px 16px;
  font-size: 1em;
  border-radius: 6px;
  cursor: pointer;
  transition: background-color 0.2s ease-in-out;
}

button:hover,
input[type="submit"]:hover {
  background-color: #0056b3;
}

.message {
  background-color: #fff;
  border-left: 5px solid #007bff;
  padding: 14px 16px;
  margin-bottom: 12px;
  border-radius: 8px;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
}

.message .username {
  font-weight: bold;
  color: #007bff;
}

.message .time {
  float: right;
  color: #999;
  font-size: 0.85em;
}

.message .content {
  margin-top: 8px;
  line-height: 1.4;
}
.message.username {
    font-weight: bold;
    color: #007bff;
}
</style>
</head>
<body>

<h1>みんなで話す場所</h1>
<a href="jun2.php">グループ作成</a>
<form method="POST" id="postForm">
    名前：<input type="text" name="username" required />
    <br /><br />
    メッセージ：
    <br />
    <textarea name="content" required></textarea>
    <br />
    <button type="submit">投稿する</button>
</form>

<h2>投稿一覧</h2>

<div id="messages">
<?php foreach ($messages as $msg): ?>
    <div class="username"><?= htmlspecialchars($msg['username'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
<div class="time"><?= htmlspecialchars($msg['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
<div class="content"><?= nl2br(htmlspecialchars($msg['content'] ?? '', ENT_QUOTES, 'UTF-8')) ?></div>
<?php endforeach; ?>
</div>

</body>
</html>
