Transfluent Backend API client
==============================

  * Version: 1.0
  * Requires: PHP 5.3 or newer, cURL-extension for PHP
  * API documentation: http://www.transfluent.com/backend-api/

Example how to retrieve languages:
```php
<?php
$client = new Transfluent\BackendClient('example@example.org', 'my-password');
try {
    $languages = $client->Languages();
} catch (Exception $e) {
    error_log($e->getMessage());
}
/**
 * $languages = array("1" => array("name" => "English","code" => "en-gb","id" => 1), .....
 **/
echo "Language id #1 is " . $languages[0][1]['name'] . PHP_EOL;
```

### About authentication tokens ###
Either specify email and password on object construct or retrieve a token, save it somewhere safe and use SetToken()-method to set the token. Latter method is recommended if your code constantly creates new instances of backend client.

