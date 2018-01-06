<?php
class VK {
    public $user = [
        "id" => null,
        "name" => null,
        "photo_50" => null,
        "cookies" => null
    ];
    
    public static function ByUser($user) {
        $a = new VK();
        $a->user = $user;
        
        if (!($a->user["cookies"] && $a->user["id"])) {
            throw new Exception("Сессия неверна или устарела. Повторите попытку входа.");
        }
        
        $data = self::getUserInfo($a->user["id"]);
        $a->user["name"] = $data["name"];
        $a->user["photo_50"] = $data["photo_50"];
        
        return $a;
    }
    
    public static function Login($email, $pass) {
        $hashes = null;
        try {
            $hashes = self::getSession();
        } catch (Exception $e) {
            throw new Exception("Не удалось создать сессию ($e).");
        }
        $lg_h = $hashes["lg_h"];
        $ip_h = $hashes["ip_h"];
        $cookies = $hashes["cookies"];

        $a = new VK();
        $data = null;
        try {
            $data = self::loginUser($email, $pass, $lg_h, $ip_h, $cookies);
        } catch (Exception $e) {
            throw new Exception("Не удалось войти. Проверьте правильность введенных данных.");
        }
        $a->user["id"] = +$data["id"];
        $a->user["cookies"] = "remixap=1; audio_vol=80; remixchk=5; remixlang=0; remixsid=" . $data["remixsid"];

        $data = self::getUserInfo($a->user["id"]);
        $a->user["name"] = $data["name"];
        $a->user["photo_50"] = $data["photo_50"];

        return $a;
    }
    
    private static function getSession() {
        if ($curl = curl_init()) {
            curl_setopt($curl, CURLOPT_URL, "https://vk.com/");
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HEADER, true);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, null);
            $data = curl_exec($curl);
            curl_close($curl);
        }

        if (!preg_match_all('|Set-Cookie: (.*);|U', $data, $parse_cookies))
        {
            throw new Exception("Cannot find cookies.");
        }
        $cookies = implode(';', $parse_cookies[1]);

        if (!preg_match('/name=\"ip_h\" value=\"([0-9a-f]+)\"/', $data, $matches))
        {
            throw new Exception("Cannot find ip_h.");
        }
        $ip_h = $matches[1];

        if (!preg_match('/name=\"lg_h\" value=\"([0-9a-f]+)\"/', $data, $matches))
        {
            throw new Exception("Cannot find lg_h.");
        }
        $lg_h = $matches[1];
        
        return [ "lg_h" => $lg_h, "ip_h" => $ip_h, "cookies" => $cookies ];
    }
    
    private static function loginUser($email, $pass, $lg_h, $ip_h, $cookies) {
        if ($curl = curl_init()) {
            $params = [
                "role"			=> "al_frame",
                "_origin"		=> "https://vk.com/",
                "ip_h"			=> $ip_h,
                "lg_h"			=> $lg_h,
                "email"			=> $email,
                "pass"			=> $pass
            ];
            
            curl_setopt($curl, CURLOPT_URL, "https://login.vk.com/?act=login");
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_HEADER, true);
            curl_setopt($curl, CURLOPT_COOKIE, $cookies);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
            $data = curl_exec($curl);
            curl_close($curl);
        }
        
        $result = [
            "id" => null,
            "remixsid" => null
        ];
        
        if (!preg_match('/remixsid=([0-9a-f]+)/', $data, $matches)) {
            throw new Exception("Cannot find remixsid.");
        }
        $result["remixsid"] = $matches[1];
        
        if (!preg_match('/\sl=([0-9]+);/', $data, $matches)) {
            throw new Exception("Cannot find l.");
        }
        $result["id"] = $matches[1];
        
        return $result;
    }
    
    private static function getUserInfo($user_id) {
        if ($curl = curl_init()) {
            $params = [
                "user_id" => $user_id,
                "fields" => "photo_50",
                "v" => "5.0"
            ];
            
            curl_setopt($curl, CURLOPT_URL, "https://api.vk.com/method/users.get");
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
            $data = curl_exec($curl);
            curl_close($curl);
        }
        
        $a = json_decode($data, true)["response"][0];
        if (!isset($a)) {
            throw new Exception("Не удалось получить информацию о пользователе.");
        }
        
        return [ "name" => $a["first_name"] . " " . $a["last_name"], "photo_50" => $a["photo_50"]];
    }
    
    public function getPhotoInfo($pid) {
        $result = [
            "pid" => $pid,
            "upload_url" => null,
            "is_original" => null,
            "original" => [
                "hash" => null, // restore hash
                "src" => null
            ],
            "actual" => [
                "hash" => null,
                "src" => null
            ]
        ];
        
        if ($curl = curl_init()) {
            curl_setopt($curl, CURLOPT_URL, trim("https://m.vk.com/photo" . $pid));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_COOKIE, $this->user["cookies"]);
            $data = curl_exec($curl);
            curl_close($curl);
        }
        
        if (!preg_match("/<a href=\"(http.+?\.jpg)\"/", $data, $matches)) {
            throw new Exception("Указанная фотография не найдена или вы не имеете к ней доступа.");
        }
        $result["actual"]["src"] = $matches[1];
        
        if ($curl = curl_init()) {
            curl_setopt($curl, CURLOPT_URL, trim("https://vk.com/al_photos.php?act=edit_photo&al=1&photo=" . $pid));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_COOKIE, $this->user["cookies"]);
            $data = curl_exec($curl);
            curl_close($curl);
        }
        $result["is_original"] = (stristr($data, "restoreOriginal(") === FALSE);

        if (!$result["is_original"]) {
            if (!preg_match("/\"src\":\"(http.+?\.jpg)\"/", $data, $matches)) {
                throw new Exception("Вы не можете редактировать эту фотографию.");
            }
            $result["original"]["src"] = str_replace('\\', '', $matches[1]);
            
            if (!preg_match("/, '(.*)'\)/", $data, $matches)) {
                throw new Exception("Неизвестная ошибка.");
            }
            $result["original"]["hash"] = $matches[1];
        }
        
        if (!preg_match("|', '(.*)'\)|U", $data, $matches)) {
            throw new Exception("Неизвестная ошибка.");
        }
        $result["actual"]["hash"] = $matches[1];
        
        if (!preg_match("/\"upload_url\":\"(.*?)\"/", $data, $matches)) {
            throw new Exception("Неизвестная ошибка.");
        }
        $result["upload_url"] = str_replace('\\', '', $matches[1]);
        
        return $result;
    }
    
    public function uploadFile($upload_url, $file_path) {
        if ($curl = curl_init()) {
            curl_setopt($curl, CURLOPT_URL, trim($upload_url));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_COOKIE, $this->user["cookies"]);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, array('file0' => curl_file_create($file_path, 'image/jpeg', 'Filtered.jpg')));
            $data = curl_exec($curl);
            curl_close($curl);
        }
        
        $error = json_decode($data, true)["error"];
        if (!empty($error)) {
            throw new Exception("Некорректный файл или недопустимый размер изображения ($error).");
        }
        
        return $data;
    }
    
    public function updatePhoto($query, $pid, $hash) {
        if ($curl = curl_init()) {
            $params = [
                "_query" => $query,
                "act" => "save_desc",
                "al" => 1,
                "conf" => "f/liber,0/////",
                "hash" => $hash,
                "photo" => $pid,
                "text" => ""
            ];
            
            curl_setopt($curl, CURLOPT_URL, 'https://vk.com/al_photos.php');
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_COOKIE, $this->user["cookies"]);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
            $data = curl_exec($curl);
            curl_close($curl);
        }
        
        if (strpos($data, '<!>8<!>')) {
            throw new Exception("Превышен лимит обновления фотографии. Повторить попутку можно будет через сутки.");
        } else if (strpos($data, '<!>5<!>')) {
            // ок, оно выполнилось
        } else if (!strpos($data, '<!json>')) {
            throw new Exception("Неизвестный ответ сервера.");
        }
        return $data;
    }
    
    public function restorePhoto($pid, $hash) {
        $pos = strpos($pid, '_');
        $oid = substr($pid, 0, $pos);
        $pid = substr($pid, $pos + 1);
        
        if ($curl = curl_init()) {
            $params = [
                "act" => "restore_original",
                "al" => 1,
                "hash" => $hash,
                "oid" => $oid,
                "pid" => $pid
            ];
            
            curl_setopt($curl, CURLOPT_URL, 'https://vk.com/al_photos.php');
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_COOKIE, $this->user["cookies"]);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
            $data = curl_exec($curl);
            curl_close($curl);
        }
        
        if (strpos($data, '<!>8<!>')) {
            throw new Exception("Ошибка доступа.");
        } else if (strpos($data, '<!>5<!>')) {
            // ок, выполнилось
        } else if (!strpos($data, '<!json>')) {
            throw new Exception("Неизвестный ответ сервера.");
        }
        
        return $data;
    }
}
?>