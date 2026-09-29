<?php

/*
  The custom headers a user stored for a webhook or an ntfy topic, as request
  header lines.

  One field, five readers, and they did not agree. sendnotifications.php read
  it as JSON and handed the result straight to cURL; sendcancellationnotifications.php
  read the same field as newline-separated text; the two ntfy blocks decoded it
  as JSON and mapped key => value into "Key: value"; the test button decoded it
  and only used the result when it was non-empty. So the same stored value
  behaved differently depending on which job happened to read it, and two of
  the readers crashed on a value the others merely ignored.

  What the readers agree on once the disagreement is removed: the field holds
  JSON, because that is what the settings page's own test button has always
  assumed and what three of the four jobs did. A JSON object is the shape the
  ntfy blocks documented by example - {"Authorization": "Bearer ..."} - and a
  JSON array of complete header lines is what a value handed straight to cURL
  had to be. Both are accepted here; anything else yields no headers rather
  than ending the run that was reading it.
*/

/**
 * Turns a stored headers value into request header lines.
 *
 * Nothing decodable produces an empty list. That matters more than it looks:
 * on PHP 8 curl_setopt(CURLOPT_HTTPHEADER) with anything but an array is a
 * TypeError, and in a cron job a TypeError is a fatal that ends the whole run
 * - every channel after it and every account after this one included. A
 * notification nobody receives because a header field holds a typo is a bad
 * trade for a feature nobody set.
 *
 * @param mixed $stored The value as it came out of the database or a form.
 * @return string[] Complete "Name: value" lines, possibly empty.
 */
function webhook_custom_headers($stored)
{
    if (!is_string($stored) || trim($stored) === '') {
        return [];
    }

    $decoded = json_decode($stored, true);

    if (!is_array($decoded)) {
        return [];
    }

    $headers = [];

    foreach ($decoded as $key => $value) {
        // Anything that cannot be written as one header line is skipped rather
        // than being stringified into something a push service would reject.
        if (is_array($value) || is_object($value) || $value === null) {
            continue;
        }

        if (is_int($key)) {
            // A list: the entries are already complete header lines.
            $line = trim((string) $value);
        } else {
            $line = trim((string) $key) . ': ' . trim((string) $value);
        }

        if ($line !== '' && strpos($line, ':') !== false) {
            $headers[] = $line;
        }
    }

    return $headers;
}
