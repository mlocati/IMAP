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
     * Initializes the instance.
     *
     * @param Client $client The parent connection to the user's mailbox
     * @param stdClass $info The message info retrieved by imap_fetch_overview
     *
     * @throws Exception
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
     * Get the value of the "To" header.
     *
     * @return string
     */
    public function getTo()
    {
        return $this->to;
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
     * @throws Exception
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
     * @throws Exception
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
     * @throws Exception
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
     * Mark this message as deleted.
     *
     * @throws Exception
     */
    public function delete()
    {
        if ($this->client->delete($this->number, true) !== true) {
            throw new Exception('Failed to delete message #'.$this->number);
        }
        $this->deleted = true;
    }

    /**
     * Mark this message as not deleted.
     *
     * @throws Exception
     */
    public function restore()
    {
        if ($this->client->undelete($this->number, true) !== true) {
            throw new Exception('Failed to undelete message #'.$this->number);
        }
        $this->deleted = false;
    }
}
