<?php
 /*
  * This file is part of evQueue
  * 
  * evQueue is free software: you can redistribute it and/or modify
  * it under the terms of the GNU General Public License as published by
  * the Free Software Foundation, either version 3 of the License, or
  * (at your option) any later version.
  * 
  * evQueue is distributed in the hope that it will be useful,
  * but WITHOUT ANY WARRANTY; without even the implied warranty of
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  * GNU General Public License for more details.
  *
  * You should have received a copy of the GNU General Public License
  * along with evQueue. If not, see <http://www.gnu.org/licenses/>.
  * 
  * Authors: Nicolas Jean, Christophe Marti 
  */

require_once "lib/Logger.php";


class WorkflowInstance {
	private $node_name;
	private $evqueue_ip;
	private $evqueue_port;
	
	
	public function __construct($node_name) {
		require 'conf/queueing.php';
		if (!isset($QUEUEING[$node_name]))
			Logger::GetInstance()->Log(LOG_ERR,'WorkflowInstance',"Unknown evqueue node '$node_name'");
		
		$this->node_name = $node_name;
		list($this->evqueue_ip,$this->evqueue_port) = explode(':', $QUEUEING[$node_name]);
	}
	
	/**
	 * 
	 * @param type $workflow_name
	 * @param array $parameters (<string> => </string>)
	 * @param type $mode 'synchronous'/'asynchronous'
	 * @return boolean
	 */
	public function LaunchWorkflowInstance($workflow_name, $parameters = array(), $mode = 'asynchronous', $user_host = false) {
		$params_xml = '';
		foreach ($parameters as $param => $value) {
			$param = htmlspecialchars($param);
			$value = htmlspecialchars($value);
			$params_xml .= "<parameter name=\"$param\" value=\"$value\" />";
		}
		
		$workflow_name = htmlspecialchars($workflow_name);
		if ($user_host) {
			list($user,$host) = split('@', $user_host);
			$user = htmlspecialchars($user);
			$host = htmlspecialchars($host);
			$user_host = $user ? "user='$user' host='$host'" : "host='$host'";
		} else {
			$user_host = '';
		}
		
		$wf = "<workflow name='$workflow_name' action='info' mode='$mode' $user_host>$params_xml</workflow>\n";
		
		$written = null;
		for ($i=0; $i<10; $i++) {
			$s = @fsockopen($this->evqueue_ip,$this->evqueue_port);
			if ($s === false) {
				Logger::GetInstance()->Log(LOG_WARNING,'WorkflowInstance',"[FAIL-$i] $workflow_name fsockopen failed");
				sleep(2);
				continue;
			}
			
			$written = @fwrite($s,$wf);
			if ($written === strlen($wf))
				break;
			
			Logger::GetInstance()->Log(LOG_WARNING,'WorkflowInstance',"[FAIL-$i] $workflow_name fwrite failed");
			sleep(2);
		}
		
		if ($written !== strlen($wf))
			Logger::GetInstance()->log(LOG_ERR,'WorkflowInstance',"Could not write workflow to evqueue ($this->evqueue_ip:$this->evqueue_port): ".htmlspecialchars($wf));
		
		$xml = fread($s,4096);
		$xml = simplexml_load_string($xml);
		
		if ($xml === false) {
			Logger::GetInstance()->Log(LOG_WARNING,'WorkflowInstance',"[FAIL] $workflow_name fread failed (invalid XML), workflow was:".htmlspecialchars($wf));
			return false;
		}
		
		$workflow_instance_id = (int)$xml->attributes()->{'workflow-instance-id'};
		fclose($s);
		
		if (strlen($workflow_instance_id) > 0) {
			Logger::GetInstance()->Log(LOG_NOTICE,'WorkflowInstance',"[SUCCESS-$i] $workflow_name launched with ID $workflow_instance_id");
			return $workflow_instance_id;
		}
		
		Logger::GetInstance()->Log(LOG_WARNING,'WorkflowInstance',"[FAIL] $workflow_name fread failed (no workflow_id), workflow was:".htmlspecialchars($wf));
		return false;
	}
	
	
	/*
	 * Gives the status of a workflow instance determined by $workflow_instance_id.
	 * @return the status as a string, or false if the workflow instance was not
	 * found.
	 */
	public function GetWorkflowStatus( $workflow_instance_id ) {
		$output = $this->GetWorkflowOutput($workflow_instance_id);
		if(!$output)
			return false;
		
		$xml = simplexml_load_string($output);
		$workflow_instance_status = (string)$xml['status'];
		
		if (in_array($workflow_instance_status, array('EXECUTING','TERMINATED','ABORTED')))
			return $workflow_instance_status;
		
		return false;
	}
	
	
	public function StopWorkflow ($workflow_instance_id) {
		
		$s = @fsockopen($this->evqueue_ip,$this->evqueue_port);
		if ($s === false)
			return false;
		
		fwrite($s,"<workflow id='$workflow_instance_id' action='cancel' />\n");
		$output = stream_get_contents($s);
		fclose($s);
		
		return true;
	}
	
	
	public function GetWorkflowOutput($workflow_instance_id)
	{
		$s = @fsockopen($this->evqueue_ip,$this->evqueue_port);
		if ($s === false)
			return false;
		
		fwrite($s,"<workflow id='$workflow_instance_id'/>\n");
		$output = stream_get_contents($s);
		fclose($s);
		
		if(!$s)
			return false;
		return $output;
	}
	
	
	public function GetStatistics($type)
	{
		$s = @fsockopen($this->evqueue_ip,$this->evqueue_port);
		if ($s === false)
			return false;
		
		fwrite($s,"<statistics type='$type' />\n");
		$output = stream_get_contents($s);
		fclose($s);
		
		if(!$s)
			return false;
		return $output;
	}
	
	public function GetConfiguration()
	{
		$s = @fsockopen($this->evqueue_ip,$this->evqueue_port);
		if ($s === false)
			return false;
		
		fwrite($s,"<status type='configuration' />\n");
		$output = stream_get_contents($s);
		fclose($s);
		
		if(!$s)
			return false;
		return $output;
	}
	
	
	public function ResetStatistics()
	{
		$s = @fsockopen($this->evqueue_ip,$this->evqueue_port);
		if ($s === false)
			return false;
		
		fwrite($s,"<statistics type='global' action='reset' />\n");
		$output = stream_get_contents($s);
		fclose($s);
		
		if(!$s)
			return false;
		return $output;
	}
	
	
	/*
	 * Reloads the lists of tasks, workflows and workflow schedules.
	 */
	public static function ReloadEvqueue () {
		$ret = true;
		require 'conf/queueing.php';
		foreach ($QUEUEING as $node => $conf) {
			$wfi = new WorkflowInstance($node);
			$ret = $ret && $wfi->ask_evqueue("<control action='reload' />\n", "count(/return[@status='OK']) = 1");
		}
		return $ret;
	}
	
	/*
	 * Asks all tasks having a retry schedule, and that failed, to try again immediately.
	 */
	public static function RetryAll ($host=QUEUEING_HOST,$port=QUEUEING_PORT) {
		$wfi = new WorkflowInstance($host,$port);
		return $wfi->ask_evqueue("<control action='retry' />\n", "count(/return[@status='OK']) = 1");
	}
	
	
	private function ask_evqueue ($query, $xpath=null) {
		$s = @fsockopen($this->evqueue_ip,$this->evqueue_port);
		if ($s === false)
			return false;
		
		fwrite($s,$query);
		$output = stream_get_contents($s);
		fclose($s);
		
		if(!$s)
			return false;
		
		if (!$xpath)
			return $output;
		
		// dig into the XML and return whatever value matching the XPath expression given
		try {
			$dom = new DOMDocument();
			if (!@$dom->loadXML($output))
				return false;
			
			$xp = new DOMXPath($dom);
			return $xp->evaluate($xpath);
			
		} catch (Exception $e) {
			return false;
		}
	}
	
	
	/*
	 * Immediately stops the execution of a task.
	 */
	public function KillTask ($workflow_instance_id, $task_pid) {
		$s = @fsockopen($this->evqueue_ip,$this->evqueue_port);
		if ($s === false)
			return false;
		
		@fwrite($s,"<workflow id='$workflow_instance_id' action='killtask' pid='$task_pid' />");
		$xml = stream_get_contents($s);
		fclose($s);
		
		return true;
	}
	
	
	public function GetNextExecutionTime() {
		$s = @fsockopen($this->evqueue_ip,$this->evqueue_port);
		if ($s === false)
			return false;
		
		@fwrite($s,'<status type="scheduler" />');
		$xml = stream_get_contents($s);
		fclose($s);
		
		return $xml;  // for now we return the XML directly, since it will be used in the XSL only
	}
	
	
	public function GetRunningWorkflows() {
		$s = @fsockopen($this->evqueue_ip,$this->evqueue_port);
		if ($s === false)
			return false;
		
		@fwrite($s,'<status type="workflows" />');
		$xml = stream_get_contents($s);
		fclose($s);
		
		// Temporary: not necessary any more when evqueue returns the node_name itself.
		$dom = new DOMDocument();
		$dom->loadXML($xml);
		$dom->documentElement->setAttribute('node_name', $this->node_name);
		$xml = $dom->saveXML();
		
		return $xml;  // for now we return the XML directly, since it will be used in the XSL only
	}
	
	/*
	 * Asks evqueue to store a notification binary on its machine.
	 */
	public static function StoreFile ($filename,$data,$host=QUEUEING_HOST,$port=QUEUEING_PORT) {
		$wfi = new WorkflowInstance($host,$port);
		return $wfi->put_or_remove_file('notification','put',$filename,$data);
	}
	
	/*
	 * Asks evqueue to delete a notification binary, previously stored via StoreFile, on its machine.
	 */
	public static function DeleteFile ($filename,$host=QUEUEING_HOST,$port=QUEUEING_PORT) {
		$wfi = new WorkflowInstance($host,$port);
		return $wfi->put_or_remove_file('notification','remove',$filename);
	}
	
	/*
	 * Asks evqueue to store a notification configuration file on its machine.
	 */
	public static function StoreConfFile ($filename,$data,$host=QUEUEING_HOST,$port=QUEUEING_PORT) {
		$wfi = new WorkflowInstance($host,$port);
		return $wfi->put_or_remove_file('notification','putconf',$filename,$data);
	}
	
	/*
	 * Asks evqueue to delete a file configuration, previously stored via StoreConfFile, on its machine.
	 */
	public static function DeleteConfFile ($filename,$host=QUEUEING_HOST,$port=QUEUEING_PORT) {
		$wfi = new WorkflowInstance($host,$port);
		return $wfi->put_or_remove_file('notification','removeconf',$filename);
	}
	
	private function put_or_remove_file ($type,$action,$filename,$data='') {
		$filename = htmlspecialchars($filename);
		$data = base64_encode($data);
		
		$return_xpath = "count(/return[@status='OK']) = 1";
		
		return $this->ask_evqueue(
						"<$type action='$action' filename='$filename' data='$data' />\n",
						$return_xpath
						);
	}
	
	/*
	 * Asks evqueue to return the content of a file configuration, previously stored via StoreConfFile.
	 */
	public static function GetConfFile ($filename,$host=QUEUEING_HOST,$port=QUEUEING_PORT) {
		$filename = htmlspecialchars($filename);
		
		$wfi = new WorkflowInstance($host,$port);
		$data = $wfi->ask_evqueue(
						"<notification action='getconf' filename='$filename' />\n",
						"string(/return[@status='OK']/@data)"
						);
		
		return $data===false ? false : base64_decode($data);
	}
	
	/*
	 * Asks evqueue to return the content of a task file.
	 */
	public static function GetTaskFile ($filename,$host=QUEUEING_HOST,$port=QUEUEING_PORT) {
		$filename = htmlspecialchars($filename);
		
		$wfi = new WorkflowInstance($host,$port);
		$data = $wfi->ask_evqueue(
						"<task action='get' filename='$filename' />\n",
						"string(/return[@status='OK']/@data)"
						);
		
		return $data===false ? false : base64_decode($data);
	}
	
	/*
	 * Asks evqueue to store given task file.
	 */
	public static function PutTaskFile ($filename,$data,$host=QUEUEING_HOST,$port=QUEUEING_PORT) {
		$wfi = new WorkflowInstance($host,$port);
		return $wfi->put_or_remove_file('task','put',$filename,$data);
	}
	
	/*
	 * Asks evqueue to delete a task file.
	 */
	public static function DeleteTaskFile ($filename,$host=QUEUEING_HOST,$port=QUEUEING_PORT) {
		$wfi = new WorkflowInstance($host,$port);
		return $wfi->put_or_remove_file('task','remove',$filename);
	}
	
}

?>