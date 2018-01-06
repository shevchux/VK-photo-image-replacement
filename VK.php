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
        if ($a->IsAuthed()) {
            $data = self::getUserInfo($a->user["id"]);
            $a->user["name"] = $data["name"];
            $a->user["photo_50"] = $data["photo_50"];
        }
        return $a;
    }
    
    public static function Login($email, $pass) {
        $hashes = self::getSession();
        $lg_h = $hashes["lg_h"];
        $ip_h = $hashes["ip_h"];
        $cookies = $hashes["cookies"];

        $data = self::loginUser($email, $pass, $lg_h, $ip_h, $cookies);
        $a = new VK();
        $a->user["id"] = +$data["id"];
        $a->user["cookies"] = empty($data["remixsid"]) ? null : "remixap=1; audio_vol=80; remixchk=5; remixlang=0; remixsid=" . $data["remixsid"];

        if ($a->IsAuthed()) {
            $data = self::getUserInfo($a->user["id"]);
            $a->user["name"] = $data["name"];
            $a->user["photo_50"] = $data["photo_50"];
        }
        
        return $a;
    }
    
    public function IsAuthed() {
        return $this->user["cookies"] && $this->user["id"];
    }
    
    private static function getUserInfo($user_id) {
        $params = [
            "user_id" => $user_id,
            "fields" => "photo_50",
            "v" => "5.0"
        ];
        if ($curl = curl_init()) {
            curl_setopt($curl, CURLOPT_URL, "https://api.vk.com/method/users.get");
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
            $data = curl_exec($curl);
            curl_close($curl);
        } else {
            throw new Exception("Cannot connect https://login.vk.com/?act=login (POST).");
        }
        $a = json_decode($data, true)["response"][0];
        return [ "name" => $a["first_name"] . " " . $a["last_name"], "photo_50" => $a["photo_50"]];
    }
    
    private static function loginUser($email, $pass, $lg_h, $ip_h, $cookies) {
        $params = [
            "role"			=> "al_frame",
            "_origin"		=> "https://vk.com/",
            "ip_h"			=> $ip_h,
            "lg_h"			=> $lg_h,
            "email"			=> $email,
            "pass"			=> $pass
        ];

        if ($curl = curl_init()) {
            curl_setopt($curl, CURLOPT_URL, "https://login.vk.com/?act=login");
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_HEADER, true);
            curl_setopt($curl, CURLOPT_COOKIE, $cookies);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
            $data = curl_exec($curl);
            curl_close($curl);
        } else {
            throw new Exception("Cannot connect https://login.vk.com/?act=login (POST).");
        }
        
        $result = [
            "id" => null,
            "remixsid" => null
        ];
        
        preg_match('/remixsid=([0-9a-f]+)/', $data, $matches);
        $result["remixsid"] = $matches[1];
        
        preg_match('/\sl=([0-9]+);/', $data, $matches);
        $result["id"] = $matches[1];
        
        return $result;
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
        } else {
            throw new Exception("Cannot connect https://vk.com/ (POST).");
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
    
    public function getPhotoInfo($pid) {
        $result = [
            "pid" => $pid,
            "src" => null,
            "upload_url" => null,
            "hash" => null,
            "is_original" => null,
            "original_src" => null,
            "restore_hash" => null
        ];
        
        if ($curl = curl_init()) {
            curl_setopt($curl, CURLOPT_URL, trim("https://m.vk.com/photo" . $pid));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_COOKIE, $this->user["cookies"]);
            $data = curl_exec($curl);
            curl_close($curl);
        } else {
            throw new Exception("Ошибка. Не удалось получить ответ от сервера ВК.");
        }
        
        if (!preg_match("/<a href=\"(http.+?\.jpg)\"/", $data, $matches)) {
            throw new Exception("Ошибка. Указанная фотография не найдена или вы не имеете к ней доступа.");
        }
        $result["src"] = $matches[1];
        
        if ($curl = curl_init()) {
            curl_setopt($curl, CURLOPT_URL, trim("https://vk.com/al_photos.php?act=edit_photo&al=1&photo=" . $pid));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_COOKIE, $this->user["cookies"]);
            $data = curl_exec($curl);
            curl_close($curl);
        } else {
            throw new Exception("Ошибка. Не удалось получить ответ от сервера ВК.");
        }
        $result["is_original"] = (stristr($data, "restoreOriginal(") === FALSE);

        if (!preg_match("/\"src\":\"(http.+?\.jpg)\"/", $data, $matches)) {
            throw new Exception("Ошибка. Вы не можете редактировать эту фотографию.");
        }
        $result["original_src"] = $matches[1];
        
        if (!preg_match("/, '(.*)'\)/", $data, $matches)) {
            throw new Exception("Неизвестная ошибка.");
        }
        $result["restore_hash"] = $matches[1];
        
        if (!preg_match("|', '(.*)'\)|U", $data, $matches)) {
            throw new Exception("Неизвестная ошибка.");
        }
        $result["hash"] = $matches[1];
        
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
        } else {
            throw new Exception("Ошибка. Не удалось получить ответ от сервера ВК.");
        }
        $error = json_decode($data, true)["error"];
        if (!empty($error)) {
            throw new Exception("Ошибка. Вероятно, был выбран некорректный файл. ($error)");
        }
        return $data;
    }
    
    public function updatePhoto($query, $pid, $hash) {
        $params = [
            "_query" => $query,
            "act" => "save_desc",
            "al" => 1,
            "conf" => "f/liber,0/////",
            "hash" => $hash,
            "photo" => $pid,
            "text" => ""
        ];
        
        if ($curl = curl_init()) {
            curl_setopt($curl, CURLOPT_URL, 'https://vk.com/al_photos.php');
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_COOKIE, $this->user["cookies"]);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
            $data = curl_exec($curl);
            curl_close($curl);
        } else {
            throw new Exception("Ошибка. Не удалось получить ответ от сервера ВК.");
        }
        if (strpos($data, '<!>8<!>')) {
            throw new Exception("Превышен лимит обновления фотографии. Подождите сутки.");
        } else if (strpos($data, '<!>5<!>')) {
            // ок, выполнилось
        } else if (!strpos($data, '<!json>')) {
            throw new Exception("Неизвестный ответ сервера.");
        }
        return $data;
    }
    
    public function restorePhoto($pid, $hash) {
        $pos = strpos($pid, '_');
        $oid = substr($pid, 0, $pos);
        $pid = substr($pid, $pos + 1);
        $params = [
            "act" => "restore_original",
            "al" => 1,
            "hash" => $hash,
            "oid" => $oid,
            "pid" => $pid
        ];
        
        if ($curl = curl_init()) {
            curl_setopt($curl, CURLOPT_URL, 'https://vk.com/al_photos.php');
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_COOKIE, $this->user["cookies"]);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
            $data = curl_exec($curl);
            curl_close($curl);
        } else {
            throw new Exception("Ошибка. Не удалось получить ответ от сервера ВК.");
        }
        if (strpos($data, '<!>8<!>')) {
            throw new Exception("Превышен лимит обновления фотографии. Подождите сутки.");
        } else if (strpos($data, '<!>5<!>')) {
            // ок, выполнилось
        } else if (!strpos($data, '<!json>')) {
            throw new Exception("Неизвестный ответ сервера.");
        }
        
        return $data;
    }
}
?>