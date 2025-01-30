<?php
// Config
$jsonFilePath = 'playlists.json';
$lastUpdateFile = 'last_update.txt';
$apiKey = 'YOUR_API_KEY'; // Youtube API Key
$channelId = 'UCxxxxxxxxxxxxxxxxxxxxx'; // Youtube channel ID
$minScore = 60; // Minimum required score for matches

// Check for query in URL
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $playlistTitle = $_GET['search'];
} else {
    header('Content-Type: application/json');
    echo json_encode(['chat' => "Aucun titre de playlist spécifié."]);
    exit;
}

// Check for force update query
$forceUpdate = isset($_GET['forceUpdate']) && $_GET['forceUpdate'] === 'true';

// Check last update date
$lastUpdate = file_exists($lastUpdateFile) ? file_get_contents($lastUpdateFile) : 0;
$updateInterval = 24 * 60 * 60;

// If 24h spend or if 'forceUpdate' is true, update playlist JSON file
if (time() - $lastUpdate > $updateInterval || $forceUpdate) {
    // Youtube API URL for playlists
    $playlistUrl = "https://www.googleapis.com/youtube/v3/playlists?part=snippet&channelId=$channelId&key=$apiKey";

    // GET
    function getPlaylistData($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }

    // Parse pages to get all playlists
    $allPlaylists = [];
    $nextPageToken = null;
    do {
        if ($nextPageToken) {
            $playlistUrl .= "&pageToken=$nextPageToken";
        }
        $playlists = getPlaylistData($playlistUrl);
        $allPlaylists = array_merge($allPlaylists, $playlists['items']);
        $nextPageToken = isset($playlists['nextPageToken']) ? $playlists['nextPageToken'] : null;
    } while ($nextPageToken);

    // Save JSON
    file_put_contents($jsonFilePath, json_encode($allPlaylists));

    // Update Time
    file_put_contents($lastUpdateFile, time());
}

// Read Playlist.json
$allPlaylists = json_decode(file_get_contents($jsonFilePath), true);

// Check for most similar title
function findMostSimilarTitle($searchTitle, $playlists, $minScore = 70) {
    $bestMatch = null;
    $bestMatchScore = 0;

    foreach ($playlists as $playlist) {
        $currentTitle = $playlist['snippet']['title'];

        // Calculate the similarity between the two titles.
        $searchTitle = strtolower(preg_replace('/[^a-z0-9\s]/', '', $searchTitle));
        $currentTitle = strtolower(preg_replace('/[^a-z0-9\s]/', '', $currentTitle));
        similar_text($searchTitle, $currentTitle, $similarity);

        // If the similarity score is better than the previous one and exceeds the required threshold
        if ($similarity > $bestMatchScore && $similarity >= $minScore) {
            $bestMatchScore = $similarity;
            $bestMatch = $playlist;
        }
    }

    return $bestMatch;
}

// Find the most similar playlist.
$bestMatch = findMostSimilarTitle($playlistTitle, $allPlaylists, $minScore);

// Return the result in JSON format.
header('Content-Type: application/json');
if ($bestMatch) {
    $playlistId = $bestMatch['id'];
    $foundTitle = $bestMatch['snippet']['title'];
    $playlistLink = "https://www.youtube.com/playlist?list=$playlistId";
    echo json_encode([
        'chat' => "J'ai trouvé la playlist '$foundTitle' $playlistLink",
        'score' => $minScore,
        'search' => $playlistTitle,
        'response' => $bestMatch
    ]);
} else {
    echo json_encode([
        'chat' => "Aucun titre de playlist ne correspond à votre recherche.",
        'score' => $minScore,
        'search' => $playlistTitle
    ]);
}