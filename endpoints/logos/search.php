<?php
    if (isset($_GET['search'])) {
        $searchTerm = urlencode($_GET['search'] . " logo");
        $url = "https://www.google.com/search?q={$searchTerm}&tbm=isch&tbs=iar:xw,ift:png";

        // Use cURL to fetch the search results page
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        if ($response === false) {
            echo json_encode(['error' => 'Failed to fetch data from Google.']);
        } else {
            // Parse the HTML response to extract image URLs
            $imageUrls = extractImageUrlsFromGoogle($response);

            // Pass the image URLs to the client
            header('Content-Type: application/json');
            echo json_encode(['imageUrls' => $imageUrls]);
        }

        curl_close($ch);
    } else {
        echo json_encode(['error' => 'Invalid request.']);
    }

    function extractImageUrlsFromGoogle($html) {
        $imageUrls = [];

        $doc = new DOMDocument();
        @$doc->loadHTML($html);

        $imgTags = $doc->getElementsByTagName('img');
        foreach ($imgTags as $imgTag) {
            $src = $imgTag->getAttribute('src');
            if (filter_var($src, FILTER_VALIDATE_URL)) {
                $imageUrls[] = $src;
            }
        }

        return $imageUrls;
    }
    
?>