<?php

namespace Transfluent {
    class BackendClient {
        const HTTP_GET = 'GET';
        const HTTP_POST = 'POST';

        static $API_URL;
        private $email;
        private $password;

        private $token = null;

        public function __construct($email, $password, $in_sandbox_mode = false) {
            $this->SystemCheck();
            $this->email = $email;
            $this->password = $password;
            if ($in_sandbox_mode) {
                self::$API_URL = 'https://sandbox.transfluent.com/v2/';
                throw new \Exception('Unfortunately sandbox for API is not public, yet. Sorry!'); // @todo: Implement when sandbox is public
            } else {
                self::$API_URL = 'https://transfluent.com/v2/';
            }
        }

        private function UriFromMethod($method_name) {
            return strtolower(preg_replace("/(?!^)([A-Z]{1}[a-z0-9]{1,})/", '/$1', $method_name)) . '/';
        }

        private function SystemCheck() {
            if (function_exists('curl_init')) {
                return;
            }
            error_log('Transfluent\'s ' . __CLASS__ . ' is missing cURL extension for PHP.');
            throw new \Exception('cURL extension for PHP is not available!');
        }

        private function CallApi($method_name, $method = self::HTTP_GET, $payload = array()) {
            if (is_null($this->token)) {
                $this->Authenticate();
            }
            $payload['token'] = $this->token;
            return $this->Request($method_name, $method, $payload);
        }

        public function SetToken($token) {
            $this->token = $token;
        }

        private function Authenticate() {
            $response = $this->Request(__FUNCTION__, 'POST', array('email' => $this->email, 'password' => $this->password));
            if (!$response->token) {
                throw new \Exception('Could not authenticate with API!');
            }
            $this->token = $response->token;
        }

        private function Request($method_name, $method = self::HTTP_GET, $payload = array()) {
            $uri = $this->UriFromMethod($method_name);

            $curl_handle = curl_init(self::$API_URL . $uri);
            if (!$curl_handle) {
                throw new \Exception('Could not initialize cURL!');
            }
            switch (strtoupper($method)) {
                case self::HTTP_GET:
                    $url = self::$API_URL . $uri . '?';
                    $url_parameters = array();
                    foreach ($payload AS $key => $value) {
                        $url_parameters[] = $key . '=' . urlencode($value);
                    }
                    $url .= implode("&", $url_parameters);
                    curl_setopt($curl_handle, CURLOPT_URL, $url);
                    break;
                case self::HTTP_POST:
                    curl_setopt($curl_handle, CURLOPT_POST, TRUE);
                    if (!empty($payload)) {
                        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $payload);
                    }
                    break;
                default:
                    throw new \Exception('Unsupported request method.');
            }
            curl_setopt($curl_handle, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
            $response = curl_exec($curl_handle);
            if (!$response) {
                throw new \Exception('Failed to connect with Transfluent\'s API. cURL error: ' . curl_error($curl_handle));
            }
            if ($method == 'FileRead') {
                // Note! /file/read/ returns file data
                return $response;
            }
            $response_obj = json_decode($response, true);
            if (!$response_obj) {
                throw new \Exception('Could not parse API\'s response: ' . $response);
            }
            if ($response_obj['status'] == 'ERROR') {
                throw new \Exception('API returned an error #' . $response_obj['error']['type'] . ': ' . $response_obj['error']['message'] . '. Error description: ' . $response_obj['response']);
            }
            if ($response_obj['status'] != 'OK') {
                throw new \Exception('API returned unexpected response: ' . $response);
            }
            return $response_obj['response'];
        }

        public function Languages() {
            return $this->Request(__FUNCTION__);
        }

        public function FileStatus($identifier, $language) {
            return $this->CallApi(__FUNCTION__, self::HTTP_GET, array('identifier' => $identifier, 'language' => $language));
        }

        /**
         * Return true if file is completely translated, otherwise returns translation completion percentage
         *
         * @param $identifier
         * @param $language
         * @return bool|string
         */
        public function IsFileComplete($identifier, $language) {
            $response = $this->FileStatus($identifier, $language);
            if ($response['progress'] == '100%') {
                return true;
            }
            return $response['progress'];
        }
    }
}
