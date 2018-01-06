<?php
header("Content-Type: text/html; charset=utf-8");
session_start();

if ($_GET['act'] == 'logout') {
    session_unset();
    header("Location: login.php");
    exit(0);
}

if (!empty($_SESSION["user"])) {
    header("Location: index.php");
    exit(0);
}

if ($_POST["login"] == 1) {
    include_once("VK.php");
    
    $email = $_POST["email"];
    $pass = $_POST["pass"];
    
    try {
        $VK = VK::Login($email, $pass);
        $_SESSION["user"] = $VK->user;
        header("Location: index.php");
        exit(0);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Авторизация</title>
</head>
<body>
    <?php
    if (!empty($error)) {
        echo "<p><i>$error</i></p><hr>";
    }
    ?>
    <form method="post">
        <p>Войдите с помощью логина и пароля от ВКонтакте.</p>
        <table>
            <tr>
                <td>Логин</td>
                <td>
                    <input type="text" name="email" placeholder="E-mail или номер телефона" required>
                </td>
            </tr>
            <tr>
                <td>Пароль</td>
                <td>
                    <input type="password" name="pass" required>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <button type="submit" name="login" value="1">Войти</button>
                </td>
            </tr>
        </table>
    </form>
</body>
</html>