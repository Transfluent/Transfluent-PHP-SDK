Transfluent Backend API client
==============================

  * Version: 1.12
  * Requires: PHP 5.3 or newer, cURL-extension for PHP
  * API documentation: http://www.transfluent.com/backend-api/

### Quick overview using code examples how to order translations for a resource file ###

Example how to order translations for a resource file:
```php
<?php
$client = new Transfluent\BackendClient('example@example.org', 'my-password');
try {
    $response = $client->SaveIosStringsFile('my-project/Localizable.strings', 1, '/home/john/work/my-project/resources/Localizable.strings');
    echo "The file contains {$response->word_count} words." . PHP_EOL;
    $response = $client->FileTranslate('my-project/Localizable.strings', 1, array(11), 'This is description of My-project etc.', 'http://www.example.org/callback-me.php');
    echo "{$response->word_count} words (for all target languages) were ordered." . PHP_EOL;
} catch (Exception $e) {
    error_log($e->getMessage());
    exit;
}
```

Example how to check translations status for a resource file:
```php
<?php
$client = new Transfluent\BackendClient('example@example.org', 'my-password');
try {
    $is_translated = $client->IsFileComplete('my-project/Localizable.strings', 11);
    if ($is_translated) {
        echo "File is translated completely." . PHP_EOL;
    } else {
        echo "File is not translated (completely). Please call FileStatus to check precise translation progress." . PHP_EOL;
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    exit;
}
```

Example how to retrieve a translated resource file:
```php
<?php
$client = new Transfluent\BackendClient('example@example.org', 'my-password');
try {
    $file_content = $client->FileRead('my-project/Localizable.strings', 11);
    // $file_content contains translated Localizable.strings, e.g. you can save it:
    file_put_contents('/home/john/work/my-project/resources/Localizable-Finnish.strings', $file_content);
} catch (Exception $e) {
    error_log($e->getMessage());
    exit;
}
```

### About authentication tokens ###
Either specify email and password on object construct or retrieve a token, save it somewhere safe and use SetToken()-method to set the token. Latter method is recommended if your code constantly creates new instances of backend client.

### About supported languages ###
Example how to retrieve supported languages:
```php
<?php
$client = new Transfluent\BackendClient('example@example.org', 'my-password');
try {
    $languages = $client->Languages();
} catch (Exception $e) {
    error_log($e->getMessage());
    exit;
}
/**
 * $languages = array("1" => array("name" => "English","code" => "en-gb","id" => 1), .....
 **/
echo "Language id #1 is " . $languages[0][1]['name'] . PHP_EOL;
```
