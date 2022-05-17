<?php

namespace MLocati\IMAP;

use DateTime;

/**
 * Represents an email message.
 */
class Message
{
    /**
     * The client connection owning this message.
     *
     * @var Client
     *
     * @internal
     */
    protected $client;

    /**
     * The email message subject.
     *
     * @var string
     */
    protected $subject;

    /**
     * The message date/time (if available).
     *
     * @var DateTime|null
     */
    protected $date;

    /**
     * The value of the "From" header.
     *
     * @var string
     */
    protected $from;

    /**
     * The email message ID.
     *
     * @var string
     */
    protected $id;

    /**
     * The internal message number.
     *
     * @var int
     */
    protected $number;

    /**
     * Is this message marked for deletion?
     *
     * @var bool
     */
    protected $deleted;

    /**
     * The root message part.
     *
     * @var RootMessagePart|null
     */
    protected $rootPart;

    /**
     * The whole message source (if already loaded).
     *
     * @var string|null
     */
    protected $source;

    /**
     * The raw message headers.
     *
     * @var string[]|null
     */
    private $rawHeaders;

    /**
     * Initializes the instance.
     *
     * @param Client $client The parent connection to the user's mailbox
     * @param \stdClass $info The message info retrieved by imap_fetch_overview
     *
     * @throws \MLocati\IMAP\Exception
     */
    public function __construct(Client $client, $info)
    {
        $this->client = $client;
        $this->subject = isset($info->subject) ? Convert::mimeEncodedToUTF8($info->subject) : '';
        if (isset($info->udate) && is_int($info->udate) && $info->udate > 0) {
            $this->date = new DateTime();
            $this->date->setTimestamp($info->udate);
        } elseif (isset($info->date) && is_string($info->date) && $info->date !== '') {
            $timestamp = @strtotime($info->date);
            if (is_int($timestamp) && $timestamp > 0) {
                $this->date = new DateTime();
                $this->date->setTimestamp($timestamp);
            } else {
                throw new Exception('Failed to parse date/time '.$info->date);
            }
        }
        $this->from = isset($info->from) ? Convert::mimeEncodedToUTF8($info->from) : '';
        $this->id = isset($info->message_id) ? Convert::mimeEncodedToUTF8($info->message_id) : '';
        $s = (isset($info->msgno) && is_int($info->msgno)) ? $info->msgno : 0;
        if ($s <= 0) {
            throw new Exception('Invalid message number ('.(isset($info->msgno) ? $info->msgno : '').')');
        }
        $this->number = $s;
        $this->deleted = (isset($info->deleted) && (!empty($info->deleted))) ? true : false;
    }

    /**
     * Get the client connection owning this message.
     *
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Get the email message subject.
     *
     * @return string
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * Get the message date/time (if available).
     *
     * @return DateTime|null
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * Get the value of the "From" header.
     *
     * @return string
     */
    public function getFrom()
    {
        return $this->from;
    }

    /**
     * Get the list of the sender email addresses (without name)
     *
     * @throws \MLocati\IMAP\Exception
     *
     * @return string[]
     */
    public function getFromAddresses()
    {
        return $this->getAddresses('from');
    }

    /**
     * Get the value of the "To" header.
     *
     * @return string
     */
    public function getTo()
    {
        $headers = $this->getRawHeaderValues('to', true);

        return $headers === array() ? '' : array_shift($headers);
    }

    /**
     * Get the list of the "To" email addresses (without name)
     *
     * @throws \MLocati\IMAP\Exception
     *
     * @return string[]
     */
    public function getToAddresses()
    {
        return $this->getAddresses('to');
    }

    /**
     * Get the value of the "CC" header.
     *
     * @throws \MLocati\IMAP\Exception
     *
     * @return string
     */
    public function getCc()
    {
        $headers = $this->getRawHeaderValues('cc', true);

        return $headers === array() ? '' : array_shift($headers);
    }

    /**
     * Get the list of the "CC" email addresses (without name)
     *
     * @throws \MLocati\IMAP\Exception
     *
     * @return string[]
     */
    public function getCcAddresses()
    {
        return $this->getAddresses('cc');
    }

    /**
     * Get the value of the "BCC" header.
     *
     * @throws \MLocati\IMAP\Exception
     *
     * @return string
     */
    public function getBcc()
    {
        $headers = $this->getRawHeaderValues('bcc', true);

        return $headers === array() ? '' : array_shift($headers);
    }

    /**
     * Get the list of the "BCC" email addresses (without name)
     *
     * @throws \MLocati\IMAP\Exception
     *
     * @return string[]
     */
    public function getBccAddresses()
    {
        return $this->getAddresses('bcc');
    }

    /**
     * Get the value of the "Reply-To" header.
     *
     * @throws \MLocati\IMAP\Exception
     *
     * @return string
     */
    public function getReplyTo()
    {
        $headers = $this->getRawHeaderValues('reply-to', true);

        return $headers === array() ? '' : array_shift($headers);
    }

    /**
     * Get the list of the "Reply-To" email addresses (without name)
     *
     * @throws \MLocati\IMAP\Exception
     *
     * @return string[]
     */
    public function getReplyToAddresses()
    {
        return $this->getAddresses('reply-to');
    }

    /**
     * Get the email message ID.
     *
     * @return string
     */
    public function getID()
    {
        return $this->id;
    }

    /**
     * Get the internal message number.
     *
     * @return int
     */
    public function getNumber()
    {
        return $this->number;
    }

    /**
     * Is this message marked for deletion?
     *
     * @return bool
     */
    public function isDeleted()
    {
        return $this->deleted;
    }

    /**
     * Get the root message part.
     *
     * @throws \MLocati\IMAP\Exception
     *
     * @return RootMessagePart
     */
    public function getRootPart()
    {
        if ($this->rootPart === null) {
            $structure = $this->client->fetchstructure($this->number);
            if (!is_object($structure)) {
                throw new Exception('Unable to fetch the structure of message #'.$this->number);
            }
            $this->rootPart = new RootMessagePart($this, $structure);
        }

        return $this->rootPart;
    }

    /**
     * Get all the message parts.
     *
     * @throws \MLocati\IMAP\Exception
     *
     * @return MessagePart[]
     */
    public function getAllParts()
    {
        return $this->getRootPart()->getAllParts();
    }

    /**
     * Retrieve the whole message source.
     *
     * @throws \MLocati\IMAP\Exception
     *
     * @return string
     */
    public function getSource()
    {
        if ($this->source === null) {
            $source = $this->client->fetchbody($this->number, '');
            if (!is_string($source) || $source === '') {
                throw new Exception('Unable to fetch the source of message #'.$this->number);
            }
            $this->source = $source;
        }

        return $this->source;
    }

    /**
     * Move this message to a specific folder.
     *
     * @param string $folder level separator must be '/'
     *
     * @throws \MLocati\IMAP\Exception
     */
    public function moveToFolder($folder)
    {
        if (!$this->client->mail_move($this->getNumber(), $folder)) {
            throw new Exception("Failed to move the message to {$folder}");
        }
    }

    /**
     * Mark this message as deleted.
     *
     * @throws \MLocati\IMAP\Exception
     */
    public function delete()
    {
        if ($this->client->delete($this->number) !== true) {
            throw new Exception('Failed to delete message #'.$this->number);
        }
        $this->deleted = true;
    }

    /**
     * Mark this message as not deleted.
     *
     * @throws \MLocati\IMAP\Exception
     */
    public function undelete()
    {
        if ($this->client->undelete($this->number) !== true) {
            throw new Exception('Failed to delete message #'.$this->number);
        }
        $this->deleted = false;
    }

    /**
     * Mark this message as not deleted.
     *
     * @throws \MLocati\IMAP\Exception
     */
    public function restore()
    {
        if ($this->client->undelete($this->number, true) !== true) {
            throw new Exception('Failed to undelete message #'.$this->number);
        }
        $this->deleted = false;
    }

    /**
     * Get the raw message headers.
     *
     * @param bool $forceRefresh
     *
     * @throws \MLocati\IMAP\Exception
     *
     * @return string[]
     */
    protected function getRawHeaders($forceRefresh = false)
    {
        if ($this->rawHeaders === null || $forceRefresh) {
            $rawHeaders = $this->client->fetchheader($this->number);
            if (!$rawHeaders) {
                throw new Exception('Failed to fetch headers of message #'.$this->number);
            }
            $rawHeaders = preg_split('/\r?\n/', $rawHeaders, -1, PREG_SPLIT_NO_EMPTY);
            // https://datatracker.ietf.org/doc/html/rfc5322#section-2.2.3
            for ($index = count($rawHeaders) - 1; $index > 0; $index--) {
                if ($rawHeaders !== '' && ($rawHeaders[$index][0] === ' ' || $rawHeaders[$index][0] === "\t")) {
                    $rawHeaders[$index - 1] .= $rawHeaders[$index];
                    array_splice($rawHeaders, $index, 1);
                }
            }
            $this->rawHeaders = $rawHeaders;
        }

        return $this->rawHeaders;
    }

    /**
     * Get the raw values of a specific header.
     *
     * @param string $field
     */
    protected function getRawHeaderValues($field, $trim = false)
    {
        $result = array();
        $m = null;
        $rxMatch = '/^' . preg_quote($field, '/') . ':(.*)$/i';
        foreach ($this->getRawHeaders() as $rawHeader) {
            if (preg_match($rxMatch, $rawHeader, $m)) {
                $result[] = $trim ? trim($m[1]) : $m[1];
            }
        }

        return $result;
    }

    /**
     * Extract the email addresses from the header info.
     *
     * @param string $field
     *
     * @throws \MLocati\IMAP\Exception
     *
     * @return string[]
     */
    protected function getAddresses($field)
    {
        $result = array();
        foreach ($this->getRawHeaderValues($field, true) as $value) {
            if ($value === '') {
                continue;
            }
            $addresses = imap_rfc822_parse_adrlist($value, '');
            if (!is_array($addresses)) {
                continue;
            }
            foreach ($addresses as $address) {
                if (!is_object($address)) {
                    continue;
                }
                if (!isset($address->mailbox) || !is_string($address->mailbox) || $address->mailbox === '') {
                    continue;
                }
                if (!isset($address->host) || !is_string($address->host) || $address->host === '') {
                    continue;
                }
                $result[] = $address->mailbox . '@' . $address->host;
            }
        }

        return $result;
    }
}
