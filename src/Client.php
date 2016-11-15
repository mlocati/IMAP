<?php

namespace MLocati\IMAP;

/**
 * Handles the connection to a user mailbox.
 */
class Client
{
    /**
     * The server connection string.
     *
     * @var string
     */
    protected $server;

    /**
     * The handle of the current connection stream.
     *
     * @var resource|null
     */
    protected $stream = null;

    /**
     * Initializes the connection to the mailbox on the server.
     *
     * @param string $username The user login
     * @param string $password The user password
     * @param string $server The server to connect to (example: 'mail.domain.com')
     * @param array $flags An array of flags (or a string of slash-separated flags). See http://www.php.net/manual/en/function.imap-open.php#refsect1-function.imap-open-parameters
     * @param int|null $port The port to connect to (defaults to 993 for ssl connections and to 143 for non-ssl connections)
     *
     * @throws Exception
     */
    public function __construct($username, $password, $server, $flags = array(), $port = null)
    {
        if (!function_exists('imap_open')) {
            throw new Exception('Missing IMAP PHP extension');
        }
        if (is_array($flags)) {
            $flags = implode('/', $flags);
        } elseif (is_string($flags)) {
            $flags = trim($flags, " \t\n\r\0\x0B/");
        } else {
            $flags = '';
        }
        if ($flags !== '') {
            $flags = '/'.$flags;
        }
        if ($port) {
            if (is_string($port) && is_numeric($port)) {
                $port = (int) $port;
            } elseif (!is_int($port)) {
                $port = null;
            }
        }
        if (!is_int($port) || $port <= 0 || $port >= 65535) {
            $port = (stripos($flags, '/tls') !== false || stripos($flags, '/ssl') !== false) ? 993 : 143;
        }
        $this->server = '{'.$server.':'.$port.'/service=imap'.$flags.'}';
        $this->resetImapMessages();
        $this->stream = @imap_open($this->server.'INBOX', $username, $password, 0, 1);
        if (!$this->stream) {
            $this->stream = null;
            throw new Exception("Failed to connect to inbox of $username on $server:$port");
        }
    }

    /**
     * Close the connection to the server.
     */
    public function __destruct()
    {
        if ($this->stream !== null) {
            @imap_close($this->stream);
            $this->resetImapMessages();
            $this->stream = null;
        }
    }

    /**
     * Retrieve the list of the user's messages.
     *
     * @param bool $includeMarkedAsDeleted true to retrieve the messages marked for deletion too, false to retrieve only normal messages
     *
     * @throws Exception
     *
     * @return Message[]
     */
    public function getMessages($includeMarkedAsDeleted = false)
    {
        $mailboxInfo = $this->check();
        if (!is_object($mailboxInfo) || !isset($mailboxInfo->Nmsgs) || (!is_int($mailboxInfo->Nmsgs))) {
            throw new Exception('Failed to check messages');
        }
        $result = array();
        if ($mailboxInfo->Nmsgs > 0) {
            $overview = $this->fetch_overview('1:'.$mailboxInfo->Nmsgs);
            if (!is_array($overview)) {
                throw new Exception('Failed to retrieve the messages overview');
            }
            foreach ($overview as $messageInfo) {
                if (!is_object($messageInfo)) {
                    throw new Exception('Wrong message overview');
                }
                if ($includeMarkedAsDeleted || !isset($messageInfo->deleted) || empty($messageInfo->deleted)) {
                    $result[] = new Message($this, $messageInfo);
                }
            }
        }

        return $result;
    }

    /**
     * Delete all messages marked for deletion.
     *
     * @throws Exception
     */
    public function expunge()
    {
        if ($this->call('expunge') !== true) {
            throw new Exception('Failed to expunge mailbox');
        }
    }

    /**
     * Reset the list of the current IMAP errors.
     */
    protected function resetImapMessages()
    {
        @imap_errors();
        @imap_alerts();
    }

    /**
     * Wrapper for imap_ functions.
     *
     * @param string $name The name of the method being called (without the 'imap_' prefix)
     * @param array $arguments Enumerated array containing the parameters passed to the $name'ed method
     *
     * @return mixed
     */
    public function __call($name, array $arguments)
    {
        return $this->callIMAP($name, $arguments);
    }

    /**
     * Wrapper for imap_ functions.
     *
     * @param string $name The name of the method being called (without the 'imap_' prefix)
     * @param array $arguments Enumerated array containing the parameters passed to the $name'ed method
     *
     * @return mixed
     */
    protected function callIMAP($name, array $arguments = array())
    {
        $this->resetImapMessages();
        $fn = 'imap_'.$name;
        if (!function_exists($fn)) {
            throw new Exception('Invalid IMAP method: '.$name);
        }
        array_unshift($arguments, $this->stream);

        return @call_user_func_array($fn, $arguments);
    }
}
