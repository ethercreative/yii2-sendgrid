<?php

namespace ethercreative\sendgrid;

use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\Json;

use \SendGrid;
use \SendGrid\Email;

class Mailer
{
	public $key;

	private $defaults = [
		'to' => [],
		'from' => null,
		'subject' => null,
		'layout' => null,
		'text' => null,
		'html' => null,
		'data' => [],
		'headers' => [
			'X-Sent-Using' => 'SendGrid-API',
			'X-Transport' => 'web',
		],
	];

	public function send(array $options)
	{
		if ($this->key)
			$key = $this->key;
		elseif (!empty(\Yii::$app->params['sendgrid']['key']))
			$key = \Yii::$app->params['sendgrid']['key'];
		else
			throw new InvalidConfigException('API key required');

		$sendgrid = new SendGrid($key);
		$email = new Email;

		$options = array_replace_recursive($this->defaults, $options);

		foreach ((array) $options['to'] as $to)
			$email->addTo($to);

		$email->setSubject($options['subject']);

		if (!empty($options['from']))
			$email->setFrom($options['from']);
		else
			$email->setFrom(\Yii::$app->params['sendgrid']['from']);

		if (!empty($options['text']))
			$email->setText($options['text']);

		if (!empty($options['html']))
		{
			$email->setHtml($options['html']);
		}
		elseif (!empty($options['layout']) && !empty($options['data']))
		{
			$path = \Yii::$app->basePath . '/mail/sendgrid/' . $options['layout'] . '.html';

			if (!file_exists($path))
				throw new \yii\web\NotFoundHttpException('Layout "' . $options['layout'] . '" cannot be found in "/mail/sendgrid/".');

			$params = [
				'subject' => $options['subject'],
			];

			foreach ($options['data'] as $key => $value)
				$params['{{' . $key . '}}'] = $value;

			$html = file_get_contents($path);
			$html = strtr($html, $params);

			$email->setHtml($html);
		}

		return $response = $sendgrid->send($email);
	}
}