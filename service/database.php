<?php
/*
 * Database connection file
 */

date_default_timezone_set("Europe/Moscow");

const DBUSER = 'root';
const DBPASS = '';
const DBHOST = 'localhost';
const DB = 'tgbot';

$sql = new mysqli(DBHOST, DBUSER, DBPASS, DB) or die("UNABLE TO CONNECT TO DATABASE"); // Todo logging
$sql->set_charset('utf8');
