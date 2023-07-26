<?php
/**
 * Nextcloud - Slack
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @author Anupam Kumar <kyteinsky@gmail.com>
 * @copyright Julien Veyssier 2022
 * @copyright Anupam Kumar 2023
 */

namespace OCA\Slack\Service;

use Exception;
use Throwable;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

use OCA\Slack\AppInfo\Application;

/**
 * Service to make network requests
 */
class NetworkService {

	private IClient $client;

	public function __construct(
    private IConfig $config,
    IClientService $clientService,
		private LoggerInterface $logger,
		private IL10N $l10n
  ) {
    $this->client = $clientService->newClient();
	}

  /**
    * @param string $userId
    * @param string $endPoint
    * @param array $params
    * @param string $method
    * @param bool $jsonResponse
    * @param string $contentType
    * @return array|mixed|resource|string|string[]
    * @throws PreConditionNotMetException
    */
  public function request(string $userId, string $endPoint, array $params = [], string $method = 'GET',
              bool $jsonResponse = true) {
    $accessToken = $this->config->getUserValue($userId, Application::APP_ID, 'token');

    try {
      $url = Application::SLACK_API_URL . $endPoint;
      $options = [
        'headers' => [
          'Authorization'  => 'Bearer ' . $accessToken,
          'Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8',
          'User-Agent'  => Application::INTEGRATION_USER_AGENT,
        ],
      ];

      if (count($params) > 0) {
        if ($method === 'GET') {
          // manage array parameters
          $paramsContent = '';
          foreach ($params as $key => $value) {
            if (is_array($value)) {
              foreach ($value as $oneArrayValue) {
                $paramsContent .= $key . '[]=' . urlencode($oneArrayValue) . '&';
              }
              unset($params[$key]);
            }
          }
          $paramsContent .= http_build_query($params);

          $url .= '?' . $paramsContent;
        } else {
          $options['body'] = $params;
        }
      }

      if ($method === 'GET') {
        $response = $this->client->get($url, $options);
      } else if ($method === 'POST') {
        $response = $this->client->post($url, $options);
      } else if ($method === 'PUT') {
        $response = $this->client->put($url, $options);
      } else if ($method === 'DELETE') {
        $response = $this->client->delete($url, $options);
      } else {
        return ['error' => $this->l10n->t('Bad HTTP method')];
      }
      $body = $response->getBody();
      $respCode = $response->getStatusCode();

      if ($respCode >= 400) {
        return ['error' => $this->l10n->t('Bad credentials')];
      }
      if ($jsonResponse) {
        return json_decode($body, true);
      }
      return $body;
    } catch (ServerException | ClientException $e) {
      $body = $e->getResponse()->getBody();
      $this->logger->warning('Slack API error : ' . $body, ['app' => Application::APP_ID]);
      return ['error' => $e->getMessage()];
    } catch (Exception | Throwable $e) {
      $this->logger->warning('Slack API error', ['exception' => $e, 'app' => Application::APP_ID]);
      return ['error' => $e->getMessage()];
    }
  }
}
