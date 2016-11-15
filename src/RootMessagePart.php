<?php

namespace MLocati\IMAP;

/**
 * Represents the root part of a message.
 */
class RootMessagePart extends MessagePart
{
    /**
     * Initializes the instance.
     *
     * @param Message|MessagePart $parent The message (if this is the root part) or the parent message part (if this is a sub-part)
     * @param stdClass $part The part info retrieved by imap_fetchstructure
     * @param int[] $bodyPath The path to the body of this part
     */
    public function __construct(Message $message, $part)
    {
        $this->allParts = array($this);
        $this->parentPart = null;
        $this->initialize($message, $part, array());
    }

    /**
     * {@inheritdoc}
     *
     * @see MessagePart::getAllParts()
     */
    public function getAllParts()
    {
        return $this->allParts;
    }
}
