<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

return function (App $app) {
    // Main route: serves the HTML form (index.html)
    $app->get('/', function (Request $request, Response $response) {
        // Serve the existing index.html file
        $response->getBody()->write(file_get_contents(__DIR__ . '/../index.html'));
        return $response;
    });

    // Search route: fetches results and returns them in JSON format
    $app->post('/search', function (Request $request, Response $response) {
        // Get search query from the form
        $data = $request->getParsedBody();
        $query = $data['query'] ?? '';

        if (empty($query)) {
            $response->getBody()->write(json_encode(['error' => 'The search query cannot be empty!'], JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            // Step 1: Make a request to Google search
            $client = new Client([
            ]);
            
            $url = "https://www.google.com/search?q=" . urlencode($query); // Use HTTP instead of HTTPS
            $res = $client->request('GET', $url, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                ]
            ]);
            
            $html = $res->getBody()->getContents();

            // Step 2: Parse the HTML to extract search results
            $crawler = new Crawler($html);

            // Filter for organic search results (e.g., titles and links)
            $results = $crawler->filter('a h3')->each(function (Crawler $node, $i) {
                // Use closest() to get the parent <a> tag of the <h3>
                $link = $node->closest('a');
                return [
                    'title' => $node->text(),
                    'link' => $link->attr('href'),
                ];
            });

            // Step 3: Return the results as JSON
            $response->getBody()->write(json_encode([
                'query' => $query,
                'results' => $results,
            ], JSON_PRETTY_PRINT));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Error fetching data: ' . $e->getMessage()], JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });
};
