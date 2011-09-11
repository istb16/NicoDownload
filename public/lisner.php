<?php
	require_once(dirname(__FILE__) . '/common.php');

	$command = $config->PhpPath . ' ' . $config->CmdDir . 'lisner.php > /dev/null &';
	$rtValue = exec($command);
	if (empty($rtValue)) echo 'OK';
	else echo 'NG ' . $rtValue;