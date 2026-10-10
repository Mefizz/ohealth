<?php

declare(strict_types=1);

require __DIR__.'/../scripts/azure-openai-review.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function simulateReview(array $options = []): array
{
    putenv('GITHUB_REPOSITORY='.($options['repository'] ?? 'openhealths/nationHealth'));
    putenv('GITHUB_REF='.($options['ref'] ?? 'refs/heads/main'));
    putenv('PR_NUMBER='.($options['number'] ?? '123'));
    putenv('AZURE_OPENAI_ENDPOINT=https://test-resource.cognitiveservices.azure.com/');
    putenv('AZURE_OPENAI_DEPLOYMENT=gpt-6.1-sol');
    putenv('EXPECTED_HEAD_SHA='.($options['expectedSha'] ?? ''));
    putenv('GITHUB_STEP_SUMMARY');
    $headSha = str_repeat('a', 40);
    $baseSha = str_repeat('b', 40);
    $pr = ['state' => 'open', 'draft' => false, 'changed_files' => 1,
        'head' => ['sha' => $headSha], 'base' => ['sha' => $baseSha, 'ref' => 'main']];
    $pr = array_replace_recursive($pr, $options['pr'] ?? []);
    $files = $options['files'] ?? [['filename' => 'app/Example.php', 'status' => 'modified',
        'patch' => "@@ -10,2 +10,3 @@\n existing\n+new bug\n existing"]];
    $finding = ['priority' => 'P2', 'path' => 'app/Example.php', 'line' => 11,
        'title' => 'Перевірити умову', 'explanation' => 'За конкретної умови повертається неправильний результат.'];
    $modelCalls = 0;
    $prReads = 0;
    $published = [];
    $failure = null;
    $request = static function (string $method, string $url, ?array $payload) use (
        &$modelCalls, &$prReads, &$published, $pr, $options, $files, $finding
    ): array {
        if (str_contains($url, '/chat/completions')) {
            $modelCalls++;
            check($payload['store'] === false && $payload['max_completion_tokens'] === 6000, 'Cost/storage settings are missing');

            return ['choices' => [['finish_reason' => 'stop', 'message' => [
                'content' => json_encode(['findings' => $options['findings'] ?? [$finding]], JSON_THROW_ON_ERROR),
            ]]], 'usage' => ['total_tokens' => 500]];
        }
        if (str_contains($url, '/comments?')) {
            return $options['comments'] ?? [];
        }
        if (str_contains($url, '/files?')) {
            return $files;
        }
        if ($method === 'GET' && str_ends_with($url, '/pulls/123')) {
            $prReads++;

            return $prReads > 1 && $modelCalls > 0 ? array_replace_recursive($pr, $options['currentPr'] ?? []) : $pr;
        }
        if (in_array($method, ['POST', 'PATCH'], true) && str_contains($url, '/comments')) {
            $published[] = ['method' => $method, 'url' => $url, 'body' => $payload['body']];

            return ['id' => 1];
        }
        throw new RuntimeException('Unexpected request in the fixture');
    };
    ob_start();
    try {
        runAzureReview($request);
    } catch (Throwable $error) {
        $failure = $error->getMessage();
    } finally {
        ob_end_clean();
    }

    return compact('published', 'modelCalls', 'failure');
}

$result = simulateReview();
check($result['failure'] === null && count($result['published']) === 1, 'Valid finding was not published');
check(str_contains($result['published'][0]['body'], '#L11'), 'Finding link must cite the reviewed line');

$result = simulateReview(['findings' => [['priority' => 'P2', 'path' => 'app/Other.php', 'line' => 11,
    'title' => 'Invented path', 'explanation' => 'Invented evidence']]]);
check($result['failure'] !== null && $result['published'] === [], 'Hallucinated paths must not be published');

$result = simulateReview(['findings' => [['priority' => 'P2', 'path' => 'app/Example.php', 'line' => 999,
    'title' => 'Wrong line', 'explanation' => 'Outside diff']]]);
check($result['failure'] !== null && $result['published'] === [], 'Lines outside the patch must not be published');

$result = simulateReview(['currentPr' => ['head' => ['sha' => str_repeat('c', 40)]]]);
check($result['modelCalls'] === 1 && $result['published'] === [], 'A changed head must discard stale findings');

$result = simulateReview(['currentPr' => ['draft' => true]]);
check($result['published'] === [], 'A PR converted to draft during review must not receive a comment');

$result = simulateReview(['expectedSha' => str_repeat('c', 40)]);
check($result['modelCalls'] === 0 && $result['published'] === [], 'An outdated queued event must not spend model tokens');

$fingerprint = '<!-- reviewed-head:'.str_repeat('a', 40).' base:'.str_repeat('b', 40).' -->';
$result = simulateReview(['comments' => [['id' => 42, 'user' => ['login' => 'github-actions[bot]'],
    'body' => REVIEW_MARKER."\n".$fingerprint]]]);
check($result['modelCalls'] === 0 && $result['published'] === [], 'Duplicate events must not repeat the model request');

$result = simulateReview(['comments' => [['id' => 42, 'user' => ['login' => 'untrusted-user'],
    'body' => REVIEW_MARKER."\n".$fingerprint]]]);
check($result['modelCalls'] === 1 && $result['published'][0]['method'] === 'POST', 'A spoofed marker must not edit or suppress user comments');

$result = simulateReview(['comments' => [['id' => 42, 'user' => ['login' => 'github-actions[bot]'], 'body' => REVIEW_MARKER]]]);
check($result['published'][0]['method'] === 'PATCH', 'A later head should update the bot comment');

$result = simulateReview(['files' => [['filename' => '.env', 'status' => 'modified', 'patch' => '+SECRET=example']]]);
check($result['modelCalls'] === 0 && str_contains($result['published'][0]['body'], 'Немає доступних'), 'Secret files must not be sent to the model');

$files = [];
for ($index = 0; $index < 25; $index++) {
    $files[] = ['filename' => "app/File$index.php", 'status' => 'added', 'patch' => "@@ -0,0 +1 @@\n+example"];
}
check(count(reviewDiffs($files)) === 25, 'Review must not silently omit files after the twentieth');
check(reviewBatches(reviewDiffs([['filename' => 'app/Huge.php', 'status' => 'added', 'patch' => str_repeat('x', 60001)]]))['skippedLines'] === 1, 'An indivisible fragment must be reported');
check(!str_contains(reviewText('@team [link](https://example.com)', 200), '@team'), 'Model output must not notify mentioned users');

$result = simulateReview(['repository' => 'attacker/fork']);
check($result['failure'] !== null && $result['modelCalls'] === 0, 'Another repository must not call the model');
$result = simulateReview(['ref' => 'refs/heads/untrusted']);
check($result['failure'] !== null && $result['modelCalls'] === 0, 'Another ref must not call the model');
$result = simulateReview(['number' => '123/../456']);
check($result['failure'] !== null && $result['modelCalls'] === 0, 'PR input must not change the API path');

$result = simulateReview(['files' => $files, 'findings' => [], 'pr' => ['changed_files' => 25]]);
check($result['failure'] === null && str_contains($result['published'][0]['body'], '25 із 25'), 'All 25 eligible files must be reviewed and reported');

$prioritized = reviewDiffs([
    ['filename' => 'resources/view.php', 'status' => 'modified', 'patch' => '+view'],
    ['filename' => 'routes/web.php', 'status' => 'modified', 'patch' => '+route'],
    ['filename' => 'app/Action.php', 'status' => 'modified', 'patch' => '+action'],
    ['filename' => 'database/change.php', 'status' => 'modified', 'patch' => '+schema'],
]);
check(array_keys($prioritized) === ['app/Action.php', 'database/change.php', 'routes/web.php', 'resources/view.php'], 'Backend logic must precede frontend paths');

$largeLines = [];
for ($index = 1; $index <= 2000; $index++) {
    $largeLines[] = '+'.str_repeat('a', 40).$index;
}
$largePatch = "@@ -0,0 +1,2000 @@\n".implode("\n", $largeLines);
$parts = reviewPatchParts($largePatch);
check(count($parts['patches']) > 1 && $parts['skippedLines'] === 0, 'Large valid hunks must be split rather than discarded');
$visibleLines = [];
foreach ($parts['patches'] as $part) {
    check(strlen($part) <= 60000, 'A split part exceeded the request size');
    $visibleLines += reviewLines($part);
}
check(count($visibleLines) === 2000 && isset($visibleLines[1], $visibleLines[2000]), 'Splitting must preserve every new-side line number');
$result = simulateReview(['files' => [['filename' => 'app/Large.php', 'status' => 'added', 'patch' => $largePatch]], 'findings' => []]);
check($result['failure'] === null && $result['modelCalls'] > 1 && count($result['published']) === 1, 'Multiple model calls must produce one final comment');
$result = simulateReview(['files' => [['filename' => 'app/Large.php', 'status' => 'added', 'patch' => $largePatch]],
    'findings' => [], 'currentPr' => ['head' => ['sha' => str_repeat('c', 40)]]]);
check($result['modelCalls'] === 1 && $result['published'] === [], 'A head change must stop the remaining batches without publishing partial results');

echo "22 review safety and behavior checks passed.\n";
