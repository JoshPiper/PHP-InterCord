<?php

namespace Internet\InterCord;

use Exception;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Internet\InterCord\Internal\Payload;
use GuzzleHttp\Exception\ClientException;

/**
 * The discord webhook class represents a webhook endpoint, and contains functions for sending payloads to it.
 * Class DiscordWebhook
 * @package Internet\InterCord
 */
class Webhook extends Client {
	/**
	 * Webhook constructor.
	 * @param string $url Either the full webhook URL or the webhook ID (assuming webhook token is not empty)
	 * @param string $token The webhook token.
	 */
    public function __construct(string $url, string $token = ''){
		parent::__construct([
			'base_uri' => empty($token) ? $url : "https://discordapp.com/api/webhooks/{$url}/{$token}",
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
			if (is_string($argument)){
				$payload->setContent($argument);
			} elseif ($argument instanceof RichEmbed){
				$payload->addEmbed($argument);
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

//		try {
//			$response = $this->post('', [
//				'body' => json_encode($payload),
//				'query' => ['wait' => $await]
//			]);
//		} catch (ClientException $ex){
//			if ($ex->getCode() == 429){
//				// Rate Limiting
//				$err = json_decode($ex->getResponse()->getBody()->getContents());
//				usleep($err->retry_after);
//				$this->execute($payload);
//			} else {
//				throw $ex;
//			}
//		}
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

	public function deliver(Payload $payload){
    	$delivered = false;
    	while (!$delivered){
    		try {
				$response = $this->post('', [
					'body' => json_encode($payload),
					'query' => ['wait' => true]
				]);
				$left = min(array_map('intval', $response->getHeader('X-RateLimit-Remaining')));
				if ($left == 0){
					$time = max(array_map('intval', $response->getHeader('X-RateLimit-Reset-After')));
					sleep($time);
				}
    			$delivered = true;
			} catch (ClientException $client_exception){
				$code = $client_exception->getCode();
    			if ($code == 429){
					$response = $client_exception->getResponse();
					$left = min(array_map('intval', $response->getHeader('X-RateLimit-Remaining')));
					if ($left == 0){
						$time = max(array_map('intval', $response->getHeader('X-RateLimit-Reset-After')));
						sleep($time);
					}
				} elseif ($code == 502){
    				sleep(2);
				} else {
					throw $client_exception;
				}
			}
		}
	}
}
