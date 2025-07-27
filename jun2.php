<?php
// DB接続設定
$dsn = 'mysql:host=localhost;dbname=sample_db;charset=utf8mb4';
$user = 'root';
$pass = 'root';

try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("DB接続失敗: " . $e->getMessage());
}

// --- クラス作成処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_class'])) {
    $name = $_POST['class_name'] ?? '';
    $password = $_POST['class_password'] ?? '';

    if ($name !== '' && $password !== '') {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO classes (name, password_hash) VALUES (?, ?)");
        $stmt->execute([$name, $hash]);

        $new_class_id = $pdo->lastInsertId();
        header("Location: ?class_id=" . $new_class_id);
        exit();
    } else {
        $error = "クラス名とパスワードを入力してください。";
    }
}


// --- コメント投稿処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['encrypted_comment'], $_POST['class_id'])) {
    $class_id = (int)$_POST['class_id'];
    $encrypted = $_POST['encrypted_comment'];

    if ($class_id > 0 && $encrypted !== '') {
        $stmt = $pdo->prepare("INSERT INTO comments (class_id, content) VALUES (?, ?)");
        $stmt->execute([$class_id, $encrypted]);
        header("Location: ?class_id=" . $class_id);
        exit();
    }
}

// --- クラス一覧取得 ---
$classes = $pdo->query("SELECT * FROM classes ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// --- クラスID取得 ---
$selected_class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;
$comments = [];

if ($selected_class_id) {
    $stmt = $pdo->prepare("SELECT * FROM comments WHERE class_id = ? ORDER BY created_at DESC");
    $stmt->execute([$selected_class_id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // クラス情報（名前など）
    $stmt2 = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
    $stmt2->execute([$selected_class_id]);
    $selected_class = $stmt2->fetch(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>クラス掲示板</title>
<style>
body {
    font-family: Arial, sans-serif;
    max-width: 700px;
    margin: 20px auto;
    padding: 0 10px;
}
h1, h2 {
    color: #333;
}
form {
    margin-bottom: 20px;
}
input[type=text], input[type=password], textarea {
    width: 100%;
    padding: 8px;
    margin-top: 5px;
    margin-bottom: 10px;
    box-sizing: border-box;
}
button {
    padding: 8px 16px;
    cursor: pointer;
}
.comment-box {
    border: 1px solid #ccc;
    padding: 10px;
    margin-bottom: 10px;
    white-space: pre-wrap;
    background-color: #f9f9f9;
}
</style>
</head>
<body>

<h1>クラス掲示板</h1>

<h2>クラス作成</h2>
<?php if (!empty($error)): ?>
    <p style="color:red;"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>
<form method="POST">
    <input type="hidden" name="new_class" value="1">
    <label>クラス名:<input type="text" name="class_name" required></label>
    <label>パスワード:<input type="password" name="class_password" required></label>
    <button type="submit">作成</button>
</form>

<h2>クラス一覧</h2>
<ul>
    <?php foreach ($classes as $cls): ?>
        <li>
            <a href="jun3.php?class_id=<?= $cls['id'] ?>">
            <?= htmlspecialchars($cls['name']) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ($selected_class_id && $selected_class): ?>
    <h2>クラス「<?= htmlspecialchars($selected_class['name']) ?>の掲示板</h2>

    <form method="POST" onsubmit="return encryptComment();">
        <input type="hidden" name="class_id" value="<?= $selected_class_id ?>">
        <textarea id="commentInput" placeholder="コメントを入力してください" required></textarea><br>
        <input type="hidden" id="encryptedComment" name="encrypted_comment">
        <button type="submit">送信（暗号化）</button>
    </form>

    <h3>コメント一覧</h3>
    <?php if (empty($comments)): ?>
        <p>まだコメントはありません。</p>
    <?php else: ?>
        <?php foreach ($comments as $comment): ?>
            <div class="comment-box" data-enc="<?= htmlspecialchars($comment['content']) ?>"></div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>

<script>
// 暗号鍵（簡易版テスト用）
const key = "secretkey";

// XORで簡単暗号化
function encrypt(text) {
    return btoa(unescape(encodeURIComponent(
        text.split('').map((c, i) => 
            String.fromCharCode(c.charCodeAt(0) ^ key.charCodeAt(i % key.length))
        ).join('')
    )));
}

// 復号化
function decrypt(enc) {
    const decoded = decodeURIComponent(escape(atob(enc)));
    return decoded.split('').map((c, i) => 
        String.fromCharCode(c.charCodeAt(0) ^ key.charCodeAt(i % key.length))
    ).join('');
}

// フォーム送信前にコメントを暗号化
function encryptComment() {
    const plain = document.getElementById('commentInput').value;
    const encrypted = encrypt(plain);
    document.getElementById('encryptedComment').value = encrypted;
    return true; // フォーム送信続行
}

// ページロード時にコメント復号化して表示
window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.comment-box').forEach(div => {
        const encrypted = div.getAttribute('data-enc');
        if (encrypted) {
            const plain = decrypt(encrypted);
            div.textContent = plain;
        }
    });
});
</script>

</body>
</html>
