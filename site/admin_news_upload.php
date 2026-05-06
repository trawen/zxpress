<?php
require 'init.inc';

if (!isset($_SESSION['login']) || !$_SESSION['login']) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$types = array('jpeg'=>'image','jpg'=>'image','png'=>'image','gif'=>'image','mp3'=>'audio','ogg'=>'audio');
$path_parts = pathinfo('/www/htdocs/inc/lib.inc.php');

function is_image($path)
{
    $a = getimagesize($path);
    $image_type = $a[2];

    if(in_array($image_type , array(IMAGETYPE_GIF , IMAGETYPE_JPEG ,IMAGETYPE_PNG , IMAGETYPE_BMP)))
    {
        return true;
    }
    return false;
}    

if ($_POST['url']) {
    csrf_verify();

    if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $_POST['url'], $match)) {
        $name = $match[1];
        $type ="youtube";

        db_exec($db, "INSERT INTO news_files (`type`,`name`) VALUES (?,?)", "ss", "youtube", $name);
            $id = mysqli_insert_id($db);
        $files[] = Array("name" => $name, "type" => $type);

        echo json_encode($files);
        exit;
    }
    else {

        $url = $_POST['url'];

        // SSRF protection: only allow http/https schemes
        $parsed = parse_url($url);
        $scheme = strtolower($parsed['scheme'] ?? '');
        if ($scheme !== 'http' && $scheme !== 'https') {
            echo json_encode(['error' => 'Invalid URL scheme']);
            exit;
        }

        // Block internal/private IPs
        $host = $parsed['host'] ?? '';
        $resolved_ip = gethostbyname($host);
        if ($resolved_ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
            echo json_encode(['error' => 'Cannot resolve host']);
            exit;
        }
        if (filter_var($resolved_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_NO_LOOPBACK) === false) {
            echo json_encode(['error' => 'Internal URLs not allowed']);
            exit;
        }

        // Validate extension before downloading
        $ext  = strtolower(pathinfo($parsed['path'] ?? '', PATHINFO_EXTENSION));
        if (!isset($types[$ext])) {
            echo json_encode(['error' => 'Unsupported file type']);
            exit;
        }

        $file = zx_storage_path('tmp', (string) time());

        // Download with size limit (10MB)
        $ctx = stream_context_create(['http' => ['timeout' => 10, 'max_redirects' => 3]]);
        $remote = @fopen($url, 'r', false, $ctx);
        if (!$remote) {
            echo json_encode(['error' => 'Failed to download']);
            exit;
        }
        $local = fopen($file, 'w');
        $max_size = 10 * 1024 * 1024;
        $downloaded = 0;
        while (!feof($remote) && $downloaded < $max_size) {
            $chunk = fread($remote, 8192);
            $downloaded += strlen($chunk);
            fwrite($local, $chunk);
        }
        fclose($remote);
        fclose($local);
        if ($downloaded >= $max_size) {
            unlink($file);
            echo json_encode(['error' => 'File too large']);
            exit;
        }

        $name = basename($parsed['path'] ?? 'file');
        $type = $types[$ext];

        if ($type) {
    
            db_exec($db, "INSERT INTO news_files (`type`,`name`) VALUES (?,?)", "ss", $type, $name);
            $id = mysqli_insert_id($db);

            if ($type == "image") {

                list($w, $h, $t, $a) = getimagesize( $file );

            }
            rename($file, zx_storage_path('news_files', $id.".".$ext));

            $files[] = Array("name" => $id.".".$ext, "type" => $type, "original_name" => $name, "w" => $w, "h" => $h);

            echo json_encode($files);
            exit;
        }
    }

}



$_csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
echo "<body style='margin: 0; padding: 0'><form style='margin: 0; padding: 0' action='admin_news_upload.php' method='post' enctype='multipart/form-data'><input type='hidden' name='csrf_token' value='{$_csrf}'><input style='font: normal 17px Times' type='file' name='files[]' multiple onchange='this.form.submit();false;'></form></body>";



if (!empty($_FILES["files"])) {
csrf_verify();

foreach ($_FILES["files"]["error"] as $key => $error) {
    
    if ($error == UPLOAD_ERR_OK) {
        
        $path = pathinfo($_FILES["files"]["name"][$key]);
        $name = $_FILES["files"]["name"][$key];
        $ext  = strtolower($path['extension']);
        $type = $types[$ext];

        if ($type) {
        
            db_exec($db, "INSERT INTO news_files (`type`,`name`) VALUES (?,?)", "ss", $type, $name);
            $id = mysqli_insert_id($db);

            if ($type == "image") {

                list($w, $h, $t, $a) = getimagesize( $_FILES["files"]["tmp_name"][$key] );

            }
            move_uploaded_file($_FILES["files"]["tmp_name"][$key], zx_storage_path('news_files', $id.".".$ext));

            $files[] = Array("name" => $id.".".$ext, "type" => $type, "original_name" => $name, "w" => $w, "h" => $h);
        }
        
    }
}

if ($files) {

    echo '<script>parent.add_files(' . json_encode($files, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) . ');</script>';

}
} // end if FILES

?>