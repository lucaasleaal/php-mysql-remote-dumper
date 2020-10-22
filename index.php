<?php
function is_connected(){
    $connected = @fsockopen("www.google.com", 80);
                                        //website, port  (try 80 or 443)
    if ($connected){
        $is_conn = true; //action when connected
        fclose($connected);
    }else{
        $is_conn = false; //action in connection failure
    }
    return $is_conn;

}

if (!is_connected()){
	echo 'Não há internet';exit;
}


set_time_limit(1800);
define('HOST','localhost');
define('USER','root');
define('PASS','');
define('DATABASE','mysqlbackup');
define('MYSQLDUMP','C:\wamp64\bin\mysql\mysql8.0.21\bin\mysqldump.exe');
define('DESTINO','D:\Backups\_bancos');
date_default_timezone_set('America/Sao_Paulo');

$teste = shell_exec("tasklist 2>NUL");
if (strpos($teste, 'mysqldump.exe')!==false){
	echo 'O backup ainda está rodando, aguarde.';exit;
}

include "vendor/autoload.php";
$l = mysqlucas::getInstance(HOST,USER,PASS,DATABASE);

$l->mysqli_prepared_query(file_get_contents(dirname(__FILE__).DIRECTORY_SEPARATOR.'database.sql'));

$remoto = $l->mysqli_prepared_query("
	SELECT
		idbanco,
		host,
		user,
		pass,
		db,
		recorrencia,
		maximo
	FROM banco
	WHERE
		(recorrencia='Minuto' and ultimo < NOW() - INTERVAL 1 MINUTE)
	or 	(recorrencia='Hora' and ultimo < NOW() - INTERVAL 1 HOUR)
	or 	(recorrencia='Dia' and date(ultimo) <= curdate() - INTERVAL 1 DAY)
	or 	(recorrencia='Semana' and ultimo < NOW() - INTERVAL 1 WEEK)
	or 	(recorrencia='Mês' and ultimo < NOW() - INTERVAL 1 MONTH)
	LIMIT 1
");

if (empty($remoto)){
	echo 'Os backups estão atualizados.';
	exit;
}

$remoto = $remoto[0];
$remoto['recorrencia'] = str_replace('ê', 'e', $remoto['recorrencia']);
$backupfile = DESTINO.DIRECTORY_SEPARATOR.$remoto['host'].'.'.$remoto['db'].'_'.$remoto['recorrencia'].'_'.date('Y-m-d-H-i-s').'.sql';
$backupfilezip = DESTINO.DIRECTORY_SEPARATOR.$remoto['host'].'.'.$remoto['db'].'_'.$remoto['recorrencia'].'_'.date('Y-m-d-H-i-s').'.7z';
$batfile = DESTINO.DIRECTORY_SEPARATOR.$remoto['host'].'.'.$remoto['db'].'_'.$remoto['recorrencia'].'_'.date('Y-m-d-H-i-s').'.bat';

$files = glob(DESTINO.DIRECTORY_SEPARATOR.$remoto['host'].'.'.$remoto['db'].'_'.$remoto['recorrencia'].'_'.'*.7z' );
$exclude_files = array('.', '..');
if (!in_array($files, $exclude_files)) {
	array_multisort(
		array_map( 'filemtime', $files ),
		SORT_NUMERIC,
		SORT_ASC,
		$files
	);
}
if($remoto['maximo']>0){
	while (count($files)>=$remoto['maximo']) {
		echo 'Removendo backup antigo: '.$files[0].'<br>';
		unlink($files[0]);
		array_shift($files);
	}
}

$dbhost = addslashes($remoto['host']);
$dbuser = addslashes($remoto['user']);
$dbpass = addslashes($remoto['pass']);
$dbname = preg_replace('/[^0-9a-zA-Z$_]/m', '', $remoto['db']);

$comando = "@ECHO OFF";
$comando.= PHP_EOL."SETLOCAL";
$comando.= PHP_EOL.MYSQLDUMP." --column-statistics=0 --skip-lock-tables --quick --single-transaction=TRUE -h $dbhost -u $dbuser ".((!empty($dbpass))?"-p$dbpass ":'')."$dbname > $backupfile";
$comando.= PHP_EOL."\"C:\Program Files\\7-Zip\\7z.exe\" a -t7z -m0=lzma -mx=9 -mfb=64 -md=32m -ms=on $backupfilezip $backupfile -sdel";
$comando.= PHP_EOL.'DEL "%~f0"';

file_put_contents($batfile, $comando);

function LaunchBackgroundProcess($command){
  // Run command Asynchroniously (in a separate thread)
  if(PHP_OS=='WINNT' || PHP_OS=='WIN32' || PHP_OS=='Windows'){
    // Windows
    $command = 'start "" '. $command;
  } else {
    // Linux/UNIX
    $command = $command .' /dev/null &';
  }

  $handle = popen($command, 'r');
  if($handle!==false){
    pclose($handle);
    return true;
  } else {
    return false;
  }
}

LaunchBackgroundProcess('cmd.exe /Q /C '.$batfile);
echo 'Backup novo em: '.$backupfile;
$l->update('banco',array('ultimo'=>'now()'),array('idbanco'=>$remoto['idbanco']),array('ultimo'));
$l->insert('log',array('arquivo'=>$backupfile,'banco_idbanco'=>$remoto['idbanco']));
