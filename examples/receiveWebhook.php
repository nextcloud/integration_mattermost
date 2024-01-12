<?php
/**
 * Example process to validate a webhook request signature
 */

$requestBody = file_get_contents('php://input');
$secret = '123';

// get the request signature
$headers = getallheaders();
$signature = $headers['X-Webhook-Signature'] ?? '';

// compute the signature
$bodyHash = hash('sha256', $requestBody . $secret);

$content = $requestBody
	. "\n\nReceived signature: " . $signature
	. "\n\nSignature check:    " . $bodyHash;

file_put_contents('/tmp/a', $content);
