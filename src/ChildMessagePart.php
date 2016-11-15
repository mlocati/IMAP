<?php

namespace MLocati\IMAP;

/**
 * Represents a child part of a message.
 */
class ChildMessagePart extends MessagePart
{
    /**
     * The root message part.
     *
     * @var RootMessagePart
     */
    protected $rootPart;

    /**
     * Initializes the instance.
     *
     * @param Message|MessagePart $parent The message (if this is the root part) or the parent message part (if this is a sub-part)
     * @param stdClass $part The part info retrieved by imap_fetchstructure
     * @param int[] $bodyPath The path to the body of this part
     */
    public function __construct(MessagePart $parentPart, $part, array $bodyPath)
    {
        unset($this->allParts);
        if ($parentPart instanceof RootMessagePart) {
            $this->rootPart = $parentPart;
        } else {
            $this->rootPart = $parentPart->rootPart;
        }
        $this->rootPart->allParts[] = $this;
        $this->parentPart = $parentPart;
        $this->initialize($parentPart->message, $part, $bodyPath);
    }

    /**
     * {@inheritdoc}
     *
     * @see MessagePart::getAllParts()
     */
    public function getAllParts()
    {
        return $this->rootPart->allParts;
    }
}
