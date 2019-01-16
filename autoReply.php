#!/usr/local/bin/php
<?php /* postfix autoreply觸發此script, 寄一封信通知寄件者. */
require_once __DIR__ . '/vendor/autoload.php';

(new Dotenv\Dotenv(__DIR__))->load();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
//use Monolog\Logger;
//use Monolog\Handler\StreamHandler;

if( !isset($argv[1]) || !isset($argv[2]) ) exit;

$sender = $argv[1];
$recipient = $argv[2];

require 'vendor/autoload.php';

//$log = new Logger('autoReply.php');
//$log->pushHandler(new StreamHandler('/home/tiger/mailScript/logs/autoReply.log', Logger::INFO));
//$log->info(sprintf('%s -> %s', $sender, $recipient));

$mail = new PHPMailer(true);                              // Passing `true` enables exceptions
try {
    // get subject, body from postfix.autoReply
    $dsn = 'mysql:unix_socket=/tmp/mysql.sock;dbname=postfix';
    //$dbUsr = 'postfix';
    $dbUsr = $_ENV['DB_USR'];
    //$dbPwd = 'password';
    $dbPwd = $_ENV['DB_PWD'];
    $dbOpt = [ PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8', ];
    $pdo = new PDO($dsn, $dbUsr, $dbPwd, $dbOpt);
    $sql = sprintf('SELECT subject,body FROM autoReply WHERE email="%s"', $recipient);
    $result = $pdo->query($sql);
    $row = $result->fetch(PDO::FETCH_ASSOC);

    $mail->Host = '127.0.0.1';  // Specify main and backup SMTP servers
    $mail->Port = 587;                                    // TCP port to connect to
    $mail->setFrom($recipient); 
    $mail->addAddress($sender);     // Add a recipient

    //Content
    $mail->isHTML(false);                                  // Set email format to HTML
    $mail->Subject = $row['subject'];
    //$mail->Body    = 'This is the HTML message body <b>in bold!</b>';
    $mail->Body = $row['body'];
    //$mail->AltBody = $row['body'];

    $mail->send();
    // echo to maillog
    //echo 'Message has been sent';
    printf('autoReply %s -> %s', $recipient, $sender);
} catch (Exception $e) {
    echo 'autoReply Error: ', $mail->ErrorInfo;
}
