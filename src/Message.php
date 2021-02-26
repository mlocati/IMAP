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
     * The value of the "To" header.
     *
     * @var string
     */
    protected $to;

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
    protected $rootPart = null;

    /**
     * The whole message source (if already loaded).
     *
     * @var string|null
     */
    protected $source = null;

    /**
     * The result of imap_headerinfo()
     *
     * @var \stdClass|null
     */
    private $headerInfo = null;

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
        $this->date = null;
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
        $this->to = isset($info->to) ? Convert::mimeEncodedToUTF8($info->to) : '';
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
        return $this->to;
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
        $headerInfo = $this->getHeaderInfo();

        return isset($headerInfo->ccaddress) ? $headerInfo->ccaddress : '';
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
        $headerInfo = $this->getHeaderInfo();

        return isset($headerInfo->bccaddress) ? $headerInfo->bccaddress : '';
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
     * Get the header info.
     *
     * @param bool $forceRefresh
     *
     * @throws \MLocati\IMAP\Exception
     *
     * @return \stdClass
     */
    protected function getHeaderInfo($forceRefresh = false)
    {
        if ($this->headerInfo === null || $forceRefresh) {
            $headerInfo = $this->client->headerinfo($this->number);
            if (!$headerInfo) {
                throw new Exception('Failed to fetch header info of message #'.$this->number);
            }
            $this->headerInfo = $headerInfo;
        }

        return $this->headerInfo;
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
        $headerInfo = $this->getHeaderInfo();
        $value = isset($headerInfo->$field) ? $headerInfo->$field : null;
        if (!is_array($value)) {
            return array();
        }
        $result = array();
        foreach ($value as $item) {
            if(!is_object($item)) {
                continue;
            }
            if (!isset($item->mailbox) || !is_string($item->mailbox)) {
                continue;
            }
            $mailbox = trim($item->mailbox);
            if ($mailbox === '') {
                continue;
            }
            if (!isset($item->host) || !is_string($item->host)) {
                continue;
            }
            $host = trim($item->host);
            if ($host === '') {
                continue;
            }
            $result[] = "{$mailbox}@{$host}";
        }

        return $result;
    }
}
