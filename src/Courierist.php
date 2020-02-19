<?php

namespace galsd\courierist;

use Exception;
use Yii;
use yii\base\Component;
use yii\helpers\ArrayHelper;

class Courierist extends Component {
    const CURL_TIMEOUT = 45;
    const HTTP_VALIDATION = 422;

    public $url;
    public $login;
    public $password;

    private $token;

    public function init() {
        parent::init();
        $this->authenticate();
    }

    public function authenticate() {
        $url = "{$this->url}/api/v1/access/login";
        try {
            $this->token = null;
            $response = $this->_curl($url, [
                "login" => $this->login,
                "password" => $this->password
            ]);
            if ($response->access_token) {
                $this->token = $response->access_token;
            } else {
                throw new Exception("Ошибка получения токена");
            }
        } catch (Exception $e) {
            throw new Exception("Авторизация не удалась. {$e->getMessage()}");
        }
    }


    public function createOrder($data) {
        $url = "{$this->url}/api/v1/order/create";
        try {
            return $this->_curl($url, $data);
        } catch (Exception $e) {
            throw new Exception("Не удалось создать заказ. {$e->getMessage()}");
        }
    }

    public function updateOrder($id, $data) {
        $url = "{$this->url}/api/v1/order/update/{$id}";
        try {
            return $this->_curl($url, $data);
        } catch (Exception $e) {
            throw new Exception("Не удалось обновить заказ. {$e->getMessage()}");
        }
    }

    /**
     * @brief HTTP запрос к Courierist API
     * @param string $url адрес запроса
     * @param array|false $post параметры запроса
     * @return array
     * @throws Exception
     */
    protected function _curl($url, $post = false) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, static::CURL_TIMEOUT);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Accept: application/json",
        ]);
        if ($this->token) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$this->token}",
            ]);
        }
        if ($post) {
            $query = json_encode($post);
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
            Yii::info("Отправлен POST запрос: $url, с параметрами: $query");
        } else {
            Yii::info("Отправлен GET запрос: $url");
        }
        $result = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($result === false) {
            $message = curl_error($ch);
            Yii::error("Не удалось выполнить запрос: $message");
            throw new Exception("Ошибка подключения к удаленному серверу: $message");
        }
        curl_close($ch);
        $answer = json_decode($result);
        if ($httpCode === static::HTTP_VALIDATION && is_array($answer)) {
            throw new Exception(implode(', ', ArrayHelper::getColumn($answer, 'message')));
        } else if ($httpCode >= 400) {
            $message = $answer->message ?? "";
            $status = $answer->status ?? 0;
            Yii::error("$message ($status)");
            throw new Exception($message, $status);
        } else {
            Yii::debug("Получен ответ: $result");
        }
        return $answer;
    }
}
