<?php

namespace ethercreative\sendgrid;

class Mailer
{
	public $key, $from;

	private $_connection, $_app, $_version;

	private $defaults = array(
		'to' => array(),
		'from' => null,
		'cc' => array(),
		'bcc' => array(),
		'subject' => null,
		'layout' => null,
		'text' => null,
		'html' => null,
		'category' => null,
		'categories' => array(),
		'data' => array(),
		'headers' => [
			'X-Sent-Using' => 'SendGrid-API',
			'X-Transport' => 'web',
		],
	);

	public function __construct($key = null, $from = null)
	{
		if ($key) $this->key = $key;
		if ($from) $this->from = $from;

		if(!empty(\Yii::$app))
		{
			$this->_app = \Yii::$app;
			$this->_version = 2;
		}
		else
		{
			$this->_app = \Yii::app();
			$this->_version = 1;
		}

		return $this;
	}

	private function getConnection()
	{
		$this->_connection = new \SendGrid($this->getKey());

		return $this->_connection;
	}

	private function getKey()
	{
		if ($this->key)
			$key = $this->key;
		elseif (!empty($this->_app->params['sendgrid']['key']))
			$key = $this->_app->params['sendgrid']['key'];
		else
		{
			$message = 'API key required';
			if ($this->_version == 2)
				throw new \yii\base\InvalidConfigException($message);
			else
				throw new \CHttpException(400, $message);
		}

		return $key;
	}

	private function getFrom($options)
	{
		if (!empty($options['from']))
			$from = $options['from'];
		elseif ($this->from)
			$from = $this->from;
		elseif (!empty($this->_app->params['sendgrid']['from']))
			$from = $this->_app->params['sendgrid']['from'];
		else
		{
			$message = 'From address required';
			if ($this->_version == 2)
				throw new \yii\base\InvalidConfigException($message);
			else
				throw new \CHttpException(400, $message);
		}

		return $from;
	}

	public function send(array $options)
	{
		$connection = $this->getConnection();
		$email = new \SendGrid\Email;

		$options = array_replace_recursive($this->defaults, $options);

		foreach ((array) $options['to'] as $to)
			$email->addTo($to);

		foreach ((array) $options['cc'] as $cc)
			$email->addCc($cc);

		foreach ((array) $options['bcc'] as $bcc)
			$email->addBcc($bcc);

		$email->setSubject($options['subject']);

		$email->setFrom($this->getFrom($options));

		if (!empty($options['category']))
			$email->setCategory($options['category']);

		if (!empty($options['categories']))
			$email->setCategories($options['categories']);

		if (!empty($options['text']))
			$email->setText($options['text']);

		if (!empty($options['html']))
		{
			$email->setHtml($options['html']);
		}
		elseif (!empty($options['layout']) && !empty($options['data']))
		{
			$path = $this->_app->basePath . '/mail/sendgrid/' . $options['layout'] . '.html';

			if (!file_exists($path))
			{
				$message = 'Layout "' . $options['layout'] . '" cannot be found in "/mail/sendgrid/".';

				if ($this->_version == 2)
					throw new \yii\web\NotFoundHttpException($message);
				else
					throw new \CHttpException(404, $message);
			}

			$params = [
				'subject' => $options['subject'],
			];

			foreach ($options['data'] as $key => $value)
				$params['{{' . $key . '}}'] = $value;

			$html = file_get_contents($path);
			$html = strtr($html, $params);

			$email->setHtml($html);
		}

		return $response = $connection->send($email);
	}

	public function unsubscribe($email)
	{
		$connection = $this->getConnection();
		return $connection->asm_suppressions->post(1, $email);
	}
}