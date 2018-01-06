<?php
header("Content-Type: text/html; charset=utf-8");
session_start();

$user = $_SESSION["user"];
if (empty($user)) {
    header("Location: login.php");
    exit(0);
}

if (isset($_POST["pid"])) {
    if (!preg_match("/photo(\-?[0-9]+\_[0-9]+)/", $_POST["pid"], $matches)) {
        header("Location: index.php");
    } else {
        header("Location: index.php?pid=" . $matches[1]);
    }
    exit(0);
}

include_once("VK.php");
$VK = null;
try {
    $VK = VK::ByUser($user);
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Обновление фотографии</title>
</head>
<body>
    <?php
        if (empty($error)) {
    ?>
        <table width="100%">
            <tr>
                <td width="30">
                    <img src="<?=$VK->user["photo_50"];?>" alt="<?=$VK->user["name"];?>" width="30" height="30">
                </td>
                <td width="10"></td>
                <td>
                    <p><strong><a href="//vk.com/id<?=$VK->user["id"];?>" target="_blank"><?=$VK->user["name"];?></a></strong></p>
                </td>
                <td align="right">
                    <form method="get" action="login.php">
                        <input type="hidden" name="act" value="logout">
                        <button type="submit">Выход</button>
                    </form>
                </td>
            </tr>
        </table>
        <hr>
    <?php
        } else {
    ?>
        <table width="100%">
            <tr>
                <td>
                    <p><i><?=$error;?></i></p>
                </td>
                <td align="right">
                    <form method="get" action="login.php">
                        <input type="hidden" name="act" value="logout">
                        <button type="submit">Выход</button>
                    </form>
                </td>
            </tr>
        </table>
        <hr>
    <?php
        exit(0);
    }
    ?>
    
    <?php
        $pid = $_GET["pid"];
        if (empty($pid)) {
    ?>
        <form method="post">
            <p>Укажите ссылку на <strong>вами</strong> загруженную фотографию в ВК.</p>
            <input type="text" name="pid" size="50" placeholder="https://vk.com/feed?z=photo-123456789_987654321">
            <button type="submit">Далее</button>
        </form>
    <?php
        } else if ($_POST["act"] == "restore") {
    ?>
        <a href="/index.php?pid=<?=$pid;?>">« Назад к обновлению фотографии</a>
        <hr>
    <?php
        $hash = $_POST["hash"];
        try {
            $VK->restorePhoto($pid, $hash);
            echo "<p>Фотография успешно восстановлена.</p>";
        } catch (Exception $e) {
            echo "<p>" . $e->getMessage() . "</p>";
        }
    } else if (isset($_FILES["photo"]["tmp_name"])) {
    ?>
        <a href="/index.php?pid=<?=$pid;?>">« Назад к обновлению фотографии</a>
        <hr>
    <?php
        $upload_url = $_POST["upload_url"];
        $hash = $_POST["hash"];
        $file_path = $_FILES["photo"]["tmp_name"];
            
        try {
            $query = $VK->uploadFile($upload_url, $file_path);
            $data = $VK->updatePhoto($query, $pid, $hash);
            echo "<p>Фотография успешно обновлена.</p>";
        } catch (Exception $e) {
            echo "<p>" . $e->getMessage() . "</p>";
        }
    } else {
    ?>
        <a href="/index.php">« Назад</a>
        <hr>
    <?php
        $pinfo = null;
        try {
            $pinfo = $VK->getPhotoInfo($pid);
        } catch (Exception $e) {
            echo "<p>" . $e->getMessage() . "</p>";
            exit(0);
        }
    ?>
        <p>Фотография: <strong>photo<?=$pinfo["pid"];?></strong></p>
    <?php
        if (!$pinfo["is_original"]) {
    ?>
            <form method="post" id="restore-form">
               <p>Оригинал (<a href="#" onclick="document.getElementById('restore-form').submit(); return false;">Восстановить</a>):</p>
               <input type="hidden" name="hash" value="<?=$pinfo["original"]["hash"];?>">
               <input type="hidden" name="act" value="restore">
               <img src="<?=$pinfo["original"]["src"];?>" border="1" alt="original" height="250">
            </form>
    <?php
        }
    ?>
        <form method="post" enctype="multipart/form-data">
            <p>Текущее изображение<?=($pinfo["is_original"] ? " (оригинал)" : "");?>:</p>
            <img src="<?=$pinfo["actual"]["src"];?>" border="2" alt="shown">
            <p>Новое изображение:</p>
            <input type="file" name="photo">
            <input type="hidden" name="hash" value="<?=$pinfo["actual"]["hash"];?>">
            <input type="hidden" name="upload_url" value="<?=$pinfo["upload_url"];?>">
            <button type="submit">Обновить</button>
        </form>
    <?php
        }
    ?>
</body>
</html>