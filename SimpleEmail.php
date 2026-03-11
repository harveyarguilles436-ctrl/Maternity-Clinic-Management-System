<?php
class SimpleEmail {
    private $host;
    private $port;
    private $username;
    private $password;
    private $fromName;
    
    public $debugLog = ""; // New public property to store logs

    public function __construct($host, $port, $user, $pass, $fromName) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $user;
        $this->password = $pass;
        $this->fromName = $fromName;
    }

    private function log($msg) {
        $this->debugLog .= $msg . "<br>";
        error_log($msg);
    }

    public function send($to, $subject, $body) {
        $this->debugLog = ""; // Reset log
        $this->log("Connecting to $this->host:$this->port...");
        
        // Fix for Localhost SSL Certificate Issues (XAMPP/WAMP)
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        // Use stream_socket_client instead of fsockopen to support context
        $socket = stream_socket_client("tcp://{$this->host}:{$this->port}", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
        
        if (!$socket) {
            $this->log("SMTP Connect Failed: $errstr ($errno)");
            $this->log("<strong>Tip:</strong> If you are on a school/office network, Port 587 might be blocked.");
            return false;
        }

        if(!$this->expect($socket, "220")) return false;

        $this->cmd($socket, "EHLO " . gethostname());
        
        // Upgrade to TLS
        $this->cmd($socket, "STARTTLS");
        
        // Enable crypto with the permissive context
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            $this->log("SMTP TLS Handshake Failed.");
            $this->log("<strong>Troubleshoot:</strong> Make sure <code>extension=openssl</code> is enabled in php.ini");
            return false;
        }

        // Authenticate
        $this->cmd($socket, "EHLO " . gethostname());
        $this->cmd($socket, "AUTH LOGIN");
        $this->cmd($socket, base64_encode($this->username));
        
        // Send Password
        fputs($socket, base64_encode($this->password) . "\r\n");
        $response = $this->read($socket);
        $code = substr($response, 0, 3);
        
        if ($code != '235') {
            $this->log("<span style='color:red'>AUTH FAILED. Server said: $response</span>");
            $this->log("<strong>Common Fix:</strong> You are using your Login Password. Google require an <strong>App Password</strong>.");
            return false;
        }

        // Send Email Data
        $this->cmd($socket, "MAIL FROM: <" . $this->username . ">");
        $this->cmd($socket, "RCPT TO: <" . $to . ">");
        $this->cmd($socket, "DATA");

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . $this->fromName . " <" . $this->username . ">\r\n";
        $headers .= "To: $to\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "Date: " . date("r") . "\r\n";

        $message = $headers . "\r\n" . $body . "\r\n.";
        
        $this->cmd($socket, $message);
        $this->cmd($socket, "QUIT");
        
        fclose($socket);
        return true;
    }

    private function cmd($socket, $command) {
        fputs($socket, $command . "\r\n");
        return $this->read($socket);
    }

    private function read($socket) {
        $response = "";
        while ($str = fgets($socket, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == " ") break;
        }
        return $response;
    }

    private function expect($socket, $code) {
        $res = $this->read($socket);
        if(substr($res, 0, 3) !== $code) {
            $this->log("SMTP Error: Expected $code but got: $res");
            return false;
        }
        return true;
    }
}
?>
