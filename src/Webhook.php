<?php

namespace Internet\InterCord;

use Exception;
use GuzzleHttp\Client;
use InvalidArgumentException;
use Internet\InterCord\Internal\Payload;
use GuzzleHttp\Exception\ClientException;

/**
 * The discord webhook class represents a webhook endpoint, and contains functions for sending payloads to it.
 * Class DiscordWebhook
 * @package Internet\InterCord
 */
class Webhook extends Client {
	protected const BASE_URL = 'https://discordapp.com/api/webhooks/';
	/**
	 * Webhook constructor.
	 * @param string $url Either the full webhook URL or the webhook ID (assuming webhook token is not empty)
	 * @param string $token The webhook token.
	 * @throws InvalidArgumentException
	 */
    public function __construct(string $url, string $token = ''){
    	if (!empty($token)){
    		if (is_numeric($url)){
    			$uri = static::BASE_URL . "{$url}/{$token}";
			} else {
    			throw new InvalidArgumentException('Non-numeric ID passed.');
			}
		} else {
    		$uri = $url;
		}

		parent::__construct([
			'base_uri' => $uri,
			'headers' => [
				'Content-Type' => 'application/json'
			]
		]);
    }

	/**
	 * Create a payload object from a set of content.
	 * @param $content
	 * @param string $username
	 * @param string $avatar
	 * @return Payload
	 */
    public static function createPayload($content, string $username = '', string $avatar = ''): Payload {
    	if ($content instanceof Payload){
    		return $content;
		}

		$payload = new Payload();
		$payload->setUsername($username);
		$payload->setAvatar($avatar);

		if (!is_array($content)){$content = [$content];}
		foreach ($content as $argument){
			if ($argument instanceof RichEmbed){
				$payload->addEmbed($argument);
			} elseif (is_scalar($argument)){
				$payload->setContent((string)$argument);
			}
		}

		return $payload;
	}

	/**
	 * Execute a webhook with either an array of strings/embeds, a string, an embed or a payload object.
	 * @param $content array|string|RichEmbed|Payload
	 * @param string $username Username override to set on the payload. Only works if content isn't a payload.
	 * @param string $avatar Avatar override to set. Only works if content isn't a payload.
	 * @param boolean $await If the webhook should await discord's response.
	 * @return Object|void
	 * @throws Exception
	 */
    public function execute($content, string $username = '', string $avatar = '', bool $await = false): ?Object {
		$payload = static::createPayload($content, $username, $avatar);

		$response = $this->post('', [
			'body' => json_encode($payload),
			'query' => ['wait' => $await]
		]);

		$limit = $response->getHeader('X-RateLimit-Limit')[0];
    	$remaining = $response->getHeader('X-RateLimit-Remaining')[0];
		if ((intval($remaining) / intval($limit)) <= 0.5){
			$wait = $response->getHeader('X-RateLimit-Reset-After')[0];
			sleep(intval($wait));
		}

    	if (!$await){
			if ($response->getStatusCode() == 204){
				return null;
			} else {
				throw new Exception("Message failed to send.");
			}
		} else {
			return json_decode($response->getBody()->getContents());
		}
	}
}
