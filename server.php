<?php

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\Server\IpBlackList;



class WS implements MessageComponentInterface {
	
	protected $clients;
	protected $users = array();
	
	public function onOpen(ConnectionInterface $conn)
	{
		$this->clients[$conn->resourceId] = $conn;
		echo "New connection! ({$conn->resourceId})\n";
	}

	public function onMessage(ConnectionInterface $from, $msg)
	{
		
		if(!$msgobj = json_decode($msg)) {
			return;
		}
		
		switch($msgobj->op) {
			
			case 'heromove':
			
			$onlineusers = [
				'op' => 'heromove',
				'user_id' => $msgobj->user_id,
				'x' => $msgobj->x,
				'y' => $msgobj->y,
			];
			
			$onlineusers = json_encode($onlineusers);
			
			foreach ($this->clients as $client) {
				$client->send($onlineusers);
			}
			break;
			
			case 'say':
			
			$onlineusers = [
				'op' => 'say',
				'user_id' => $msgobj->user_id,
				'msg' => $msgobj->msg,
			];
			
			$onlineusers = json_encode($onlineusers);
			
			foreach ($this->clients as $client) {
				$client->send($onlineusers);
			}
			break;
			
			case 'getonlineuser':
			$onusers = [];
			if($this->users) {
				foreach($this->users as $user) {
					$onusers[] = $user['user_id'];
				}
			}
			
			$onlineusers = [
				'op' => 'getonlineuser',
				'users' => $onusers,
			];
			
			$onlineusers = json_encode($onlineusers);
			$from->send($onlineusers);
			break;
			
			case 'reg':
			
			$this->users[] = [
				'user_id' =>$msgobj->user_id,
				'client' => $from,
				'login_at' => date('Y-m-d H:i:s'),
			];
			
			$onusers = [];
			if($this->users) {
				foreach($this->users as $user) {
					$onusers[] = $user['user_id'];
				}
			}
			
			$onlineusers = [
				'op' => 'getonlineuser',
				'users' => $onusers,
			];
			
			$onlineusers = json_encode($onlineusers);

			foreach ($this->clients as $client) {
				$client->send($onlineusers);
			}
				
			
			break;
			case 'sendmsg':
			
			$fromuser = null;
			foreach($this->users as $user) {
				if($user['client'] == $from){
					$fromuser = $user;
					break;
				}
			}
			
			
			$touserid = $msgobj->to_user_id;
			
			$touserclient = null;
			foreach($this->users as $user) {
				if($user['user_id'] == $touserid){
					$touserclient[] = $user;
					break;
				}
			}
			if(!$touserclient){
				return;
			}
			
			foreach($touserclient as $toclient) {
				
				$sendmsg = [
					'op' => 'getmsg',
					'from_user_id' => $fromuser['user_id'],
					'msg' => $msgobj->msg,
					'send_at' => date('Y-m-d H:i:s'),
				];
				
				$sendmsg = json_encode($sendmsg);
				
				$toclient['client']->send($sendmsg);
			}
			
			break;
			
			
		}
	}

	public function onClose(ConnectionInterface $conn)
	{
		 // The connection is closed, remove it, as we can no longer send it messages
		unset($this->clients[$conn->resourceId]);
		
		foreach($this->users as $k=>$user) {
				if($user['client'] == $conn){
					unset($this->users[$k]);
					break;
				}
			}
		echo "Connection {$conn->resourceId} has disconnected\n";
	}

	public function onError(ConnectionInterface $conn, \Exception $e)
	{
		echo "An error has occurred: {$e->getMessage()}\n";
		$conn->close();
	}
};


$blackList = new IpBlackList(
			new HttpServer(
				new WsServer(
					new WS()
				)
			)
		);
 //$blackList->blockAddress('74.125.226.46');

$server = IoServer::factory($blackList, 8080);	

$server->run();