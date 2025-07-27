<?php
// DB接続設定
$dsn = 'mysql:host=localhost;dbname=sample_db;charset=utf8mb4';
$user = 'root';
$pass = 'root';

try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB接続失敗: " . $e->getMessage());
}

// 暗号化キー（適切に管理してください）
define('ENCRYPTION_KEY', 'your-secret-key-here');

// 暗号化関数
function encryptMessage($message) {
    return openssl_encrypt($message, 'AES-128-CTR', ENCRYPTION_KEY, 0, '1234567891011121');
}

// 復号化関数
function decryptMessage($encryptedMessage) {
    return openssl_decrypt($encryptedMessage, 'AES-128-CTR', ENCRYPTION_KEY, 0, '1234567891011121');
}

// APIモード判定（Ajax通信時）
$action = $_GET['action'] ?? null;

if ($action === 'send_message') {
    // メッセージ送信API
    header('Content-Type: application/json; charset=utf-8');
    $input = json_decode(file_get_contents('php://input'), true);

    $class_id = isset($input['class_id']) ? intval($input['class_id']) : null;
    $username = htmlspecialchars($input['username'] ?? '', ENT_QUOTES, 'UTF-8');
    $content = htmlspecialchars($input['content'] ?? '', ENT_QUOTES, 'UTF-8');

    if ($class_id && $username && $content) {
        try {
            $encryptedContent = encryptMessage($content); // メッセージを暗号化
            $stmt = $pdo->prepare("INSERT INTO messages (class_id, username, content) VALUES (?, ?, ?)");
            $stmt->execute([$class_id, $username, $encryptedContent]);
            echo json_encode(['status' => 'success']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'SQLエラー: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => '不正なパラメータ']);
    }
    exit;
} elseif ($action === 'get_messages') {
    // メッセージ取得API
    header('Content-Type: application/json; charset=utf-8');
    $class_id = intval($_GET['class_id'] ?? 0);

    if ($class_id) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM messages WHERE class_id = ? ORDER BY created_at ASC");
            $stmt->execute([$class_id]);
            $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // メッセージを復号化
            foreach ($msgs as &$msg) {
                $msg['content'] = decryptMessage($msg['content']);
            }

            echo json_encode($msgs);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'SQLエラー: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode([]);
    }
    exit;
}
if ($action === 'get_messages') {
  // メッセージ取得API
  header('Content-Type: application/json; charset=utf-8');
  $class_id = intval($_GET['class_id'] ?? 0);

  if ($class_id) {
      try {
          $stmt = $pdo->prepare("SELECT * FROM messages WHERE class_id = ? ORDER BY created_at ASC");
          $stmt->execute([$class_id]);
          $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

          // メッセージを復号化
          foreach ($msgs as &$msg) {
              $decryptedContent = decryptMessage($msg['content']);
              if ($decryptedContent === false) {
                  $msg['content'] = '復号化エラー';
              } else {
                  $msg['content'] = $decryptedContent;
              }
          }

          echo json_encode($msgs);
      } catch (PDOException $e) {
          echo json_encode(['status' => 'error', 'message' => 'SQLエラー: ' . $e->getMessage()]);
      }
  } else {
      echo json_encode([]);
  }
  exit;
}


?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>グループチャット</title>
    <style>
        body { max-width: 700px; margin: 20px auto; font-family: Arial, sans-serif; }
        #chat { border: 1px solid #ccc; padding: 10px; height: 400px; overflow-y: scroll; background: #f9f9f9; }
        .message { border-bottom: 1px solid #ddd; padding: 5px; }
        .username { font-weight: bold; color: #333; }
        .time { font-size: 0.8em; color: #999; }
        #sendForm { margin-top: 10px; }
        #sendForm input, #sendForm textarea { width: 100%; padding: 8px; box-sizing: border-box; margin-bottom: 5px; }
        #sendForm button { padding: 8px 16px; }
    </style>
</head>
<body>

<h1>グループチャット</h1>
<div id="time"></div>
<div id="chat"></div>

<form id="sendForm">
    <input type="text" id="username" placeholder="名前" required maxlength="50">
    <textarea id="content" placeholder="メッセージ" required></textarea>
    <button type="submit">送信</button>
</form>

<script>
const classId = 1; // クラスIDを固定（必要に応じて動的に変更可能）
const chatDiv = document.getElementById('chat');
const sendForm = document.getElementById('sendForm');
const timeDiv = document.getElementById('time');

// HTMLエスケープ関数
function escapeHtml(text) {
    return text.replace(/[&<>"']/g, m => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    })[m]);
}

// 現在の時間を表示
function updateTime() {
    const now = new Date();
    timeDiv.textContent = `現在の時間: ${now.toLocaleString()}`;
}
setInterval(updateTime, 1000);
updateTime();

// メッセージ取得＆表示
async function fetchMessages() {
    try {
        const res = await fetch(`?action=get_messages&class_id=${classId}`);
        const msgs = await res.json();
        chatDiv.innerHTML = ''; // チャットエリアをクリア
        msgs.forEach(msg => {
            const div = document.createElement('div');
            div.classList.add('message');
            div.innerHTML = `
                <div class="username">${escapeHtml(msg.username)}</div>
                <div class="time">${new Date(msg.created_at).toLocaleString()}</div>
                <div class="content">${escapeHtml(msg.content).replace(/\n/g, '<br>')}</div>
            `;
            chatDiv.appendChild(div);
        });
        chatDiv.scrollTop = chatDiv.scrollHeight; // スクロールを一番下に
    } catch (e) {
        console.error('メッセージ取得中にエラーが発生しました:', e);
    }
}

// メッセージ送信
sendForm.addEventListener('submit', async e => {
    e.preventDefault();
    const username = document.getElementById('username').value.trim();
    const content = document.getElementById('content').value.trim();
    if (!username || !content) return alert('名前とメッセージを入力してください。');

    try {
        const res = await fetch('?action=send_message', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({class_id: classId, username, content})
        });
        const json = await res.json();
        if (json.status === 'success') {
            document.getElementById('content').value = '';
            fetchMessages(); // メッセージ送信後に即時更新
        } else {
            alert('送信エラー: ' + json.message);
        }
    } catch (e) {
        alert('送信中にエラーが発生しました。');
        console.error(e);
    }
});

// 5秒ごとにメッセージ更新
setInterval(fetchMessages, 5000);
fetchMessages();
</script>

</body>
</html>