<?php

namespace Transfluent {
    /**
     * Transfluent API client
     * Version 2.2
     * @see https://github.com/Transfluent/Transfluent-Backend-API-client
     */
    class BackendClient {
        const HTTP_GET = 'GET';
        const HTTP_POST = 'POST';
        const HTTP_PUT = 'PUT';
        const HTTP_DELETE = 'DELETE';

        const LEVEL_TRANSLATION = 'translation';
        const LEVEL_TRANSLATION_AND_PROOF_READING = 'translation+proof-reading';
        const LEVEL_EXPERT_TRANSLATION = 'expert';
        const LEVEL_EXPERT_TRANSLATION_AND_PROOF_READING = 'expert+review';
        const LEVEL_MACHINE_TRANSLATION = 'machine';

        static $API_URL;
        static $API_V3_URL;
        private $_sandbox_mode = false;
        private $email;
        private $password;

        private $token = null;

        public function __construct($email = null, $password = null, $in_sandbox_mode = false) {
            $this->SystemCheck();
            $this->email = $email;
            $this->password = $password;
            if ($in_sandbox_mode) {
                self::$API_URL = 'https://demo.transfluent.com/v2/';
                self::$API_V3_URL = 'https://demo.transfluent.com/';
                $this->_sandbox_mode = true;
            } else {
                self::$API_URL = 'https://transfluent.com/v2/';
                self::$API_V3_URL = 'https://transfluent.com/';
            }
        }

        private function UriFromMethod($method_name, $api_version) {
            return strtolower(preg_replace("/(?!^)([A-Z]{1}[a-z0-9]{1,})/", '/$1', $method_name)) . ($api_version == 'v3' ? '' : '/');
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

        /**
         * To avoid storing account credentials, generate / request an API token and use SetToken() to use it
         *
         * @param $token
         */
        public function SetToken($token) {
            $this->token = $token;
        }

        public function GetToken() {
            if (is_null($this->token)) {
                $this->Authenticate();
            }
            return $this->token;
        }

        private function Authenticate() {
            $response = $this->Request(__FUNCTION__, 'POST', array('email' => $this->email, 'password' => $this->password), 'v3');
            if (!$response) {
                throw new \Exception('Could not authenticate with API!');
            }
            $this->token = $response;
        }

        /**
         * @param $email
         * @param bool $accept_terms_of_service
         * @return string - An API token to access the newly created account
         * @throws \Exception
         */
        public function CreateAccount($email, $accept_terms_of_service = false) {
            if (!$accept_terms_of_service) {
                throw new \Exception('You must accept terms of service to create an account');
            }
            $response = $this->Request(__FUNCTION__, 'POST', array('email' => $email, 'terms' => 'ok'));
            if (!$response) {
                throw new \Exception('Could not create an account!');
            }
            return $response['token'];
        }

        private function Request($method_name, $method = self::HTTP_GET, $payload = array(), $api_version = 'v2') {
            switch ($api_version) {
                case 'v2':
                    $api_url = self::$API_URL;
                    break;
                case 'v3':
                    $api_url = self::$API_V3_URL;
                    break;
                default:
                    throw new \Exception('API version must be defined!');
            }
            $uri = $this->UriFromMethod($method_name, $api_version);

            $curl_handle = curl_init($api_url . $uri);
            if (!$curl_handle) {
                throw new \Exception('Could not initialize cURL!');
            }
            switch (strtoupper($method)) {
                case self::HTTP_GET:
                    $url = $api_url . $uri . '?';
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
                        $json_payload = json_encode($payload);
                        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $json_payload);
                        curl_setopt($curl_handle, CURLOPT_HTTPHEADER, array(
                                'Content-Type: application/json',
                                'Content-Length: ' . strlen($json_payload))
                        );
                    }
                    break;
                default:
                    throw new \Exception('Unsupported request method.');
            }
            curl_setopt($curl_handle, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
            if ($this->_sandbox_mode) {
                curl_setopt ($curl_handle, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt ($curl_handle, CURLOPT_SSL_VERIFYPEER, 0);
            }
            $response = curl_exec($curl_handle);
            if (!$response) {
                throw new \Exception('Failed to connect with Transfluent\'s API. cURL error: ' . curl_error($curl_handle));
            }
            if ($method_name == 'FileRead') {
                // Note! /file/read/ returns file data
                $http_code = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
                if ($http_code != 200) {
                    throw new \Exception('Could not retrieve the file! Error: ' . $response);
                }
                return $response;
            }
            $response_obj = json_decode($response, true);
            if (!$response_obj) {
                throw new \Exception('Could not parse API\'s response: ' . $response);
            }
            if ($api_version == 'v3') {
                // v3 Response processing
                $http_code = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
                switch ($http_code) {
                    case 200:
                        return $response_obj;
                    case 400:
                    case 401:
                    case 403:
                    case 500:
                    default:
                        if (!isset($response_obj['type'])) {
                            throw new \Exception('API returned unexpected response: ' . $response);
                        }
                        throw new \Exception('API returned an error #' . $response_obj['type'] . ': ' . $response_obj['message'] . '.');
                }
            }
            // v2 Response processing
            if ($response_obj['status'] == 'ERROR') {
                throw new \Exception('API returned an error #' . $response_obj['error']['type'] . ': ' . $response_obj['error']['message'] . '. Error description: ' . @$response_obj['response']);
            }
            if ($response_obj['status'] != 'OK') {
                throw new \Exception('API returned unexpected response: ' . $response);
            }
            return $response_obj['response'];
        }

        /**
         * /languages/ can be called without token&any authentication, we can call Request directly
         *
         * @throws \Exception
         * @return mixed
         */
        public function Languages() {
            return $this->Request(__FUNCTION__, self::HTTP_GET, array(), 'v3');
        }

        /**
         * Retrieve translation status of a file
         *
         * @throws \Exception
         * @param $identifier
         * @param $language
         * @return mixed
         */
        public function FileStatus($identifier, $language) {
            return $this->CallApi(__FUNCTION__, self::HTTP_GET, array('identifier' => $identifier, 'language' => $language));
        }

        /**
         * Retrieve translated file
         *
         * @throws \Exception
         * @param $identifier
         * @param $language
         * @return mixed
         */
        public function FileRead($identifier, $language) {
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

        private function FileSave($identifier, $language, $format, $file_name, $type, $save_translations_only = false) {
            if (!is_file($file_name)) {
                throw new \Exception('File not found!');
            }
            $file_content = base64_encode(file_get_contents($file_name));

            $payload = array('identifier' => $identifier, 'language' => $language, 'format' => $format, 'content' => $file_content, 'type' => $type);
            if ($save_translations_only) {
                $payload['master'] = 'no';
            }
            $response = $this->CallApi(__FUNCTION__, self::HTTP_POST, $payload);
            if (!$response['word_count']) {
                throw new \Exception('Response does not comply expected form!');
            }
            return $response;
        }

        public function SaveMooToolsLocaleFile($identifier, $language, $file, $save_translations_only = false) {
            return $this->FileSave($identifier, $language, 'UTF-8', $file, 'MooTools-locale', $save_translations_only);
        }

        public function SaveIosStringsFile($identifier, $language, $file, $save_translations_only = false) {
            return $this->FileSave($identifier, $language, 'UTF-16', $file, 'iOS-strings', $save_translations_only);
        }

        public function SaveAndroidStringsFile($identifier, $language, $file, $save_translations_only = false) {
            return $this->FileSave($identifier, $language, 'UTF-8', $file, 'Android-strings', $save_translations_only);
        }

        public function SaveAndroidArraysFile($identifier, $language, $file, $save_translations_only = false) {
            return $this->FileSave($identifier, $language, 'UTF-8', $file, 'Android-arrays', $save_translations_only);
        }

        public function SaveJsonFile($identifier, $language, $file, $save_translations_only = false) {
            return $this->FileSave($identifier, $language, 'UTF-8', $file, 'json-file', $save_translations_only);
        }

        public function SaveYamlFile($identifier, $language, $file, $save_translations_only = false) {
            return $this->FileSave($identifier, $language, 'UTF-8', $file, 'YAML-file', $save_translations_only);
        }

        /**
         * @param $identifier - File identifier (e.g. /foobar/foo.xml)
         * @param $language - Language code, e.g. English en-gb
         * @param array $target_languages - Array of language codes to translate file into
         * @param string $comment - Context comment or further information to the translator
         * @param string $callback_url - A callback URL which will receive a GET request when translation is completed
         * @param $level - Translation level
         * @param $callbacks - An array of event callbacks, e.g. array("processing" => "http://example.org/my-processing-callback")
         * @param $file_name - File name to read latest content for the master file. Please note that calling FileSave() is necessary to create a new file, this parameter can be used to update the file before translation.
         * @return array
         * @throws \Exception
         */
        public function FileTranslate($identifier, $language, array $target_languages, $comment = '', $callback_url = '', $level = self::LEVEL_TRANSLATION, $callbacks = null, $file_name = null) {
            if (!is_array($target_languages)) {
                throw new \Exception('Target languages MUST be provided as an array!');
            }
            if (!$language) {
                throw new \Exception('Language id MUST be provided!');
            }

            if (!$level) {
                throw new \Exception('Level MUST be provided!');
            }

            $payload = array('identifier' => $identifier, 'language' => $language, 'target_languages' => json_encode($target_languages), 'comment' => $comment, 'callback_url' => $callback_url, 'level' => $level);
            if (!is_null($callbacks)) {
                $payload['_callbacks'] = $callbacks;
            }
            if ($file_name && is_file($file_name)) {
                $payload['content'] = base64_encode(file_get_contents($file_name));
            }
            $response = $this->CallApi(__FUNCTION__, 'POST', $payload);
            if (isset($payload['_callbacks']['processing'])) {
                if (!mb_substr_count($response, 'processing callback will be sent to')) {
                    throw new \Exception('Response does not comply expected form!');
                }
                return $response;
            }
            if (!$response['word_count']) {
                throw new \Exception('Response does not comply expected form!');
            }
            return $response;
        }

        /**
         * Save one or more texts (identified by a key)
         *
         * @param string $group_id
         * @param $language_code
         * @param $texts
         * @return mixed
         * @throws \Exception
         */
        public function Texts($group_id = '', $language_code, $texts) {
            if (!is_array($texts) || empty($texts)) {
                throw new \Exception('Texts MUST be provided as key-value array!');
            }
            foreach ($texts AS $key => $value) {
                if (is_null($value) || is_array($value)) {
                    throw new \Exception('Texts MUST be provided as key-value array!');
                }
            }
            $response = $this->CallApi(__FUNCTION__, 'POST',
                array(
                    'group_id' => $group_id,
                    'language' => $language_code,
                    'texts' => $texts
                )
            );
            return $response;
        }

        public function TextsTranslate($group_id = '', $language_code, $text_ids, $target_languages, $level = self::LEVEL_TRANSLATION, $comment = '', $callback_url = null) {
            if (!is_array($text_ids) || empty($text_ids)) {
                throw new \Exception('Text ids to translate MUST be provided!');
            }
            foreach ($text_ids AS $data) {
                if (!isset($data['id'])) {
                    throw new \Exception('Text ids to translate MUST be provided!');
                }
            }
            if (!is_array($target_languages)) {
                throw new \Exception('Always provide target languages as an array!');
            }
            $response = $this->CallApi(__FUNCTION__, 'POST',
                array(
                    'group_id' => $group_id,
                    'source_language' => $language_code,
                    'texts' => $text_ids,
                    'level' => $level,
                    'target_languages' => $target_languages,
                    'comment' => $comment,
                    'callback_url' => $callback_url
                )
            );
            return $response;
        }

        /**
         * Retrieve translated text
         *
         * @throws \Exception
         * @param $text_id
         * @param $language_code
         * @param $group_id
         * @return mixed
         */
        public function Text($text_id, $language_code, $group_id = null) {
            return $this->CallApi(__FUNCTION__, self::HTTP_GET, array('text_id' => $text_id, 'language' => $language_code, 'group_id' => $group_id));
        }

        /**
         * Retrieve translation status of a text
         *
         * @throws \Exception
         * @param $text_id
         * @param $language_code
         * @param $group_id
         * @return mixed
         */
        public function TextStatus($text_id, $language_code, $group_id = null) {
            return $this->CallApi(__FUNCTION__, self::HTTP_GET, array('text_id' => $text_id, 'language' => $language_code, 'group_id' => $group_id));
        }
    }
}
