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

session_start();
session_destroy();

require_once 'inc/logger.php';
require_once 'lib/XSLEngine.php';
require_once 'lib/evQueue.php';

$xsl = new XSLEngine();

if(isset($_POST['engine_host']) && isset($_POST['engine_port']))
{
	$error = false;
	try
	{
		
		try{
			$evqueue = new evQueue("tcp://{$_POST['engine_host']}:{$_POST['engine_port']}");
			$evqueue->Api('ping');
		}
		catch(Exception $e){
			if($e->getCode() != evQueue::ERROR_AUTH_REQUIRED) //it's ok if engine require auth
				throw $e;
		}
		
		$f = @fopen("conf/queueing.php",'w',true);
		if($f===false)
			throw new Exception("Unable to open config file 'conf/queueing.php'");
		
		fputs($f,"<?php\n// This file has been generated by install.php\n\$QUEUEING = [\n\t'tcp://{$_POST['engine_host']}:{$_POST['engine_port']}'\n];\n?>");
		fclose($f);
		
		header("Location: auth.php");
		die();
	}
	catch(Exception $e)
	{
		$xsl->SetParameter('ERROR',$e->getMessage());
	}
}

$xsl->DisplayXHTML('xsl/install.xsl');
?>
