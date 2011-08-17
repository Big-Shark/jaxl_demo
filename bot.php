<?php

require_once 'JAXL/core/jaxl.class.php';

class Bot {

	protected $jaxl = NULL;
	
	protected $admin = NULL;//JID

	protected $xep = array(
		'JAXL0115', // Entity Capabilities
		'JAXL0092', // Software Version
		'JAXL0199', // XMPP Ping
		'JAXL0203', // Delayed Delivery
		'JAXL0202' // Entity Time
	);

	//init
	public function __construct() {
		$this->jaxl = new JAXL($this->config());
		// Include required XEP's
		$this->jaxl->requires($this->xep);

		// Add callbacks on various event handlers
		$this->jaxl->addPlugin('jaxl_post_auth', array($this, 'post_auth'));
		$this->jaxl->addPlugin('jaxl_get_message', array($this, 'get_message'));
		$this->jaxl->addPlugin('jaxl_get_presence', array($this, 'get_presence'));
		$this->jaxl->addPlugin('jaxl_post_roster_update', array($this, 'post_roster_update'));
		$this->jaxl->addPlugin('jaxl_post_subscription_request', array($this, 'post_subscription_request'));
		$this->jaxl->addPlugin('jaxl_post_subscription_accept', array($this, 'post_subscription_accept'));
		$this->jaxl->addPlugin('jaxl_get_id', array($this, 'get_id'));

		// Fire start Jaxl core
		$this->jaxl->startCore("stream");

	}

	//вызывается после авторизации бота на сервере
	function post_auth($payload, $jaxl) {
		$this->jaxl->sendMessage($this->admin, 'Hello');
		$this->jaxl->setStatus(false, false, false, true);
		$this->jaxl->discoItems($this->jaxl->domain, array($this, 'handle_disco_items'));
		$this->jaxl->getRosterList();
	}

	//хз что делает
	function handle_disco_items($payload, $jaxl) {
		if(!is_array($payload['queryItemJid']))
			return $payload;

		$items = array_unique($payload['queryItemJid']); 
		foreach($items as $item)
			$this->jaxl->discoInfo($item, array($this, 'handle_disco_info'));
	}

	//хз что делает
	function handle_disco_info($payload, $jaxl) {
		// print_r($payload);
	}

	//хз что делает
	function post_roster_update($payload, $jaxl) {
		// Use $jaxl->roster which holds retrived roster list
		// print_r($jaxl->roster);

		// set echobot status
		$this->jaxl->setStatus(false, false, false, true);
	}

	//данныя функция вызывается при получении ботом сообшения
	function get_message($payloads, $jaxl) {
		$time = 0;
		foreach($payloads as $payload) {
			if( ! isset($payload['offline']) or $payload['offline'] != JAXL0203::$ns) {
				if(strlen($payload['body']) > 0) {
					
					//дальше идет немного сложный код который вкратце делает следуюшие:
					//Определяем что за запрос к нам пришел, разбираем его по пробелам и первое слова из запроса
					//подставляем в строчку которая определит какую функцию вызвать допусти есть мы пришлем боту тест status он вызовет функцию action_status
					//все остальные слова он разобьет по пробелам и передаст в функцию как параметры, в последний параметр передастся строка до конца.
					$body = trim($payload['body']);
					if(strpos($body, ' ') === FALSE){
						$method_name = 'action_'.$body;
					}else{
						$method_name = 'action_'.substr($body, 0, strpos($body, ' '));
					}

					if(method_exists($this, $method_name)){
						$method = new ReflectionMethod($this, $method_name);
						$params = explode(' ', $body, count($method->getParameters()));
						$params[0] = $payload;
						call_user_func_array(array($this, $method_name), $params);
					}
				}
			}
		}
	}
	
	//незнаю что делает
	function get_presence($payloads, $jaxl) { 
		foreach($payloads as $payload) {
			//$jaxl->sendMessage($this->admin, print_r($payload, true));
		}
	}
	
	//Запрос на добовления
	function post_subscription_request($payload, $jaxl) {
		$this->jaxl->log("Subscription request sent to ".$payload['from']);
	}
	
	//Подтверждения
	function post_subscription_accept($payload, $jaxl) {
		$this->jaxl->log("Subscription accepted by ".$payload['from']);
	}
	
	//хрень какаято
	function get_id($payload, $jaxl) {
		return $payload;
	}
	
	//команда bye
	function action_bye($payload){
		$this->jaxl->sendMessage($payload['from'], 'Bye!');
		$this->jaxl->shutdown();
	}
	
	//команда subscribe
	function action_subscribe($payload, $from = NULL){
		if( ! isset($from))
			$from = $payload['from'];

		$this->jaxl->subscribe($from);
		$this->jaxl->subscribed($from);
	}
	
	//команда pid
	function action_pid($payload){
		$this->jaxl->sendMessage($payload['from'], 'pid: '.$this->jaxl->pid.PHP_EOL.'pid path: '.$this->jaxl->pidPath);
	}
	
	//команда status
	function action_status($payload){
		$text = 'Время отправления: '.date('r').PHP_EOL.
			'Сожрал памяти: '.memory_get_usage();
		$this->jaxl->sendMessage((isset($payload['from'])?$payload['from']:$this->admin), $text);
		//Так как пыха течет переодически, чтобы бот не вис мы его перезагружаем как только он сжирает 20 мб памяти.
		if(memory_get_usage() > 20971520){ //20mb
			$this->jaxl->sendMessage($this->admin, 'Restart.');
			$this->jaxl->shutdown();
		}
	}
	
	//команда send
	function action_send($payload, $to, $message){
		$this->jaxl->sendMessage($to, $message);
	}
	
	//возврашает конфиг
	function config(){
		return array();
	}

	//дублируем send_message
	function send_message($jid, $message){
		return $this->jaxl->sendMessage($jid, $message);
	}
	
	//возврашает чистый jid
	static function jid($jid){
		return substr($jid, 0, strpos($jid, '/'));
	}

} // End Bot

class Test_Bot extends Bot {

	protected  $admin = '';//JID


	function config(){
		return array(
			'user'=>'',
			'pass'=>'',
			'host'=>'jabber.org',
			'domain'=>'jabber.org',
			'authType'=>'PLAIN',
			'logPath'=>'test.log',
			'pidPath'=>'test.pid',
			'autoSubscribe'=>true,
			'pingInterval'=>60,
			'logLevel'=>4,
		);
	}

	function post_auth($payload, $jaxl) {
		JAXLCron::add(array($this, 'status'), 300); //Вешаем ивент на выполнение функции статус каждый 300мс
		parent::post_auth($payload, $jaxl);
	}

	function status($jaxl = NULL){
		$this->action_status(array());
	}
} // End Test Bot

new Test_Bot();