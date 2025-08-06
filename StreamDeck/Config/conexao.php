<?php

/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */
error_reporting(0);
date_default_timezone_set('America/Sao_Paulo');

CONST LOCAL = '177.107.115.204';
CONST USER = 'CALORY_SITE';
CONST PASS = '@CALORY123@';
CONST PORTA = '30590';
CONST DB = 'calory_site';

$mysqli = new mysqli(LOCAL,USER,PASS,DB, PORTA);
if ($mysqli->connect_error){
    echo "Falha ao Conectar: (".$mysqli->connect_errno.")".$mysqli->connect_error;
}
?>