<?php

namespace MLocati\IMAP;

use Exception;
use Throwable;

/**
 * Represents a message part.
 */
abstract class MessagePart
{
    /**
     * The parent message.
     *
     * @var Message
     */
    protected $message;

    /**
     * All message parts (kept only for RootMessagePart).
     *
     * @var MessagePart[]
     */
    protected $allParts = null;

    /**
     * The children parts of this message part.
     *
     * @var MessagePart[]
     */
    protected $childParts;

    /**
     * The parent part of this message part (null if and only if it's the root part).
     *
     * @var MessagePart|null
     */
    protected $parentPart;

    /**
     * The body identifier.
     *
     * @var string
     */
    protected $bodyIdentifier;

    /**
     * The ID of the type of this message part.
     * It can be one of the following values:
     * - TYPETEXT (0): unformatted text
     * - TYPEMULTIPART (1): multiple part
     * - TYPEMESSAGE (2): encapsulated message
     * - TYPEAPPLICATION (3): application data
     * - TYPEAUDIO (4): audio
     * - TYPEIMAGE (5): static image (GIF, JPEG, etc.)
     * - TYPEVIDEO (6): video
     * - TYPEMODEL (7): model
     * - TYPEOTHER (8): unknown.
     *
     * @var int
     */
    protected $mainTypeID;

    /**
     * The type of this message part (eg 'text' if this part is a 'text/plain' message part).
     * It can be one of the following values:
     * - 'text'
     * - 'multipart'
     * - 'message'
     * - 'application'
     * - 'audio'
     * - 'image'
     * - 'video'
     * - 'model'
     * - 'other'.
     *
     * @var string
     */
    protected $mainType;

    /**
     * The sub-type of this message part (eg 'plain' if this part is a 'text/plain' message part).
     *
     * @var string
     */
    protected $subType;

    /**
     * The type of this message part (eg 'text/plain').
     *
     * @var string
     */
    protected $fullType;

    /**
     * The name of this message part.
     *
     * @var string
     */
    protected $name;

    /**
     * The original character-set of this message part.
     *
     * @var string
     */
    protected $originalCharset;

    /**
     * The description of this message part.
     *
     * @var string
     */
    protected $description;

    /**
     * The disposition of this message part.
     *
     * @var string
     */
    protected $disposition;

    /**
     * The disposition name of this message part.
     *
     * @var string
     */
    protected $dispositionName;

    /**
     * The original encoding of this message part.
     * It can be one of the following:
     * - ENC7BIT
     * - ENC8BIT
     * - ENCBINARY
     * - ENCBASE64
     * - ENCQUOTEDPRINTABLE
     * - ENCOTHER.
     *
     * @var int
     */
    protected $originalEncoding;

    /**
     * The contents of this message part (may be loaded later).
     *
     * @var string
     */
    protected $contents;

    /**
     * Initializes the instance.
     *
     * @param Message $message The message that contains this part
     * @param \stdClass $part The part info retrieved by imap_fetchstructure
     * @param int[] $bodyPath The path to the body of this part
     */
    protected function initialize(Message $message, $part, array $bodyPath)
    {
        $this->message = $message;
        $this->determineBodyIdentifier($bodyPath);
        $this->determineType($part);
        $this->parsePartParameters($part);
        $this->description = (isset($part->description) && is_string($part->description)) ? trim($part->description) : '';
        $this->disposition = (isset($part->disposition) && is_string($part->disposition)) ? trim($part->disposition) : '';
        $this->parsePartDParameters($part);
        $this->originalEncoding = (isset($part->encoding) && (is_int($part->encoding) || (is_string($part->encoding) && is_numeric($part->encoding)))) ? (int) $part->encoding : null;
        $this->readChildren($part, $bodyPath);
        if ($this->bodyIdentifier === null) {
            $this->contents = '';
        } elseif (isset($part->bytes) && is_int($part->bytes)) {
            $this->contents = ($part->bytes === 0) ? '' : null;
        } elseif (!empty($this->childParts)) {
            $this->contents = '';
        } else {
            $this->contents = null;
        }
    }

    /**
     * Get the message owning this part.
     *
     * @return \MLocati\IMAP\Message
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Get all the parts.
     *
     * @return MessagePart[]
     */
    abstract public function getAllParts();

    /**
     * Get the child parts of this part.
     *
     * @return MessagePart[]
     */
    public function getParts()
    {
        return $this->childParts;
    }

    /**
     * Determine the body identifier.
     *
     * @param int[] $bodyPath The path to the body of this part
     */
    private function determineBodyIdentifier(array $bodyPath)
    {
        if ($this->parentPart !== null && $this->parentPart->mainTypeID !== TYPEMESSAGE) {
            $this->bodyIdentifier = implode('.', $bodyPath);
        } elseif ($this->parentPart === null) {
            $this->bodyIdentifier = '1';
        } else {
            $this->bodyIdentifier = null;
        }
    }

    /**
     * Determine the mainType/subType/fullType fields.
     *
     * @param \stdClass $part The part info retrieved by imap_fetchstructure
     */
    private function determineType($part)
    {
        $this->mainTypeID = TYPEOTHER;
        if (isset($part->type)) {
            if (is_int($part->type)) {
                $this->mainTypeID = $part->type;
            } elseif (is_string($part->type) && is_numeric($part->type)) {
                $this->mainTypeID = (int) $part->type;
            }
        }
        switch ($this->mainTypeID) {
            case TYPETEXT:
                $this->mainType = 'text';
                break;
            case TYPEMULTIPART:
                $this->mainType = 'multipart';
                break;
            case TYPEMESSAGE:
                $this->mainType = 'message';
                break;
            case TYPEAPPLICATION:
                $this->mainType = 'application';
                break;
            case TYPEAUDIO:
                $this->mainType = 'audio';
                break;
            case TYPEIMAGE:
                $this->mainType = 'image';
                break;
            case TYPEVIDEO:
                $this->mainType = 'video';
                break;
            case TYPEMODEL:
                $this->mainType = 'model';
                break;
            case TYPEOTHER:
            default:
                $this->mainType = 'other';
                break;
        }
        $this->subType = (isset($part->subtype) && is_string($part->subtype)) ? strtolower(trim($part->subtype)) : '';
        if ($this->mainType === '' || $this->subType === '') {
            $this->fullType = $this->mainType.$this->subType;
        } else {
            $this->fullType = $this->mainType.'/'.$this->subType;
        }
    }

    /**
     * Determine the name and original character set by reading the part parameters.
     *
     * @param \stdClass $part The part info retrieved by imap_fetchstructure
     */
    private function parsePartParameters($part)
    {
        $this->name = '';
        $this->originalCharset = '';
        if (isset($part->parameters) && is_array($part->parameters)) {
            foreach ($part->parameters as $parameter) {
                if (!is_object($parameter)) {
                    throw new Exception('Invalid message part parameter');
                }
                switch (isset($parameter->attribute) ? strtolower((string) $parameter->attribute) : '') {
                    case 'name':
                        if (($this->name === '') && isset($parameter->value)) {
                            $this->name = Convert::mimeEncodedToUTF8((string) $parameter->value);
                        }
                        break;
                    case 'charset':
                        if (($this->originalCharset === '') && isset($parameter->value)) {
                            $this->originalCharset = strtoupper((string) $parameter->value);
                        }
                        break;
                }
            }
        }
    }

    /**
     * Determine the disposition name by reading the part D parameters.
     *
     * @param \stdClass $part The part info retrieved by imap_fetchstructure
     */
    private function parsePartDParameters($part)
    {
        $this->dispositionName = '';
        if (isset($part->dparameters) && is_array($part->dparameters)) {
            foreach ($part->dparameters as $parameter) {
                switch (isset($parameter->attribute) ? strtolower((string) $parameter->attribute) : '') {
                    case 'filename':
                        if (($this->dispositionName === '') && isset($parameter->value)) {
                            $this->dispositionName = Convert::mimeEncodedToUTF8((string) $parameter->value);
                        }
                        break;
                }
            }
        }
    }

    /**
     * Read the child parts.
     *
     * @param \stdClass $part The part info retrieved by imap_fetchstructure
     * @param int[] $bodyPath The path to the body of this part
     */
    private function readChildren($part, array $bodyPath)
    {
        $this->childParts = array();
        if (isset($part->parts) && is_array($part->parts)) {
            $numParts = count($part->parts);
            foreach ($part->parts as $subPartIndex => $subPart) {
                if (!is_object($subPart)) {
                    throw new Exception('Invalid message part sub-part');
                }
                if ($numParts === 1 && $this->mainTypeID === TYPEMESSAGE) {
                    $this->childParts[] = new ChildMessagePart($this, $subPart, $bodyPath);
                } else {
                    $subBodyPath = $bodyPath;
                    $subBodyPath[] = $subPartIndex + 1;
                    $this->childParts[] = new ChildMessagePart($this, $subPart, $subBodyPath);
                }
            }
        }
    }

    /**
     * Get the main type of this message part (eg 'text' if this part is a 'text/plain' message part).
     * It can be one of the following values:
     * - 'text'
     * - 'multipart'
     * - 'message'
     * - 'application'
     * - 'audio'
     * - 'image'
     * - 'video'
     * - 'model'
     * - 'other'.
     *
     * @return string
     */
    public function getMainType()
    {
        return $this->mainType;
    }

    /**
     * Get the sub-type of this message part (eg 'plain' if this part is a 'text/plain' message part).
     *
     * @return string
     */
    public function getSubType()
    {
        return $this->subType;
    }

    /**
     * Get the full type of this message part (eg 'text/plain').
     *
     * @return string
     */
    public function getFullType()
    {
        return $this->fullType;
    }

    /**
     * The name of this message part.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * The description of this message part.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * The disposition of this message part.
     *
     * @return string
     */
    public function getDisposition()
    {
        return $this->disposition;
    }

    /**
     * The disposition name of this message part.
     *
     * @return string
     */
    public function getDispositionName()
    {
        return $this->dispositionName;
    }

    /**
     * Retrieves the part contents (strings always in utf-8).
     *
     * @return string
     */
    public function getContents()
    {
        if (!isset($this->contents)) {
            $contents = $this->message->getClient()->fetchbody($this->message->getNumber(), $this->bodyIdentifier);
            if (!is_string($contents)) {
                throw Exception('Unable to fetch message part "'.$this->bodyIdentifier.'" of message #'.$this->message->getNumber());
            }
            if ($contents !== '') {
                if (isset($this->originalEncoding)) {
                    $decoded = Convert::decodeEncoding($this->originalEncoding, $contents);
                    if ($decoded === false) {
                        throw new Exception('Unable to decode from encoding '.$this->originalEncoding);
                    }
                    $contents = $decoded;
                    unset($decoded);
                }
                if ($this->originalCharset !== '') {
                    try {
                        $decoded = Convert::charsetToUtf8($this->originalCharset, $contents);
                    } catch (Exception $x) {
                        $decoded = false;
                    } catch (Throwable $x) {
                        $decoded = false;
                    }
                    if ($decoded !== false) {
                        $contents = $decoded;
                    }
                }
            }
            $this->contents = $contents;
        }

        return $this->contents;
    }
}
