<?php

declare(strict_types=1);

namespace App\Command;

use App\Services\Cloudflare;
use App\Utils\Tools;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use UnexpectedValueException;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function filesize;
use function get_current_user;
use function bin2hex;
use function hash_equals;
use function hash_file;
use function is_array;
use function is_file;
use function is_string;
use function json_decode;
use function json_encode;
use function php_uname;
use function preg_match;
use function random_bytes;
use function rename;
use function posix_geteuid;
use function posix_getpwuid;
use function str_contains;
use function str_replace;
use function strtolower;
use function substr;
use function tempnam;
use function time;
use function trim;
use function unlink;
use const BASE_PATH;
use const PHP_EOL;
use const PHP_OS;

final class ClientDownload extends Command
{
    public string $description = '├─=: php xcat ClientDownload - 更新客户端' . PHP_EOL;
    private Client $client;
    private string $basePath = BASE_PATH . '/';
    private array $version;
    private array $releaseMetadata = [];

    /**
     * @throws GuzzleException
     */
    public function boot(): void
    {
        $this->client = new Client();
        $this->version = $this->getLocalVersions();
        $clientsPath = BASE_PATH . '/config/clients.json';

        if (! is_file($clientsPath)) {
            echo 'clients.json 不存在，脚本中止。' . PHP_EOL;
            exit(0);
        }

        if (PHP_OS !== 'WINNT' && ! str_contains(php_uname(), 'Windows NT')) {
            $runningUser = posix_getpwuid(posix_geteuid())['name'];
            $fileOwner = get_current_user();

            if ($runningUser !== $fileOwner) {
                echo '当前用户为 ' . $runningUser . '，与文件所有者 ' . $fileOwner . ' 不符，脚本中止。' . PHP_EOL;
                exit(0);
            }
        }

        $clients = json_decode(file_get_contents($clientsPath), true);

        foreach ($clients['clients'] as $client) {
            try {
                $this->getSoft($client);
            } catch (GuzzleException|UnexpectedValueException $exception) {
                echo '- 获取 ' . ($client['name'] ?? 'unknown client') . ' 发布信息失败：'
                    . $exception->getMessage() . PHP_EOL;
            }
        }
    }

    /**
     * 下载远程文件
     */
    private function getSourceFile(
        string $fileName,
        string $savePath,
        string $url,
        string $expectedSha256
    ): bool
    {
        $targetPath = $savePath . $fileName;
        $temporaryPath = $targetPath . '.part.' . bin2hex(random_bytes(8));

        try {
            if (! file_exists($savePath)) {
                echo '目标文件夹 ' . $savePath . ' 不存在，下載失败。' . PHP_EOL;
                return false;
            }

            echo '- 开始下载 ' . $fileName . '...' . PHP_EOL;
            $options = $this->githubRequestOptions(false);
            $options['sink'] = $temporaryPath;
            $this->client->get($url, $options);
            echo '- 下载 ' . $fileName . ' 成功，正在保存...' . PHP_EOL;

            if (! is_file($temporaryPath) || filesize($temporaryPath) <= 0) {
                if (is_file($temporaryPath)) {
                    unlink($temporaryPath);
                }
                echo '- 保存 ' . $fileName . ' 至 ' . $savePath . ' 失败。' . PHP_EOL;
                return false;
            }

            $actualSha256 = hash_file('sha256', $temporaryPath);
            if ($actualSha256 === false || ! hash_equals(strtolower($expectedSha256), strtolower($actualSha256))) {
                unlink($temporaryPath);
                echo '- ' . $fileName . ' SHA-256 校验失败，保留现有版本。' . PHP_EOL;
                return false;
            }

            if (! rename($temporaryPath, $targetPath)) {
                unlink($temporaryPath);
                echo '- 原子替换 ' . $fileName . ' 失败，保留现有版本。' . PHP_EOL;
                return false;
            }

            echo '- 保存 ' . $fileName . ' 至 ' . $savePath . ' 成功。' . PHP_EOL;
            return true;
        } catch (GuzzleException $e) {
            if (file_exists($temporaryPath)) {
                unlink($temporaryPath);
            }
            echo '- 下载 ' . $fileName . ' 失败...' . PHP_EOL;
            echo $e->getMessage() . PHP_EOL;

            return false;
        }
    }

    /**
     * 获取 GitHub 常规 Release
     *
     * @throws GuzzleException
     */
    private function getLatestReleaseTagName(string $repo): string
    {
        $url = 'https://api.github.com/repos/' . $repo . '/releases/latest';
        $request = $this->client->get($url, $this->githubRequestOptions());
        $release = json_decode($request->getBody()->getContents(), true);
        if (! is_array($release) || ! is_string($release['tag_name'] ?? null)) {
            throw new UnexpectedValueException('GitHub release metadata is invalid.');
        }
        $this->releaseMetadata[$repo] = $release;

        return $release['tag_name'];
    }

    /**
     * 获取 GitHub Pre-Release
     *
     * @throws GuzzleException
     */
    private function getLatestPreReleaseTagName(string $repo): string
    {
        $url = 'https://api.github.com/repos/' . $repo . '/releases';
        $request = $this->client->get($url, $this->githubRequestOptions());
        $releases = json_decode(
            $request->getBody()->getContents(),
            true
        );
        $latest = is_array($releases) ? ($releases[0] ?? null) : null;
        if (! is_array($latest) || ! is_string($latest['tag_name'] ?? null)) {
            throw new UnexpectedValueException('GitHub pre-release metadata is invalid.');
        }
        $this->releaseMetadata[$repo] = $latest;

        return $latest['tag_name'];
    }

    /**
     * 获取本地软体版本库
     *
     * @return array
     */
    private function getLocalVersions(): array
    {
        $fileName = 'LocalClientVersion.json';
        $filePath = BASE_PATH . '/storage/' . $fileName;

        if (! is_file($filePath)) {
            echo '本地软体版本库 LocalClientVersion.json 不存在，创建文件中...' . PHP_EOL;

            $result = file_put_contents(
                $filePath,
                json_encode(
                    [
                        'createTime' => time(),
                    ]
                )
            );

            if (! $result) {
                echo 'LocalClientVersion.json 创建失败，脚本中止。' . PHP_EOL;
                exit(0);
            }
        }

        $fileContent = file_get_contents($filePath);

        if (! Tools::isJson($fileContent)) {
            echo 'LocalClientVersion.json 文件格式异常，脚本中止。' . PHP_EOL;
            exit(0);
        }

        return json_decode($fileContent, true);
    }

    /**
     * 储存本地软体版本库
     *
     * @param array $versions
     */
    private function setLocalVersions(array $versions): bool
    {
        $fileName = 'LocalClientVersion.json';
        $filePath = BASE_PATH . '/storage/' . $fileName;
        $temporaryPath = tempnam(BASE_PATH . '/storage', $fileName . '.');

        if ($temporaryPath === false || file_put_contents($temporaryPath, json_encode($versions)) === false) {
            if (is_string($temporaryPath)) {
                unlink($temporaryPath);
            }

            return false;
        }

        if (! rename($temporaryPath, $filePath)) {
            unlink($temporaryPath);

            return false;
        }

        return true;
    }

    private static function getNames($name, $taskName, $tagName): array|string
    {
        return str_replace(
            [
                '%taskName%',
                '%tagName%',
                '%tagName1%',
            ],
            [
                $taskName,
                $tagName,
                substr($tagName, 1),
            ],
            $name
        );
    }

    /**
     * @param array $task
     *
     * @throws GuzzleException
     */
    private function getSoft(array $task): void
    {
        $savePath = $this->basePath . $task['savePath'];
        echo '====== ' . $task['name'] . ' 开始 ======' . PHP_EOL;

        $tagName = match ($task['tagMethod']) {
            'github_pre_release' => $this->getLatestPreReleaseTagName($task['gitRepo']),
            default => $this->getLatestReleaseTagName($task['gitRepo']),
        };

        if (! isset($this->version[$task['name']])) {
            echo '- 本地不存在 ' . $task['name'] . '，检测到当前最新版本为 ' . $tagName . PHP_EOL;
        } else {
            if ($tagName === $this->version[$task['name']]) {
                echo '- 检测到当前 ' . $task['name'] . ' 最新版本与本地版本一致，跳过此任务。' . PHP_EOL;
                echo '====== ' . $task['name'] . ' 结束 ======' . PHP_EOL;
                return;
            }
            echo '- 检测到当前 ' . $task['name'] . ' 最新版本为 ' .
                $tagName . '，本地最新版本为 ' . $this->version[$task['name']] . PHP_EOL;
        }

        $allSucceeded = true;

        foreach ($task['downloads'] as $download) {
            $fileName = $download['saveName'] !== '' ? $download['saveName'] : $download['sourceName'];
            $fileName = self::getNames($fileName, $task['name'], $tagName);
            $sourceName = trim((string) self::getNames($download['sourceName'], $task['name'], $tagName));
            $filePath = $savePath . $fileName;

            $downloadUrl = 'https://github.com/' . $task['gitRepo'] .
                '/releases/download/' . $tagName . '/' . $sourceName;
            $expectedSha256 = $this->releaseSha256($task['gitRepo'], $sourceName, $download);

            if ($expectedSha256 === null) {
                echo '- GitHub 未提供 ' . $sourceName . ' 的可信 SHA-256，保留现有版本。' . PHP_EOL;
                $allSucceeded = false;
                continue;
            }

            if (! $this->getSourceFile($fileName, $savePath, $downloadUrl, $expectedSha256)) {
                $allSucceeded = false;
                continue;
            }

            if ($_ENV['enable_r2_client_download'] && file_exists($filePath)) {
                Cloudflare::uploadR2($fileName, file_get_contents($filePath));
                unlink($filePath);
            }
        }

        if ($allSucceeded) {
            $this->version[$task['name']] = $tagName;
            if (! $this->setLocalVersions($this->version)) {
                echo '- 本地版本索引更新失败；下次运行会重新校验下载。' . PHP_EOL;
            }
        }

        echo '====== ' . $task['name'] . ' 结束 ======' . PHP_EOL;
    }

    private function githubRequestOptions(bool $authenticated = true): array
    {
        $headers = [
            'Accept' => $authenticated ? 'application/vnd.github+json' : 'application/octet-stream',
            'User-Agent' => 'SSPanel-UIM ClientDownload',
        ];
        $token = trim((string) ($_ENV['github_access_token'] ?? ''));
        if ($authenticated && $token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return [
            'headers' => $headers,
            'connect_timeout' => 10,
            'timeout' => 120,
        ];
    }

    private function releaseSha256(string $repo, string $sourceName, array $download): ?string
    {
        $configured = strtolower(trim((string) ($download['sha256'] ?? '')));
        if (preg_match('/^[a-f0-9]{64}$/', $configured) === 1) {
            return $configured;
        }

        foreach ($this->releaseMetadata[$repo]['assets'] ?? [] as $asset) {
            if (($asset['name'] ?? null) !== $sourceName) {
                continue;
            }

            $digest = strtolower((string) ($asset['digest'] ?? ''));
            if (preg_match('/^sha256:([a-f0-9]{64})$/', $digest, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }
}
