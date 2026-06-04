<?php

$url = "http://192.168.0.184/ISAPI/System/deviceInfo";

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
    CURLOPT_USERPWD => "admin:H.246180"
]);

$response = curl_exec($ch);

echo "<pre>";
echo htmlspecialchars($response);
echo "</pre>";

echo curl_error($ch);

curl_close($ch);