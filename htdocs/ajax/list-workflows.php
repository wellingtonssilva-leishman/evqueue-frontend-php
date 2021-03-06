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
  * Author: Thibault KUMMER
  */

require_once __DIR__ . '/../includes/inc/auth_check.php';

$xsl = new XSLEngine();
$xsl->AddFragment(["workflows" => $xsl->Api("workflows", "list")]);

// Get git only workflows
if($_SESSION['git_enabled'])
	$xsl->AddFragment(["git-workflows" => $xsl->Api("git", "list_workflows")]);

$xsl->DisplayXHTML('../xsl/ajax/list-workflows.xsl');
?>